<?php
// C:\PDP\nitec_api\app\Http\Controllers\Auth\AutenticacaoController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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

        return response()->json([
            'status' => true,
            'token' => $token_nitec,
            'usuario' => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'tipo_usuario' => $usuario->tipo_usuario,
            ]
        ], 200);
    }
}