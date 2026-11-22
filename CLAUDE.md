# VV360 — guia para o Claude

Este arquivo orienta qualquer sessão do Claude (aqui no app ou no Claude Code) a
trabalhar no VV360 sem reexplicar o projeto. Leia antes de alterar código.

## O que é

VV360 é o CRM da **Vanessa Viagens e Turismo**, em processo de virar **produto para
vender a outras agências de viagem**. Cobre site institucional, captação de leads,
funil, propostas/orçamentos, roteiros (padrão "passaporte"), financeiro, vouchers,
portal do cliente/guia da viagem, pesquisa de satisfação e módulo de grupos.

## Stack e ambiente

- **PHP puro + SQLite**, sem framework e sem etapa de build.
- Roda em **hospedagem compartilhada GoDaddy (cPanel, Apache, PHP 8.4)**. Qualquer
  solução precisa funcionar nesse ambiente: sem Composer obrigatório, sem workers,
  sem serviços externos que exijam porta aberta. A GoDaddy derruba requisições
  longas (timeout), então **nada de chamadas síncronas demoradas** (ex.: gerar
  roteiro via API trava; manter modo manual/leve).
- A raiz do repositório é o conteúdo de `public_html`.

## Mapa de pastas

- `index.html`, `privacidade.html`, `img/`, `js/`, `destinos/`, `s/` — site público.
- `crm/` — o CRM. `crm/index.php` é o roteador; `crm/inc/*.php` são os módulos.
- `d/` — portal/guia do cliente (link privado por token).
- `r/` — página do roteiro que o cliente abre e aprova.
- `api/` — endpoints do site (ex.: pedido de proposta).
- `dados/` — `config.php` (por instalação, fora do repo) e `lib.php` (helpers base).
- `painel/` — material estático.

## Convenções que NÃO podem ser quebradas

- **Roteador** (`crm/index.php`): `$p = preg_replace('/[^a-z]/', '', $_GET['p'])`.
  Isso **remove dígitos e underscores**, então nome de rota é só letras minúsculas
  (`exportarclaude`, não `exportar_claude`).
- **POST**: todo POST cai em `crm/inc/acoes.php` após `checar_csrf()`. O dispatch é
  por prefixo da ação: `fin_` → `acao_financeiro()`, `prop_` → `acao_propostas()`,
  `grupo_` → `acao_grupos()`. Um módulo novo segue o mesmo padrão.
- **Migrações de banco**: `crm/inc/app.php`, constante `CRM_VERSAO` e função
  `crm_migrar()` com blocos `if ($v < N)`. Tabelas novas entram como
  `CREATE TABLE IF NOT EXISTS` dentro do bloco da versão nova (roda em instalação
  nova e em upgrade). **Sempre suba `CRM_VERSAO`** ao adicionar uma migração.
- **Escape**: use `h()` (htmlspecialchars) em toda saída. Consultas sempre com
  prepared statements (`q()`, `um()`, `todos()`, `valor()`).
- **Criptografia**: documentos são cifrados com AES-256-GCM via `cripto()`/`decripto()`.
  A chave fica em `chave-documentos.php`, **fora da pasta pública** em produção.
  Nunca commitar, nunca apagar, e orientar backup à parte — sem a chave os documentos
  cifrados são perdidos.

## Padrão visual "passaporte"

Azul-marinho `#1B2A4A`, azul `#3D5A8A`; títulos em Georgia (serif); corpo em Segoe UI;
rótulos/horários em "Courier New" maiúsculo. Roteiros e materiais de cliente seguem
esse padrão (ver skill `vanessa-roteiro-html`).

## Como adicionar um módulo ao CRM

1. Criar `crm/inc/<modulo>.php` com funções `pagina_*` (telas) e `acao_<modulo>_*`.
2. `require` em `crm/index.php` e adicionar os `case` no `switch` de páginas
   (rota só com letras minúsculas).
3. Dispatch da ação em `crm/inc/acoes.php` (`if (strpos($a,'<modulo>_')===0) ...`).
4. Item no menu em `crm/inc/layout.php` (array `$grupos`).
5. Tabelas via migração em `app.php` + subir `CRM_VERSAO`.

## Invariantes de segurança e privacidade

- `CRM_CHAVE_INSTALACAO` deve ficar `''` no repositório e nos pacotes.
- Nunca commitar `*.sqlite`, `config.php` real, `chave-documentos.php`, roteiros ou
  documentos de clientes (ver `.gitignore`).
- Os arquivos "Exportar para Claude" **nunca** contêm CPF, RG, passaporte, data de
  nascimento, contato pessoal, custo, comissão, markup ou valor por item. No modo
  PROPOSTA exporta só o valor total; no modo ROTEIRO FINAL, nenhum valor.

## Testar

- Lint: `php -l arquivo.php` em cada arquivo alterado.
- Local: `php -S 127.0.0.1:8099 -t .` com `CRM_PASTA_DADOS` apontando para um diretório
  temporário e `CRM_CHAVE_INSTALACAO` com uma chave de teste; instalar admin e exercitar
  as telas via curl antes de empacotar.

## Empacotar para a GoDaddy

`scripts/empacotar.sh` gera o .zip excluindo `.sqlite`, `roteiros/`, `docs/` e o
`config.php` real. Confirmar `CRM_CHAVE_INSTALACAO = ''` antes de gerar.

## Roadmap do módulo de Grupos

- **Fase 1 (feito)**: núcleo — grupo, painel de viabilidade, participantes na jornada
  Interesse → Confirmando → Confirmado.
- **Fase 2 (feito)**: hotsite público por grupo em `/g/?t=TOKEN` (padrão passaporte,
  vagas ao vivo, "Tenho interesse" cria participante, "Quero mais detalhes"/WhatsApp) +
  geradores de prompt de flyer e de roteiro na ficha. Identidade da agência via
  `AGENCIA_NOME/WHATSAPP/INSTAGRAM` e `SITE_URL` em `dados/config.php`.
- **Fase 3**: participante confirmado vira venda no Financeiro e ganha portal/guia.
