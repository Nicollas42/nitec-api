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

    public function obter_resumo_dashboard(Request $requisicao)
    {
        // 1. Recebe e prepara os dados
        $data_inicio = $requisicao->input('data_inicio') ? Carbon::parse($requisicao->input('data_inicio'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $data_fim = $requisicao->input('data_fim') ? Carbon::parse($requisicao->input('data_fim'))->endOfDay() : Carbon::now()->endOfDay();

        // 2. Chama a Camada de Serviço para fazer o trabalho pesado
        $dados_relatorio = $this->dashboardService->gerar_relatorio_completo($data_inicio, $data_fim);

        // 3. Devolve a Resposta
        return response()->json(array_merge(['sucesso' => true], $dados_relatorio));
    }
}