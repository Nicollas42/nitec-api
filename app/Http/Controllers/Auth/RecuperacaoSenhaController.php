<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\Tenant;

class RecuperacaoSenhaController extends Controller
{
    public function solicitar_link(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'loja' => 'required|string'
        ]);

        $email = $request->email;
        $loja = $request->loja;

        $eh_local = app()->environment('local');
        $base_url = $eh_local ? 'nitec.localhost:5173' : 'nitec.dev.br';
        $protocolo = $eh_local ? 'http://' : 'https://';

        if ($loja === 'master') {
            $usuario = User::where('email', $email)->first();
            $dominio_link = $base_url; 
        } else {
            $tenant = Tenant::find($loja);
            if (!$tenant) {
                return response()->json(['sucesso' => false, 'mensagem' => 'Código da loja não encontrado.'], 404);
            }

            tenancy()->initialize($tenant);
            $usuario = User::where('email', $email)->first();
            $dominio_link = "{$loja}.{$base_url}"; 
            tenancy()->end();
        }

        if (!$usuario) {
            return response()->json(['sucesso' => true, 'mensagem' => 'Se o e-mail existir, receberá um link em breve.']);
        }

        $token = Str::random(60);

        // CORREÇÃO: Salva SEMPRE no banco Central (Master). Evita o erro 500 de conexão fechada.
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => $token, 'created_at' => now()]
        );

        $link_recuperacao = "{$protocolo}{$dominio_link}/#/redefinir-senha?token={$token}&email={$email}";

        Mail::raw(
            "Olá {$usuario->name},\n\nRecebemos um pedido para redefinir a sua senha no NitecSystem.\n\n" .
            "Clique no link abaixo para criar uma nova senha (será necessário confirmar o seu CPF/CNPJ):\n" .
            "{$link_recuperacao}\n\n" .
            "Se você não solicitou esta alteração, ignore este e-mail.", 
            function ($mensagem) use ($email) {
                $mensagem->to($email)
                         ->subject('Recuperação de Senha - NitecSystem');
            }
        );

        return response()->json(['sucesso' => true, 'mensagem' => 'Instruções enviadas para o seu e-mail!']);
    }


    public function resetar_senha(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'documento' => 'required', // CPF ou CNPJ informado na tela
            'password' => 'required|min:6|confirmed',
        ]);

        // 1. Valida o Token no banco central
        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$reset) {
            return response()->json(['sucesso' => false, 'mensagem' => 'Link inválido ou expirado.'], 400);
        }

        // 2. Localiza o usuário e valida o documento (CPF/CNPJ)
        // Tentamos no banco Master e, se não achar, varremos os Tenants
        $usuario = \App\Models\User::where('email', $request->email)->first();
        $tenant_vinculado = null;

        if (!$usuario) {
            $tenants = \App\Models\Tenant::all();
            foreach ($tenants as $t) {
                tenancy()->initialize($t);
                $u = \App\Models\User::where('email', $request->email)->first();
                if ($u) {
                    $usuario = $u;
                    $tenant_vinculado = $t;
                    break;
                }
                tenancy()->end();
            }
        }

        // Validação de segurança: o documento informado deve bater com o salvo no Tenant
        if ($tenant_vinculado) {
            $doc_salvo = preg_replace('/\D/', '', $tenant_vinculado->cnpj);
            $doc_informado = preg_replace('/\D/', '', $request->documento);

            if ($doc_salvo !== $doc_informado) {
                return response()->json(['sucesso' => false, 'mensagem' => 'Documento não confere.'], 403);
            }
            
            $tenant_vinculado->update(['senha_inicial' => $request->password]);
        }

        // 3. Atualiza a senha e limpa o token
        $usuario->password = \Illuminate\Support\Facades\Hash::make($request->password);
        $usuario->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['sucesso' => true, 'mensagem' => 'Senha alterada com sucesso!']);
    }
}