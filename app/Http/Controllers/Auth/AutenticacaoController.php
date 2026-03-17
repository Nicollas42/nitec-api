<?php
// C:\PDP\nitec_api\app\Http\Controllers\Auth\AutenticacaoController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\PerfilPermissao;

class AutenticacaoController extends Controller
{
    /**
     * Valida as credenciais estritamente via API e retorna o token Sanctum.
     * @param  \Illuminate\Http\Request  $requisicao
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $requisicao)
    {
        $credenciais = $requisicao->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // NOVIDADE: Verifica se o Tenant atual foi desativado pelo Super Admin
        if (tenant() && !tenant('ativo')) {
            return response()->json([
                'status' => false,
                'mensagem' => 'O acesso ao seu sistema foi suspenso. Contacte o suporte Nitec.'
            ], 403);
        }

        $usuario = User::where('email', $credenciais['email'])->first();

        if (!$usuario || !Hash::check($credenciais['password'], $usuario->password)) {
            return response()->json([
                'status' => false,
                'mensagem' => 'Credenciais inválidas. Verifique o seu e-mail e password.'
            ], 401);
        }

        $token_nitec = $usuario->createToken('token_acesso_nitec')->plainTextToken;

        $permissoes = [];
        if (tenant() && !in_array($usuario->tipo_usuario, ['admin_master', 'dono'])) {
            $perfil = PerfilPermissao::where('perfil', $usuario->tipo_usuario)->first();
            if ($perfil) {
                $permissoes = $perfil->permissoes;
            } else {
                $permissoes = [
                    'acessar_pdv' => $usuario->tipo_usuario === 'caixa' || $usuario->tipo_usuario === 'gerente',
                    'acessar_mesas' => true,
                    'acessar_comandas' => true,
                    'cancelar_vendas' => $usuario->tipo_usuario === 'gerente',
                    'aplicar_desconto' => $usuario->tipo_usuario === 'caixa' || $usuario->tipo_usuario === 'gerente',
                    'gerenciar_produtos' => $usuario->tipo_usuario === 'gerente',
                    'gerenciar_equipe' => $usuario->tipo_usuario === 'gerente',
                    'ver_analises' => $usuario->tipo_usuario === 'gerente',
                ];
            }
        }

        return response()->json([
            'status' => true,
            'token' => $token_nitec,
            'usuario' => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'tipo_usuario' => $usuario->tipo_usuario,
                'permissoes' => $permissoes
            ]
        ], 200);
    }
}