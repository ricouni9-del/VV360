<?php
// ================= Acesso =================
function pagina_instalar($erro) {
    topo('Instalação'); ?>
  <div class="login">
    <div class="marca"><img src="vv360-simbolo.svg" alt="VV360" style="width:40px"><span>VV360<small>Instalação · Vanessa Viagens e Turismo</small></span></div>
    <?php if (CRM_CHAVE_INSTALACAO === ''): ?>
      <p class="aviso erro">Para instalar, preencha <b>CRM_CHAVE_INSTALACAO</b> no arquivo dados/config.php e recarregue esta página.</p>
    <?php else: ?>
      <p class="mudo">Crie o usuário administrador. Ele poderá cadastrar o restante da equipe.</p>
      <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif; ?>
      <form method="post">
        <label>Chave de instalação</label><input name="chave" type="password" required>
        <label>Seu nome</label><input name="nome" required>
        <label>E-mail</label><input name="email" type="email" required>
        <label>Senha (mínimo 10 caracteres)</label><input name="senha" type="password" minlength="10" required>
        <p><button class="btn btn-pri" type="submit">Instalar CRM</button></p>
      </form>
    <?php endif; ?>
  </div>
<?php rodape(); }

function pagina_entrar($erro) {
    topo('Entrar'); ?>
  <div class="login">
    <div class="marca"><img src="vv360-simbolo.svg" alt="VV360" style="width:40px"><span>VV360<small>Vanessa Viagens e Turismo</small></span></div>
    <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif; ?>
    <form method="post"><?= csrf_campo() ?>
      <label>E-mail</label><input name="email" type="email" autocomplete="username" required autofocus>
      <label>Senha</label><input name="senha" type="password" autocomplete="current-password" required>
      <p><button class="btn btn-pri" type="submit">Entrar</button></p>
    </form>
  </div>
<?php rodape(); }

