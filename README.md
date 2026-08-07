# engineAPI — SDK PHP

SDK PHP oficial da **engineAPI**, a infraestrutura fiscal para desenvolvedores: NF-e, NFC-e e NFS-e por uma única API REST.

[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net/)
[![Guzzle](https://img.shields.io/badge/Guzzle-7.8+-green.svg)](https://docs.guzzlephp.org/)
[![Licença](https://img.shields.io/badge/licen%C3%A7a-MIT-green.svg)](LICENSE)

Documentação completa: [docs.engineapi.com.br](https://docs.engineapi.com.br)

## Instalação

```bash
composer require engineapi/sdk
```

Requer PHP 8.1 ou superior.

## Autenticação

Duas formas, as duas suportadas pelo SDK:

- **API Key** (recomendada para servidor a servidor): header `X-API-Key`, sem expiração de sessão.
- **Login com email e senha**: devolve um JWT e já o guarda no cliente para as chamadas seguintes.

```php
use EngineApi\EngineApiClient;

$client = new EngineApiClient([
    'base_url' => 'https://api.engineapi.com.br',
    'api_key'  => 'ek_live_sua_chave_aqui',
]);

// Alternativa: login com email e senha
$client = new EngineApiClient(['base_url' => 'https://api.engineapi.com.br']);
$client->login('voce@suaempresa.com.br', 'sua-senha');
```

## Exemplo mínimo: emitir uma NF-e

Toda resposta de sucesso vem envelopada em `['data' => ..., 'meta' => ...]`.

```php
use EngineApi\EngineApiClient;
use EngineApi\EngineApiError;

$client = new EngineApiClient([
    'base_url' => 'https://api.engineapi.com.br',
    'api_key'  => 'ek_test_sua_chave_aqui',
]);

try {
    $resposta = $client->nfe->emitir([
        'naturezaOperacao' => 'VENDA DE MERCADORIA',
        'idDest' => 1,
        'indFinal' => 1,
        'destinatario' => [
            'cnpjCpf' => '99888777000100',
            'nome' => 'Cliente Exemplo SA',
            'endereco' => [
                'logradouro' => 'Av Goias', 'numero' => '500',
                'bairro' => 'Centro', 'codigoMunicipio' => '5208707',
                'municipio' => 'Goiania', 'uf' => 'GO', 'cep' => '74063010',
            ],
            'indicadorIE' => 9,
        ],
        'items' => [[
            'codigo' => 'PROD001',
            'descricao' => 'Produto Teste',
            'ncm' => '84713012',
            'cfop' => '5102',
            'unidade' => 'UN',
            'quantidade' => 2,
            'valorUnitario' => 150.00,
            'icms' => ['origem' => 0, 'csosn' => '400'],
        ]],
        'pagamentos' => [['forma' => '01', 'valor' => 300.00]],
    ]);

    $nota = $resposta['data'];
    echo "NF-e autorizada: {$nota['accessKey']} ({$nota['status']})\n";
} catch (EngineApiError $e) {
    echo "Erro {$e->getStatusCode()}: {$e->getMessage()}\n";
}
```

O campo dos itens é `items` e o das formas de pagamento é `pagamentos`. O contrato de emissão é estrito: campo desconhecido recebe `400` apontando o nome certo, antes de consumir numeração fiscal. A lista completa de campos está no [catálogo de campos da NF-e](https://docs.engineapi.com.br/api-reference/campos-nfe).

## Módulos

### NF-e

```php
$client->nfe->emitir([...]);
$client->nfe->listar(page: 1, limit: 20, status: 'AUTHORIZED');
$client->nfe->consultar('id-da-nota');
$client->nfe->cancelar('chave_de_acesso', 'Erro de digitacao no destinatario');
$client->nfe->cartaCorrecao('chave_de_acesso', 'Correcao do endereco de entrega');
$client->nfe->status();

file_put_contents('danfe.pdf', $client->nfe->downloadPdf('chave_de_acesso'));
file_put_contents('nota.xml', $client->nfe->downloadXml('chave_de_acesso'));
```

#### Combustíveis com tributação monofásica (CST 02 e 61)

Revenda de combustível ou GLP: o item leva o grupo `combustivel` (código da ANP) e o ICMS monofásico. A alíquota ad rem é em reais por unidade, não é percentual, e a base é a quantidade do produto. O valor precisa bater com `quantidade × alíquota`, senão a emissão é recusada com `422` antes de consumir numeração fiscal. Vale nos dois regimes, inclusive Simples Nacional.

```php
$nfe = $client->nfe->emitir([
    // ... demais campos da nota
    'items' => [[
        'codigo' => 'GLP13',
        'descricao' => 'GAS LIQUEFEITO DE PETROLEO BOTIJAO 13KG',
        'ncm' => '27111910',
        'cfop' => '5656',
        'unidade' => 'kg',
        'quantidade' => 13,
        'valorUnitario' => 8.45,
        'icms' => [
            'origem' => 0,
            'cst' => '61',            // cobrado anteriormente (revenda)
            'qBCMonoRet' => 13,       // quantidade tributada, em kg
            'adRemICMSRet' => 1.2196, // R$ por kg
            'vICMSMonoRet' => 15.85,  // 13 × 1,2196
        ],
        'combustivel' => [
            'cProdANP' => '210203001',
            'descANP' => 'GLP',
            'ufConsumo' => 'GO',
            'pGLP' => 60.5,
            'pGNn' => 39.5,
            'pGNi' => 0,
            'vPart' => 4.35,
        ],
    ]],
]);
```

Na NFC-e (venda ao consumidor no balcão) o leiaute aceita só o CST `61`. A tributação monofásica própria (CST `02`) sai em NF-e.

### Empresas emissoras

Cadastro em `/companies`, com endereço em campos planos (`address`, `number`, `city`...).

```php
$empresa = $client->companies->criar([
    'cnpj' => '99888777000100',
    'name' => 'Minha Empresa LTDA',
    'crt' => 1, // 1=Simples, 2=Simples com excesso de sublimite, 3=Regime Normal, 4=MEI
    'address' => 'Av Goias', 'number' => '500',
    'neighborhood' => 'Centro', 'city' => 'Goiania',
    'state' => 'GO', 'cep' => '74063010', 'ibgeCode' => '5208707',
]);

// Certificado digital A1 (.pfx), enviado em multipart/form-data
$conteudoPfx = file_get_contents('certificado.pfx');
$client->companies->uploadCertificado($empresa['data']['id'], $conteudoPfx, 'senha-do-certificado');

$client->companies->listar();
$client->companies->buscar('id-da-empresa');
$client->companies->consultarCnpj('99888777000100');
```

## Tratamento de erros

Toda falha vira `EngineApiError`, com o status HTTP e o corpo da resposta preservados.

```php
use EngineApi\EngineApiError;

try {
    $client->nfe->emitir([...]);
} catch (EngineApiError $e) {
    echo "Erro {$e->getStatusCode()}: {$e->getMessage()}\n";

    if ($e->isValidationError()) {      // 400
        print_r($e->getResponse());
    } elseif ($e->isUnauthorized()) {   // 401
        echo "Chave ou token inválido\n";
    } elseif ($e->isRateLimited()) {    // 429
        echo "Limite de requisições atingido\n";
    } elseif ($e->isServerError()) {    // 5xx
        echo "Falha do lado do servidor\n";
    }
}
```

A mensagem curta sai do corpo de erro no formato RFC 7807 (`error.detail`), o mesmo texto que a referência da API documenta.

## Política de versão

Versionamento semântico (SemVer):

- **MAJOR**: mudança incompatível na API pública do SDK.
- **MINOR**: recurso novo mantendo compatibilidade.
- **PATCH**: correção compatível.

Cada versão publicada no Packagist corresponde a uma tag Git deste repositório (`v1.2.0`, por exemplo). A versão do SDK é independente da versão da API REST (`/v1`), que segue o próprio ciclo. As mudanças de cada versão ficam no [CHANGELOG.md](CHANGELOG.md); as do produto, no [changelog público](https://docs.engineapi.com.br/changelog).

## Desenvolvimento

```bash
git clone https://github.com/3X-GIT/engineapi-sdk-php.git
cd engineapi-sdk-php
composer install

./vendor/bin/phpunit   # testes
composer validate --strict
```

## Suporte

- Documentação: [docs.engineapi.com.br](https://docs.engineapi.com.br)
- Dúvida ou defeito **neste SDK**: [abra uma issue](https://github.com/3X-GIT/engineapi-sdk-php/issues)
- Assuntos de conta, plano ou emissão: contato@3xtec.com.br
- Site: [engineapi.com.br](https://engineapi.com.br)

## Licença

MIT. Ver [LICENSE](LICENSE).
