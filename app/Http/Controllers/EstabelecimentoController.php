<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class EstabelecimentoController extends Controller
{
    /**
     * Lista todos os bares cadastrados no sistema central.
     * @return JsonResponse
     */
    public function listar_estabelecimentos(): JsonResponse
    {
        try {
            $todos_os_bares = Tenant::with('domains')->get();
            return response()->json(['sucesso' => true, 'dados' => $todos_os_bares], 200);
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()], 500);
        }
    }

    /**
     * Registra um novo bar e popula o banco com dados iniciais.
     * @param Request $requisicao
     * @return JsonResponse
     */
    public function registrar_novo_bar(Request $requisicao): JsonResponse
    {
        try {
            $dados = $requisicao->validate([
                'id_do_bar'  => 'required|string|unique:tenants,id',
                'dominio'    => 'required|string|unique:domains,domain',
                'nome_dono'  => 'required|string',
                'email_dono' => 'required|email',
                'senha_dono' => 'required|string|min:6'
            ]);

            // 1. Cria o registro central e o banco físico
            $novo_bar = Tenant::create(['id' => $dados['id_do_bar']]);
            $novo_bar->domains()->create(['domain' => $dados['dominio']]);

            // 2. Entra no banco do bar e cria o usuário DONO com os dados recebidos
            $novo_bar->run(function () use ($dados) {
                User::create([
                    'name' => $dados['nome_dono'],
                    'email' => $dados['email_dono'],
                    'password' => Hash::make($dados['senha_dono']),
                    'tipo_usuario' => 'cliente' // Nível de acesso do dono do bar
                ]);
            });

            return response()->json(['sucesso' => true, 'mensagem' => 'Bar e Usuário Dono criados!'], 201);

        } catch (\Exception $e) {
            Log::error("Falha SaaS: " . $e->getMessage());
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()], 500);
        }
    }

    /**
     * Gera um token de API (Sanctum) para login temporário no modo suporte.2
     * @param string $tenant_id
     * @return JsonResponse
     */
    public function gerar_link_suporte(string $tenant_id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($tenant_id);
            $dominio = $tenant->domains()->first()->domain;

            // Entra no banco do cliente e gera um Token de API real
            $dados_suporte = $tenant->run(function () {
                $usuario = User::first();
                if (!$usuario) {
                    throw new \Exception('O banco deste bar está vazio (sem utilizadores).');
                }
                
                // Apaga tokens antigos de suporte para manter a base de dados limpa
                $usuario->tokens()->where('name', 'token_suporte')->delete();
                
                return [
                    'token' => $usuario->createToken('token_suporte')->plainTextToken,
                    'nome' => $usuario->name
                ];
            });

            return response()->json([
                'sucesso' => true,
                'token' => $dados_suporte['token'],
                'nome_dono' => $dados_suporte['nome'],
                'api_url' => "http://{$dominio}:8000/api" // Define a URL dinâmica para o Axios
            ], 200);

        } catch (\Exception $e) {
            Log::error("Erro no Suporte Nitec: " . $e->getMessage());
            return response()->json(['sucesso' => false, 'erro' => $e->getMessage()], 500);
        }
    }
}