// ================= Início =================
function pagina_inicio() {
    $u = usuario();
    $ini = date('Y-m-01');
    $leadsMes = (int)valor("SELECT COUNT(*) FROM leads WHERE criado_em >= ? AND etapa <> 'entrega'", [$ini]);
    $fechMes = (int)valor("SELECT COUNT(*) FROM atividades WHERE tipo = 'etapa' AND texto LIKE '%→ Fechado%' AND criado_em >= ?", [$ini]);
    $abertos = um("SELECT COUNT(*) n, COALESCE(SUM(valor_estimado),0) v FROM leads WHERE etapa IN ('novo','conversa','roteiro','proposta','ajustes')");
    $concl = um("SELECT SUM(etapa IN ('fechado','viagem','pos')) g, SUM(etapa = 'perdido') p FROM leads");
    $taxa = ($concl['g'] + $concl['p']) ? round(100 * $concl['g'] / ($concl['g'] + $concl['p'])) : null;
    $tarefas = todos('SELECT t.*, c.nome cliente, l.destino FROM tarefas t LEFT JOIN clientes c ON c.id = t.cliente_id LEFT JOIN leads l ON l.id = t.lead_id
                      WHERE t.feita = 0 AND t.vence_em <= ? ORDER BY t.vence_em LIMIT 12', [date('Y-m-d', strtotime('+1 day'))]);
    $novos = todos("SELECT l.*, c.nome FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.etapa = 'novo' ORDER BY l.criado_em DESC LIMIT 8");
    $parados = todos("SELECT l.*, c.nome FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.etapa IN ('proposta','ajustes') AND l.etapa_desde <= ? ORDER BY l.etapa_desde LIMIT 8", [date('Y-m-d H:i:s', strtotime('-5 days'))]);
    $proximas = todos("SELECT l.*, c.nome FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.etapa IN ('fechado','viagem') AND l.data_embarque >= ? ORDER BY l.data_embarque LIMIT 6", [hoje()]);
    $porOrigem = todos("SELECT COALESCE(origem,'Não informada') o, COUNT(*) n, SUM(etapa IN ('fechado','viagem','pos')) g FROM leads GROUP BY o ORDER BY n DESC");
    $maxO = max(array_column($porOrigem, 'n') ?: [1]);
    topo('Início', 'inicio'); ?>
  <div class="cab"><h1>Olá, <?= h(primeiro_nome($u['nome'])) ?>.</h1><a class="btn btn-pri" href="./?p=novo">+ Novo lead</a></div>
  <div class="metricas">
    <div class="metrica"><b><?= $leadsMes ?></b><span>Leads este mês</span></div>
    <div class="metrica"><b><?= $fechMes ?></b><span>Vendas fechadas este mês</span></div>
    <div class="metrica"><b><?= $abertos['n'] ?></b><span>Em negociação</span></div>
    <div class="metrica"><b><?= reais($abertos['v']) ?: 'R$ 0' ?></b><span>Valor em negociação</span></div>
    <div class="metrica"><b><?= $taxa === null ? '—' : $taxa . '%' ?></b><span>Taxa de fechamento</span></div>
  </div>
  <div class="grade2">
    <div>
      <div class="cartao"><h2>Tarefas para hoje e atrasadas</h2>
        <?php if (!$tarefas): ?><p class="vazio">Nenhuma tarefa pendente. 🎉</p><?php endif;
        foreach ($tarefas as $t) linha_tarefa($t, true); ?>
        <p style="margin:10px 0 0"><a href="./?p=tarefas">Ver todas as tarefas</a></p>
      </div>
      <?php if (pode_financeiro()): $fv = todos("SELECT p.*, c.nome, v.id vid FROM venda_parcelas p JOIN vendas v ON v.id = p.venda_id JOIN clientes c ON c.id = v.cliente_id WHERE p.pago_em IS NULL AND p.vencimento <= ? ORDER BY p.vencimento LIMIT 6", [date('Y-m-d', strtotime('+3 days'))]);
        $ff = todos("SELECT i.*, c.nome, v.id vid FROM venda_itens i JOIN vendas v ON v.id = i.venda_id JOIN clientes c ON c.id = v.cliente_id WHERE i.pago_direto = 0 AND i.custo > 0 AND i.pago_fornecedor_em IS NULL AND i.vencimento_fornecedor <= ? ORDER BY i.vencimento_fornecedor LIMIT 6", [date('Y-m-d', strtotime('+3 days'))]);
        if ($fv || $ff): ?>
      <div class="cartao"><h2>Financeiro: atenção</h2>
        <?php foreach ($fv as $p): ?><p style="margin:6px 0">Receber <b><?= reais($p['valor']) ?></b> de <a href="./?p=venda&id=<?= $p['vid'] ?>"><?= h($p['nome']) ?></a> <span class="mudo">· <?= data_br($p['vencimento']) ?></span><?= $p['vencimento'] < hoje() ? ' <span class="tag perdido">Vencida</span>' : '' ?></p><?php endforeach; ?>
        <?php foreach ($ff as $i): ?><p style="margin:6px 0">Pagar <b><?= reais($i['custo']) ?></b> a <?= h($i['fornecedor']) ?> <span class="mudo">· <a href="./?p=venda&id=<?= $i['vid'] ?>"><?= h($i['nome']) ?></a> · <?= data_br($i['vencimento_fornecedor']) ?></span><?= $i['vencimento_fornecedor'] < hoje() ? ' <span class="tag perdido">Atrasado</span>' : '' ?></p><?php endforeach; ?>
        <p style="margin:10px 0 0"><a href="./?p=financeiro">Abrir o Financeiro</a></p>
      </div>
      <?php endif; endif; ?>
      <?php $pvz = todos("SELECT p.*, c.nome FROM propostas p JOIN leads l ON l.id = p.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE p.status = 'enviada' AND p.validade IS NOT NULL AND p.validade <= ? AND l.etapa IN ('proposta','ajustes') ORDER BY p.validade LIMIT 8", [date('Y-m-d', strtotime('+3 days'))]); if ($pvz): ?>
      <div class="cartao"><h2>Cotações vencendo</h2><p class="mudo" style="margin-top:-6px">Bom motivo para retomar o contato com o cliente.</p>
        <?php foreach ($pvz as $x): ?><p style="margin:6px 0"><a href="./?p=proposta&id=<?= $x['id'] ?>"><b><?= h($x['nome']) ?></b> · v<?= (int)$x['versao'] ?></a> <span class="tag <?= $x['validade'] < hoje() ? 'perdido' : 'proposta' ?>"><?= $x['validade'] < hoje() ? 'Venceu em ' : 'Vence em ' ?><?= data_br($x['validade']) ?></span></p><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php $abertos_ = todos("SELECT l.*, c.nome FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.etapa IN ('novo','conversa','roteiro','proposta','ajustes')");
        $quentes = []; $esfriando = [];
        foreach ($abertos_ as $x) { $t = termometro($x); if (!$t) continue; $x['temp'] = $t;
            if ($t['nivel'] === 'quente' && dias_desde($t['ultimo_contato'] ?: $x['criado_em']) >= 2) $quentes[] = $x;
            if ($x['temp_snapshot'] !== null && $x['temp_snapshot'] - $t['nota'] >= 15) $esfriando[] = $x; }
        $vistos = todos("SELECT p.id pid, p.versao, l.id lid, l.codigo, c.nome, MAX(a.acessado_em) quando, COUNT(*) n FROM roteiro_acessos a JOIN propostas p ON p.id = a.proposta_id JOIN leads l ON l.id = p.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE a.acessado_em >= ? GROUP BY p.id ORDER BY quando DESC LIMIT 6", [date('Y-m-d H:i:s', strtotime('-48 hours'))]);
        if ($quentes || $vistos || $esfriando): ?>
      <div class="cartao"><h2>🔥 Prioridade de hoje</h2>
        <?php if ($vistos): ?><p class="mudo" style="margin:0 0 4px">Abriram o roteiro nas últimas 48 horas</p><?php foreach ($vistos as $x): ?><p style="margin:4px 0"><a href="./?p=lead&id=<?= $x['lid'] ?>"><b><?= h($x['nome']) ?></b></a> <span class="mudo">· <?= h($x['codigo']) ?>-v<?= (int)$x['versao'] ?> · <?= (int)$x['n'] ?>× · última vez <?= data_br($x['quando'], true) ?></span></p><?php endforeach; endif; ?>
        <?php if ($quentes): ?><p class="mudo" style="margin:10px 0 4px">Quentes sem contato há 2 dias ou mais</p><?php foreach ($quentes as $x): ?><p style="margin:4px 0"><a href="./?p=lead&id=<?= $x['id'] ?>"><b><?= h($x['nome']) ?></b></a> · <?= h($x['destino'] ?: 'Destino a definir') ?> <?= selo_temp($x['temp']) ?></p><?php endforeach; endif; ?>
        <?php if ($esfriando): ?><p class="mudo" style="margin:10px 0 4px">Esfriando desde ontem</p><?php foreach ($esfriando as $x): ?><p style="margin:4px 0"><a href="./?p=lead&id=<?= $x['id'] ?>"><b><?= h($x['nome']) ?></b></a> <span class="mudo">· de <?= (int)$x['temp_snapshot'] ?> para</span> <?= selo_temp($x['temp']) ?></p><?php endforeach; endif; ?>
      </div>
      <?php endif; ?>
      <div class="cartao"><h2>Propostas sem retorno há mais de 5 dias</h2>
        <?php if (!$parados): ?><p class="vazio">Nenhuma proposta parada.</p><?php endif; ?>
        <?php foreach ($parados as $l): ?><p style="margin:6px 0"><a href="./?p=lead&id=<?= $l['id'] ?>"><b><?= h($l['nome']) ?></b></a> · <?= h($l['destino'] ?: 'Destino a definir') ?> <span class="mudo">· <?= h(ETAPAS[$l['etapa']]) ?> há <?= dias_desde($l['etapa_desde']) ?> dias</span></p><?php endforeach; ?>
      </div>
    </div>
    <div>
      <?php $mn = todos("SELECT * FROM mensagens WHERE status = 'nova' ORDER BY tipo = 'reclamacao' DESC, criado_em DESC LIMIT 5"); if ($mn): ?>
      <div class="cartao"><h2>Mensagens novas do site</h2>
        <?php foreach ($mn as $mm): ?><p style="margin:6px 0"><span class="tag <?= ['elogio' => 'fechado', 'duvida' => 'novo', 'reclamacao' => 'perdido'][$mm['tipo']] ?>"><?= h(TIPOS_MENSAGEM[$mm['tipo']]) ?></span> <b><?= h($mm['nome']) ?></b> <span class="mudo">· <?= data_br($mm['criado_em']) ?></span></p><?php endforeach; ?>
        <p style="margin:10px 0 0"><a href="./?p=mensagens">Ver mensagens</a></p>
      </div>
      <?php endif; ?>
      <div class="cartao"><h2>Novos leads</h2>
        <?php if (!$novos): ?><p class="vazio">Nenhum lead novo.</p><?php endif; ?>
        <?php foreach ($novos as $l): ?><p style="margin:6px 0"><a href="./?p=lead&id=<?= $l['id'] ?>"><b><?= h($l['nome']) ?></b></a> · <?= h($l['destino'] ?: 'Destino a definir') ?><span class="mudo"> · <?= h($l['origem']) ?> · <?= data_br($l['criado_em']) ?></span></p><?php endforeach; ?>
      </div>
      <?php $pv = todos("SELECT c.id, c.nome, c.passaporte_validade v, 'titular' q FROM clientes c WHERE c.passaporte_validade IS NOT NULL AND c.passaporte_validade <= ?
                         UNION ALL SELECT v.cliente_id, v.nome, v.passaporte_validade, v.parentesco FROM viajantes v WHERE v.passaporte_validade IS NOT NULL AND v.passaporte_validade <= ? ORDER BY 3 LIMIT 8",
                        [date('Y-m-d', strtotime('+6 months')), date('Y-m-d', strtotime('+6 months'))]); if ($pv): ?>
      <div class="cartao"><h2>Passaportes vencidos ou vencendo em 6 meses</h2>
        <?php foreach ($pv as $x): [$pc, $pt] = situacao_passaporte($x['v']); ?><p style="margin:6px 0"><a href="./?p=cliente&id=<?= $x['id'] ?>"><?= h($x['nome']) ?></a> <span class="mudo">· <?= data_br($x['v']) ?></span> <span class="tag <?= $pc ?>"><?= $pt ?></span></p><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="cartao"><h2>Próximos embarques</h2>
        <?php if (!$proximas): ?><p class="vazio">Nenhum embarque agendado.</p><?php endif; ?>
        <?php foreach ($proximas as $l): ?><p style="margin:6px 0"><b><?= data_br($l['data_embarque']) ?></b> · <a href="./?p=lead&id=<?= $l['id'] ?>"><?= h($l['nome']) ?></a> · <?= h($l['destino']) ?></p><?php endforeach; ?>
      </div>
      <div class="cartao"><h2>Leads por origem</h2>
        <?php if (!$porOrigem): ?><p class="vazio">Ainda sem dados.</p><?php endif; ?>
        <?php foreach ($porOrigem as $o): ?>
          <div style="margin:8px 0"><div style="display:flex;justify-content:space-between;font-size:14px"><span><?= h($o['o']) ?></span><span class="mudo"><?= $o['n'] ?> <?= $o['n'] == 1 ? 'lead' : 'leads' ?> · <?= (int)$o['g'] ?> <?= (int)$o['g'] === 1 ? 'venda' : 'vendas' ?></span></div>
          <div class="barra"><i style="width:<?= round(100 * $o['n'] / $maxO) ?>%"></i></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php rodape(); }

function linha_tarefa($t, $mostrarCliente = false) {
    $atrasada = !$t['feita'] && $t['vence_em'] < hoje();
    $volta = h($_SERVER['REQUEST_URI']); ?>
  <div class="tarefa <?= $atrasada ? 'atrasada' : '' ?>">
    <?php if (!$t['feita']): ?>
      <form method="post" action="./?p=acao&a=tarefa_feita"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="voltar" value="<?= $volta ?>"><button class="chk" title="Marcar como feita" aria-label="Marcar como feita"></button></form>
    <?php else: ?><span style="width:22px;text-align:center;color:var(--ok)">✓</span><?php endif; ?>
    <div style="flex:1"><div class="<?= $t['feita'] ? 'feita' : '' ?>"><?= h($t['titulo']) ?></div>
      <span class="mudo"><?= $atrasada ? 'Atrasada · ' : '' ?><?= data_br($t['vence_em']) ?>
      <?php if ($mostrarCliente && !empty($t['cliente'])): ?> · <a href="./?p=<?= $t['lead_id'] ? 'lead&id=' . $t['lead_id'] : 'cliente&id=' . $t['cliente_id'] ?>"><?= h($t['cliente']) ?></a><?= !empty($t['destino']) ? ' · ' . h($t['destino']) : '' ?><?php endif; ?></span>
      <?php if (!$t['feita']): ?>
      <details class="tarefa-editar"><summary>editar</summary>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;align-items:center">
          <form method="post" action="./?p=acao&a=tarefa_adiar" style="display:flex;gap:4px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="voltar" value="<?= $volta ?>"><button name="d" value="1" class="btn btn-pq">+1d</button><button name="d" value="3" class="btn btn-pq">+3d</button><button name="d" value="7" class="btn btn-pq">+7d</button></form>
          <form method="post" action="./?p=acao&a=tarefa_editar" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="voltar" value="<?= $volta ?>">
            <input type="date" name="vence_em" value="<?= h($t['vence_em']) ?>"><input name="titulo" value="<?= h($t['titulo']) ?>" style="min-width:180px"><button class="btn btn-pq btn-pri">Salvar</button></form>
          <form method="post" action="./?p=acao&a=tarefa_excluir" onsubmit="return confirm('Excluir esta tarefa?')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="voltar" value="<?= $volta ?>"><button class="btn btn-pq btn-perigo">Excluir</button></form>
        </div>
      </details>
      <?php endif; ?></div>
  </div>
<?php }

// ================= Funil =================
function pagina_funil() {
    $busca = trim($_GET['b'] ?? '');
    $mostrarPerdidos = !empty($_GET['perdidos']);
    $ordem = ($_GET['ordem'] ?? '') === 'temp' ? 'temp' : 'tempo';
    $sql = "SELECT l.*, c.nome, c.telefone FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.etapa <> 'entrega'";
    $temWhere = true;
    $par = [];
    if ($busca !== '') { $sql .= ' AND (c.nome LIKE ? OR l.destino LIKE ? OR l.codigo LIKE ?)'; $par = ["%$busca%", "%$busca%", '%' . strtoupper($busca) . '%']; }
    $sql .= ' ORDER BY l.etapa_desde';
    $leads = todos($sql, $par);
    $porEtapa = array_fill_keys(array_keys(ETAPAS), []);
    foreach ($leads as $l) { $l['temp'] = termometro($l); $porEtapa[$l['etapa']][] = $l; }
    if ($ordem === 'temp') foreach ($porEtapa as &$col) usort($col, fn($a, $b) => ($b['temp'] ? ['quente' => 3, 'morno' => 2, 'frio' => 1][$b['temp']['nivel']] * 1000 + $b['temp']['nota'] : 0) <=> ($a['temp'] ? ['quente' => 3, 'morno' => 2, 'frio' => 1][$a['temp']['nivel']] * 1000 + $a['temp']['nota'] : 0));
    unset($col);
    topo('Funil', 'funil'); ?>
  <div class="cab"><h1>Funil de vendas</h1>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><input type="hidden" name="p" value="funil">
      <input name="b" value="<?= h($busca) ?>" placeholder="Cliente, destino ou código" style="width:220px">
      <select name="ordem" style="width:auto" onchange="this.form.submit()"><option value="tempo">Ordenar por tempo na etapa</option><option value="temp" <?= $ordem === 'temp' ? 'selected' : '' ?>>Ordenar pelos mais quentes</option></select>
      <label style="display:flex;align-items:center;gap:6px;margin:0;font-weight:400"><input type="checkbox" name="perdidos" value="1" style="width:auto" <?= $mostrarPerdidos ? 'checked' : '' ?> onchange="this.form.submit()">Mostrar perdidos</label>
      <a class="btn btn-pri" href="./?p=novo">+ Novo lead</a></form>
  </div>
  <p class="mudo" style="margin-top:-8px">Arraste os cartões entre as colunas para mudar a etapa. No celular, abra o lead e troque a etapa por lá. Cartões com borda coral estão parados há mais de 7 dias.</p>
  <div class="funil">
    <?php foreach (ETAPAS as $k => $rot): if ($k === 'perdido' && !$mostrarPerdidos) continue;
      $soma = array_sum(array_map(fn($l) => (float)$l['valor_estimado'], $porEtapa[$k])); ?>
      <section class="coluna" data-etapa="<?= $k ?>">
        <h3><?= h($rot) ?> <small><?= count($porEtapa[$k]) ?><?= $soma ? ' · ' . reais($soma) : '' ?></small></h3>
        <?php foreach ($porEtapa[$k] as $l): $parado = dias_desde($l['etapa_desde']) > 7 && !in_array($k, ['aprovada', 'fechado', 'viagem', 'pos', 'perdido']); ?>
          <a class="card <?= $parado ? 'parado' : '' ?>" href="./?p=lead&id=<?= $l['id'] ?>" draggable="true" data-id="<?= $l['id'] ?>">
            <span class="mudo" style="display:flex;justify-content:space-between;gap:6px"><span><?= h($l['codigo']) ?></span><?= selo_temp($l['temp']) ?></span>
            <b><?= h($l['nome']) ?></b>
            <span><?= h($l['destino'] ?: 'Destino a definir') ?></span>
            <span class="mudo"><?= h($l['periodo'] ?: '') ?><?= $l['valor_estimado'] ? ' · ' . reais($l['valor_estimado']) : '' ?></span>
            <span class="mudo"><?= h($l['origem'] ?: '') ?> · <?= ($dd = dias_desde($l['etapa_desde'])) ? 'há ' . $dd . ($dd == 1 ? ' dia' : ' dias') . ' nesta etapa' : 'nesta etapa desde hoje' ?></span>
          </a>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php rodape(<<<'JS'
var arrastado=null;
document.querySelectorAll('.card').forEach(function(c){
  c.addEventListener('dragstart',function(e){arrastado=c;c.classList.add('arrastando');e.dataTransfer.effectAllowed='move';});
  c.addEventListener('dragend',function(){c.classList.remove('arrastando');});
});
document.querySelectorAll('.coluna').forEach(function(col){
  col.addEventListener('dragover',function(e){e.preventDefault();col.classList.add('sobre');});
  col.addEventListener('dragleave',function(){col.classList.remove('sobre');});
  col.addEventListener('drop',function(e){
    e.preventDefault();col.classList.remove('sobre'); if(!arrastado) return;
    var etapa=col.getAttribute('data-etapa'), id=arrastado.getAttribute('data-id');
    if(etapa==='perdido'){ location.href='./?p=lead&id='+id+'#etapa'; return; }
    var fd=new FormData(); fd.append('csrf',CSRF); fd.append('id',id); fd.append('etapa',etapa); fd.append('ajax','1');
    fetch('./?p=acao&a=mudar_etapa',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){ if(r.ok) location.reload(); else alert('Não foi possível mover o lead.'); });
  });
});
JS);
}

