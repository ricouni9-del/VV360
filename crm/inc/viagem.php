<?php
// ================= Pós-fechamento: documentos da viagem e pesquisa =================

function portal_do_lead($lead_id, $tipo, $criar = false) {
    $p = um('SELECT * FROM portais WHERE lead_id = ? AND tipo = ?', [$lead_id, $tipo]);
    if ($p || !$criar) return $p;
    $dias = (int)cfg('doc_retencao_dias', 7);
    $l = um('SELECT data_retorno FROM leads WHERE id = ?', [$lead_id]);
    $base = ($l && $l['data_retorno']) ? $l['data_retorno'] : hoje();
    $expira = date('Y-m-d', strtotime($base . ' +' . $dias . ' days'));
    q('INSERT INTO portais (lead_id, tipo, token, criado_em, expira_em) VALUES (?,?,?,?,?)',
      [$lead_id, $tipo, bin2hex(random_bytes(12)), agora(), $expira]);
    return um('SELECT * FROM portais WHERE lead_id = ? AND tipo = ?', [$lead_id, $tipo]);
}
function url_portal($tipo, $token) { return url_base() . ($tipo === 'docs' ? '/d/?t=' : '/s/?t=') . $token; }
function portal_valido($p) { return $p && !$p['revogado'] && (!$p['expira_em'] || $p['expira_em'] >= hoje()); }

function campos_data_doc() { return ['hospedagem' => ['Check-in', 'Check-out'], 'seguro' => ['Início da vigência', 'Fim da vigência'], 'voos' => ['Data', 'Hora'], 'passeios' => ['Data', 'Hora'], 'transfer' => ['Data', 'Hora']]; }

// ---------- Cartão "Documentos da viagem" no lead ----------
/** Mapeia um serviço da venda para a categoria de documento. */
function categoria_do_servico($servico) {
    $s = mb_strtolower((string)$servico);
    if (strpos($s, 'hosped') !== false) return 'hospedagem';
    if (strpos($s, 'aére') !== false || strpos($s, 'aere') !== false || strpos($s, 'voo') !== false) return 'voos';
    if (strpos($s, 'passe') !== false || strpos($s, 'ingress') !== false) return 'passeios';
    if (strpos($s, 'trasl') !== false || strpos($s, 'transf') !== false) return 'transfer';
    if (strpos($s, 'seguro') !== false) return 'seguro';
    return 'outros';
}
/** Datas sugeridas (início, fim) para um serviço, conforme a categoria e as datas da viagem. */
function datas_sugeridas($cat, $embarque, $retorno) {
    if ($cat === 'hospedagem') return [$embarque, $retorno];
    if ($cat === 'seguro') return [$embarque, $retorno];
    return [$embarque, null]; // voos/passeios/transfer: começa no embarque
}

