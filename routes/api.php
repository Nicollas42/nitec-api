<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AutenticacaoController;
use App\Http\Controllers\EstabelecimentoController;
use App\Http\Controllers\Auth\RecuperacaoSenhaController;

/*
|--------------------------------------------------------------------------
| API Routes - Central Application (nitec_sistema_v3)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {
    
    // Login Exclusivo para o Painel Central (Admin Master)
    // URL Final: POST /api/admin/login
    Route::post('/login', [AutenticacaoController::class, 'login']);

    Route::post('/esqueci-senha', [RecuperacaoSenhaController::class, 'solicitar_link']);
    Route::post('/resetar-senha', [RecuperacaoSenhaController::class, 'resetar_senha']);

    /**
     * PAINEL ADMINISTRATIVO (Super Admin)
     * Protegido via Sanctum.
     */
    Route::middleware('auth:sanctum')->group(function () {

        Route::put('/alternar-status-bar/{id}', [EstabelecimentoController::class, 'alternar_status']);
        Route::delete('/excluir-bar/{id}', [EstabelecimentoController::class, 'excluir_bar']);
        
        // Retorna os dados do Admin Logado
        Route::get('/usuario', function (Request $request) {
            return $request->user();
        });

        Route::get('/listar-estabelecimentos', [EstabelecimentoController::class, 'listar_estabelecimentos']);
        Route::post('/cadastrar-novo-bar', [EstabelecimentoController::class, 'registrar_novo_bar']);
        Route::get('/gerar-acesso-suporte/{tenant_id}', [EstabelecimentoController::class, 'gerar_link_suporte']);
    });
});