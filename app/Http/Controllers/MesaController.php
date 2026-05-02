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
     * Resolve TODAS as chamadas de atendimento empilhadas, limpando a flag
     * da mesa. As solicitações hoje contêm apenas nome + telefone do cliente
     * que chamou — o garçom atende presencialmente.
     *
     * @param int $id ID da mesa
     * @return JsonResponse
     */
    public function resolver_atendimento(int $id): JsonResponse
    {
        $mesa = Mesa::findOrFail($id);

        $mesa->update([
            'solicitando_atendimento' => false,
            'solicitacao_detalhes'    => null,
        ]);

        return response()->json(['sucesso' => true]);
    }

    /**
     * Resolve uma chamada individual da pilha pelo índice (0-based).
     * Se a pilha ficar vazia após a remoção, limpa o flag de atendimento.
     *
     * @param Request $requisicao
     * @param int     $id    ID da mesa
     * @return JsonResponse
     */
    public function resolver_atendimento_individual(Request $requisicao, int $id): JsonResponse
    {
        $dados = $requisicao->validate([
            'indice' => ['required', 'integer', 'min:0'],
        ]);

        $mesa = Mesa::findOrFail($id);

        $pilha = $mesa->solicitacao_detalhes ?? [];

        // Suporte ao formato antigo (objeto único em vez de array de pedidos)
        if (!empty($pilha) && array_is_list($pilha) === false) {
            $pilha = [$pilha];
        }

        $indice = (int) $dados['indice'];

        if (!isset($pilha[$indice])) {
            return response()->json(['mensagem' => 'Chamada nao encontrada na pilha.'], 404);
        }

        // Remove a chamada resolvida e re-indexa
        array_splice($pilha, $indice, 1);

        $pilha_vazia = empty($pilha);

        $mesa->update([
            'solicitando_atendimento' => !$pilha_vazia,
            'solicitacao_detalhes'    => $pilha_vazia ? null : array_values($pilha),
        ]);

        return response()->json(['sucesso' => true]);
    }

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
                $consulta_sql->whereIn('status_comanda', ['aberta', 'pendente'])
                             ->orderBy('id')
                             ->with(['listar_itens' => fn($q) => $q->orderBy('id'), 'listar_itens.buscar_produto', 'listar_itens.adicionais.buscar_item_adicional', 'buscar_cliente', 'buscar_usuario']);
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