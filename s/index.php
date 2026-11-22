<?php
// Pesquisa de satisfação: vanessaviagenseturismo.com.br/s/?t=...
require dirname(__DIR__) . '/crm/inc/app.php';
require dirname(__DIR__) . '/crm/inc/viagem.php';
header('X-Robots-Tag: noindex, nofollow'); header('Referrer-Policy: no-referrer');

$t = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
$portal = strlen($t) === 24 ? um("SELECT * FROM portais WHERE token = ? AND tipo = 'pesquisa'", [$t]) : null;

function telas($titulo, $msg) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vanessa Viagens e Turismo</title><body style="font-family:Georgia,serif;background:#1B2A4A;color:#fff;display:grid;place-items:center;min-height:90vh;text-align:center;padding:24px"><div><h1 style="font-weight:400;font-size:22px">' . $titulo . '</h1><p style="font-family:system-ui;color:#B9C2CF">' . $msg . '</p></div></body>';
    exit;
}
if (!$portal) { http_response_code(404); telas('Link não encontrado', 'Confira o endereço com a Vanessa.'); }
if ($portal['revogado'] || ($portal['expira_em'] && $portal['expira_em'] < hoje())) { http_response_code(410); telas('Pesquisa encerrada', 'Obrigada pelo interesse!'); }

$lead = um('SELECT l.*, c.nome cliente, c.nascimento, c.id cid FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.id = ?', [$portal['lead_id']]);
$pesq = um('SELECT * FROM pesquisas WHERE lead_id = ? ORDER BY id DESC LIMIT 1', [$portal['lead_id']]);
if ($pesq && $pesq['respondida_em']) { telas('Obrigada!', 'Você já respondeu a esta pesquisa. Agradecemos muito.'); }

// serviços a avaliar: dos vouchers; senão, dos itens da venda
$servicos = [];
foreach (todos("SELECT DISTINCT nome, categoria FROM viagem_docs WHERE lead_id = ? AND categoria NOT IN ('roteiro','outros','seguro')", [$portal['lead_id']]) as $d)
    $servicos[] = ['servico' => $d['nome'], 'fornecedor' => $d['nome']];
if (!$servicos) foreach (todos("SELECT i.fornecedor, i.servico FROM venda_itens i JOIN vendas v ON v.id = i.venda_id WHERE v.lead_id = ?", [$portal['lead_id']]) as $i)
    $servicos[] = ['servico' => $i['fornecedor'], 'fornecedor' => $i['fornecedor']];

$exige_trava = !empty($lead['nascimento']);
$liberado = !$exige_trava;
session_name('vvportal'); @session_start();
$chave = 'pesq_ok_' . $portal['id'];
if (!$exige_trava || !empty($_SESSION[$chave])) $liberado = true;

// trava
if (!$liberado && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nasc'])) {
    if (($_POST['nasc'] ?? '') === $lead['nascimento']) { $_SESSION[$chave] = true; $liberado = true; }
    else { sleep(1); $erro = 'Data não confere.'; }
}

