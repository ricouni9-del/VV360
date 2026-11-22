<?php
// Vanessa Viagens e Turismo · CRM
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/paginas.php';
require __DIR__ . '/inc/clientes.php';
require __DIR__ . '/inc/financeiro.php';
require __DIR__ . '/inc/propostas.php';
require __DIR__ . '/inc/grupos.php';
require __DIR__ . '/inc/viagem.php';
require __DIR__ . '/inc/exportar.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
crm_iniciar_sessao();

$p = preg_replace('/[^a-z]/', '', $_GET['p'] ?? 'inicio');
$temUsuarios = (int)valor('SELECT COUNT(*) FROM usuarios') > 0;

// ---------- Instalação (primeiro administrador) ----------
if (!$temUsuarios) {
    $erro = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $chave = (string)($_POST['chave'] ?? '');
        if (CRM_CHAVE_INSTALACAO === '' || !hash_equals(CRM_CHAVE_INSTALACAO, $chave)) { sleep(2); $erro = 'Chave de instalação incorreta.'; }
        elseif (mb_strlen(post('nome')) < 2 || !filter_var(post('email'), FILTER_VALIDATE_EMAIL)) $erro = 'Informe nome e e-mail válidos.';
        elseif (strlen($_POST['senha'] ?? '') < 10) $erro = 'A senha precisa ter pelo menos 10 caracteres.';
        else {
            q('INSERT INTO usuarios (nome, email, senha_hash, papel, criado_em) VALUES (?,?,?,?,?)',
              [post('nome', 80), post('email', 120), password_hash($_POST['senha'], PASSWORD_DEFAULT), 'admin', agora()]);
            redirecionar('./?p=entrar', 'CRM instalado. Entre com seu e-mail e senha.');
        }
    }
    pagina_instalar($erro); exit;
}

// ---------- Entrar / sair ----------
if ($p === 'sair') { if (usuario()) registrar('saiu'); session_destroy(); header('Location: ./?p=entrar'); exit; }
if ($p === 'entrar') {
    if (usuario()) redirecionar('./');
    $erro = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checar_csrf();
        $chave = 'login:' . ip_hash();
        if (bloqueado($chave)) $erro = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
        else {
            $u = um('SELECT * FROM usuarios WHERE email = ? AND ativo = 1', [post('email', 120)]);
            if ($u && password_verify((string)($_POST['senha'] ?? ''), $u['senha_hash'])) {
                session_regenerate_id(true);
                $_SESSION['uid'] = $u['id']; $_SESSION['csrf'] = bin2hex(random_bytes(16));
                if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT))
                    q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($_POST['senha'], PASSWORD_DEFAULT), $u['id']]);
                registrar('entrou');
                redirecionar('./');
            }
            falhou($chave); sleep(1); $erro = 'E-mail ou senha incorretos.';
        }
    }
    pagina_entrar($erro); exit;
}

exigir_login();

