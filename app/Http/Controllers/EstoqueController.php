<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\ProdutoFornecedor;
use App\Services\GestaoEstoqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EstoqueController extends Controller
{
    /**
     * Servico central para entradas, perdas e baixa FIFO por lote.
     *
     * @var GestaoEstoqueService
     */
    private GestaoEstoqueService $gestaoEstoqueService;

    /**
     * Injeta o servico de gestao de estoque na controller.
     */
    public function __construct(GestaoEstoqueService $gestaoEstoqueService)
    {
        $this->gestaoEstoqueService = $gestaoEstoqueService;
    }

    /**
     * Registra uma perda de estoque consumindo os lotes mais antigos primeiro.
     */
    public function registrar_perda(Request $request): JsonResponse
    {
        $dados_validados = $request->validate([
            'produto_id' => 'required|exists:produtos,id',
            'quantidade' => 'required|integer|min:1',
            'motivo' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $produto = Produto::query()->lockForUpdate()->findOrFail($dados_validados['produto_id']);

            $this->gestaoEstoqueService->registrar_perda(
                $produto,
                $request->user()->id,
                (int) $dados_validados['quantidade'],
                $dados_validados['motivo']
            );

            DB::commit();

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Baixa registrada com sucesso.',
            ]);
        } catch (ValidationException $validation_exception) {
            DB::rollBack();
            throw $validation_exception;
        } catch (\Throwable $throwable) {
            DB::rollBack();

            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Erro ao registrar perda.',
            ], 500);
        }
    }

    /**
     * Registra uma entrada de estoque por fornecedor ou um ajuste operacional do PDV.
     */
    public function registrar_entrada(Request $request): JsonResponse
    {
        $dados_validados = $request->validate([
            'modo_entrada' => 'required|string|in:compra_fornecedor,ajuste_pdv,ajuste_manual',
            'produto_id' => 'required|exists:produtos,id',
            'fornecedor_id' => 'nullable|exists:fornecedores,id',
            'quantidade_comprada' => 'required|integer|min:1',
            'custo_unitario_compra' => 'required|numeric|min:0',
            'data_validade_lote' => 'nullable|date',
        ]);

        try {
            $dados_resposta = [];

            DB::transaction(function () use ($request, $dados_validados, &$dados_resposta): void {
                $produto = Produto::query()->lockForUpdate()->findOrFail($dados_validados['produto_id']);
                $fornecedor_vinculado = $this->obter_fornecedor_vinculado($dados_validados);
                $dados_resposta = $this->gestaoEstoqueService->registrar_entrada(
                    $produto,
                    $fornecedor_vinculado,
                    $request->user()->id,
                    $dados_validados['modo_entrada'],
                    (int) $dados_validados['quantidade_comprada'],
                    (float) $dados_validados['custo_unitario_compra'],
                    $dados_validados['data_validade_lote'] ?? null
                );
            });

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Entrada de estoque registrada com sucesso.',
                'dados' => $dados_resposta,
            ]);
        } catch (ValidationException $validation_exception) {
            throw $validation_exception;
        } catch (\Throwable $throwable) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Erro ao registrar entrada.',
            ], 500);
        }
    }

    /**
     * Recupera o vinculo produto-fornecedor quando a entrada exige compra por embalagem.
     */
    private function obter_fornecedor_vinculado(array $dados_validados): ?ProdutoFornecedor
    {
        if (in_array($dados_validados['modo_entrada'], ['ajuste_pdv', 'ajuste_manual'], true)) {
            return null;
        }

        if (empty($dados_validados['fornecedor_id'])) {
            throw ValidationException::withMessages([
                'fornecedor_id' => ['Selecione um fornecedor para registrar a compra.'],
            ]);
        }

        $fornecedor_vinculado = ProdutoFornecedor::query()
            ->where('produto_id', $dados_validados['produto_id'])
            ->where('fornecedor_id', $dados_validados['fornecedor_id'])
            ->first();

        if (!$fornecedor_vinculado instanceof ProdutoFornecedor) {
            throw ValidationException::withMessages([
                'fornecedor_id' => ['O fornecedor selecionado nao esta vinculado a este produto.'],
            ]);
        }

        return $fornecedor_vinculado;
    }

}