// envio da resposta
if ($liberado && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'responder') {
    $geral = max(1, min(5, (int)($_POST['geral'] ?? 0)));
    $rec = in_array($_POST['recomenda'] ?? '', ['Com certeza', 'Talvez', 'Não'], true) ? $_POST['recomenda'] : null;
    $com = trim(mb_substr((string)($_POST['comentario'] ?? ''), 0, 2000));
    $aut = !empty($_POST['autoriza']) && $com !== '';
    db()->beginTransaction();
    // próxima viagem
    $quer = ($_POST['prox'] ?? '') === 'sim';
    $pd = $quer ? trim(mb_substr((string)($_POST['prox_destino'] ?? ''), 0, 200)) : '';
    $pq = $quer ? trim(mb_substr((string)($_POST['prox_quando'] ?? ''), 0, 100)) : '';
    $pw = $quer ? trim(mb_substr((string)($_POST['prox_quem'] ?? ''), 0, 120)) : '';
    $pc = $quer && preg_match('/^\d{4}-\d{2}$/', $_POST['prox_contato'] ?? '') ? $_POST['prox_contato'] : '';
    $po = $quer ? trim(mb_substr((string)($_POST['prox_obs'] ?? ''), 0, 1000)) : '';
    q('INSERT INTO pesquisas (lead_id, cliente_id, nota_geral, recomenda, comentario, autoriza_publicar, prox_destino, prox_quando, prox_quem, prox_contato, prox_obs, respondida_em, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$portal['lead_id'], $lead['cid'], $geral ?: null, $rec, $com, $aut ? 1 : 0, $pd ?: null, $pq ?: null, $pw ?: null, $pc ?: null, $po ?: null, agora(), agora()]);
    $pid = (int)db()->lastInsertId();
    // cria lead recorrente só se o cliente quis e não houver lead aberto recente do mesmo cliente
    if ($quer) {
        $jaAberto = valor("SELECT id FROM leads WHERE cliente_id = ? AND etapa IN ('novo','conversa','roteiro','proposta','ajustes','aprovada') AND id <> ? ORDER BY criado_em DESC LIMIT 1", [$lead['cid'], $portal['lead_id']]);
        if (!$jaAberto) {
            $det = array_filter(['Pedido surgido na pesquisa de pós-viagem.', $pd ? 'Destino: ' . $pd : '', $pq ? 'Quando: ' . $pq : '', $pw ? 'Quem viaja: ' . $pw : '', $pc ? 'Contatar a partir de: ' . date('m/Y', strtotime($pc . '-01')) : '', $po ? 'Observações: ' . $po : '']);
            q('INSERT INTO leads (cliente_id, destino, periodo, detalhes, origem, etapa, etapa_desde, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?,?,?)',
              [$lead['cid'], $pd, $pq, implode("\n", $det), 'Cliente recorrente', 'novo', agora(), agora(), agora()]);
            $novo = (int)db()->lastInsertId();
            q('UPDATE pesquisas SET prox_lead_id = ? WHERE id = ?', [$novo, $pid]);
            // tarefa datada: preferência do cliente > estimativa pelo período > sem urgência
            if ($pc) $venc = date('Y-m-01', strtotime($pc . '-01'));
            else $venc = hoje();
            q('INSERT INTO atividades (lead_id, cliente_id, tipo, texto, criado_em) VALUES (?,?,?,?,?)', [$novo, $lead['cid'], 'nota', 'Lead criado a partir da pesquisa de pós-viagem (cliente demonstrou interesse na próxima viagem).', agora()]);
            q('INSERT INTO tarefas (lead_id, cliente_id, titulo, vence_em, criado_em) VALUES (?,?,?,?,?)', [$novo, $lead['cid'], 'Retomar contato sobre a próxima viagem', $venc, agora()]);
        }
    }
    foreach ($servicos as $i => $s) {
        $n = max(0, min(5, (int)($_POST['s' . $i] ?? 0)));
        if ($n) q('INSERT INTO pesquisa_itens (pesquisa_id, servico, fornecedor, nota) VALUES (?,?,?,?)', [$pid, $s['servico'], $s['fornecedor'], $n]);
    }
    // elogio autorizado vira mensagem publicável (fluxo do menu Mensagens)
    if ($aut && $geral >= 4) {
        q('INSERT INTO mensagens (tipo, nome, contato, email, viagem, texto, autoriza_publicar, status, cliente_id, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?)',
          ['elogio', $lead['cliente'], '', '', $lead['destino'], $com, 1, 'nova', $lead['cid'], agora()]);
    }
    q('INSERT INTO atividades (lead_id, cliente_id, tipo, texto, criado_em) VALUES (?,?,?,?,?)',
      [$portal['lead_id'], $lead['cid'], 'nota', 'Cliente respondeu a pesquisa de satisfação (nota geral ' . $geral . '/5).', agora()]);
    db()->commit();
    telas('Obrigada, ' . h(explode(' ', trim($lead['cliente']))[0]) . '!', 'Sua opinião nos ajuda a cuidar ainda melhor de você. 💙');
}

header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: private, no-store');
?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex"><meta name="theme-color" content="#1B2A4A">
<title>Como foi sua viagem?</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:'Segoe UI',system-ui,Arial,sans-serif;background:#F3F4F6;color:#2A2E35;padding-bottom:env(safe-area-inset-bottom,0)}
.topo{background:#1B2A4A;color:#fff;padding:calc(20px + env(safe-area-inset-top,0)) 20px 20px}
.topo small{color:#8FA9D3;font-size:11px;letter-spacing:.08em;text-transform:uppercase}.topo h1{font-family:Georgia,serif;font-weight:400;font-size:22px;margin:4px 0 2px}.topo .sub{color:#8FD3F4;font-size:13px}
.wrap{max-width:560px;margin:0 auto;padding:16px}
.card{background:#fff;border:1px solid #E2E5EA;border-radius:12px;padding:14px 16px;margin-bottom:14px}
.secao{font-size:11px;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px}
.lin{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid #EEE}.lin:first-of-type{border-top:none}
.lin .nm{font-size:14px}
.estrelas{direction:rtl;display:inline-flex}
.estrelas input{display:none}
.estrelas label{font-size:26px;color:#D6DCE6;cursor:pointer;padding:0 1px}
.estrelas label:hover,.estrelas label:hover ~ label,.estrelas input:checked ~ label{color:#EF9F27}
.rec{display:flex;gap:8px;margin-top:4px}
.rec label{flex:1}.rec input{display:none}
.rec span{display:block;text-align:center;font-size:13px;padding:9px;border-radius:8px;border:1px solid #E2E5EA;color:#6B7280;cursor:pointer}
.rec input:checked + span{background:#E1F5EE;color:#0F6E56;border-color:#9FE1CB;font-weight:600}
textarea{width:100%;font:inherit;font-size:15px;padding:10px;border:1px solid #E2E5EA;border-radius:8px;min-height:80px;resize:vertical}
.aut{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;color:#5F6775}
.enviar{width:100%;padding:14px;border:none;border-radius:10px;background:#1E8E4E;color:#fff;font-size:15px;font-weight:600;cursor:pointer;margin-bottom:24px}
.trava{background:#243654;border-radius:14px;padding:30px;max-width:380px;margin:10vh auto;color:#fff}.trava h1{font-family:Georgia,serif;font-weight:400;font-size:21px;margin:0 0 6px}.trava p{color:#B9C2CF;font-size:14px}.trava input{width:100%;font-size:16px;padding:12px;border:none;border-radius:8px;box-sizing:border-box;margin-top:8px}.trava button{width:100%;margin-top:14px;padding:13px;border:none;border-radius:8px;background:#6EC5E8;color:#0B1830;font-weight:600;font-size:15px}.e{color:#F4C0C0;font-size:13px;margin-top:8px}
</style></head><body>
<?php if (!$liberado): ?>
  <div class="trava"><h1>Como foi sua viagem?</h1><p>Para confirmar que é você, informe a data de nascimento do titular.</p>
  <form method="post"><input type="date" name="nasc" required autofocus><?php if (!empty($erro)) echo '<div class="e">' . h($erro) . '</div>'; ?><button type="submit">Começar</button></form></div>
<?php else: ?>
<div class="topo"><div style="max-width:560px;margin:0 auto"><small>Vanessa Viagens e Turismo</small><h1>Como foi sua viagem<?= $lead['destino'] ? ' a ' . h($lead['destino']) : '' ?>?</h1><div class="sub">Sua opinião ajuda a cuidar ainda melhor de você</div></div></div>
<div class="wrap"><form method="post" id="f"><input type="hidden" name="acao" value="responder">
  <?php if ($servicos): ?><div class="card"><div class="secao">Os serviços da viagem</div>
    <?php foreach ($servicos as $i => $s): ?><div class="lin"><span class="nm"><?= h($s['servico']) ?></span>
      <span class="estrelas"><?php for ($v = 5; $v >= 1; $v--): ?><input type="radio" name="s<?= $i ?>" id="s<?= $i ?>_<?= $v ?>" value="<?= $v ?>"><label for="s<?= $i ?>_<?= $v ?>" title="<?= $v ?>">★</label><?php endfor; ?></span>
    </div><?php endforeach; ?>
  </div><?php endif; ?>
  <div class="card"><div class="lin"><span class="nm" style="font-weight:600">Nota geral da viagem</span>
    <span class="estrelas"><?php for ($v = 5; $v >= 1; $v--): ?><input type="radio" name="geral" id="g<?= $v ?>" value="<?= $v ?>" required><label for="g<?= $v ?>" title="<?= $v ?>">★</label><?php endfor; ?></span></div></div>
  <div class="card"><div class="secao">Recomendaria a Vanessa Viagens?</div>
    <div class="rec"><?php foreach (['Com certeza', 'Talvez', 'Não'] as $r): ?><label><input type="radio" name="recomenda" value="<?= $r ?>"><span><?= $r ?></span></label><?php endforeach; ?></div></div>
  <div class="card"><div class="secao">Quer deixar um recado?</div>
    <textarea name="comentario" placeholder="Conte como foi a experiência..."></textarea>
    <label class="aut"><input type="checkbox" name="autoriza" value="1"> Autorizo publicar meu comentário no site</label></div>
  <div class="card" style="border-color:#C9E6DA">
    <div class="secao" style="color:#0F6E56">Já está pensando na próxima viagem?</div>
    <div class="rec" style="margin-bottom:0">
      <label><input type="radio" name="prox" value="sim" id="prox-sim"><span>Sim, com vontade!</span></label>
      <label><input type="radio" name="prox" value="nao" id="prox-nao"><span>Ainda não</span></label>
    </div>
    <div id="bloco-prox" hidden style="margin-top:12px;border-left:2px solid #9FE1CB;padding-left:12px">
      <label style="font-size:13px;color:#2A2E35;display:block;margin-bottom:4px">Para onde você sonha ir?</label>
      <input name="prox_destino" placeholder="Ex.: Europa, Caribe, ainda não sei" style="width:100%;font-size:15px;padding:10px;border:1px solid #E2E5EA;border-radius:8px;box-sizing:border-box;margin-bottom:10px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
        <div><label style="font-size:13px;color:#2A2E35;display:block;margin-bottom:4px">Quando, mais ou menos?</label><input name="prox_quando" placeholder="Ex.: julho/2027" style="width:100%;font-size:15px;padding:10px;border:1px solid #E2E5EA;border-radius:8px;box-sizing:border-box"></div>
        <div><label style="font-size:13px;color:#2A2E35;display:block;margin-bottom:4px">Quem viajaria?</label><input name="prox_quem" placeholder="Ex.: a família toda" style="width:100%;font-size:15px;padding:10px;border:1px solid #E2E5EA;border-radius:8px;box-sizing:border-box"></div>
      </div>
      <div style="background:#EAF2FF;border-radius:8px;padding:10px 12px">
        <label style="font-size:13px;color:#2B5797;display:block;margin-bottom:4px;font-weight:600">A partir de quando podemos te procurar?</label>
        <input type="month" name="prox_contato" style="width:100%;font-size:15px;padding:10px;border:1px solid #E2E5EA;border-radius:8px;box-sizing:border-box">
        <div style="font-size:12px;color:#6B7280;margin-top:4px">A Vanessa entra em contato na época que você preferir, sem pressa.</div>
      </div>
    </div>
  </div>
  <button class="enviar" type="submit">Enviar avaliação</button>
</form></div>
<?php endif; ?>
<script>(function(){var s=document.getElementById('prox-sim'),n=document.getElementById('prox-nao'),bl=document.getElementById('bloco-prox');
function u(){if(bl)bl.hidden=!(s&&s.checked);} if(s)s.addEventListener('change',u); if(n)n.addEventListener('change',u); u();})();</script>
</body></html>
