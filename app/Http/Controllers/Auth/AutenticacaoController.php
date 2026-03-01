<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AutenticacaoController extends Controller
{
    /**
     * Valida as credenciais do banco e retorna o token Sanctum.
     * * @param  \Illuminate\Http\Request  $requisicao
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $requisicao)
    {
        // Validação usando snake_case internamente conforme padrão
        $credenciais = $requisicao->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credenciais)) {
            $usuario = Auth::user();
            
            // Criando o token para o aplicativo Electron/Mobile
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

        return response()->json([
            'status' => false,
            'mensagem' => 'E-mail ou senha incorretos.'
        ], 401);
    }
}