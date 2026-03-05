<?php
// C:\PDP\nitec_api\app\Http\Controllers\EstabelecimentoController.php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class EstabelecimentoController extends Controller
{
    /**
     * Lista todos os estabelecimentos separados por status (Ativos e Inativos).
     */
    public function listar_estabelecimentos()
    {
        $todos = Tenant::with('domains')->get();

        // Função para forçar a extração dos dados mágicos do pacote Tenancy
        $formatar_tenant = function ($tenant) {
            return [
                'id' => $tenant->id,
                'ativo' => $tenant->ativo,
                'nome_dono' => $tenant->nome_dono,         // Extrai do JSON
                'email_dono' => $tenant->email_dono,       // Extrai do JSON
                'senha_inicial' => $tenant->senha_inicial, // Extrai do JSON
                'cnpj' => $tenant->cnpj,                   // Extrai do JSON
                'telefone' => $tenant->telefone,           // Extrai do JSON
                'domains' => $tenant->domains,
            ];
        };

        return response()->json([
            'sucesso' => true,
            'ativos' => $todos->where('ativo', true)->map($formatar_tenant)->values(),
            'inativos' => $todos->where('ativo', false)->map($formatar_tenant)->values(),
        ]);
    }

    /**
     * Provisiona um novo cliente SaaS na infraestrutura multi-tenant.
     */
    public function registrar_novo_bar(Request $request)
    {
        $dados = $request->validate([
            'id_do_bar' => 'required|string|unique:tenants,id',
            'dominio' => 'required|string|unique:domains,domain',
            'nome_dono' => 'required|string',
            'email_dono' => 'required|email',
            'senha_dono' => 'required|string|min:6',
            'cnpj' => 'nullable|string',
            'telefone' => 'nullable|string',
        ]);

        // 1. Cria o Inquilino na base central
        $tenant = Tenant::create([
            'id' => $dados['id_do_bar'],
            'ativo' => true, 
            'nome_dono' => $dados['nome_dono'],
            'email_dono' => $dados['email_dono'],
            'senha_inicial' => $dados['senha_dono'], 
            'cnpj' => $dados['cnpj'] ?? 'Não informado',
            'telefone' => $dados['telefone'] ?? 'Não informado',
        ]);

        // 2. Associa o Domínio
        $tenant->domains()->create(['domain' => $dados['dominio']]);

        // 3. O PULO DO GATO: Entramos no banco do cliente recém-criado e inserimos o dono
        $tenant->run(function () use ($dados) {
            \App\Models\User::create([
                'name' => $dados['nome_dono'],
                'email' => $dados['email_dono'],
                'password' => \Illuminate\Support\Facades\Hash::make($dados['senha_dono']),
                'tipo_usuario' => 'dono', // Define o cargo máximo no PDV
            ]);
        });

        return response()->json(['sucesso' => true, 'mensagem' => 'Cliente provisionado!']);
    }

    /**
     * Ativa ou desativa o acesso de um cliente SaaS.
     */
    public function alternar_status(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);
        
        // Inverte o status atual
        $tenant->ativo = !$tenant->ativo;
        $tenant->save();

        return response()->json([
            'sucesso' => true, 
            'mensagem' => $tenant->ativo ? 'Acesso do cliente Reativado com sucesso!' : 'Acesso do cliente Desativado!'
        ]);
    }

    /**
     * Gera um token mágico de acesso para o modo de suporte.
     */
    public function gerar_link_suporte($tenant_id)
    {
        $tenant = \App\Models\Tenant::find($tenant_id);
        
        if (!$tenant) {
            return response()->json(['sucesso' => false, 'mensagem' => 'Cliente não encontrado.'], 404);
        }

        // Recupera o domínio para enviarmos ao Frontend
        $dominio = $tenant->domains()->first()->domain;
        
        // 1. O PULO DO GATO: Inicializamos o banco do Inquilino!
        tenancy()->initialize($tenant);

        // 2. Procuramos quem é o dono desta loja específica
        $dono_da_loja = \App\Models\User::where('tipo_usuario', 'dono')->first();
        
        if (!$dono_da_loja) {
            tenancy()->end();
            return response()->json(['sucesso' => false, 'mensagem' => 'Dono da loja não localizado.'], 404);
        }

        // 3. Geramos o token DENTRO da tabela personal_access_tokens do Inquilino
        // Disfarçamo-nos de dono da loja temporariamente!
        $token_suporte = $dono_da_loja->createToken('acesso_suporte_admin')->plainTextToken;
        $nome_dono = $dono_da_loja->name;

        // 4. Fechamos a conexão com o Inquilino
        tenancy()->end();

        // 5. Devolvemos o token mágico para o Vue entrar pela porta da frente
        $eh_local = app()->environment('local');
        $protocolo = $eh_local ? 'http://' : 'https://';
        $porta = $eh_local ? ':8000' : '';

        return response()->json([
            'sucesso' => true,
            'token' => $token_suporte,
            'api_url' => "{$protocolo}{$dominio}{$porta}/api",
            'nome_dono' => $nome_dono
        ]);
    }


    /**
     * Exclui PERMANENTEMENTE o cliente, o seu domínio e o seu banco de dados.
     */
    public function excluir_bar($id)
    {
        $tenant = Tenant::findOrFail($id);
        
        // O método delete() do stancl/tenancy cuida de apagar o banco de dados tenant_xxx
        $tenant->delete(); 

        return response()->json([
            'sucesso' => true, 
            'mensagem' => 'Cliente e banco de dados excluídos permanentemente!'
        ]);
    }
}