<?php
// ================= Financeiro =================
// Regra de cálculo (vale para comissão e markup, inclusive os dois juntos):
//   receita da agência no item = (valor ao cliente − custo, quando o cliente paga à agência) + comissão do fornecedor

function status_parcela($p) {
    if ($p['pago_em']) return ['fechado', 'Pago em ' . data_br($p['pago_em'])];
    if ($p['vencimento'] < hoje()) return ['perdido', 'Vencida'];
    if ($p['vencimento'] <= date('Y-m-d', strtotime('+7 days'))) return ['proposta', 'Vence em breve'];
    return ['novo', 'Em aberto'];
}

function campos_item($i = [], $proposta = false) {
    $v = fn($k, $d = '') => h($i[$k] ?? $d);
    $num = fn($k) => isset($i[$k]) && $i[$k] !== null && $i[$k] !== '' ? h(number_format((float)$i[$k], 2, ',', '.')) : ''; ?>
  <div class="linha"><div><label>Fornecedor</label><input name="fornecedor" value="<?= $v('fornecedor') ?>" required placeholder="Ex.: Operadora X, Hotel Y, LATAM"></div>
    <div><label>Serviço</label><select name="servico"><?= select_opcoes(SERVICOS, $i['servico'] ?? '') ?></select></div></div>
  <label>Como o cliente paga este item</label>
  <div class="linha" style="grid-template-columns:1fr 1fr">
    <label style="font-weight:400;display:flex;gap:8px;align-items:flex-start"><input type="radio" name="pago_direto" value="0" style="width:auto;margin-top:4px" <?= empty($i['pago_direto']) ? 'checked' : '' ?>><span><b>Paga à agência</b><br><span class="mudo">A agência recebe e repassa ao fornecedor (markup ou revenda)</span></span></label>
    <label style="font-weight:400;display:flex;gap:8px;align-items:flex-start"><input type="radio" name="pago_direto" value="1" style="width:auto;margin-top:4px" <?= !empty($i['pago_direto']) ? 'checked' : '' ?>><span><b>Paga direto ao fornecedor</b><br><span class="mudo">A agência ganha só a comissão</span></span></label>
  </div>
  <div class="linha"><div><label>Valor ao cliente (R$)</label><input name="valor_cliente" class="vc" inputmode="decimal" value="<?= $num('valor_cliente') ?>" required></div>
    <?php if (!$proposta): ?>
    <div class="so-agencia"><label>Moeda do custo</label><select name="custo_moeda"><?= select_opcoes(MOEDAS, $i['custo_moeda'] ?? 'BRL') ?></select></div>
    <div class="so-agencia"><label>Custo (na moeda)</label><input name="custo_orig" inputmode="decimal" value="<?= isset($i['custo_orig']) ? $num('custo_orig') : (isset($i['custo']) && ($i['custo_moeda'] ?? 'BRL') === 'BRL' ? $num('custo') : '') ?>"></div>
    <div class="so-agencia moeda-estr" hidden><label>Taxa do dia p/ real</label><input name="custo_taxa" inputmode="decimal" value="<?= isset($i['custo_taxa']) && $i['custo_taxa'] ? h(str_replace('.', ',', (float)$i['custo_taxa'])) : '' ?>"></div>
    <?php else: ?><div class="so-agencia"><label>Custo (R$)</label><input name="custo" inputmode="decimal" value="<?= $num('custo') ?>"></div><?php endif; ?></div>
  <?php if (!$proposta): ?><div class="linha"><div class="so-agencia"><label>Fechado em</label><input type="date" name="fechado_em" value="<?= $v('fechado_em') ?>"></div>
    <div class="so-agencia"><label>Pagar ao fornecedor até</label><input type="date" name="vencimento_fornecedor" value="<?= $v('vencimento_fornecedor') ?>"></div></div><?php endif; ?>
  <div class="linha"><div><label>Comissão (%)</label><input name="comissao_pct" class="cp" inputmode="decimal" value="<?= isset($i['comissao_pct']) && $i['comissao_pct'] !== null ? h(str_replace('.', ',', (float)$i['comissao_pct'])) : '' ?>" placeholder="Ex.: 10"></div>
    <div><label>Comissão (R$)</label><input name="comissao" class="cv" inputmode="decimal" value="<?= $num('comissao') ?>" placeholder="Calculada pelo %"></div>
    <?php if (!$proposta): ?><div><label>Comissão prevista para</label><input type="date" name="comissao_prevista" value="<?= $v('comissao_prevista') ?>"></div><?php endif; ?></div>
  <?php if (!$proposta): ?>
  <?php
    $serv_atual = $i['servico'] ?? '';
    $precisa_reserva = in_array($serv_atual, ['Aéreo', 'Hospedagem', 'Traslados', 'Ingressos e passeios', 'Cruzeiro', 'Trem', 'Locação de carro'], true);
    $tem_reserva = ($i['reserva_codigo'] ?? $i['voo_numero'] ?? $i['hora_apresentacao'] ?? '') !== '';
  ?>
  <details class="reserva-guia" style="margin-top:10px;border:1px solid var(--linha);border-radius:8px;padding:8px 12px;background:var(--cinza-bg)"<?= ($precisa_reserva || $tem_reserva) ? ' open' : '' ?>>
    <summary style="cursor:pointer;font-weight:600;color:var(--sora)">📋 Dados da reserva (para o Guia da Viagem)</summary>
    <p class="mudo" style="margin:6px 0">Preencha conforme o tipo de serviço (localizador, horários, contato…). Usado no Roteiro Final; opcional.</p>
    <div class="linha"><div><label>Código da reserva / localizador</label><input name="reserva_codigo" value="<?= $v('reserva_codigo') ?>" placeholder="Ex.: ABC123, 987654"></div>
      <div><label>Contato do fornecedor</label><input name="fornecedor_contato" value="<?= $v('fornecedor_contato') ?>" placeholder="+55 11 90000-0000"></div></div>
    <div class="linha"><div><label>Data/hora de apresentação ou check-in</label><input name="hora_apresentacao" value="<?= $v('hora_apresentacao') ?>" placeholder="Ex.: 15/01 16:00, ou 08:00"></div>
      <div><label>Regime / observação da reserva</label><input name="regime" value="<?= $v('regime') ?>" placeholder="Ex.: café da manhã, all inclusive"></div></div>
    <div class="linha"><div><label>Endereço</label><input name="endereco" value="<?= $v('endereco') ?>" placeholder="Endereço do hotel/ponto de encontro"></div>
      <div><label>Bagagem (voos)</label><input name="bagagem" value="<?= $v('bagagem') ?>" placeholder="Ex.: 1 mala de 23 kg"></div></div>
    <div style="border-top:1px dashed var(--linha);margin:8px 0;padding-top:8px"><span class="mudo" style="font-size:12px">Voos (preencha só se for aéreo):</span></div>
    <div class="linha"><div><label>Número do voo</label><input name="voo_numero" value="<?= $v('voo_numero') ?>" placeholder="Ex.: LA1234"></div>
      <div><label>Origem → horário</label><input name="voo_origem" value="<?= $v('voo_origem') ?>" placeholder="GRU 07:10"></div></div>
    <div class="linha"><div><label>Partida (data/hora)</label><input name="voo_partida" value="<?= $v('voo_partida') ?>" placeholder="07:10"></div>
      <div><label>Chegada (data/hora)</label><input name="voo_chegada" value="<?= $v('voo_chegada') ?>" placeholder="10:25"></div></div>
    <div class="linha"><div><label>Destino → horário</label><input name="voo_destino" value="<?= $v('voo_destino') ?>" placeholder="REC 10:25"></div><div></div></div>
  </details>
  <?php endif; ?>
  <label>Observações</label><input name="observacoes" value="<?= $v('observacoes') ?>" placeholder="Ex.: localizador, condições, prazo de cancelamento">
<?php }

