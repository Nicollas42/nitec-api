<?php

namespace App\Http\Controllers;

use App\Models\Fornecedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FornecedorController extends Controller
{
    /**
     * Lista os fornecedores ativos e inativos para selecao no front-end.
     */
    public function listar_fornecedores(): JsonResponse
    {
        $fornecedores = Fornecedor::query()
            ->orderBy('nome_fantasia')
            ->get([
                'id',
                'nome_fantasia',
                'razao_social',
                'cnpj',
                'telefone',
                'email',
                'vendedor',
                'contato_vendedor',
                'status_fornecedor',
            ]);

        return response()->json([
            'fornecedores' => $fornecedores,
        ]);
    }

    /**
     * Cadastra um novo fornecedor para uso imediato na tela de produtos.
     */
    public function cadastrar_fornecedor(Request $request): JsonResponse
    {
        $dados_validados = $this->validar_payload_fornecedor($request);

        $fornecedor = Fornecedor::create($dados_validados);

        return response()->json([
            'status' => true,
            'mensagem' => 'Fornecedor cadastrado com sucesso.',
            'fornecedor' => $fornecedor,
        ], 201);
    }

    /**
     * Atualiza um fornecedor existente sem sair da tela de gestao.
     */
    public function atualizar_fornecedor(Request $request, int $id): JsonResponse
    {
        $fornecedor = Fornecedor::query()->findOrFail($id);
        $dados_validados = $this->validar_payload_fornecedor($request, $fornecedor->id);

        $fornecedor->update($dados_validados);

        return response()->json([
            'status' => true,
            'mensagem' => 'Fornecedor atualizado com sucesso.',
            'fornecedor' => $fornecedor->fresh(),
        ]);
    }

    /**
     * Exclui um fornecedor sem historico operacional nem vinculos ativos.
     */
    public function excluir_fornecedor(int $id): JsonResponse
    {
        $fornecedor = Fornecedor::query()
            ->withCount(['produto_fornecedores', 'estoque_entradas', 'estoque_lotes'])
            ->findOrFail($id);

        if ($fornecedor->produto_fornecedores_count > 0) {
            throw ValidationException::withMessages([
                'fornecedor' => ['Nao e possivel excluir este fornecedor porque ele ainda esta vinculado a um ou mais produtos.'],
            ]);
        }

        if ($fornecedor->estoque_entradas_count > 0) {
            throw ValidationException::withMessages([
                'fornecedor' => ['Nao e possivel excluir este fornecedor porque ja existe historico de entradas vinculado a ele.'],
            ]);
        }

        if ($fornecedor->estoque_lotes_count > 0) {
            throw ValidationException::withMessages([
                'fornecedor' => ['Nao e possivel excluir este fornecedor porque ainda existem saldos ou historico de estoque vinculados a ele.'],
            ]);
        }

        $fornecedor->delete();

        return response()->json([
            'status' => true,
            'mensagem' => 'Fornecedor removido com sucesso.',
        ]);
    }

    /**
     * Valida o payload do fornecedor para criacao ou atualizacao.
     *
     * @return array<string, mixed>
     */
    private function validar_payload_fornecedor(Request $request, ?int $fornecedor_id = null): array
    {
        return $request->validate([
            'nome_fantasia' => 'required|string|max:255',
            'razao_social' => 'required|string|max:255',
            'cnpj' => 'required|string|max:30|unique:fornecedores,cnpj,' . $fornecedor_id,
            'telefone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'vendedor' => 'nullable|string|max:255',
            'contato_vendedor' => 'nullable|string|max:255',
            'status_fornecedor' => 'required|string|in:ativo,inativo',
        ]);
    }
}