// ================= Lead =================
function pagina_lead($id) {
    $l = um('SELECT l.*, c.nome, c.email, c.telefone, c.id cid FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.id = ?', [$id]);
    if (!$l) redirecionar('./?p=funil', '!Lead não encontrado.');
    $ativ = todos('SELECT a.*, u.nome usuario FROM atividades a LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE a.lead_id = ? ORDER BY a.criado_em DESC, a.id DESC', [$id]);
    $tarefas = todos('SELECT * FROM tarefas WHERE lead_id = ? ORDER BY feita, vence_em', [$id]);
    $equipe = todos('SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome');
    $cli = ['nome' => $l['nome']];
    topo($l['codigo'] . ' · ' . $l['nome'], 'funil'); ?>
  <div class="cab">
    <div><p class="mudo" style="margin:0"><a href="./?p=funil">Funil</a> › <?= h($l['codigo']) ?></p>
      <h1><a href="./?p=cliente&id=<?= $l['cid'] ?>" style="text-decoration:none;color:inherit"><?= h($l['nome']) ?></a> · <?= h($l['destino'] ?: 'Destino a definir') ?></h1>
      <span class="tag <?= h($l['etapa']) ?>"><?= h(ETAPAS[$l['etapa']]) ?></span> <span class="mudo">desde <?= data_br($l['etapa_desde']) ?> · origem: <?= h($l['origem'] ?: '—') ?></span></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($z = link_whats($l['telefone'])): ?><a class="btn btn-zap" href="<?= h($z) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      <?php if ($l['email']): ?><a class="btn" href="mailto:<?= h($l['email']) ?>">E-mail</a><?php endif; ?>
    </div>
  </div>
  <?= trilha_etapas($l) ?>
  <div class="grade2">
    <div>
      <?php $tp = termometro($l); if ($tp): [$ti, $tr] = NIVEIS_TEMP[$tp['nivel']]; ?>
      <div class="cartao" id="termometro"><h2>Termômetro</h2>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap"><span style="font-size:30px"><?= $ti ?></span>
          <div style="flex:1;min-width:200px"><b style="font-size:18px"><?= $tr ?></b><?= $tp['manual'] ? ' <span class="mudo">(ajustado pela equipe · calculado: ' . NIVEIS_TEMP[$tp['auto']][1] . ' ' . $tp['nota'] . ')</span>' : ' <span class="mudo">· nota ' . $tp['nota'] . ' de 100</span>' ?>
            <div class="barra" style="margin-top:6px"><i style="width:<?= $tp['nota'] ?>%;background:<?= ['quente' => '#E7907A', 'morno' => '#E9B949', 'frio' => '#8FA9D3'][$tp['auto']] ?>"></i></div></div></div>
        <ul style="margin:10px 0 0;padding-left:18px;font-size:14px"><?php foreach ($tp['motivos'] as [$pt, $tx]): ?><li><span style="color:<?= $pt >= 0 ? 'var(--ok)' : 'var(--erro)' ?>;font-weight:700"><?= $pt >= 0 ? '+' . $pt : $pt ?></span> <?= h($tx) ?></li><?php endforeach; ?></ul>
        <form method="post" action="./?p=acao&a=temp_salvar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $l['id'] ?>">
          <label style="margin:0">Sua percepção:</label><select name="temp" style="width:auto"><option value="">Automático</option><?php foreach (NIVEIS_TEMP as $k => [$i, $r]): ?><option value="<?= $k ?>" <?= $tp['manual'] && $l['temp_manual'] === $k ? 'selected' : '' ?>><?= $i ?> <?= $r ?></option><?php endforeach; ?></select>
          <button class="btn btn-pq">Salvar</button><span class="mudo" style="font-size:12.5px">O ajuste manual vale por 14 dias.</span></form>
      </div>
      <?php endif; ?>
      <div class="cartao" id="etapa"><h2>Etapa</h2>
        <form method="post" action="./?p=acao&a=mudar_etapa" class="linha" style="align-items:end"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $l['id'] ?>">
          <div><label>Etapa</label><select name="etapa" id="sel-etapa"><?= select_opcoes(ETAPAS, $l['etapa']) ?></select></div>
          <div id="motivo" <?= $l['etapa'] === 'perdido' ? '' : 'hidden' ?>><label>Motivo da perda</label><select name="motivo_perda"><?= select_opcoes(MOTIVOS_PERDA, $l['motivo_perda'], 'Selecione') ?></select></div>
          <div><button class="btn btn-pri" type="submit" style="margin-top:10px">Atualizar etapa</button></div>
        </form>
        <?php if ($l['etapa'] === 'aprovada'): $ev = um("SELECT o.* FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE p.lead_id = ? AND p.status = 'aprovada' AND o.escolhida = 1 ORDER BY p.versao DESC LIMIT 1", [$l['id']]); ?>
          <div style="margin-top:14px;border-top:1px solid var(--linha);padding-top:14px">
            <p style="margin:0 0 8px">O cliente aprovou<?= $ev ? ' a opção <b>' . h($ev['nome']) . '</b>' : '' ?><?= $ev && !empty($ev['aprovada_cliente_em']) ? ' <span class="mudo">(pelo site, em ' . data_br($ev['aprovada_cliente_em'], true) . ', como ' . h($ev['aprovada_cliente_nome']) . ')</span>' : '' ?>. Ao receber o sinal e confirmar as reservas, feche a venda:</p>
            <form method="post" action="./?p=acao&a=prop_fechar" onsubmit="return confirm('Confirmar o fechamento? O lead vai para Fechado e a venda entra no Financeiro.')"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>"><button class="btn btn-pri">Confirmar fechamento</button></form>
          </div>
        <?php endif; ?>
      </div>
      <?php if (in_array($l['etapa'], ['fechado', 'viagem', 'pos'], true)) cartao_documentos_lead($l); ?>
      <?php if ($l['etapa'] === 'pos') cartao_pesquisa_lead($l); ?>
      <?php cartao_propostas_lead($l); ?>
      <div class="cartao"><h2>Registrar contato ou nota</h2>
        <form method="post" action="./?p=acao&a=atividade"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
          <div class="linha"><div><label>Tipo</label><select name="tipo"><?= select_opcoes(TIPOS_ATIVIDADE, 'nota') ?></select></div></div>
          <label>O que aconteceu</label><textarea name="texto" required placeholder="Ex.: Liguei, a família prefere hotel com piscina aquecida. Enviar opções até sexta."></textarea>
          <p style="margin:10px 0 0"><button class="btn btn-pri">Salvar</button></p>
        </form>
        <details class="modelos" style="margin-top:14px"><summary>Mensagens prontas de WhatsApp</summary>
          <div class="pilha" style="margin-top:10px">
          <?php foreach (modelos_whats($cli, $l) as $rot => $txt): $z = link_whats($l['telefone'], $txt); ?>
            <div><b style="font-size:14px"><?= h($rot) ?></b><p class="mudo" style="margin:2px 0 6px"><?= h($txt) ?></p><?php if ($z): ?><a class="btn btn-pq btn-zap" href="<?= h($z) ?>" target="_blank" rel="noopener">Abrir no WhatsApp</a><?php endif; ?></div>
          <?php endforeach; if (!link_whats($l['telefone'])): ?><p class="mudo">Cadastre um telefone com DDD no cliente para usar os atalhos.</p><?php endif; ?>
          </div>
        </details>
      </div>
      <div class="cartao"><h2>Histórico</h2>
        <ul class="tempo"><?php foreach ($ativ as $a): ?>
          <li><span class="mudo"><?= data_br($a['criado_em'], true) ?> · <?= h(TIPOS_ATIVIDADE[$a['tipo']] ?? ($a['tipo'] === 'etapa' ? 'Etapa' : $a['tipo'])) ?><?= $a['usuario'] ? ' · ' . h(primeiro_nome($a['usuario'])) : '' ?></span><p><?= h($a['texto']) ?></p></li>
        <?php endforeach; ?></ul>
        <?php if (!$ativ): ?><p class="vazio">Sem registros ainda.</p><?php endif; ?>
      </div>
    </div>
    <div>
      <?php if (pode_financeiro() && in_array($l['etapa'], ['aprovada', 'fechado', 'viagem', 'pos'], true)): $vd = um('SELECT id FROM vendas WHERE lead_id = ?', [$l['id']]); ?>
      <div class="cartao"><h2>Financeiro</h2>
        <?php if ($vd): $rv = resumo_venda($vd['id']); ?><p style="margin:0 0 10px">Viagem <b><?= reais($rv['total_cliente']) ?: 'R$ 0' ?></b> · receita da agência <b><?= reais($rv['receita']) ?: 'R$ 0' ?></b> (<?= pct($rv['margem']) ?>)<br>
          <span class="mudo">Recebido <?= reais($rv['recebido']) ?: 'R$ 0' ?> de <?= reais($rv['a_receber_cliente']) ?: 'R$ 0' ?><?= $rv['proxima'] ? ' · próxima parcela em ' . data_br($rv['proxima']) : '' ?></span></p>
          <a class="btn btn-pri btn-pq" href="./?p=venda&id=<?= $vd['id'] ?>">Abrir financeiro da venda</a>
        <?php else: ?><form method="post" action="./?p=acao&a=fin_abrir"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><button class="btn btn-pri btn-pq">Abrir financeiro da venda</button></form><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="cartao"><h2>Tarefas</h2>
        <?php foreach ($tarefas as $t) linha_tarefa($t); if (!$tarefas): ?><p class="vazio">Nenhuma tarefa.</p><?php endif; ?>
        <form method="post" action="./?p=acao&a=nova_tarefa" style="margin-top:10px"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>"><input type="hidden" name="cliente_id" value="<?= $l['cid'] ?>">
          <div class="linha"><div><label>Nova tarefa</label><input name="titulo" required placeholder="Ex.: Enviar opções de hotel"></div><div><label>Para</label><input type="date" name="vence_em" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required></div></div>
          <p style="margin:10px 0 0"><button class="btn">Adicionar tarefa</button></p>
        </form>
      </div>
      <div class="cartao"><h2>Detalhes da viagem</h2>
        <form method="post" action="./?p=acao&a=salvar_lead"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $l['id'] ?>">
          <label>Destino</label><input name="destino" value="<?= h($l['destino']) ?>">
          <div class="linha"><div><label>Quando</label><input name="periodo" value="<?= h($l['periodo']) ?>"></div><div><label>Duração</label><input name="duracao" value="<?= h($l['duracao']) ?>"></div></div>
          <div class="linha"><div><label>Adultos</label><input name="adultos" type="number" min="0" value="<?= h($l['adultos']) ?>"></div><div><label>Crianças (idades)</label><input name="criancas" value="<?= h($l['criancas']) ?>"></div></div>
          <div class="linha"><div><label>Ocasião</label><input name="ocasiao" value="<?= h($l['ocasiao']) ?>"></div><div><label>Orçamento indicado</label><input name="orcamento" value="<?= h($l['orcamento']) ?>"></div></div>
          <div class="linha"><div><label>Valor estimado da venda (R$)</label><input name="valor_estimado" inputmode="numeric" value="<?= $l['valor_estimado'] !== null ? h(number_format((float)$l['valor_estimado'], 0, ',', '.')) : '' ?>"></div>
            <div><label>Origem</label><select name="origem"><?= select_opcoes(ORIGENS, $l['origem'], 'Não informada') ?></select></div></div>
          <div class="linha"><div><label>Embarque</label><input type="date" name="data_embarque" value="<?= h($l['data_embarque']) ?>"></div><div><label>Retorno</label><input type="date" name="data_retorno" value="<?= h($l['data_retorno']) ?>"></div></div>
          <label>Responsável</label><select name="responsavel_id"><?= select_opcoes(array_column($equipe, 'nome', 'id'), $l['responsavel_id'], 'Ninguém') ?></select>
          <label>Detalhes e preferências</label><textarea name="detalhes" rows="5"><?= h($l['detalhes']) ?></textarea>
          <p style="margin:12px 0 0;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><button class="btn btn-pri">Salvar detalhes</button></p>
        </form>
        <form method="post" action="./?p=acao&a=excluir_lead" onsubmit="return confirm('Excluir este lead e todo o histórico dele?')" style="margin-top:14px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><button class="btn btn-pq btn-perigo">Excluir lead</button></form>
      </div>
    </div>
  </div>
<?php rodape("var s=document.getElementById('sel-etapa'),m=document.getElementById('motivo');s.addEventListener('change',function(){m.hidden=s.value!=='perdido';});");
}

