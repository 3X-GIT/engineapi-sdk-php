<?php

declare(strict_types=1);

namespace EngineApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;

/**
 * HTTP Client wrapper para comunicação com a Engine API.
 *
 * Todas as requisições são prefixadas com /v1.
 * Suporta autenticação via API Key (X-API-Key) e/ou JWT (Bearer).
 */
class HttpClient
{
    private Client $client;
    private string $baseUrl;
    private ?string $apiKey;
    private ?string $token;
    private float $timeout;
    private ?HandlerStack $handlerStack;

    /**
     * @param HandlerStack|null $handlerStack só para teste — injeta um
     *   `MockHandler` nos testes de contrato (`tests/CompaniesClientTest.php`),
     *   sem bater na rede real. Ausente = comportamento normal do Guzzle.
     */
    public function __construct(
        string $baseUrl,
        ?string $apiKey = null,
        ?string $token = null,
        float $timeout = 30.0,
        ?HandlerStack $handlerStack = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->token = $token;
        $this->timeout = $timeout;
        $this->handlerStack = $handlerStack;

        $this->client = $this->buildClient();
    }

    private function buildClient(): Client
    {
        $config = [
            'base_uri' => $this->baseUrl . '/v1/',
            'timeout' => $this->timeout,
            'headers' => $this->buildHeaders(),
        ];
        if ($this->handlerStack !== null) {
            $config['handler'] = $this->handlerStack;
        }
        return new Client($config);
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
        $this->client = $this->buildClient();
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->apiKey !== null) {
            $headers['X-API-Key'] = $this->apiKey;
        }

        if ($this->token !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    /**
     * @return array<string, mixed>
     */
    public function post(string $path, ?array $json = null): array
    {
        $options = [];
        if ($json !== null) {
            $options['json'] = $json;
        }
        return $this->request('POST', $path, $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function put(string $path, ?array $json = null): array
    {
        $options = [];
        if ($json !== null) {
            $options['json'] = $json;
        }
        return $this->request('PUT', $path, $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(string $path, ?array $json = null): array
    {
        $options = [];
        if ($json !== null) {
            $options['json'] = $json;
        }
        return $this->request('PATCH', $path, $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * POST `multipart/form-data`. Usado por
     * `CompaniesClient::uploadCertificado()`: o endpoint de certificado recebe
     * o arquivo em multipart, não em JSON.
     *
     * @param array<int, array{name: string, contents: mixed, filename?: string, headers?: array<string, string>}> $multipart
     *   Formato nativo do Guzzle (`RequestOptions::MULTIPART`). O Guzzle monta
     *   o boundary e o `Content-Type: multipart/form-data; boundary=...`
     *   sozinho, e remove o `Content-Type: application/json` padrão do cliente
     *   nesta requisição.
     * @return array<string, mixed>
     */
    public function postMultipart(string $path, array $multipart): array
    {
        return $this->request('POST', $path, ['multipart' => $multipart]);
    }

    public function download(string $path): string
    {
        try {
            $response = $this->client->request('GET', ltrim($path, '/'));
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            throw new EngineApiError("Download failed: {$statusCode}", $statusCode);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        try {
            $response = $this->client->request($method, ltrim($path, '/'), $options);
            $body = $response->getBody()->getContents();
            return json_decode($body, true) ?? [];
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
            $body = null;

            if ($e->getResponse()) {
                $rawBody = $e->getResponse()->getBody()->getContents();
                $body = json_decode($rawBody, true);
            }

            $message = self::extractErrorMessage($body, $e->getMessage());

            throw new EngineApiError($message, $statusCode, $body);
        }
    }

    /**
     * Extrai uma mensagem de erro legível do corpo JSON de uma resposta de
     * erro da engineAPI.
     *
     * O corpo de erro segue a RFC 7807 e vem aninhado em `error`:
     * `{ error: { type, title, status, detail, erros, instance, requestId,
     * timestamp } }`.
     *
     * Ordem de extração: `error.detail`, depois `error.title`, depois
     * `message`/`detail` no topo (compatibilidade com corpos fora do formato
     * RFC 7807, como o de um proxy que devolve outro formato) e por fim o
     * `$fallback` (mensagem genérica do Guzzle) se nada bater.
     */
    private static function extractErrorMessage(?array $body, string $fallback): string
    {
        if (is_array($body)) {
            $err = $body['error'] ?? null;
            if (is_array($err)) {
                if (is_string($err['detail'] ?? null) && $err['detail'] !== '') {
                    return $err['detail'];
                }
                if (is_string($err['title'] ?? null) && $err['title'] !== '') {
                    return $err['title'];
                }
            }

            if (is_string($body['message'] ?? null) && $body['message'] !== '') {
                return $body['message'];
            }
            if (is_string($body['detail'] ?? null) && $body['detail'] !== '') {
                return $body['detail'];
            }
        }

        return $fallback;
    }
}