const JS_FIN = <<<'JS'
function n(v){v=(v||'').replace(/[^\d,.-]/g,'');if(v.indexOf(',')>-1)v=v.replace(/\./g,'').replace(',','.');return parseFloat(v)||0;}
document.querySelectorAll('form.item').forEach(function(f){
  var vc=f.querySelector('.vc'),cp=f.querySelector('.cp'),cv=f.querySelector('.cv');
  function calc(){ if(cp.value!==''){ cv.value=(n(vc.value)*n(cp.value)/100).toFixed(2).replace('.',','); } }
  cp.addEventListener('input',calc); vc.addEventListener('input',function(){ if(cp.value!=='') calc(); });
  function modo(){ var d=f.querySelector('input[name=pago_direto]:checked').value==='1'; f.querySelectorAll('.so-agencia').forEach(function(e){e.style.opacity=d?.4:1; var i=e.querySelector('input,select'); if(i)i.disabled=d;}); }
  f.querySelectorAll('input[name=pago_direto]').forEach(function(r){r.addEventListener('change',modo);}); modo();
  var cm=f.querySelector('select[name=custo_moeda]'), me=f.querySelector('.moeda-estr');
  function moeda(){ if(cm&&me){ me.hidden = cm.value==='BRL'; } }
  if(cm){ cm.addEventListener('change',moeda); moeda(); }
});
JS;

