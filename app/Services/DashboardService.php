<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Comanda;
use App\Models\Produto;

class DashboardService
{
    public function gerar_relatorio_completo($data_inicio, $data_fim)
    {
        $comandas_fechadas = Comanda::where('status_comanda', 'fechada')
            ->whereBetween('data_hora_fechamento', [$data_inicio, $data_fim]);

        $qtd_comandas = $comandas_fechadas->count();
        $faturamento_bruto = $comandas_fechadas->sum('valor_total');
        $ticket_medio = $qtd_comandas > 0 ? ($faturamento_bruto / $qtd_comandas) : 0;
        
        $tempo_medio_minutos = $comandas_fechadas->whereNotNull('data_hora_abertura')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, data_hora_abertura, data_hora_fechamento)) as media')
            ->value('media') ?? 0;

        // Raio-X da Equipe
        $equipe_base = DB::table('comandas')
            ->join('users', 'comandas.usuario_id', '=', 'users.id')
            ->where('comandas.status_comanda', 'fechada')
            ->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])
            ->select(
                'users.id as usuario_id', 'users.name',
                DB::raw('count(comandas.id) as total_mesas'),
                DB::raw('sum(comandas.valor_total) as total_vendas'),
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, comandas.data_hora_abertura, comandas.data_hora_fechamento)) as tempo_medio_minutos')
            )
            ->groupBy('users.id', 'users.name')
            ->get();

        $equipe_itens = DB::table('comanda_itens')
            ->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')
            ->join('produtos', 'comanda_itens.produto_id', '=', 'produtos.id')
            ->where('comandas.status_comanda', 'fechada')
            ->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])
            ->select('comandas.usuario_id', DB::raw('SUM(comanda_itens.quantidade) as itens_servidos'), DB::raw('SUM(comanda_itens.quantidade * (comanda_itens.preco_unitario - COALESCE(produtos.preco_custo, 0))) as lucro_gerado'))
            ->groupBy('comandas.usuario_id')
            ->get();

        $equipe_produtos = DB::table('comanda_itens')
            ->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')
            ->join('produtos', 'comanda_itens.produto_id', '=', 'produtos.id')
            ->where('comandas.status_comanda', 'fechada')
            ->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])
            ->select('comandas.usuario_id', 'produtos.nome_produto', DB::raw('SUM(comanda_itens.quantidade) as qtd_vendida'))
            ->groupBy('comandas.usuario_id', 'produtos.nome_produto')
            ->orderBy('comandas.usuario_id')->orderByDesc('qtd_vendida')
            ->get();

        $performance_equipe = collect($equipe_base)->map(function ($base) use ($equipe_itens, $equipe_produtos) {
            $itens = $equipe_itens->firstWhere('usuario_id', $base->usuario_id);
            $campeao = $equipe_produtos->firstWhere('usuario_id', $base->usuario_id);

            return [
                'name' => $base->name,
                'total_mesas' => $base->total_mesas,
                'total_vendas' => $base->total_vendas,
                'tempo_medio_minutos' => $base->tempo_medio_minutos ? round($base->tempo_medio_minutos) : 0,
                'itens_servidos' => $itens ? $itens->itens_servidos : 0,
                'lucro_gerado' => $itens ? round($itens->lucro_gerado, 2) : 0,
                'produto_campeao' => $campeao ? $campeao->nome_produto : 'Nenhum item',
                'qtd_campeao' => $campeao ? $campeao->qtd_vendida : 0,
            ];
        })->sortByDesc('total_vendas')->values()->all();

        // Demais Consultas (Vendas por dia, Mapas, ABC, Mesas...)
        $vendas_por_hora = DB::table('comanda_itens')->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])->select(DB::raw('HOUR(comanda_itens.data_hora_lancamento) as hora'), DB::raw('count(*) as total_pedidos'))->groupBy('hora')->orderBy('hora')->get();
        $produtos_por_hora = DB::table('comanda_itens')->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')->join('produtos', 'comanda_itens.produto_id', '=', 'produtos.id')->where('comandas.status_comanda', 'fechada')->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])->select(DB::raw('HOUR(comanda_itens.data_hora_lancamento) as hora'), 'produtos.id as produto_id', 'produtos.nome_produto', DB::raw('SUM(comanda_itens.quantidade) as quantidade'))->groupBy('hora', 'produtos.id', 'produtos.nome_produto')->get();
        
        $produtos_vendidos_ids = DB::table('comanda_itens')->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])->pluck('produto_id')->unique();
        $produtos_encalhados = Produto::whereNotIn('id', $produtos_vendidos_ids)->where('estoque_atual', '>', 0)->select('nome_produto', 'estoque_atual', 'preco_venda')->take(10)->get();
        
        $ranking_produtos = DB::table('comanda_itens')->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')->join('produtos', 'comanda_itens.produto_id', '=', 'produtos.id')->where('comandas.status_comanda', 'fechada')->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])->select('produtos.id as produto_id', 'produtos.nome_produto', DB::raw('SUM(comanda_itens.quantidade) as quantidade_total'), DB::raw('SUM(comanda_itens.quantidade * comanda_itens.preco_unitario) as receita_total'), DB::raw('SUM(comanda_itens.quantidade * (comanda_itens.preco_unitario - COALESCE(produtos.preco_custo, 0))) as lucro_total'))->groupBy('produtos.id', 'produtos.nome_produto')->orderByDesc('receita_total')->get();
        $ranking_mesas = DB::table('comandas')->join('mesas', 'comandas.mesa_id', '=', 'mesas.id')->where('comandas.status_comanda', 'fechada')->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])->select('mesas.nome_mesa', DB::raw('count(comandas.id) as total_atendimentos'), DB::raw('sum(comandas.valor_total) as receita_gerada'))->groupBy('mesas.id', 'mesas.nome_mesa')->orderByDesc('receita_gerada')->get();
        $vendas_por_dia = DB::table('comandas')->where('status_comanda', 'fechada')->whereBetween('data_hora_fechamento', [$data_inicio, $data_fim])->select(DB::raw('DAYOFWEEK(data_hora_fechamento) as dia_semana'), DB::raw('sum(valor_total) as faturamento_total'), DB::raw('count(id) as total_comandas'))->groupBy('dia_semana')->orderBy('dia_semana')->get();

        return [
            'indicadores' => [
                'faturamento_bruto' => round($faturamento_bruto, 2),
                'ticket_medio' => round($ticket_medio, 2),
                'total_pedidos' => $qtd_comandas,
                'tempo_permanencia' => round($tempo_medio_minutos) . " min"
            ],
            'equipe' => $performance_equipe,
            'horarios' => $vendas_por_hora,
            'produtos_por_hora' => $produtos_por_hora,
            'encalhados' => $produtos_encalhados,
            'ranking_produtos' => $ranking_produtos,
            'ranking_mesas' => $ranking_mesas,       
            'vendas_por_dia' => $vendas_por_dia      
        ];
    }

    // 🟢 Log de Auditoria de Estoque
    public function obter_log_auditoria($data_inicio, $data_fim)
    {
        // 1. Buscar todas as Entradas (Compras)
        $entradas = \App\Models\EstoqueEntrada::with(['produto' => function($q) { $q->withTrashed(); }, 'usuario'])
            ->whereBetween('created_at', [$data_inicio, $data_fim])
            ->get()
            ->map(function($item) {
                return [
                    'tipo_evento' => 'entrada',
                    'data_hora' => $item->created_at,
                    'icone' => '📦',
                    'cor' => 'blue',
                    'titulo' => "Entrada de Estoque: " . ($item->produto->nome_produto ?? 'Produto Apagado'),
                    'descricao' => "Adicionadas {$item->quantidade_adicionada} un. compradas a R$ {$item->custo_unitario_compra} cada.",
                    'usuario' => $item->usuario->name ?? 'Sistema',
                    'detalhes_extras' => $item->fornecedor ? "Fornecedor: " . $item->fornecedor : ''
                ];
            });

        // 2. Buscar todas as Perdas (Baixas/Quebras)
        $perdas = \App\Models\EstoquePerda::with(['produto' => function($q) { $q->withTrashed(); }, 'usuario'])
            ->whereBetween('created_at', [$data_inicio, $data_fim])
            ->get()
            ->map(function($item) {
                return [
                    'tipo_evento' => 'perda',
                    'data_hora' => $item->created_at,
                    'icone' => '📉',
                    'cor' => 'red',
                    'titulo' => "Baixa de Estoque: " . ($item->produto->nome_produto ?? 'Produto Apagado'),
                    'descricao' => "Perda de {$item->quantidade} un. Motivo: {$item->motivo}.",
                    'usuario' => $item->usuario->name ?? 'Sistema',
                    'detalhes_extras' => "Prejuízo financeiro: R$ " . $item->custo_total_perda
                ];
            });

        // 3. Buscar as Vendas Fechadas (Comandas)
        $vendas = \App\Models\Comanda::with(['buscar_usuario', 'buscar_mesa', 'listar_itens.buscar_produto' => function($q) { $q->withTrashed(); }])
            ->where('status_comanda', 'fechada')
            ->whereBetween('data_hora_fechamento', [$data_inicio, $data_fim])
            ->get()
            ->map(function($comanda) {
                $itens_str = $comanda->listar_itens->take(3)->map(function($i) {
                    return $i->quantidade . 'x ' . ($i->buscar_produto->nome_produto ?? 'Item Apagado');
                })->implode(', ');
                
                if ($comanda->listar_itens->count() > 3) $itens_str .= ' e mais...';

                $mesa_nome = $comanda->buscar_mesa ? $comanda->buscar_mesa->nome_mesa : 'Balcão/Avulsa';

                return [
                    'tipo_evento' => 'venda',
                    'data_hora' => $comanda->data_hora_fechamento,
                    'icone' => '💰',
                    'cor' => 'green',
                    'titulo' => "Venda Concluída: Comanda #{$comanda->id} ({$mesa_nome})",
                    'descricao' => "Faturou R$ {$comanda->valor_total}.",
                    'usuario' => $comanda->buscar_usuario->name ?? 'Sistema',
                    'detalhes_extras' => "Itens: " . $itens_str,
                    'comanda_raw' => $comanda // Enviamos a comanda completa para caso o dono clique em "Ver Detalhes"
                ];
            });

        // 4. Juntar tudo numa linha do tempo unificada e ordenar pela data (Mais recente primeiro)
        $timeline = collect([...$entradas, ...$perdas, ...$vendas])
                    ->sortByDesc('data_hora')
                    ->values()
                    ->all();

        return $timeline;
    }
}