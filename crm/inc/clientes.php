<?php
// ================= Clientes =================
function pagina_clientes() {
    $b = trim($_GET['b'] ?? '');
    $par = []; $w = '';
    if ($b !== '') {
        $w = 'WHERE c.nome LIKE ? OR c.email LIKE ? OR c.telefone LIKE ?'; $par = ["%$b%", "%$b%", "%$b%"];
        if (strlen(so_digitos($b)) === 11) { $w .= ' OR c.cpf_hash = ?'; $par[] = cpf_hash($b); }
    }
    $lista = todos("SELECT c.*, (SELECT COUNT(*) FROM leads l WHERE l.cliente_id = c.id) leads,
                           (SELECT COUNT(*) FROM viagens v WHERE v.cliente_id = c.id) viagens,
                           (SELECT MAX(v.ida) FROM viagens v WHERE v.cliente_id = c.id) ultima
                    FROM clientes c $w ORDER BY c.atualizado_em DESC LIMIT 300", $par);
    topo('Clientes', 'clientes'); ?>
  <div class="cab"><h1>Clientes</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <form method="get" style="display:flex;gap:8px"><input type="hidden" name="p" value="clientes"><input name="b" value="<?= h($b) ?>" placeholder="Nome, e-mail, telefone ou CPF" style="width:250px"><button class="btn">Buscar</button></form>
      <a class="btn" href="./?p=exportar">Exportar leads</a><a class="btn btn-pri" href="./?p=novocliente">+ Novo cliente</a></div></div>
  <div class="cartao tabela"><table><thead><tr><th>Cliente</th><th>Contato</th><th>Cidade</th><th>Viagens</th><th>Última viagem</th><th>Leads</th></tr></thead><tbody>
    <?php foreach ($lista as $c): ?><tr>
      <td><a href="./?p=cliente&id=<?= $c['id'] ?>"><b><?= h($c['nome']) ?></b></a></td>
      <td><?= h($c['telefone']) ?><br><span class="mudo"><?= h($c['email']) ?></span></td>
      <td><?= h(trim($c['cidade'] . ($c['uf'] ? '/' . $c['uf'] : ''), '/')) ?></td>
      <td><?= (int)$c['viagens'] ?></td><td><?= data_br($c['ultima']) ?></td><td><?= (int)$c['leads'] ?></td>
    </tr><?php endforeach; ?>
  </tbody></table><?php if (!$lista): ?><p class="vazio">Nenhum cliente encontrado.</p><?php endif; ?></div>
<?php rodape(); }

function campos_cadastro($c = []) {
    $v = fn($k) => h($c[$k] ?? ''); ?>
  <label>Nome completo</label><input name="nome" value="<?= $v('nome') ?>" required>
  <div class="linha"><div><label>WhatsApp</label><input name="telefone" type="tel" value="<?= $v('telefone') ?>" placeholder="(11) 90000-0000"></div>
    <div><label>E-mail</label><input name="email" type="email" value="<?= $v('email') ?>"></div>
    <div><label>Data de nascimento</label><input name="nascimento" type="date" value="<?= $v('nascimento') ?>"></div></div>
  <h3 style="margin:18px 0 0">Endereço</h3>
  <div class="linha"><div><label>CEP</label><input name="cep" class="cep" inputmode="numeric" value="<?= $v('cep') ?>" placeholder="00000-000"></div>
    <div style="grid-column:span 2"><label>Logradouro</label><input name="logradouro" value="<?= $v('logradouro') ?>"></div></div>
  <div class="linha"><div><label>Número</label><input name="numero" value="<?= $v('numero') ?>"></div><div><label>Complemento</label><input name="complemento" value="<?= $v('complemento') ?>"></div><div><label>Bairro</label><input name="bairro" value="<?= $v('bairro') ?>"></div></div>
  <div class="linha"><div><label>Cidade</label><input name="cidade" value="<?= $v('cidade') ?>"></div><div><label>UF</label><select name="uf"><?= select_opcoes(UFS, $c['uf'] ?? '', '—') ?></select></div>
    <div><label>Origem do cliente</label><select name="origem"><?= select_opcoes(ORIGENS, $c['origem'] ?? '', 'Não informada') ?></select></div></div>
  <label>Preferências de viagem</label><textarea name="preferencias" placeholder="Ex.: gosta de hotéis boutique, evita voos noturnos, ama gastronomia"><?= $v('preferencias') ?></textarea>
  <label>Notas</label><textarea name="notas"><?= $v('notas') ?></textarea>
<?php }

function campos_documentos($pref, $d = []) { ?>
  <div class="linha"><div><label>CPF</label><input name="<?= $pref ?>cpf" inputmode="numeric" value="<?= h(cpf_formatado(decripto($d['cpf_enc'] ?? ''))) ?>" placeholder="000.000.000-00"></div>
    <div><label>RG (com órgão emissor)</label><input name="<?= $pref ?>rg" value="<?= h(decripto($d['rg_enc'] ?? '')) ?>" placeholder="Ex.: 12.345.678-9 SSP/SP"></div></div>
  <div class="linha"><div><label>Passaporte nº</label><input name="<?= $pref ?>passaporte" value="<?= h(decripto($d['passaporte_enc'] ?? '')) ?>" style="text-transform:uppercase"></div>
    <div><label>Emissão</label><input type="date" name="<?= $pref ?>passaporte_emissao" value="<?= h($d['passaporte_emissao'] ?? '') ?>"></div>
    <div><label>Validade</label><input type="date" name="<?= $pref ?>passaporte_validade" value="<?= h($d['passaporte_validade'] ?? '') ?>"></div></div>
<?php }

const JS_CEP = <<<'JS'
function cpfOk(c){c=c.replace(/\D/g,'');if(c.length!==11||/^(\d)\1{10}$/.test(c))return false;for(var t=9;t<11;t++){var s=0;for(var i=0;i<t;i++)s+=c[i]*(t+1-i);if(+c[t]!==((10*s)%11)%10)return false;}return true;}
document.querySelectorAll('input[name$=cpf]').forEach(function(i){i.addEventListener('blur',function(){var d=i.value.replace(/\D/g,'');if(d.length===11)i.value=d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);i.setCustomValidity(d&&!cpfOk(d)?'CPF inválido. Confira os números.':'');});});
document.querySelectorAll('input.cep').forEach(function(i){
  i.addEventListener('blur',function(){
    var c=i.value.replace(/\D/g,''); if(c.length!==8) return; var f=i.form;
    fetch('https://viacep.com.br/ws/'+c+'/json/').then(function(r){return r.json();}).then(function(d){
      if(d.erro) return; i.value=c.slice(0,5)+'-'+c.slice(5);
      var p={logradouro:d.logradouro,bairro:d.bairro,cidade:d.localidade,uf:d.uf};
      Object.keys(p).forEach(function(k){ var el=f.querySelector('[name='+k+']'); if(el && !el.value && p[k]) el.value=p[k]; });
      var n=f.querySelector('[name=numero]'); if(n && !n.value) n.focus();
    }).catch(function(){});
  });
});
JS;

