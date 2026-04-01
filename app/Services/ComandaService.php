<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Mesa;
use App\Models\Comanda;
use App\Models\Cliente;
use App\Models\ComandaItem;
use App\Models\Produto;
use App\Models\EstoquePerda;
use App\Models\ComandaItemAdicional;
use App\Models\ItemAdicional;
use App\Models\PedidoCozinha;
use Carbon\Carbon;

class ComandaService
{
    /**
     * Servico responsavel pela baixa FIFO e restauracao do estoque por referencia.
     *
     * @var GestaoEstoqueService
     */
    private GestaoEstoqueService $gestaoEstoqueService;

    /**
     * Injeta o servico especializado de estoque na camada de comandas.
     */
    public function __construct(GestaoEstoqueService $gestaoEstoqueService)
    {
        $this->gestaoEstoqueService = $gestaoEstoqueService;
    }

    // 🟢 FOCO OPERACIONAL: Carrega apenas Hoje e as Abertas
    // Inclui listar_itens e buscar_produto — essencial para o modo offline
    // O app persiste essa resposta no comandas_store e usa offline
    public function obter_comandas_do_dia()
    {
        $hoje = Carbon::today();
        return Comanda::with(['buscar_mesa', 'buscar_cliente', 'listar_itens.buscar_produto', 'listar_itens.adicionais.buscar_item_adicional'])
            ->where('status_comanda', 'aberta')
            ->orWhere(function($query) use ($hoje) {
                $query->whereIn('status_comanda', ['fechada', 'cancelada'])
                      ->whereDate('updated_at', $hoje);
            })
            ->orderBy('id', 'desc')
            ->get();
    }

    public function abrir_nova_comanda($dados, $usuario_id)
    {
        $cliente_id = $dados['cliente_id'] ?? null;

        if (!$cliente_id && !empty($dados['nome_cliente'])) {
            $cliente = Cliente::firstOrCreate(['nome_cliente' => $dados['nome_cliente']]);
            $cliente_id = $cliente->id;
        }

        $comanda = Comanda::create([
            'mesa_id' => $dados['mesa_id'],
            'cliente_id' => $cliente_id,
            'usuario_id' => $usuario_id,
            'status_comanda' => 'aberta',
            'valor_total' => 0,
            'tipo_conta' => $dados['tipo_conta'] ?? 'geral',
            'data_hora_abertura' => $dados['data_hora_abertura'] ?? Carbon::now()
        ]);

        Mesa::where('id', $dados['mesa_id'])->update(['status_mesa' => 'ocupada']);
        return $comanda;
    }

    /**
     * Processa uma venda de balcao criando a comanda fechada e baixando estoque em FIFO.
     */
    public function processar_venda_balcao($itens, $desconto, $usuario_id, $forma_pagamento = null)
    {
        $agora = Carbon::now();
        $comanda = Comanda::create([
            'usuario_id' => $usuario_id, 'mesa_id' => null,
            'status_comanda' => 'fechada', 'tipo_conta' => 'geral',
            'valor_total' => 0, 'desconto' => $desconto ?? 0,
            'forma_pagamento' => $forma_pagamento,
            'data_hora_abertura' => $agora, 'data_hora_fechamento' => $agora
        ]);

        $subtotal = $this->lancar_itens_e_baixar_estoque($comanda->id, $itens, $agora);
        $comanda->update(['valor_total' => max(0, $subtotal - ($desconto ?? 0))]);
    }

    /**
     * Adiciona novos itens a uma comanda aberta e baixa o estoque correspondente.
     */
    public function adicionar_itens($comanda_id, $itens)
    {
        $subtotal = $this->lancar_itens_e_baixar_estoque($comanda_id, $itens, Carbon::now());
        $comanda = Comanda::findOrFail($comanda_id);
        $comanda->increment('valor_total', $subtotal);
        return $comanda;
    }