function pagina_venda($id) {
    exigir_financeiro();
    $v = um('SELECT v.*, c.nome, l.etapa, l.data_embarque, l.codigo FROM vendas v JOIN clientes c ON c.id = v.cliente_id LEFT JOIN leads l ON l.id = v.lead_id WHERE v.id = ?', [$id]);
    if (!$v) redirecionar('./?p=financeiro', '!Venda não encontrada.');
    $itens = todos('SELECT * FROM venda_itens WHERE venda_id = ? ORDER BY id', [$id]);
    $parc = todos('SELECT * FROM venda_parcelas WHERE venda_id = ? ORDER BY vencimento, id', [$id]);
    $r = resumo_venda($id);
    $falta = round($r['a_receber_cliente'] - $r['parcelado'], 2);
    topo('Financeiro · ' . $v['nome'], 'financeiro'); ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="./?p=financeiro">Financeiro</a> › Venda <?= h($v['codigo'] ?: '#' . $v['id']) ?></p>
      <h1><a href="./?p=cliente&id=<?= $v['cliente_id'] ?>" style="color:inherit;text-decoration:none"><?= h($v['nome']) ?></a> · <?= h($v['descricao']) ?></h1>
      <span class="mudo">Venda em <?= data_br($v['data_venda']) ?><?= $v['data_embarque'] ? ' · embarque ' . data_br($v['data_embarque']) : '' ?><?php if ($v['lead_id']): ?> · <a href="./?p=lead&id=<?= $v['lead_id'] ?>">ver lead</a><?php endif; ?></span></div>
    <?php if ($v['lead_id']): ?><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start"><a class="btn btn-pri" href="./?p=exportarclaude&modo=roteiro&lead=<?= $v['lead_id'] ?>">Exportar Roteiro Final p/ Claude</a></div><?php endif; ?>
  </div>
  <div class="metricas">
    <div class="metrica"><b><?= reais($r['total_cliente']) ?: 'R$ 0' ?></b><span>Valor total da viagem</span></div>
    <div class="metrica"><b><?= reais($r['receita']) ?: 'R$ 0' ?></b><span>Receita da agência · margem <?= pct($r['margem']) ?></span></div>
    <div class="metrica"><b><?= reais($r['recebido']) ?: 'R$ 0' ?></b><span>Recebido do cliente, de <?= reais($r['a_receber_cliente']) ?: 'R$ 0' ?></span></div>
    <div class="metrica"><b><?= reais($r['a_pagar'] - $r['pago_fornecedores']) ?: 'R$ 0' ?></b><span>A pagar a fornecedores</span></div>
    <div class="metrica"><b><?= reais($r['comissoes'] - $r['comissoes_recebidas']) ?: 'R$ 0' ?></b><span>Comissões a receber</span></div>
  </div>
  <?php if ($v['lead_id']): ?>
  <div class="cartao"><h2>Seguro e emergência (para o Guia da Viagem)</h2>
    <p class="mudo" style="margin-top:-6px">Opcional. Esses dados entram no Roteiro Final exportado para o Claude.</p>
    <form method="post" action="./?p=acao&a=fin_salvar_guia"><?= csrf_campo() ?><input type="hidden" name="venda_id" value="<?= $id ?>">
      <div class="linha"><div><label>Seguradora</label><input name="seguradora" value="<?= h($v['seguradora']) ?>" placeholder="Ex.: Hero, Assist Card"></div>
        <div><label>Plano / cobertura</label><input name="seguro_plano" value="<?= h($v['seguro_plano']) ?>" placeholder="Ex.: R$ 30 mil por pessoa"></div></div>
      <div class="linha"><div><label>Apólice</label><input name="seguro_apolice" value="<?= h($v['seguro_apolice']) ?>"></div>
        <div><label>Central 24h do seguro</label><input name="seguro_central" value="<?= h($v['seguro_central']) ?>" placeholder="0800..."></div></div>
      <div class="linha"><div><label>Plantão de emergência da agência</label><input name="plantao_emergencia" value="<?= h($v['plantao_emergencia']) ?>" placeholder="+55 11 97543-9074"></div>
        <div><label>Observações para o guia</label><input name="guia_obs" value="<?= h($v['guia_obs']) ?>" placeholder="Ex.: levar passaporte físico"></div></div>
      <p style="margin:12px 0 0"><button class="btn btn-pri">Salvar dados do guia</button></p>
    </form>
  </div>
  <?php endif; ?>
  <div class="cartao"><h2>Fornecedores e serviços</h2>
    <?php if ($itens): ?><div class="tabela"><table><thead><tr><th>Fornecedor</th><th>Pagamento</th><th>Valor ao cliente</th><th>Custo</th><th>Comissão</th><th>Receita</th><th>Fornecedor</th><th>Comissão</th><th></th></tr></thead><tbody>
      <?php foreach ($itens as $i): $rec = ($i['pago_direto'] ? 0 : $i['valor_cliente'] - $i['custo']) + $i['comissao']; ?>
      <?php $precisa = in_array($i['servico'], ['Aéreo', 'Hospedagem', 'Traslados', 'Ingressos e passeios', 'Cruzeiro', 'Trem', 'Locação de carro'], true);
            $tem = ($i['reserva_codigo'] ?? $i['voo_numero'] ?? $i['hora_apresentacao'] ?? '') !== ''; ?>
      <tr><td><b><?= h($i['fornecedor']) ?></b><br><span class="mudo"><?= h($i['servico']) ?><?= $i['observacoes'] ? ' · ' . h($i['observacoes']) : '' ?></span><?php if ($precisa && !$tem): ?><br><span class="tag proposta" style="font-size:11px">⚠ falta dados da reserva (Guia)</span><?php elseif ($tem): ?><br><span class="tag fechado" style="font-size:11px">✓ reserva preenchida</span><?php endif; ?></td>
        <td class="mudo"><?= $i['pago_direto'] ? 'Direto ao fornecedor' : 'À agência' ?></td>
        <td><?= reais($i['valor_cliente']) ?></td><td><?= $i['pago_direto'] ? '—' : reais($i['custo']) ?></td>
        <td><?= $i['comissao'] ? reais($i['comissao']) . ($i['comissao_pct'] ? '<br><span class="mudo">' . str_replace('.', ',', (float)$i['comissao_pct']) . '%</span>' : '') : '—' ?></td>
        <td><b><?= reais($rec) ?></b></td>
        <td><?php if ($i['pago_direto'] || $i['custo'] <= 0): ?><span class="mudo">—</span>
          <?php elseif ($i['pago_fornecedor_em']): ?><span class="tag fechado">Pago <?= data_br($i['pago_fornecedor_em']) ?></span>
          <?php else: ?><span class="tag <?= $i['vencimento_fornecedor'] && $i['vencimento_fornecedor'] < hoje() ? 'perdido' : 'proposta' ?>"><?= $i['vencimento_fornecedor'] ? 'Até ' . data_br($i['vencimento_fornecedor']) : 'A pagar' ?></span><?php botao_fin('fin_pagar_fornecedor', $i['id'], 'Marcar pago'); endif; ?></td>
        <td><?php if ($i['comissao'] <= 0): ?><span class="mudo">—</span>
          <?php elseif ($i['comissao_recebida_em']): ?><span class="tag fechado">Recebida <?= data_br($i['comissao_recebida_em']) ?></span>
          <?php else: ?><span class="tag proposta"><?= $i['comissao_prevista'] ? 'Prevista ' . data_br($i['comissao_prevista']) : 'A receber' ?></span><?php botao_fin('fin_receber_comissao', $i['id'], 'Recebida'); endif; ?></td>
        <td><details><summary class="btn btn-pq">Editar</summary><div style="min-width:min(640px,80vw);margin-top:8px">
          <form class="item" method="post" action="./?p=acao&a=fin_salvar_item"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $i['id'] ?>"><input type="hidden" name="venda_id" value="<?= $id ?>"><?php campos_item($i); ?>
            <p style="margin:10px 0 0;display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-pri">Salvar</button>
            <?php if ($i['pago_fornecedor_em']) botao_fin('fin_desfazer_fornecedor', $i['id'], 'Desfazer pagamento ao fornecedor', '', true); if ($i['comissao_recebida_em']) botao_fin('fin_desfazer_comissao', $i['id'], 'Desfazer comissão recebida', '', true); ?></p></form>
          <?php botao_fin('fin_excluir_item', $i['id'], 'Excluir item', 'btn-perigo'); ?></div></details></td></tr>
      <?php endforeach; ?>
    </tbody></table></div><?php else: ?><p class="vazio">Cadastre cada fornecedor da viagem: aéreo, hotel, operadora, seguro... A receita da agência é calculada automaticamente.</p><?php endif; ?>
    <details style="margin-top:12px" <?= $itens ? '' : 'open' ?>><summary style="cursor:pointer;font-weight:600;color:var(--sora)">+ Adicionar fornecedor ou serviço</summary>
      <form class="item" method="post" action="./?p=acao&a=fin_salvar_item"><?= csrf_campo() ?><input type="hidden" name="venda_id" value="<?= $id ?>"><?php campos_item(); ?>
        <p style="margin:10px 0 0"><button class="btn btn-pri">Adicionar</button></p></form></details>
  </div>
  <div class="grade2">
    <div class="cartao"><h2>Pagamentos do cliente</h2>
      <?php if (abs($falta) >= 0.01 && $r['a_receber_cliente'] > 0): ?><div class="aviso erro"><?= $falta > 0 ? 'Faltam ' . reais($falta) . ' para as parcelas cobrirem o que o cliente paga à agência.' : 'As parcelas passam ' . reais(-$falta) . ' do que o cliente paga à agência. Confira os valores.' ?></div><?php endif; ?>
      <?php if ($parc): ?><div class="tabela"><table><thead><tr><th>Vencimento</th><th>Parcela</th><th>Forma</th><th>Valor</th><th>Situação</th><th></th></tr></thead><tbody>
        <?php foreach ($parc as $p): [$pc, $pt] = status_parcela($p); ?><tr><td><?= data_br($p['vencimento']) ?></td><td><?= h($p['descricao']) ?></td><td class="mudo"><?= h($p['forma']) ?></td><td><?= reais($p['valor']) ?></td>
          <td><span class="tag <?= $pc ?>"><?= h($pt) ?></span></td>
          <td style="display:flex;gap:6px"><?php if (!$p['pago_em']) botao_fin('fin_parcela_paga', $p['id'], 'Recebido', 'btn-pri'); else botao_fin('fin_parcela_aberta', $p['id'], 'Desfazer'); botao_fin('fin_excluir_parcela', $p['id'], '×', 'btn-perigo'); ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php else: ?><p class="vazio">Nenhuma parcela lançada.</p><?php endif; ?>
    </div>
    <div class="cartao"><h2>Lançar parcelas</h2>
      <form method="post" action="./?p=acao&a=fin_gerar_parcelas"><?= csrf_campo() ?><input type="hidden" name="venda_id" value="<?= $id ?>">
        <div class="linha"><div><label>Valor a parcelar (R$)</label><input name="total" inputmode="decimal" value="<?= $falta > 0 ? h(number_format($falta, 2, ',', '.')) : '' ?>" required></div><div><label>Forma</label><select name="forma"><?= select_opcoes(FORMAS_PAGAMENTO, 'Pix') ?></select></div></div>
        <div class="linha"><div><label>Entrada (R$)</label><input name="entrada" inputmode="decimal" placeholder="Opcional"></div><div><label>Data da entrada</label><input type="date" name="entrada_data" value="<?= hoje() ?>"></div></div>
        <div class="linha"><div><label>Nº de parcelas</label><input name="n" type="number" min="1" max="24" value="1"></div><div><label>1º vencimento</label><input type="date" name="primeira" value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div></div>
        <p style="margin:10px 0 0"><button class="btn btn-pri">Gerar parcelas</button></p>
        <p class="mudo" style="margin-bottom:0">As parcelas mensais dividem o valor restante após a entrada. Se o cartão for pela operadora, lance só o que o cliente paga à agência.</p>
      </form>
    </div>
  </div>
  <div class="cartao"><h2>Dados da venda</h2>
    <form method="post" action="./?p=acao&a=fin_salvar_venda"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
      <div class="linha"><div><label>Descrição</label><input name="descricao" value="<?= h($v['descricao']) ?>"></div><div><label>Data da venda</label><input type="date" name="data_venda" value="<?= h($v['data_venda']) ?>" required></div></div>
      <label>Observações</label><textarea name="observacoes"><?= h($v['observacoes']) ?></textarea>
      <p style="margin:10px 0 0"><button class="btn btn-pri">Salvar</button></p></form>
    <?php if (usuario()['papel'] === 'admin'): ?><div style="margin-top:14px"><?php botao_fin('fin_excluir_venda', $id, 'Excluir venda e todos os lançamentos', 'btn-perigo', false, 'Excluir esta venda, os fornecedores e as parcelas?'); ?></div><?php endif; ?>
  </div>
<?php rodape(JS_FIN); }

