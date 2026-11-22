<?php
// Vanessa Viagens e Turismo · CRM — Módulo de Grupos / Excursões (Fase 1 · núcleo)
// Grupo (destino, vagas, mínimo, valor, roteiro), painel de viabilidade e participantes
// na jornada Interesse → Confirmando → Confirmado.

function grupo_ou_sai($id) {
    $g = um('SELECT * FROM grupos WHERE id = ?', [(int)$id]);
    if (!$g) redirecionar('./?p=grupos', '!Grupo não encontrado.');
    return $g;
}

function tag_status_grupo($st) {
    $cls = ['planejamento' => '', 'aberto' => 'proposta', 'confirmado' => 'fechado', 'fechado' => 'viagem', 'cancelado' => 'perdido'][$st] ?? '';
    return '<span class="tag ' . $cls . '">' . h(GRUPO_STATUS[$st] ?? $st) . '</span>';
}

/** Selo de viabilidade a partir do resumo. */
function selo_viabilidade($r) {
    if ($r['vagas_minimo'] <= 0) return ['—', 'mudo', 'Defina o mínimo de vagas para avaliar a viabilidade.'];
    if ($r['viavel']) return ['✓ Viável', 'ok', 'O mínimo de ' . $r['vagas_minimo'] . ' já está confirmado.'];
    if ($r['perto']) return ['Perto do mínimo', 'atencao', 'Faltam ' . $r['falta_minimo'] . ' confirmados; há ' . $r['confirmando'] . ' em confirmação.'];
    return ['Faltam ' . $r['falta_minimo'], 'erro', 'Ainda faltam ' . $r['falta_minimo'] . ' confirmados para atingir o mínimo.'];
}