function pagina_cliente_form() {
    $old = $_SESSION['antigo_cliente'] ?? []; unset($_SESSION['antigo_cliente']);
    $oldDocs = $old ? ['cpf_enc' => cripto($old['cpf'] ?? ''), 'rg_enc' => cripto($old['rg'] ?? ''), 'passaporte_enc' => cripto($old['passaporte'] ?? ''),
                       'passaporte_emissao' => $old['passaporte_emissao'] ?? '', 'passaporte_validade' => $old['passaporte_validade'] ?? ''] : [];
    topo('Novo cliente', 'clientes'); ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="./?p=clientes">Clientes</a> › Novo cliente</p><h1>Novo cliente</h1></div></div>
  <form method="post" action="./?p=acao&a=novo_cliente" class="grade2"><?= csrf_campo() ?>
    <div class="cartao"><h2>Dados do cliente</h2><?php campos_cadastro($old); ?></div>
    <div>
      <div class="cartao"><h2>Documentos</h2><?php campos_documentos('', $oldDocs); ?>
        <p class="mudo" style="margin-bottom:0">Os documentos são guardados criptografados e só aparecem para a equipe logada no CRM.</p></div>
      <div class="cartao"><button class="btn btn-pri">Cadastrar cliente</button> <span class="mudo">O cadastro não cria lead; crie um lead depois, se precisar.</span></div>
    </div>
  </form>
<?php rodape(JS_CEP); }