function botao_fin($a, $id, $rot, $classe = '', $soBotao = false, $conf = '') {
    if ($soBotao) { echo '<button class="btn btn-pq ' . $classe . '" formaction="./?p=acao&a=' . $a . '&id=' . (int)$id . '">' . h($rot) . '</button>'; return; } ?>
  <form method="post" action="./?p=acao&a=<?= $a ?>" style="display:inline-block;margin:4px 0 0" <?= $conf ? 'onsubmit="return confirm(\'' . h($conf) . '\')"' : '' ?>><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="voltar" value="<?= h($_SERVER['REQUEST_URI']) ?>"><button class="btn btn-pq <?= $classe ?>"><?= h($rot) ?></button></form>
<?php }

// ---------- Painel financeiro ----------
function pagina_financeiro() {
    exigir_financeiro();
    $mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');
    $ini = "$mes-01"; $fim = date('Y-m-t', strtotime($ini));
    $item = "(CASE WHEN i.pago_direto = 0 THEN i.valor_cliente - i.custo ELSE 0 END + i.comissao)";
    $m = um("SELECT COALESCE(SUM(i.valor_cliente),0) fat, COALESCE(SUM($item),0) rec, COUNT(DISTINCT v.id) n FROM vendas v LEFT JOIN venda_itens i ON i.venda_id = v.id WHERE v.data_venda BETWEEN ? AND ?", [$ini, $fim]);
    $recebido = (float)valor('SELECT COALESCE(SUM(valor),0) FROM venda_parcelas WHERE pago_em BETWEEN ? AND ?', [$ini, $fim]);
    $vencido = (float)valor('SELECT COALESCE(SUM(valor),0) FROM venda_parcelas WHERE pago_em IS NULL AND vencimento < ?', [hoje()]);
    $pagar15 = (float)valor('SELECT COALESCE(SUM(custo),0) FROM venda_itens WHERE pago_direto = 0 AND custo > 0 AND pago_fornecedor_em IS NULL AND (vencimento_fornecedor IS NULL OR vencimento_fornecedor <= ?)', [date('Y-m-d', strtotime('+15 days'))]);
    $comiss = (float)valor('SELECT COALESCE(SUM(comissao),0) FROM venda_itens WHERE comissao > 0 AND comissao_recebida_em IS NULL');
    $receber = todos("SELECT p.*, c.nome, v.descricao, v.id vid FROM venda_parcelas p JOIN vendas v ON v.id = p.venda_id JOIN clientes c ON c.id = v.cliente_id WHERE p.pago_em IS NULL AND p.vencimento <= ? ORDER BY p.vencimento LIMIT 30", [date('Y-m-d', strtotime('+30 days'))]);
    $pagar = todos("SELECT i.*, c.nome, v.id vid FROM venda_itens i JOIN vendas v ON v.id = i.venda_id JOIN clientes c ON c.id = v.cliente_id WHERE i.pago_direto = 0 AND i.custo > 0 AND i.pago_fornecedor_em IS NULL ORDER BY i.vencimento_fornecedor IS NULL, i.vencimento_fornecedor LIMIT 30");
    $comis = todos("SELECT i.*, c.nome, v.id vid FROM venda_itens i JOIN vendas v ON v.id = i.venda_id JOIN clientes c ON c.id = v.cliente_id WHERE i.comissao > 0 AND i.comissao_recebida_em IS NULL ORDER BY i.comissao_prevista IS NULL, i.comissao_prevista LIMIT 30");
    $vendasMes = todos("SELECT v.*, c.nome, l.codigo FROM vendas v JOIN clientes c ON c.id = v.cliente_id LEFT JOIN leads l ON l.id = v.lead_id WHERE v.data_venda BETWEEN ? AND ? ORDER BY v.data_venda DESC", [$ini, $fim]);
    $ano = substr($mes, 0, 4);
    $meses = todos("SELECT substr(v.data_venda,1,7) m, COALESCE(SUM(i.valor_cliente),0) fat, COALESCE(SUM($item),0) rec FROM vendas v LEFT JOIN venda_itens i ON i.venda_id = v.id WHERE substr(v.data_venda,1,4) = ? GROUP BY m ORDER BY m", [$ano]);
    $porDestino = todos("SELECT COALESCE(NULLIF(l.destino,''), v.descricao) d, COUNT(DISTINCT v.id) n, COALESCE(SUM(i.valor_cliente),0) fat, COALESCE(SUM($item),0) rec FROM vendas v LEFT JOIN leads l ON l.id = v.lead_id LEFT JOIN venda_itens i ON i.venda_id = v.id WHERE substr(v.data_venda,1,4) = ? GROUP BY lower(d) ORDER BY rec DESC LIMIT 10", [$ano]);
    $porCanal = todos("SELECT COALESCE(l.origem, c.origem, 'Não informada') o, COUNT(DISTINCT v.id) n, COALESCE(SUM($item),0) rec FROM vendas v JOIN clientes c ON c.id = v.cliente_id LEFT JOIN leads l ON l.id = v.lead_id LEFT JOIN venda_itens i ON i.venda_id = v.id WHERE substr(v.data_venda,1,4) = ? GROUP BY o ORDER BY rec DESC", [$ano]);
    $nomeMes = ['01' => 'janeiro', '02' => 'fevereiro', '03' => 'março', '04' => 'abril', '05' => 'maio', '06' => 'junho', '07' => 'julho', '08' => 'agosto', '09' => 'setembro', '10' => 'outubro', '11' => 'novembro', '12' => 'dezembro'];
    topo('Financeiro', 'financeiro'); ?>
  <div class="cab"><h1>Financeiro · <?= $nomeMes[substr($mes, 5)] ?> de <?= $ano ?></h1>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><input type="hidden" name="p" value="financeiro"><input type="month" name="mes" value="<?= h($mes) ?>" style="width:auto"><button class="btn">Ver mês</button><a class="btn" href="./?p=exportarfin&mes=<?= h($mes) ?>">Exportar para o contador</a></form></div>
  <div class="metricas">
    <div class="metrica"><b><?= reais($m['fat']) ?: 'R$ 0' ?></b><span>Vendido no mês (<?= (int)$m['n'] ?> <?= (int)$m['n'] === 1 ? 'venda' : 'vendas' ?>)</span></div>
    <div class="metrica"><b><?= reais($m['rec']) ?: 'R$ 0' ?></b><span>Receita da agência · margem <?= pct($m['fat'] > 0 ? $m['rec'] / $m['fat'] : null) ?></span></div>
    <div class="metrica"><b><?= reais($recebido) ?: 'R$ 0' ?></b><span>Recebido de clientes no mês</span></div>
    <div class="metrica"><b style="<?= $vencido > 0 ? 'color:var(--erro)' : '' ?>"><?= reais($vencido) ?: 'R$ 0' ?></b><span>Parcelas vencidas</span></div>
    <div class="metrica"><b><?= reais($pagar15) ?: 'R$ 0' ?></b><span>A pagar a fornecedores (15 dias)</span></div>
    <div class="metrica"><b><?= reais($comiss) ?: 'R$ 0' ?></b><span>Comissões a receber</span></div>
  </div>
  <div class="grade3">
    <div class="cartao"><h2>A receber de clientes</h2><p class="mudo" style="margin-top:-6px">Vencidas e próximos 30 dias</p>
      <?php foreach ($receber as $p): [$pc, $pt] = status_parcela($p); ?><div class="tarefa"><div style="flex:1"><a href="./?p=venda&id=<?= $p['vid'] ?>"><b><?= h($p['nome']) ?></b></a> · <?= reais($p['valor']) ?><br><span class="mudo"><?= data_br($p['vencimento']) ?> · <?= h($p['descricao']) ?> · <?= h($p['forma']) ?></span> <span class="tag <?= $pc ?>"><?= h($pt) ?></span></div><?php botao_fin('fin_parcela_paga', $p['id'], 'Recebido', 'btn-pri'); ?></div><?php endforeach; ?>
      <?php if (!$receber): ?><p class="vazio">Nada a receber no período.</p><?php endif; ?></div>
    <div class="cartao"><h2>A pagar a fornecedores</h2>
      <?php foreach ($pagar as $i): ?><div class="tarefa"><div style="flex:1"><b><?= h($i['fornecedor']) ?></b> · <?= reais($i['custo']) ?><br><span class="mudo"><a href="./?p=venda&id=<?= $i['vid'] ?>"><?= h($i['nome']) ?></a> · <?= $i['vencimento_fornecedor'] ? 'até ' . data_br($i['vencimento_fornecedor']) : 'sem prazo' ?></span><?= $i['vencimento_fornecedor'] && $i['vencimento_fornecedor'] < hoje() ? ' <span class="tag perdido">Atrasado</span>' : '' ?></div><?php botao_fin('fin_pagar_fornecedor', $i['id'], 'Pago'); ?></div><?php endforeach; ?>
      <?php if (!$pagar): ?><p class="vazio">Nenhum pagamento pendente.</p><?php endif; ?></div>
    <div class="cartao"><h2>Comissões a receber</h2>
      <?php foreach ($comis as $i): ?><div class="tarefa"><div style="flex:1"><b><?= h($i['fornecedor']) ?></b> · <?= reais($i['comissao']) ?><br><span class="mudo"><a href="./?p=venda&id=<?= $i['vid'] ?>"><?= h($i['nome']) ?></a> · <?= $i['comissao_prevista'] ? 'prevista ' . data_br($i['comissao_prevista']) : 'sem previsão' ?></span><?= $i['comissao_prevista'] && $i['comissao_prevista'] < hoje() ? ' <span class="tag perdido">Atrasada</span>' : '' ?></div><?php botao_fin('fin_receber_comissao', $i['id'], 'Recebida'); ?></div><?php endforeach; ?>
      <?php if (!$comis): ?><p class="vazio">Nenhuma comissão pendente.</p><?php endif; ?></div>
  </div>
  <div class="cartao tabela"><h2>Vendas do mês</h2>
    <?php if ($vendasMes): ?><table><thead><tr><th>Data</th><th>Código</th><th>Cliente</th><th>Viagem</th><th>Valor total</th><th>Receita</th><th>Margem</th><th>Recebido</th></tr></thead><tbody>
      <?php foreach ($vendasMes as $v): $r = resumo_venda($v['id']); ?><tr><td><?= data_br($v['data_venda']) ?></td><td class="mudo"><?= h($v['codigo']) ?></td><td><a href="./?p=venda&id=<?= $v['id'] ?>"><?= h($v['nome']) ?></a></td><td><?= h($v['descricao']) ?></td><td><?= reais($r['total_cliente']) ?></td><td><b><?= reais($r['receita']) ?></b></td><td><?= pct($r['margem']) ?></td><td><?= reais($r['recebido']) ?> <span class="mudo">de <?= reais($r['a_receber_cliente']) ?></span></td></tr><?php endforeach; ?>
    </tbody></table><?php else: ?><p class="vazio">Nenhuma venda neste mês. As vendas são criadas quando um lead vai para "Fechado".</p><?php endif; ?></div>
  <div class="grade3">
    <div class="cartao tabela"><h2>Mês a mês em <?= $ano ?></h2><table><thead><tr><th>Mês</th><th>Vendido</th><th>Receita</th><th>Margem</th></tr></thead><tbody>
      <?php foreach ($meses as $x): ?><tr><td><?= ucfirst($nomeMes[substr($x['m'], 5)]) ?></td><td><?= reais($x['fat']) ?></td><td><?= reais($x['rec']) ?></td><td><?= pct($x['fat'] > 0 ? $x['rec'] / $x['fat'] : null) ?></td></tr><?php endforeach; ?>
    </tbody></table><?php if (!$meses): ?><p class="vazio">Sem vendas no ano.</p><?php endif; ?></div>
    <div class="cartao tabela"><h2>Receita por destino</h2><table><tbody>
      <?php foreach ($porDestino as $x): ?><tr><td><?= h($x['d']) ?><br><span class="mudo"><?= $x['n'] ?> <?= $x['n'] == 1 ? 'venda' : 'vendas' ?> · margem <?= pct($x['fat'] > 0 ? $x['rec'] / $x['fat'] : null) ?></span></td><td style="text-align:right"><b><?= reais($x['rec']) ?></b></td></tr><?php endforeach; ?>
    </tbody></table><?php if (!$porDestino): ?><p class="vazio">Sem dados.</p><?php endif; ?></div>
    <div class="cartao tabela"><h2>Receita por canal de origem</h2><table><tbody>
      <?php foreach ($porCanal as $x): ?><tr><td><?= h($x['o']) ?><br><span class="mudo"><?= $x['n'] ?> <?= $x['n'] == 1 ? 'venda' : 'vendas' ?></span></td><td style="text-align:right"><b><?= reais($x['rec']) ?></b></td></tr><?php endforeach; ?>
    </tbody></table><?php if (!$porCanal): ?><p class="vazio">Sem dados.</p><?php endif; ?></div>
  </div>
<?php rodape(); }

