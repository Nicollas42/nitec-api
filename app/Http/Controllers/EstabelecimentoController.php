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

        $tenant = Tenant::create([
            'id' => $dados['id_do_bar'],
            'ativo' => true, 
            'nome_dono' => $dados['nome_dono'],
            'email_dono' => $dados['email_dono'],
            'senha_inicial' => $dados['senha_dono'], // AGORA SALVAMOS A SENHA INICIAL AQUI!
            'cnpj' => $dados['cnpj'] ?? 'Não informado',
            'telefone' => $dados['telefone'] ?? 'Não informado',
        ]);

        $tenant->domains()->create(['domain' => $dados['dominio']]);

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
    public function gerar_link_suporte(Request $request, $tenant_id)
    {
        $tenant = Tenant::findOrFail($tenant_id);

        if (!$tenant->ativo) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Não é possível aceder ao suporte de um cliente desativado.'
            ], 403);
        }

        // Simulação da geração de token. O ideal é você ter um sistema de 
        // impersonation (representação de usuário) nativo do Sanctum ou gerar um token com prazo curto.
        $token_temporario = "suporte_temp_" . bin2hex(random_bytes(16));

        return response()->json([
            'sucesso' => true,
            'token' => $token_temporario,
            'nome_dono' => $tenant->nome_dono ?? 'Lojista',
            'api_url' => "http://{$tenant->id}.nitec.localhost:8000/api"
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