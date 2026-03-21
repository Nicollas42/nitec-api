<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\DashboardService; // 🟢 Importamos o nosso Serviço!

class AnalisesController extends Controller
{
    protected $dashboardService;

    // A Injeção de Dependência resolve a instanciação para nós
    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function obter_encalhados(Request $requisicao)
    {
        $data_inicio = $requisicao->filled('data_inicio') ? Carbon::parse($requisicao->input('data_inicio'))->startOfDay() : null;
        $data_fim    = $requisicao->filled('data_fim')    ? Carbon::parse($requisicao->input('data_fim'))->endOfDay()   : null;

        $encalhados = $this->dashboardService->obter_encalhados($data_inicio, $data_fim);

        return response()->json(['sucesso' => true, 'encalhados' => $encalhados]);
    }

    public function obter_analise_fornecedores()
    {
        $dados = $this->dashboardService->obter_analise_fornecedores();
        return response()->json(['sucesso' => true, ...$dados]);
    }

    public function obter_resumo_dashboard(Request $requisicao)
    {
        // 1. Recebe e prepara os dados
        $data_inicio = $requisicao->input('data_inicio') ? Carbon::parse($requisicao->input('data_inicio'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $data_fim = $requisicao->input('data_fim') ? Carbon::parse($requisicao->input('data_fim'))->endOfDay() : Carbon::now()->endOfDay();

        // 2. Chama a Camada de Serviço para fazer o trabalho pesado
        $dados_relatorio = $this->dashboardService->gerar_relatorio_completo($data_inicio, $data_fim);
        $log_auditoria = $this->dashboardService->obter_log_auditoria($data_inicio, $data_fim); // 🟢 CHAMA AQUI
        // 3. Devolve a Resposta
        return response()->json(array_merge(['sucesso' => true, 'log_auditoria' => $log_auditoria], $dados_relatorio));
    }
}