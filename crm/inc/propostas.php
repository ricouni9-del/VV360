<?php
// ================= Propostas (versões, opções e itens econômicos) =================
function tag_status_proposta($p) {
    $cls = ['rascunho' => 'novo', 'enviada' => 'proposta', 'substituida' => '', 'aprovada' => 'fechado', 'recusada' => 'perdido'][$p['status']] ?? '';
    return '<span class="tag ' . $cls . '">' . h(STATUS_PROPOSTA[$p['status']] ?? $p['status']) . '</span>';
}
function validade_txt($p) {
    if (!$p['validade']) return '';
    if ($p['status'] === 'enviada' && $p['validade'] < hoje()) return ' <span class="tag perdido">Cotação vencida em ' . data_br($p['validade']) . '</span>';
    if ($p['status'] === 'enviada' && $p['validade'] <= date('Y-m-d', strtotime('+3 days'))) return ' <span class="tag proposta">Vence em ' . data_br($p['validade']) . '</span>';
    return ' <span class="mudo">· válida até ' . data_br($p['validade']) . '</span>';
}
function link_roteiro($o) {
    if (!empty($o['roteiro_token'])) return url_base() . '/r/?t=' . $o['roteiro_token'];
    return $o['roteiro_url'] ?? '' ?: '';
}
function titulo_do_html($arq) {
    $txt = @file_get_contents($arq, false, null, 0, 20000); if ($txt === false) return '';
    if (preg_match('#<title[^>]*>(.*?)</title>#is', $txt, $m)) $t = $m[1];
    elseif (preg_match('#<h1[^>]*>(.*?)</h1>#is', $txt, $m)) $t = $m[1];
    else return '';
    $t = trim(html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8'));
    $t = preg_replace('/\\s*[·|\\-–—]\\s*Vanessa Viagens.*$/iu', '', $t); // tira sufixo da marca, se houver
    return mb_substr(trim($t), 0, 120);
}
/** Bloco do roteiro de uma opção, usado dentro de cada cartão de opção. */
function bloco_roteiro_opcao($o, $cli_nome, $cli_tel, $editavel) {
    $link = link_roteiro($o);
    $acs = $o['roteiro_token'] ? um('SELECT COUNT(*) n, MAX(acessado_em) u FROM roteiro_acessos WHERE opcao_id = ?', [$o['id']]) : ['n' => 0, 'u' => null];
    ob_start(); ?>
    <div style="border-top:1px dashed var(--linha);margin-top:12px;padding-top:12px">
      <b style="font-size:14px;color:var(--ai)">Roteiro desta opção</b>
      <?php if ($link): ?>
        <p style="margin:6px 0"><a href="<?= h($link . (!empty($o['roteiro_token']) ? '&preview=1' : '')) ?>" target="_blank" rel="noopener"><?= !empty($o['roteiro_token']) ? h($o['roteiro_nome'] ?: 'Roteiro') . ' (link para o cliente)' : h($link) ?></a>
        <?php if ($acs['n']): ?><span class="mudo">· aberto <?= (int)$acs['n'] ?>×, última vez <?= data_br($acs['u'], true) ?></span><?php endif; ?></p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-pq" type="button" onclick="navigator.clipboard.writeText(<?= h(json_encode($link)) ?>);this.textContent='Link copiado'">Copiar link</button>
          <?php if ($z = link_whats($cli_tel, 'Olá, ' . primeiro_nome($cli_nome) . '! Preparei com muito carinho o roteiro da sua viagem. Você pode ver aqui: ' . $link)): ?><a class="btn btn-pq btn-zap" href="<?= h($z) ?>" target="_blank" rel="noopener">Enviar pelo WhatsApp</a><?php endif; ?>
          <?php if ($editavel && !empty($o['roteiro_token'])): ?><form method="post" action="./?p=acao&a=prop_roteiro_remover" onsubmit="return confirm('Remover o roteiro desta opção? O link deixa de funcionar.')"><?= csrf_campo() ?><input type="hidden" name="opcao_id" value="<?= $o['id'] ?>"><button class="btn btn-pq btn-perigo">Remover</button></form><?php endif; ?>
        </div>
      <?php else: ?><p class="mudo" style="margin:6px 0">Nenhum roteiro anexado a esta opção.</p><?php endif; ?>
      <?php if ($editavel): ?>
        <form method="post" action="./?p=acao&a=prop_roteiro_enviar" enctype="multipart/form-data" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><?= csrf_campo() ?><input type="hidden" name="opcao_id" value="<?= $o['id'] ?>">
          <input type="file" name="arquivo" accept=".html,.htm" required style="max-width:280px"><button class="btn btn-pq"><?= $link ? 'Substituir roteiro' : 'Anexar roteiro (HTML)' ?></button></form>
      <?php endif; ?>
      <p style="margin:8px 0 0;display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($editavel && ia_tem_chave() && valor('SELECT COUNT(*) FROM proposta_itens WHERE opcao_id = ?', [$o['id']])): ?><a class="btn btn-pq btn-pri" href="./?p=gerarroteiro&opcao=<?= $o['id'] ?>" onclick="return confirm('Gerar o roteiro desta opção com o Claude? Consome uma geração (centavos).')">✨ Gerar roteiro com o Claude</a><?php endif; ?>
        <a class="btn btn-pq" href="./?p=exportarclaude&modo=proposta&id=<?= $o['proposta_id'] ?>&opcao=<?= $o['id'] ?>">Exportar para o Claude (manual)</a>
      </p>
    </div>
<?php return ob_get_clean(); }
function url_base() {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'vanessaviagenseturismo.com.br');
}

/** Cartão "Propostas" exibido na página do lead. */
function cartao_propostas_lead($l) {
    $props = todos('SELECT * FROM propostas WHERE lead_id = ? ORDER BY versao DESC', [$l['id']]);
    $fin = pode_financeiro(); ?>
  <div class="cartao" id="propostas"><h2>Propostas</h2>
    <?php if (!$props): ?><p class="vazio" style="padding-top:0">Nenhuma proposta registrada. Registre a cotação enquanto monta o roteiro: no fechamento, ela vira a venda no Financeiro sem redigitar.</p><?php endif; ?>
    <?php foreach ($props as $p): $ops = todos('SELECT * FROM proposta_opcoes WHERE proposta_id = ? ORDER BY ordem, id', [$p['id']]); ?>
      <div style="border-top:1px solid var(--linha);padding:10px 0">
        <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><div><a href="./?p=proposta&id=<?= $p['id'] ?>"><b><?= h($l['codigo']) ?>-v<?= (int)$p['versao'] ?> · <?= h(rotulo_proposta($p, $ops)) ?></b></a> <?= tag_status_proposta($p) ?><?= validade_txt($p) ?></div>
          <span class="mudo"><?= $p['enviada_em'] ? 'enviada ' . data_br($p['enviada_em']) : 'criada ' . data_br($p['criado_em']) ?><?= $p['origem'] === 'claude' ? ' · pelo Claude' : '' ?></span></div>
        <?php foreach ($ops as $o): $r = resumo_opcao($o['id']); ?><div class="mudo" style="margin-top:4px"><?= $o['escolhida'] ? '✓ ' : '' ?><?= h($o['nome']) ?>: <b style="color:var(--tinta)"><?= moeda_fmt($r['total'], $r['moeda']) ?: 'R$ 0' ?></b><?= $fin && $r['markup'] > 0 ? ' · markup ' . moeda_fmt($r['markup'], $r['moeda']) : '' ?><?= ($o['roteiro_token'] || $o['roteiro_url']) ? ' · 📄 roteiro' : '' ?></div><?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
      <?php if ($props): ?><form method="post" action="./?p=acao&a=prop_nova_versao"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $props[0]['id'] ?>"><button class="btn btn-pq btn-pri">Nova versão a partir da v<?= (int)$props[0]['versao'] ?></button></form><?php endif; ?>
      <form method="post" action="./?p=acao&a=prop_nova"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>"><button class="btn btn-pq <?= $props ? '' : 'btn-pri' ?>"><?= $props ? 'Proposta em branco' : '+ Nova proposta' ?></button></form>
    </div>
  </div>
<?php }