// ================= Novo lead =================
function pagina_novo() {
    $cid = (int)($_GET['cliente'] ?? 0);
    $cli = $cid ? um('SELECT * FROM clientes WHERE id = ?', [$cid]) : null;
    topo('Novo lead', 'funil'); ?>
  <div class="cab"><h1>Novo lead</h1></div>
  <form method="post" action="./?p=acao&a=novo_lead" class="grade2"><?= csrf_campo() ?>
    <div class="cartao"><h2>Cliente</h2>
      <?php if ($cli): ?><input type="hidden" name="cliente_id" value="<?= $cli['id'] ?>"><p><b><?= h($cli['nome']) ?></b><br><span class="mudo"><?= h($cli['telefone']) ?> <?= h($cli['email']) ?></span></p>
      <?php else: ?>
        <p class="mudo" style="margin-top:0">Se o cliente já existir (mesmo e-mail ou telefone), o lead é ligado ao cadastro dele.</p>
        <label>Nome</label><input name="nome" required>
        <div class="linha"><div><label>WhatsApp</label><input name="telefone" type="tel" placeholder="(11) 90000-0000"></div><div><label>E-mail</label><input name="email" type="email"></div></div>
        <label>Cidade</label><input name="cidade">
      <?php endif; ?>
      <label>Origem do lead</label><select name="origem"><?= select_opcoes(ORIGENS, 'WhatsApp') ?></select>
    </div>
    <div class="cartao"><h2>Viagem</h2>
      <label>Destino</label><input name="destino" placeholder="Ex.: Orlando, ainda não sabe">
      <div class="linha"><div><label>Quando</label><input name="periodo" placeholder="Ex.: férias de julho/2027"></div><div><label>Duração</label><input name="duracao"></div></div>
      <div class="linha"><div><label>Adultos</label><input name="adultos" type="number" min="0" value="2"></div><div><label>Crianças (idades)</label><input name="criancas" placeholder="Ex.: 7 e 13 anos"></div></div>
      <label>Valor estimado (R$)</label><input name="valor_estimado" inputmode="numeric">
      <label>Detalhes</label><textarea name="detalhes"></textarea>
      <p style="margin:12px 0 0"><button class="btn btn-pri">Criar lead</button></p>
    </div>
  </form>
<?php rodape(); }

