<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class FuncionarioController extends Controller
{
    public function listar_funcionarios()
    {
        $funcionarios = User::orderBy('status_conta', 'asc')->orderBy('name', 'asc')->get();

        $agora = Carbon::now();
        $precisam_desativar = false;

        foreach ($funcionarios as $func) {
            if ($func->status_conta === 'ativo' && $func->expiracao_acesso && $func->expiracao_acesso <= $agora) {
                $func->status_conta = 'inativo';
                $func->save();
                $precisam_desativar = true;
            }
        }

        if ($precisam_desativar) {
            $funcionarios = User::orderBy('status_conta', 'asc')->orderBy('name', 'asc')->get();
        }

        return response()->json(['sucesso' => true, 'funcionarios' => $funcionarios]);
    }

    public function cadastrar_funcionario(Request $requisicao)
    {
        $regras = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telefone' => 'nullable|string|max:20', // 🟢 NOVO
            'password' => 'required|string|min:6',
            'tipo_usuario' => 'required|in:dono,caixa,garcom',
            'tipo_contrato' => 'required|in:fixo,temporario'
        ];

        $dados = $requisicao->validate($regras, ['email.unique' => 'Este e-mail já está em uso.']);

        $expiracao = null;
        if ($dados['tipo_contrato'] === 'temporario' && $requisicao->filled('horas_validade')) {
            $expiracao = Carbon::now()->addHours($requisicao->horas_validade);
        }

        $funcionario = User::create([
            'name' => $dados['name'],
            'email' => $dados['email'],
            'telefone' => $dados['telefone'] ?? null, // 🟢 NOVO
            'password' => Hash::make($dados['password']),
            'tipo_usuario' => $dados['tipo_usuario'],
            'status_conta' => 'ativo',
            'tipo_contrato' => $dados['tipo_contrato'],
            'expiracao_acesso' => $expiracao
        ]);

        return response()->json(['sucesso' => true, 'mensagem' => 'Cadastrado com sucesso!', 'funcionario' => $funcionario], 201);
    }

    // 🟢 NOVA FUNÇÃO: EDITAR
    public function editar_funcionario(Request $requisicao, $id)
    {
        $funcionario = User::findOrFail($id);

        $regras = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id, // Ignora o próprio ID na checagem
            'telefone' => 'nullable|string|max:20',
            'tipo_usuario' => 'required|in:dono,caixa,garcom',
            'tipo_contrato' => 'required|in:fixo,temporario'
        ];

        // Só exige senha se o dono preencheu alguma coisa (se quiser mudar)
        if ($requisicao->filled('password')) {
            $regras['password'] = 'string|min:6';
        }

        $dados = $requisicao->validate($regras, ['email.unique' => 'Este e-mail já está em uso por outro funcionário.']);

        $funcionario->name = $dados['name'];
        $funcionario->email = $dados['email'];
        $funcionario->telefone = $dados['telefone'] ?? null;
        $funcionario->tipo_usuario = $dados['tipo_usuario'];
        $funcionario->tipo_contrato = $dados['tipo_contrato'];

        if ($requisicao->filled('password')) {
            $funcionario->password = Hash::make($dados['password']);
        }

        // Se mudou para temporário ou renovou o timer
        if ($dados['tipo_contrato'] === 'temporario' && $requisicao->filled('horas_validade')) {
            $funcionario->expiracao_acesso = Carbon::now()->addHours($requisicao->horas_validade);
        } elseif ($dados['tipo_contrato'] === 'fixo') {
            $funcionario->expiracao_acesso = null;
        }

        $funcionario->save();

        return response()->json(['sucesso' => true, 'mensagem' => 'Atualizado com sucesso!', 'funcionario' => $funcionario]);
    }

    // 🟢 NOVA FUNÇÃO: ALTERNAR STATUS (Ativar/Desativar)

    public function alternar_status($id)
    {
        $funcionario = User::findOrFail($id);
        
        if ($funcionario->id === request()->user()->id) {
             return response()->json(['sucesso' => false, 'mensagem' => 'Não pode desativar a sua própria conta.'], 403);
        }

        $novo_status = $funcionario->status_conta === 'ativo' ? 'inativo' : 'ativo';
        $funcionario->status_conta = $novo_status;
        
        if($novo_status === 'inativo') $funcionario->expiracao_acesso = null;

        $funcionario->save();

        return response()->json(['sucesso' => true, 'novo_status' => $novo_status]);
    }

    // 🟢 NOVA FUNÇÃO: DEMITIR (Arquivar)
    public function demitir($id)
    {
        $funcionario = User::findOrFail($id);
        
        if ($funcionario->id === request()->user()->id || $funcionario->tipo_usuario === 'dono') {
             return response()->json(['sucesso' => false, 'mensagem' => 'Não é possível demitir o dono ou a si mesmo.'], 403);
        }

        $funcionario->status_conta = 'demitido';
        $funcionario->expiracao_acesso = null;
        $funcionario->save();

        return response()->json(['sucesso' => true, 'mensagem' => 'Funcionário arquivado. O histórico de vendas foi mantido.']);
    }

    // 🟢 NOVA FUNÇÃO: READMITIR (Restaurar)
    public function readmitir($id)
    {
        $funcionario = User::findOrFail($id);
        $funcionario->status_conta = 'inativo'; // Volta como inativo por segurança
        $funcionario->save();

        return response()->json(['sucesso' => true, 'mensagem' => 'Funcionário readmitido. Ative o acesso para que ele possa logar.']);
    }
}