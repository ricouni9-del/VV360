<?php
// Recebe o "Pedir uma proposta": grava o lead no CRM e, no envio por e-mail, avisa a Vanessa e confirma ao cliente.
require dirname(__DIR__) . '/crm/inc/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder($ok, $msg, $cod = 200, $extra = []) {
    http_response_code($cod);
    echo json_encode(['ok' => $ok, 'mensagem' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}
function campo($k, $max = 200) { return trim(str_replace(["\r", "\0"], '', mb_substr((string)($_POST[$k] ?? ''), 0, $max))); }
function linha_unica($s) { return trim(preg_replace('/[\r\n]+/', ' ', $s)); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método não permitido.', 405);
if (!empty($_POST['site'])) responder(true, 'Recebido.');
$inicio = (int)($_POST['t'] ?? 0);
if ($inicio && (time() - $inicio) < 5) responder(false, 'Envio muito rápido. Tente novamente.', 429);

$d = [
    'nome' => linha_unica(campo('nome', 80)), 'whats' => linha_unica(campo('whats', 30)), 'email' => linha_unica(campo('email', 120)),
    'preferencia' => linha_unica(campo('preferencia', 20)), 'destino' => campo('destino', 150), 'periodo' => campo('periodo', 100),
    'duracao' => campo('duracao', 40), 'flexibilidade' => campo('flexibilidade', 60), 'origem' => campo('origem', 100),
    'ocasiao' => campo('ocasiao', 60), 'estilos' => campo('estilos', 300), 'adultos' => campo('adultos', 3),
    'criancas' => campo('criancas', 400), 'orcamento' => campo('orcamento', 40), 'mensagem' => campo('mensagem', 3000),
];
$canais = ['Site', 'Google Ads', 'Busca Google', 'Instagram', 'Facebook'];
$canal = in_array($_POST['canal'] ?? '', $canais, true) ? $_POST['canal'] : 'Site';
$d['gclid'] = substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($_POST['gclid'] ?? '')), 0, 200) ?: null;
$via = ($_POST['via'] ?? '') === 'whatsapp' ? 'whatsapp' : 'email';
if (mb_strlen($d['nome']) < 2) responder(false, 'Informe seu nome.', 422);
if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $d['email'] = '';
if ($via === 'email' && $d['email'] === '') responder(false, 'Informe um e-mail válido para receber a confirmação.', 422);
if ($via === 'whatsapp' && $d['email'] === '' && strlen(preg_replace('/\D/', '', $d['whats'])) < 10) responder(false, 'Informe um contato.', 422);

// Limite: até 5 pedidos por hora do mesmo endereço
if (!limite_formulario('proposta:' . ip_hash())) responder(false, 'Muitos envios em pouco tempo. Tente mais tarde ou use o WhatsApp.', 429);

$rot = [
    'destino' => 'Destino', 'periodo' => 'Quando', 'duracao' => 'Duração', 'flexibilidade' => 'Datas',
    'origem' => 'Saindo de', 'ocasiao' => 'Ocasião', 'adultos' => 'Adultos', 'criancas' => 'Crianças/jovens',
    'estilos' => 'Não pode faltar', 'orcamento' => 'Investimento por pessoa', 'mensagem' => 'Observações',
];
if ($d['destino'] === '') $d['destino'] = 'Ainda não definido';
$texto = '';
foreach ($rot as $k => $r) if ($d[$k] !== '') $texto .= "$r: {$d[$k]}\n";
$contato = $d['email'] . ($d['whats'] ? ' · ' . $d['whats'] : '');

// Grava direto no CRM
try { $lid = crm_registrar_proposta($d, $canal, $via); $codigo = valor('SELECT codigo FROM leads WHERE id = ?', [$lid]); }
catch (Throwable $e) { responder(false, 'Não conseguimos registrar seu pedido agora. Tente pelo WhatsApp.', 500); }
if ($via === 'whatsapp') responder(true, 'Registrado.', 200, ['codigo' => $codigo]);

// E-mail em HTML para a Vanessa (responder = responde direto ao cliente)
$linhas = '';
$tab = ['Nome' => $d['nome'], 'E-mail' => $d['email'], 'WhatsApp' => $d['whats'], 'Prefere retorno por' => $d['preferencia']];
foreach ($rot as $k => $r) $tab[$r] = $d[$k];
foreach ($tab as $r => $val) {
    if ($val === '') continue;
    $linhas .= '<tr><td style="padding:8px 14px 8px 0;color:#6B7280;vertical-align:top;white-space:nowrap">' . h($r)
            . '</td><td style="padding:8px 0;color:#2A2E35">' . nl2br(h($val)) . '</td></tr>';
}
$zap = preg_replace('/\D/', '', $d['whats']);
$botoes = '<a href="mailto:' . h($d['email']) . '" style="background:#3D5A8A;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;margin-right:8px">Responder por e-mail</a>'
        . ($zap ? '<a href="https://wa.me/' . (strlen($zap) <= 11 ? '55' : '') . $zap . '" style="background:#1E8E4E;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Abrir WhatsApp</a>' : '');
