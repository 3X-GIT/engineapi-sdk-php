<?php

declare(strict_types=1);

namespace EngineApi\Tests;

use EngineApi\EngineApiClient;
use EngineApi\EngineApiError;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * `EngineApiClient::login()`: prova de contrato no fio. Toda resposta de
 * sucesso da API vem envelopada em `{data: {...}, meta: {...}}`, e o teste
 * garante que o envelope é desembrulhado e o token guardado para a chamada
 * seguinte.
 */
class LoginContractTest extends TestCase
{
    /**
     * @return array{0: EngineApiClient, 1: array<int, array{request: \Psr\Http\Message\RequestInterface}>}
     */
    private function makeClient(int $status, array $body): array
    {
        $history = [];
        $mock = new MockHandler([new Response($status, [], json_encode($body))]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new EngineApiClient([
            'base_url' => 'http://example.test',
            'handler_stack' => $stack,
        ]);

        return [$client, &$history];
    }

    public function testLoginDesembrulhaOEnvelopeESetaOTokenParaChamadasSeguintes(): void
    {
        // Formato real da resposta de login: `user` dentro do envelope
        // `{data, meta}`.
        $envelope = [
            'data' => [
                'access_token' => 'jwt.fake.token',
                'user' => [
                    'id' => 'u1',
                    'email' => 'dev@parceiro.com.br',
                    'name' => 'Dev Parceiro',
                    'partnerId' => 'p1',
                    'role' => 'ADMIN',
                ],
            ],
            'meta' => ['requestId' => 'req_abc123', 'timestamp' => '2026-08-04T12:00:00.000Z'],
        ];

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode($envelope)),
            // 2ª resposta: a chamada de companies->listar() logo abaixo, só
            // para capturar o header Authorization que ela manda.
            new Response(200, [], json_encode(['success' => true, 'data' => []])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new EngineApiClient([
            'base_url' => 'http://example.test',
            'handler_stack' => $stack,
        ]);

        $result = $client->login('dev@parceiro.com.br', 'senha123');

        // Desembrulhado: SEM `data`/`meta` por fora, direto access_token/user.
        $this->assertArrayNotHasKey('data', $result);
        $this->assertArrayNotHasKey('meta', $result);
        $this->assertSame('jwt.fake.token', $result['access_token']);
        $this->assertSame('dev@parceiro.com.br', $result['user']['email']);
        $this->assertSame('p1', $result['user']['partnerId']);
        $this->assertArrayNotHasKey('partner', $result);

        // setToken() foi chamado de verdade: a PRÓXIMA chamada (JWT-only por
        // padrão) já sai com o Bearer certo, sem o consumidor fazer nada.
        $client->companies->listar();
        $this->assertCount(2, $history);
        $segundaRequisicao = $history[1]['request'];
        $this->assertSame('Bearer jwt.fake.token', $segundaRequisicao->getHeaderLine('Authorization'));
    }

    public function testLogin401PassaPeloTratamentoDeErroExistente(): void
    {
        // Formato real de erro (RFC 7807): o detail fica ANINHADO em
        // `error.detail`, não no topo do corpo.
        $errorBody = [
            'error' => [
                'type' => 'https://engineapi.com.br/errors/UNAUTHORIZED',
                'title' => 'Não Autorizado',
                'status' => 401,
                'detail' => 'Credenciais inválidas',
                'requestId' => 'req_xyz789',
                'timestamp' => '2026-08-04T12:00:00.000Z',
            ],
        ];

        [$client] = $this->makeClient(401, $errorBody);

        try {
            $client->login('dev@parceiro.com.br', 'senha-errada');
            $this->fail('Esperava EngineApiError em credenciais inválidas (401)');
        } catch (EngineApiError $e) {
            // O 401 propaga íntegro pelo tratamento de erro do SDK, o que
            // garante que login() com credencial errada nunca retorna
            // silenciosamente um array sem token: sempre lança.
            $this->assertSame(401, $e->getStatusCode());
            $this->assertTrue($e->isUnauthorized());
            $this->assertSame($errorBody, $e->getResponse());
            // A mensagem sai de `error.detail` (RFC 7807 aninhado), não da
            // string genérica do Guzzle (ver ErrorMessageExtractionTest).
            $this->assertSame('Credenciais inválidas', $e->getMessage());
        }
    }
}
