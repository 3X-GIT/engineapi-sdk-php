<?php

declare(strict_types=1);

namespace EngineApi\Tests;

use EngineApi\Clients\CompaniesClient;
use EngineApi\HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Testes de contrato de `CompaniesClient`: prova de fio (não mock de
 * método). Injeta um `MockHandler` do Guzzle no `HttpClient` e captura o
 * request que o SDK monta de verdade (método, caminho, headers e corpo).
 */
class CompaniesClientTest extends TestCase
{
    /**
     * @param array<int, array{request: \Psr\Http\Message\RequestInterface}> $history
     *   Passado POR REFERÊNCIA de propósito: `GuzzleHttp\Middleware::history()`
     *   também recebe `array &$container` — se o array fosse devolvido por
     *   valor (return normal / list-assign sem `&`), a mutação feita pelo
     *   middleware durante a requisição não apareceria mais no array que o
     *   chamador enxerga (cópia desconectada, footgun clássico de
     *   copy-on-write do PHP com referências dentro de retorno de função).
     */
    private function makeCapturingClient(
        array &$history,
        int $status = 200,
        array $body = ['success' => true]
    ): HttpClient {
        $history = [];
        $mock = new MockHandler([new Response($status, [], json_encode($body))]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new HttpClient(
            baseUrl: 'http://example.test',
            token: 'jwt-fake',
            handlerStack: $stack,
        );
    }

    public function testCriarBateEmPostCompanies(): void
    {
        $history = [];
        $http = $this->makeCapturingClient($history, 201, ['success' => true, 'data' => ['id' => 'empresa-1']]);
        $companies = new CompaniesClient($http);

        $result = $companies->criar(['cnpj' => '99888777000100', 'name' => 'Minha Empresa LTDA']);

        $this->assertCount(1, $history);
        $req = $history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/v1/companies', $req->getUri()->getPath());
        $this->assertSame('Bearer jwt-fake', $req->getHeaderLine('Authorization'));

        $parsed = json_decode((string) $req->getBody(), true);
        $this->assertSame('Minha Empresa LTDA', $parsed['name']);
        $this->assertSame('empresa-1', $result['data']['id']);
    }

    public function testBuscarBateEmGetCompaniesId(): void
    {
        $history = [];
        $http = $this->makeCapturingClient($history, 200, ['success' => true, 'data' => ['id' => 'empresa-1']]);
        $companies = new CompaniesClient($http);

        $companies->buscar('empresa-1');

        $this->assertCount(1, $history);
        $this->assertSame('/v1/companies/empresa-1', $history[0]['request']->getUri()->getPath());
    }

    public function testConsultarCnpjBateEmGetCompaniesConsult(): void
    {
        $history = [];
        $http = $this->makeCapturingClient($history);
        $companies = new CompaniesClient($http);

        $companies->consultarCnpj('99888777000100');

        $this->assertCount(1, $history);
        $this->assertSame('/v1/companies/consult/99888777000100', $history[0]['request']->getUri()->getPath());
    }

    public function testUploadCertificadoMandaMultipartComFileEPassword(): void
    {
        $history = [];
        $http = $this->makeCapturingClient($history);
        $companies = new CompaniesClient($http);

        $companies->uploadCertificado('empresa-1', 'conteudo-fake-do-pfx', 'senha123');

        $this->assertCount(1, $history);
        $req = $history[0]['request'];
        $this->assertSame('/v1/companies/empresa-1/certificate', $req->getUri()->getPath());
        $this->assertMatchesRegularExpression(
            '#^multipart/form-data; boundary=#',
            $req->getHeaderLine('Content-Type'),
        );

        $body = (string) $req->getBody();
        $this->assertStringContainsString('name="password"', $body);
        $this->assertStringContainsString('senha123', $body);
        $this->assertStringContainsString('name="file"; filename="certificado.pfx"', $body);
        $this->assertStringContainsString('conteudo-fake-do-pfx', $body);
    }

    public function testUploadCertificadoAceitaBase64ParaRetrocompat(): void
    {
        $history = [];
        $http = $this->makeCapturingClient($history);
        $companies = new CompaniesClient($http);

        $companies->uploadCertificado(
            'empresa-1',
            base64_encode('conteudo-fake-do-pfx'),
            'senha123',
            'certificado.pfx',
            true,
        );

        $this->assertCount(1, $history);
        $body = (string) $history[0]['request']->getBody();
        $this->assertStringContainsString('conteudo-fake-do-pfx', $body);
    }
}
