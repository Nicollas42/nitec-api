<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PerfilPermissao;
use Illuminate\Support\Facades\DB;

class PermissaoController extends Controller
{
    /**
     * Retorna a matriz de permissões atual. Se não existir, retorna um padrão.
     */
    public function index()
    {
        $perfis = ['caixa', 'garcom', 'gerente'];
        $matriz = [];

        foreach ($perfis as $perfil) {
            $registro = PerfilPermissao::where('perfil', $perfil)->first();
            
            if ($registro) {
                // Junta a string do perfil com o array dinâmico de permissões
                $dados = $registro->permissoes;
                $dados['perfil'] = $perfil;
                $matriz[] = $dados;
            } else {
                // Caso ainda não tenha no banco, retorna o padrão e depois será salvo
                $padrao = [
                    'perfil' => $perfil,
                    'acessar_pdv' => $perfil === 'caixa' || $perfil === 'gerente',
                    'acessar_mesas' => true,
                    'acessar_comandas' => true,
                    'cancelar_vendas' => $perfil === 'gerente',
                    'aplicar_desconto' => $perfil === 'caixa' || $perfil === 'gerente',
                    'gerenciar_produtos' => $perfil === 'gerente',
                    'gerenciar_equipe'   => $perfil === 'gerente',
                    'ver_analises'       => $perfil === 'gerente',
                    'gerenciar_cardapio' => $perfil === 'gerente',
                ];
                $matriz[] = $padrao;
            }
        }

        return response()->json($matriz);
    }

    /**
     * Atualiza a matriz de permissões.
     */
    public function store(Request $request)
    {
        $matriz = $request->input('matriz', []);

        DB::beginTransaction();
        try {
            foreach ($matriz as $item) {
                if (!isset($item['perfil'])) continue;
                
                $perfil = $item['perfil'];
                $permissoes = $item;
                unset($permissoes['perfil']); // Remove a string 'perfil', deixando só os booleanos

                PerfilPermissao::updateOrCreate(
                    ['perfil' => $perfil],
                    ['permissoes' => $permissoes]
                );
            }
            DB::commit();
            return response()->json(['mensagem' => 'Permissões salvas com sucesso!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['mensagem' => 'Erro ao salvar permissões.', 'erro' => $e->getMessage()], 500);
        }
    }
}
