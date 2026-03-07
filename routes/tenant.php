<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AutenticacaoController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\AnalisesController; // 🟢 IMPORTAÇÃO ADICIONADA
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

        // Gestão de Comandas
        Route::post('/abrir-comanda', [ComandaController::class, 'abrir_comanda_mesa']);
        Route::post('/fechar-comanda/{id}', [ComandaController::class, 'fechar_comanda']);
        Route::get('/buscar-comanda/{id}', [ComandaController::class, 'buscar_comanda']); 
        Route::get('/listar-comandas', [ComandaController::class, 'listar_todas_comandas']);
        Route::post('/adicionar-itens-comanda/{id}', [ComandaController::class, 'adicionar_itens_comanda']);
        Route::post('/alterar-quantidade-item/{id}', [ComandaController::class, 'alterar_quantidade_item']);
        Route::delete('/remover-item-comanda/{id}', [ComandaController::class, 'remover_item_comanda']);

        // 🟢 Inteligência de Negócios (BI)
        Route::get('/analises/dashboard', [AnalisesController::class, 'obter_resumo_dashboard']); 
    });
});