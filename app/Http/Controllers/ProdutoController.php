<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produto;

class ProdutoController extends Controller
{
    /**
     * Lista todos os produtos cadastrados.
     * * @return \Illuminate\Http\JsonResponse
     */
    public function listar_produtos()
    {
        // Retorna os produtos mais recentes primeiro
        $lista = Produto::orderBy('id', 'desc')->get();
        return response()->json(['produtos' => $lista]);
    }

    /**
     * Salva um novo produto no banco.
     * * @param \Illuminate\Http\Request $requisicao
     * * @return \Illuminate\Http\JsonResponse
     */
    public function cadastrar_produto(Request $requisicao)
    {
        $dados_validados = $requisicao->validate([
            'nome_produto' => 'required|string|max:255',
            'preco_venda' => 'required|numeric',
            'estoque_atual' => 'required|integer'
        ]);

        Produto::create($dados_validados);

        return response()->json(['status' => true, 'mensagem' => 'Produto criado!'], 201);
    }
}