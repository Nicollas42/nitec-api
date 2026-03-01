<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mesa;

class MesaController extends Controller
{
    /**
     * Lista todas as mesas do estabelecimento.
     * * @return \Illuminate\Http\JsonResponse
     */
    public function listar_mesas()
    {
        $mesas = Mesa::all();
        return response()->json(['mesas' => $mesas]);
    }

    /**
     * Cadastra uma nova mesa física no sistema.
     * * @param \Illuminate\Http\Request $requisicao
     * * @return \Illuminate\Http\JsonResponse
     */
    public function cadastrar_mesa(Request $requisicao)
    {
        $dados = $requisicao->validate([
            'nome_mesa' => 'required|string|max:50|unique:mesas,nome_mesa'
        ]);

        $nova_mesa = Mesa::create([
            'nome_mesa' => $dados['nome_mesa'],
            'status_mesa' => 'livre'
        ]);

        return response()->json(['status' => true, 'mesa' => $nova_mesa], 201);
    }

    /**
     * Traz o resumo completo de uma mesa, incluindo as comandas abertas e os itens consumidos.
     * * @param int $id
     * * @return \Illuminate\Http\JsonResponse
     */
    public function detalhes_mesa($id)
    {
        try {
            // Eager Loading: Agora traz a mesa, as comandas, os itens E os dados do CLIENTE.
            $mesa = Mesa::with(['listar_comandas' => function($query) {
                $query->where('status_comanda', 'aberta')
                      ->with(['listar_itens.buscar_produto', 'buscar_cliente', 'buscar_usuario']);
            }])->findOrFail($id);

            return response()->json([
                'status' => true, 
                'mesa' => $mesa
            ]);
            
        } catch (\Exception $erro) {
            return response()->json([
                'status' => false,
                'mensagem' => 'Mesa não encontrada ou erro ao carregar detalhes.'
            ], 404);
        }
    }
}