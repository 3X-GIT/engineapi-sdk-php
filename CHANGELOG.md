# Changelog

Todas as mudanças relevantes deste SDK. O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o versionamento é [SemVer](https://semver.org/lang/pt-BR/).

## 1.2.0

Primeira versão distribuída pelo Packagist. As versões anteriores circularam apenas junto do produto, sem publicação em registry.

### Adicionado

- ICMS monofásico de combustíveis (CST 02 e 61) para revenda de posto e GLP, com o grupo `combustivel` e os campos `qBCMono`, `adRemICMS`, `vICMSMono`, `qBCMonoRet`, `adRemICMSRet` e `vICMSMonoRet` no item. Vale em NF-e e NFC-e, nos dois regimes.
- `cBenef` no item: código do benefício fiscal concedido pela UF, transcrito sem alteração para o documento. Algumas UFs exigem o campo quando o CST tem benefício.

### Alterado

- `login()` desembrulha o envelope `{data, meta}` das respostas de sucesso, guarda o `access_token` e devolve o conteúdo de `data`. A chamada seguinte já sai autenticada.
- Cadastro de empresas emissoras em `/companies`, com o certificado digital A1 enviado em `multipart/form-data` (campos `file` e `password`).
- A mensagem de `EngineApiError` sai do corpo de erro no formato RFC 7807 (`error.detail`, com `error.title` como segunda opção).
- A superfície pública do SDK cobre NF-e, NFC-e e NFS-e.
- O `composer.json` não declara mais o campo `version`: a versão publicada vem da tag Git deste repositório, como recomenda o Packagist.

### Corrigido

- Suíte de testes passou a exercitar o contrato de verdade (método, caminho, headers e corpo capturados no fio).
