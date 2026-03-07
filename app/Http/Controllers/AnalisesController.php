<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Comanda;
use App\Models\Produto;

class AnalisesController extends Controller
{
    public function obter_resumo_dashboard(Request $requisicao)
    {
        $data_inicio = $requisicao->input('data_inicio') ? Carbon::parse($requisicao->input('data_inicio'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $data_fim = $requisicao->input('data_fim') ? Carbon::parse($requisicao->input('data_fim'))->endOfDay() : Carbon::now()->endOfDay();

        $comandas_fechadas = Comanda::where('status_comanda', 'fechada')
            ->whereBetween('data_hora_fechamento', [$data_inicio, $data_fim]);

        // 💰 INDICADORES FINANCEIROS
        $qtd_comandas = $comandas_fechadas->count();
        $faturamento_bruto = $comandas_fechadas->sum('valor_total');
        $ticket_medio = $qtd_comandas > 0 ? ($faturamento_bruto / $qtd_comandas) : 0;
        
        $tempo_medio_minutos = $comandas_fechadas->whereNotNull('data_hora_abertura')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, data_hora_abertura, data_hora_fechamento)) as media')
            ->value('media') ?? 0;

        // 👥 RANKING DE ATENDENTES
        $performance_equipe = DB::table('comandas')
            ->join('users', 'comandas.usuario_id', '=', 'users.id')
            ->where('comandas.status_comanda', 'fechada')
            ->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])
            ->select('users.name', DB::raw('count(comandas.id) as total_mesas'), DB::raw('sum(comandas.valor_total) as total_vendas'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_vendas')
            ->get();

        // 🕒 MAPA DE CALOR (VENDAS POR HORA)
        $vendas_por_hora = DB::table('comanda_itens')
            ->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')
            ->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])
            ->select(DB::raw('HOUR(comanda_itens.data_hora_lancamento) as hora'), DB::raw('count(*) as total_pedidos'))
            ->groupBy('hora')
            ->orderBy('hora')
            ->get();

        $produtos_por_hora = DB::table('comanda_itens')
            ->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')
            ->join('produtos', 'comanda_itens.produto_id', '=', 'produtos.id')
            ->where('comandas.status_comanda', 'fechada')
            ->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])
            ->select(DB::raw('HOUR(comanda_itens.data_hora_lancamento) as hora'), 'produtos.id as produto_id', 'produtos.nome_produto', DB::raw('SUM(comanda_itens.quantidade) as quantidade'))
            ->groupBy('hora', 'produtos.id', 'produtos.nome_produto')
            ->get();

        // ⚠️ PRODUTOS ENCALHADOS
        $produtos_vendidos_ids = DB::table('comanda_itens')
            ->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')
            ->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])
            ->pluck('produto_id')
            ->unique();

        $produtos_encalhados = Produto::whereNotIn('id', $produtos_vendidos_ids)
            ->where('estoque_atual', '>', 0)
            ->select('nome_produto', 'estoque_atual', 'preco_venda')
            ->take(10)
            ->get();

        // 🏆 CURVA ABC
        $ranking_produtos = DB::table('comanda_itens')
            ->join('comandas', 'comanda_itens.comanda_id', '=', 'comandas.id')
            ->join('produtos', 'comanda_itens.produto_id', '=', 'produtos.id')
            ->where('comandas.status_comanda', 'fechada')
            ->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])
            ->select('produtos.id as produto_id', 'produtos.nome_produto', DB::raw('SUM(comanda_itens.quantidade) as quantidade_total'), DB::raw('SUM(comanda_itens.quantidade * comanda_itens.preco_unitario) as receita_total'), DB::raw('SUM(comanda_itens.quantidade * (comanda_itens.preco_unitario - COALESCE(produtos.preco_custo, 0))) as lucro_total'))
            ->groupBy('produtos.id', 'produtos.nome_produto')
            ->orderByDesc('receita_total')
            ->get();

        // 🟢 NOVO: RANKING DE MESAS (A Mina de Ouro)
        $ranking_mesas = DB::table('comandas')
            ->join('mesas', 'comandas.mesa_id', '=', 'mesas.id')
            ->where('comandas.status_comanda', 'fechada')
            ->whereBetween('comandas.data_hora_fechamento', [$data_inicio, $data_fim])
            ->select('mesas.nome_mesa', DB::raw('count(comandas.id) as total_atendimentos'), DB::raw('sum(comandas.valor_total) as receita_gerada'))
            ->groupBy('mesas.id', 'mesas.nome_mesa')
            ->orderByDesc('receita_gerada')
            ->get();

        // 🟢 NOVO: FATURAMENTO POR DIA DA SEMANA
        // No MySQL: 1=Domingo, 2=Segunda, 3=Terça, etc...
        $vendas_por_dia = DB::table('comandas')
            ->where('status_comanda', 'fechada')
            ->whereBetween('data_hora_fechamento', [$data_inicio, $data_fim])
            ->select(DB::raw('DAYOFWEEK(data_hora_fechamento) as dia_semana'), DB::raw('sum(valor_total) as faturamento_total'), DB::raw('count(id) as total_comandas'))
            ->groupBy('dia_semana')
            ->orderBy('dia_semana')
            ->get();

        return response()->json([
            'sucesso' => true,
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
            'ranking_mesas' => $ranking_mesas,       // <-- Novo
            'vendas_por_dia' => $vendas_por_dia      // <-- Novo
        ]);
    }
}