<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AgenteIaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgenteIaController extends Controller
{
    /**
     * Inicializa o controller com a camada de integracao do agente.
     */
    public function __construct(
        private readonly AgenteIaService $agente_ia_service,
    ) {
    }

    /**
     * Recebe a pergunta do frontend e a encaminha para o agente SQL local.
     */
    public function consultar_pergunta(Request $request): JsonResponse
    {
        $dados_validados = $request->validate([
            'pergunta' => ['required', 'string', 'min:3', 'max:2000'],
            'limite_linhas' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $tenant_atual = tenant();

        if (! $tenant_atual) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Nao foi possivel identificar o tenant atual.',
            ], 400);
        }

        try {
            $resposta = $this->agente_ia_service->consultar_pergunta(
                tenant_id: (string) $tenant_atual->getTenantKey(),
                tenant_domain: $request->getHost(),
                pergunta: (string) $dados_validados['pergunta'],
                limite_linhas: (int) ($dados_validados['limite_linhas'] ?? 10),
            );

            return response()->json($resposta);
        } catch (RuntimeException $exception) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => $exception->getMessage(),
            ], 502);
        }
    }
}
