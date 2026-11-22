<?php
// Portal de documentos da viagem: vanessaviagenseturismo.com.br/d/?t=...
require dirname(__DIR__) . '/crm/inc/app.php';
require dirname(__DIR__) . '/crm/inc/viagem.php';
header('X-Robots-Tag: noindex, nofollow'); header('Referrer-Policy: no-referrer');

$t = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
$portal = strlen($t) === 24 ? um("SELECT * FROM portais WHERE token = ? AND tipo = 'docs'", [$t]) : null;

function tela($titulo, $msg) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vanessa Viagens e Turismo</title><body style="font-family:Georgia,serif;background:#1B2A4A;color:#fff;display:grid;place-items:center;min-height:90vh;text-align:center;padding:24px"><div><h1 style="font-weight:400;font-size:22px">' . $titulo . '</h1><p style="font-family:system-ui;color:#B9C2CF">' . $msg . '</p><p><a href="https://wa.me/5511975439074" style="color:#8FD3F4">Falar com a Vanessa</a></p></div></body>';
    exit;
}
if (!$portal) { http_response_code(404); tela('Link não encontrado', 'Confira o endereço com a Vanessa.'); }
if ($portal['revogado']) { http_response_code(410); tela('Link indisponível', 'Este link foi desativado.'); }
if ($portal['expira_em'] && $portal['expira_em'] < hoje()) { http_response_code(410); tela('Link expirado', 'O acesso a estes documentos expirou.'); }

$lead = um('SELECT l.*, c.nome cliente, c.nascimento FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.id = ?', [$portal['lead_id']]);
$exige_trava = !empty($lead['nascimento']);
$liberado = !$exige_trava;

if ($exige_trava) {
    session_name('vvportal'); @session_start();
    $chave = 'doc_ok_' . $portal['id'];
    if (!empty($_SESSION[$chave])) $liberado = true;
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nasc'])) {
        if (($_POST['nasc'] ?? '') === $lead['nascimento']) { $_SESSION[$chave] = true; $liberado = true; }
        else { sleep(1); $erro = 'Data não confere. Tente novamente.'; }
    }
}