function pagina_proposta($id) {
    $p = um('SELECT p.*, l.cliente_id, l.etapa, l.destino, l.codigo, c.nome, c.telefone FROM propostas p JOIN leads l ON l.id = p.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE p.id = ?', [$id]);
    if (!$p) redirecionar('./?p=funil', '!Proposta não encontrada.');
    $ops = todos('SELECT * FROM proposta_opcoes WHERE proposta_id = ? ORDER BY ordem, id', [$id]);
    $editavel = $p['status'] === 'rascunho';
    $fin = pode_financeiro();
    topo($p['codigo'] . '-v' . $p['versao'] . ' · ' . $p['nome'], 'funil'); ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="./?p=lead&id=<?= $p['lead_id'] ?>#propostas"><?= h($p['nome']) ?> · <?= h($p['destino'] ?: 'Lead') ?></a> › Proposta v<?= (int)$p['versao'] ?></p>
      <h1><?= h($p['codigo']) ?>-v<?= (int)$p['versao'] ?> · <?= h(rotulo_proposta($p, $ops)) ?></h1>
      <?php $acs = um('SELECT COUNT(*) n, MAX(acessado_em) u FROM roteiro_acessos WHERE proposta_id = ?', [$id]); ?>
      <?= tag_status_proposta($p) ?><?= validade_txt($p) ?> <span class="mudo">· <?= $p['enviada_em'] ? 'enviada em ' . data_br($p['enviada_em']) : 'criada em ' . data_br($p['criado_em']) ?><?= $acs['n'] ? ' · roteiro aberto ' . (int)$acs['n'] . ($acs['n'] == 1 ? ' vez' : ' vezes') . ', a última em ' . data_br($acs['u'], true) : '' ?></span></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">
      <?php if ($editavel): $confirmar = !empty($_GET['confirmar_envio']); ?><form method="post" action="./?p=acao&a=prop_enviar" onsubmit="return confirm('Marcar como enviada ao cliente? A versão fica congelada; ajustes viram uma nova versão.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><?php if ($confirmar): ?><input type="hidden" name="confirmar" value="1"><button class="btn btn-pri" style="background:#8A5A00">Enviar mesmo assim</button><?php else: ?><button class="btn btn-pri">Marcar como enviada</button><?php endif; ?></form><?php endif; ?>
      <?php if (!$editavel): ?><form method="post" action="./?p=acao&a=prop_nova_versao"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn">Criar nova versão (ajustes)</button></form><?php endif; ?>
      <?php if ($p['status'] === 'enviada' && in_array($p['etapa'], ['proposta', 'ajustes', 'novo', 'conversa', 'roteiro'], true)): ?><form method="post" action="./?p=acao&a=prop_rascunho" onsubmit="return confirm('Voltar esta proposta para rascunho? Ela poderá ser editada de novo e o lead volta para Montando roteiro.')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn">Voltar para rascunho</button></form><?php endif; ?>
      <?php if ($p['status'] === 'enviada'): ?><form method="post" action="./?p=acao&a=prop_recusar" onsubmit="return confirm('Marcar esta proposta como recusada?')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-perigo">Recusada</button></form><?php endif; ?>
      <?php if ($editavel): ?><form method="post" action="./?p=acao&a=prop_excluir" onsubmit="return confirm('Excluir este rascunho?')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-perigo">Excluir rascunho</button></form><?php endif; ?>
      <a class="btn" href="./?p=propostapdf&id=<?= $id ?>" target="_blank" rel="noopener">Baixar PDF</a>
      <a class="btn" href="./?p=exportarclaude&modo=proposta&id=<?= $id ?>">Exportar para o Claude</a>
    </div></div>
  <?php if (!$editavel && $p['status'] !== 'aprovada'): ?><p class="mudo" style="margin-top:-8px">Esta versão está congelada. Para mudar valores ou fornecedores, crie uma nova versão.</p><?php endif; ?>
  <?php if ($p['status'] === 'enviada' && count($ops) > 1): ?><div class="aviso">Quando o cliente decidir, clique em "Cliente aprovou esta opção" na opção escolhida. O lead vai para "Aprovada pelo Cliente/Aguardando fechamento"; o fechamento (e o envio ao Financeiro) é confirmado depois, na página do lead.</div><?php endif; ?>

  <?php foreach ($ops as $o): $r = resumo_opcao($o['id']); $itens = todos('SELECT * FROM proposta_itens WHERE opcao_id = ? ORDER BY id', [$o['id']]); $sm = SIMBOLO_MOEDA[$r['moeda']] ?? 'R$'; ?>
  <div class="cartao" style="<?= $o['escolhida'] ? 'border:2px solid var(--ok)' : '' ?>">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
      <h2 style="margin:0"><?= $o['escolhida'] ? '✓ ' : '' ?><?= h($o['nome']) ?><?php if ($o['data_inicio'] && $o['data_fim']): ?> <span class="mudo" style="font-family:var(--sans);font-size:13px;font-weight:400"><?= data_br($o['data_inicio']) ?>–<?= data_br($o['data_fim']) ?></span><?php endif; ?><?php if (!empty($o['aprovada_cliente_em'])): ?> <span class="tag fechado" title="Aprovada em <?= data_br($o['aprovada_cliente_em'], true) ?> · <?= h($o['aprovada_cliente_nome']) ?>">✓ aprovada pelo cliente</span><?php endif; ?></h2>
      <?php if (!empty($o['aprovada_cliente_obs'])): ?><p style="background:#F0F6F2;border-left:3px solid #14602E;padding:8px 12px;margin:6px 0;font-size:13.5px"><b>Observação do cliente na aprovação:</b> <?= h($o['aprovada_cliente_obs']) ?></p><?php endif; ?>
      <?php if ($p['status'] === 'enviada'): ?><form method="post" action="./?p=acao&a=prop_aprovar" onsubmit="return confirm('Confirmar que o cliente aprovou esta opção?')"><?= csrf_campo() ?><input type="hidden" name="opcao_id" value="<?= $o['id'] ?>"><button class="btn btn-pri btn-pq">Cliente aprovou esta opção</button></form><?php endif; ?>
    </div>

    <?php if ($editavel): ?>
      <form method="post" action="./?p=acao&a=prop_opcao_moeda" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:12px 0"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $o['id'] ?>">
        <div><label style="margin-top:0">Moeda</label><select name="moeda"><?= select_opcoes(MOEDAS, $r['moeda']) ?></select></div>
        <?php if ($r['moeda'] !== 'BRL'): $sug = cambio_sugestao($r['moeda']); ?><div><label style="margin-top:0">Taxa p/ real</label><input name="taxa_cambio" inputmode="decimal" value="<?= $o['taxa_cambio'] ? h(str_replace('.', ',', (float)$o['taxa_cambio'])) : '' ?>" placeholder="<?= $sug ? h(number_format($sug, 4, ',', '.')) : 'ex.: 5,45' ?>" style="width:110px"></div><?php if ($sug): ?><span class="mudo" style="padding-bottom:9px">sugestão de hoje: <?= number_format($sug, 4, ',', '.') ?></span><?php endif; endif; ?>
        <button class="btn btn-pq" style="margin-bottom:1px">Aplicar</button>
      </form>
      <form method="post" action="./?p=acao&a=prop_opcao_datas" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin:0 0 12px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $o['id'] ?>">
        <div><label style="margin-top:0">Início da viagem</label><input type="date" name="data_inicio" value="<?= h($o['data_inicio']) ?>"></div>
        <div><label style="margin-top:0">Fim da viagem</label><input type="date" name="data_fim" value="<?= h($o['data_fim']) ?>"></div>
        <?php $du = duracao_viagem($o['data_inicio'], $o['data_fim']); if ($du): ?><span class="mudo" style="padding-bottom:9px"><?= h($du) ?></span><?php endif; ?>
        <button class="btn btn-pq" style="margin-bottom:1px">Salvar datas</button>
      </form>
    <?php elseif ($o['data_inicio'] && $o['data_fim']): ?>
      <p class="mudo" style="margin:8px 0 0">Viagem: <?= data_br($o['data_inicio']) ?> a <?= data_br($o['data_fim']) ?> · <?= h(duracao_viagem($o['data_inicio'], $o['data_fim'])) ?></p>
    <?php endif; ?>

    <?php if ($itens): ?><div class="tabela" style="margin-top:6px"><table><thead><tr><th>Serviço</th><th>Detalhes</th><th style="text-align:right">Valor (<?= h($r['moeda']) ?>)</th><?php if ($editavel): ?><th></th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($itens as $i): ?><tr>
        <td><?php if ($editavel): ?><form class="linha-item" method="post" action="./?p=acao&a=prop_item_salvar" style="display:contents"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $i['id'] ?>"><input type="hidden" name="opcao_id" value="<?= $o['id'] ?>"><select name="servico" style="font-size:13px;padding:6px"><?= select_opcoes(SERVICOS, $i['servico']) ?></select><?php else: ?><b><?= h($i['servico']) ?></b><?php endif; ?></td>
        <td><?php if ($editavel): ?><input name="detalhes" value="<?= h($i['detalhes']) ?>" style="font-size:13px;padding:6px;width:100%" placeholder="Ex.: Hotel 5★, 4 noites, café"><?php else: ?><?= h($i['detalhes']) ?><?php endif; ?></td>
        <td style="text-align:right;white-space:nowrap"><?php if ($editavel): ?><input name="valor_cliente" inputmode="decimal" value="<?= h(number_format((float)$i['valor_cliente'], 2, ',', '.')) ?>" style="font-size:13px;padding:6px;width:110px;text-align:right"><?php else: ?><?= moeda_fmt($i['valor_cliente'], $r['moeda']) ?><?php endif; ?></td>
        <?php if ($editavel): ?><td style="white-space:nowrap"><button class="btn btn-pq btn-pri" title="Salvar">✓</button></form> <form method="post" action="./?p=acao&a=prop_item_excluir" style="display:inline"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $i['id'] ?>"><button class="btn btn-pq btn-perigo" title="Excluir">×</button></form></td><?php endif; ?>
      </tr><?php endforeach; ?>
    </tbody></table></div>
    <?php else: ?><p class="vazio">Nenhum serviço nesta opção.</p><?php endif; ?>

    <?php if ($editavel): ?>
      <form method="post" action="./?p=acao&a=prop_item_salvar" class="add-serv" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:8px;background:var(--cinza-bg);padding:10px;border-radius:8px"><?= csrf_campo() ?><input type="hidden" name="opcao_id" value="<?= $o['id'] ?>">
        <div><label style="margin-top:0">Serviço</label><select name="servico"><?= select_opcoes(SERVICOS, 'Hospedagem') ?></select></div>
        <div style="flex:1;min-width:180px"><label style="margin-top:0">Detalhes</label><input name="detalhes" placeholder="Ex.: Hotel 5★ em Kyoto, 4 noites"></div>
        <div><label style="margin-top:0">Valor (<?= h($r['moeda']) ?>)</label><input name="valor_cliente" inputmode="decimal" style="width:120px;text-align:right"></div>
        <button class="btn btn-pri" style="margin-bottom:1px">+ Adicionar</button>
      </form>
    <?php endif; ?>

    <div style="margin-top:12px;border-top:1px solid var(--linha);padding-top:10px;max-width:420px;margin-left:auto">
      <div style="display:flex;justify-content:space-between;font-size:14px;padding:3px 0"><span class="mudo">Total dos serviços</span><span><?= moeda_fmt($r['soma_itens'], $r['moeda']) ?></span></div>
      <div style="display:flex;justify-content:space-between;align-items:center;font-size:14px;padding:3px 0"><span class="mudo">Markup do pacote</span>
        <?php if ($editavel): ?><form method="post" action="./?p=acao&a=prop_opcao_markup" style="display:flex;gap:6px;align-items:center"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $o['id'] ?>"><span class="mudo"><?= $sm ?></span><input name="markup" inputmode="decimal" value="<?= h(number_format($r['markup'], 2, ',', '.')) ?>" style="width:110px;text-align:right;font-size:13px;padding:6px"><button class="btn btn-pq">ok</button></form><?php else: ?><span><?= moeda_fmt($r['markup'], $r['moeda']) ?></span><?php endif; ?></div>
      <div style="display:flex;justify-content:space-between;font-size:17px;font-weight:600;color:var(--ai);border-top:1px solid var(--linha);margin-top:6px;padding-top:8px"><span>Total ao cliente</span><span><?= moeda_fmt($r['total'], $r['moeda']) ?></span></div>
      <?php if ($r['moeda'] !== 'BRL'): ?><div style="text-align:right;font-size:12px;color:var(--t2)">≈ <?= reais($r['total_brl']) ?> <span class="mudo">(taxa <?= number_format($r['taxa'], 4, ',', '.') ?>)</span></div><?php endif; ?>
    </div>

    <?= bloco_roteiro_opcao($o, $p['nome'], $p['telefone'], $editavel) ?>
    <?php if ($editavel): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <form method="post" action="./?p=acao&a=prop_opcao_renomear" style="display:flex;gap:6px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $o['id'] ?>"><input name="nome" value="<?= h($o['nome']) ?>" style="width:240px"><button class="btn btn-pq">Renomear</button></form>
        <?php if (count($ops) > 1): ?><form method="post" action="./?p=acao&a=prop_opcao_excluir" onsubmit="return confirm('Excluir esta opção e seus itens?')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $o['id'] ?>"><button class="btn btn-pq btn-perigo">Excluir opção</button></form><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if ($editavel): ?>
  <div class="cartao">
    <form method="post" action="./?p=acao&a=prop_opcao_nova" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end"><?= csrf_campo() ?><input type="hidden" name="proposta_id" value="<?= $id ?>">
      <div style="flex:1;min-width:220px"><label style="margin-top:0">Adicionar outra opção para o cliente comparar</label><input name="nome" placeholder="Ex.: Opção B · Bariloche" required></div>
      <label style="display:flex;gap:6px;align-items:center;font-weight:400;margin:0 0 10px"><input type="checkbox" name="copiar" value="1" checked style="width:auto">Copiar os itens da primeira opção</label>
      <button class="btn">Adicionar opção</button></form>
    <form method="post" action="./?p=acao&a=prop_opcao_roteiro" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;border-top:1px dashed var(--linha);margin-top:12px;padding-top:12px"><?= csrf_campo() ?><input type="hidden" name="proposta_id" value="<?= $id ?>">
      <div style="flex:1;min-width:220px"><label style="margin-top:0">Ou criar uma opção a partir de um roteiro pronto (HTML)</label><input type="file" name="arquivo" accept=".html,.htm" required></div>
      <button class="btn">Criar opção com o roteiro</button></form>
    <p class="mudo" style="margin:8px 0 0">A opção nasce com o nome lido do título do roteiro e já com o link para o cliente. Os valores você preenche depois.</p>
  </div>
  <?php endif; ?>

  <div class="grade2">
    <div class="cartao"><h2>Roteiros</h2>
      <p class="mudo" style="margin-top:0">Cada opção tem o seu próprio roteiro e link para o cliente, anexados dentro do cartão da opção, acima. Para propostas antigas, abra a opção e use "Anexar roteiro (HTML)".</p>
      <?php $prim = $ops[0] ?? null; if ($prim): $lk = link_roteiro($prim); if ($lk): ?><p style="margin:6px 0 0">Roteiro da <?= h($prim['nome']) ?>: <a href="<?= h($lk . (!empty($prim['roteiro_token']) ? '&preview=1' : '')) ?>" target="_blank" rel="noopener">abrir</a></p><?php endif; endif; ?>
    </div>
    <div class="cartao"><h2>Dados da proposta</h2>
      <form method="post" action="./?p=acao&a=prop_salvar"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
        <label>Título</label><input name="titulo" value="<?= h($p['titulo']) ?>" placeholder="Ex.: Japão das cerejeiras, 12 dias">
        <div class="linha"><div><label>Validade da cotação</label><input type="date" name="validade" value="<?= h($p['validade']) ?>"></div><div><label>Ou link externo do roteiro</label><input name="roteiro_url" value="<?= h($p['roteiro_url']) ?>" placeholder="https://..."></div></div>
        <label>Observações</label><textarea name="observacoes" placeholder="Ex.: tarifa aérea garantida até 10/10; hotel com cancelamento grátis até 30 dias antes"><?= h($p['observacoes']) ?></textarea>
        <p style="margin:10px 0 0"><button class="btn btn-pri">Salvar</button></p></form>
    </div>
  </div>
<?php rodape(JS_FIN); }

