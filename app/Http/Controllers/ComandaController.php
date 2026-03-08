<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ComandaService; // 🟢 Importamos a nossa inteligência

class ComandaController extends Controller
{
    protected $comandaService;

    public function __construct(ComandaService $comandaService)
    {
        $this->comandaService = $comandaService;
    }

    public function listar_todas_comandas()
    {
        // O Foco Operacional vai fazer a tela abrir num piscar de olhos!
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
            $this->comandaService->fechar_pagamento($id, $dados['data_hora_fechamento'], $dados['desconto']);
            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Comanda fechada com sucesso!']);
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

    // (As funções remover_item_comanda, alterar_quantidade_item e buscar_comanda permanecem como estavam para evitar prolongar o ficheiro)
    public function buscar_comanda($id) { return response()->json(['sucesso' => true, 'dados' => \App\Models\Comanda::with('listar_itens.buscar_produto')->findOrFail($id)]); }
}