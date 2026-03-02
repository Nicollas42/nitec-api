<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MesaController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'api', // Mudamos de 'web' para 'api' para facilitar o uso com Vue.js
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api')->group(function () {

    // Endpoints das Mesas (kebab-case nas URLs)
    Route::get('/listar-mesas', [MesaController::class, 'listar_mesas']);
    Route::post('/cadastrar-mesa', [MesaController::class, 'cadastrar_mesa']);
    Route::get('/detalhes-mesa/{id}', [MesaController::class, 'detalhes_mesa']);

});