// ---------- Ações ----------
function proxima_versao($lead_id) { return (int)valor('SELECT COALESCE(MAX(versao),0) + 1 FROM propostas WHERE lead_id = ?', [$lead_id]); }
function proposta_ou_sai($id) { $p = um('SELECT p.*, l.cliente_id FROM propostas p JOIN leads l ON l.id = p.lead_id WHERE p.id = ?', [(int)$id]); if (!$p) redirecionar('./?p=funil', '!Proposta não encontrada.'); return $p; }
function exigir_rascunho($p) { if ($p['status'] !== 'rascunho') redirecionar('./?p=proposta&id=' . $p['id'], '!Esta versão já foi enviada e está congelada. Crie uma nova versão para alterar.'); }
function nota_lead($lead_id, $cliente_id, $txt) { q('INSERT INTO atividades (lead_id, cliente_id, usuario_id, tipo, texto, criado_em) VALUES (?,?,?,?,?,?)', [$lead_id, $cliente_id, usuario()['id'], 'proposta', $txt, agora()]); }

/** Aprova uma opção: proposta aprovada, lead fechado e itens copiados para a venda. Devolve a venda. */
/** O cliente aprovou uma opção: marca a opção/proposta, move o lead para "Aprovada pelo Cliente/Aguardando fechamento" e NÃO cria a venda ainda. */
function aprovar_opcao($oid) {
    $o = um('SELECT o.*, p.lead_id, p.versao, p.id pid FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [$oid]);
    $l = um('SELECT * FROM leads WHERE id = ?', [$o['lead_id']]);
    q('UPDATE proposta_opcoes SET escolhida = (id = ?) WHERE proposta_id = ?', [$oid, $o['pid']]);
    q("UPDATE propostas SET status = 'aprovada', decidida_em = ?, atualizado_em = ? WHERE id = ?", [agora(), agora(), $o['pid']]);
    q("UPDATE propostas SET status = 'substituida' WHERE lead_id = ? AND id <> ? AND status = 'enviada'", [$l['id'], $o['pid']]);
    $total = resumo_opcao($oid)['total'];
    if ($o['data_inicio'] || $o['data_fim'])
        q('UPDATE leads SET data_embarque = COALESCE(?, data_embarque), data_retorno = COALESCE(?, data_retorno) WHERE id = ?', [$o['data_inicio'] ?: null, $o['data_fim'] ?: null, $l['id']]);
    if (in_array($l['etapa'], ['novo', 'conversa', 'roteiro', 'proposta', 'ajustes'], true)) {
        q("UPDATE leads SET etapa = 'aprovada', etapa_desde = ?, valor_estimado = ?, motivo_perda = NULL, atualizado_em = ? WHERE id = ?", [agora(), $total ?: $l['valor_estimado'], agora(), $l['id']]);
        q('INSERT INTO atividades (lead_id, cliente_id, usuario_id, tipo, texto, criado_em) VALUES (?,?,?,?,?,?)', [$l['id'], $l['cliente_id'], usuario()['id'], 'etapa', ETAPAS[$l['etapa']] . ' → ' . ETAPAS['aprovada'], agora()]);
        q('INSERT INTO tarefas (lead_id, cliente_id, usuario_id, titulo, vence_em, criado_em) VALUES (?,?,?,?,?,?)', [$l['id'], $l['cliente_id'], usuario()['id'], 'Receber sinal e confirmar reservas para fechar', date('Y-m-d', strtotime('+2 days')), agora()]);
    }
    nota_lead($l['id'], $l['cliente_id'], 'Cliente aprovou a proposta v' . $o['versao'] . ' · ' . $o['nome'] . ' (' . reais($total) . '). Aguardando fechamento.');
    registrar('cliente aprovou proposta', 'v' . $o['versao'] . ' · lead #' . $l['id']);
    return $l['id'];
}
/** Confirma o fechamento: lead para "Fechado" e cria a venda no Financeiro com a opção escolhida. */
function confirmar_fechamento($lead_id) {
    $l = um('SELECT * FROM leads WHERE id = ?', [$lead_id]);
    $op = um("SELECT o.* FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE p.lead_id = ? AND p.status = 'aprovada' AND o.escolhida = 1 ORDER BY p.versao DESC LIMIT 1", [$lead_id]);
    q("UPDATE leads SET etapa = 'fechado', etapa_desde = ?, motivo_perda = NULL, atualizado_em = ? WHERE id = ?", [agora(), agora(), $lead_id]);
    q('INSERT INTO atividades (lead_id, cliente_id, usuario_id, tipo, texto, criado_em) VALUES (?,?,?,?,?,?)', [$lead_id, $l['cliente_id'], usuario()['id'], 'etapa', ETAPAS[$l['etapa']] . ' → Fechado', agora()]);
    q('INSERT INTO tarefas (lead_id, cliente_id, usuario_id, titulo, vence_em, criado_em) VALUES (?,?,?,?,?,?)', [$lead_id, $l['cliente_id'], usuario()['id'], 'Conferir reservas, documentos e vouchers', date('Y-m-d', strtotime('+2 days')), agora()]);
    if ($l['data_embarque'] && $l['data_embarque'] > hoje()) q('INSERT INTO tarefas (lead_id, cliente_id, usuario_id, titulo, vence_em, criado_em) VALUES (?,?,?,?,?,?)', [$lead_id, $l['cliente_id'], usuario()['id'], 'Mandar mensagem de boa viagem', date('Y-m-d', max(time(), strtotime($l['data_embarque'] . ' -2 days'))), agora()]);
    [$venda, $copiou] = $op ? proposta_para_venda($l, $op['id']) : [venda_do_lead($l), false];
    registrar('confirmou fechamento', 'lead #' . $lead_id);
    return [$venda, $copiou];
}

/** Valida e salva um upload de roteiro HTML. Devolve ['token'=>..,'nome'=>..] ou uma string "!erro". */
function roteiro_salvar_upload($proposta_id, $token_atual) {
    $f = $_FILES['arquivo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) return '!Não recebi o arquivo.';
    if ($f['size'] > 15 * 1024 * 1024) return '!Arquivo maior que 15 MB.';
    if (!in_array(strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)), ['html', 'htm'], true) || stripos(file_get_contents($f['tmp_name'], false, null, 0, 4096), '<html') === false)
        return '!Envie o arquivo .html do roteiro.';
    $nome = titulo_do_html($f['tmp_name']) ?: mb_substr(pathinfo($f['name'], PATHINFO_FILENAME), 0, 120);
    $tok = $token_atual ?: bin2hex(random_bytes(12));
    if (!move_uploaded_file($f['tmp_name'], pasta_roteiros() . '/' . $tok . '.html')) return '!Não consegui salvar o arquivo.';
    return ['token' => $tok, 'nome' => $nome];
}