function pagina_cliente($id) {
    $c = um('SELECT * FROM clientes WHERE id = ?', [$id]);
    if (!$c) redirecionar('./?p=clientes', '!Cliente não encontrado.');
    $viaj = todos('SELECT * FROM viajantes WHERE cliente_id = ? ORDER BY nascimento', [$id]);
    $leads = todos("SELECT * FROM leads WHERE cliente_id = ? AND etapa <> 'entrega' ORDER BY criado_em DESC", [$id]);
    $viagens = todos('SELECT * FROM viagens WHERE cliente_id = ? ORDER BY ida DESC', [$id]);
    $msgs = todos('SELECT * FROM mensagens WHERE cliente_id = ? ORDER BY criado_em DESC', [$id]);
    $cpf = decripto($c['cpf_enc']); [$pcls, $ptxt] = situacao_passaporte($c['passaporte_validade']);
    topo($c['nome'], 'clientes'); ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="./?p=clientes">Clientes</a> › <?= h($c['nome']) ?></p><h1><?= h($c['nome']) ?></h1>
      <span class="mudo"><?= $c['nascimento'] ? 'Nascimento ' . data_br($c['nascimento']) . ' · ' : '' ?>Cliente desde <?= data_br($c['criado_em']) ?> · <?= count($viagens) ?> <?= count($viagens) == 1 ? 'viagem' : 'viagens' ?></span></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><?php if ($z = link_whats($c['telefone'])): ?><a class="btn btn-zap" href="<?= h($z) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?><a class="btn btn-pri" href="./?p=novo&cliente=<?= $c['id'] ?>">+ Novo lead</a></div></div>
  <div class="grade2">
    <div>
      <div class="cartao"><h2>Histórico de viagens</h2>
        <?php if ($viagens): ?><div class="tabela"><table><thead><tr><th>Destino</th><th>Ida</th><th>Volta</th><th>Valor</th><th></th></tr></thead><tbody>
          <?php foreach ($viagens as $v): ?><tr><td><b><?= h($v['destino']) ?></b><?php $ex = array_filter([$v['passageiros'] ? 'Passageiros: ' . $v['passageiros'] : '', $v['hospedagem'] ? 'Hospedagem: ' . $v['hospedagem'] : '', $v['fornecedor'] ? 'Fornecedor: ' . $v['fornecedor'] : '', $v['observacoes']]); if ($ex): ?><br><span class="mudo"><?= h(implode(' · ', $ex)) ?></span><?php endif; ?></td>
            <td><?= data_br($v['ida']) ?></td><td><?= data_br($v['volta']) ?></td><td><?= reais($v['valor']) ?></td>
            <td><form method="post" action="./?p=acao&a=excluir_viagem" onsubmit="return confirm('Remover esta viagem do histórico?')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="btn btn-pq btn-perigo" title="Remover">×</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div><?php else: ?><p class="vazio">Nenhuma viagem registrada.</p><?php endif; ?>
        <details style="margin-top:12px"><summary style="cursor:pointer;font-weight:600;color:var(--sora)">+ Registrar viagem anterior</summary>
          <form method="post" action="./?p=acao&a=nova_viagem"><?= csrf_campo() ?><input type="hidden" name="cliente_id" value="<?= $c['id'] ?>"><?php campos_viagem(); ?>
            <p style="margin:10px 0 0"><button class="btn btn-pri">Salvar viagem</button></p></form></details>
      </div>
      <div class="cartao"><h2>Leads e propostas</h2>
        <?php foreach ($leads as $l): ?><p style="margin:8px 0"><span class="mudo"><?= h($l['codigo']) ?></span> <a href="./?p=lead&id=<?= $l['id'] ?>"><b><?= h($l['destino'] ?: 'Destino a definir') ?></b></a> <span class="tag <?= h($l['etapa']) ?>"><?= h(ETAPAS[$l['etapa']]) ?></span><br><span class="mudo"><?= h($l['periodo']) ?><?= $l['data_embarque'] ? ' · embarque ' . data_br($l['data_embarque']) : '' ?> · criado em <?= data_br($l['criado_em']) ?></span></p><?php endforeach; ?>
        <?php if (!$leads): ?><p class="vazio">Nenhum lead.</p><?php endif; ?>
      </div>
      <?php if ($msgs): ?><div class="cartao"><h2>Mensagens enviadas pelo site</h2>
        <?php foreach ($msgs as $mm): ?><p style="margin:8px 0"><span class="tag <?= ['elogio' => 'fechado', 'duvida' => 'novo', 'reclamacao' => 'perdido'][$mm['tipo']] ?>"><?= h(TIPOS_MENSAGEM[$mm['tipo']]) ?></span> <span class="mudo"><?= data_br($mm['criado_em']) ?> · <?= h(STATUS_MENSAGEM[$mm['status']] ?? '') ?></span><br><?= h(mb_substr($mm['texto'], 0, 240)) ?></p><?php endforeach; ?>
      </div><?php endif; ?>
      <div class="cartao"><h2>Quem viaja junto</h2>
        <?php if ($viaj): ?><div class="tabela"><table><thead><tr><th>Nome</th><th>Parentesco</th><th>Nascimento</th><th>Passaporte</th><th></th></tr></thead><tbody>
          <?php foreach ($viaj as $v): [$vc, $vt] = situacao_passaporte($v['passaporte_validade']); ?><tr><td><?= h($v['nome']) ?><?= $v['observacoes'] ? '<br><span class="mudo">' . h($v['observacoes']) . '</span>' : '' ?></td><td><?= h($v['parentesco']) ?></td>
            <td><?= data_br($v['nascimento']) ?><?php if ($v['nascimento']): ?><br><span class="mudo"><?= (int)((time() - strtotime($v['nascimento'])) / 31557600) ?> anos</span><?php endif; ?></td>
            <td><?= $vt ? '<span class="tag ' . $vc . '">' . $vt . '</span><br><span class="mudo">até ' . data_br($v['passaporte_validade']) . '</span>' : '<span class="mudo">—</span>' ?></td>
            <td><form method="post" action="./?p=acao&a=excluir_viajante" onsubmit="return confirm('Remover?')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><input type="hidden" name="cliente_id" value="<?= $c['id'] ?>"><button class="btn btn-pq btn-perigo">Remover</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div><?php else: ?><p class="vazio">Nenhum acompanhante cadastrado.</p><?php endif; ?>
        <form method="post" action="./?p=acao&a=novo_viajante" style="margin-top:10px"><?= csrf_campo() ?><input type="hidden" name="cliente_id" value="<?= $c['id'] ?>">
          <div class="linha"><div><label>Nome</label><input name="nome" required></div><div><label>Parentesco</label><input name="parentesco" placeholder="Ex.: filha, cônjuge"></div><div><label>Nascimento</label><input type="date" name="nascimento"></div></div>
          <label>Observações</label><input name="observacoes" placeholder="Ex.: alergia a amendoim, tem medo de altura">
          <p style="margin:10px 0 0"><button class="btn">Adicionar</button> <span class="mudo">Os documentos dos acompanhantes ficam em "Documentos".</span></p>
        </form>
      </div>
    </div>
    <div>
      <div class="cartao"><h2>Documentos</h2>
        <p style="margin:0 0 6px">CPF: <b><?= $cpf ? h(mascarar(cpf_formatado($cpf), 5)) : '<span class="mudo">não informado</span>' ?></b><br>
          RG: <b><?= $c['rg_enc'] ? h(mascarar(decripto($c['rg_enc']), 3)) : '<span class="mudo">não informado</span>' ?></b><br>
          Passaporte: <?php if ($c['passaporte_enc']): ?><b><?= h(mascarar(decripto($c['passaporte_enc']), 3)) ?></b> · validade <?= data_br($c['passaporte_validade']) ?> <span class="tag <?= $pcls ?>"><?= $ptxt ?></span><?php else: ?><span class="mudo">não informado</span><?php endif; ?></p>
        <a class="btn btn-pq" href="./?p=documentos&id=<?= $c['id'] ?>">Ver e editar documentos<?= $viaj ? ' (inclui acompanhantes)' : '' ?></a>
      </div>
      <div class="cartao"><h2>Cadastro</h2>
        <form method="post" action="./?p=acao&a=salvar_cliente"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
          <?php campos_cadastro($c); ?>
          <p style="margin:12px 0 0"><button class="btn btn-pri">Salvar cadastro</button></p>
        </form>
        <hr style="border:none;border-top:1px solid var(--linha);margin:18px 0">
        <p class="mudo" style="margin-top:0">LGPD: a pedido do cliente, você pode entregar uma cópia dos dados ou excluir o cadastro inteiro.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn btn-pq" href="./?p=dadoscliente&id=<?= $c['id'] ?>">Baixar dados do cliente</a>
        <form method="post" action="./?p=acao&a=excluir_cliente" onsubmit="return confirm('Excluir DEFINITIVAMENTE este cliente, documentos, viagens, leads e todo o histórico?')"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-pq btn-perigo">Excluir cliente</button></form></div>
      </div>
    </div>
  </div>
<?php rodape(JS_CEP); }

