<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AutenticacaoController;
use App\Http\Controllers\EstabelecimentoController;

/*
|--------------------------------------------------------------------------
| API Routes - Central Application (nitec_sistema_v3)
|--------------------------------------------------------------------------
*/

// Login Exclusivo para o Painel Central (Admin Master)
// Obs: Os lojistas fazem login pela mesma rota, mas no arquivo tenant.php
Route::post('/realizar-login', [AutenticacaoController::class, 'login']);

/**
 * PAINEL ADMINISTRATIVO (Super Admin)
 * Gerencia Bares, Quiosques e Suporte. Protegido via Sanctum.
 */
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/listar-estabelecimentos', [EstabelecimentoController::class, 'listar_estabelecimentos']);
    Route::post('/cadastrar-novo-bar', [EstabelecimentoController::class, 'registrar_novo_bar']);
    Route::get('/gerar-acesso-suporte/{tenant_id}', [EstabelecimentoController::class, 'gerar_link_suporte']);
});

/**
 * Retorna dados do Admin logado
 */
Route::middleware('auth:sanctum')->get('/usuario', function (Request $request) {
    return $request->user();
});