function exportar_financeiro() {
    exigir_financeiro();
    $mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');
    $ini = "$mes-01"; $fim = date('Y-m-t', strtotime($ini));
    registrar('exportou financeiro', $mes);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="financeiro-' . $mes . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    $l = fn($r) => fputcsv($o, array_map(fn($v) => is_float($v) ? number_format($v, 2, ',', '') : (is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v), $r), ';');
    $l(["VENDAS DE $mes (por data da venda)"]);
    $l(['Código', 'Data', 'Cliente', 'Viagem', 'Fornecedor', 'Serviço', 'Pagamento', 'Valor ao cliente', 'Custo', 'Comissão', 'Receita da agência', 'Fornecedor pago em', 'Comissão recebida em']);
    foreach (todos("SELECT v.id, v.data_venda, c.nome, v.descricao, COALESCE(ld.codigo, '#' || v.id) codigo, i.* FROM vendas v JOIN clientes c ON c.id = v.cliente_id LEFT JOIN leads ld ON ld.id = v.lead_id JOIN venda_itens i ON i.venda_id = v.id WHERE v.data_venda BETWEEN ? AND ? ORDER BY v.data_venda, v.id", [$ini, $fim]) as $r)
        $l([$r['codigo'], data_br($r['data_venda']), $r['nome'], $r['descricao'], $r['fornecedor'], $r['servico'], $r['pago_direto'] ? 'Direto ao fornecedor' : 'À agência',
            (float)$r['valor_cliente'], $r['pago_direto'] ? 0.0 : (float)$r['custo'], (float)$r['comissao'], (float)(($r['pago_direto'] ? 0 : $r['valor_cliente'] - $r['custo']) + $r['comissao']),
            data_br($r['pago_fornecedor_em']), data_br($r['comissao_recebida_em'])]);
    $l([]); $l(["RECEBIMENTOS DE CLIENTES EM $mes"]); $l(['Venda', 'Cliente', 'Parcela', 'Forma', 'Vencimento', 'Recebido em', 'Valor']);
    foreach (todos('SELECT p.*, c.nome FROM venda_parcelas p JOIN vendas v ON v.id = p.venda_id JOIN clientes c ON c.id = v.cliente_id WHERE p.pago_em BETWEEN ? AND ? ORDER BY p.pago_em', [$ini, $fim]) as $p)
        $l([(int)$p['venda_id'], $p['nome'], $p['descricao'], $p['forma'], data_br($p['vencimento']), data_br($p['pago_em']), (float)$p['valor']]);
    $l([]); $l(["COMISSÕES RECEBIDAS EM $mes"]); $l(['Venda', 'Cliente', 'Fornecedor', 'Recebida em', 'Valor']);
    foreach (todos('SELECT i.*, c.nome FROM venda_itens i JOIN vendas v ON v.id = i.venda_id JOIN clientes c ON c.id = v.cliente_id WHERE i.comissao_recebida_em BETWEEN ? AND ? ORDER BY i.comissao_recebida_em', [$ini, $fim]) as $i)
        $l([(int)$i['venda_id'], $i['nome'], $i['fornecedor'], data_br($i['comissao_recebida_em']), (float)$i['comissao']]);
    exit;
}

