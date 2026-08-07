<?php

declare(strict_types=1);

namespace EngineApi\Clients;

use EngineApi\HttpClient;

/**
 * Módulo Empresas: cadastro, certificados e consulta de CNPJ.
 *
 * Cadastro de empresas emissoras em `/companies`. O upload do certificado
 * digital A1 é enviado em `multipart/form-data` (campo `file` mais o campo
 * `password`).
 */
class CompaniesClient
{
    private HttpClient $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /** Cadastrar nova empresa/emissor. Aceita JWT (`token`) ou API Key. */
    public function criar(array $params): array
    {
        return $this->http->post('/companies', $params);
    }

    /** Listar empresas/emissores do parceiro. Aceita JWT ou API Key. */
    public function listar(): array
    {
        return $this->http->get('/companies');
    }

    /** Buscar empresa por ID. Aceita JWT ou API Key. */
    public function buscar(string $id): array
    {
        return $this->http->get("/companies/{$id}");
    }

    /** Consultar CNPJ na Receita Federal. Aceita JWT ou API Key. */
    public function consultarCnpj(string $cnpj): array
    {
        return $this->http->get("/companies/consult/{$cnpj}");
    }

    /**
     * Upload certificado digital A1 (.pfx). Aceita JWT ou API Key.
     *
     * Envia `multipart/form-data` com os campos `file` e `password`.
     *
     * @param string $certificado Conteúdo binário do `.pfx`. Passe
     *   `file_get_contents('certificado.pfx')` (recomendado) ou uma string
     *   base64 com `$base64 = true` (compatibilidade com a assinatura antiga
     *   do SDK 1.0.0, que só aceitava base64 dentro de um corpo JSON).
     */
    public function uploadCertificado(
        string $id,
        string $certificado,
        string $senha,
        string $filename = 'certificado.pfx',
        bool $base64 = false
    ): array {
        $conteudo = $base64 ? base64_decode($certificado) : $certificado;

        return $this->http->postMultipart("/companies/{$id}/certificate", [
            [
                'name' => 'file',
                'contents' => $conteudo,
                'filename' => $filename,
                'headers' => ['Content-Type' => 'application/x-pkcs12'],
            ],
            [
                'name' => 'password',
                'contents' => $senha,
            ],
        ]);
    }
}