$html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:620px;margin:0 auto">'
      . '<div style="background:#1B2A4A;color:#fff;padding:22px 26px;border-radius:8px 8px 0 0"><div style="font-size:12px;letter-spacing:.15em;color:#6E8CB8">VANESSA VIAGENS E TURISMO</div>'
      . '<div style="font-family:Georgia,serif;font-size:24px;margin-top:6px">Novo pedido de proposta</div><div style="font-size:13px;color:#B9C2CF;margin-top:4px">Protocolo ' . h($codigo) . '</div></div>'
      . '<div style="border:1px solid #E2E5EA;border-top:none;padding:22px 26px;border-radius:0 0 8px 8px">'
      . '<table style="border-collapse:collapse;font-size:15px;width:100%">' . $linhas . '</table>'
      . '<p style="margin:22px 0 6px">' . $botoes . '</p></div></div>';

function enviar_email_html($para, $assunto, $html, $responder_para = '') {
    $b = md5(uniqid('', true));
    $cab = "From: Vanessa Viagens e Turismo <" . EMAIL_REMETENTE . ">\r\n"
         . ($responder_para ? "Reply-To: $responder_para\r\n" : '')
         . "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$b\"";
    $txt = trim(html_entity_decode(strip_tags(str_replace(['</tr>', '<br />', '</p>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $corpo = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n$txt\r\n"
           . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n$html\r\n--$b--";
    return @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpo, $cab);
}

$ok = enviar_email_html(EMAIL_PROPOSTAS, "[$codigo] Pedido de proposta: {$d['nome']} · {$d['destino']}", $html,
                   '"' . str_replace('"', '', $d['nome']) . '" <' . $d['email'] . '>');

// Confirmação para o cliente
$primeiro = explode(' ', $d['nome'])[0];
$conf = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:620px;margin:0 auto">'
      . '<div style="background:#1B2A4A;color:#fff;padding:22px 26px;border-radius:8px 8px 0 0"><div style="font-size:12px;letter-spacing:.15em;color:#6E8CB8">VANESSA VIAGENS E TURISMO</div>'
      . '<div style="font-family:Georgia,serif;font-size:24px;margin-top:6px">Recebemos seu pedido, ' . h($primeiro) . '!</div><div style="font-size:13px;color:#B9C2CF;margin-top:4px">Protocolo ' . h($codigo) . '</div></div>'
      . '<div style="border:1px solid #E2E5EA;border-top:none;padding:22px 26px;border-radius:0 0 8px 8px;color:#2A2E35;font-size:15px;line-height:1.6">'
      . '<p>Obrigada por confiar a sua próxima viagem a nós. A Vanessa já recebeu as informações e vai entrar em contato'
      . ($d['preferencia'] ? ' por ' . h(mb_strtolower($d['preferencia'])) : '') . ' para a conversa inicial.</p>'
      . '<p style="font-family:Georgia,serif;font-style:italic;font-size:18px;color:#1B2A4A">“Eu cuido de você e da sua família. Apenas relaxe e curta o seu momento.”</p>'
      . '<p style="color:#6B7280;font-size:13.5px">Resumo do seu pedido:</p><table style="border-collapse:collapse;font-size:14px;width:100%">' . $linhas . '</table>'
      . '<p style="margin-top:22px;font-size:13.5px;color:#6B7280">Vanessa Viagens e Turismo · WhatsApp (11) 97543-9074 · vanessaviagenseturismo.com.br</p></div></div>';
enviar_email_html($d['email'], "Recebemos seu pedido de proposta ($codigo) · Vanessa Viagens e Turismo", $conf, EMAIL_PROPOSTAS);

responder(true, "Obrigada, $primeiro! Seu protocolo é $codigo. A Vanessa recebeu seu pedido e vai entrar em contato"
    . ($d['preferencia'] ? ' por ' . mb_strtolower($d['preferencia']) : '') . '. Enviamos uma confirmação para ' . $d['email'] . '.', 200, ['codigo' => $codigo]);
