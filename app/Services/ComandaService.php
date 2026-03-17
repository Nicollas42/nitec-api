<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Mesa;
use App\Models\Comanda;
use App\Models\Cliente;
use App\Models\ComandaItem;
use App\Models\Produto;
use App\Models\EstoquePerda;
use Carbon\Carbon;

class ComandaService
{
    // 🟢 FOCO OPERACIONAL: Carrega apenas Hoje e as Abertas
    // Inclui listar_itens e buscar_produto — essencial para o modo offline
    // O app persiste essa resposta no comandas_store e usa offline
    public function obter_comandas_do_dia()
    {
        $hoje = Carbon::today();
        return Comanda::with(['buscar_mesa', 'buscar_cliente', 'listar_itens.buscar_produto'])
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
        $cliente_id = null;
        if (!empty($dados['nome_cliente'])) {
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

    public function processar_venda_balcao($itens, $desconto, $usuario_id)
    {
        $agora = Carbon::now();
        $comanda = Comanda::create([
            'usuario_id' => $usuario_id, 'mesa_id' => null, 
            'status_comanda' => 'fechada', 'tipo_conta' => 'geral',
            'valor_total' => 0, 'desconto' => $desconto ?? 0, 
            'data_hora_abertura' => $agora, 'data_hora_fechamento' => $agora
        ]);

        $subtotal = $this->lancar_itens_e_baixar_estoque($comanda->id, $itens, $agora);
        $comanda->update(['valor_total' => max(0, $subtotal - ($desconto ?? 0))]);
    }

    public function adicionar_itens($comanda_id, $itens)
    {
        $subtotal = $this->lancar_itens_e_baixar_estoque($comanda_id, $itens, Carbon::now());
        $comanda = Comanda::findOrFail($comanda_id);
        $comanda->increment('valor_total', $subtotal);
        return $comanda;
    }

    private function lancar_itens_e_baixar_estoque($comanda_id, $itens, $data_hora)
    {
        $subtotal = 0;
        foreach ($itens as $item) {
            $subtotal += ($item['quantidade'] * $item['preco_unitario']);
            ComandaItem::create([
                'comanda_id' => $comanda_id, 'produto_id' => $item['produto_id'],
                'quantidade' => $item['quantidade'], 'preco_unitario' => $item['preco_unitario'],
                'data_hora_lancamento' => $data_hora
            ]);
            Produto::where('id', $item['produto_id'])->decrement('estoque_atual', $item['quantidade']);
        }
        return $subtotal;
    }

    /**
     * FECHAR PAGAMENTO (Com Verificação de Mesa)
     */
    public function fechar_pagamento($id, $data_hora_fechamento, $desconto)
    {
        $comanda = Comanda::findOrFail($id);
        if ($comanda->status_comanda !== 'aberta') throw new \Exception('A comanda não está aberta.');

        $desconto_aplicado = $desconto ?? 0;
        $comanda->update([
            'status_comanda' => 'fechada',
            'data_hora_fechamento' => Carbon::parse($data_hora_fechamento),
            'desconto' => $desconto_aplicado,
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
                Produto::where('id', $item->produto_id)->increment('estoque_atual', $item->quantidade);
            } else {
                EstoquePerda::create([
                    'produto_id' => $item->produto_id, 
                    'usuario_id' => $usuario_id,
                    'quantidade' => $item->quantidade, 
                    'motivo' => "Cancelamento/Calote - CMD #{$comanda->id}",
                    'custo_total_perda' => ($item->buscar_produto->preco_custo ?? 0) * $item->quantidade
                ]);
            }
        }
    }
}