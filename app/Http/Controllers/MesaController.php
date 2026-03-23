<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mesa;
use App\Models\PedidoCozinha;
use Illuminate\Http\JsonResponse;

/**
 * Gerencia as operações de mesas dentro do ambiente isolado do bar.
 */
class MesaController extends Controller
{
    /**
     * Retorna a lista de todas as mesas do estabelecimento atual.
     * * @return JsonResponse
     */
    public function listar_mesas(): JsonResponse
    {
        $lista_de_mesas = Mesa::all();
        
        return response()->json([
            'sucesso' => true,
            'dados' => $lista_de_mesas
        ], 200);
    }

    /**
     * Cadastra uma nova mesa física garantindo o isolamento do tenant.
     * * @param Request $requisicao_original
     * @return JsonResponse
     */
    public function cadastrar_mesa(Request $requisicao_original): JsonResponse
    {
        $dados_validados = $requisicao_original->validate([
            'nome_mesa' => 'required|string|max:50|unique:mesas,nome_mesa',
            'capacidade_pessoas' => 'nullable|integer'
        ]);

        $nova_mesa = Mesa::create([
            'nome_mesa' => $dados_validados['nome_mesa'],
            'capacidade_pessoas' => $dados_validados['capacidade_pessoas'] ?? 4,
            'status_mesa' => 'livre'
        ]);

        return response()->json([
            'sucesso' => true, 
            'mensagem' => 'Mesa criada com sucesso!',
            'mesa' => $nova_mesa
        ], 201);
    }

    /**
     * Traz o resumo completo de uma mesa, incluindo as comandas abertas.
     * * @param int $id_da_mesa
     * @return JsonResponse
     */
    public function detalhes_mesa($id_da_mesa): JsonResponse
    {
        try {
            $informacoes_da_mesa = Mesa::with(['listar_comandas' => function($consulta_sql) {
                $consulta_sql->where('status_comanda', 'aberta')
                             ->with(['listar_itens.buscar_produto', 'listar_itens.adicionais.buscar_item_adicional', 'buscar_cliente', 'buscar_usuario']);
            }])->findOrFail($id_da_mesa);

            // Enriquecer cada item com status de cozinha
            $status_cozinha_por_item = PedidoCozinha::whereIn(
                'comanda_item_id',
                $informacoes_da_mesa->listar_comandas->flatMap(fn($c) => $c->listar_itens->pluck('id'))
            )->pluck('status', 'comanda_item_id');

            $dados = $informacoes_da_mesa->toArray();
            foreach ($dados['listar_comandas'] as &$comanda) {
                foreach ($comanda['listar_itens'] as &$item) {
                    $item['status_cozinha'] = $status_cozinha_por_item[$item['id']] ?? null;
                }
            }

            return response()->json([
                'sucesso' => true,
                'dados' => $dados
            ], 200);
            
        } catch (\Exception $erro_sistema) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Erro ao localizar a mesa ou carregar detalhes.'
            ], 404);
        }
    }
}