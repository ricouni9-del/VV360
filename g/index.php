<?php
// Hotsite público de um grupo/excursão: SITE/g/?t=TOKEN
// Padrão visual "passaporte". Mostra vagas ao vivo e capta interesse direto no CRM.
require dirname(__DIR__) . '/crm/inc/app.php';
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$t = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
$g = strlen($t) === 24 ? um('SELECT * FROM grupos WHERE hotsite_token = ? AND hotsite_ativo = 1', [$t]) : null;

function hs_erro($msg) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $wa = link_whats(agencia_whats());
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h(agencia_nome()) . '</title><body style="font-family:Georgia,serif;background:#1B2A4A;color:#fff;display:grid;place-items:center;min-height:90vh;text-align:center;padding:20px"><div><h1 style="font-weight:400">' . h($msg) . '</h1><p style="font-family:system-ui;color:#B9C2CF">Fale com a gente' . ($wa ? ' pelo <a style="color:#fff" href="' . h($wa) . '">WhatsApp</a>' : '') . '.</p></div></body>';
    exit;
}
if (!$g) hs_erro('Esta excursão não está disponível no momento.');

$r = grupo_resumo($g);
$encerrado = in_array($g['status'], ['fechado', 'cancelado'], true);
$ok = false; $erro = '';

// ---------- POST: visitante registra interesse ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'interesse' && !$encerrado) {
    $nome = trim(mb_substr((string)($_POST['nome'] ?? ''), 0, 120));
    $contato = trim(mb_substr((string)($_POST['contato'] ?? ''), 0, 160));
    $pessoas = max(1, min(99, (int)($_POST['pessoas'] ?? 1)));
    if (mb_strlen($nome) < 3) $erro = 'Por favor, escreva seu nome.';
    elseif ($contato === '') $erro = 'Deixe um WhatsApp ou e-mail para a gente te retornar.';
    else {
        q('INSERT INTO grupo_participantes (grupo_id, nome, contato, pessoas, etapa, obs, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?,?)',
          [$g['id'], $nome, $contato, $pessoas, 'interesse', 'Veio pelo hotsite', agora(), agora()]);
        $ok = true;
        $r = grupo_resumo($g); // recarrega contadores
    }
}