// ================= Tarefas =================
function pagina_tarefas() {
    $filtro = $_GET['f'] ?? 'pendentes';
    $w = $filtro === 'feitas' ? 't.feita = 1' : 't.feita = 0';
    $ord = $filtro === 'feitas' ? 't.feita_em DESC' : 't.vence_em';
    $lista = todos("SELECT t.*, c.nome cliente, l.destino FROM tarefas t LEFT JOIN clientes c ON c.id = t.cliente_id LEFT JOIN leads l ON l.id = t.lead_id WHERE $w ORDER BY $ord LIMIT 200");
    topo('Tarefas', 'tarefas'); ?>
  <div class="cab"><h1>Tarefas</h1><div style="display:flex;gap:8px"><a class="btn <?= $filtro !== 'feitas' ? 'btn-pri' : '' ?>" href="./?p=tarefas">Pendentes</a><a class="btn <?= $filtro === 'feitas' ? 'btn-pri' : '' ?>" href="./?p=tarefas&f=feitas">Concluídas</a></div></div>
  <div class="cartao"><?php foreach ($lista as $t) linha_tarefa($t, true); if (!$lista): ?><p class="vazio">Nada por aqui.</p><?php endif; ?></div>
<?php rodape(); }

// ================= Equipe e conta =================
function pagina_usuarios() {
    $lista = todos('SELECT * FROM usuarios ORDER BY ativo DESC, nome');
    $reg = todos('SELECT r.*, u.nome FROM registro r LEFT JOIN usuarios u ON u.id = r.usuario_id ORDER BY r.id DESC LIMIT 30');
    topo('Equipe', 'usuarios'); ?>
  <div class="cab"><h1>Equipe</h1></div>
  <div class="grade2">
    <div class="cartao tabela"><h2>Usuários</h2><table><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th></th></tr></thead><tbody>
      <?php foreach ($lista as $x): ?><tr><td><?= h($x['nome']) ?><?= $x['ativo'] ? '' : ' <span class="tag perdido">inativo</span>' ?></td><td><?= h($x['email']) ?></td><td><?= $x['papel'] === 'admin' ? 'Administrador' : 'Usuário' ?><?= $x['papel'] !== 'admin' && $x['acesso_financeiro'] ? '<br><span class="mudo">com Financeiro</span>' : '' ?></td>
        <td><?php if ($x['papel'] !== 'admin'): ?><form method="post" action="./?p=acao&a=alternar_financeiro" style="display:inline-block"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="btn btn-pq"><?= $x['acesso_financeiro'] ? 'Tirar Financeiro' : 'Dar Financeiro' ?></button></form><?php endif; ?>
        <?php if ($x['id'] != usuario()['id']): ?><details style="display:inline-block"><summary class="btn btn-pq">Redefinir senha</summary><form method="post" action="./?p=acao&a=redefinir_senha" style="margin-top:6px;display:flex;gap:6px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $x['id'] ?>"><input type="password" name="senha" minlength="10" required placeholder="Senha provisória" style="width:170px"><button class="btn btn-pq btn-pri">OK</button></form></details>
        <form method="post" action="./?p=acao&a=alternar_usuario" style="display:inline-block"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="btn btn-pq"><?= $x['ativo'] ? 'Desativar' : 'Reativar' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table>
      <h2 style="margin-top:22px">Atividade recente no CRM</h2>
      <table><tbody><?php foreach ($reg as $r): ?><tr><td class="mudo"><?= data_br($r['criado_em'], true) ?></td><td><?= h($r['nome'] ?: '—') ?></td><td><?= h($r['acao']) ?> <span class="mudo"><?= h($r['detalhe']) ?></span></td></tr><?php endforeach; ?></tbody></table>
    </div>
    <div class="cartao"><h2>Adicionar pessoa</h2>
      <form method="post" action="./?p=acao&a=novo_usuario"><?= csrf_campo() ?>
        <label>Nome</label><input name="nome" required><label>E-mail</label><input name="email" type="email" required>
        <label>Senha provisória (mínimo 10 caracteres)</label><input name="senha" type="password" minlength="10" required>
        <label>Perfil</label><select name="papel"><option value="usuario">Usuário</option><option value="admin">Administrador</option></select>
        <label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="financeiro" value="1" style="width:auto">Acesso ao Financeiro</label>
        <p style="margin:12px 0 0"><button class="btn btn-pri">Adicionar</button></p>
      </form>
      <hr style="border:none;border-top:1px solid var(--linha);margin:18px 0">
      <h2>Configurações</h2>
      <form method="post" action="./?p=acao&a=salvar_config"><?= csrf_campo() ?>
        <label>Tamanho máximo por documento da viagem (MB)</label>
        <input name="doc_max_mb" type="number" min="1" max="50" value="<?= h(cfg('doc_max_mb')) ?>">
        <label>Retenção do link do cliente após a volta (dias)</label>
        <input name="doc_retencao_dias" type="number" min="1" max="365" value="<?= h(cfg('doc_retencao_dias')) ?>">
        <p class="mudo" style="margin:6px 0 0">Vale para o portal de documentos e para a pesquisa de satisfação.</p>
        <p style="margin:12px 0 0"><button class="btn btn-pri">Salvar configurações</button></p>
      </form>

      <hr style="border:none;border-top:1px solid var(--linha);margin:18px 0">
      <h2>Inteligência (Claude)</h2>
      <p class="mudo" style="margin-top:-6px">Permite o VV360 gerar o roteiro automaticamente, via API do Claude. A chave é guardada cifrada e nunca é exibida de volta.</p>
      <form method="post" action="./?p=acao&a=salvar_ia"><?= csrf_campo() ?>
        <label>Chave da API do Claude</label>
        <input name="ia_chave" type="password" autocomplete="off" placeholder="<?= ia_tem_chave() ? 'Chave salva: ' . h(mascarar(ia_chave(), 4)) . ' — deixe em branco para manter' : 'cole aqui a chave (sk-ant-...)' ?>">
        <label>Modelo para gerar o roteiro</label>
        <select name="ia_modelo"><?= select_opcoes(MODELOS_IA, ia_modelo_roteiro()) ?></select>
        <label>Limite de gerações automáticas por dia</label>
        <input name="ia_teto_dia" type="number" min="1" max="500" value="<?= h(ia_teto_dia()) ?>">
        <p class="mudo" style="margin:6px 0 0">Protege contra uso acidental. Hoje: <?= ia_uso_hoje() ?> de <?= ia_teto_dia() ?> usadas.</p>
        <p style="margin:12px 0 0"><button class="btn btn-pri">Salvar chave e preferências</button>
        <?php if (ia_tem_chave()): ?> <a class="btn" href="./?p=acao&a=ia_testar" onclick="return confirm('Fazer uma chamada de teste à API do Claude? Consome alguns centavos.')">Testar conexão</a>
        <a class="btn btn-perigo" href="./?p=acao&a=ia_remover" onclick="return confirm('Remover a chave da API?')">Remover chave</a><?php endif; ?></p>
      </form>
      <p class="mudo">Peça para a pessoa trocar a senha provisória em "Conta" no primeiro acesso.</p>
    </div>
  </div>
<?php rodape(); }

