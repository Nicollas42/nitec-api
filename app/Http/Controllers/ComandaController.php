<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ComandaService; 

class ComandaController extends Controller
{
    protected $comandaService;

    public function __construct(ComandaService $comandaService)
    {
        $this->comandaService = $comandaService;
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
        $dados = $requisicao->validate(['itens' => 'required|array', 'itens.*.produto_id' => 'required', 'itens.*.quantidade' => 'required|integer', 'itens.*.preco_unitario' => 'required|numeric']);
        
        DB::beginTransaction();
        try {
            $comanda = $this->comandaService->adicionar_itens($id_comanda, $dados['itens']);
            DB::commit();
            return response()->json(['status' => true, 'mensagem' => 'Itens lançados!', 'comanda' => $comanda]);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['status' => false], 500); }
    }

    public function venda_balcao(Request $requisicao)
    {
        $dados = $requisicao->validate(['itens' => 'required|array', 'itens.*.produto_id' => 'required', 'itens.*.quantidade' => 'required', 'itens.*.preco_unitario' => 'required', 'desconto' => 'nullable|numeric']);
        
        DB::beginTransaction();
        try {
            $this->comandaService->processar_venda_balcao($dados['itens'], $dados['desconto'], $requisicao->user()->id);
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Venda Balcão concluída!']);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false], 500); }
    }

    public function fechar_comanda(Request $requisicao, $id)
    {
        $dados = $requisicao->validate(['data_hora_fechamento' => 'required|date', 'desconto' => 'nullable|numeric']);
        
        DB::beginTransaction();
        try {
            $comanda = \App\Models\Comanda::with('listar_itens')->findOrFail($id);
            
            if ($comanda->listar_itens->count() === 0) {
                // Descarta automaticamente
                $this->comandaService->cancelar_ou_calote($id, 'Conta descartada (Sem consumo)', false, $requisicao->user()->id);
                DB::commit();
                return response()->json(['sucesso' => true, 'mensagem' => '✔️ Conta vazia anulada e descartada!']);
            }

            // Fluxo normal se tiver itens
            $this->comandaService->fechar_pagamento($id, $dados['data_hora_fechamento'], $dados['desconto']);
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => '💳 Pagamento confirmado!']);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false], 500); }
    }

    public function cancelar_comanda(Request $requisicao, $id)
    {
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
            if ($comanda->status_comanda !== 'fechada') return response()->json(['sucesso' => false, 'mensagem' => 'Apenas comandas fechadas podem ser reabertas.'], 400);
            
            $comanda->status_comanda = 'aberta';
            $comanda->data_hora_fechamento = null;
            $comanda->save();

            if ($comanda->mesa_id) {
                $mesa = \App\Models\Mesa::find($comanda->mesa_id);
                if ($mesa) { $mesa->status_mesa = 'ocupada'; $mesa->save(); }
            }

            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Comanda reaberta com sucesso!']);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['sucesso' => false, 'mensagem' => 'Erro ao reabrir.'], 500); }
    }

    public function buscar_comanda($id) { return response()->json(['sucesso' => true, 'dados' => \App\Models\Comanda::with('listar_itens.buscar_produto')->findOrFail($id)]); }

    // 🟢 NOVA FUNÇÃO: Botões de + e -
    public function alterar_quantidade(Request $request, $id_item) 
    {
        DB::beginTransaction();
        try {
            $item = \App\Models\ComandaItem::findOrFail($id_item);
            $produto = \App\Models\Produto::findOrFail($item->produto_id);
            
            if ($request->acao === 'incrementar') {
                $item->quantidade += 1;
                $produto->decrement('estoque_atual', 1);
            } else if ($request->acao === 'decrementar') {
                if ($item->quantidade <= 1) {
                    $produto->increment('estoque_atual', 1);
                    $item->delete();
                } else {
                    $item->quantidade -= 1;
                    $produto->increment('estoque_atual', 1);
                }
            }
            if ($item->exists) $item->save();
            
            // Recalcular Total da Comanda
            $comanda = \App\Models\Comanda::findOrFail($item->comanda_id);
            $comanda->valor_total = \App\Models\ComandaItem::where('comanda_id', $comanda->id)->sum(\DB::raw('quantidade * preco_unitario'));
            $comanda->save();
            
            DB::commit();
            return response()->json(['sucesso' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['sucesso' => false, 'mensagem' => 'Erro ao alterar quantidade.'], 500);
        }
    }

    // 🟢 NOVA FUNÇÃO: Botão de Lixeira (Remover item completo)
    public function remover_item($id_item) 
    {
        DB::beginTransaction();
        try {
            $item = \App\Models\ComandaItem::findOrFail($id_item);
            
            // Devolve as quantidades para o estoque
            $produto = \App\Models\Produto::findOrFail($item->produto_id);
            $produto->increment('estoque_atual', $item->quantidade);
            
            $comanda_id = $item->comanda_id;
            $item->delete();

            // Recalcular Total da Comanda
            $comanda = \App\Models\Comanda::findOrFail($comanda_id);
            $comanda->valor_total = \App\Models\ComandaItem::where('comanda_id', $comanda->id)->sum(\DB::raw('quantidade * preco_unitario'));
            $comanda->save();

            DB::commit();
            return response()->json(['sucesso' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['sucesso' => false, 'mensagem' => 'Erro ao remover item.'], 500);
        }
    }
}