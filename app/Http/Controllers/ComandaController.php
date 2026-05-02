<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // 🟢 Essencial para ler o 'off_'
use App\Services\ComandaService;
use App\Services\GestaoEstoqueService;

class ComandaController extends Controller
{
    protected $comandaService;
    protected $gestaoEstoqueService;

    /**
     * Injeta os servicos de comanda e de estoque utilizados pela controller.
     */
    public function __construct(ComandaService $comandaService, GestaoEstoqueService $gestaoEstoqueService)
    {
        $this->comandaService = $comandaService;
        $this->gestaoEstoqueService = $gestaoEstoqueService;
    }

    public function listar_todas_comandas()
    {
        $comandas = $this->comandaService->obter_comandas_do_dia();
        return response()->json(['status' => true, 'comandas' => $comandas]);
    }

    public function abrir_comanda_mesa(Request $requisicao)
    {
        $dados = $requisicao->validate(['mesa_id' => 'required|integer', 'nome_cliente' => 'nullable|string', 'tipo_conta' => 'nullable|string', 'data_hora_abertura' => 'nullable|date']);
        DB::beginTransaction();
        try {
            $comanda = $this->comandaService->abrir_nova_comanda($dados, $requisicao->user()->id);
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Comanda aberta!', 'comanda' => $comanda], 201);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false], 500); }
    }

    public function adicionar_itens_comanda(Request $requisicao, $id_comanda)
    {
        // 🟢 TRADUTOR OFFLINE: Converte a comanda fantasma ('off_5') no ID real da mesa
        if (Str::startsWith($id_comanda, 'off_')) {
            $comanda_ativa = \App\Models\Comanda::where('mesa_id', str_replace('off_', '', $id_comanda))->where('status_comanda', 'aberta')->first();
            if ($comanda_ativa) $id_comanda = $comanda_ativa->id;
            else return response()->json(['status' => false, 'mensagem' => 'Nenhuma conta aberta nesta mesa.'], 404);
        }

        $dados = $requisicao->validate([
            'itens' => 'required|array', 'itens.*.produto_id' => 'required', 'itens.*.quantidade' => 'required|integer', 'itens.*.preco_unitario' => 'required|numeric',
            'itens.*.adicionais' => 'nullable|array',
            'itens.*.adicionais.*.item_adicional_id' => 'required|integer',
            'itens.*.adicionais.*.quantidade' => 'nullable|integer|min:1',
            'itens.*.adicionais.*.preco_unitario' => 'required|numeric|min:0',
        ]);
        DB::beginTransaction();
        try {
            $comanda = $this->comandaService->adicionar_itens($id_comanda, $dados['itens']);
            DB::commit();
            return response()->json(['status' => true, 'mensagem' => 'Itens lançados!', 'comanda' => $comanda]);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['status' => false], 500); }
    }