function campos_viagem() { ?>
  <label>Destino</label><input name="destino" required placeholder="Ex.: Orlando e Miami">
  <div class="linha"><div><label>Ida</label><input type="date" name="ida" required></div><div><label>Volta</label><input type="date" name="volta"></div><div><label>Valor total (R$)</label><input name="valor" inputmode="numeric"></div></div>
  <div class="linha"><div><label>Passageiros</label><input name="passageiros" placeholder="Ex.: 2 adultos e 2 crianças"></div><div><label>Hospedagem</label><input name="hospedagem"></div><div><label>Operadora / fornecedor</label><input name="fornecedor"></div></div>
  <label>Observações</label><input name="observacoes" placeholder="Ex.: amou o ryokan, quer voltar no outono">
<?php }

// ================= Documentos (acesso registrado) =================
function pagina_documentos($id) {
    $c = um('SELECT * FROM clientes WHERE id = ?', [$id]);
    if (!$c) redirecionar('./?p=clientes', '!Cliente não encontrado.');
    $viaj = todos('SELECT * FROM viajantes WHERE cliente_id = ? ORDER BY nascimento', [$id]);
    registrar('abriu documentos', $c['nome'] . ' (#' . $id . ')');
    topo('Documentos · ' . $c['nome'], 'clientes'); ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="./?p=clientes">Clientes</a> › <a href="./?p=cliente&id=<?= $id ?>"><?= h($c['nome']) ?></a> › Documentos</p><h1>Documentos</h1></div></div>
  <p class="mudo" style="margin-top:-8px">Estes dados são guardados criptografados. Cada abertura desta página fica registrada no histórico de acessos da equipe.</p>
  <form method="post" action="./?p=acao&a=salvar_documentos"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="cartao"><h2><?= h($c['nome']) ?> <span class="mudo" style="font-family:var(--sans);font-size:14px">· titular</span></h2><?php campos_documentos('c_', $c); ?></div>
    <?php foreach ($viaj as $v): ?>
      <div class="cartao"><h2><?= h($v['nome']) ?> <span class="mudo" style="font-family:var(--sans);font-size:14px">· <?= h($v['parentesco'] ?: 'acompanhante') ?></span></h2><?php campos_documentos('v' . $v['id'] . '_', $v); ?></div>
    <?php endforeach; ?>
    <p><button class="btn btn-pri">Salvar documentos</button> <a class="btn" href="./?p=cliente&id=<?= $id ?>">Voltar</a></p>
  </form>
<?php rodape(JS_CEP); }

