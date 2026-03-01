<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AutenticacaoController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\ComandaController;

Route::post('/realizar-login', [AutenticacaoController::class, 'login']);

// GRUPO PROTEGIDO: O Laravel agora sabe quem é o usuário em todas estas rotas
Route::middleware('auth:sanctum')->group(function () {
    
    // Teste de usuário
    Route::get('/usuario', function (Request $request) {
        return $request->user();
    });

    // Produtos
    Route::get('/produtos/listar', [ProdutoController::class, 'listar_produtos']);
    Route::post('/produtos/cadastrar', [ProdutoController::class, 'cadastrar_produto']);

    // Mesas
    Route::get('/mesas/listar', [MesaController::class, 'listar_mesas']);
    Route::post('/mesas/cadastrar', [MesaController::class, 'cadastrar_mesa']);
    Route::get('/mesas/{id}/detalhes', [MesaController::class, 'detalhes_mesa']);

    // Comandas
    Route::post('/comandas/abrir', [ComandaController::class, 'abrir_comanda_mesa']);
    Route::get('/comandas/listar', [ComandaController::class, 'listar_todas_comandas']);
    Route::post('/comandas/{id}/adicionar-itens', [ComandaController::class, 'adicionar_itens_comanda']);
    Route::post('/comandas/item/{id_item}/alterar-quantidade', [ComandaController::class, 'alterar_quantidade_item']);
    Route::delete('/comandas/item/{id_item}', [ComandaController::class, 'remover_item_comanda']);
});