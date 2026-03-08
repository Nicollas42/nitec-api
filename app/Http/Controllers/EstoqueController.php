<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produto;
use App\Models\EstoquePerda;
use App\Models\EstoqueEntrada; // 🟢 NOVO
use Illuminate\Support\Facades\DB;

class EstoqueController extends Controller
{
    public function registrar_perda(Request $requisicao)
    {
        $dados = $requisicao->validate([
            'produto_id' => 'required|exists:produtos,id',
            'quantidade' => 'required|integer|min:1',
            'motivo' => 'required|string|max:255'
        ]);

        try {
            DB::beginTransaction();
            $produto = Produto::findOrFail($dados['produto_id']);

            if ($produto->estoque_atual < $dados['quantidade']) {
                return response()->json(['sucesso' => false, 'mensagem' => 'Estoque insuficiente para esta baixa.'], 400);
            }

            $custo_unitario = $produto->preco_custo ?? 0;
            
            EstoquePerda::create([
                'produto_id' => $produto->id,
                'usuario_id' => $requisicao->user()->id,
                'quantidade' => $dados['quantidade'],
                'motivo' => $dados['motivo'],
                'custo_total_perda' => $custo_unitario * $dados['quantidade']
            ]);

            $produto->decrement('estoque_atual', $dados['quantidade']);
            DB::commit();

            return response()->json(['sucesso' => true, 'mensagem' => 'Baixa registrada!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['sucesso' => false, 'mensagem' => 'Erro ao registrar perda.'], 500);
        }
    }

    // 🟢 NOVA FUNÇÃO: Registrar Compra/Entrada
    public function registrar_entrada(Request $requisicao)
    {
        $dados = $requisicao->validate([
            'produto_id' => 'required|exists:produtos,id',
            'quantidade' => 'required|integer|min:1',
            'custo_unitario' => 'required|numeric|min:0',
            'fornecedor' => 'nullable|string|max:255'
        ]);

        try {
            DB::beginTransaction();
            $produto = Produto::findOrFail($dados['produto_id']);

            EstoqueEntrada::create([
                'produto_id' => $produto->id,
                'usuario_id' => $requisicao->user()->id,
                'quantidade_adicionada' => $dados['quantidade'],
                'custo_unitario_compra' => $dados['custo_unitario'],
                'fornecedor' => $dados['fornecedor']
            ]);

            // Soma no estoque físico
            $produto->increment('estoque_atual', $dados['quantidade']);
            
            // Opcional: Atualiza o preço de custo base do produto para refletir a compra mais recente
            $produto->update(['preco_custo' => $dados['custo_unitario']]);

            DB::commit();
            return response()->json(['sucesso' => true, 'mensagem' => 'Entrada de estoque registrada!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['sucesso' => false, 'mensagem' => 'Erro ao registrar entrada.'], 500);
        }
    }
}