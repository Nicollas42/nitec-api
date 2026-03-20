<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\ProdutoCodigoBarras;
use App\Models\ProdutoEstoqueLote;
use App\Models\ProdutoFornecedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProdutoController extends Controller
{
    /**
     * Lista o catalogo enxuto de produtos para tela de gestao e PDV.
     */
    public function listar_produtos(): JsonResponse
    {
        $produtos = Produto::query()
            ->with(['codigos_barras', 'produto_fornecedores', 'estoque_lotes.fornecedor'])
            ->orderBy('nome_produto')
            ->get()
            ->map(fn (Produto $produto) => $this->mapear_produto_listagem($produto))
            ->values();

        return response()->json([
            'produtos' => $produtos,
        ]);
    }

    /**
     * Retorna os detalhes completos de um produto para edicao.
     */
    public function detalhes_produto(int $id): JsonResponse
    {
        $produto = Produto::query()
            ->with(['codigos_barras', 'produto_fornecedores.fornecedor', 'estoque_lotes.fornecedor'])
            ->findOrFail($id);

        return response()->json([
            'produto' => $this->mapear_produto_detalhes($produto),
        ]);
    }

    /**
     * Cadastra um novo produto com aliases e vinculos de fornecedor.
     */
    public function cadastrar_produto(Request $request): JsonResponse
    {
        return $this->salvar_produto($request);
    }

    /**
     * Atualiza um produto existente com aliases e vinculos de fornecedor.
     */
    public function atualizar_produto(Request $request, int $id): JsonResponse
    {
        $produto = Produto::query()->findOrFail($id);

        return $this->salvar_produto($request, $produto);
    }

    /**
     * Exclui logicamente um produto e remove os vinculos editaveis do cadastro.
     */
    public function excluir_produto(int $id): JsonResponse
    {
        $produto = Produto::query()->with(['codigos_barras', 'produto_fornecedores'])->findOrFail($id);

        DB::transaction(function () use ($produto): void {
            $produto->codigos_barras()->delete();
            $produto->produto_fornecedores()->delete();
            $produto->delete();
        });

        return response()->json([
            'status' => true,
            'mensagem' => 'Produto removido com sucesso.',
        ]);
    }

    /**
     * Salva os dados principais do produto e sincroniza os relacionamentos.
     */
    private function salvar_produto(Request $request, ?Produto $produto = null): JsonResponse
    {
        $dados_validados = $this->validar_payload_produto($request, $produto?->id);
        $mensagem = $produto ? 'Produto atualizado com sucesso.' : 'Produto criado com sucesso.';

        DB::transaction(function () use (&$produto, $dados_validados): void {
            $dados_produto = collect($dados_validados)->only([
                'nome_produto',
                'codigo_interno',
                'unidade_medida',
                'preco_venda',
                'preco_custo_medio',
                'margem_lucro_percentual',
                'categoria',
            ])->toArray();

            if ($produto instanceof Produto) {
                $produto->update($dados_produto);
            } else {
                $produto = Produto::query()->create($dados_produto);
            }

            $this->sincronizar_codigos_barras($produto, $dados_validados['codigos_barras_adicionais'] ?? []);
            $this->sincronizar_fornecedores_vinculados($produto, $dados_validados['fornecedores_vinculados'] ?? []);
            $produto->refresh()->load(['codigos_barras', 'produto_fornecedores.fornecedor', 'estoque_lotes.fornecedor']);
        });

        return response()->json([
            'status' => true,
            'mensagem' => $mensagem,
            'produto' => $this->mapear_produto_detalhes($produto),
        ], $produto->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Valida o payload do produto e aplica as regras de unicidade complementares.
     *
     * @return array<string, mixed>
     */
    private function validar_payload_produto(Request $request, ?int $produto_id = null): array
    {
        $dados_validados = $request->validate([
            'nome_produto'                                       => 'required|string|max:255',
            'codigo_interno'                                     => [
                'required',
                'string',
                'max:255',
                Rule::unique('produtos', 'codigo_interno')->ignore($produto_id),
            ],
            'unidade_medida'                                     => ['required', 'string', Rule::in(['UN', 'KG', 'LT', 'CX'])],
            'preco_venda'                                        => 'required|numeric|min:0',
            'preco_custo_medio'                                  => 'nullable|numeric|min:0',
            'margem_lucro_percentual'                            => 'nullable|numeric|min:0',
            'categoria'                                          => 'required|string|max:150',
            'codigos_barras_adicionais'                          => 'nullable|array',
            'codigos_barras_adicionais.*.id'                     => 'nullable|integer',
            'codigos_barras_adicionais.*.codigo_barras'          => 'nullable|string|max:100',
            'codigos_barras_adicionais.*.descricao_variacao'     => 'nullable|string|max:255',
            'fornecedores_vinculados'                            => 'nullable|array',
            'fornecedores_vinculados.*.fornecedor_id'            => 'nullable|integer|exists:fornecedores,id',
            'fornecedores_vinculados.*.codigo_sku_fornecedor'    => 'nullable|string|max:255',
            'fornecedores_vinculados.*.unidade_embalagem'        => 'nullable|string|max:10',
            'fornecedores_vinculados.*.fator_conversao'          => 'nullable|integer|min:1',
            'fornecedores_vinculados.*.custo_embalagem'          => 'nullable|numeric|min:0',
        ]);

        $dados_validados['unidade_medida']          = $dados_validados['unidade_medida'] ?? 'UN';
        $dados_validados['preco_custo_medio']        = $dados_validados['preco_custo_medio'] ?? 0;
        $dados_validados['margem_lucro_percentual']  = $dados_validados['margem_lucro_percentual'] ?? 0;
        $dados_validados['codigos_barras_adicionais'] = $this->normalizar_codigos_barras($dados_validados['codigos_barras_adicionais'] ?? []);
        $dados_validados['fornecedores_vinculados']   = $this->normalizar_fornecedores_vinculados($dados_validados['fornecedores_vinculados'] ?? []);

        $this->validar_codigos_barras_unicos($dados_validados['codigos_barras_adicionais'], $produto_id);
        $this->validar_fornecedores_unicos($dados_validados['fornecedores_vinculados']);

        return $dados_validados;
    }

    /**
     * Remove linhas vazias de aliases e padroniza o payload recebido.
     *
     * @param array<int, array<string, mixed>> $codigos_barras_adicionais
     * @return array<int, array<string, mixed>>
     */
    private function normalizar_codigos_barras(array $codigos_barras_adicionais): array
    {
        return collect($codigos_barras_adicionais)
            ->map(function (array $codigo_barras): array {
                return [
                    'id'                  => $codigo_barras['id'] ?? null,
                    'codigo_barras'       => trim((string) ($codigo_barras['codigo_barras'] ?? '')),
                    'descricao_variacao'  => $codigo_barras['descricao_variacao'] ?? null,
                ];
            })
            ->filter(function (array $codigo_barras): bool {
                return $codigo_barras['codigo_barras'] !== '' || !empty($codigo_barras['descricao_variacao']);
            })
            ->values()
            ->all();
    }

    /**
     * Remove linhas vazias de vinculos de fornecedor e padroniza o payload recebido.
     *
     * @param array<int, array<string, mixed>> $fornecedores_vinculados
     * @return array<int, array<string, mixed>>
     */
    private function normalizar_fornecedores_vinculados(array $fornecedores_vinculados): array
    {
        return collect($fornecedores_vinculados)
            ->map(function (array $fornecedor): array {
                return [
                    'fornecedor_id'           => $fornecedor['fornecedor_id'] ?? null,
                    'codigo_sku_fornecedor'   => trim((string) ($fornecedor['codigo_sku_fornecedor'] ?? '')),
                    'unidade_embalagem'       => strtoupper(trim((string) ($fornecedor['unidade_embalagem'] ?? 'CX'))),
                    'fator_conversao'         => (int) ($fornecedor['fator_conversao'] ?? 1),
                    'custo_embalagem'         => isset($fornecedor['custo_embalagem']) ? (float) $fornecedor['custo_embalagem'] : null,
                ];
            })
            ->filter(function (array $fornecedor): bool {
                return !empty($fornecedor['fornecedor_id']);
            })
            ->values()
            ->all();
    }

    /**
     * Valida unicidade dos codigos de barras entre os aliases do formulario.
     *
     * @param array<int, array<string, mixed>> $codigos_barras_adicionais
     */
    private function validar_codigos_barras_unicos(array $codigos_barras_adicionais, ?int $produto_id): void
    {
        $codigos = collect($codigos_barras_adicionais)
            ->pluck('codigo_barras')
            ->filter()
            ->values();

        if ($codigos->count() !== $codigos->unique()->count()) {
            throw ValidationException::withMessages([
                'codigos_barras_adicionais' => ['Existem codigos de barras duplicados no formulario.'],
            ]);
        }
    }

    /**
     * Valida unicidade de fornecedor e preenchimento de SKU obrigatorio.
     *
     * @param array<int, array<string, mixed>> $fornecedores_vinculados
     */
    private function validar_fornecedores_unicos(array $fornecedores_vinculados): void
    {
        $fornecedor_ids = collect($fornecedores_vinculados)
            ->pluck('fornecedor_id')
            ->filter()
            ->values();

        if ($fornecedor_ids->count() !== $fornecedor_ids->unique()->count()) {
            throw ValidationException::withMessages([
                'fornecedores_vinculados' => ['O mesmo fornecedor nao pode ser vinculado mais de uma vez ao produto.'],
            ]);
        }

        foreach ($fornecedores_vinculados as $indice => $fornecedor_vinculado) {
            if (empty($fornecedor_vinculado['fornecedor_id'])) {
                throw ValidationException::withMessages([
                    "fornecedores_vinculados.$indice.fornecedor_id" => ['Selecione um fornecedor valido para o vinculo informado.'],
                ]);
            }

            if ($fornecedor_vinculado['codigo_sku_fornecedor'] === '') {
                throw ValidationException::withMessages([
                    "fornecedores_vinculados.$indice.codigo_sku_fornecedor" => ['Informe o codigo SKU do fornecedor.'],
                ]);
            }
        }
    }

    /**
     * Sincroniza os aliases de codigo de barras do produto.
     *
     * @param array<int, array<string, mixed>> $codigos_barras_adicionais
     */
    private function sincronizar_codigos_barras(Produto $produto, array $codigos_barras_adicionais): void
    {
        $ids_mantidos = [];

        foreach ($codigos_barras_adicionais as $codigo_barras_dados) {
            $codigo_barras = $produto->codigos_barras()
                ->whereKey($codigo_barras_dados['id'] ?? 0)
                ->first() ?? new ProdutoCodigoBarras();

            $codigo_barras->fill([
                'codigo_barras'      => $codigo_barras_dados['codigo_barras'],
                'descricao_variacao' => $codigo_barras_dados['descricao_variacao'],
            ]);

            $produto->codigos_barras()->save($codigo_barras);
            $ids_mantidos[] = $codigo_barras->id;
        }

        $consulta = $produto->codigos_barras();

        if (empty($ids_mantidos)) {
            $consulta->delete();
            return;
        }

        $consulta->whereNotIn('id', $ids_mantidos)->delete();
    }

    /**
     * Sincroniza os vinculos de fornecedores do produto, incluindo unidade_embalagem.
     *
     * @param array<int, array<string, mixed>> $fornecedores_vinculados
     */
    private function sincronizar_fornecedores_vinculados(Produto $produto, array $fornecedores_vinculados): void
    {
        $fornecedor_ids = [];

        foreach ($fornecedores_vinculados as $fornecedor_vinculado) {
            $dados_update = [
                'codigo_sku_fornecedor' => $fornecedor_vinculado['codigo_sku_fornecedor'],
                'unidade_embalagem'     => $fornecedor_vinculado['unidade_embalagem'],
                'fator_conversao'       => (int) $fornecedor_vinculado['fator_conversao'],
            ];

            // Persiste custo_embalagem como ultimo_preco_compra quando informado
            if (!empty($fornecedor_vinculado['custo_embalagem']) && (float) $fornecedor_vinculado['custo_embalagem'] > 0) {
                $dados_update['ultimo_preco_compra'] = (float) $fornecedor_vinculado['custo_embalagem'];
            }

            ProdutoFornecedor::query()->updateOrCreate(
                [
                    'produto_id'    => $produto->id,
                    'fornecedor_id' => $fornecedor_vinculado['fornecedor_id'],
                ],
                $dados_update
            );

            $fornecedor_ids[] = (int) $fornecedor_vinculado['fornecedor_id'];
        }

        $consulta = ProdutoFornecedor::query()->where('produto_id', $produto->id);

        if (empty($fornecedor_ids)) {
            $consulta->delete();
            return;
        }

        $consulta->whereNotIn('fornecedor_id', $fornecedor_ids)->delete();
    }

    /**
     * Mapeia o produto para o payload enxuto de listagem.
     *
     * @return array<string, mixed>
     */
    private function mapear_produto_listagem(Produto $produto): array
    {
        $codigos_barras = $produto->codigos_barras
            ->pluck('codigo_barras')
            ->values()
            ->all();
        $estoque_por_fornecedor = $this->mapear_lotes_estoque($produto);
        $data_validade_proxima = collect($estoque_por_fornecedor)
            ->pluck('data_validade')
            ->filter()
            ->sort()
            ->first();

        return [
            'id'                              => $produto->id,
            'nome_produto'                    => $produto->nome_produto,
            'codigo_interno'                  => $produto->codigo_interno,
            'unidade_medida'                  => $produto->unidade_medida,
            'codigo_barras_principal'         => $codigos_barras[0] ?? null,
            'codigos_barras'                  => $codigos_barras,
            'preco_venda'                     => $produto->preco_venda,
            'preco_custo_medio'               => $produto->preco_custo_medio,
            'margem_lucro_percentual'         => $produto->margem_lucro_percentual,
            'categoria'                       => $produto->categoria,
            'estoque_atual'                   => $produto->estoque_atual,
            'data_validade'                   => $data_validade_proxima,
            'quantidade_fornecedores_vinculados' => $produto->produto_fornecedores->count(),
            'pode_expandir_estoque'           => count($estoque_por_fornecedor) > 1,
            'estoque_por_fornecedor'          => $estoque_por_fornecedor,
        ];
    }

    /**
     * Mapeia o produto para o payload completo de edicao.
     *
     * @return array<string, mixed>
     */
    private function mapear_produto_detalhes(Produto $produto): array
    {
        $produto->loadMissing(['codigos_barras', 'produto_fornecedores.fornecedor', 'estoque_lotes.fornecedor']);

        return array_merge(
            $this->mapear_produto_listagem($produto),
            [
                'codigos_barras_adicionais' => $produto->codigos_barras
                    ->map(function (ProdutoCodigoBarras $codigo_barras): array {
                        return [
                            'id'                 => $codigo_barras->id,
                            'codigo_barras'      => $codigo_barras->codigo_barras,
                            'descricao_variacao' => $codigo_barras->descricao_variacao,
                        ];
                    })
                    ->values()
                    ->all(),
                'fornecedores_vinculados' => $produto->produto_fornecedores
                    ->map(function (ProdutoFornecedor $produto_fornecedor): array {
                        return [
                            'fornecedor_id'          => $produto_fornecedor->fornecedor_id,
                            'nome_fantasia'           => $produto_fornecedor->fornecedor?->nome_fantasia,
                            'codigo_sku_fornecedor'  => $produto_fornecedor->codigo_sku_fornecedor,
                            'unidade_embalagem'      => $produto_fornecedor->unidade_embalagem ?? 'CX',
                            'fator_conversao'        => $produto_fornecedor->fator_conversao,
                            'ultimo_preco_compra'    => $produto_fornecedor->ultimo_preco_compra,
                        ];
                    })
                    ->values()
                    ->all(),
            ]
        );
    }

    /**
     * Lista os lotes ativos do produto preservando fornecedor e validade por entrada.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapear_lotes_estoque(Produto $produto): array
    {
        $lotes = $produto->estoque_lotes
            ->filter(function (ProdutoEstoqueLote $lote): bool {
                return (int) $lote->quantidade_atual > 0;
            })
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ]);

        return $lotes
            ->map(function (ProdutoEstoqueLote $lote): array {
                return [
                    'lote_id'           => $lote->id,
                    'fornecedor_id'     => $lote->fornecedor_id,
                    'nome_fornecedor'   => $lote->fornecedor?->nome_fantasia,
                    'nome_exibicao'     => $lote->fornecedor?->nome_fantasia ?: 'Producao Interna / Sem Fornecedor',
                    'quantidade_atual'  => (int) $lote->quantidade_atual,
                    'preco_custo_medio' => round((float) $lote->custo_unitario_medio, 2),
                    'data_validade'     => $lote->data_validade,
                    'primeira_entrada_em' => optional($lote->created_at)->toDateTimeString(),
                ];
            })
            ->sortBy('primeira_entrada_em')
            ->values()
            ->all();
    }
}