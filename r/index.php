<?php
// Link privado do roteiro para o cliente: vanessaviagenseturismo.com.br/r/?t=...
// Mostra o roteiro (HTML, em iframe isolado) e, fora dele, a barra "Aprovar esta viagem".
require dirname(__DIR__) . '/crm/inc/app.php';
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$t = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
$o = strlen($t) === 24 ? um('SELECT o.id opcao_id, o.nome opcao_nome, o.escolhida, o.aprovada_cliente_em, p.id pid, p.versao, p.status, p.titulo, p.lead_id, l.cliente_id, l.etapa, c.nome cliente FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id JOIN leads l ON l.id = p.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE o.roteiro_token = ?', [$t]) : null;
$arq = $o ? pasta_roteiros() . '/' . $t . '.html' : '';

function pagina_erro($msg) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vanessa Viagens e Turismo</title><body style="font-family:Georgia,serif;background:#1B2A4A;color:#fff;display:grid;place-items:center;min-height:90vh;text-align:center;padding:20px"><div><h1 style="font-weight:400">' . $msg . '</h1><p style="font-family:system-ui;color:#B9C2CF">Fale com a Vanessa pelo <a style="color:#fff" href="https://wa.me/5511975439074">WhatsApp</a>.</p></div></body>';
    exit;
}
if (!$o || !is_file($arq)) pagina_erro('Este roteiro não está mais disponível.');

$preview = !empty($_GET['preview']);
$ehBot = preg_match('/bot|crawl|spider|preview|facebookexternalhit|whatsapp|telegram|slack/i', $_SERVER['HTTP_USER_AGENT'] ?? '');