// ===================== LISTA DE GRUPOS =====================
function pagina_grupos() {
    exigir_login();
    $grupos = todos("SELECT * FROM grupos WHERE status <> 'cancelado' OR ? ORDER BY
        CASE status WHEN 'aberto' THEN 0 WHEN 'confirmado' THEN 1 WHEN 'planejamento' THEN 2 ELSE 3 END,
        COALESCE(data_inicio,'9999'), id DESC", [isset($_GET['todos']) ? 1 : 0]);
    $fin = pode_financeiro();
    topo('Grupos', 'grupos'); ?>
  <div class="cab">
    <div><h1>Grupos e excursões</h1><p class="mudo" style="margin:4px 0 0">Monte a excursão, acompanhe a viabilidade e conduza cada participante até a confirmação.</p></div>
    <form method="post" action="./?p=acao&a=grupo_novo"><?= csrf_campo() ?><button class="btn btn-pri">+ Novo grupo</button></form>
  </div>
  <?php if (!$grupos): ?>
    <div class="cartao"><p class="vazio" style="padding:4px 0">Nenhum grupo ainda. Crie o primeiro: defina destino, datas, o número mínimo de vagas para a excursão sair e o valor por pessoa. O painel mostra, em tempo real, quantos faltam para o grupo ser viável.</p></div>
  <?php else: ?>
    <div class="grade3" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
    <?php foreach ($grupos as $g): $r = grupo_resumo($g); [$txt, $cor, $dica] = selo_viabilidade($r); ?>
      <div class="cartao" style="margin:0">
        <div style="display:flex;justify-content:space-between;gap:8px;align-items:start">
          <div><a href="./?p=grupo&id=<?= $g['id'] ?>" style="text-decoration:none"><b style="font-family:var(--serif);font-size:18px;color:var(--ai)"><?= h($g['titulo']) ?></b></a>
            <div class="mudo" style="margin-top:2px"><?= h($g['codigo']) ?><?= $g['destino'] ? ' · ' . h($g['destino']) : '' ?></div></div>
          <?= tag_status_grupo($g['status']) ?>
        </div>
        <div class="mudo" style="margin:8px 0 10px">
          <?php if ($g['data_inicio']): ?><?= data_br($g['data_inicio']) ?><?= $g['data_fim'] ? ' a ' . data_br($g['data_fim']) : '' ?><?php else: ?><?= h($g['periodo'] ?: 'Datas a definir') ?><?php endif; ?>
          <?= $g['valor'] > 0 ? ' · ' . moeda_fmt($g['valor'], $g['moeda']) . '/pessoa' : '' ?>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <div style="flex:1;height:8px;background:var(--linha);border-radius:5px;overflow:hidden"><div style="height:100%;width:<?= $r['ocup_pct'] ?>%;background:var(--sora)"></div></div>
          <span class="mudo" style="white-space:nowrap"><b style="color:var(--ai)"><?= $r['confirmados'] ?></b>/<?= $r['vagas_total'] ?: '∞' ?> vagas</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
          <span class="aviso <?= $cor ?>" style="margin:0;padding:4px 10px;font-size:13px;font-weight:600"><?= h($txt) ?></span>
          <span class="mudo" style="font-size:12.5px"><?= $r['confirmando'] ?> confirmando · <?= $r['interesse'] ?> interesse</span>
        </div>
        <?php if ($fin && $r['receita_confirmada'] > 0): ?><div class="mudo" style="margin-top:8px;font-size:12.5px">Receita confirmada: <b style="color:var(--ok)"><?= moeda_fmt($r['receita_confirmada'], $r['moeda']) ?></b></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <p class="mudo" style="margin-top:14px"><?= isset($_GET['todos']) ? '<a href="./?p=grupos">Ocultar cancelados</a>' : '<a href="./?p=grupos&todos=1">Ver também cancelados</a>' ?></p>
<?php rodape(); }

// ===================== FICHA DO GRUPO =====================
function pagina_grupo($id) {
    exigir_login();
    $g = grupo_ou_sai($id);
    $r = grupo_resumo($g);
    [$txt, $cor, $dica] = selo_viabilidade($r);
    $fin = pode_financeiro();
    $parts = todos('SELECT * FROM grupo_participantes WHERE grupo_id = ? ORDER BY criado_em DESC', [$id]);
    $porEtapa = ['interesse' => [], 'confirmando' => [], 'confirmado' => [], 'desistiu' => []];
    foreach ($parts as $p) $porEtapa[$p['etapa']][] = $p;
    topo($g['codigo'] . ' · ' . $g['titulo'], 'grupos'); ?>
  <div class="cab"><div>
    <p class="mudo" style="margin:0"><a href="./?p=grupos">Grupos</a> › <?= h($g['codigo']) ?></p>
    <h1><?= h($g['titulo']) ?> <?= tag_status_grupo($g['status']) ?></h1>
    <p class="mudo" style="margin:4px 0 0"><?= $g['destino'] ? h($g['destino']) . ' · ' : '' ?><?php if ($g['data_inicio']): ?><?= data_br($g['data_inicio']) ?><?= $g['data_fim'] ? ' a ' . data_br($g['data_fim']) : '' ?><?php else: ?><?= h($g['periodo'] ?: 'Datas a definir') ?><?php endif; ?><?= $g['valor'] > 0 ? ' · ' . moeda_fmt($g['valor'], $g['moeda']) . ' por pessoa' : '' ?></p>
  </div></div>

  <!-- Painel de viabilidade -->
  <div class="metricas">
    <div class="metrica"><b><?= $r['confirmados'] ?><span style="font-size:16px;color:var(--t2)">/<?= $r['vagas_total'] ?: '∞' ?></span></b><span>Vagas confirmadas</span></div>
    <div class="metrica"><b><?= $r['confirmando'] ?></b><span>Em confirmação</span></div>
    <div class="metrica"><b><?= $r['interesse'] ?></b><span>Interessados</span></div>
    <div class="metrica"><b><?= $r['vagas_minimo'] ?: '—' ?></b><span>Mínimo p/ sair</span></div>
    <?php if ($fin): ?><div class="metrica"><b style="font-size:22px"><?= moeda_fmt($r['receita_confirmada'], $r['moeda']) ?: 'R$ 0' ?></b><span>Receita confirmada</span></div>
    <div class="metrica"><b style="font-size:22px;color:var(--t2)"><?= moeda_fmt($r['receita_potencial'], $r['moeda']) ?: 'R$ 0' ?></b><span>Potencial (+ confirmando)</span></div><?php endif; ?>
  </div>
  <div class="aviso <?= $cor ?>" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
    <span><b><?= h($txt) ?></b> — <?= h($dica) ?></span>
    <span style="font-size:13px"><?= $r['lotado'] ? 'Grupo lotado.' : ($r['vagas_total'] ? $r['vagas_restantes'] . ' vaga(s) restante(s).' : '') ?></span>
  </div>

  <div class="grade2">
    <!-- Participantes por etapa -->
    <div>
      <div class="cartao">
        <div style="display:flex;justify-content:space-between;align-items:center"><h2 style="margin:0">Participantes</h2>
          <span class="mudo"><?= count($parts) ?> registro(s) · <?= array_sum(array_column($parts,'pessoas')) ?> pessoa(s)</span></div>
        <?php foreach (['interesse','confirmando','confirmado','desistiu'] as $et): $lst = $porEtapa[$et]; if ($et === 'desistiu' && !$lst) continue; ?>
          <div style="margin-top:14px">
            <div class="mudo" style="font-weight:600;text-transform:uppercase;letter-spacing:.05em;font-size:12px;color:var(--t2);border-bottom:1px solid var(--linha);padding-bottom:5px"><?= h(GRUPO_ETAPAS[$et]) ?> · <?= count($lst) ?></div>
            <?php if (!$lst): ?><p class="vazio" style="padding:8px 0;font-size:13.5px">Ninguém nesta etapa.</p><?php endif; ?>
            <?php foreach ($lst as $p): ?>
              <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--linha)">
                <div>
                  <b><?= h($p['nome']) ?></b><?= $p['pessoas'] > 1 ? ' <span class="tag">' . (int)$p['pessoas'] . ' pessoas</span>' : '' ?>
                  <div class="mudo" style="font-size:13px"><?= $p['contato'] ? h($p['contato']) : 'sem contato' ?><?= $p['obs'] ? ' · ' . h($p['obs']) : '' ?></div>
                </div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end">
                  <?php
                    $fluxo = ['interesse' => ['confirmando', '→ Confirmando'], 'confirmando' => ['confirmado', '✓ Confirmar'], 'confirmado' => ['confirmando', '↩ Reabrir']];
                    if (isset($fluxo[$et])): [$dest, $rot] = $fluxo[$et]; ?>
                    <form method="post" action="./?p=acao&a=grupo_part_etapa"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="grupo_id" value="<?= $g['id'] ?>"><input type="hidden" name="etapa" value="<?= $dest ?>"><button class="btn btn-pq <?= $dest === 'confirmado' ? 'btn-pri' : '' ?>"><?= $rot ?></button></form>
                  <?php endif; ?>
                  <?php if ($et !== 'desistiu'): ?><form method="post" action="./?p=acao&a=grupo_part_etapa"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="grupo_id" value="<?= $g['id'] ?>"><input type="hidden" name="etapa" value="desistiu"><button class="btn btn-pq btn-perigo" title="Marcar como desistência">✕</button></form>
                  <?php else: ?><form method="post" action="./?p=acao&a=grupo_part_etapa"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="grupo_id" value="<?= $g['id'] ?>"><input type="hidden" name="etapa" value="interesse"><button class="btn btn-pq">↩</button></form><?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

        <!-- Adicionar participante -->
        <form method="post" action="./?p=acao&a=grupo_part_add" style="margin-top:16px;border-top:2px solid var(--linha);padding-top:12px">
          <?= csrf_campo() ?><input type="hidden" name="grupo_id" value="<?= $g['id'] ?>">
          <div class="linha">
            <div style="grid-column:span 2"><label>Nome do participante</label><input name="nome" required placeholder="Ex.: Maria Santos"></div>
            <div><label>Pessoas</label><input type="number" name="pessoas" value="1" min="1" max="99"></div>
          </div>
          <div class="linha">
            <div><label>Contato (WhatsApp/e-mail)</label><input name="contato" placeholder="opcional"></div>
            <div><label>Entra como</label><select name="etapa"><?php foreach (GRUPO_ETAPAS as $k => $v) if ($k !== 'desistiu') echo '<option value="' . $k . '">' . h($v) . '</option>'; ?></select></div>
          </div>
          <label>Observação</label><input name="obs" placeholder="opcional — ex.: quarto duplo, vai com a irmã">
          <button class="btn btn-pri" style="margin-top:12px">Adicionar participante</button>
        </form>
      </div>
    </div>

    <!-- Dados do grupo -->
    <div>
      <div class="cartao">
        <h2>Dados do grupo</h2>
        <form method="post" action="./?p=acao&a=grupo_salvar">
          <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $g['id'] ?>">
          <label>Título da excursão</label><input name="titulo" required value="<?= h($g['titulo']) ?>" placeholder="Ex.: Grupo Patagônia Julho">
          <label>Destino</label><input name="destino" value="<?= h($g['destino']) ?>" placeholder="Ex.: El Calafate e Ushuaia">
          <div class="linha">
            <div><label>Início</label><input type="date" name="data_inicio" value="<?= h($g['data_inicio']) ?>"></div>
            <div><label>Fim</label><input type="date" name="data_fim" value="<?= h($g['data_fim']) ?>"></div>
          </div>
          <label>Período (texto, quando as datas ainda forem flexíveis)</label><input name="periodo" value="<?= h($g['periodo']) ?>" placeholder="Ex.: Julho/2027, datas a confirmar">
          <div class="linha">
            <div><label>Vagas totais</label><input type="number" name="vagas_total" min="0" max="999" value="<?= (int)$g['vagas_total'] ?>"></div>
            <div><label>Mínimo p/ sair</label><input type="number" name="vagas_minimo" min="0" max="999" value="<?= (int)$g['vagas_minimo'] ?>"></div>
          </div>
          <div class="linha">
            <div style="grid-column:span 2"><label>Valor por pessoa</label><input type="number" name="valor" step="0.01" min="0" value="<?= $g['valor'] ? rtrim(rtrim(number_format($g['valor'],2,'.',''),'0'),'.') : '' ?>" placeholder="0,00"></div>
            <div><label>Moeda</label><select name="moeda"><?php foreach (MOEDAS as $k => $v) echo '<option value="' . $k . '"' . ($g['moeda'] === $k ? ' selected' : '') . '>' . h($k) . '</option>'; ?></select></div>
          </div>
          <label>Link do roteiro (opcional)</label><input name="roteiro_url" value="<?= h($g['roteiro_url']) ?>" placeholder="https://...">
          <label>Descrição interna</label><textarea name="descricao" placeholder="Anotações sobre o grupo, fornecedores, condições..."><?= h($g['descricao']) ?></textarea>
          <label>Status</label><select name="status"><?php foreach (GRUPO_STATUS as $k => $v) echo '<option value="' . $k . '"' . ($g['status'] === $k ? ' selected' : '') . '>' . h($v) . '</option>'; ?></select>
          <button class="btn btn-pri" style="margin-top:14px">Salvar dados</button>
        </form>
        <?php if ($g['roteiro_url']): ?><p style="margin-top:12px"><a class="btn btn-pq" href="<?= h($g['roteiro_url']) ?>" target="_blank" rel="noopener">📄 Abrir roteiro</a></p><?php endif; ?>
      </div>
      <div class="cartao">
        <h2 style="font-size:17px">Próximas fases</h2>
        <p class="mudo" style="margin:6px 0 0">A Fase 1 cobre o núcleo: grupo, viabilidade e participantes. Em seguida vêm o <b>hotsite público</b> (vagas ao vivo, "Tenho interesse"), os <b>prompts de flyer e roteiro</b>, e a <b>virada de confirmado em venda</b> no Financeiro.</p>
      </div>
      <form method="post" action="./?p=acao&a=grupo_excluir" onsubmit="return confirm('Excluir este grupo e todos os participantes? Esta ação não pode ser desfeita.')" style="margin-top:4px">
        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn btn-pq btn-perigo">Excluir grupo</button>
      </form>
    </div>
  </div>
<?php rodape(); }

