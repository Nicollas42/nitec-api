<?php

namespace App\Http\Controllers;

use App\Models\CardapioConfig;
use App\Models\Cliente;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Mesa;
use App\Models\Produto;
use App\Services\ComandaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CardapioController extends Controller
{
    public function __construct(private ComandaService $comandaService) {}

    public function config_publica(): JsonResponse
    {
        return response()->json($this->mapear_config(CardapioConfig::obter()));
    }

    public function produtos_publicos(): JsonResponse
    {
        $produtos = Produto::where('visivel_cardapio', true)
            ->orderByRaw('COALESCE(categoria, "")')
            ->orderBy('nome_produto')
            ->get([
                'id',
                'nome_produto',
                'categoria',
                'preco_venda',
                'unidade_medida',
                'foto_produto_path',
            ])
            ->map(fn (Produto $produto) => [
                'id' => $produto->id,
                'nome_produto' => $produto->nome_produto,
                'categoria' => $produto->categoria,
                'preco_venda' => $produto->preco_venda,
                'unidade_medida' => $produto->unidade_medida,
                'foto_produto_url' => $produto->foto_produto_url,
            ])
            ->values();

        return response()->json(['produtos' => $produtos]);
    }

    public function dados_mesa_publica(Request $request, int $id): JsonResponse
    {
        $mesa = Mesa::findOrFail($id);
        $comandaId = (int) $request->query('comanda_id');

        $comanda = null;
        if ($comandaId > 0) {
            $comanda = Comanda::where('id', $comandaId)
                ->where('mesa_id', $mesa->id)
                ->where('status_comanda', 'aberta')
                ->with([
                    'buscar_cliente',
                    'listar_itens' => function ($query) {
                        $query->orderBy('id');
                    },
                    'listar_itens.buscar_produto',
                    'listar_itens.adicionais.buscar_item_adicional',
                ])
                ->first();
        }

        return response()->json([
            'mesa' => [
                'id' => $mesa->id,
                'nome_mesa' => $mesa->nome_mesa,
                'status_mesa' => $mesa->status_mesa,
                'solicitando_atendimento' => (bool) $mesa->solicitando_atendimento,
            ],
            'comanda' => $comanda ? $this->mapear_comanda_cliente($comanda) : null,
        ]);
    }

    public function registrar_cliente(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'mesa_id' => ['required', 'integer', 'exists:mesas,id'],
            'nome' => ['required', 'string', 'max:100'],
            'telefone' => ['required', 'string', 'max:20'],
        ]);

        $mesaId = (int) $dados['mesa_id'];
        $nome = trim($dados['nome']);
        $telefone = trim($dados['telefone']);

        DB::beginTransaction();

        try {
            $cliente = Cliente::firstOrCreate(
                ['telefone' => $telefone],
                ['nome_cliente' => $nome]
            );

            if ($cliente->nome_cliente !== $nome) {
                $cliente->update(['nome_cliente' => $nome]);
            }

            $comandaExistente = Comanda::where('mesa_id', $mesaId)
                ->where('status_comanda', 'aberta')
                ->where('tipo_conta', 'digital')
                ->where('cliente_id', $cliente->id)
                ->first();

            if ($comandaExistente) {
                DB::commit();

                return response()->json([
                    'comanda_id' => $comandaExistente->id,
                    'mesa_id' => $mesaId,
                    'nome' => $cliente->nome_cliente,
                    'telefone' => $cliente->telefone,
                ]);
            }

            $comanda = $this->comandaService->abrir_nova_comanda([
                'mesa_id' => $mesaId,
                'cliente_id' => $cliente->id,
                'nome_cliente' => $cliente->nome_cliente,
                'tipo_conta' => 'digital',
                'data_hora_abertura' => Carbon::now(),
            ], null);

            DB::commit();

            return response()->json([
                'comanda_id' => $comanda->id,
                'mesa_id' => $mesaId,
                'nome' => $cliente->nome_cliente,
                'telefone' => $cliente->telefone,
            ], 201);
        } catch (\Throwable $erro) {
            DB::rollBack();
            report($erro);

            return response()->json(['mensagem' => 'Erro ao registrar cliente.'], 500);
        }
    }

    public function solicitar_atendimento(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'mesa_id' => ['required', 'integer', 'exists:mesas,id'],
            'comanda_id' => ['required', 'integer'],
            'nome_cliente' => ['required', 'string', 'max:100'],
            'telefone' => ['required', 'string', 'max:20'],
            'produtos_desejados' => ['required', 'array', 'min:1'],
            'produtos_desejados.*.id' => ['required', 'integer'],
            'produtos_desejados.*.quantidade' => ['required', 'integer', 'min:1'],
        ]);

        $comanda = Comanda::where('id', $dados['comanda_id'])
            ->where('mesa_id', $dados['mesa_id'])
            ->where('status_comanda', 'aberta')
            ->first();

        if (!$comanda) {
            return response()->json(['mensagem' => 'Comanda invalida para esta mesa.'], 422);
        }

        $idsProdutos = collect($dados['produtos_desejados'])
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        $produtos = Produto::whereIn('id', $idsProdutos)
            ->where('visivel_cardapio', true)
            ->get(['id', 'nome_produto', 'preco_venda'])
            ->keyBy('id');

        if ($produtos->count() !== $idsProdutos->count()) {
            return response()->json(['mensagem' => 'Um ou mais produtos solicitados nao estao disponiveis no cardapio.'], 422);
        }

        $produtosDesejados = collect($dados['produtos_desejados'])
            ->map(function (array $item) use ($produtos) {
                $produto = $produtos->get((int) $item['id']);

                return [
                    'produto_id' => $produto->id,
                    'nome_produto' => $produto->nome_produto,
                    'quantidade' => (int) $item['quantidade'],
                    'preco_venda' => (float) $produto->preco_venda,
                ];
            })
            ->values()
            ->all();

        $mesa = Mesa::findOrFail($dados['mesa_id']);

        // Normaliza para array de pedidos (suporte ao formato antigo de objeto único)
        $pilha = $mesa->solicitacao_detalhes ?? [];
        if (!empty($pilha) && array_is_list($pilha) === false) {
            $pilha = [$pilha]; // converte objeto único para array
        }

        $pilha[] = [
            'comanda_id'       => (int) $dados['comanda_id'],
            'nome_cliente'     => trim($dados['nome_cliente']),
            'telefone'         => trim($dados['telefone']),
            'solicitado_em'    => Carbon::now()->toISOString(),
            'produtos_desejados' => $produtosDesejados,
        ];

        $mesa->update([
            'solicitando_atendimento' => true,
            'solicitacao_detalhes'    => $pilha,
        ]);

        return response()->json(['mensagem' => 'Garcom notificado!']);
    }

    public function config_admin(): JsonResponse
    {
        return response()->json($this->mapear_config(CardapioConfig::obter()));
    }

    public function atualizar_config(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome_exibicao' => ['required', 'string', 'max:100'],
            'subtitulo' => ['nullable', 'string', 'max:200'],
            'mensagem_boas_vindas' => ['nullable', 'string', 'max:500'],
            'cor_primaria' => ['required', 'string', 'max:20'],
            'cor_destaque' => ['required', 'string', 'max:20'],
            'cor_fundo' => ['required', 'string', 'max:20'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ]);

        $config = CardapioConfig::obter();
        $config->update($dados);

        return response()->json($this->mapear_config($config->fresh()));
    }

    public function toggle_visibilidade(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'visivel_cardapio' => ['required', 'boolean'],
        ]);

        $produto = Produto::findOrFail($id);
        $produto->update(['visivel_cardapio' => $dados['visivel_cardapio']]);

        return response()->json([
            'id' => $produto->id,
            'visivel_cardapio' => (bool) $produto->visivel_cardapio,
        ]);
    }

    private function mapear_comanda_cliente(Comanda $comanda): array
    {
        $itens = $comanda->listar_itens
            ->map(fn (ComandaItem $item) => $this->mapear_item_comanda_cliente($item))
            ->values()
            ->all();

        return [
            'id' => $comanda->id,
            'status' => $comanda->status_comanda,
            'nome_cliente' => $comanda->buscar_cliente?->nome_cliente,
            'telefone' => $comanda->buscar_cliente?->telefone,
            'valor_total' => (float) $comanda->valor_total,
            'itens' => $itens,
        ];
    }

    private function mapear_item_comanda_cliente(ComandaItem $item): array
    {
        $adicionais = $item->relationLoaded('adicionais') ? $item->adicionais : collect();
        $valorAdicionais = $adicionais->sum(function ($adicional) {
            return ((float) $adicional->preco_unitario) * max(1, (int) $adicional->quantidade);
        });

        $subtotal = (((float) $item->preco_unitario) + $valorAdicionais) * ((int) $item->quantidade);

        return [
            'id' => $item->id,
            'produto_id' => $item->produto_id,
            'nome_produto' => $item->buscar_produto?->nome_produto ?? '(produto removido)',
            'quantidade' => (int) $item->quantidade,
            'preco_unitario' => (float) $item->preco_unitario,
            'subtotal' => round($subtotal, 2),
        ];
    }

    private function mapear_config(CardapioConfig $config): array
    {
        return [
            'nome_exibicao' => $config->nome_exibicao,
            'subtitulo' => $config->subtitulo,
            'mensagem_boas_vindas' => $config->mensagem_boas_vindas,
            'cor_primaria' => $config->cor_primaria,
            'cor_destaque' => $config->cor_destaque,
            'cor_fundo' => $config->cor_fundo,
            'logo_url' => $config->logo_url,
        ];
    }
}