$lotado = $r['lotado'];
$restam = $r['vagas_restantes'];
$periodo = $g['data_inicio'] ? data_br($g['data_inicio']) . ($g['data_fim'] ? ' a ' . data_br($g['data_fim']) : '') : ($g['periodo'] ?: '');
$dur = ($g['data_inicio'] && $g['data_fim']) ? duracao_viagem($g['data_inicio'], $g['data_fim']) : '';
$primeira = mb_strtoupper(mb_substr(trim($g['destino'] ?: $g['titulo']), 0, 1));
$msgWa = 'Olá! Tenho interesse na excursão ' . ($g['titulo'] ?: $g['destino']) . ($periodo ? ' (' . $periodo . ')' : '') . '. Pode me passar mais detalhes?';
$waLink = link_whats(agencia_whats(), $msgWa);
?><!doctype html>
<html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($g['titulo'] ?: $g['destino']) ?> · <?= h(agencia_nome()) ?></title>
<style>
:root{--ai:#1B2A4A;--ai2:#243654;--sora:#3D5A8A;--claro:#8FA9D3;--tinta:#2A2E35;--t2:#5F6775;--linha:#E2E5EA;--bg:#F3F4F6;--ok:#14602E;--okbg:#E7F5EC;--alerta:#8A5A00;--alertabg:#FFF4DB;--serif:Georgia,'Times New Roman',serif;--mono:'Courier New',monospace;--sans:'Segoe UI',system-ui,-apple-system,Roboto,Arial,sans-serif}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tinta);font-family:var(--sans);line-height:1.6}
.capa{background:linear-gradient(135deg,#1B2A4A,#243654);color:#fff;padding:48px 22px 40px;position:relative;overflow:hidden}
.capa .in{max-width:760px;margin:0 auto;position:relative;z-index:2}
.sobrancelha{font-family:var(--mono);text-transform:uppercase;letter-spacing:.22em;font-size:12.5px;color:var(--claro);margin:0 0 14px}
.capa h1{font-family:var(--serif);font-weight:700;font-size:clamp(34px,8vw,58px);line-height:1.04;margin:0}
.rota{font-family:var(--sans);color:#C9D5E8;font-size:16px;margin:10px 0 0}
.selo{position:absolute;top:34px;right:26px;width:84px;height:84px;background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.35);border-radius:10px;display:grid;place-items:center;transform:rotate(-5deg);font-family:var(--serif);font-size:40px;color:#fff;z-index:2}
.fatos{display:flex;flex-wrap:wrap;gap:10px 26px;margin:22px 0 0;font-family:var(--sans);font-size:15px;color:#DDE5F1}
.fatos b{display:block;font-family:var(--mono);text-transform:uppercase;letter-spacing:.1em;font-size:11px;color:var(--claro);font-weight:400;margin-bottom:2px}
.pg{max-width:760px;margin:0 auto;padding:26px 22px 60px}
.card{background:#fff;border:1px solid var(--linha);border-radius:12px;padding:22px 24px;margin:0 0 18px}
.vagas{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.barra{flex:1;min-width:160px;height:12px;background:var(--linha);border-radius:7px;overflow:hidden}
.barra i{display:block;height:100%;background:linear-gradient(90deg,#3D5A8A,#6E8CB8)}
.vagas .num{font-family:var(--serif);font-size:26px;color:var(--ai);white-space:nowrap}
.urg{margin:12px 0 0;padding:9px 13px;border-radius:8px;font-size:14.5px;font-weight:600}
.urg.ok{background:var(--okbg);color:var(--ok)} .urg.al{background:var(--alertabg);color:var(--alerta)}
.chamada{font-family:var(--serif);font-size:19px;color:var(--ai);line-height:1.5}
.ctas{display:flex;gap:12px;flex-wrap:wrap;margin-top:6px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font:inherit;font-size:16px;font-weight:600;padding:14px 20px;border-radius:9px;border:1.5px solid var(--sora);background:#fff;color:var(--ai);cursor:pointer;text-decoration:none}
.btn-pri{background:var(--sora);color:#fff} .btn-zap{background:#1E8E4E;border-color:#1E8E4E;color:#fff}
.btn:hover{filter:brightness(.97)}
label{display:block;font-size:13px;font-weight:600;margin:12px 0 4px;color:var(--tinta)}
input{width:100%;font:inherit;font-size:16px;padding:12px 13px;border:1.5px solid var(--linha);border-radius:8px}
input:focus{outline:none;border-color:var(--sora);box-shadow:0 0 0 3px rgba(110,140,184,.22)}
.rodape{text-align:center;color:var(--t2);font-size:13px;padding:10px 0 0}
#form-int{display:none;margin-top:14px;border-top:1px solid var(--linha);padding-top:14px}
#form-int.abre{display:block}
.ok-box{background:var(--okbg);color:var(--ok);border-radius:10px;padding:18px 20px;font-size:16px}
.erro{background:#FDECEA;color:#9B1C1C;border-radius:8px;padding:10px 13px;margin-bottom:10px;font-size:14.5px}
</style></head>
<body>
<header class="capa">
  <div class="selo"><?= h($primeira) ?></div>
  <div class="in">
    <p class="sobrancelha"><?= h(agencia_nome()) ?></p>
    <h1><?= h($g['destino'] ?: $g['titulo']) ?></h1>
    <?php if ($g['titulo'] && $g['destino'] && $g['titulo'] !== $g['destino']): ?><p class="rota"><?= h($g['titulo']) ?></p><?php endif; ?>
    <div class="fatos">
      <?php if ($periodo): ?><div><b>Quando</b><?= h($periodo) ?><?= $dur ? ' · ' . h($dur) : '' ?></div><?php endif; ?>
      <?php if ($g['valor'] > 0): ?><div><b>A partir de</b><?= moeda_fmt($g['valor'], $g['moeda']) ?> por pessoa</div><?php endif; ?>
    </div>
  </div>
</header>

<main class="pg">
  <?php if ($g['chamada']): ?><div class="card"><p class="chamada" style="margin:0"><?= nl2br(h($g['chamada'])) ?></p></div><?php endif; ?>

  <div class="card">
    <?php if ($encerrado): ?>
      <p class="urg al" style="margin:0">As inscrições para esta excursão estão encerradas. Fale com a gente para as próximas datas.</p>
    <?php else: ?>
      <div class="vagas">
        <?php if ($g['vagas_total'] > 0): ?>
          <div class="barra"><i style="width:<?= $r['ocup_pct'] ?>%"></i></div>
          <div class="num"><?= $lotado ? 'Esgotado' : 'Restam ' . $restam ?></div>
        <?php else: ?>
          <div class="num">Vagas abertas</div>
        <?php endif; ?>
      </div>
      <?php if ($lotado): ?>
        <p class="urg al">Vagas esgotadas — entre na lista de espera e avisamos se abrir uma vaga.</p>
      <?php elseif ($g['vagas_total'] > 0 && $restam <= 4): ?>
        <p class="urg al">Últimas <?= $restam ?> vaga<?= $restam > 1 ? 's' : '' ?> — o grupo está quase fechando.</p>
      <?php elseif ($r['viavel']): ?>
        <p class="urg ok">Grupo confirmado! As vagas restantes estão abertas.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (!$encerrado): ?>
  <div class="card">
    <?php if ($ok): ?>
      <div class="ok-box"><b>Recebemos seu interesse!</b><br>Em breve a gente te chama<?= agencia_whats() ? ' no WhatsApp' : '' ?> com todos os detalhes da <?= h($g['titulo'] ?: $g['destino']) ?>.</div>
    <?php else: ?>
      <div class="ctas">
        <button class="btn btn-pri" type="button" onclick="document.getElementById('form-int').classList.add('abre');this.style.display='none'"><?= $lotado ? 'Entrar na lista de espera' : 'Tenho interesse' ?></button>
        <?php if ($waLink): ?><a class="btn btn-zap" href="<?= h($waLink) ?>" target="_blank" rel="noopener">Quero mais detalhes</a><?php endif; ?>
        <?php if ($g['roteiro_url']): ?><a class="btn" href="<?= h($g['roteiro_url']) ?>" target="_blank" rel="noopener">Ver o roteiro</a><?php endif; ?>
      </div>
      <form id="form-int" class="<?= $erro ? 'abre' : '' ?>" method="post">
        <?php if ($erro): ?><div class="erro"><?= h($erro) ?></div><?php endif; ?>
        <input type="hidden" name="acao" value="interesse">
        <label>Seu nome</label><input name="nome" required value="<?= h($_POST['nome'] ?? '') ?>" autocomplete="name">
        <label>WhatsApp ou e-mail</label><input name="contato" required value="<?= h($_POST['contato'] ?? '') ?>" placeholder="(11) 90000-0000">
        <label>Quantas pessoas</label><input type="number" name="pessoas" min="1" max="99" value="<?= (int)($_POST['pessoas'] ?? 1) ?>">
        <button class="btn btn-pri" style="margin-top:14px;width:100%">Enviar meu interesse</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <p class="rodape"><?= h(agencia_nome()) ?><?= agencia_insta() ? ' · ' . h(agencia_insta()) : '' ?></p>
</main>
</body></html>
