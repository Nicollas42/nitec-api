<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AgenteIaService
{
    /**
     * Encaminha a pergunta do ERP para a API protegida do agente local.
     *
     * @return array<string, mixed>
     */
    public function consultar_pergunta(
        string $tenant_id,
        ?string $tenant_domain,
        string $pergunta,
        int $limite_linhas = 10,
    ): array {
        $base_url = rtrim((string) config('services.agente_ia.base_url'), '/');

        if ($base_url === '') {
            throw new RuntimeException('A URL base do agente de IA nao foi configurada.');
        }

        try {
            $resposta = Http::asJson()
                ->acceptJson()
                ->withHeaders($this->obter_headers_autenticacao())
                ->connectTimeout((int) config('services.agente_ia.connect_timeout_seconds', 10))
                ->timeout((int) config('services.agente_ia.timeout_seconds', 120))
                ->post($base_url . '/api/v1/consultar-agente', [
                    'tenant_id' => $tenant_id,
                    'tenant_domain' => $tenant_domain,
                    'pergunta' => $pergunta,
                    'limite_linhas' => $limite_linhas,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Nao foi possivel conectar ao agente de IA.', 0, $exception);
        }

        try {
            $resposta->throw();
        } catch (RequestException $exception) {
            $mensagem = $this->extrair_mensagem_erro($exception);
            throw new RuntimeException($mensagem, 0, $exception);
        }

        $dados = $resposta->json();

        if (! is_array($dados)) {
            throw new RuntimeException('O agente de IA retornou um payload invalido.');
        }

        return $dados;
    }

    /**
     * Monta os headers de autenticacao do agente quando um gateway externo for usado.
     *
     * @return array<string, string>
     */
    private function obter_headers_autenticacao(): array
    {
        if (! (bool) config('services.agente_ia.usar_cloudflare_access', true)) {
            return [];
        }

        $client_id = (string) config('services.agente_ia.cf_access_client_id');
        $client_secret = (string) config('services.agente_ia.cf_access_client_secret');

        if ($client_id === '' || $client_secret === '') {
            throw new RuntimeException('As credenciais do Cloudflare Access para o agente de IA nao foram configuradas.');
        }

        return [
            'CF-Access-Client-Id' => $client_id,
            'CF-Access-Client-Secret' => $client_secret,
        ];
    }

    /**
     * Extrai uma mensagem de erro legivel a partir da resposta da API remota.
     */
    private function extrair_mensagem_erro(RequestException $exception): string
    {
        $resposta = $exception->response;
        $payload = $resposta?->json();

        if (is_array($payload) && isset($payload['detail']) && is_string($payload['detail'])) {
            return $payload['detail'];
        }

        if (is_array($payload) && isset($payload['mensagem']) && is_string($payload['mensagem'])) {
            return $payload['mensagem'];
        }

        return 'O agente de IA retornou um erro ao processar a consulta.';
    }
}
