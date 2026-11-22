<?php
// ============================================================
//  VV360 — CONFIGURAÇÃO (modelo)
//  Copie este arquivo para "config.php" e edite com os dados da agência.
//  O config.php real NÃO vai para o repositório (está no .gitignore).
// ============================================================

// E-mail que recebe o aviso de cada nova mensagem do site.
const EMAIL_AVISO = 'contato@suaagencia.com.br';

// E-mail que recebe os pedidos de proposta enviados pelo site.
const EMAIL_PROPOSTAS = 'contato@suaagencia.com.br';

// Remetente dos avisos (precisa ser um e-mail do próprio domínio).
const EMAIL_REMETENTE = 'site@suaagencia.com.br';

// ============================================================
//  CRM (pasta crm/)
// ============================================================

// Chave de instalação: defina uma senha temporária aqui para criar o
// primeiro usuário administrador. Depois da instalação, volte este valor
// para '' (vazio) para desativar a tela de instalação.
const CRM_CHAVE_INSTALACAO = '';

// Onde fica o banco de dados do CRM. Por padrão, fora da pasta pública
// (public_html), que é o lugar mais seguro. Se o servidor não permitir,
// o CRM usa automaticamente a pasta dados/, que também é bloqueada.
define('CRM_PASTA_DADOS', dirname(__DIR__, 2) . '/vv-crm-dados');
