<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GrupoAdicional;
use App\Models\ItemAdicional;

class AdicionalController extends Controller
{
    /**
     * Lista todos os grupos com seus itens.
     */
    public function listar_grupos()
    {
        $grupos = GrupoAdicional::with('itens')->orderBy('nome')->get();
        return response()->json(['status' => true, 'grupos' => $grupos]);
    }

    /**
     * Cria um novo grupo de adicionais.
     */
    public function criar_grupo(Request $requisicao)
    {
        $dados = $requisicao->validate([
            'nome' => 'required|string|max:255|unique:grupos_adicionais,nome',
            'maximo_selecoes' => 'nullable|integer|min:0',
        ]);

        $grupo = GrupoAdicional::create([
            'nome' => $dados['nome'],
            'maximo_selecoes' => $dados['maximo_selecoes'] ?? 0,
        ]);

        return response()->json(['status' => true, 'mensagem' => 'Grupo criado!', 'grupo' => $grupo->load('itens')], 201);
    }

    /**
     * Edita um grupo existente.
     */
    public function editar_grupo(Request $requisicao, $id)
    {
        $grupo = GrupoAdicional::findOrFail($id);

        $dados = $requisicao->validate([
            'nome' => 'required|string|max:255|unique:grupos_adicionais,nome,' . $id,
            'maximo_selecoes' => 'nullable|integer|min:0',
        ]);

        $grupo->update([
            'nome' => $dados['nome'],
            'maximo_selecoes' => $dados['maximo_selecoes'] ?? 0,
        ]);

        return response()->json(['status' => true, 'mensagem' => 'Grupo atualizado!', 'grupo' => $grupo->load('itens')]);
    }

    /**
     * Exclui um grupo e seus itens (cascade).
     */
    public function excluir_grupo($id)
    {
        $grupo = GrupoAdicional::findOrFail($id);
        $grupo->delete();
        return response()->json(['status' => true, 'mensagem' => 'Grupo excluído!']);
    }

    /**
     * Adiciona um item a um grupo.
     */
    public function criar_item(Request $requisicao, $id_grupo)
    {
        $grupo = GrupoAdicional::findOrFail($id_grupo);

        $dados = $requisicao->validate([
            'nome' => 'required|string|max:255',
            'preco' => 'required|numeric|min:0',
        ]);

        $item = $grupo->itens()->create($dados);

        return response()->json(['status' => true, 'mensagem' => 'Item criado!', 'item' => $item], 201);
    }

    /**
     * Edita um item adicional.
     */
    public function editar_item(Request $requisicao, $id)
    {
        $item = ItemAdicional::findOrFail($id);

        $dados = $requisicao->validate([
            'nome' => 'required|string|max:255',
            'preco' => 'required|numeric|min:0',
        ]);

        $item->update($dados);

        return response()->json(['status' => true, 'mensagem' => 'Item atualizado!', 'item' => $item]);
    }

    /**
     * Exclui um item adicional.
     */
    public function excluir_item($id)
    {
        $item = ItemAdicional::findOrFail($id);
        $item->delete();
        return response()->json(['status' => true, 'mensagem' => 'Item excluído!']);
    }
}
