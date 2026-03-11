<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AutenticacaoController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\AnalisesController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Middleware\IdempotenciaMiddleware; // 🟢 IMPORTAÇÃO DO MIDDLEWARE
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'api', 
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api')->group(function () { 

    Route::post('/realizar-login', [AutenticacaoController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        
        Route::get('/usuario', function (Request $request) {
            return $request->user();
        });

        // Gestão de Mesas
        Route::get('/listar-mesas', [MesaController::class, 'listar_mesas']);
        Route::post('/cadastrar-mesa', [MesaController::class, 'cadastrar_mesa']);
        Route::get('/detalhes-mesa/{id}', [MesaController::class, 'detalhes_mesa']);

        // Gestão de Produtos
        Route::get('/listar-produtos', [ProdutoController::class, 'listar_produtos']);
        Route::post('/cadastrar-produto', [ProdutoController::class, 'cadastrar_produto']);
        Route::post('/produtos/editar/{id}', [ProdutoController::class, 'atualizar_produto']);

        // 🟢 GESTÃO DE COMANDAS (Protegidas por Idempotência Contra Duplicação)
        Route::get('/buscar-comanda/{id}', [ComandaController::class, 'buscar_comanda']); 
        Route::get('/listar-comandas', [ComandaController::class, 'listar_todas_comandas']);
        
        Route::middleware([IdempotenciaMiddleware::class])->group(function () {
            Route::post('/abrir-comanda', [ComandaController::class, 'abrir_comanda_mesa']);
            Route::post('/fechar-comanda/{id}', [ComandaController::class, 'fechar_comanda']);
            Route::post('/adicionar-itens-comanda/{id}', [ComandaController::class, 'adicionar_itens_comanda']);
            Route::post('/alterar-quantidade-item/{id}', [ComandaController::class, 'alterar_quantidade']);
            Route::delete('/remover-item-comanda/{id}', [ComandaController::class, 'remover_item']);
            Route::post('/venda-balcao', [ComandaController::class, 'venda_balcao']);
            Route::post('/fechar-comanda/cancelar/{id}', [ComandaController::class, 'cancelar_comanda']);
            Route::post('/reabrir-comanda/{id}', [ComandaController::class, 'reabrir_comanda']);
        });

        // Inteligência de Negócios (BI)
        Route::get('/analises/dashboard', [AnalisesController::class, 'obter_resumo_dashboard']);

        // Gestão de Equipa
        Route::get('/equipe/listar', [FuncionarioController::class, 'listar_funcionarios']);
        Route::post('/equipe/cadastrar', [FuncionarioController::class, 'cadastrar_funcionario']);
        Route::post('/equipe/alternar-status/{id}', [FuncionarioController::class, 'alternar_status']);
        Route::post('/equipe/demitir/{id}', [FuncionarioController::class, 'demitir']);
        Route::post('/equipe/readmitir/{id}', [FuncionarioController::class, 'readmitir']);
        Route::post('/equipe/editar/{id}', [FuncionarioController::class, 'editar_funcionario']);

        // Gestão de Estoque
        Route::post('/estoque/registrar-perda', [\App\Http\Controllers\EstoqueController::class, 'registrar_perda']);
        Route::post('/estoque/registrar-entrada', [\App\Http\Controllers\EstoqueController::class, 'registrar_entrada']);
    });
});