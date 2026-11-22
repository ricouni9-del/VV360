<?php
function topo($titulo, $ativo = '') {
    $u = usuario();
    // menu agrupado por função; cada grupo vira uma seção com divisória sutil
    $grupos = [
        'dia'       => ['inicio' => 'Início', 'funil' => 'Funil', 'tarefas' => 'Tarefas', 'mensagens' => 'Mensagens'],
        'comercial' => ['clientes' => 'Clientes', 'grupos' => 'Grupos'],
        'operacao'  => ['viagens' => 'Viagens', 'vouchers' => 'Vouchers', 'satisfacao' => 'Satisfação'],
        'config'    => [],
    ];
    if ($u && pode_financeiro()) $grupos['comercial']['financeiro'] = 'Financeiro';
    if ($u && $u['papel'] === 'admin') $grupos['config']['usuarios'] = 'Equipe';
    $grupos = array_filter($grupos); // remove grupos vazios (ex.: config sem admin)
    $atrasadas = $u ? (int)valor('SELECT COUNT(*) FROM tarefas WHERE feita = 0 AND vence_em <= ?', [hoje()]) : 0;
    $novasMsg = $u ? (int)valor("SELECT COUNT(*) FROM mensagens WHERE status = 'nova'") : 0;
    ?><!DOCTYPE html>
<html lang="pt-BR"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow"><meta name="theme-color" content="#1B2A4A">
<title><?= h($titulo) ?> · VV360</title>
<link rel="icon" type="image/png" href="vv360-favicon.png">
<link rel="apple-touch-icon" href="vv360-touch.png">
<style>
:root{--ai:#1B2A4A;--ai2:#243654;--sora:#3D5A8A;--sora-c:#6E8CB8;--claro:#8FA9D3;--bg:#F3F4F6;--sup:#fff;--tinta:#2A2E35;--t2:#6B7280;--linha:#E2E5EA;
--ok:#14602E;--okbg:#E7F5EC;--erro:#9B1C1C;--errobg:#FDECEA;--alerta:#8A5A00;--alertabg:#FFF4DB;
--serif:Georgia,'Times New Roman',serif;--sans:'Segoe UI',system-ui,-apple-system,Roboto,Arial,sans-serif}
*{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--tinta);font-family:var(--sans);font-size:15.5px;line-height:1.5;padding-bottom:env(safe-area-inset-bottom,0)}
a{color:var(--sora)} h1,h2,h3{font-family:var(--serif);color:var(--ai);margin:0;line-height:1.2}
h1{font-size:27px} h2{font-size:20px;margin-bottom:12px} h3{font-size:17px}
header.topo{background:var(--ai);color:#fff;position:sticky;top:0;z-index:20;padding-top:env(safe-area-inset-top,0)}
.topo .in{max-width:1400px;margin:0 auto;padding:0 18px;display:flex;align-items:center;gap:18px;height:58px}
.marca{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;font-family:var(--serif);font-size:16px;white-space:nowrap}
.marca img{width:34px} .marca small{font-family:var(--sans);font-size:11px;color:var(--claro);display:block;letter-spacing:.08em;text-transform:uppercase}
nav.menu{display:flex;gap:4px;margin-left:10px;flex:1;overflow-x:auto}
nav.menu a{color:#B9C2CF;text-decoration:none;padding:8px 12px;border-radius:6px;font-size:14.5px;white-space:nowrap;position:relative}
nav.menu a.ativo,nav.menu a:hover{background:rgba(255,255,255,.1);color:#fff}
.menu-grupo{display:flex;gap:4px}
.menu-sep{width:1px;align-self:center;height:20px;background:rgba(255,255,255,.18);margin:0 6px;flex:none}
.bolinha{display:inline-block;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#E7907A;color:#fff;font-size:11px;font-weight:700;line-height:18px;text-align:center;margin-left:4px}
.conta{display:flex;align-items:center;gap:12px;font-size:13.5px;color:#B9C2CF;white-space:nowrap} .conta a{color:#DDE5F1}
main{max-width:1400px;margin:0 auto;padding:22px 18px 60px}
.cab{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
.cartao{background:var(--sup);border:1px solid var(--linha);border-radius:10px;padding:18px 20px;margin-bottom:16px}
.grade2{display:grid;grid-template-columns:1.4fr 1fr;gap:16px;align-items:start}
.grade3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.metricas{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px}
.metrica{background:var(--sup);border:1px solid var(--linha);border-radius:10px;padding:14px 16px}
.metrica b{display:block;font-family:var(--serif);font-size:28px;color:var(--ai);line-height:1.1}
.metrica span{font-size:13px;color:var(--t2)}
.btn{display:inline-flex;align-items:center;gap:6px;font:inherit;font-size:14px;font-weight:600;padding:9px 15px;border-radius:7px;border:1px solid var(--linha);background:#fff;color:var(--ai);cursor:pointer;text-decoration:none;white-space:nowrap}
.btn:hover{border-color:var(--sora)} .btn-pri{background:var(--sora);border-color:var(--sora);color:#fff} .btn-pri:hover{background:#34507D}
.btn-zap{background:#1E8E4E;border-color:#1E8E4E;color:#fff} .btn-perigo{color:var(--erro)} .btn-pq{padding:5px 10px;font-size:13px}
label{display:block;font-size:13px;font-weight:600;margin:10px 0 4px;color:var(--tinta)}
input,select,textarea{width:100%;font:inherit;font-size:15px;padding:9px 11px;border:1.5px solid var(--linha);border-radius:7px;background:#fff;color:var(--tinta)}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--sora);box-shadow:0 0 0 3px rgba(110,140,184,.22)}
textarea{min-height:80px;resize:vertical}
.linha{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0 12px}
.aviso{padding:11px 14px;border-radius:8px;margin-bottom:16px;background:var(--okbg);color:var(--ok);font-size:14.5px}
.aviso.atencao{background:#FBF4E7;border-color:#E7D4AE;color:#8A5A00}
.aviso.erro{background:var(--errobg);color:var(--erro)}
table{width:100%;border-collapse:collapse;font-size:14.5px} th{text-align:left;font-size:12.5px;color:var(--t2);font-weight:600;padding:8px 10px;border-bottom:1px solid var(--linha)}
td{padding:10px;border-bottom:1px solid var(--linha);vertical-align:top} tr:last-child td{border-bottom:none}
.tabela{overflow-x:auto}
.tag{display:inline-block;font-size:12px;font-weight:600;padding:2px 9px;border-radius:20px;background:#E8EDF5;color:var(--ai);white-space:nowrap}
.tag.novo{background:#E8EDF5} .tag.aprovada{background:#FFF4DB;color:#8A5A00} .tag.fechado,.tag.viagem,.tag.pos{background:var(--okbg);color:var(--ok)} .tag.perdido{background:var(--errobg);color:var(--erro)}
.tag.proposta,.tag.ajustes{background:var(--alertabg);color:var(--alerta)}
.mudo{color:var(--t2);font-size:13.5px}
.vazio{color:var(--t2);padding:10px 0}
/* Funil */
.funil{display:flex;gap:12px;overflow-x:auto;padding-bottom:10px;align-items:flex-start;min-height:60vh}
.coluna{flex:0 0 250px;background:#E9ECF1;border-radius:10px;padding:10px;min-height:200px}
.coluna.sobre{outline:2px dashed var(--sora);background:#DFE6F1}
.coluna h3{font-family:var(--sans);font-size:13.5px;font-weight:700;display:flex;justify-content:space-between;color:var(--ai);margin:2px 4px 10px}
.coluna h3 small{font-weight:600;color:var(--t2)}
.card{display:block;background:#fff;border-radius:8px;padding:10px 12px;margin-bottom:8px;box-shadow:0 1px 2px rgba(27,42,74,.08);text-decoration:none;color:var(--tinta);cursor:grab;border-left:3px solid var(--claro)}
.card.parado{border-left-color:#E7907A} .card b{display:block;color:var(--ai);font-size:14.5px}
.card .mudo{font-size:12.5px;display:block} .card.arrastando{opacity:.5}
/* Linha do tempo */
.tempo{list-style:none;margin:0;padding:0} .tempo li{padding:10px 0 10px 18px;border-left:2px solid var(--linha);position:relative}
.tempo li::before{content:"";position:absolute;left:-6px;top:15px;width:10px;height:10px;border-radius:50%;background:var(--claro)}
.tempo .mudo{display:block;font-size:12.5px} .tempo p{margin:2px 0 0;white-space:pre-wrap}
.tarefa{display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--linha)} .tarefa:last-child{border-bottom:none}
.tarefa form{margin:0} .tarefa .chk{width:22px;height:22px;border:2px solid var(--sora);border-radius:6px;background:#fff;cursor:pointer;padding:0;flex:none}
.tarefa.atrasada .mudo{color:var(--erro);font-weight:600}
.tarefa-editar{margin-top:2px} .tarefa-editar summary{cursor:pointer;color:var(--sora);font-size:12.5px;list-style:none} .tarefa-editar summary::-webkit-details-marker{display:none} .tarefa-editar input{font-size:13px;padding:5px 8px}
.feita{text-decoration:line-through;color:var(--t2)}
.barra{height:8px;background:#E9ECF1;border-radius:4px;overflow:hidden} .barra i{display:block;height:100%;background:var(--sora)}
details.modelos summary{cursor:pointer;font-weight:600;color:var(--sora);font-size:14px}
.pilha > * + *{margin-top:8px}
.login{max-width:380px;margin:10vh auto;background:#fff;border:1px solid var(--linha);border-radius:12px;padding:30px}
.login .marca{color:var(--ai);margin-bottom:18px}
@media (max-width:900px){.grade2,.grade3{grid-template-columns:1fr} .conta span{display:none} .topo .in{gap:8px;flex-wrap:wrap;height:auto;padding:8px 12px} .marca span{display:none} nav.menu{order:3;flex-basis:100%;margin-left:0;flex-wrap:wrap} .menu-grupo{flex-wrap:wrap} .menu-sep{display:none}}

/* Trilha de etapas (stepper) */
.trilha{display:flex;overflow:hidden;border-radius:10px;border:1px solid var(--linha);margin:16px 0 2px;background:var(--sup)}
.trilha .passo{position:relative;flex:1;min-width:0;padding:11px 8px 11px 22px;font-size:11.5px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--t2);background:#EEF1F6}
.trilha .passo:first-child{padding-left:14px}
.trilha .passo::after{content:"";position:absolute;top:0;right:-11px;width:22px;height:100%;background:inherit;clip-path:polygon(0 0,55% 50%,0 100%);z-index:2}
.trilha .passo::before{content:"";position:absolute;top:0;right:-13px;width:22px;height:100%;background:var(--linha);clip-path:polygon(0 0,55% 50%,0 100%);z-index:1}
.trilha .passo.feito{background:#2E4372;color:#cdd7e8}.trilha .passo.feito::after{background:#2E4372}
.trilha .passo.atual{background:#6EC5E8;color:#0B1830;font-weight:700}.trilha .passo.atual::after{background:#6EC5E8}
.trilha .passo:last-child::after,.trilha .passo:last-child::before{display:none}
.trilha-mini{font-size:11px;color:var(--t2);margin:0 0 10px 2px}
.trilha-perdido{display:flex;align-items:center;gap:10px;border:1px solid #E7C6C6;background:#FBECEC;border-radius:10px;padding:11px 16px;margin:16px 0 12px}
.trilha-perdido .tp-ic{width:26px;height:26px;border-radius:50%;background:#C0392B;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;flex:none}
.trilha-perdido b{color:#9B1C1C;margin-right:4px}
.trilha-mob{display:none;margin:16px 0 12px;background:var(--sup);border:1px solid var(--linha);border-radius:10px;padding:12px 14px}
.trilha-mob .tm-top{display:flex;justify-content:space-between;align-items:baseline;gap:8px}
.trilha-mob .tm-at{font-weight:700;color:#0B6EA8;font-size:14px}
.trilha-mob .tm-n{font-size:12px;color:var(--t2)}
.trilha-mob .tm-dots{display:flex;gap:6px;margin-top:8px}
.trilha-mob .tm-dots i{flex:1;height:5px;border-radius:3px;background:var(--linha)}
.trilha-mob .tm-dots i.on{background:#6EC5E8}
@media(max-width:760px){
  .trilha{display:none}
  .trilha-mini{display:none}
  .trilha-mob{display:block}
}
</style></head><body>
<?php if ($u): ?>
<header class="topo"><div class="in">
  <a class="marca" href="./"><img src="vv360-simbolo.svg" alt="VV360"><span>VV360 <sup style="font-size:10px;color:var(--sora-c,#6E8CB8);font-family:var(--sans);vertical-align:super">V2</sup><small>Vanessa Viagens e Turismo</small></span></a>
  <nav class="menu"><?php $primeiro = true; foreach ($grupos as $gk => $itens): ?><?php if (!$primeiro): ?><span class="menu-sep" aria-hidden="true"></span><?php endif; $primeiro = false; ?><span class="menu-grupo"><?php foreach ($itens as $k => $v): ?><a class="<?= $ativo === $k ? 'ativo' : '' ?>" href="./?p=<?= $k ?>"><?= $v ?><?php if ($k === 'tarefas' && $atrasadas): ?><span class="bolinha"><?= $atrasadas ?></span><?php endif; ?><?php if ($k === 'mensagens' && $novasMsg): ?><span class="bolinha"><?= $novasMsg ?></span><?php endif; ?></a><?php endforeach; ?></span><?php endforeach; ?></nav>
  <div class="conta"><span><?= h(primeiro_nome($u['nome'])) ?></span><a href="./?p=ajuda" target="_blank" rel="noopener">Ajuda</a><a href="./?p=conta">Conta</a><a href="./?p=sair">Sair</a></div>
</div></header>
<?php endif; ?>
<main>
<?php if ($a = aviso()): $cl = substr($a, 0, 1) === '!' ? 'erro' : (substr($a, 0, 1) === '?' ? 'atencao' : ''); ?><div class="aviso <?= $cl ?>"><?= h(ltrim($a, '!?')) ?></div><?php endif;
}

function rodape($js = '') { ?>
</main>
<script>
var CSRF=<?= json_encode($_SESSION['csrf'] ?? '') ?>;
<?= $js ?>
</script>
</body></html>
<?php }

function select_opcoes($opcoes, $atual, $vazio = null) {
    $s = $vazio !== null ? '<option value="">' . h($vazio) . '</option>' : '';
    foreach ($opcoes as $k => $v) { $k = is_int($k) ? $v : $k; $s .= '<option value="' . h($k) . '"' . ((string)$atual === (string)$k ? ' selected' : '') . '>' . h($v) . '</option>'; }
    return $s;
}
