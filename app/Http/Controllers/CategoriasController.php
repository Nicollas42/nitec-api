<?php

namespace App\Http\Controllers;

use App\Models\CategoriaProduto;
use App\Models\Produto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoriasController extends Controller
{
    public function listar(): JsonResponse
    {
        $categorias = CategoriaProduto::orderBy('nome')->pluck('nome');
        return response()->json(['sucesso' => true, 'categorias' => $categorias]);
    }

    public function criar(Request $request): JsonResponse
    {
        $nome = trim((string) $request->input('nome'));
        $request->merge(['nome' => $nome]);

        $request->validate([
            'nome' => ['required', 'string', 'max:80', Rule::unique('categorias_produtos', 'nome')],
        ]);

        $categoria = CategoriaProduto::create(['nome' => $nome]);

        return response()->json(['sucesso' => true, 'categoria' => $categoria->nome], 201);
    }

    public function excluir(string $nome): JsonResponse
    {
        if ($nome === 'Geral') {
            return response()->json(['mensagem' => 'A categoria "Geral" não pode ser removida.'], 422);
        }

        DB::transaction(function () use ($nome): void {
            $categoria = CategoriaProduto::where('nome', $nome)->firstOrFail();

            Produto::query()
                ->where('categoria', $categoria->nome)
                ->update(['categoria' => 'Geral']);

            $categoria->delete();
        });

        return response()->json(['sucesso' => true]);
    }
}