function pagina_conta() {
    $u = usuario();
    topo('Minha conta', ''); ?>
  <div class="cab"><h1>Minha conta</h1></div>
  <div class="cartao" style="max-width:480px"><p><b><?= h($u['nome']) ?></b><br><span class="mudo"><?= h($u['email']) ?></span></p>
    <form method="post" action="./?p=acao&a=trocar_senha"><?= csrf_campo() ?>
      <label>Senha atual</label><input name="atual" type="password" required autocomplete="current-password">
      <label>Nova senha (mínimo 10 caracteres)</label><input name="nova" type="password" minlength="10" required autocomplete="new-password">
      <p style="margin:12px 0 0"><button class="btn btn-pri">Trocar senha</button></p>
    </form></div>
<?php rodape(); }

// ================= Exportação =================
function exportar_csv() {
    registrar('exportou planilha de leads');
    $linhas = todos("SELECT l.codigo, c.nome, c.telefone, c.email, c.cidade, l.destino, l.periodo, l.duracao, l.adultos, l.criancas, l.origem, l.etapa, l.valor_estimado, l.motivo_perda, l.data_embarque, l.data_retorno, l.criado_em, l.atualizado_em
                     FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.etapa <> \'entrega\' ORDER BY l.criado_em DESC");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="crm-leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Código', 'Cliente', 'Telefone', 'E-mail', 'Cidade', 'Destino', 'Quando', 'Duração', 'Adultos', 'Crianças', 'Origem', 'Etapa', 'Valor estimado', 'Motivo da perda', 'Embarque', 'Retorno', 'Criado em', 'Atualizado em'], ';');
    foreach ($linhas as $r) { $r['etapa'] = ETAPAS[$r['etapa']] ?? $r['etapa']; fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r), ';'); }
    exit;
}