// ---------- POST: cliente aprova a viagem ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['acao'] ?? '', ['aprovar', 'ajustar'], true) && !$preview) {
    $acaoCli = $_POST['acao'];
    $nome = trim(mb_substr((string)($_POST['nome'] ?? ''), 0, 120));
    $obs = trim(mb_substr((string)($_POST['obs'] ?? ''), 0, 1500));
    $pode = in_array($o['etapa'], ['novo', 'conversa', 'roteiro', 'proposta', 'ajustes'], true) && $o['status'] === 'enviada';
    if (mb_strlen($nome) < 3) { $erro_aprovar = 'Por favor, escreva seu nome para confirmar.'; $abrir = $acaoCli; }
    elseif ($acaoCli === 'ajustar' && $obs === '') { $erro_aprovar = 'Conte o que você gostaria de ajustar.'; $abrir = 'ajustar'; }
    elseif (!$pode) { $ja = true; }
    elseif ($acaoCli === 'aprovar') {
        $ip = ip_hash();
        db()->beginTransaction();
        q('UPDATE proposta_opcoes SET escolhida = (id = ?), aprovada_cliente_em = ?, aprovada_cliente_nome = ?, aprovada_cliente_ip = ?, aprovada_cliente_obs = ? WHERE id = ? AND proposta_id = ?',
          [$o['opcao_id'], agora(), $nome, $ip, $obs ?: null, $o['opcao_id'], $o['pid']]);
        q("UPDATE propostas SET status = 'aprovada', decidida_em = ?, atualizado_em = ? WHERE id = ?", [agora(), agora(), $o['pid']]);
        q("UPDATE propostas SET status = 'substituida' WHERE lead_id = ? AND id <> ? AND status = 'enviada'", [$o['lead_id'], $o['pid']]);
        q("UPDATE leads SET etapa = 'aprovada', etapa_desde = ?, motivo_perda = NULL, atualizado_em = ? WHERE id = ?", [agora(), agora(), $o['lead_id']]);
        q('INSERT INTO atividades (lead_id, cliente_id, tipo, texto, criado_em) VALUES (?,?,?,?,?)',
          [$o['lead_id'], $o['cliente_id'], 'proposta', 'O CLIENTE APROVOU a proposta v' . (int)$o['versao'] . ' · ' . $o['opcao_nome'] . ' pelo link do roteiro (nome: ' . $nome . ').' . ($obs ? ' Observação: ' . $obs : ''), agora()]);
        q('INSERT INTO tarefas (lead_id, cliente_id, titulo, vence_em, criado_em) VALUES (?,?,?,?,?)',
          [$o['lead_id'], $o['cliente_id'], 'Cliente aprovou pelo site: receber sinal e confirmar para fechar', hoje(), agora()]);
        db()->commit();
        $codigo = valor('SELECT codigo FROM leads WHERE id = ?', [$o['lead_id']]);
        $assunto = '=?UTF-8?B?' . base64_encode('✅ Cliente aprovou a proposta ' . $codigo) . '?=';
        $obsHtml = $obs ? '<p style="background:#F0F6F2;border-left:3px solid #14602E;padding:10px 12px;margin:10px 0"><b>Observação do cliente:</b><br>' . nl2br(h($obs)) . '</p>' : '';
        $corpo = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;color:#2A2E35"><div style="background:#14602E;color:#fff;padding:16px 20px;border-radius:8px 8px 0 0;font-family:Georgia,serif;font-size:20px">Proposta aprovada pelo cliente</div><div style="border:1px solid #E2E5EA;border-top:none;padding:18px 20px;border-radius:0 0 8px 8px"><p><b>' . h($o['cliente']) . '</b> aprovou a proposta <b>' . h($codigo) . '-v' . (int)$o['versao'] . '</b> (' . h($o['opcao_nome']) . ') pelo link do roteiro.</p><p>Nome informado: ' . h($nome) . '<br>Data: ' . data_br(agora(), true) . '</p>' . $obsHtml . '<p>O lead foi para <b>Aprovada pelo Cliente/Aguardando fechamento</b>. Receba o sinal e confirme o fechamento no CRM.</p><p><a href="https://vanessaviagenseturismo.com.br/crm/?p=lead&id=' . (int)$o['lead_id'] . '" style="background:#3D5A8A;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Abrir o lead</a></p></div></div>';
        @mail(EMAIL_PROPOSTAS, $assunto, $corpo, "From: VV360 <" . EMAIL_REMETENTE . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8");
        $aprovado_agora = true;
    } else { // ajustar: pedido de alterações — mantém a porta aberta, lead vai para "Ajustes"
        db()->beginTransaction();
        q("UPDATE leads SET etapa = 'ajustes', etapa_desde = ?, atualizado_em = ? WHERE id = ?", [agora(), agora(), $o['lead_id']]);
        q('INSERT INTO atividades (lead_id, cliente_id, tipo, texto, criado_em) VALUES (?,?,?,?,?)',
          [$o['lead_id'], $o['cliente_id'], 'proposta', 'O CLIENTE PEDIU AJUSTES na proposta v' . (int)$o['versao'] . ' · ' . $o['opcao_nome'] . ' pelo link (nome: ' . $nome . '). Pedido: ' . $obs, agora()]);
        q('INSERT INTO tarefas (lead_id, cliente_id, titulo, vence_em, criado_em) VALUES (?,?,?,?,?)',
          [$o['lead_id'], $o['cliente_id'], 'Cliente pediu ajustes pelo site: revisar e enviar nova versão', hoje(), agora()]);
        db()->commit();
        $codigo = valor('SELECT codigo FROM leads WHERE id = ?', [$o['lead_id']]);
        $assunto = '=?UTF-8?B?' . base64_encode('✏️ Cliente pediu ajustes na proposta ' . $codigo) . '?=';
        $corpo = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;color:#2A2E35"><div style="background:#8A5A00;color:#fff;padding:16px 20px;border-radius:8px 8px 0 0;font-family:Georgia,serif;font-size:20px">Pedido de ajustes do cliente</div><div style="border:1px solid #E2E5EA;border-top:none;padding:18px 20px;border-radius:0 0 8px 8px"><p><b>' . h($o['cliente']) . '</b> pediu ajustes na proposta <b>' . h($codigo) . '-v' . (int)$o['versao'] . '</b> (' . h($o['opcao_nome']) . ') pelo link do roteiro.</p><p style="background:#FBF4E7;border-left:3px solid #8A5A00;padding:10px 12px;margin:10px 0"><b>O que o cliente gostaria de ajustar:</b><br>' . nl2br(h($obs)) . '</p><p>Nome informado: ' . h($nome) . '<br>Data: ' . data_br(agora(), true) . '</p><p>O lead foi para <b>Ajustes</b>. Revise e envie uma nova versão.</p><p><a href="https://vanessaviagenseturismo.com.br/crm/?p=lead&id=' . (int)$o['lead_id'] . '" style="background:#3D5A8A;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Abrir o lead</a></p></div></div>';
        @mail(EMAIL_PROPOSTAS, $assunto, $corpo, "From: VV360 <" . EMAIL_REMETENTE . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8");
        $ajuste_agora = true;
    }
}

// registra a abertura (como antes)
if (!$preview && !$ehBot && empty($aprovado_agora)) {
    $ip = ip_hash();
    $recente = valor('SELECT MAX(acessado_em) FROM roteiro_acessos WHERE opcao_id = ?', [$o['opcao_id']]);
    if (!valor('SELECT 1 FROM roteiro_acessos WHERE opcao_id = ? AND ip_hash = ? AND acessado_em >= ?', [$o['opcao_id'], $ip, date('Y-m-d H:i:s', strtotime('-30 minutes'))])) {
        q('INSERT INTO roteiro_acessos (proposta_id, opcao_id, acessado_em, ip_hash) VALUES (?,?,?,?)', [$o['pid'], $o['opcao_id'], agora(), $ip]);
        if (!$recente || $recente < date('Y-m-d H:i:s', strtotime('-6 hours')))
            q('INSERT INTO atividades (lead_id, cliente_id, tipo, texto, criado_em) VALUES (?,?,?,?,?)', [$o['lead_id'], $o['cliente_id'], 'nota', 'O cliente abriu o roteiro da proposta v' . (int)$o['versao'] . '.', agora()]);
    }
}

// estado atual da aprovação (para decidir o que mostrar na barra)
// três estados possíveis da barra:
//  'aprovar'      → proposta enviada e lead em negociação: pode aprovar
//  'aprovada'     → já aprovada (por cliente ou pela equipe), lead em "aprovada"/"fechado"/"viagem"/"pos"
//  'indisponivel' → proposta recusada/substituída, ou lead perdido: não oferece aprovar nem diz "já aprovou"
$em_negociacao = in_array($o['etapa'], ['novo', 'conversa', 'roteiro', 'proposta', 'ajustes'], true);
$ganho = in_array($o['etapa'], ['aprovada', 'fechado', 'viagem', 'pos'], true);
if ($o['status'] === 'enviada' && $em_negociacao) $estado = 'aprovar';
elseif (!empty($o['aprovada_cliente_em']) || ($ganho && $o['escolhida'])) $estado = 'aprovada';
elseif ($ganho) $estado = 'aprovada';
else $estado = 'indisponivel';
$mostrarBarra = !$preview && !$ehBot;

// caminho absoluto desta própria página (ex.: /r/ ou /r/index.php), para o iframe não resolver errado
$base = strtok($_SERVER['REQUEST_URI'] ?? '/r/', '?');
$iframe = $base . '?t=' . rawurlencode($t) . '&doc=1'; // a própria página serve o HTML cru quando doc=1
if (!empty($_GET['doc'])) {
    header('Content-Security-Policy: sandbox allow-popups allow-popups-to-escape-sandbox');
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, max-age=300');
    readfile($arq);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
$primeiro = explode(' ', trim($o['cliente']))[0];
?><!DOCTYPE html><html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= h($o['titulo'] ?: $o['opcao_nome'] ?: 'Sua viagem') ?> · Vanessa Viagens e Turismo</title>
<style>
*{box-sizing:border-box} html,body{margin:0;height:100%}
body{display:flex;flex-direction:column;background:#1B2A4A;font-family:'Segoe UI',system-ui,Arial,sans-serif}
iframe{flex:1;width:100%;border:0;background:#fff}
.barra{flex:none;background:#fff;border-top:1px solid #E2E5EA;padding:14px 18px calc(14px + env(safe-area-inset-bottom,0px));box-shadow:0 -6px 20px rgba(0,0,0,.12)}
.in{max-width:760px;margin:0 auto;display:flex;align-items:center;gap:14px;flex-wrap:wrap;justify-content:space-between}
.msg{font-size:15px;color:#2A2E35} .msg b{color:#1B2A4A}
.btn{font:inherit;font-size:15px;font-weight:700;border:none;border-radius:8px;padding:12px 22px;cursor:pointer;background:#14602E;color:#fff}
.btn:hover{background:#0F4A24}
.aprovado{color:#14602E;font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px}
.modal{position:fixed;inset:0;background:rgba(15,23,42,.6);display:none;place-items:center;padding:20px;z-index:10}
.modal.on{display:grid}
.cartao{background:#fff;border-radius:14px;padding:26px;max-width:420px;width:100%;font-family:'Segoe UI',Arial,sans-serif}
.cartao h2{font-family:Georgia,serif;color:#1B2A4A;margin:0 0 6px;font-size:23px}
.cartao p{color:#5F6775;font-size:14.5px;margin:0 0 16px}
.cartao label{display:block;font-size:13px;font-weight:600;margin:0 0 4px;color:#2A2E35}
.cartao input{width:100%;font:inherit;font-size:16px;padding:11px 12px;border:1.5px solid #E2E5EA;border-radius:8px}
.cartao input:focus,.cartao textarea:focus{outline:none;border-color:#3D5A8A}
.cartao textarea{width:100%;font:inherit;font-size:15px;padding:11px 12px;border:1.5px solid #E2E5EA;border-radius:8px;min-height:84px;resize:vertical;margin-top:4px}
.btn-ajustar{background:#fff;color:#8A5A00;border:1.5px solid #E7D4AE}
.erro{color:#9B1C1C;font-size:13.5px;margin:8px 0 0}
.linha{display:flex;gap:10px;margin-top:18px}
.btn-sec{background:#fff;color:#3D5A8A;border:1.5px solid #E2E5EA}
.ok{background:#fff;border-radius:14px;padding:30px;max-width:440px;text-align:center;font-family:'Segoe UI',Arial,sans-serif}
.ok .sel{width:64px;height:64px;border-radius:50%;background:#E7F5EC;color:#14602E;display:grid;place-items:center;font-size:32px;margin:0 auto 14px}
.ok h2{font-family:Georgia,serif;color:#1B2A4A;margin:0 0 8px}
.ok p{color:#5F6775;margin:0 0 6px}
</style></head><body>
<?php if (!empty($aprovado_agora)): ?>
  <div style="flex:1;display:grid;place-items:center;padding:24px"><div class="ok">
    <div class="sel">✓</div><h2>Que alegria, <?= h($primeiro) ?>!</h2>
    <p>Recebemos a sua aprovação desta viagem.</p>
    <p>A Vanessa já foi avisada e vai falar com você para combinar os próximos passos.</p>
    <p style="margin-top:14px"><a href="https://wa.me/5511975439074?text=<?= rawurlencode('Olá, Vanessa! Acabei de aprovar a proposta da minha viagem pelo site.') ?>" style="background:#1E8E4E;color:#fff;padding:11px 18px;border-radius:8px;text-decoration:none;font-weight:600">Falar no WhatsApp</a></p>
  </div></div>
<?php elseif (!empty($ajuste_agora)): ?>
  <div style="flex:1;display:grid;place-items:center;padding:24px"><div class="ok">
    <div class="sel" style="background:#FBF4E7;color:#8A5A00">✎</div><h2>Obrigada, <?= h($primeiro) ?>!</h2>
    <p>Recebemos o seu pedido de ajustes.</p>
    <p>A Vanessa vai revisar o roteiro com o que você pediu e logo te envia uma nova versão.</p>
    <p style="margin-top:14px"><a href="https://wa.me/5511975439074?text=<?= rawurlencode('Olá, Vanessa! Pedi alguns ajustes na proposta da minha viagem pelo site.') ?>" style="background:#1E8E4E;color:#fff;padding:11px 18px;border-radius:8px;text-decoration:none;font-weight:600">Falar no WhatsApp</a></p>
  </div></div>
<?php else: ?>
  <iframe src="<?= h($iframe) ?>" title="Roteiro da viagem"></iframe>
  <?php if ($mostrarBarra): ?>
  <div class="barra"><div class="in">
    <?php if ($estado === 'aprovar'): ?>
      <span class="msg">Gostou? <b>Aprove esta viagem</b> e a Vanessa cuida do resto.</span>
      <span style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-ajustar" onclick="abrir('a')">Quero ajustar</button>
        <button class="btn" onclick="abrir('m')">Aprovar esta viagem</button>
      </span>
    <?php elseif ($estado === 'aprovada'): ?>
      <span class="aprovado">✓ Esta viagem já foi aprovada. A Vanessa está cuidando dos próximos passos.</span>
      <a class="btn" style="background:#1E8E4E" href="https://wa.me/5511975439074">Falar com a Vanessa</a>
    <?php else: ?>
      <span class="msg">Esta proposta não está mais ativa. Fale com a Vanessa para a versão atualizada.</span>
      <a class="btn" style="background:#1E8E4E" href="https://wa.me/5511975439074">Falar com a Vanessa</a>
    <?php endif; ?>
  </div></div>
  <div class="modal" id="m"><div class="cartao">
    <h2>Aprovar esta viagem</h2>
    <p>Confirme que deseja seguir com esta proposta. A Vanessa entrará em contato para combinar o pagamento e as reservas.</p>
    <form method="post">
      <input type="hidden" name="acao" value="aprovar">
      <label>Seu nome</label>
      <input name="nome" value="<?= h($o['cliente']) ?>" required autocomplete="name">
      <label style="margin-top:12px">Alguma observação? <span style="font-weight:400;color:#8A93A3">(opcional)</span></label>
      <textarea name="obs" placeholder="Ex.: podemos adiantar o voo da volta?"><?= $abrir==='aprovar' ? h($_POST['obs'] ?? '') : '' ?></textarea>
      <?php if (!empty($erro_aprovar) && ($abrir ?? '')==='aprovar'): ?><p class="erro"><?= h($erro_aprovar) ?></p><?php endif; ?>
      <div class="linha">
        <button type="button" class="btn btn-sec" style="flex:1" onclick="fechar('m')">Voltar</button>
        <button type="submit" class="btn" style="flex:1">Confirmar aprovação</button>
      </div>
    </form>
  </div></div>
  <div class="modal" id="a"><div class="cartao">
    <h2>Quero ajustar</h2>
    <p>Sem problema! Conte o que você gostaria de mudar e a Vanessa te envia uma nova versão do roteiro.</p>
    <form method="post">
      <input type="hidden" name="acao" value="ajustar">
      <label>Seu nome</label>
      <input name="nome" value="<?= h($o['cliente']) ?>" required autocomplete="name">
      <label style="margin-top:12px">O que você gostaria de ajustar?</label>
      <textarea name="obs" required placeholder="Ex.: ficou acima do meu orçamento; gostaria de trocar o hotel; incluir mais um dia em..."><?= $abrir==='ajustar' ? h($_POST['obs'] ?? '') : '' ?></textarea>
      <?php if (!empty($erro_aprovar) && ($abrir ?? '')==='ajustar'): ?><p class="erro"><?= h($erro_aprovar) ?></p><?php endif; ?>
      <div class="linha">
        <button type="button" class="btn btn-sec" style="flex:1" onclick="fechar('a')">Voltar</button>
        <button type="submit" class="btn btn-ajustar" style="flex:1;background:#8A5A00;color:#fff;border:none">Enviar pedido</button>
      </div>
    </form>
  </div></div>
  <script>
    function abrir(id){document.getElementById(id).classList.add('on');}
    function fechar(id){document.getElementById(id).classList.remove('on');}
    <?php if (!empty($erro_aprovar) && !empty($abrir)): ?>abrir(<?= json_encode($abrir==='ajustar'?'a':'m') ?>);<?php endif; ?>
  </script>
  <?php endif; ?>
<?php endif; ?>
</body></html>