// ---------- Envios por e-mail (links GET autenticados) ----------
if ($p === 'acao' && in_array($_GET['a'] ?? '', ['ia_testar', 'ia_remover'], true)) {
    exigir_admin();
    if ($_GET['a'] === 'ia_remover') { set_cfg('ia_chave_enc', ''); registrar('removeu chave da API'); redirecionar('./?p=usuarios', 'Chave da API removida.'); }
    $r = ia_chamar('Responda apenas: OK', 'Você é um verificador de conexão. Responda somente OK.', ia_modelo_roteiro(), 20);
    redirecionar('./?p=usuarios', $r['ok'] ? 'Conexão com o Claude funcionando!' : '!Falha no teste: ' . $r['erro']);
}
if ($p === 'acao' && in_array($_GET['a'] ?? '', ['doc_email', 'pesq_email'], true)) {
    $lid = (int)($_GET['lead'] ?? 0);
    $l = um('SELECT l.*, c.nome cli, c.email FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.id = ?', [$lid]);
    if (!$l) redirecionar('./?p=funil', '!Lead não encontrado.');
    $tipo = $_GET['a'] === 'pesq_email' ? 'pesquisa' : 'docs';
    if (!$l['email']) redirecionar('./?p=lead&id=' . $lid . ($tipo === 'pesquisa' ? '#pesquisa' : '#documentos'), '!O cliente não tem e-mail cadastrado.');
    $portal = portal_do_lead($lid, $tipo, true);
    $link = url_portal($tipo, $portal['token']);
    $primeiro = explode(' ', trim($l['cli']))[0];
    if ($tipo === 'docs') { $assunto = 'Os documentos da sua viagem · Vanessa Viagens e Turismo'; $corpo = '<p>Olá, ' . h($primeiro) . '!</p><p>Reuni todos os documentos da sua viagem num link seguro, para você acessar quando precisar, inclusive durante a viagem:</p><p><a href="' . h($link) . '">Ver os documentos da viagem</a></p>'; }
    else { $assunto = 'Como foi sua viagem? · Vanessa Viagens e Turismo'; $corpo = '<p>Olá, ' . h($primeiro) . '!</p><p>Sua opinião me ajuda a cuidar ainda melhor de você. Leva só dois minutinhos:</p><p><a href="' . h($link) . '">Responder a pesquisa</a></p>'; }
    $corpo = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;color:#2A2E35">' . $corpo . '<p style="color:#6B7280;font-size:13px">Com carinho, Vanessa Viagens e Turismo.</p></div>';
    $ok = @mail($l['email'], '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpo, "From: Vanessa Viagens e Turismo <" . EMAIL_REMETENTE . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8");
    registrar('enviou link por e-mail', $tipo . ' · lead #' . $lid);
    redirecionar('./?p=lead&id=' . $lid . ($tipo === 'pesquisa' ? '#pesquisa' : '#documentos'), $ok ? 'E-mail enviado ao cliente.' : 'Tentativa de envio feita (confira se o e-mail chegou).');
}

// ---------- Ações (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') { checar_csrf(); require __DIR__ . '/inc/acoes.php'; exit; }

// ---------- Páginas ----------
switch ($p) {
    case 'funil':    pagina_funil(); break;
    case 'lead':     pagina_lead((int)($_GET['id'] ?? 0)); break;
    case 'novo':     pagina_novo(); break;
    case 'clientes': pagina_clientes(); break;
    case 'cliente':  pagina_cliente((int)($_GET['id'] ?? 0)); break;
    case 'novocliente': pagina_cliente_form(); break;
    case 'documentos': pagina_documentos((int)($_GET['id'] ?? 0)); break;
    case 'viagens':  pagina_viagens(); break;
    case 'importar': pagina_importar(); break;
    case 'modelo':   baixar_modelo(); break;
    case 'grupos':   pagina_grupos(); break;
    case 'grupo':    pagina_grupo((int)($_GET['id'] ?? 0)); break;
    case 'grupoprompt': pagina_grupo_prompt((int)($_GET['id'] ?? 0)); break;
    case 'proposta': pagina_proposta((int)($_GET['id'] ?? 0)); break;
    case 'propostapdf': proposta_pdf((int)($_GET['id'] ?? 0)); break;
    case 'propostaclaude': proposta_claude((int)($_GET['id'] ?? 0)); break;
    case 'exportarclaude': pagina_exportar(); break;
    case 'gerarroteiro': pagina_gerar_roteiro(); break;
    case 'financeiro': pagina_financeiro(); break;
    case 'venda':    pagina_venda((int)($_GET['id'] ?? 0)); break;
    case 'exportarfin': exportar_financeiro(); break;
    case 'ajuda':    header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__ . '/inc/manual.html'); exit;
    case 'tarefas':  pagina_tarefas(); break;
    case 'mensagens': pagina_mensagens(); break;
    case 'usuarios': exigir_admin(); pagina_usuarios(); break;
    case 'conta':    pagina_conta(); break;
    case 'satisfacao': pagina_satisfacao(); break;
    case 'vouchers': pagina_vouchers(); break;
    case 'vouchernovo': pagina_voucher_novo(); break;
    case 'voucher': pagina_voucher((int)($_GET['id'] ?? 0)); break;
    case 'baixardoc': exigir_login(); baixar_doc((int)($_GET['id'] ?? 0)); break;
    case 'exportar': exportar_csv(); break;
    case 'dadoscliente': exportar_cliente((int)($_GET['id'] ?? 0)); break;
    default:         pagina_inicio();
}
