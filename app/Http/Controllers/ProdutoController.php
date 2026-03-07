<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produto;

class ProdutoController extends Controller
{
    public function listar_produtos()
    {
        // Retorna os produtos mais recentes primeiro
        $lista = Produto::orderBy('id', 'desc')->get();
        return response()->json(['produtos' => $lista]);
    }

    public function cadastrar_produto(Request $requisicao)
    {
        // 🟢 ATUALIZADO: Adicionadas as validações para os campos de BI
        $dados_validados = $requisicao->validate([
            'nome_produto' => 'required|string|max:255',
            'codigo_barras' => 'nullable|string|max:100', 
            'preco_venda' => 'required|numeric',
            'estoque_atual' => 'required|integer',
            'categoria' => 'required|string|max:150',    // 🟢 Novo campo de BI (Obrigatório, pois o Vue manda 'Geral' por padrão)
            'preco_custo' => 'nullable|numeric'          // 🟢 Novo campo de BI (Opcional)
        ]);

        Produto::create($dados_validados);

        return response()->json(['status' => true, 'mensagem' => 'Produto criado com sucesso!'], 201);
    }
}