function exportar_cliente($id) {
    $c = um('SELECT * FROM clientes WHERE id = ?', [$id]);
    if (!$c) redirecionar('./?p=clientes');
    registrar('exportou dados do cliente', '#' . $id);
    foreach (['cpf_enc' => 'cpf', 'rg_enc' => 'rg', 'passaporte_enc' => 'passaporte'] as $k => $n) { $c[$n] = decripto($c[$k]); unset($c[$k]); }
    unset($c['cpf_hash']);
    $vj = todos('SELECT nome, parentesco, nascimento, observacoes, cpf_enc, rg_enc, passaporte_enc, passaporte_emissao, passaporte_validade FROM viajantes WHERE cliente_id = ?', [$id]);
    foreach ($vj as &$v) foreach (['cpf_enc' => 'cpf', 'rg_enc' => 'rg', 'passaporte_enc' => 'passaporte'] as $k => $n) { $v[$n] = decripto($v[$k]); unset($v[$k]); }
    unset($v);
    $dados = ['cliente' => $c, 'viagens' => todos('SELECT destino, ida, volta, passageiros, hospedagem, fornecedor, valor, observacoes FROM viagens WHERE cliente_id = ?', [$id]), 'viajantes' => $vj,
              'leads' => todos("SELECT * FROM leads WHERE cliente_id = ? AND etapa <> 'entrega'", [$id]),
              'historico' => todos('SELECT tipo, texto, criado_em FROM atividades WHERE cliente_id = ? ORDER BY criado_em', [$id]),
              'mensagens_site' => todos('SELECT tipo, texto, viagem, status, criado_em FROM mensagens WHERE cliente_id = ?', [$id])];
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="dados-cliente-' . $id . '.json"');
    echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); exit;
}