function cartao_documentos_lead($l) {
    $docs = todos('SELECT * FROM viagem_docs WHERE lead_id = ? ORDER BY inicio IS NULL, inicio, id', [$l['id']]);
    $portal = portal_do_lead($l['id'], 'docs');
    $usado = array_sum(array_column($docs, 'tamanho'));
    $maxMb = (int)cfg('doc_max_mb', 10);
    $icone = fn($ext) => ['pdf' => 'ti-file-type-pdf', 'jpg' => 'ti-photo', 'jpeg' => 'ti-photo', 'png' => 'ti-photo', 'html' => 'ti-file-code'][$ext] ?? 'ti-file'; ?>
  <div class="cartao" id="documentos"><h2>Documentos da viagem</h2>
    <p class="mudo" style="margin-top:-6px">Vouchers e documentos que o cliente acessa pelo link, durante a viagem.</p>
    <?php foreach ($docs as $d): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-top:1px solid var(--linha)">
        <span style="font-size:13px;color:var(--t2);width:24px;text-align:center">●</span>
        <div style="flex:1;min-width:0"><b style="font-weight:600;color:var(--ai)"><?= h($d['nome']) ?></b>
          <span class="mudo"> · <?= h(CATEGORIAS_DOC[$d['categoria']] ?? $d['categoria']) ?><?= $d['inicio'] ? ' · ' . data_br($d['inicio']) . ($d['fim'] && $d['fim'] !== $d['inicio'] ? ' a ' . data_br($d['fim']) : '') . ($d['hora'] ? ' ' . h($d['hora']) : '') : '' ?> · <?= number_format($d['tamanho'] / 1024, 0, ',', '.') ?> KB</span></div>
        <a class="btn btn-pq" href="./?p=baixardoc&id=<?= $d['id'] ?>" target="_blank" rel="noopener">Ver</a>
        <form method="post" action="./?p=acao&a=doc_excluir" onsubmit="return confirm('Remover este documento?')" style="margin:0"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn btn-pq btn-perigo">×</button></form>
      </div>
    <?php endforeach; ?>
    <?php if (!$docs): ?><p class="vazio">Nenhum documento ainda.</p><?php endif; ?>

    <?php
    // Checklist dos serviços vendidos que ainda não têm voucher anexado
    $venda = um('SELECT id FROM vendas WHERE lead_id = ?', [$l['id']]);
    $pendentes = [];
    if ($venda) {
        $jaNomes = array_map(fn($d) => mb_strtolower($d['nome']), $docs);
        foreach (todos("SELECT servico, detalhes FROM venda_itens WHERE venda_id = ? AND servico <> 'Markup do pacote' ORDER BY id", [$venda['id']]) as $it) {
            $nome = trim($it['servico'] . ($it['detalhes'] ? ' · ' . $it['detalhes'] : ''));
            if (in_array(mb_strtolower($nome), $jaNomes, true)) continue; // já anexado
            $pendentes[] = $it + ['nome' => $nome, 'cat' => categoria_do_servico($it['servico'])];
        }
    }
    if ($pendentes): $rotcat = campos_data_doc(); ?>
    <div style="margin-top:14px;border-top:1px solid var(--linha);padding-top:12px">
      <h3 style="font-family:var(--sans);font-size:14px">Serviços da venda sem voucher <span class="mudo" style="font-weight:400">(<?= count($pendentes) ?>)</span></h3>
      <p class="mudo" style="margin:2px 0 8px">Confirme a data real e anexe o voucher de cada um.</p>
      <?php foreach ($pendentes as $k => $pp): [$sdi, $sdf] = datas_sugeridas($pp['cat'], $l['data_embarque'], $l['data_retorno']); $labels = $rotcat[$pp['cat']] ?? ['Data', '']; ?>
        <form method="post" action="./?p=acao&a=doc_enviar" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;background:var(--cinza-bg);border-radius:8px;padding:10px;margin-bottom:8px"><?= csrf_campo() ?>
          <input type="hidden" name="lead_id" value="<?= $l['id'] ?>"><input type="hidden" name="nome" value="<?= h($pp['nome']) ?>"><input type="hidden" name="categoria" value="<?= h($pp['cat']) ?>">
          <div style="flex:1;min-width:180px"><b style="color:var(--ai);font-size:14px"><?= h($pp['servico']) ?></b><?= $pp['detalhes'] ? '<br><span class="mudo" style="font-size:12.5px">' . h($pp['detalhes']) . '</span>' : '' ?></div>
          <?php if ($labels[0]): ?><div><label style="margin-top:0;font-size:12px"><?= h($labels[0]) ?></label><input type="date" name="inicio" value="<?= h($sdi) ?>"></div><?php endif; ?>
          <?php if ($labels[1]): $ehHora = stripos($labels[1], 'hora') !== false; ?><div><label style="margin-top:0;font-size:12px"><?= h($labels[1]) ?></label><input type="<?= $ehHora ? 'time' : 'date' ?>" name="<?= $ehHora ? 'hora' : 'fim' ?>" value="<?= $ehHora ? '' : h($sdf) ?>"></div><?php endif; ?>
          <div><label style="margin-top:0;font-size:12px">Voucher</label><input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.html,.htm" required style="max-width:200px"></div>
          <button class="btn btn-pq btn-pri" style="margin-bottom:1px">Anexar</button>
        </form>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <details style="margin-top:12px" <?= $docs ? '' : 'open' ?>><summary style="cursor:pointer;font-weight:600;color:var(--sora)">+ Adicionar documento</summary>
      <form method="post" action="./?p=acao&a=doc_enviar" enctype="multipart/form-data" id="form-doc"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
        <label>Nome</label><input name="nome" placeholder="Ex.: Voucher Hotel Kyoto" required>
        <div class="linha"><div><label>Categoria</label><select name="categoria" id="doc-cat"><?= select_opcoes(CATEGORIAS_DOC, 'hospedagem') ?></select></div>
          <div id="doc-d1-wrap"><label id="doc-d1-lb">Check-in</label><input type="date" name="inicio" id="doc-d1"></div>
          <div id="doc-d2-wrap"><label id="doc-d2-lb">Check-out</label><input type="date" name="fim" id="doc-d2"></div>
          <div id="doc-hora-wrap" hidden><label>Hora</label><input type="time" name="hora" id="doc-hora"></div></div>
        <label>Arquivo (PDF, imagem ou HTML · até <?= $maxMb ?> MB)</label><input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.html,.htm" required>
        <p style="margin:10px 0 0"><button class="btn btn-pri">Enviar documento</button> <span class="mudo">As datas são opcionais.</span></p>
      </form>
    </details>

    <div class="cartao" style="background:var(--cinza-bg);margin:14px 0 0">
      <h3 style="font-family:var(--sans);font-size:14px">Link do cliente</h3>
      <?php if ($portal && portal_valido($portal)): $link = url_portal('docs', $portal['token']); ?>
        <p class="mudo" style="margin:4px 0">Trava: nascimento do titular<?= um('SELECT nascimento FROM clientes WHERE id = ?', [$l['cliente_id']])['nascimento'] ? '' : ' (não cadastrado — abre só com o link)' ?> · expira em <?= data_br($portal['expira_em']) ?></p>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <button class="btn btn-pq" type="button" onclick="navigator.clipboard.writeText(<?= h(json_encode($link)) ?>);this.textContent='Copiado'">Copiar link</button>
          <?php if ($z = link_whats(um('SELECT telefone FROM clientes WHERE id = ?', [$l['cliente_id']])['telefone'] ?? '', 'Olá! Reuni todos os documentos da sua viagem num link seguro: ' . $link)): ?><a class="btn btn-pq btn-zap" href="<?= h($z) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
          <a class="btn btn-pq" href="./?p=acao&a=doc_email&lead=<?= $l['id'] ?>" onclick="return confirm('Enviar o link dos documentos por e-mail ao cliente?')">E-mail</a>
          <form method="post" action="./?p=acao&a=portal_revogar" style="margin:0" onsubmit="return confirm('Revogar o link? O cliente deixa de acessar.')"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>"><input type="hidden" name="tipo" value="docs"><button class="btn btn-pq btn-perigo">Revogar</button></form>
        </div>
      <?php elseif ($portal && !portal_valido($portal)): ?>
        <p class="mudo" style="margin:4px 0"><?= $portal['revogado'] ? 'Link revogado.' : 'Link expirado em ' . data_br($portal['expira_em']) . '.' ?></p>
        <form method="post" action="./?p=acao&a=portal_criar" style="margin:0"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>"><input type="hidden" name="tipo" value="docs"><button class="btn btn-pq btn-pri">Gerar novo link</button></form>
      <?php else: ?>
        <p class="mudo" style="margin:4px 0">Gere o link quando os documentos estiverem prontos.</p>
        <form method="post" action="./?p=acao&a=portal_criar" style="margin:0"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>"><input type="hidden" name="tipo" value="docs"><button class="btn btn-pq btn-pri">Gerar link do cliente</button></form>
      <?php endif; ?>
    </div>
  </div>
  <script>(function(){var cat=document.getElementById('doc-cat');if(!cat)return;var rot={hospedagem:['Check-in','Check-out',false],seguro:['Início da vigência','Fim da vigência',false],voos:['Data','',true],passeios:['Data','',true],transfer:['Data','',true],roteiro:['','',false],outros:['','',false]};
  function upd(){var r=rot[cat.value]||['Data','',false];var w1=document.getElementById('doc-d1-wrap'),w2=document.getElementById('doc-d2-wrap'),wh=document.getElementById('doc-hora-wrap');
  w1.hidden=!r[0];document.getElementById('doc-d1-lb').textContent=r[0];w2.hidden=!r[1];if(r[1])document.getElementById('doc-d2-lb').textContent=r[1];wh.hidden=!r[2];}
  cat.addEventListener('change',upd);upd();})();</script>
<?php }

