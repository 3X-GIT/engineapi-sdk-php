<?php

declare(strict_types=1);

namespace EngineApi\Tests;

use PHPUnit\Framework\TestCase;
use EngineApi\EngineApiError;

class EngineApiErrorTest extends TestCase
{
    public function testBasicError(): void
    {
        $err = new EngineApiError('Not found', 404);
        $this->assertEquals('Not found', $err->getMessage());
        $this->assertEquals(404, $err->getStatusCode());
        $this->assertTrue($err->isNotFound());
        $this->assertFalse($err->isUnauthorized());
    }

    public function testUnauthorized(): void
    {
        $err = new EngineApiError('Invalid token', 401);
        $this->assertTrue($err->isUnauthorized());
        $this->assertFalse($err->isNotFound());
    }

    public function testRateLimited(): void
    {
        $err = new EngineApiError('Too many requests', 429);
        $this->assertTrue($err->isRateLimited());
    }

    public function testValidationError(): void
    {
        $response = ['errors' => [['field' => 'cnpj']]];
        $err = new EngineApiError('Bad request', 400, $response);
        $this->assertTrue($err->isValidationError());
        $this->assertEquals($response, $err->getResponse());
    }

    public function testServerError(): void
    {
        $err = new EngineApiError('Internal', 500);
        $this->assertTrue($err->isServerError());
    }

    public function test502IsServerError(): void
    {
        $err = new EngineApiError('Bad Gateway', 502);
        $this->assertTrue($err->isServerError());
    }
}