// ================= Mensagens do site (elogios, dúvidas e reclamações) =================
function nome_sugerido($n) {
    $p = preg_split('/\s+/u', trim($n));
    return count($p) > 1 ? $p[0] . ' ' . mb_substr(end($p), 0, 1) . '.' : $p[0];
}
function pagina_mensagens() {
    $f = $_GET['f'] ?? 'novas';
    $abas = ['novas' => 'Novas', 'todas' => 'Todas', 'elogio' => 'Elogios', 'duvida' => 'Dúvidas', 'reclamacao' => 'Reclamações'];
    if (!isset($abas[$f])) $f = 'novas';
    $cont = ['todas' => (int)valor('SELECT COUNT(*) FROM mensagens'), 'novas' => (int)valor("SELECT COUNT(*) FROM mensagens WHERE status = 'nova'")];
    foreach (TIPOS_MENSAGEM as $k => $v) $cont[$k] = (int)valor('SELECT COUNT(*) FROM mensagens WHERE tipo = ?', [$k]);
    $w = $f === 'novas' ? "WHERE m.status = 'nova'" : (isset(TIPOS_MENSAGEM[$f]) ? 'WHERE m.tipo = ?' : '');
    $lista = todos("SELECT m.*, c.nome cliente FROM mensagens m LEFT JOIN clientes c ON c.id = m.cliente_id $w ORDER BY m.criado_em DESC LIMIT 200", isset(TIPOS_MENSAGEM[$f]) ? [$f] : []);
    $admin = usuario()['papel'] === 'admin';
    topo('Mensagens', 'mensagens'); ?>
  <div class="cab"><h1>Mensagens do site</h1><a class="btn" href="../#contato" target="_blank" rel="noopener">Ver formulário no site</a></div>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
    <?php foreach ($abas as $k => $v): ?><a class="btn <?= $f === $k ? 'btn-pri' : '' ?>" href="./?p=mensagens&f=<?= $k ?>"><?= $v ?> (<?= $cont[$k] ?>)</a><?php endforeach; ?>
  </div>
  <?php if (!$lista): ?><div class="cartao"><p class="vazio">Nenhuma mensagem aqui.</p></div><?php endif; ?>
  <?php foreach ($lista as $m):
    $z = link_whats($m['telefone']);
    $cor = ['elogio' => 'fechado', 'duvida' => 'novo', 'reclamacao' => 'perdido'][$m['tipo']]; ?>
    <div class="cartao" style="<?= $m['status'] === 'nova' ? 'border-left:4px solid var(--sora)' : '' ?>">
      <div style="display:flex;flex-wrap:wrap;gap:6px 12px;align-items:center;font-size:14px;margin-bottom:8px">
        <span class="tag <?= $cor ?>"><?= h(TIPOS_MENSAGEM[$m['tipo']]) ?></span>
        <b><?= h($m['nome']) ?></b>
        <?php if ($m['email']): ?><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a><?php elseif ($z): ?><a href="<?= h($z) ?>" target="_blank" rel="noopener"><?= h($m['telefone']) ?></a><?php else: ?><?= h($m['contato']) ?><?php endif; ?>
        <?php if ($m['viagem']): ?><span class="mudo">· <?= h($m['viagem']) ?></span><?php endif; ?>
        <span class="mudo">· <?= data_br($m['criado_em'], true) ?> · <?= h(STATUS_MENSAGEM[$m['status']] ?? $m['status']) ?></span>
        <?php if ($m['cliente_id']): ?><a href="./?p=cliente&id=<?= $m['cliente_id'] ?>">Ver cliente</a><?php endif; ?>
        <?php if ($m['lead_id']): ?><a href="./?p=lead&id=<?= $m['lead_id'] ?>">Ver lead</a><?php endif; ?>
      </div>
      <p style="white-space:pre-wrap;margin:0 0 12px"><?= h($m['texto']) ?></p>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php $acao = function ($a, $rot, $classe = '', $conf = '') use ($m) { ?>
          <form method="post" action="./?p=acao&a=<?= $a ?>" <?= $conf ? 'onsubmit="return confirm(\'' . $conf . '\')"' : '' ?>><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="voltar" value="<?= h($_SERVER['REQUEST_URI']) ?>"><button class="btn btn-pq <?= $classe ?>"><?= $rot ?></button></form>
        <?php };
        if ($m['status'] === 'nova') $acao('msg_respondida', 'Marcar como respondida');
        if ($m['status'] === 'respondida') $acao('msg_reabrir', 'Reabrir');
        if ($z && $m['tipo'] !== 'elogio'): ?><a class="btn btn-pq btn-zap" href="<?= h($z) ?>" target="_blank" rel="noopener">Responder no WhatsApp</a><?php endif;
        if (!$m['lead_id'] && $m['tipo'] === 'duvida') $acao('msg_lead', 'Transformar em lead', 'btn-pri');
        if ($m['status'] === 'publicado' && $admin) $acao('msg_despublicar', 'Tirar do site');
        if ($m['tipo'] === 'elogio' && $m['autoriza_publicar'] && !in_array($m['status'], ['publicado', 'nao_publicado']) && $admin) $acao('msg_nao_publicar', 'Não publicar');
        $acao('msg_excluir', 'Excluir', 'btn-perigo', 'Excluir esta mensagem definitivamente?'); ?>
      </div>
      <?php if ($m['tipo'] === 'elogio'): ?>
        <?php if (!$m['autoriza_publicar']): ?><p class="mudo" style="margin:10px 0 0">O cliente não autorizou a publicação deste elogio.</p>
        <?php elseif ($m['status'] !== 'publicado' && $admin): ?>
          <details style="margin-top:12px;border-top:1px dashed var(--linha);padding-top:10px"><summary style="cursor:pointer;font-weight:600;color:var(--sora)">Aprovar e publicar no site</summary>
            <form method="post" action="./?p=acao&a=msg_publicar"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="voltar" value="<?= h($_SERVER['REQUEST_URI']) ?>">
              <div class="linha"><div><label>Nome como vai aparecer</label><input name="nome_publico" value="<?= h(nome_sugerido($m['nome'])) ?>" required></div><div><label>Viagem</label><input name="viagem" value="<?= h($m['viagem']) ?>" placeholder="Ex.: Japão em família"></div></div>
              <label>Texto publicado (corrija só erros de digitação)</label><textarea name="texto_publico" required><?= h($m['texto']) ?></textarea>
              <p style="margin:10px 0 0"><button class="btn btn-pri">Publicar no site</button></p>
            </form></details>
        <?php elseif ($m['status'] !== 'publicado'): ?><p class="mudo" style="margin:10px 0 0">A publicação de elogios é feita por um administrador.</p><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach;
  rodape(); }