// ---------- Cartão de pesquisa no lead (pós-viagem) ----------
function cartao_pesquisa_lead($l) {
    $pesq = um('SELECT * FROM pesquisas WHERE lead_id = ? ORDER BY id DESC LIMIT 1', [$l['id']]);
    $portal = portal_do_lead($l['id'], 'pesquisa'); ?>
  <div class="cartao" id="pesquisa"><h2>Pesquisa de satisfação</h2>
    <?php if ($pesq && $pesq['respondida_em']): ?>
      <p style="margin-top:-4px">Respondida em <?= data_br($pesq['respondida_em']) ?> · nota geral <b><?= (int)$pesq['nota_geral'] ?>/5</b> · <?= h($pesq['recomenda'] ?: '') ?></p>
      <?php if ($pesq['comentario']): ?><p style="background:var(--cinza-bg);border-radius:8px;padding:10px 12px;margin:6px 0"><?= h($pesq['comentario']) ?><?= $pesq['autoriza_publicar'] ? ' <span class="tag novo">autorizou publicar</span>' : '' ?></p><?php endif; ?>
      <?php foreach (todos('SELECT * FROM pesquisa_itens WHERE pesquisa_id = ?', [$pesq['id']]) as $it): ?><div class="mudo" style="font-size:13px"><?= h($it['servico']) ?>: <?= str_repeat('★', (int)$it['nota']) . str_repeat('☆', 5 - (int)$it['nota']) ?></div><?php endforeach; ?>
      <?php if (!empty($pesq['prox_destino']) || !empty($pesq['prox_lead_id'])): ?>
        <div style="background:#E1F5EE;border-radius:8px;padding:10px 12px;margin:8px 0 0">
          <b style="color:#0F6E56;font-size:13px">✈ Quer viajar de novo!</b>
          <div class="mudo" style="font-size:13px;color:var(--tinta)"><?= h(trim(($pesq['prox_destino'] ?: 'destino a definir') . ($pesq['prox_quando'] ? ' · ' . $pesq['prox_quando'] : ''))) ?><?= $pesq['prox_contato'] ? ' · contatar a partir de ' . date('m/Y', strtotime($pesq['prox_contato'] . '-01')) : '' ?></div>
          <?php if ($pesq['prox_lead_id']): ?><a href="./?p=lead&id=<?= (int)$pesq['prox_lead_id'] ?>" style="font-size:13px">Abrir o novo lead →</a><?php endif; ?>
        </div>
      <?php endif; ?>
      <p style="margin:8px 0 0"><a href="./?p=satisfacao">Ver no painel de satisfação</a></p>
    <?php else: $link = url_portal('pesquisa', $portal['token'] ?? ''); ?>
      <p class="mudo" style="margin-top:-4px">Envie a pesquisa ao cliente. As perguntas usam os serviços da viagem.</p>
      <?php if ($portal && portal_valido($portal)): ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <button class="btn btn-pq" type="button" onclick="navigator.clipboard.writeText(<?= h(json_encode($link)) ?>);this.textContent='Copiado'">Copiar link</button>
          <?php if ($z = link_whats(um('SELECT telefone FROM clientes WHERE id = ?', [$l['cliente_id']])['telefone'] ?? '', 'Olá! Queremos muito saber como foi sua viagem. Leva 2 minutinhos: ' . $link)): ?><a class="btn btn-pq btn-zap" href="<?= h($z) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
          <a class="btn btn-pq" href="./?p=acao&a=pesq_email&lead=<?= $l['id'] ?>" onclick="return confirm('Enviar a pesquisa por e-mail ao cliente?')">E-mail</a>
        </div>
      <?php else: ?>
        <form method="post" action="./?p=acao&a=portal_criar" style="margin:0"><?= csrf_campo() ?><input type="hidden" name="lead_id" value="<?= $l['id'] ?>"><input type="hidden" name="tipo" value="pesquisa"><button class="btn btn-pq btn-pri">Gerar pesquisa</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php }

