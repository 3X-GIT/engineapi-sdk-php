<?php

declare(strict_types=1);

namespace EngineApi;

use RuntimeException;

/**
 * Exceção customizada da Engine API.
 *
 * Fornece propriedades tipadas para identificar o tipo de erro HTTP.
 */
class EngineApiError extends RuntimeException
{
    private int $statusCode;
    private ?array $response;

    public function __construct(string $message, int $statusCode, ?array $response = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    public function isValidationError(): bool
    {
        return $this->statusCode === 400;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}
