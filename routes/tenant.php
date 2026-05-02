<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\AutenticacaoController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\ComandaController;
use App\Http\Controllers\AnalisesController;
use App\Http\Controllers\AgenteIaController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\PermissaoController; // 🟢 NOVO CONTEUDO
use App\Http\Controllers\ConsultasProntasController;
use App\Http\Controllers\CategoriasController;
use App\Http\Controllers\AdicionalController;
use App\Http\Controllers\CozinhaController;
use App\Http\Controllers\CardapioController;
use App\Http\Middleware\IdempotenciaMiddleware;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'api', 
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api')->group(function () { 

    // 🟢 Rota pública de ping — usada pelo app para detectar se a VPS está online
    // Não requer autenticação. Retorna 200 sempre que o servidor estiver de pé.
    Route::get('/ping', function () {
        return response()->json(['ok' => true, 'servidor' => 'vps']);
    });

    Route::post('/realizar-login', [AutenticacaoController::class, 'login']);

    // ─── CARDÁPIO DIGITAL PÚBLICO (sem auth — acessado pelo cliente via QR Code) ───
    Route::prefix('cardapio')->group(function () {
        Route::get('/config',                 [CardapioController::class, 'config_publica']);
        Route::get('/mesa/{id}',              [CardapioController::class, 'dados_mesa_publica']);
        Route::get('/pdfs/{id}/arquivo',      [CardapioController::class, 'servir_pdf_publico']);
        Route::post('/login',                 [CardapioController::class, 'login_cliente']);
        Route::post('/registrar',             [CardapioController::class, 'registrar_cliente']);
        Route::get('/comanda/{id}/status',    [CardapioController::class, 'status_comanda_publica']);
        Route::get('/sessao/{token}',         [CardapioController::class, 'sessao_por_token']);
        Route::post('/solicitar-atendimento', [CardapioController::class, 'solicitar_atendimento']);
    });

    Route::get('/midias/produtos/{path}', [ProdutoController::class, 'exibir_imagem_produto'])
        ->where('path', '.*');

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
        Route::get('/produtos/detalhes/{id}', [ProdutoController::class, 'detalhes_produto']);
        Route::post('/cadastrar-produto', [ProdutoController::class, 'cadastrar_produto']);
        Route::post('/produtos/editar/{id}', [ProdutoController::class, 'atualizar_produto']);
        Route::delete('/produtos/excluir/{id}', [ProdutoController::class, 'excluir_produto']);
        Route::post('/produtos/{id}/requer-cozinha', [ProdutoController::class, 'alternar_requer_cozinha']);
        Route::get('/listar-fornecedores', [FornecedorController::class, 'listar_fornecedores']);
        Route::post('/fornecedores/cadastrar', [FornecedorController::class, 'cadastrar_fornecedor']);
        Route::post('/fornecedores/editar/{id}', [FornecedorController::class, 'atualizar_fornecedor']);
        Route::delete('/fornecedores/excluir/{id}', [FornecedorController::class, 'excluir_fornecedor']);

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
        Route::get('/analises/encalhados', [AnalisesController::class, 'obter_encalhados']);
        Route::get('/analises/fornecedores', [AnalisesController::class, 'obter_analise_fornecedores']);

        Route::get('/produtos/categorias', [CategoriasController::class, 'listar']);
        Route::post('/produtos/categorias', [CategoriasController::class, 'criar']);
        Route::delete('/produtos/categorias/{nome}', [CategoriasController::class, 'excluir']);
        Route::post('/agente-ia/consultar-pergunta', [AgenteIaController::class, 'consultar_pergunta']);
        Route::get('/analises/consultas-prontas', [ConsultasProntasController::class, 'listar']);
        Route::get('/analises/consultas-prontas/{slug}', [ConsultasProntasController::class, 'executar']);

        // Gestão de Adicionais
        Route::get('/grupos-adicionais', [AdicionalController::class, 'listar_grupos']);
        Route::post('/grupos-adicionais', [AdicionalController::class, 'criar_grupo']);
        Route::put('/grupos-adicionais/{id}', [AdicionalController::class, 'editar_grupo']);
        Route::delete('/grupos-adicionais/{id}', [AdicionalController::class, 'excluir_grupo']);
        Route::post('/grupos-adicionais/{id}/itens', [AdicionalController::class, 'criar_item']);
        Route::put('/itens-adicionais/{id}', [AdicionalController::class, 'editar_item']);
        Route::delete('/itens-adicionais/{id}', [AdicionalController::class, 'excluir_item']);

        // Cozinha
        Route::get('/cozinha/pedidos', [CozinhaController::class, 'listar_pedidos']);
        Route::put('/cozinha/pedido/{id}/status', [CozinhaController::class, 'atualizar_status']);
        Route::get('/cozinha/status-mesas', [CozinhaController::class, 'status_mesas']);
        Route::post('/cozinha/marcar-visto/{mesa_id}', [CozinhaController::class, 'marcar_visto']);

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

        // Gestão de Permissões
        Route::get('/permissoes', [PermissaoController::class, 'index']);
        Route::post('/permissoes', [PermissaoController::class, 'store']);

        // ─── CARDÁPIO DIGITAL ADMIN ───────────────────────────────────────────────
        Route::get('/cardapio/admin/config', [CardapioController::class, 'config_admin']);
        Route::put('/cardapio/admin/config', [CardapioController::class, 'atualizar_config']);
        Route::get('/cardapio/admin/pdfs',           [CardapioController::class, 'listar_pdfs_admin']);
        Route::post('/cardapio/admin/pdfs',          [CardapioController::class, 'upload_pdf_admin']);
        Route::put('/cardapio/admin/pdfs/{id}',      [CardapioController::class, 'atualizar_pdf_admin']);
        Route::delete('/cardapio/admin/pdfs/{id}',   [CardapioController::class, 'excluir_pdf_admin']);
        Route::post('/mesas/{id}/resolver-atendimento',                [MesaController::class, 'resolver_atendimento']);
        Route::post('/mesas/{id}/resolver-atendimento-individual',    [MesaController::class, 'resolver_atendimento_individual']);

        // Aprovação/rejeição de comandas pendentes (garçom)
        Route::post('/comandas/{id}/aprovar',              [ComandaController::class, 'aprovar_comanda']);
        Route::post('/comandas/{id}/rejeitar',             [ComandaController::class, 'rejeitar_comanda']);
        Route::post('/mesas/{id}/aprovar-todas-pendentes', [ComandaController::class, 'aprovar_todas_pendentes']);
    });
});