function baixar_doc($id) {
    $d = um('SELECT * FROM viagem_docs WHERE id = ?', [$id]);
    if (!$d) { http_response_code(404); exit('Documento não encontrado.'); }
    $arq = pasta_docs() . '/' . $d['arquivo'];
    if (!is_file($arq)) { http_response_code(404); exit('Arquivo ausente.'); }
    $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'html' => 'text/html', 'htm' => 'text/html'][$d['ext']] ?? 'application/octet-stream';
    registrar('abriu documento da viagem', $d['nome']);
    header('Content-Type: ' . $mime); header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\- ]/', '', $d['nome']) . '.' . $d['ext'] . '"');
    if ($d['ext'] === 'html' || $d['ext'] === 'htm') header('Content-Security-Policy: sandbox allow-popups');
    readfile($arq); exit;
}

// ---------- Painel de satisfação ----------
function pagina_satisfacao() {
    exigir_login();
    $resp = todos("SELECT p.*, l.codigo, l.destino, c.nome FROM pesquisas p JOIN leads l ON l.id = p.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE p.respondida_em IS NOT NULL ORDER BY p.respondida_em DESC");
    $n = count($resp);
    $media = $n ? round(array_sum(array_column($resp, 'nota_geral')) / $n, 1) : null;
    $recomendam = $n ? round(100 * count(array_filter($resp, fn($r) => $r['recomenda'] === 'Com certeza')) / $n) : null;
    $forn = todos("SELECT fornecedor, servico, AVG(nota) media, COUNT(*) n FROM pesquisa_itens i JOIN pesquisas p ON p.id = i.pesquisa_id WHERE p.respondida_em IS NOT NULL AND nota > 0 GROUP BY COALESCE(NULLIF(fornecedor,''), servico) ORDER BY media DESC");
    topo('Satisfação', 'satisfacao'); ?>
  <div class="cab"><h1>Satisfação</h1></div>
  <div class="metricas">
    <div class="metrica"><b><?= $media !== null ? number_format($media, 1, ',', '.') : '—' ?></b><span>Nota média geral</span></div>
    <div class="metrica"><b><?= $recomendam !== null ? $recomendam . '%' : '—' ?></b><span>Recomendam a agência</span></div>
    <div class="metrica"><b><?= $n ?></b><span>Respostas</span></div>
  </div>
  <div class="grade2">
    <div class="cartao"><h2>Fornecedores mais bem avaliados</h2>
      <?php if (!$forn): ?><p class="vazio">Ainda sem avaliações por serviço.</p><?php endif; ?>
      <?php foreach ($forn as $f): $m = round($f['media'], 1); $cor = $m >= 4 ? 'var(--ok)' : ($m >= 3 ? '#8A5A00' : 'var(--erro)'); ?>
        <div style="margin:8px 0"><div style="display:flex;justify-content:space-between;font-size:14px"><span><?= h($f['fornecedor'] ?: $f['servico']) ?> <span class="mudo">(<?= (int)$f['n'] ?>)</span></span><b><?= number_format($m, 1, ',', '.') ?></b></div>
          <div class="barra"><i style="width:<?= round($m / 5 * 100) ?>%;background:<?= $cor ?>"></i></div></div>
      <?php endforeach; ?>
    </div>
    <div class="cartao"><h2>Querem viajar de novo</h2>
      <?php $prox = todos("SELECT p.prox_destino, p.prox_quando, p.prox_contato, p.prox_lead_id, c.nome FROM pesquisas p JOIN clientes c ON c.id = p.cliente_id WHERE p.respondida_em IS NOT NULL AND (p.prox_destino IS NOT NULL OR p.prox_lead_id IS NOT NULL) ORDER BY p.respondida_em DESC LIMIT 12");
      if (!$prox): ?><p class="vazio">Ninguém sinalizou ainda.</p><?php endif; ?>
      <?php foreach ($prox as $x): ?><div style="padding:7px 0;border-top:1px solid var(--linha)"><?= $x['prox_lead_id'] ? '<a href="./?p=lead&id=' . (int)$x['prox_lead_id'] . '"><b>' . h($x['nome']) . '</b></a>' : '<b>' . h($x['nome']) . '</b>' ?> <span class="mudo">· <?= h($x['prox_destino'] ?: 'destino a definir') ?><?= $x['prox_quando'] ? ' · ' . h($x['prox_quando']) : '' ?><?= $x['prox_contato'] ? ' · contatar ' . date('m/Y', strtotime($x['prox_contato'] . '-01')) : '' ?></span></div><?php endforeach; ?>
    </div>
    <div class="cartao"><h2>Respostas recentes</h2>
      <?php if (!$resp): ?><p class="vazio">Nenhuma resposta ainda.</p><?php endif; ?>
      <?php foreach (array_slice($resp, 0, 15) as $r): ?>
        <div style="padding:8px 0;border-top:1px solid var(--linha)">
          <div style="display:flex;justify-content:space-between"><a href="./?p=lead&id=<?= $r['lead_id'] ?>"><b><?= h($r['nome']) ?></b></a> <span class="mudo">· <?= h($r['destino']) ?></span><span style="color:#C2410C">★ <?= number_format($r['nota_geral'], 1, ',', '.') ?></span></div>
          <?php if ($r['comentario']): ?><div class="mudo" style="font-size:13px;margin-top:2px">"<?= h(mb_substr($r['comentario'], 0, 120)) ?>"<?= $r['autoriza_publicar'] ? ' <span style="color:var(--sora)">· publicável</span>' : '' ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php rodape(); }