// ---------- Ações ----------
function acao_financeiro($a) {
    exigir_financeiro();
    $volta = fn($vid) => './?p=venda&id=' . (int)$vid;
    $voltar = function ($padrao) { $v = (string)($_POST['voltar'] ?? ''); $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/'; return ($v !== '' && strpos($v, $base) === 0 && strpos($v, '//') === false) ? $v : $padrao; };
    $iid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $item = fn() => um('SELECT * FROM venda_itens WHERE id = ?', [$iid]) ?: redirecionar('./?p=financeiro', '!Lançamento não encontrado.');
    $parc = fn() => um('SELECT * FROM venda_parcelas WHERE id = ?', [$iid]) ?: redirecionar('./?p=financeiro', '!Parcela não encontrada.');
    switch ($a) {
    case 'fin_abrir':
        $l = um('SELECT * FROM leads WHERE id = ?', [$iid]) ?: redirecionar('./?p=funil', '!Lead não encontrado.');
        redirecionar($volta(venda_do_lead($l)['id']));
    case 'fin_salvar_guia':
        $vid = (int)($_POST['venda_id'] ?? 0);
        if (!valor('SELECT 1 FROM vendas WHERE id = ?', [$vid])) redirecionar('./?p=financeiro', '!Venda não encontrada.');
        q('UPDATE vendas SET seguradora=?, seguro_plano=?, seguro_apolice=?, seguro_central=?, plantao_emergencia=?, guia_obs=?, atualizado_em=? WHERE id=?',
          [post('seguradora', 80), post('seguro_plano', 120), post('seguro_apolice', 60), post('seguro_central', 60), post('plantao_emergencia', 60), post('guia_obs', 300), agora(), $vid]);
        redirecionar($volta($vid), 'Dados do guia salvos.');

    case 'fin_salvar_item':
        $vid = (int)($_POST['venda_id'] ?? 0);
        if (!valor('SELECT 1 FROM vendas WHERE id = ?', [$vid])) redirecionar('./?p=financeiro', '!Venda não encontrada.');
        $direto = ($_POST['pago_direto'] ?? '0') === '1';
        $vc = num_valor($_POST['valor_cliente'] ?? '') ?? 0;
        // custo: na moeda de origem + taxa do dia -> converte para real
        $cmoeda = isset(MOEDAS[$_POST['custo_moeda'] ?? '']) ? $_POST['custo_moeda'] : 'BRL';
        $cOrig = num_valor($_POST['custo_orig'] ?? $_POST['custo'] ?? '') ?? 0;
        $ctaxa = $cmoeda === 'BRL' ? 1.0 : (num_valor($_POST['custo_taxa'] ?? '') ?: (cambio_sugestao($cmoeda) ?: 1));
        $custo = $direto ? 0 : round($cOrig * $ctaxa, 2);
        $pctv = ($_POST['comissao_pct'] ?? '') === '' ? null : (float)str_replace(',', '.', preg_replace('/[^\d,\.]/', '', $_POST['comissao_pct']));
        $com = num_valor($_POST['comissao'] ?? '');
        if ($com === null && $pctv !== null) $com = round($vc * $pctv / 100, 2);
        if (post('fornecedor') === '' || $vc <= 0) redirecionar($volta($vid), '!Informe o fornecedor e o valor ao cliente.');
        // campos de reserva (Guia da Viagem) — opcionais
        $res = [post('reserva_codigo', 60), post('fornecedor_contato', 60), post('hora_apresentacao', 60), post('regime', 80), post('endereco', 200), post('bagagem', 80),
                post('voo_numero', 40), post('voo_origem', 60), post('voo_partida', 40), post('voo_chegada', 40), post('voo_destino', 60)];
        $d = [post('fornecedor', 120), in_array($_POST['servico'] ?? '', SERVICOS, true) ? $_POST['servico'] : 'Outro', $direto ? 1 : 0, $vc, $custo, $pctv, $com ?? 0,
              $direto ? null : data_valida($_POST['vencimento_fornecedor'] ?? ''), data_valida($_POST['comissao_prevista'] ?? ''), post('observacoes', 300),
              $cmoeda, $cmoeda === 'BRL' ? null : $ctaxa, data_valida($_POST['fechado_em'] ?? ''), ...$res];
        if (!empty($_POST['id']) && valor('SELECT 1 FROM venda_itens WHERE id = ? AND venda_id = ?', [(int)$_POST['id'], $vid]))
            q('UPDATE venda_itens SET fornecedor=?, servico=?, pago_direto=?, valor_cliente=?, custo=?, comissao_pct=?, comissao=?, vencimento_fornecedor=?, comissao_prevista=?, observacoes=?, custo_moeda=?, custo_taxa=?, fechado_em=?, reserva_codigo=?, fornecedor_contato=?, hora_apresentacao=?, regime=?, endereco=?, bagagem=?, voo_numero=?, voo_origem=?, voo_partida=?, voo_chegada=?, voo_destino=? WHERE id=?', [...$d, (int)$_POST['id']]);
        else q('INSERT INTO venda_itens (fornecedor, servico, pago_direto, valor_cliente, custo, comissao_pct, comissao, vencimento_fornecedor, comissao_prevista, observacoes, custo_moeda, custo_taxa, fechado_em, reserva_codigo, fornecedor_contato, hora_apresentacao, regime, endereco, bagagem, voo_numero, voo_origem, voo_partida, voo_chegada, voo_destino, venda_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$d, $vid]);
        q('UPDATE vendas SET atualizado_em = ? WHERE id = ?', [agora(), $vid]);
        registrar('lançou fornecedor na venda', '#' . $vid);
        redirecionar($volta($vid), 'Lançamento salvo.');
    case 'fin_excluir_item':
        $i = $item(); q('DELETE FROM venda_itens WHERE id = ?', [$i['id']]); registrar('excluiu lançamento', 'venda #' . $i['venda_id']);
        redirecionar($volta($i['venda_id']), 'Lançamento excluído.');
    case 'fin_pagar_fornecedor': case 'fin_desfazer_fornecedor':
        $i = $item(); q('UPDATE venda_itens SET pago_fornecedor_em = ? WHERE id = ?', [$a === 'fin_pagar_fornecedor' ? hoje() : null, $i['id']]);
        registrar($a === 'fin_pagar_fornecedor' ? 'marcou fornecedor pago' : 'desfez pagamento a fornecedor', $i['fornecedor'] . ' · venda #' . $i['venda_id']);
        redirecionar($voltar($volta($i['venda_id'])), 'Pagamento ao fornecedor atualizado.');
    case 'fin_receber_comissao': case 'fin_desfazer_comissao':
        $i = $item(); q('UPDATE venda_itens SET comissao_recebida_em = ? WHERE id = ?', [$a === 'fin_receber_comissao' ? hoje() : null, $i['id']]);
        registrar($a === 'fin_receber_comissao' ? 'registrou comissão recebida' : 'desfez comissão recebida', $i['fornecedor'] . ' · venda #' . $i['venda_id']);
        redirecionar($voltar($volta($i['venda_id'])), 'Comissão atualizada.');
    case 'fin_parcela_paga': case 'fin_parcela_aberta':
        $p = $parc(); q('UPDATE venda_parcelas SET pago_em = ? WHERE id = ?', [$a === 'fin_parcela_paga' ? hoje() : null, $p['id']]);
        registrar($a === 'fin_parcela_paga' ? 'registrou recebimento' : 'desfez recebimento', reais($p['valor']) . ' · venda #' . $p['venda_id']);
        redirecionar($voltar($volta($p['venda_id'])), $a === 'fin_parcela_paga' ? 'Recebimento registrado.' : 'Parcela reaberta.');
    case 'fin_excluir_parcela':
        $p = $parc(); q('DELETE FROM venda_parcelas WHERE id = ?', [$p['id']]); registrar('excluiu parcela', 'venda #' . $p['venda_id']);
        redirecionar($voltar($volta($p['venda_id'])), 'Parcela excluída.');
    case 'fin_gerar_parcelas':
        $vid = (int)($_POST['venda_id'] ?? 0);
        if (!valor('SELECT 1 FROM vendas WHERE id = ?', [$vid])) redirecionar('./?p=financeiro', '!Venda não encontrada.');
        $total = num_valor($_POST['total'] ?? '') ?? 0; $ent = num_valor($_POST['entrada'] ?? '') ?? 0;
        $n = max(1, min(24, (int)($_POST['n'] ?? 1))); $forma = in_array($_POST['forma'] ?? '', FORMAS_PAGAMENTO, true) ? $_POST['forma'] : 'Outro';
        $primeira = data_valida($_POST['primeira'] ?? '') ?: hoje();
        if ($total <= 0 || $ent > $total) redirecionar($volta($vid), '!Confira o valor a parcelar e a entrada.');
        db()->beginTransaction();
        if ($ent > 0) q('INSERT INTO venda_parcelas (venda_id, descricao, vencimento, valor, forma) VALUES (?,?,?,?,?)', [$vid, 'Entrada', data_valida($_POST['entrada_data'] ?? '') ?: hoje(), $ent, $forma]);
        $resto = round($total - $ent, 2);
        if ($resto > 0) {
            $base = floor($resto / $n * 100) / 100;
            for ($k = 1; $k <= $n; $k++) {
                $val = $k === $n ? round($resto - $base * ($n - 1), 2) : $base;
                $venc = date('Y-m-d', strtotime($primeira . ' +' . ($k - 1) . ' month'));
                q('INSERT INTO venda_parcelas (venda_id, descricao, vencimento, valor, forma) VALUES (?,?,?,?,?)', [$vid, $n === 1 ? 'Parcela única' : "Parcela $k de $n", $venc, $val, $forma]);
            }
        }
        db()->commit();
        registrar('lançou parcelas', 'venda #' . $vid);
        redirecionar($volta($vid), 'Parcelas lançadas.');
    case 'fin_salvar_venda':
        q('UPDATE vendas SET descricao = ?, data_venda = ?, observacoes = ?, atualizado_em = ? WHERE id = ?',
          [post('descricao', 150), data_valida($_POST['data_venda'] ?? '') ?: hoje(), post('observacoes', 3000), agora(), $iid]);
        redirecionar($volta($iid), 'Venda atualizada.');
    case 'fin_excluir_venda':
        exigir_admin();
        q('DELETE FROM vendas WHERE id = ?', [$iid]); registrar('excluiu venda', '#' . $iid);
        redirecionar('./?p=financeiro', 'Venda excluída.');
    }
    http_response_code(404); exit('Ação desconhecida.');
}
