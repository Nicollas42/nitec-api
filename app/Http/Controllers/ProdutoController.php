<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produto;

class ProdutoController extends Controller
{
    public function listar_produtos()
    {
        $lista = Produto::orderBy('nome_produto', 'asc')->get(); // Melhor ordenar por ordem alfabética agora
        return response()->json(['produtos' => $lista]);
    }

    public function cadastrar_produto(Request $requisicao)
    {
        $dados_validados = $requisicao->validate([
            'nome_produto' => 'required|string|max:255',
            'codigo_barras' => 'nullable|string|max:100', 
            'preco_venda' => 'required|numeric',
            'estoque_atual' => 'required|integer',
            'categoria' => 'required|string|max:150',
            'preco_custo' => 'nullable|numeric',
            'data_validade' => 'nullable|date' 
        ]);

        Produto::create($dados_validados);

        return response()->json(['status' => true, 'mensagem' => 'Produto criado com sucesso!'], 201);
    }

    // 🟢 NOVA FUNÇÃO: Atualizar Produto
    public function atualizar_produto(Request $requisicao, $id)
    {
        $produto = Produto::findOrFail($id);

        $dados_validados = $requisicao->validate([
            'nome_produto' => 'required|string|max:255',
            'codigo_barras' => 'nullable|string|max:100', 
            'preco_venda' => 'required|numeric',
            'estoque_atual' => 'required|integer',
            'categoria' => 'required|string|max:150',
            'preco_custo' => 'nullable|numeric',
            'data_validade' => 'nullable|date' 
        ]);

        $produto->update($dados_validados);

        return response()->json(['status' => true, 'mensagem' => 'Produto atualizado com sucesso!']);
    }
}