// ================= Entregas de vouchers avulsas (fora do funil) =================
function pagina_vouchers() {
    exigir_login();
    $b = trim($_GET['b'] ?? '');
    $par = []; $w = "l.etapa = 'entrega'";
    if ($b !== '') { $w .= ' AND (c.nome LIKE ? OR l.destino LIKE ?)'; $par = ["%$b%", "%$b%"]; }
    $lista = todos("SELECT l.*, c.nome, c.telefone,
                    (SELECT COUNT(*) FROM viagem_docs d WHERE d.lead_id = l.id) ndocs
                    FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE $w ORDER BY l.criado_em DESC LIMIT 300", $par);
    topo('Vouchers', 'vouchers'); ?>
  <div class="cab"><h1>Entrega de vouchers</h1><a class="btn btn-pri" href="./?p=vouchernovo">+ Nova entrega</a></div>
  <p class="mudo" style="margin-top:-10px">Envie vouchers ao cliente por um link seguro, para vendas que não passaram pelo funil. Estas entregas não aparecem no funil nem nos relatórios.</p>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px"><input type="hidden" name="p" value="vouchers"><input name="b" value="<?= h($b) ?>" placeholder="Cliente ou destino" style="width:260px"><button class="btn">Buscar</button></form>
  <div class="cartao tabela"><table><thead><tr><th>Cliente</th><th>Destino</th><th>Viagem</th><th>Documentos</th><th>Criada em</th></tr></thead><tbody>
    <?php foreach ($lista as $v): ?><tr>
      <td><a href="./?p=voucher&id=<?= $v['id'] ?>"><b><?= h($v['nome']) ?></b></a></td>
      <td><?= h($v['destino']) ?></td>
      <td><?= $v['data_embarque'] ? data_br($v['data_embarque']) . ($v['data_retorno'] ? ' a ' . data_br($v['data_retorno']) : '') : '—' ?></td>
      <td><?= (int)$v['ndocs'] ?></td><td><?= data_br($v['criado_em']) ?></td>
    </tr><?php endforeach; ?>
  </tbody></table><?php if (!$lista): ?><p class="vazio">Nenhuma entrega de vouchers ainda. Clique em "+ Nova entrega" para começar.</p><?php endif; ?></div>
<?php rodape(); }

function pagina_voucher_novo() {
    exigir_login();
    topo('Nova entrega de vouchers', 'vouchers'); ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="./?p=vouchers">Vouchers</a> › Nova entrega</p><h1>Nova entrega de vouchers</h1></div></div>
  <form method="post" action="./?p=acao&a=voucher_criar" class="grade2"><?= csrf_campo() ?>
    <div class="cartao"><h2>Cliente</h2>
      <p class="mudo" style="margin-top:0">Se o cliente já existir (mesmo e-mail ou telefone), a entrega é ligada ao cadastro dele.</p>
      <label>Nome do cliente</label><input name="nome" required>
      <div class="linha"><div><label>WhatsApp</label><input name="telefone" type="tel" placeholder="(11) 90000-0000"></div><div><label>E-mail</label><input name="email" type="email"></div></div>
      <label>Data de nascimento do titular <span class="mudo">(trava de segurança do link)</span></label><input type="date" name="nascimento">
    </div>
    <div class="cartao"><h2>Viagem</h2>
      <label>Destino</label><input name="destino" placeholder="Ex.: Orlando">
      <div class="linha"><div><label>Início da viagem</label><input type="date" name="data_embarque"></div><div><label>Fim da viagem</label><input type="date" name="data_retorno"></div></div>
      <p class="mudo">As datas organizam o portal e definem quando o link expira. Os vouchers você anexa no próximo passo.</p>
      <p style="margin:12px 0 0"><button class="btn btn-pri">Criar e anexar vouchers</button></p>
    </div>
  </form>
<?php rodape(); }

function pagina_voucher($id) {
    exigir_login();
    $l = um("SELECT l.*, c.nome, c.email, c.telefone, c.id cid FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.id = ? AND l.etapa = 'entrega'", [$id]);
    if (!$l) redirecionar('./?p=vouchers', '!Entrega não encontrada.');
    topo('Vouchers · ' . $l['nome'], 'vouchers'); ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="./?p=vouchers">Vouchers</a> › <?= h($l['nome']) ?></p>
      <h1><?= h($l['nome']) ?><?= $l['destino'] ? ' · ' . h($l['destino']) : '' ?></h1>
      <span class="mudo"><?= $l['data_embarque'] ? data_br($l['data_embarque']) . ($l['data_retorno'] ? ' a ' . data_br($l['data_retorno']) : '') : 'Sem datas' ?> · entrega avulsa (fora do funil)</span></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><?php if ($z = link_whats($l['telefone'])): ?><a class="btn btn-zap" href="<?= h($z) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?><a class="btn" href="./?p=cliente&id=<?= $l['cid'] ?>">Ver cliente</a></div>
  </div>
  <div class="grade2">
    <div><?php cartao_documentos_lead($l); ?></div>
    <div class="cartao"><h2>Editar dados</h2>
      <form method="post" action="./?p=acao&a=voucher_salvar"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $l['id'] ?>">
        <label>Destino</label><input name="destino" value="<?= h($l['destino']) ?>">
        <div class="linha"><div><label>Início</label><input type="date" name="data_embarque" value="<?= h($l['data_embarque']) ?>"></div><div><label>Fim</label><input type="date" name="data_retorno" value="<?= h($l['data_retorno']) ?>"></div></div>
        <p style="margin:12px 0 0"><button class="btn btn-pri">Salvar</button></p>
      </form>
      <hr style="border:none;border-top:1px solid var(--linha);margin:16px 0">
      <?php $temViagem = valor('SELECT 1 FROM viagens WHERE lead_id = ?', [$l['id']]); ?>
      <p class="mudo" style="margin-top:0">Esta entrega não entra no histórico de viagens automaticamente.</p>
      <?php if (!$temViagem): ?><form method="post" action="./?p=acao&a=voucher_historico"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><button class="btn btn-pq">Registrar no histórico de viagens</button></form>
      <?php else: ?><p class="mudo">✓ Já registrada no histórico de viagens.</p><?php endif; ?>
      <form method="post" action="./?p=acao&a=voucher_excluir" onsubmit="return confirm('Excluir esta entrega e seus vouchers?')" style="margin-top:14px"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $l['id'] ?>"><button class="btn btn-pq btn-perigo">Excluir entrega</button></form>
    </div>
  </div>
<?php rodape(); }