if (!$liberado) {
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex"><title>Documentos da viagem</title>
    <style>body{margin:0;font-family:'Segoe UI',system-ui,Arial,sans-serif;background:#1B2A4A;color:#fff;display:grid;place-items:center;min-height:100vh;padding:24px}
    .c{background:#243654;border-radius:14px;padding:30px;max-width:380px;width:100%}.c h1{font-family:Georgia,serif;font-weight:400;font-size:22px;margin:0 0 6px}.c p{color:#B9C2CF;font-size:14px;margin:0 0 18px}
    label{font-size:13px;color:#DDE5F1;display:block;margin:0 0 6px}input{width:100%;font-size:16px;padding:12px;border:none;border-radius:8px;box-sizing:border-box}
    button{width:100%;margin-top:16px;padding:13px;border:none;border-radius:8px;background:#6EC5E8;color:#0B1830;font-size:15px;font-weight:600;cursor:pointer}.e{color:#F4C0C0;font-size:13px;margin-top:10px}</style></head>
    <body><div class="c"><h1>Documentos da sua viagem</h1><p>Para sua segurança, confirme a data de nascimento do titular da viagem.</p>
    <form method="post"><label>Data de nascimento</label><input type="date" name="nasc" required autofocus><?php if (!empty($erro)) echo '<div class="e">' . h($erro) . '</div>'; ?><button type="submit">Acessar</button></form></div></body></html><?php
    exit;
}

// download de um documento (já passou pela trava)
if (!empty($_GET['dl'])) {
    $d = um('SELECT * FROM viagem_docs WHERE id = ? AND lead_id = ?', [(int)$_GET['dl'], $portal['lead_id']]);
    if (!$d) { http_response_code(404); exit('Documento não encontrado.'); }
    $arq = pasta_docs() . '/' . $d['arquivo'];
    if (!is_file($arq)) { http_response_code(404); exit('Arquivo ausente.'); }
    $mime = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','html'=>'text/html','htm'=>'text/html'][$d['ext']] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\- ]/', '', $d['nome']) . '.' . $d['ext'] . '"');
    if (in_array($d['ext'], ['html','htm'], true)) header('Content-Security-Policy: sandbox allow-popups');
    header('Cache-Control: private, max-age=120');
    readfile($arq); exit;
}
$docs = todos('SELECT * FROM viagem_docs WHERE lead_id = ? ORDER BY inicio IS NULL, inicio, hora, id', [$portal['lead_id']]);
$icone = fn($ext) => ['pdf' => 'PDF', 'jpg' => 'IMG', 'jpeg' => 'IMG', 'png' => 'IMG', 'html' => 'DOC', 'htm' => 'DOC'][$ext] ?? 'DOC';
$venda = um('SELECT * FROM vendas WHERE lead_id = ?', [$portal['lead_id']]);
$itens = $venda ? todos("SELECT * FROM venda_itens WHERE venda_id = ? AND servico <> 'Markup do pacote' ORDER BY COALESCE(hora_apresentacao,'~'), id", [$venda['id']]) : [];
$cat = function ($s) {
    $s = mb_strtolower((string)$s);
    if (strpos($s, 'aére') !== false || strpos($s, 'aere') !== false || strpos($s, 'voo') !== false) return 'voos';
    if (strpos($s, 'hosped') !== false) return 'hospedagem';
    if (strpos($s, 'trasl') !== false || strpos($s, 'transf') !== false) return 'transfer';
    if (strpos($s, 'ingress') !== false || strpos($s, 'passe') !== false) return 'passeios';
    return 'outros';
};
$voos = $hosp = $exper = [];
foreach ($itens as $i) {
    $c = $cat($i['servico']);
    if ($c === 'voos' && ($i['voo_numero'] || $i['voo_partida'] || $i['reserva_codigo'])) $voos[] = $i;
    elseif ($c === 'hospedagem' && ($i['reserva_codigo'] || $i['hora_apresentacao'] || $i['endereco'])) $hosp[] = $i;
    elseif (in_array($c, ['transfer', 'passeios'], true) && ($i['hora_apresentacao'] || $i['fornecedor_contato'])) $exper[] = $i;
}
$tem_guia = $voos || $hosp || $exper;
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: private, no-store');
?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex"><meta name="theme-color" content="#1B2A4A">
<title>Sua viagem<?= $lead['destino'] ? ' · ' . h($lead['destino']) : '' ?></title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:'Segoe UI',system-ui,Arial,sans-serif;background:#F3F4F6;color:#2A2E35;padding-bottom:env(safe-area-inset-bottom,0)}
.topo{background:#1B2A4A;color:#fff;padding:calc(20px + env(safe-area-inset-top,0)) 20px 20px}
.topo small{color:#8FA9D3;font-size:11px;letter-spacing:.08em;text-transform:uppercase}
.topo h1{font-family:Georgia,serif;font-weight:400;font-size:23px;margin:4px 0 2px}.topo .sub{color:#8FD3F4;font-size:13px}
.wrap{max-width:620px;margin:0 auto;padding:16px}
.secao{font-family:'Courier New',monospace;font-size:11px;color:#7A8699;text-transform:uppercase;letter-spacing:.12em;margin:22px 0 8px}
.cartao{background:#fff;border:1px solid #E2E5EA;border-radius:10px;padding:11px 13px;margin-bottom:8px}
.cartao .lin1{display:flex;justify-content:space-between;align-items:baseline;gap:8px}
.cartao b{color:#1B2A4A;font-size:14px}
.cartao .cod{font-size:12px;color:#5F6775;white-space:nowrap}
.cartao .det{font-size:12.5px;color:#5F6775;margin-top:3px;line-height:1.5}
.cartao .det b{font-size:12.5px}
.doc{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #E2E5EA;border-radius:10px;padding:12px 14px;margin-bottom:8px;text-decoration:none;color:inherit}
.doc .tp{font-size:10px;font-weight:700;color:#3D5A8A;background:#E8EDF5;border-radius:4px;padding:4px 6px;flex:none}
.doc .n{flex:1;min-width:0}.doc .n b{font-weight:600;font-size:14px;display:block}.doc .n span{font-size:12px;color:#6B7280}
.doc .dl{color:#3D5A8A;font-size:13px;flex:none}
.emerg{background:#1B2A4A;color:#fff;border-radius:12px;padding:14px 16px;margin:22px 0;display:flex;align-items:flex-start;gap:12px}
.emerg .ic{font-size:22px}.emerg a{color:#8FD3F4;text-decoration:none;font-weight:600}
.emerg .t{font-size:13px}.emerg .d{color:#8FA9D3;font-size:12px;margin-top:2px;line-height:1.5}
.rod{text-align:center;color:#6B7280;font-size:12px;padding:10px 0 30px}
</style></head><body>
<div class="topo"><div style="max-width:620px;margin:0 auto"><small>Vanessa Viagens e Turismo</small><h1>Sua viagem<?= $lead['destino'] ? ' · ' . h($lead['destino']) : '' ?></h1><?php if ($lead['data_embarque']): ?><div class="sub"><?= h($lead['cliente']) ?> · <?= data_br($lead['data_embarque']) ?><?= $lead['data_retorno'] ? ' a ' . data_br($lead['data_retorno']) : '' ?></div><?php endif; ?></div></div>
<div class="wrap">
<?php if (!$tem_guia && !$docs): ?><p style="color:#6B7280;text-align:center;padding:30px 0">As informações da sua viagem serão disponibilizadas em breve.</p><?php endif; ?>
<?php if ($voos): ?><div class="secao">Voos</div>
  <?php foreach ($voos as $i): ?><div class="cartao"><div class="lin1"><b><?= $i['voo_origem'] || $i['voo_destino'] ? h(trim($i['voo_origem'] . ' → ' . $i['voo_destino'])) : h($i['fornecedor'] ?: 'Voo') ?></b><?php if ($i['voo_numero']): ?><span class="cod"><?= h($i['voo_numero']) ?></span><?php endif; ?></div><div class="det"><?= h($i['fornecedor']) ?><?= $i['voo_partida'] || $i['voo_chegada'] ? ' · ' . h(trim($i['voo_partida'] . ($i['voo_chegada'] ? ' → ' . $i['voo_chegada'] : ''))) : '' ?><?= $i['reserva_codigo'] ? ' · Localizador <b>' . h($i['reserva_codigo']) . '</b>' : '' ?><?= $i['bagagem'] ? '<br>Bagagem: ' . h($i['bagagem']) : '' ?></div></div><?php endforeach; ?>
<?php endif; ?>
<?php if ($hosp): ?><div class="secao">Hospedagem</div>
  <?php foreach ($hosp as $i): ?><div class="cartao"><div class="lin1"><b><?= h($i['fornecedor'] ?: $i['detalhes'] ?: 'Hotel') ?></b><?php if ($i['reserva_codigo']): ?><span class="cod">Reserva <?= h($i['reserva_codigo']) ?></span><?php endif; ?></div><div class="det"><?= $i['hora_apresentacao'] ? 'Check-in ' . h($i['hora_apresentacao']) : '' ?><?= $i['regime'] ? ' · ' . h($i['regime']) : '' ?><?= $i['endereco'] ? '<br>Endereço: ' . h($i['endereco']) : '' ?><?= $i['fornecedor_contato'] ? '<br>Telefone: ' . h($i['fornecedor_contato']) : '' ?></div></div><?php endforeach; ?>
<?php endif; ?>
<?php if ($exper): ?><div class="secao">Passeios e traslados</div>
  <?php foreach ($exper as $i): ?><div class="cartao"><div class="lin1"><b><?= h($i['detalhes'] ?: $i['servico']) ?></b><?php if ($i['hora_apresentacao']): ?><span class="cod"><?= h($i['hora_apresentacao']) ?></span><?php endif; ?></div><div class="det"><?= $i['fornecedor'] ? h($i['fornecedor']) : '' ?><?= $i['fornecedor_contato'] ? ' · ' . h($i['fornecedor_contato']) : '' ?><?= $i['reserva_codigo'] ? ' · Voucher ' . h($i['reserva_codigo']) : '' ?></div></div><?php endforeach; ?>
<?php endif; ?>
<?php if ($docs): ?><div class="secao">Documentos</div>
  <?php foreach ($docs as $d): ?><a class="doc" href="?t=<?= h($t) ?>&dl=<?= $d['id'] ?>" target="_blank" rel="noopener"><span class="tp"><?= $icone($d['ext']) ?></span><span class="n"><b><?= h($d['nome']) ?></b><span><?= h(CATEGORIAS_DOC[$d['categoria']] ?? '') ?><?= $d['inicio'] ? ' · ' . data_br($d['inicio']) : '' ?></span></span><span class="dl">baixar ↓</span></a><?php endforeach; ?>
<?php endif; ?>
<?php if ($venda && ($venda['seguradora'] || $venda['seguro_apolice'])): ?><div class="secao">Seguro</div>
  <div class="cartao"><div class="lin1"><b><?= h($venda['seguradora'] ?: 'Seguro viagem') ?></b><?php if ($venda['seguro_apolice']): ?><span class="cod">Apólice <?= h($venda['seguro_apolice']) ?></span><?php endif; ?></div><?php if ($venda['seguro_plano'] || $venda['seguro_central']): ?><div class="det"><?= h($venda['seguro_plano']) ?><?= $venda['seguro_central'] ? ' · Central 24h ' . h($venda['seguro_central']) : '' ?></div><?php endif; ?></div>
<?php endif; ?>
<div class="emerg"><div class="ic">🛟</div><div><div class="t">Precisa de ajuda na viagem?</div><div class="d"><a href="https://wa.me/5511975439074">Vanessa · +55 11 97543-9074</a><?= $venda && $venda['plantao_emergencia'] && so_digitos($venda['plantao_emergencia']) !== so_digitos('5511975439074') ? '<br>Plantão: ' . h($venda['plantao_emergencia']) : '' ?></div></div></div>
</div>
<div class="rod">Vanessa Viagens e Turismo</div>
</body></html>
