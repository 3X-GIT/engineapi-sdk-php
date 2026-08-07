<?php

declare(strict_types=1);

namespace EngineApi\Tests;

use PHPUnit\Framework\TestCase;
use EngineApi\EngineApiClient;

/**
 * A superfície pública deste SDK cobre NFe, NFCe e NFSe. Regressão: garante
 * que ninguém reintroduz `$client->dfe` ou `EngineApi\Clients\DfeClient`
 * sem ser deliberado.
 */
class DfeRemovedTest extends TestCase
{
    public function testClientNaoTemPropriedadeDfe(): void
    {
        $client = new EngineApiClient(['base_url' => 'http://api.example.test']);

        $this->assertFalse(property_exists($client, 'dfe'));
    }

    public function testClasseDfeClientNaoExisteMais(): void
    {
        $this->assertFalse(class_exists(\EngineApi\Clients\DfeClient::class));
    }
}
