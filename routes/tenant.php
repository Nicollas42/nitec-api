<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AutenticacaoController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\ComandaController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant API Routes (Isolamento Lógico do Lojista)
|--------------------------------------------------------------------------
*/

Route::middleware([
    'api', 
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api')->group(function () { // <-- O prefixo de volta aqui!

    // 1. Rota de Login Isolada
    Route::post('/realizar-login', [AutenticacaoController::class, 'login']);

    // 2. Rotas Protegidas do Lojista
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
        Route::get('/listar-comandas', [ComandaController::class, 'listar_todas_comandas']);
    });
});