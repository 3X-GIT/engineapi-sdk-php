<?php

declare(strict_types=1);

namespace EngineApi\Tests;

use PHPUnit\Framework\TestCase;
use EngineApi\EngineApiClient;

class EngineApiClientTest extends TestCase
{
    public function testCreatesSubClients(): void
    {
        $client = new EngineApiClient([
            'base_url' => 'http://api.example.test',
        ]);

        $this->assertInstanceOf(\EngineApi\Clients\NfeClient::class, $client->nfe);
        $this->assertInstanceOf(\EngineApi\Clients\CompaniesClient::class, $client->companies);
    }

    public function testAcceptsApiKey(): void
    {
        $client = new EngineApiClient([
            'base_url' => 'http://api.example.test',
            'api_key' => 'ek_live_test123',
        ]);

        // Should not throw — just validates construction
        $this->assertNotNull($client->nfe);
    }

    public function testAcceptsToken(): void
    {
        $client = new EngineApiClient([
            'base_url' => 'http://api.example.test',
            'token' => 'jwt_token_123',
        ]);

        $this->assertNotNull($client->companies);
    }

    public function testAcceptsTimeout(): void
    {
        $client = new EngineApiClient([
            'base_url' => 'http://api.example.test',
            'timeout' => 60.0,
        ]);

        $this->assertNotNull($client->companies);
    }
}
