<?php

declare(strict_types=1);

namespace EngineApi\Clients;

use EngineApi\HttpClient;

/**
 * Módulo NFe — Emissão, consulta, cancelamento, CC-e, DANFE e XML.
 */
class NfeClient
{
    private HttpClient $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /** Emitir NFe (Modelo 55). */
    public function emitir(array $params): array
    {
        return $this->http->post('/nfe', $params);
    }

    /** Listar NFes com paginação e filtros. */
    public function listar(int $page = 1, int $limit = 20, ?string $issuerId = null, ?string $status = null): array
    {
        $query = ['page' => $page, 'limit' => $limit];
        if ($issuerId !== null) $query['issuerId'] = $issuerId;
        if ($status !== null) $query['status'] = $status;
        return $this->http->get('/nfe', $query);
    }

    /** Consultar NFe por ID. */
    public function consultar(string $id): array
    {
        return $this->http->get("/nfe/{$id}");
    }

    /** Cancelar NFe. */
    public function cancelar(string $accessKey, string $justificativa): array
    {
        return $this->http->post("/nfe/{$accessKey}/cancelar", [
            'justificativa' => $justificativa,
        ]);
    }

    /** Carta de Correção (CC-e). */
    public function cartaCorrecao(string $accessKey, string $correcao): array
    {
        return $this->http->post("/nfe/{$accessKey}/carta-correcao", [
            'correcao' => $correcao,
        ]);
    }

    /** Download DANFE (PDF). */
    public function downloadPdf(string $accessKey): string
    {
        return $this->http->download("/nfe/pdf/{$accessKey}");
    }

    /** Download XML assinado. */
    public function downloadXml(string $accessKey): string
    {
        return $this->http->download("/nfe/xml/{$accessKey}");
    }

    /** Download XML da CC-e. */
    public function downloadCceXml(string $accessKey): string
    {
        return $this->http->download("/nfe/cce/{$accessKey}/xml");
    }

    /** Download PDF da CC-e. */
    public function downloadCcePdf(string $accessKey): string
    {
        return $this->http->download("/nfe/cce/{$accessKey}/pdf");
    }

    /** Status do serviço SEFAZ. */
    public function status(): array
    {
        return $this->http->get('/nfe/status');
    }
}