function acao_propostas($a) {
    switch ($a) {
    case 'prop_nova':
        $l = um('SELECT * FROM leads WHERE id = ?', [(int)($_POST['lead_id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Lead não encontrado.');
        q('INSERT INTO propostas (lead_id, versao, titulo, validade, criado_por, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?)',
          [$l['id'], proxima_versao($l['id']), '', date('Y-m-d', strtotime('+7 days')), usuario()['id'], agora(), agora()]);
        $pid = (int)db()->lastInsertId();
        q('INSERT INTO proposta_opcoes (proposta_id, nome, ordem) VALUES (?,?,1)', [$pid, 'Opção A']);
        redirecionar('./?p=proposta&id=' . $pid, 'Rascunho criado. Lance os fornecedores e valores.');
    case 'prop_nova_versao':
        $p = proposta_ou_sai($_POST['id'] ?? 0);
        db()->beginTransaction();
        q('INSERT INTO propostas (lead_id, versao, titulo, validade, roteiro_url, observacoes, criado_por, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?,?,?)',
          [$p['lead_id'], proxima_versao($p['lead_id']), $p['titulo'], date('Y-m-d', strtotime('+7 days')), $p['roteiro_url'], $p['observacoes'], usuario()['id'], agora(), agora()]);
        $nid = (int)db()->lastInsertId();
        foreach (todos('SELECT * FROM proposta_opcoes WHERE proposta_id = ? ORDER BY ordem, id', [$p['id']]) as $o) {
            q('INSERT INTO proposta_opcoes (proposta_id, nome, ordem, moeda, taxa_cambio, markup, data_inicio, data_fim) VALUES (?,?,?,?,?,?,?,?)', [$nid, $o['nome'], $o['ordem'], $o['moeda'] ?? 'BRL', $o['taxa_cambio'], $o['markup'] ?? 0, $o['data_inicio'] ?? null, $o['data_fim'] ?? null]);
            $noid = (int)db()->lastInsertId();
            q("INSERT INTO proposta_itens (opcao_id, servico, detalhes, valor_cliente, fornecedor, pago_direto, custo, comissao)
               SELECT ?, servico, detalhes, valor_cliente, '', 0, 0, 0 FROM proposta_itens WHERE opcao_id = ?", [$noid, $o['id']]);
            // copia o link externo do roteiro; arquivo hospedado precisa ser reanexado na nova versão (token é único)
            if (!empty($o['roteiro_url'])) q('UPDATE proposta_opcoes SET roteiro_url = ? WHERE id = ?', [$o['roteiro_url'], $noid]);
        }
        db()->commit();
        redirecionar('./?p=proposta&id=' . $nid, 'Nova versão criada a partir da v' . $p['versao'] . '. Os roteiros em HTML não são copiados: reanexe-os nas opções. Depois marque como enviada.');
    case 'prop_salvar':
        $p = proposta_ou_sai($_POST['id'] ?? 0);
        $url = post('roteiro_url', 500);
        if ($url !== '' && !preg_match('#^https?://#i', $url)) redirecionar('./?p=proposta&id=' . $p['id'], '!O link do roteiro precisa começar com https://');
        if ($p['status'] === 'rascunho') q('UPDATE propostas SET titulo = ?, validade = ?, roteiro_url = ?, observacoes = ?, atualizado_em = ? WHERE id = ?', [post('titulo', 150), data_valida($_POST['validade'] ?? ''), $url, post('observacoes', 3000), agora(), $p['id']]);
        else q('UPDATE propostas SET roteiro_url = ?, observacoes = ?, atualizado_em = ? WHERE id = ?', [$url, post('observacoes', 3000), agora(), $p['id']]);
        redirecionar('./?p=proposta&id=' . $p['id'], 'Proposta salva.');
    case 'prop_item_salvar':
        $o = um('SELECT o.*, p.status, p.id pid FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [(int)($_POST['opcao_id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Opção não encontrada.');
        exigir_rascunho(['status' => $o['status'], 'id' => $o['pid']]);
        $vc = num_valor($_POST['valor_cliente'] ?? '') ?? 0;
        $serv = in_array($_POST['servico'] ?? '', SERVICOS, true) ? $_POST['servico'] : 'Outro';
        if ($vc <= 0) redirecionar('./?p=proposta&id=' . $o['pid'], '!Informe o valor do serviço.');
        if (!empty($_POST['id']) && valor('SELECT 1 FROM proposta_itens WHERE id = ? AND opcao_id = ?', [(int)$_POST['id'], $o['id']]))
            q('UPDATE proposta_itens SET servico=?, detalhes=?, valor_cliente=? WHERE id=?', [$serv, post('detalhes', 300), $vc, (int)$_POST['id']]);
        else q("INSERT INTO proposta_itens (servico, detalhes, valor_cliente, opcao_id, fornecedor, pago_direto, custo, comissao) VALUES (?,?,?,?,'',0,0,0)", [$serv, post('detalhes', 300), $vc, $o['id']]);
        q('UPDATE propostas SET atualizado_em = ? WHERE id = ?', [agora(), $o['pid']]);
        redirecionar('./?p=proposta&id=' . $o['pid'], 'Serviço salvo.');
    case 'prop_item_excluir':
        $i = um('SELECT i.id, p.id pid, p.status FROM proposta_itens i JOIN proposta_opcoes o ON o.id = i.opcao_id JOIN propostas p ON p.id = o.proposta_id WHERE i.id = ?', [(int)($_POST['id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Item não encontrado.');
        exigir_rascunho(['status' => $i['status'], 'id' => $i['pid']]);
        q('DELETE FROM proposta_itens WHERE id = ?', [$i['id']]);
        redirecionar('./?p=proposta&id=' . $i['pid'], 'Item excluído.');
    case 'prop_opcao_nova':
        $p = proposta_ou_sai($_POST['proposta_id'] ?? 0); exigir_rascunho($p);
        q('INSERT INTO proposta_opcoes (proposta_id, nome, ordem) VALUES (?,?,?)', [$p['id'], post('nome', 120) ?: 'Nova opção', (int)valor('SELECT COALESCE(MAX(ordem),0)+1 FROM proposta_opcoes WHERE proposta_id = ?', [$p['id']])]);
        $noid = (int)db()->lastInsertId();
        if (!empty($_POST['copiar']) && ($pri = valor('SELECT id FROM proposta_opcoes WHERE proposta_id = ? AND id <> ? ORDER BY ordem, id LIMIT 1', [$p['id'], $noid])))
            q('UPDATE proposta_opcoes SET moeda = (SELECT moeda FROM proposta_opcoes WHERE id = ?), taxa_cambio = (SELECT taxa_cambio FROM proposta_opcoes WHERE id = ?), markup = (SELECT markup FROM proposta_opcoes WHERE id = ?), data_inicio = (SELECT data_inicio FROM proposta_opcoes WHERE id = ?), data_fim = (SELECT data_fim FROM proposta_opcoes WHERE id = ?) WHERE id = ?', [$pri, $pri, $pri, $pri, $pri, $noid]);
            q("INSERT INTO proposta_itens (opcao_id, servico, detalhes, valor_cliente, fornecedor, pago_direto, custo, comissao) SELECT ?, servico, detalhes, valor_cliente, '', 0, 0, 0 FROM proposta_itens WHERE opcao_id = ?", [$noid, $pri]);
        redirecionar('./?p=proposta&id=' . $p['id'], 'Opção adicionada.');
    case 'prop_opcao_moeda':
        $o = um('SELECT o.*, p.status FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [(int)($_POST['id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Opção não encontrada.');
        exigir_rascunho(['status' => $o['status'], 'id' => $o['proposta_id']]);
        $moeda = isset(MOEDAS[$_POST['moeda'] ?? '']) ? $_POST['moeda'] : 'BRL';
        $taxa = $moeda === 'BRL' ? null : (num_valor($_POST['taxa_cambio'] ?? '') ?: null);
        q('UPDATE proposta_opcoes SET moeda = ?, taxa_cambio = ? WHERE id = ?', [$moeda, $taxa, $o['id']]);
        redirecionar('./?p=proposta&id=' . $o['proposta_id'], 'Moeda atualizada.');
    case 'prop_opcao_datas':
        $o = um('SELECT o.*, p.status FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [(int)($_POST['id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Opção não encontrada.');
        exigir_rascunho(['status' => $o['status'], 'id' => $o['proposta_id']]);
        $di = data_valida($_POST['data_inicio'] ?? ''); $df = data_valida($_POST['data_fim'] ?? '');
        if ($di && $df && $df < $di) redirecionar('./?p=proposta&id=' . $o['proposta_id'], '!A data de fim não pode ser antes do início.');
        q('UPDATE proposta_opcoes SET data_inicio = ?, data_fim = ? WHERE id = ?', [$di, $df, $o['id']]);
        redirecionar('./?p=proposta&id=' . $o['proposta_id'], 'Datas da viagem salvas.');
    case 'prop_opcao_markup':
        $o = um('SELECT o.*, p.status FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [(int)($_POST['id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Opção não encontrada.');
        exigir_rascunho(['status' => $o['status'], 'id' => $o['proposta_id']]);
        q('UPDATE proposta_opcoes SET markup = ? WHERE id = ?', [num_valor($_POST['markup'] ?? '') ?? 0, $o['id']]);
        redirecionar('./?p=proposta&id=' . $o['proposta_id'], 'Markup atualizado.');
    case 'prop_opcao_renomear': case 'prop_opcao_excluir':
        $o = um('SELECT o.*, p.status FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [(int)($_POST['id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Opção não encontrada.');
        exigir_rascunho(['status' => $o['status'], 'id' => $o['proposta_id']]);
        if ($a === 'prop_opcao_renomear') q('UPDATE proposta_opcoes SET nome = ? WHERE id = ?', [post('nome', 120) ?: $o['nome'], $o['id']]);
        elseif (valor('SELECT COUNT(*) FROM proposta_opcoes WHERE proposta_id = ?', [$o['proposta_id']]) > 1) q('DELETE FROM proposta_opcoes WHERE id = ?', [$o['id']]);
        redirecionar('./?p=proposta&id=' . $o['proposta_id'], 'Opção atualizada.');
    case 'prop_enviar':
        $p = proposta_ou_sai($_POST['id'] ?? 0); exigir_rascunho($p);
        $opcoes = todos('SELECT * FROM proposta_opcoes WHERE proposta_id = ? ORDER BY ordem, id', [$p['id']]);
        if (!$opcoes) redirecionar('./?p=proposta&id=' . $p['id'], '!Crie ao menos uma opção antes de enviar.');
        // TRAVA 1: toda opção precisa de roteiro (HTML anexado OU link externo)
        $semRoteiro = [];
        foreach ($opcoes as $op) if (empty($op['roteiro_token']) && empty($op['roteiro_url'])) $semRoteiro[] = $op['nome'];
        if ($semRoteiro) redirecionar('./?p=proposta&id=' . $p['id'], '!Falta anexar o roteiro em: ' . implode(', ', $semRoteiro) . '. Anexe o roteiro (ou informe o link) de cada opção antes de enviar.');
        // TRAVA 2: nenhuma opção pode ter total zero (sem serviços)
        $semValor = [];
        foreach ($opcoes as $op) if (resumo_opcao($op['id'])['total'] <= 0) $semValor[] = $op['nome'];
        if ($semValor) redirecionar('./?p=proposta&id=' . $p['id'], '!Sem valor em: ' . implode(', ', $semValor) . '. Lance ao menos um serviço com valor em cada opção.');
        $l = um('SELECT l.*, c.telefone, c.email FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.id = ?', [$p['lead_id']]);
        // AVISOS (não bloqueiam; a Vanessa confirma): sem contato do cliente ou cotação vencida
        if (empty($_POST['confirmar'])) {
            $avisos = [];
            if (empty($l['telefone']) && empty($l['email'])) $avisos[] = 'o cliente não tem WhatsApp nem e-mail cadastrado (você não terá como enviar o link por aqui)';
            if (!empty($p['validade']) && $p['validade'] < hoje()) $avisos[] = 'a validade da cotação (' . data_br($p['validade']) . ') já passou';
            if ($avisos) redirecionar('./?p=proposta&id=' . $p['id'] . '&confirmar_envio=1', '?Atenção: ' . implode('; ', $avisos) . '. Clique em "Enviar mesmo assim" para continuar.');
        }
        db()->beginTransaction();
        q("UPDATE propostas SET status = 'substituida' WHERE lead_id = ? AND status = 'enviada'", [$l['id']]);
        q("UPDATE propostas SET status = 'enviada', enviada_em = ?, atualizado_em = ? WHERE id = ?", [agora(), agora(), $p['id']]);
        $prim = valor('SELECT id FROM proposta_opcoes WHERE proposta_id = ? ORDER BY ordem, id LIMIT 1', [$p['id']]);
        q('UPDATE leads SET valor_estimado = ?, atualizado_em = ? WHERE id = ?', [resumo_opcao($prim)['total'], agora(), $l['id']]);
        if (in_array($l['etapa'], ['novo', 'conversa', 'roteiro', 'ajustes'], true)) {
            q("UPDATE leads SET etapa = 'proposta', etapa_desde = ? WHERE id = ?", [agora(), $l['id']]);
            q('INSERT INTO atividades (lead_id, cliente_id, usuario_id, tipo, texto, criado_em) VALUES (?,?,?,?,?,?)', [$l['id'], $l['cliente_id'], usuario()['id'], 'etapa', ETAPAS[$l['etapa']] . ' → Proposta enviada', agora()]);
        }
        nota_lead($l['id'], $l['cliente_id'], 'Proposta v' . $p['versao'] . ' enviada' . ($p['validade'] ? ' (válida até ' . data_br($p['validade']) . ')' : '') . '.');
        q('INSERT INTO tarefas (lead_id, cliente_id, usuario_id, titulo, vence_em, criado_em) VALUES (?,?,?,?,?,?)', [$l['id'], $l['cliente_id'], usuario()['id'], 'Acompanhar a proposta v' . $p['versao'], date('Y-m-d', strtotime('+3 days')), agora()]);
        db()->commit();
        registrar('enviou proposta', 'v' . $p['versao'] . ' · lead #' . $l['id']);
        redirecionar('./?p=proposta&id=' . $p['id'], 'Proposta v' . $p['versao'] . ' marcada como enviada. O lead foi para "Proposta enviada".');
    case 'prop_aprovar':
        $oid = (int)($_POST['opcao_id'] ?? 0);
        $st = valor('SELECT p.status FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [$oid]);
        if ($st !== 'enviada') redirecionar('./?p=funil', '!Só propostas enviadas podem ser aprovadas.');
        db()->beginTransaction(); $lead_id = aprovar_opcao($oid); db()->commit();
        redirecionar('./?p=lead&id=' . $lead_id . '#etapa', 'Opção aprovada pelo cliente. O lead foi para "Aprovada pelo Cliente/Aguardando fechamento". Quando receber o sinal e confirmar, clique em "Confirmar fechamento".');
    case 'prop_fechar':
        $lead_id = (int)($_POST['lead_id'] ?? 0);
        $l = um('SELECT * FROM leads WHERE id = ?', [$lead_id]);
        if (!$l || $l['etapa'] !== 'aprovada') redirecionar('./?p=funil', '!Este lead não está aguardando fechamento.');
        db()->beginTransaction(); [$venda, $copiou] = confirmar_fechamento($lead_id); db()->commit();
        if (pode_financeiro()) redirecionar('./?p=venda&id=' . $venda['id'], $copiou ? 'Fechamento confirmado! Os fornecedores e valores da proposta já estão no Financeiro. Lance os pagamentos do cliente.' : 'Fechamento confirmado. A venda já tinha lançamentos, então os valores não foram copiados de novo.');
        redirecionar('./?p=lead&id=' . $lead_id, 'Fechamento confirmado!');
    case 'prop_recusar':
        $p = proposta_ou_sai($_POST['id'] ?? 0);
        q("UPDATE propostas SET status = 'recusada', decidida_em = ? WHERE id = ? AND status = 'enviada'", [agora(), $p['id']]);
        nota_lead($p['lead_id'], $p['cliente_id'], 'Proposta v' . $p['versao'] . ' recusada pelo cliente.');
        redirecionar('./?p=lead&id=' . $p['lead_id'] . '#propostas', 'Proposta marcada como recusada. Se for o caso, crie uma nova versão ou mova o lead para Perdido.');
    case 'prop_rascunho':
        $p = proposta_ou_sai($_POST['id'] ?? 0);
        if ($p['status'] !== 'enviada') redirecionar('./?p=proposta&id=' . $p['id'], '!Só uma proposta enviada pode voltar para rascunho.');
        $l = um('SELECT * FROM leads WHERE id = ?', [$p['lead_id']]);
        if (!in_array($l['etapa'], ['proposta', 'ajustes', 'novo', 'conversa', 'roteiro'], true)) redirecionar('./?p=proposta&id=' . $p['id'], '!Esta proposta já foi aprovada pelo cliente. Para alterar, crie uma nova versão.');
        db()->beginTransaction();
        q("UPDATE propostas SET status = 'rascunho', enviada_em = NULL, atualizado_em = ? WHERE id = ?", [agora(), $p['id']]);
        // remove a tarefa de acompanhamento que o envio criou, se ainda pendente
        q("DELETE FROM tarefas WHERE lead_id = ? AND feita = 0 AND titulo LIKE 'Acompanhar a proposta%'", [$p['lead_id']]);
        if (in_array($l['etapa'], ['proposta', 'ajustes'], true)) {
            q("UPDATE leads SET etapa = 'roteiro', etapa_desde = ?, atualizado_em = ? WHERE id = ?", [agora(), agora(), $l['id']]);
            q('INSERT INTO atividades (lead_id, cliente_id, usuario_id, tipo, texto, criado_em) VALUES (?,?,?,?,?,?)', [$l['id'], $l['cliente_id'], usuario()['id'], 'etapa', ETAPAS[$l['etapa']] . ' → Montando roteiro', agora()]);
        }
        nota_lead($p['lead_id'], $p['cliente_id'], 'Proposta v' . $p['versao'] . ' voltou para rascunho.');
        db()->commit();
        registrar('reabriu proposta', 'v' . $p['versao'] . ' · lead #' . $p['lead_id']);
        redirecionar('./?p=proposta&id=' . $p['id'], 'Proposta reaberta como rascunho. Faça os ajustes e marque como enviada de novo.');
    case 'prop_excluir':
        $p = proposta_ou_sai($_POST['id'] ?? 0); exigir_rascunho($p);
        foreach (todos('SELECT roteiro_token FROM proposta_opcoes WHERE proposta_id = ? AND roteiro_token IS NOT NULL', [$p['id']]) as $rt) @unlink(pasta_roteiros() . '/' . $rt['roteiro_token'] . '.html');
        q('DELETE FROM propostas WHERE id = ?', [$p['id']]);
        redirecionar('./?p=lead&id=' . $p['lead_id'] . '#propostas', 'Rascunho excluído.');
    case 'prop_roteiro_enviar':
        $o = um('SELECT o.*, p.status, p.id pid, p.lead_id FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [(int)($_POST['opcao_id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Opção não encontrada.');
        exigir_rascunho(['status' => $o['status'], 'id' => $o['pid']]);
        $tok = roteiro_salvar_upload($o['pid'], $o['roteiro_token']);
        if (is_string($tok) && str_starts_with($tok, '!')) redirecionar('./?p=proposta&id=' . $o['pid'], $tok);
        q('UPDATE proposta_opcoes SET roteiro_token = ?, roteiro_nome = ? WHERE id = ?', [$tok['token'], $tok['nome'], $o['id']]);
        registrar('anexou roteiro à opção', 'opção #' . $o['id'] . ' · lead #' . $o['lead_id']);
        redirecionar('./?p=proposta&id=' . $o['pid'], 'Roteiro anexado à opção. O link para o cliente está pronto.');
    case 'prop_roteiro_remover':
        $o = um('SELECT o.*, p.id pid FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [(int)($_POST['opcao_id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Opção não encontrada.');
        if ($o['roteiro_token']) @unlink(pasta_roteiros() . '/' . $o['roteiro_token'] . '.html');
        q('UPDATE proposta_opcoes SET roteiro_token = NULL, roteiro_nome = NULL WHERE id = ?', [$o['id']]);
        redirecionar('./?p=proposta&id=' . $o['pid'], 'Roteiro removido desta opção.');
    case 'prop_opcao_roteiro':
        $p = proposta_ou_sai($_POST['proposta_id'] ?? 0); exigir_rascunho($p);
        $r = roteiro_salvar_upload($p['id'], null);
        if (is_string($r) && str_starts_with($r, '!')) redirecionar('./?p=proposta&id=' . $p['id'], $r);
        $nome = $r['nome'] ?: 'Nova opção';
        q('INSERT INTO proposta_opcoes (proposta_id, nome, ordem, roteiro_token, roteiro_nome) VALUES (?,?,?,?,?)',
          [$p['id'], $nome, (int)valor('SELECT COALESCE(MAX(ordem),0)+1 FROM proposta_opcoes WHERE proposta_id = ?', [$p['id']]), $r['token'], $r['nome']]);
        registrar('criou opção a partir de roteiro', 'lead #' . $p['lead_id']);
        redirecionar('./?p=proposta&id=' . $p['id'], 'Opção "' . $nome . '" criada com o roteiro anexado. Agora lance os valores dela.');
    }
    http_response_code(404); exit('Ação desconhecida.');
}

// ================= PDF da proposta (via impressão do navegador) =================
function proposta_pdf($id) {
    $p = um('SELECT p.*, l.cliente_id, l.destino, l.codigo, c.nome FROM propostas p JOIN leads l ON l.id = p.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE p.id = ?', [$id]);
    if (!$p) redirecionar('./?p=funil', '!Proposta não encontrada.');
    $ops = todos('SELECT * FROM proposta_opcoes WHERE proposta_id = ? ORDER BY ordem, id', [$id]);
    $parc = todos('SELECT * FROM venda_parcelas WHERE venda_id = (SELECT id FROM vendas WHERE lead_id = ?) ORDER BY vencimento, id', [$p['lead_id']]);
    registrar('gerou PDF da proposta', $p['codigo'] . '-v' . $p['versao']);
    $logo = ''; $f = dirname(__DIR__, 2) . '/img/logo.png';
    if (is_file($f)) $logo = 'data:image/png;base64,' . base64_encode(file_get_contents($f));
    header('Content-Type: text/html; charset=utf-8'); header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
    ?><!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Proposta <?= h($p['codigo']) ?> · <?= h($p['nome']) ?></title>
<style>
@page { margin: 18mm 16mm; }
:root{--ai:#1B2A4A;--sora:#3D5A8A;--claro:#8FA9D3;--linha:#E2E5EA;--t2:#5F6775}
*{box-sizing:border-box} body{margin:0;font-family:Georgia,'Times New Roman',serif;color:#2A2E35;line-height:1.55;font-size:15px}
.barra{background:var(--ai);color:#fff;padding:7px 16px;text-align:center;font-family:'Segoe UI',Arial,sans-serif;font-size:13px}
.barra button{font:inherit;background:#fff;color:var(--ai);border:none;border-radius:5px;padding:6px 14px;font-weight:600;cursor:pointer;margin-left:8px}
.pg{max-width:760px;margin:0 auto;padding:30px 24px}
.topo{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:3px solid var(--ai);padding-bottom:18px}
.topo img{width:62px} .marca b{font-size:20px;color:var(--ai);display:block;line-height:1.1} .marca span{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:var(--t2)}
.proto{text-align:right;font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:var(--t2)}
h1{font-size:30px;color:var(--ai);margin:26px 0 4px;font-weight:700}
.sub{font-family:'Segoe UI',Arial,sans-serif;color:var(--t2);font-size:14px;margin:0 0 20px}
.opcao{border:1px solid var(--linha);border-radius:10px;padding:18px 22px;margin:0 0 16px;page-break-inside:avoid}
.opcao h2{font-size:20px;color:var(--ai);margin:0 0 12px;display:flex;justify-content:space-between;align-items:baseline;gap:12px}
.opcao h2 .val{font-size:22px;white-space:nowrap}
.serv{list-style:none;margin:0;padding:0}
.serv li{padding:7px 0;border-top:1px solid var(--linha);display:flex;justify-content:space-between;gap:14px}
.serv li:first-child{border-top:none} .serv small{color:var(--t2);font-family:'Segoe UI',Arial,sans-serif}
.total{display:flex;justify-content:space-between;border-top:2px solid var(--ai);margin-top:10px;padding-top:10px;font-size:17px;color:var(--ai);font-weight:700}
.bloco{margin:22px 0}.bloco h3{font-size:16px;color:var(--ai);margin:0 0 6px}
.cond{font-family:'Segoe UI',Arial,sans-serif;font-size:14px}
.cond li{margin:3px 0}
.frase{font-style:italic;color:var(--ai);font-size:17px;border-left:3px solid var(--sora);padding-left:16px;margin:26px 0}
.rodape{border-top:1px solid var(--linha);margin-top:28px;padding-top:14px;font-family:'Segoe UI',Arial,sans-serif;font-size:12.5px;color:var(--t2);text-align:center}
@media print{.barra{display:none}.pg{max-width:none;padding:0}}
</style></head><body>
<div class="barra">Use "Salvar como PDF" na janela de impressão.<button onclick="window.print()">Imprimir / Salvar PDF</button></div>
<div class="pg">
  <div class="topo"><div style="display:flex;gap:14px;align-items:center"><?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php endif; ?><div class="marca"><b>Vanessa Viagens e Turismo</b><span>Curadoria de viagens · desde 2011</span></div></div>
    <div class="proto">Proposta <?= h($p['codigo']) ?>-v<?= (int)$p['versao'] ?><br><?= $p['validade'] ? 'Válida até ' . data_br($p['validade']) : 'Emitida em ' . data_br(hoje()) ?></div></div>
  <h1><?= h(rotulo_proposta($p, $ops) ?: 'Proposta de viagem') ?></h1>
  <p class="sub">Preparada especialmente para <?= h($p['nome']) ?></p>
  <?php foreach ($ops as $o): $r = resumo_opcao($o['id']); $itens = todos('SELECT * FROM proposta_itens WHERE opcao_id = ? ORDER BY id', [$o['id']]); ?>
    <div class="opcao"><h2><span><?= count($ops) > 1 ? h($o['nome']) : 'A sua viagem' ?></span><span class="val"><?= moeda_fmt($r['total'], $r['moeda']) ?></span></h2>
      <?php if ($o['data_inicio'] && $o['data_fim']): ?><div style="font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:var(--t2);margin:-6px 0 10px"><?= data_br($o['data_inicio']) ?> a <?= data_br($o['data_fim']) ?> · <?= h(duracao_viagem($o['data_inicio'], $o['data_fim'])) ?></div><?php endif; ?>
      <ul class="serv"><?php foreach ($itens as $i): ?><li><span><b><?= h($i['servico']) ?></b><?= $i['detalhes'] ? '<br><small>' . h($i['detalhes']) . '</small>' : '' ?></span></li><?php endforeach; ?></ul>
      <div class="total"><span>Investimento total</span><span><?= moeda_fmt($r['total'], $r['moeda']) ?></span></div>
      <?php if ($r['moeda'] !== 'BRL'): ?><div style="text-align:right;font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:var(--t2);margin-top:4px">valores em <?= h($r['moeda']) ?></div><?php endif; ?></div>
  <?php endforeach; ?>
  <?php if ($parc): ?><div class="bloco"><h3>Condições de pagamento</h3><ul class="cond">
    <?php foreach ($parc as $x): ?><li><?= h($x['descricao']) ?>: <b><?= reais($x['valor']) ?></b> — venc. <?= data_br($x['vencimento']) ?><?= $x['forma'] ? ' (' . h($x['forma']) . ')' : '' ?></li><?php endforeach; ?>
  </ul></div><?php endif; ?>
  <?php if ($p['observacoes']): ?><div class="bloco"><h3>Observações</h3><p class="cond" style="white-space:pre-wrap;margin:0"><?= h($p['observacoes']) ?></p></div><?php endif; ?>
  <p class="frase">"Eu cuido de você e da sua família. Apenas relaxe e curta o seu momento."<br><span style="font-size:14px;font-style:normal;color:var(--t2)">— Vanessa</span></p>
  <div class="rodape">Vanessa Viagens e Turismo · WhatsApp (11) 97543-9074 · vanessa@vanessaviagenseturismo.com.br · @vanessaviagenseturismo<br>CNPJ 35.731.239/0001-16 · Cadastur 35.731.239/0001-16 · Valores sujeitos a confirmação de disponibilidade e câmbio no fechamento.</div>
</div>
<script>setTimeout(function(){try{window.print()}catch(e){}},400)</script>
</body></html><?php exit;
}

// ================= Arquivo para o Claude montar o roteiro em HTML =================
function proposta_claude($id) {
    $p = um('SELECT p.*, l.cliente_id, l.destino, l.periodo, l.duracao, l.adultos, l.criancas, l.ocasiao, l.detalhes, l.codigo, c.nome, c.preferencias FROM propostas p JOIN leads l ON l.id = p.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE p.id = ?', [$id]);
    if (!$p) redirecionar('./?p=funil', '!Proposta não encontrada.');
    $oid = (int)($_GET['opcao'] ?? 0);
    if ($oid) { $ops = todos('SELECT * FROM proposta_opcoes WHERE proposta_id = ? AND id = ?', [$id, $oid]); if (!$ops) redirecionar('./?p=proposta&id=' . $id, '!Opção não encontrada.'); }
    else $ops = todos('SELECT * FROM proposta_opcoes WHERE proposta_id = ? ORDER BY ordem, id', [$id]);
    $uma = count($ops) === 1;
    registrar('gerou briefing para o Claude', $p['codigo'] . '-v' . $p['versao'] . ($oid ? ' · ' . $ops[0]['nome'] : ''));
    $L = [];
    $L[] = 'INSTRUÇÕES PARA O CLAUDE';
    $L[] = 'Monte um roteiro de viagem em HTML no padrão visual "passaporte" da Vanessa Viagens e Turismo, usando a skill de roteiros. Na seção de investimento, mostre APENAS o valor total ao cliente na moeda indicada (não liste valor por serviço). NÃO invente preços nem serviços além dos listados.' . (!$uma ? ' Quando houver mais de uma opção, apresente-as para o cliente comparar.' : '');
    $L[] = '';
    $L[] = '=== DADOS DA VIAGEM ===';
    $L[] = 'Proposta: ' . $p['codigo'] . '-v' . $p['versao'];
    $L[] = 'Cliente: ' . $p['nome'];
    if ($p['titulo']) $L[] = 'Título sugerido: ' . $p['titulo'];
    $L[] = 'Destino: ' . ($p['destino'] ?: '(a definir)');
    if ($p['periodo']) $L[] = 'Período: ' . $p['periodo'];
    if ($p['duracao']) $L[] = 'Duração: ' . $p['duracao'];
    $pax = trim(($p['adultos'] ? $p['adultos'] . ' adulto(s)' : '') . ($p['criancas'] ? ' · crianças/jovens: ' . $p['criancas'] : ''));
    if ($pax) $L[] = 'Quem viaja: ' . $pax;
    if ($p['ocasiao']) $L[] = 'Ocasião: ' . $p['ocasiao'];
    if ($p['preferencias']) $L[] = 'Preferências do cliente: ' . preg_replace('/\s+/', ' ', $p['preferencias']);
    if ($p['detalhes']) $L[] = 'Observações do atendimento: ' . preg_replace('/\s+/', ' ', $p['detalhes']);
    if ($p['validade']) $L[] = 'Validade da cotação: ' . data_br($p['validade']);
    foreach ($ops as $k => $o) {
        $r = resumo_opcao($o['id']);
        $itens = todos('SELECT servico, detalhes FROM proposta_itens WHERE opcao_id = ? ORDER BY id', [$o['id']]);
        $L[] = '';
        $L[] = '=== ' . ($uma ? 'ROTEIRO' : strtoupper($o['nome'])) . ' · VALOR TOTAL AO CLIENTE: ' . moeda_fmt($r['total'], $r['moeda']) . ' ===';
        if ($o['data_inicio'] && $o['data_fim']) $L[] = 'Período: ' . data_br($o['data_inicio']) . ' a ' . data_br($o['data_fim']) . ' (' . duracao_viagem($o['data_inicio'], $o['data_fim']) . ')';
        $L[] = 'Serviços incluídos (sem valor individual):';
        foreach ($itens as $i) $L[] = '- ' . $i['servico'] . ($i['detalhes'] ? ': ' . $i['detalhes'] : '');
    }
    $L[] = '';
    $L[] = '(Gerado pelo VV360. Mostre ao cliente apenas o VALOR TOTAL na moeda indicada; não há valores por item e não há custos/comissões.)';
    $txt = implode("\n", $L);
    header('Content-Type: text/plain; charset=utf-8');
    $sufixo = $oid ? '-' . preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $ops[0]['nome'])) : '';
    header('Content-Disposition: attachment; filename="roteiro-' . $p['codigo'] . '-v' . $p['versao'] . $sufixo . '.txt"');
    echo $txt; exit;
}