    /**
     * Lanca os itens da comanda e consome o estoque por ordem de entrada dos lotes.
     */
    private function lancar_itens_e_baixar_estoque($comanda_id, $itens, $data_hora)
    {
        $subtotal = 0;
        foreach ($itens as $item) {
            // Custo dos adicionais por unidade do produto
            $custo_adicionais = 0;
            if (!empty($item['adicionais'])) {
                foreach ($item['adicionais'] as $ad) {
                    $custo_adicionais += ($ad['preco_unitario'] ?? 0) * ($ad['quantidade'] ?? 1);
                }
            }

            $subtotal += $item['quantidade'] * ($item['preco_unitario'] + $custo_adicionais);

            $comanda_item = ComandaItem::create([
                'comanda_id' => $comanda_id, 'produto_id' => $item['produto_id'],
                'quantidade' => $item['quantidade'], 'preco_unitario' => $item['preco_unitario'],
                'data_hora_lancamento' => $data_hora
            ]);

            // Persiste os adicionais escolhidos
            if (!empty($item['adicionais'])) {
                foreach ($item['adicionais'] as $ad) {
                    ComandaItemAdicional::create([
                        'comanda_item_id'    => $comanda_item->id,
                        'item_adicional_id'  => $ad['item_adicional_id'],
                        'quantidade'         => $ad['quantidade'] ?? 1,
                        'preco_unitario'     => $ad['preco_unitario'] ?? 0,
                    ]);
                }
            }

            $produto = Produto::query()->lockForUpdate()->findOrFail($item['produto_id']);
            $this->gestaoEstoqueService->consumir_para_comanda_item($produto, (int) $item['quantidade'], $comanda_item->id);

            // Cria pedido de cozinha se o produto requer preparo
            if ($produto->requer_cozinha) {
                $comanda = Comanda::find($comanda_id);
                $adicionais_texto = null;
                if (!empty($item['adicionais'])) {
                    $ids = collect($item['adicionais'])->pluck('item_adicional_id')->filter()->toArray();
                    $nomes = ItemAdicional::whereIn('id', $ids)->pluck('nome', 'id');
                    $adicionais_texto = collect($item['adicionais'])
                        ->map(function ($ad) use ($nomes) {
                            $qtd = $ad['quantidade'] ?? 1;
                            $nome = $nomes[$ad['item_adicional_id']] ?? 'Adicional';
                            return ($qtd > 1 ? "{$qtd}x " : '') . $nome;
                        })
                        ->implode(', ');
                }
                PedidoCozinha::create([
                    'comanda_item_id'  => $comanda_item->id,
                    'comanda_id'       => $comanda_id,
                    'mesa_id'          => $comanda?->mesa_id,
                    'produto_nome'     => $produto->nome_produto,
                    'adicionais_texto' => $adicionais_texto,
                    'quantidade'       => $item['quantidade'],
                    'status'           => 'pendente',
                    'visto_pelo_garcom'=> false,
                ]);
            }
        }
        return $subtotal;
    }

    /**
     * FECHAR PAGAMENTO (Com Verificação de Mesa)
     */
    public function fechar_pagamento($id, $data_hora_fechamento, $desconto, $forma_pagamento = null)
    {
        $comanda = Comanda::findOrFail($id);
        if ($comanda->status_comanda !== 'aberta') throw new \Exception('A comanda não está aberta.');

        $desconto_aplicado = $desconto ?? 0;
        $comanda->update([
            'status_comanda' => 'fechada',
            'data_hora_fechamento' => Carbon::parse($data_hora_fechamento),
            'desconto' => $desconto_aplicado,
            'forma_pagamento' => $forma_pagamento,
            'valor_total' => max(0, $comanda->valor_total - $desconto_aplicado)
        ]);

        // 🟢 SEGURANÇA: Só libera a mesa se for a ÚLTIMA comanda aberta
        if ($comanda->mesa_id) {
            $outras_abertas = Comanda::where('mesa_id', $comanda->mesa_id)
                ->where('status_comanda', 'aberta')
                ->count();

            if ($outras_abertas === 0) {
                Mesa::where('id', $comanda->mesa_id)->update(['status_mesa' => 'livre']);
            }
        }
    }

    /**
     * CANCELAR / CALOTE (Com Verificação de Mesa)
     */
    /**
     * Cancela ou regista calote, com opcional de restaurar o estoque consumido.
     */
    public function cancelar_ou_calote($id, $motivo, $retornar_estoque, $usuario_id)
    {
        $comanda = Comanda::with('listar_itens.buscar_produto')->findOrFail($id);
        
        $comanda->update([
            'status_comanda' => 'cancelada', 
            'motivo_cancelamento' => $motivo, 
            'data_hora_fechamento' => Carbon::now()
        ]);

        // 🟢 SEGURANÇA: Só libera a mesa se for a ÚLTIMA comanda aberta
        if ($comanda->mesa_id) {
            $outras_abertas = Comanda::where('mesa_id', $comanda->mesa_id)
                ->where('status_comanda', 'aberta')
                ->count();

            if ($outras_abertas === 0) {
                Mesa::where('id', $comanda->mesa_id)->update(['status_mesa' => 'livre']);
            }
        }

        foreach ($comanda->listar_itens as $item) {
            if ($retornar_estoque) {
                $this->gestaoEstoqueService->restaurar_por_referencia('comanda_item', $item->id);
            } else {
                $custo_total_perda = \App\Models\ProdutoEstoqueConsumo::query()
                    ->where('referencia_tipo', 'comanda_item')
                    ->where('referencia_id', $item->id)
                    ->selectRaw('COALESCE(SUM(quantidade * custo_unitario_medio), 0) as custo_total_perda')
                    ->value('custo_total_perda');

                EstoquePerda::create([
                    'produto_id' => $item->produto_id, 
                    'usuario_id' => $usuario_id,
                    'quantidade' => $item->quantidade, 
                    'motivo' => "Cancelamento/Calote - CMD #{$comanda->id}",
                    'custo_total_perda' => $custo_total_perda ?: (($item->buscar_produto->preco_custo_medio ?? 0) * $item->quantidade)
                ]);
            }
        }
    }
}