// ================= Histórico de viagens =================
function pagina_viagens() {
    $b = trim($_GET['b'] ?? ''); $ano = preg_replace('/\D/', '', $_GET['ano'] ?? '');
    $w = []; $par = [];
    if ($b !== '') { $w[] = '(v.destino LIKE ? OR c.nome LIKE ?)'; $par[] = "%$b%"; $par[] = "%$b%"; }
    if ($ano) { $w[] = "substr(v.ida,1,4) = ?"; $par[] = $ano; }
    $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
    $lista = todos("SELECT v.*, c.nome FROM viagens v JOIN clientes c ON c.id = v.cliente_id $where ORDER BY v.ida DESC LIMIT 500", $par);
    $tot = um("SELECT COUNT(*) n, COALESCE(SUM(v.valor),0) s, COUNT(DISTINCT v.cliente_id) cl FROM viagens v JOIN clientes c ON c.id = v.cliente_id $where", $par);
    $anos = array_column(todos("SELECT DISTINCT substr(ida,1,4) a FROM viagens WHERE ida IS NOT NULL ORDER BY a DESC"), 'a');
    $top = todos("SELECT destino, COUNT(*) n FROM viagens v JOIN clientes c ON c.id = v.cliente_id $where GROUP BY lower(destino) ORDER BY n DESC LIMIT 5", $par);
    topo('Viagens', 'viagens'); ?>
  <div class="cab"><h1>Histórico de viagens</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" href="./?p=modelo">Baixar modelo de planilha</a><a class="btn btn-pri" href="./?p=importar">Importar planilha</a></div></div>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px"><input type="hidden" name="p" value="viagens">
    <input name="b" value="<?= h($b) ?>" placeholder="Destino ou cliente" style="width:240px">
    <select name="ano" style="width:auto"><?= select_opcoes(array_combine($anos, $anos) ?: [], $ano, 'Todos os anos') ?></select><button class="btn">Filtrar</button></form>
  <div class="metricas">
    <div class="metrica"><b><?= (int)$tot['n'] ?></b><span>Viagens</span></div>
    <div class="metrica"><b><?= (int)$tot['cl'] ?></b><span>Clientes</span></div>
    <div class="metrica"><b><?= reais($tot['s']) ?: 'R$ 0' ?></b><span>Valor total</span></div>
    <div class="metrica"><b style="font-size:18px;line-height:1.3"><?= $top ? h(implode(', ', array_map(fn($t) => $t['destino'], array_slice($top, 0, 3)))) : '—' ?></b><span>Destinos mais vendidos</span></div>
  </div>
  <div class="cartao tabela"><table><thead><tr><th>Cliente</th><th>Destino</th><th>Ida</th><th>Volta</th><th>Passageiros</th><th>Valor</th><th>Origem</th></tr></thead><tbody>
    <?php foreach ($lista as $v): ?><tr><td><a href="./?p=cliente&id=<?= $v['cliente_id'] ?>"><?= h($v['nome']) ?></a></td><td><b><?= h($v['destino']) ?></b><?= $v['fornecedor'] ? '<br><span class="mudo">' . h($v['fornecedor']) . '</span>' : '' ?></td>
      <td><?= data_br($v['ida']) ?></td><td><?= data_br($v['volta']) ?></td><td><?= h($v['passageiros']) ?></td><td><?= reais($v['valor']) ?></td>
      <td class="mudo"><?= ['manual' => 'Cadastro', 'importacao' => 'Planilha', 'funil' => 'Funil'][$v['fonte']] ?? '' ?></td></tr><?php endforeach; ?>
  </tbody></table><?php if (!$lista): ?><p class="vazio">Nenhuma viagem registrada. Importe seu histórico com a planilha modelo ou registre na ficha de cada cliente.</p><?php endif; ?></div>
<?php rodape(); }

