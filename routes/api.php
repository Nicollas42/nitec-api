<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AutenticacaoController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\EstabelecimentoController;

/*
|--------------------------------------------------------------------------
| API Routes - Central Application
|--------------------------------------------------------------------------
|
| Este arquivo gerencia as rotas da aplicação central, incluindo o painel
| administrativo para gestão de tenants e as rotas legadas do sistema.
|
*/

/**
 * Autenticação Global
 */
Route::post('/realizar-login', [AutenticacaoController::class, 'login']);

/**
 * PAINEL ADMINISTRATIVO (Super Admin)
 * Gerencia os Bares, Quiosques e suporte técnico.
 */
Route::prefix('admin')->group(function () {
    
    // Removido o "Admin\" do caminho da Controller
    Route::get('/listar-estabelecimentos', [EstabelecimentoController::class, 'listar_estabelecimentos']);
    Route::post('/cadastrar-novo-bar', [EstabelecimentoController::class, 'registrar_novo_bar']);
    Route::get('/gerar-acesso-suporte/{tenant_id}', [EstabelecimentoController::class, 'gerar_link_suporte']);
});

/**
 * GRUPO PROTEGIDO (Sanctum)
 * Rotas que exigem token de autenticação.
 */
Route::middleware('auth:sanctum')->group(function () {
    
    // Informações do Usuário Logado
    Route::get('/usuario', function (Request $request) {
        return $request->user();
    });

    /**
     * Gestão de Produtos
     */
    Route::prefix('produtos')->group(function () {
        Route::get('/listar', [ProdutoController::class, 'listar_produtos']);
        Route::post('/cadastrar', [ProdutoController::class, 'cadastrar_produto']);
    });

    /**
     * Gestão de Mesas
     */
    Route::prefix('mesas')->group(function () {
        Route::get('/listar', [MesaController::class, 'listar_mesas']);
        Route::post('/cadastrar', [MesaController::class, 'cadastrar_mesa']);
        Route::get('/{id}/detalhes', [MesaController::class, 'detalhes_mesa']);
    });

    /**
     * Gestão de Comandas e Itens
     */
    Route::prefix('comandas')->group(function () {
        Route::post('/abrir', [ComandaController::class, 'abrir_comanda_mesa']);
        Route::get('/listar', [ComandaController::class, 'listar_todas_comandas']);
        
        // Operações em itens específicos da comanda
        Route::post('/{id}/adicionar-itens', [ComandaController::class, 'adicionar_itens_comanda']);
        Route::post('/item/{id_item}/alterar-quantidade', [ComandaController::class, 'alterar_quantidade_item']);
        Route::delete('/item/{id_item}', [ComandaController::class, 'remover_item_comanda']);
    });
});