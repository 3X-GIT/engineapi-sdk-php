<?php

declare(strict_types=1);

namespace EngineApi;

use EngineApi\Clients\NfeClient;
use EngineApi\Clients\CompaniesClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

/**
 * SDK PHP oficial da Engine API.
 *
 * Uso:
 *   $client = new EngineApiClient([
 *       'base_url' => 'https://api.engineapi.com.br',
 *       'api_key'  => 'ek_live_xxx',
 *   ]);
 *   $nfe = $client->nfe->emitir([...]);
 *
 * A superfície pública deste SDK cobre NFe, NFCe e NFSe. Não existe
 * `$client->dfe`; a regressão que garante isso está em
 * `tests/DfeRemovedTest.php`.
 */
class EngineApiClient
{
    private HttpClient $http;

    /** Módulo NFe — Emissão, consulta, cancelamento. */
    public NfeClient $nfe;

    /** Módulo Empresas — Cadastro e certificados. */
    public CompaniesClient $companies;

    /**
     * @param array{
     *   base_url: string,
     *   api_key?: string,
     *   token?: string,
     *   timeout?: float,
     *   handler_stack?: HandlerStack
     * } $config `handler_stack` só para teste — injeta um `MockHandler`
     *   (ver `tests/LoginContractTest.php`), sem bater na rede real.
     */
    public function __construct(array $config)
    {
        $this->http = new HttpClient(
            baseUrl: $config['base_url'],
            apiKey: $config['api_key'] ?? null,
            token: $config['token'] ?? null,
            timeout: $config['timeout'] ?? 30.0,
            handlerStack: $config['handler_stack'] ?? null,
        );

        $this->nfe = new NfeClient($this->http);
        $this->companies = new CompaniesClient($this->http);
    }

    /**
     * Login com email/senha, guardando o JWT para as chamadas seguintes.
     *
     * Toda resposta de sucesso da API vem envelopada em
     * `{ data: {...}, meta: {...} }`: o `access_token` fica em
     * `data.access_token`. Este método desembrulha o envelope, guarda o token
     * no cliente e devolve o conteúdo de `data`, para que a chamada seguinte
     * já saia autenticada sem nenhum passo extra.
     *
     * @return array{access_token: string, user: array{id: string, email: string, name: string, partnerId: string, role: string}}
     */
    public function login(string $email, string $password): array
    {
        $envelope = $this->http->post('/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $data = $envelope['data'] ?? null;
        if (is_array($data) && isset($data['access_token'])) {
            $this->http->setToken($data['access_token']);
        }

        return $data ?? $envelope;
    }

    /** Regenerar API Key. */
    public function regenerateApiKey(): array
    {
        return $this->http->post('/auth/api-keys/regenerate');
    }

    /** Info da API Key atual. */
    public function apiKeyInfo(): array
    {
        return $this->http->get('/auth/api-keys/info');
    }

    /** Verifica se a API está online (GET /health). */
    public function health(): array
    {
        $guzzle = new Client(['timeout' => 10]);
        $response = $guzzle->get($this->http->getBaseUrl() . '/health');
        return json_decode($response->getBody()->getContents(), true) ?? [];
    }
}