// ================= Importação por planilha =================
const COLUNAS_IMPORTACAO = [
    'nome' => ['nome do cliente', 'nome', 'cliente'], 'cpf' => ['cpf'], 'email' => ['e-mail', 'email'],
    'telefone' => ['telefone', 'whatsapp', 'celular', 'telefone / whatsapp'], 'nascimento' => ['data de nascimento', 'nascimento'],
    'destino' => ['destino'], 'ida' => ['data de ida', 'ida', 'embarque'], 'volta' => ['data de volta', 'volta', 'retorno'],
    'passageiros' => ['passageiros'], 'hospedagem' => ['hospedagem', 'hotel'], 'fornecedor' => ['operadora / fornecedor', 'operadora', 'fornecedor'],
    'valor' => ['valor total (r$)', 'valor total', 'valor'], 'observacoes' => ['observacoes', 'observações', 'obs'],
];

function normalizar_cabecalho($t) {
    $t = mb_strtolower(trim(str_replace('*', '', (string)$t)));
    $t = strtr($t, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    foreach (COLUNAS_IMPORTACAO as $campo => $nomes) foreach ($nomes as $n) if ($t === strtr($n, ['ç' => 'c', 'õ' => 'o'])) return $campo;
    return null;
}

function ler_xlsx($arq) {
    if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) throw new RuntimeException('O servidor não abre arquivos .xlsx. Salve a planilha como CSV e envie de novo.');
    $z = new ZipArchive();
    if ($z->open($arq) !== true) throw new RuntimeException('Não foi possível abrir a planilha.');
    $ss = [];
    if (($x = $z->getFromName('xl/sharedStrings.xml')) !== false) {
        $xml = simplexml_load_string($x, 'SimpleXMLElement', LIBXML_NONET);
        foreach ($xml->si as $si) { $t = ''; if (isset($si->t)) $t = (string)$si->t; else foreach ($si->r as $r) $t .= (string)$r->t; $ss[] = $t; }
    }
    $wb = simplexml_load_string($z->getFromName('xl/workbook.xml'), 'SimpleXMLElement', LIBXML_NONET);
    $rels = simplexml_load_string($z->getFromName('xl/_rels/workbook.xml.rels'), 'SimpleXMLElement', LIBXML_NONET);
    $alvo = null; $primeira = null;
    foreach ($wb->sheets->sheet as $s) {
        $rid = (string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        foreach ($rels->Relationship as $r) if ((string)$r['Id'] === $rid) {
            $caminho = 'xl/' . ltrim(preg_replace('#^/?xl/#', '', (string)$r['Target']), '/');
            if (!$primeira) $primeira = $caminho;
            if (stripos((string)$s['name'], 'viagens') !== false) $alvo = $caminho;
        }
    }
    $sheet = simplexml_load_string($z->getFromName($alvo ?: $primeira), 'SimpleXMLElement', LIBXML_NONET);
    $linhas = [];
    foreach ($sheet->sheetData->row as $row) {
        $l = [];
        foreach ($row->c as $cel) {
            preg_match('/^([A-Z]+)/', (string)$cel['r'], $m); $col = 0;
            foreach (str_split($m[1]) as $ch) $col = $col * 26 + (ord($ch) - 64);
            $t = (string)$cel['t'];
            if ($t === 's') $v = $ss[(int)$cel->v] ?? '';
            elseif ($t === 'inlineStr') $v = (string)$cel->is->t;
            else $v = (string)$cel->v;
            $l[$col - 1] = $v;
        }
        if ($l) { $max = max(array_keys($l)); $linhas[(int)$row['r']] = array_replace(array_fill(0, $max + 1, ''), $l); }
    }
    $z->close();
    return $linhas;
}

function ler_csv($arq) {
    $txt = file_get_contents($arq);
    if (substr($txt, 0, 3) === "\xEF\xBB\xBF") $txt = substr($txt, 3);
    if (!mb_check_encoding($txt, 'UTF-8')) $txt = mb_convert_encoding($txt, 'UTF-8', 'Windows-1252');
    $sep = substr_count(strtok($txt, "\n"), ';') >= substr_count(strtok($txt, "\n"), ',') ? ';' : ',';
    $linhas = []; $n = 0;
    $f = fopen('php://memory', 'r+'); fwrite($f, $txt); rewind($f);
    while (($r = fgetcsv($f, 0, $sep)) !== false) $linhas[++$n] = $r;
    return $linhas;
}

function data_planilha($v) {
    $v = trim((string)$v);
    if ($v === '') return null;
    if (is_numeric($v) && $v > 1000 && $v < 80000) return gmdate('Y-m-d', ((int)$v - 25569) * 86400);
    if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$#', $v, $m)) { $a = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3]; return checkdate((int)$m[2], (int)$m[1], (int)$a) ? sprintf('%04d-%02d-%02d', $a, $m[2], $m[1]) : false; }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? "$m[1]-$m[2]-$m[3]" : false;
    return false;
}
function valor_planilha($v) {
    $v = trim((string)$v); if ($v === '') return null;
    if (is_numeric($v)) return (float)$v;
    return num_valor($v);
}

/** Lê a planilha e devolve as linhas validadas, sem gravar nada. */
function analisar_planilha($arq, $ext) {
    $linhas = $ext === 'csv' ? ler_csv($arq) : ler_xlsx($arq);
    $mapa = null; $saida = [];
    foreach ($linhas as $num => $cels) {
        if ($mapa === null) {
            $m = []; foreach ($cels as $i => $t) if ($campo = normalizar_cabecalho($t)) $m[$campo] = $i;
            if (isset($m['nome'], $m['destino'])) $mapa = $m;
            continue;
        }
        $r = []; foreach (COLUNAS_IMPORTACAO as $campo => $_) $r[$campo] = isset($mapa[$campo]) ? trim((string)($cels[$mapa[$campo]] ?? '')) : '';
        if (implode('', $r) === '') continue;
        if ($r['nome'] === 'Maria Exemplo da Silva') continue; // linha de exemplo do modelo
        $erros = [];
        if ($r['nome'] === '') $erros[] = 'sem nome';
        if ($r['destino'] === '') $erros[] = 'sem destino';
        foreach (['ida', 'volta', 'nascimento'] as $d) { $r[$d] = data_planilha($r[$d]); if ($r[$d] === false) { $erros[] = "data de $d inválida"; $r[$d] = null; } }
        if (!$r['ida'] && !in_array('data de ida inválida', $erros, true)) $erros[] = 'sem data de ida';
        if ($r['cpf'] !== '' && !cpf_valido($r['cpf'])) $erros[] = 'CPF inválido';
        if ($r['email'] !== '' && !filter_var($r['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'e-mail inválido';
        $r['valor'] = valor_planilha($r['valor']);
        $r['linha'] = $num; $r['erros'] = array_values(array_filter($erros));
        $saida[] = $r;
    }
    if ($mapa === null) throw new RuntimeException('Não encontrei o cabeçalho. Use a planilha modelo: as colunas "Nome do cliente" e "Destino" são obrigatórias.');
    return $saida;
}

/** Descobre o cliente de cada linha: por CPF, e-mail, telefone e, sem nenhum desses, pelo nome exato. */
function cliente_da_linha($r, &$cache) {
    $chaves = array_filter(['cpf:' . so_digitos($r['cpf']) => $r['cpf'] !== '', 'email:' . mb_strtolower($r['email']) => $r['email'] !== '', 'tel:' . substr(so_digitos($r['telefone']), -9) => strlen(so_digitos($r['telefone'])) >= 8]);
    $chaves = array_keys($chaves);
    if (!$chaves) $chaves = ['nome:' . mb_strtolower($r['nome'])];
    foreach ($chaves as $k) if (isset($cache[$k])) return [$cache[$k], 'mesmo cliente de outra linha'];
    $id = null; $como = '';
    if ($r['cpf'] !== '' && ($id = valor('SELECT id FROM clientes WHERE cpf_hash = ?', [cpf_hash($r['cpf'])]))) $como = 'encontrado pelo CPF';
    elseif ($id = crm_achar_cliente($r['email'], $r['telefone'])) $como = 'encontrado pelo contato';
    elseif ($r['cpf'] === '' && $r['email'] === '' && $r['telefone'] === '' && ($id = valor('SELECT id FROM clientes WHERE nome = ? COLLATE NOCASE', [$r['nome']]))) $como = 'encontrado pelo nome, confira';
    return [$id ? (int)$id : null, $id ? $como : 'novo cliente'];
}

function arquivo_importacao($token) { return dirname(crm_caminho_banco()) . '/importacao-' . preg_replace('/[^a-f0-9]/', '', $token) . '.json'; }

function pagina_importar() {
    $token = $_GET['t'] ?? '';
    $dados = $token && is_file(arquivo_importacao($token)) ? json_decode(file_get_contents(arquivo_importacao($token)), true) : null;
    topo('Importar histórico', 'viagens'); ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="./?p=viagens">Viagens</a> › Importar planilha</p><h1>Importar histórico de viagens</h1></div></div>
  <?php if (!$dados): ?>
  <div class="grade2">
    <div class="cartao"><h2>1. Preencha a planilha modelo</h2>
      <p>Uma linha por viagem. O mesmo cliente pode aparecer em várias linhas: o CRM reconhece pelo CPF, e-mail ou telefone e junta tudo na mesma ficha.</p>
      <p>Obrigatórios: <b>Nome do cliente</b>, <b>Destino</b> e <b>Data de ida</b>. Os demais campos são opcionais.</p>
      <a class="btn" href="./?p=modelo">Baixar modelo (.xlsx)</a></div>
    <div class="cartao"><h2>2. Envie a planilha</h2>
      <form method="post" action="./?p=acao&a=importar_enviar" enctype="multipart/form-data"><?= csrf_campo() ?>
        <label>Arquivo Excel (.xlsx) ou CSV</label><input type="file" name="arquivo" accept=".xlsx,.csv" required>
        <p style="margin:12px 0 0"><button class="btn btn-pri">Conferir antes de importar</button></p>
      </form>
      <p class="mudo">Nada é gravado neste passo. Você vê uma prévia de cada linha e só depois confirma.</p></div>
  </div>
  <?php else: $ok = array_filter($dados['linhas'], fn($r) => !$r['erros']); $cache = []; $novos = 0; ?>
  <div class="cartao"><h2>Prévia: <?= h($dados['arquivo']) ?></h2>
    <p><b><?= count($ok) ?></b> de <?= count($dados['linhas']) ?> linhas prontas para importar. Linhas com problema são ignoradas; corrija na planilha e importe de novo se quiser. Viagens já existentes (mesmo cliente, destino e data de ida) não são duplicadas.</p>
    <div class="tabela"><table><thead><tr><th>Linha</th><th>Cliente</th><th>Situação do cliente</th><th>Destino</th><th>Ida</th><th>Volta</th><th>Valor</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($dados['linhas'] as $r):
        if (!$r['erros']) { [$cid, $como] = cliente_da_linha($r, $cache); if (!$cid && $como === 'novo cliente') { $novos++; foreach (['cpf:' . so_digitos($r['cpf']) => $r['cpf'], 'email:' . mb_strtolower($r['email']) => $r['email'], 'tel:' . substr(so_digitos($r['telefone']), -9) => strlen(so_digitos($r['telefone'])) >= 8 ? 1 : '', 'nome:' . mb_strtolower($r['nome']) => !$r['cpf'] && !$r['email'] && !$r['telefone']] as $k => $tem) if ($tem) $cache[$k] = 'novo'; } } else $como = ''; ?>
      <tr><td class="mudo"><?= (int)$r['linha'] ?></td><td><?= h($r['nome']) ?></td><td class="mudo"><?= h($como) ?></td><td><?= h($r['destino']) ?></td><td><?= data_br($r['ida']) ?></td><td><?= data_br($r['volta']) ?></td><td><?= reais($r['valor']) ?></td>
        <td><?= $r['erros'] ? '<span class="tag perdido">' . h(implode(', ', $r['erros'])) . '</span>' : '<span class="tag fechado">OK</span>' ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <p><?= $novos ?> cliente(s) novo(s) serão cadastrados.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($ok): ?><form method="post" action="./?p=acao&a=importar_confirmar"><?= csrf_campo() ?><input type="hidden" name="t" value="<?= h($token) ?>"><button class="btn btn-pri">Importar <?= count($ok) ?> viagens</button></form><?php endif; ?>
      <a class="btn" href="./?p=importar">Enviar outro arquivo</a></div>
  </div>
  <?php endif;
  rodape(); }

function baixar_modelo() {
    $arq = __DIR__ . '/modelo-historico-viagens.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="modelo-historico-viagens.xlsx"');
    header('Content-Length: ' . filesize($arq));
    readfile($arq); exit;
}
