# VV360

CRM e site para agências de viagem — captação de leads, funil, propostas e orçamentos,
roteiros no padrão "passaporte", financeiro, vouchers, portal/guia do cliente, pesquisa
de satisfação e módulo de grupos/excursões.

Feito para rodar em **hospedagem compartilhada** (GoDaddy cPanel, Apache, PHP 8.4) em
**PHP puro + SQLite**, sem framework e sem etapa de build.

> Produto em evolução a partir do sistema da Vanessa Viagens e Turismo. Código
> proprietário — ver [LICENSE](LICENSE).

## Começar

```bash
# 1. Configuração da instalação
cp dados/config.exemplo.php dados/config.php
#   edite e-mails e defina CRM_CHAVE_INSTALACAO com uma senha temporária

# 2. Rodar localmente
php -S 127.0.0.1:8099 -t .
#   abra http://127.0.0.1:8099/            (site)
#   abra http://127.0.0.1:8099/crm/        (CRM — primeira vez pede a chave de instalação)
```

Depois de criar o administrador, volte `CRM_CHAVE_INSTALACAO` para `''`.

## Publicar na GoDaddy

Gere o pacote e suba o conteúdo pela File Manager para `public_html`:

```bash
scripts/empacotar.sh
```

O banco é criado sozinho no primeiro acesso ao CRM, e as migrações rodam automaticamente.
Veja [docs/INSTALL.md](docs/INSTALL.md) para o passo a passo completo.

## Estrutura

| Pasta        | O que é                                              |
|--------------|------------------------------------------------------|
| raiz         | site público (`index.html`, `img/`, `js/`, ...)      |
| `crm/`       | o CRM (`index.php` roteador, `inc/` módulos)         |
| `d/`         | portal/guia do cliente (link por token)              |
| `r/`         | página do roteiro que o cliente aprova               |
| `api/`       | endpoints do site                                    |
| `dados/`     | `config.php` (por instalação) e `lib.php`            |
| `scripts/`   | utilitários (empacotamento)                          |
| `docs/`      | documentação (instalação, changelog)                 |

## Desenvolvimento

As convenções do projeto (roteador, migrações, segurança, padrão visual, como criar um
módulo) estão em **[CLAUDE.md](CLAUDE.md)** — leia antes de alterar o código.

Lint de um arquivo: `php -l caminho.php`.

## Segurança

Nunca versione: `dados/config.php` real, `chave-documentos.php` (chave de criptografia
dos documentos — guarde um backup à parte), bancos `*.sqlite`, roteiros e documentos de
clientes. Tudo já está no [.gitignore](.gitignore).