// ===================== AÇÕES =====================
function acao_grupos($a) {
    switch ($a) {
    case 'grupo_novo':
        q('INSERT INTO grupos (codigo, titulo, status, criado_em, atualizado_em) VALUES (?,?,?,?,?)',
          [grupo_proximo_codigo(), 'Novo grupo', 'planejamento', agora(), agora()]);
        $id = (int)db()->lastInsertId();
        registrar('criou grupo', '#' . $id);
        redirecionar('./?p=grupo&id=' . $id, 'Grupo criado. Preencha os dados e comece a lançar os interessados.');

    case 'grupo_salvar':
        $g = grupo_ou_sai($_POST['id'] ?? 0);
        $ini = data_valida($_POST['data_inicio'] ?? ''); $fim = data_valida($_POST['data_fim'] ?? '');
        $status = array_key_exists($_POST['status'] ?? '', GRUPO_STATUS) ? $_POST['status'] : $g['status'];
        $moeda = array_key_exists($_POST['moeda'] ?? '', MOEDAS) ? $_POST['moeda'] : 'BRL';
        q('UPDATE grupos SET titulo = ?, destino = ?, data_inicio = ?, data_fim = ?, periodo = ?, vagas_total = ?, vagas_minimo = ?, valor = ?, moeda = ?, roteiro_url = ?, descricao = ?, status = ?, atualizado_em = ? WHERE id = ?',
          [post('titulo', 120) ?: 'Novo grupo', post('destino', 120), $ini, $fim, post('periodo', 120),
           max(0, (int)($_POST['vagas_total'] ?? 0)), max(0, (int)($_POST['vagas_minimo'] ?? 0)),
           (float)str_replace(',', '.', $_POST['valor'] ?? '0'), $moeda, post('roteiro_url', 300), post('descricao', 3000), $status, agora(), $g['id']]);
        registrar('editou grupo', $g['codigo']);
        redirecionar('./?p=grupo&id=' . $g['id'], 'Dados do grupo salvos.');

    case 'grupo_part_add':
        $g = grupo_ou_sai($_POST['grupo_id'] ?? 0);
        $nome = post('nome', 120);
        if ($nome === '') redirecionar('./?p=grupo&id=' . $g['id'], '!Informe o nome do participante.');
        $etapa = array_key_exists($_POST['etapa'] ?? '', GRUPO_ETAPAS) && $_POST['etapa'] !== 'desistiu' ? $_POST['etapa'] : 'interesse';
        q('INSERT INTO grupo_participantes (grupo_id, nome, contato, pessoas, etapa, obs, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?,?)',
          [$g['id'], $nome, post('contato', 160), max(1, (int)($_POST['pessoas'] ?? 1)), $etapa, post('obs', 300), agora(), agora()]);
        registrar('grupo: novo participante', $g['codigo'] . ' · ' . $nome);
        redirecionar('./?p=grupo&id=' . $g['id'], 'Participante adicionado.');

    case 'grupo_part_etapa':
        $p = um('SELECT * FROM grupo_participantes WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if (!$p) redirecionar('./?p=grupos', '!Participante não encontrado.');
        $etapa = array_key_exists($_POST['etapa'] ?? '', GRUPO_ETAPAS) ? $_POST['etapa'] : 'interesse';
        q('UPDATE grupo_participantes SET etapa = ?, atualizado_em = ? WHERE id = ?', [$etapa, agora(), $p['id']]);
        redirecionar('./?p=grupo&id=' . $p['grupo_id'], 'Participante movido para ' . GRUPO_ETAPAS[$etapa] . '.');

    case 'grupo_excluir':
        $g = grupo_ou_sai($_POST['id'] ?? 0);
        q('DELETE FROM grupos WHERE id = ?', [$g['id']]);
        registrar('excluiu grupo', $g['codigo']);
        redirecionar('./?p=grupos', 'Grupo excluído.');
    }
    http_response_code(404); exit('Ação desconhecida.');
}