    public function venda_balcao(Request $requisicao)
    {
        $dados = $requisicao->validate([
            'itens' => 'required|array', 'itens.*.produto_id' => 'required', 'itens.*.quantidade' => 'required', 'itens.*.preco_unitario' => 'required', 'desconto' => 'nullable|numeric',
            'itens.*.adicionais' => 'nullable|array',
            'itens.*.adicionais.*.item_adicional_id' => 'required|integer',
            'itens.*.adicionais.*.quantidade' => 'nullable|integer|min:1',
            'itens.*.adicionais.*.preco_unitario' => 'required|numeric|min:0',
            'forma_pagamento' => 'nullable|string|in:dinheiro,pix,debito,credito',
        ]);
        DB::beginTransaction();
        try {
            $this->comandaService->processar_venda_balcao($dados['itens'], $dados['desconto'], $requisicao->user()->id, $dados['forma_pagamento'] ?? null);
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Venda Balcão concluída!']);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false], 500); }
    }

    public function fechar_comanda(Request $requisicao, $id)
    {
        // 🟢 TRADUTOR OFFLINE
        if (Str::startsWith($id, 'off_')) {
            $comanda_ativa = \App\Models\Comanda::where('mesa_id', str_replace('off_', '', $id))->where('status_comanda', 'aberta')->first();
            if ($comanda_ativa) $id = $comanda_ativa->id;
            else return response()->json(['sucesso' => false, 'mensagem' => 'Nenhuma conta aberta para fechar.'], 404);
        }

        $dados = $requisicao->validate([
            'data_hora_fechamento' => 'required|date',
            'desconto' => 'nullable|numeric',
            'forma_pagamento' => 'nullable|string|in:dinheiro,pix,debito,credito',
        ]);
        DB::beginTransaction();
        try {
            $comanda = \App\Models\Comanda::with('listar_itens')->findOrFail($id);
            if ($comanda->listar_itens->count() === 0) {
                $this->comandaService->cancelar_ou_calote($id, 'Conta descartada (Sem consumo)', false, $requisicao->user()->id);
                DB::commit();
                return response()->json(['sucesso' => true, 'mensagem' => '✔️ Conta vazia anulada e descartada!']);
            }
            $this->comandaService->fechar_pagamento($id, $dados['data_hora_fechamento'], $dados['desconto'], $dados['forma_pagamento'] ?? null);
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => '💳 Pagamento confirmado!']);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false], 500); }
    }

    public function cancelar_comanda(Request $requisicao, $id)
    {
        // 🟢 TRADUTOR OFFLINE
        if (Str::startsWith($id, 'off_')) {
            $comanda_ativa = \App\Models\Comanda::where('mesa_id', str_replace('off_', '', $id))->where('status_comanda', 'aberta')->first();
            if ($comanda_ativa) $id = $comanda_ativa->id;
            else return response()->json(['sucesso' => false, 'mensagem' => 'Nenhuma conta aberta para cancelar.'], 404);
        }

        $dados = $requisicao->validate(['motivo_cancelamento' => 'required|string', 'retornar_ao_estoque' => 'required|boolean']);
        DB::beginTransaction();
        try {
            $this->comandaService->cancelar_ou_calote($id, $dados['motivo_cancelamento'], $dados['retornar_ao_estoque'], $requisicao->user()->id);
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Comanda cancelada!']);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false], 500); }
    }

    public function reabrir_comanda($id)
    {
        DB::beginTransaction();
        try {
            $comanda = \App\Models\Comanda::findOrFail($id);
            if ($comanda->status_comanda !== 'fechada') return response()->json(['sucesso' => false, 'mensagem' => 'Apenas fechadas reabrem.'], 400);
            $comanda->status_comanda = 'aberta'; $comanda->data_hora_fechamento = null; $comanda->save();
            if ($comanda->mesa_id) { $mesa = \App\Models\Mesa::find($comanda->mesa_id); if ($mesa) { $mesa->status_mesa = 'ocupada'; $mesa->save(); } }
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Reaberta!']);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false], 500); }
    }

    public function buscar_comanda($id) {
        $eager = ['listar_itens.buscar_produto', 'listar_itens.adicionais.buscar_item_adicional'];

        // 🟢 TRADUTOR OFFLINE (Caso a internet volte exatamente quando ele clica na conta fantasma)
        if (Str::startsWith($id, 'off_')) {
            $comanda_ativa = \App\Models\Comanda::with($eager)->where('mesa_id', str_replace('off_', '', $id))->where('status_comanda', 'aberta')->first();
            if ($comanda_ativa) return response()->json(['sucesso' => true, 'dados' => $comanda_ativa]);
            return response()->json(['sucesso' => false, 'mensagem' => 'Comanda não encontrada'], 404);
        }
        return response()->json(['sucesso' => true, 'dados' => \App\Models\Comanda::with($eager)->findOrFail($id)]);
    }

    /**
     * Aprova uma comanda pendente (garçom confirma a entrada do cliente).
     */
    public function aprovar_comanda($id)
    {
        DB::beginTransaction();
        try {
            $comanda = $this->comandaService->aprovar_comanda($id);
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Comanda aprovada!', 'comanda' => $comanda]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['sucesso' => false, 'mensagem' => $e->getMessage()], 400);
        }
    }

    /**
     * Aprova todas as comandas pendentes de uma mesa.
     */
    public function aprovar_todas_pendentes($mesa_id)
    {
        DB::beginTransaction();
        try {
            $pendentes = \App\Models\Comanda::where('mesa_id', $mesa_id)
                ->where('status_comanda', 'pendente')
                ->get();

            foreach ($pendentes as $comanda) {
                $this->comandaService->aprovar_comanda($comanda->id);
            }

            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => "{$pendentes->count()} comanda(s) aprovada(s)!"]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['sucesso' => false, 'mensagem' => $e->getMessage()], 400);
        }
    }

    /**
     * Rejeita uma comanda pendente (garçom recusa a entrada do cliente).
     */
    public function rejeitar_comanda($id)
    {
        DB::beginTransaction();
        try {
            $this->comandaService->rejeitar_comanda($id);
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Comanda rejeitada.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['sucesso' => false, 'mensagem' => $e->getMessage()], 400);
        }
    }

    /**
     * Altera a quantidade de um item ja lancado, refletindo a baixa ou a devolucao no FIFO.
     */
    public function alterar_quantidade(Request $requisicao, $id_item) 
    {
        DB::beginTransaction();
        try {
            $item = \App\Models\ComandaItem::findOrFail($id_item);
            $produto = \App\Models\Produto::lockForUpdate()->findOrFail($item->produto_id);

            if ($requisicao->acao === 'incrementar') {
                $item->quantidade += 1;
                $this->gestaoEstoqueService->consumir_para_comanda_item($produto, 1, $item->id);
            } 
            else if ($requisicao->acao === 'decrementar') {
                if ($item->quantidade <= 1) {
                    $this->gestaoEstoqueService->restaurar_por_referencia('comanda_item', $item->id);
                    $item->delete();
                } 
                else {
                    $item->quantidade -= 1;
                    $this->gestaoEstoqueService->restaurar_quantidade_por_referencia('comanda_item', $item->id, 1);
                }
            }
            if ($item->exists) $item->save();
            $comanda = \App\Models\Comanda::findOrFail($item->comanda_id);
            $comanda->valor_total = \App\Models\ComandaItem::where('comanda_id', $comanda->id)->sum(\DB::raw('quantidade * preco_unitario'));
            $comanda->save();
            DB::commit();
            return response()->json(['sucesso' => true]);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false], 500); }
    }

    /**
     * Remove completamente um item da comanda e devolve o consumo FIFO para o estoque.
     */
    public function remover_item(Request $requisicao, $id_item) 
    {
        DB::beginTransaction();
        try {
            $item = \App\Models\ComandaItem::findOrFail($id_item);
            $this->gestaoEstoqueService->restaurar_por_referencia('comanda_item', $item->id);
            $cid = $item->comanda_id; $item->delete();
            $comanda = \App\Models\Comanda::findOrFail($cid);
            $comanda->valor_total = \App\Models\ComandaItem::where('comanda_id', $cid)->sum(\DB::raw('quantidade * preco_unitario'));
            $comanda->save();
            DB::commit();
            return response()->json(['sucesso' => true]);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false], 500); }
    }
}
