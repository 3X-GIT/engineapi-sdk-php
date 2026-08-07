<?php

declare(strict_types=1);

namespace EngineApi\Tests;

use EngineApi\EngineApiClient;
use EngineApi\EngineApiError;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Extração da mensagem curta de `EngineApiError`. O corpo de erro da
 * engineAPI segue a RFC 7807: `{ error: { type, title, status, detail, ... } }`,
 * com `detail` e `message` aninhados, nunca no topo do corpo.
 */
class ErrorMessageExtractionTest extends TestCase
{
    private function clientWithMockedResponse(int $status, $body): EngineApiClient
    {
        $mock = new MockHandler([
            new Response($status, [], is_string($body) ? $body : json_encode($body)),
        ]);
        $stack = HandlerStack::create($mock);

        return new EngineApiClient([
            'base_url' => 'http://example.test',
            'handler_stack' => $stack,
        ]);
    }

    public function testCorpoRfc7807RealVirmaMensagemDoDetail(): void
    {
        $client = $this->clientWithMockedResponse(401, [
            'error' => [
                'type' => 'https://engineapi.com.br/errors/UNAUTHORIZED',
                'title' => 'Não Autorizado',
                'status' => 401,
                'detail' => 'Credenciais inválidas',
            ],
        ]);

        try {
            $client->companies->listar();
            $this->fail('Esperava EngineApiError');
        } catch (EngineApiError $e) {
            $this->assertSame('Credenciais inválidas', $e->getMessage());
            $this->assertSame(401, $e->getStatusCode());
        }
    }

    public function testCorpoRfc7807SemDetailUsaOTitle(): void
    {
        $client = $this->clientWithMockedResponse(403, [
            'error' => [
                'type' => 'https://engineapi.com.br/errors/FORBIDDEN',
                'title' => 'Acesso Negado',
                'status' => 403,
                // sem `detail`
            ],
        ]);

        try {
            $client->companies->listar();
            $this->fail('Esperava EngineApiError');
        } catch (EngineApiError $e) {
            $this->assertSame('Acesso Negado', $e->getMessage());
        }
    }

    public function testCorpoForaDoShapeRfc7807CaiNoFallbackDeTopo(): void
    {
        // Compatibilidade: corpo com `message` no topo (fora do formato RFC
        // 7807, como o de um proxy ou gateway na frente da API) continua
        // funcionando.
        $client = $this->clientWithMockedResponse(400, [
            'message' => 'Erro genérico de gateway',
        ]);

        try {
            $client->companies->listar();
            $this->fail('Esperava EngineApiError');
        } catch (EngineApiError $e) {
            $this->assertSame('Erro genérico de gateway', $e->getMessage());
        }
    }

    public function testCorpoTotalmenteForaDoShapeNaoQuebraCaiNoFallbackGenerico(): void
    {
        // Corpo de erro sem NENHUM campo reconhecível (nem `error.detail`,
        // nem `message`/`detail` de topo) não pode quebrar o SDK — cai no
        // fallback (mensagem genérica do Guzzle), nunca uma exceção não
        // tratada nem um TypeError.
        $client = $this->clientWithMockedResponse(500, ['algumCampoQualquer' => 'x']);

        try {
            $client->companies->listar();
            $this->fail('Esperava EngineApiError');
        } catch (EngineApiError $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertIsString($e->getMessage());
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public function testCorpoNaoJsonNaoQuebraCaiNoFallback(): void
    {
        // Corpo de erro que nem chega a ser JSON válido (ex.: 502 de um
        // proxy devolvendo HTML) — `$body` vira `null` (json_decode falha),
        // `extractErrorMessage` tem que aguentar `null` sem TypeError.
        $client = $this->clientWithMockedResponse(502, '<html>Bad Gateway</html>');

        try {
            $client->companies->listar();
            $this->fail('Esperava EngineApiError');
        } catch (EngineApiError $e) {
            $this->assertSame(502, $e->getStatusCode());
            $this->assertIsString($e->getMessage());
            $this->assertNotSame('', $e->getMessage());
        }
    }
}
