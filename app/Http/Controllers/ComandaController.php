<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Mesa;
use App\Models\Comanda;
use App\Models\Cliente;
use App\Models\ComandaItem; // <- O Laravel precisava disto
use App\Models\Produto;     // <- E disto!

class ComandaController extends Controller
{
    /**
     * Abre uma nova comanda atrelada a uma mesa e, opcionalmente, a um cliente.
     * * @param \Illuminate\Http\Request $requisicao
     * * @return \Illuminate\Http\JsonResponse
     */
    public function abrir_comanda_mesa(Request $requisicao)
    {
        $dados = $requisicao->validate([
            'mesa_id' => 'required|integer|exists:mesas,id',
            'nome_cliente' => 'nullable|string|max:100'
        ]);

        DB::beginTransaction();

        try {
            $cliente_id = null;

            if (!empty($dados['nome_cliente'])) {
                $cliente = Cliente::firstOrCreate(
                    ['nome_cliente' => $dados['nome_cliente']]
                );
                $cliente_id = $cliente->id;
            }

            $nova_comanda = Comanda::create([
                'mesa_id' => $dados['mesa_id'],
                'cliente_id' => $cliente_id,
                'usuario_id' => $requisicao->user()->id,
                'status_comanda' => 'aberta',
                'valor_total' => 0
            ]);

            $mesa = Mesa::findOrFail($dados['mesa_id']);
            $mesa->update(['status_mesa' => 'ocupada']);

            DB::commit();

            return response()->json([
                'status' => true, 
                'mensagem' => 'Comanda aberta com sucesso!', 
                'comanda' => $nova_comanda
            ], 201);

        } catch (\Exception $erro) {
            DB::rollBack();
            return response()->json([
                'status' => false, 
                'mensagem' => 'Erro ao abrir comanda.', 
                'detalhe' => $erro->getMessage()
            ], 500);
        }
    }

    /**
     * Lista todas as comandas do sistema com suas relações.
     * * @return \Illuminate\Http\JsonResponse
     */
    public function listar_todas_comandas()
    {
        $comandas = Comanda::with(['buscar_mesa', 'buscar_cliente'])
                           ->orderBy('id', 'desc')
                           ->get();

        return response()->json([
            'status' => true,
            'comandas' => $comandas
        ]);
    }

    /**
     * Recebe um array de produtos do PDV e lança na comanda, descontando o estoque.
     * * @param \Illuminate\Http\Request $requisicao
     * * @param int $id_comanda
     * * @return \Illuminate\Http\JsonResponse
     */
    public function adicionar_itens_comanda(Request $requisicao, $id_comanda)
    {
        $dados = $requisicao->validate([
            'itens' => 'required|array|min:1',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.quantidade' => 'required|integer|min:1',
            'itens.*.preco_unitario' => 'required|numeric|min:0'
        ]);

        DB::beginTransaction();

        try {
            $comanda = Comanda::findOrFail($id_comanda);
            $valor_adicional_total = 0;

            foreach ($dados['itens'] as $item) {
                // Regista o item na comanda
                ComandaItem::create([
                    'comanda_id' => $comanda->id,
                    'produto_id' => $item['produto_id'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco_unitario']
                ]);

                $valor_adicional_total += ($item['quantidade'] * $item['preco_unitario']);

                // Dá baixa no estoque
                $produto = Produto::findOrFail($item['produto_id']);
                $produto->estoque_atual -= $item['quantidade'];
                $produto->save();
            }

            // Atualiza o valor da comanda
            $comanda->valor_total += $valor_adicional_total;
            $comanda->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'mensagem' => 'Itens lançados com sucesso!',
                'comanda' => $comanda
            ]);

        } catch (\Exception $erro) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'mensagem' => 'Erro interno ao lançar produtos.',
                'detalhe' => $erro->getMessage()
            ], 500);
        }
    }


    /**
     * Remove um item da comanda, devolve ao estoque e recalcula o total.
     * * @param int $id_item
     * * @return \Illuminate\Http\JsonResponse
     */
    public function remover_item_comanda($id_item)
    {
        DB::beginTransaction();

        try {
            $item = ComandaItem::findOrFail($id_item);
            $comanda = Comanda::findOrFail($item->comanda_id);
            $produto = Produto::findOrFail($item->produto_id);

            // 1. Devolve a quantidade ao estoque
            $produto->estoque_atual += $item->quantidade;
            $produto->save();

            // 2. Subtrai o valor do total da comanda
            $valor_a_abater = $item->quantidade * $item->preco_unitario;
            $comanda->valor_total -= $valor_a_abater;
            $comanda->save();

            // 3. Apaga o registo do item
            $item->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'mensagem' => 'Item removido e estoque restaurado com sucesso!'
            ]);

        } catch (\Exception $erro) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'mensagem' => 'Erro ao remover item da comanda.',
                'detalhe' => $erro->getMessage()
            ], 500);
        }
    }

    /**
     * Incrementa ou decrementa a quantidade de um item na comanda.
     * * @param \Illuminate\Http\Request $requisicao
     * * @param int $id_item
     * * @return \Illuminate\Http\JsonResponse
     */
    public function alterar_quantidade_item(Request $requisicao, $id_item)
    {
        $dados = $requisicao->validate([
            'acao' => 'required|in:incrementar,decrementar'
        ]);

        DB::beginTransaction();

        try {
            $item = ComandaItem::findOrFail($id_item);
            $comanda = Comanda::findOrFail($item->comanda_id);
            $produto = Produto::findOrFail($item->produto_id);

            if ($dados['acao'] === 'incrementar') {
                if ($produto->estoque_atual < 1) {
                    return response()->json(['status' => false, 'mensagem' => 'Estoque insuficiente para este produto.'], 400);
                }
                $item->quantidade += 1;
                $produto->estoque_atual -= 1;
                $comanda->valor_total += $item->preco_unitario;
            } else {
                // acao = decrementar
                $item->quantidade -= 1;
                $produto->estoque_atual += 1;
                $comanda->valor_total -= $item->preco_unitario;
            }

            $produto->save();
            $comanda->save();

            // Se a quantidade chegar a zero, excluímos a linha inteira da comanda
            if ($item->quantidade <= 0) {
                $item->delete();
            } else {
                $item->save();
            }

            DB::commit();

            return response()->json(['status' => true, 'mensagem' => 'Quantidade atualizada!']);

        } catch (\Exception $erro) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'mensagem' => 'Erro ao alterar quantidade.',
                'detalhe' => $erro->getMessage()
            ], 500);
        }
    }
}