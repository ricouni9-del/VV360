<?php
// Todas as ações de gravação do CRM (já passaram por login e CSRF)
$a = preg_replace('/[^a-z_]/', '', $_GET['a'] ?? '');
$uid = usuario()['id'];
if (strpos($a, 'fin_') === 0) acao_financeiro($a);
if (strpos($a, 'prop_') === 0) acao_propostas($a);
if (strpos($a, 'grupo_') === 0) acao_grupos($a);

function lead_ou_sai($id) { $l = um('SELECT * FROM leads WHERE id = ?', [(int)$id]); if (!$l) redirecionar('./?p=funil', '!Lead não encontrado.'); return $l; }
function voltar_seguro($padrao) {
    $v = (string)($_POST['voltar'] ?? '');
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    return ($v !== '' && strpos($v, $base) === 0 && strpos($v, '//') === false) ? $v : $padrao;
}
function atividade($lead, $cliente, $tipo, $texto) {
    q('INSERT INTO atividades (lead_id, cliente_id, usuario_id, tipo, texto, criado_em) VALUES (?,?,?,?,?,?)', [$lead, $cliente, usuario()['id'], $tipo, $texto, agora()]);
}
function tarefa($lead, $cliente, $titulo, $data) {
    q('INSERT INTO tarefas (lead_id, cliente_id, usuario_id, titulo, vence_em, criado_em) VALUES (?,?,?,?,?,?)', [$lead, $cliente, usuario()['id'], $titulo, $data, agora()]);
}
function achar_ou_criar_cliente($nome, $tel, $email, $cidade, $origem) {
    $c = null;
    if ($email) $c = um('SELECT id FROM clientes WHERE email = ? COLLATE NOCASE', [$email]);
    if (!$c && strlen(so_digitos($tel)) >= 8)
        $c = um("SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,' ',''),'(',''),')',''),'-',''),'+','') LIKE ?", ['%' . substr(so_digitos($tel), -9)]);
    if ($c) return (int)$c['id'];
    q('INSERT INTO clientes (nome, telefone, email, cidade, origem, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?)', [$nome, $tel, $email, $cidade, $origem, agora(), agora()]);
    return (int)db()->lastInsertId();
}

function msg_ou_sai($id) { $m = um('SELECT * FROM mensagens WHERE id = ?', [(int)$id]); if (!$m) redirecionar('./?p=mensagens', '!Mensagem não encontrada.'); return $m; }

/** Lê CPF, RG e passaporte do formulário (com prefixo) e devolve os campos já criptografados, ou uma mensagem de erro. */
function docs_do_post($pref) {
    $cpf = so_digitos($_POST[$pref . 'cpf'] ?? '');
    if ($cpf !== '' && !cpf_valido($cpf)) return 'CPF inválido. Confira os números.';
    $emi = data_valida($_POST[$pref . 'passaporte_emissao'] ?? ''); $val = data_valida($_POST[$pref . 'passaporte_validade'] ?? '');
    if ($emi && $val && $val <= $emi) return 'a validade do passaporte precisa ser depois da emissão.';
    return ['cpf_enc' => $cpf ? cripto(cpf_formatado($cpf)) : null, 'cpf_hash' => $cpf ? cpf_hash($cpf) : null,
            'rg_enc' => cripto(post($pref . 'rg', 40)), 'passaporte_enc' => cripto(strtoupper(post($pref . 'passaporte', 20))),
            'passaporte_emissao' => $emi, 'passaporte_validade' => $val];
}

switch ($a) {

case 'mudar_etapa':
    $l = lead_ou_sai($_POST['id'] ?? 0);
    $nova = $_POST['etapa'] ?? '';
    if (!isset(ETAPAS[$nova])) redirecionar('./?p=lead&id=' . $l['id'], '!Etapa inválida.');
    if ($l['etapa'] === 'aprovada' && in_array($nova, ['novo', 'conversa', 'roteiro', 'proposta', 'ajustes'], true)) {
        q("UPDATE proposta_opcoes SET aprovada_cliente_em = NULL, aprovada_cliente_nome = NULL, aprovada_cliente_ip = NULL WHERE proposta_id IN (SELECT id FROM propostas WHERE lead_id = ? AND status = 'aprovada')", [$l['id']]);
        q("UPDATE propostas SET status = 'enviada', decidida_em = NULL WHERE lead_id = ? AND status = 'aprovada'", [$l['id']]);
    }
    if ($l['etapa'] === 'perdido' && in_array($nova, ['proposta', 'ajustes'], true)) {
        $pr = valor("SELECT id FROM propostas WHERE lead_id = ? AND status = 'recusada' ORDER BY versao DESC LIMIT 1", [$l['id']]);
        if ($pr) { q("UPDATE propostas SET status = 'enviada', decidida_em = NULL WHERE id = ?", [$pr]); atividade($l['id'], $l['cliente_id'], 'proposta', 'Proposta reativada ao recuperar o lead.'); }
    }
    $motivo = $nova === 'perdido' ? post('motivo_perda', 60) : null;
    if ($nova !== $l['etapa'] || $motivo !== $l['motivo_perda']) {
        q('UPDATE leads SET etapa = ?, motivo_perda = ?, etapa_desde = CASE WHEN etapa = ? THEN etapa_desde ELSE ? END, atualizado_em = ? WHERE id = ?',
          [$nova, $motivo, $nova, agora(), agora(), $l['id']]);
        if ($nova !== $l['etapa']) {
            atividade($l['id'], $l['cliente_id'], 'etapa', ETAPAS[$l['etapa']] . ' → ' . ETAPAS[$nova] . ($motivo ? " (motivo: $motivo)" : ''));
            // Lembretes automáticos
            if ($nova === 'perdido') q("UPDATE propostas SET status = 'recusada', decidida_em = ? WHERE lead_id = ? AND status = 'enviada'", [agora(), $l['id']]);
            if ($nova === 'proposta' && !valor("SELECT 1 FROM propostas WHERE lead_id = ? AND status = 'enviada'", [$l['id']])) $dicaProposta = true;
            if ($nova === 'proposta') tarefa($l['id'], $l['cliente_id'], 'Acompanhar a proposta enviada', date('Y-m-d', strtotime('+3 days')));
            if ($nova === 'aprovada') {
                // registra a opção escolhida, se houver proposta enviada
                $pa = um("SELECT * FROM propostas WHERE lead_id = ? AND status = 'enviada' ORDER BY versao DESC LIMIT 1", [$l['id']]);
                if ($pa) {
                    $ops = todos('SELECT id FROM proposta_opcoes WHERE proposta_id = ?', [$pa['id']]);
                    if (count($ops) === 1) { aprovar_opcao($ops[0]['id']); }
                    else $escolherProposta = $pa['id'];
                }
                tarefa($l['id'], $l['cliente_id'], 'Receber sinal e confirmar reservas para fechar', date('Y-m-d', strtotime('+2 days')));
            }
            if ($nova === 'fechado') {
                // se veio de "aprovada", usa a opção já escolhida; senão, pega a proposta enviada
                $op = um("SELECT o.id FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE p.lead_id = ? AND p.status = 'aprovada' AND o.escolhida = 1 ORDER BY p.versao DESC LIMIT 1", [$l['id']]);
                if ($op) { [$vendaCriada] = proposta_para_venda($l, $op['id']); }
                else {
                    $vendaCriada = venda_do_lead($l);
                    $pa = um("SELECT * FROM propostas WHERE lead_id = ? AND status = 'enviada' ORDER BY versao DESC LIMIT 1", [$l['id']]);
                    if ($pa) {
                        $ops = todos('SELECT id FROM proposta_opcoes WHERE proposta_id = ?', [$pa['id']]);
                        if (count($ops) === 1) { aprovar_opcao($ops[0]['id']); [$vendaCriada] = proposta_para_venda($l, $ops[0]['id']); q("UPDATE leads SET etapa='fechado' WHERE id=?", [$l['id']]); }
                        else $escolherProposta = $pa['id'];
                    }
                }
                tarefa($l['id'], $l['cliente_id'], 'Conferir reservas, documentos e vouchers', date('Y-m-d', strtotime('+2 days')));
                if ($l['data_embarque'] && $l['data_embarque'] > hoje()) tarefa($l['id'], $l['cliente_id'], 'Mandar mensagem de boa viagem', date('Y-m-d', max(time(), strtotime($l['data_embarque'] . ' -2 days'))));
            }
            if ($nova === 'pos' && !valor('SELECT 1 FROM viagens WHERE lead_id = ?', [$l['id']]))
                q('INSERT INTO viagens (cliente_id, lead_id, destino, ida, volta, passageiros, valor, fonte, criado_em) VALUES (?,?,?,?,?,?,?,?,?)',
                  [$l['cliente_id'], $l['id'], $l['destino'] ?: 'Destino não informado', $l['data_embarque'], $l['data_retorno'],
                   trim(($l['adultos'] ? $l['adultos'] . ' adulto(s)' : '') . ($l['criancas'] ? ' e crianças: ' . $l['criancas'] : '')),
                   ($vv = valor('SELECT id FROM vendas WHERE lead_id = ?', [$l['id']])) && resumo_venda($vv)['total_cliente'] > 0 ? resumo_venda($vv)['total_cliente'] : $l['valor_estimado'], 'funil', agora()]);
            if ($nova === 'pos') tarefa($l['id'], $l['cliente_id'], 'Perguntar como foi a viagem e convidar para deixar um elogio no site', hoje());
            registrar('mudou etapa', 'lead #' . $l['id'] . ' → ' . ETAPAS[$nova]);
        }
    }
    if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo '{"ok":true}'; exit; }
    if (!empty($escolherProposta)) redirecionar('./?p=proposta&id=' . $escolherProposta, 'Esta proposta tinha mais de uma opção: clique em "Cliente aprovou esta opção" na que o cliente escolheu.');
    if (!empty($dicaProposta)) redirecionar('./?p=lead&id=' . $l['id'] . '#propostas', 'Etapa atualizada. Dica: registre a proposta enviada no quadro Propostas para os valores irem direto ao Financeiro no fechamento.');
    if (!empty($vendaCriada) && pode_financeiro()) redirecionar('./?p=venda&id=' . $vendaCriada['id'], 'Venda fechada! Lance os fornecedores e os pagamentos do cliente.');
    redirecionar('./?p=lead&id=' . $l['id'], 'Etapa atualizada.');

case 'temp_salvar':
    $l = lead_ou_sai($_POST['id'] ?? 0);
    $t = $_POST['temp'] ?? '';
    if (!isset(NIVEIS_TEMP[$t])) $t = null;
    q('UPDATE leads SET temp_manual = ?, temp_manual_em = ? WHERE id = ?', [$t, $t ? agora() : null, $l['id']]);
    if ($t) q('INSERT INTO atividades (lead_id, cliente_id, usuario_id, tipo, texto, criado_em) VALUES (?,?,?,?,?,?)', [$l['id'], $l['cliente_id'], $uid, 'nota', 'Termômetro ajustado para ' . NIVEIS_TEMP[$t][1] . '.', agora()]);
    redirecionar('./?p=lead&id=' . $l['id'] . '#termometro', $t ? 'Termômetro ajustado.' : 'Termômetro voltou ao cálculo automático.');

case 'atividade':
    $l = lead_ou_sai($_POST['lead_id'] ?? 0);
    $tipo = isset(TIPOS_ATIVIDADE[$_POST['tipo'] ?? '']) ? $_POST['tipo'] : 'nota';
    $txt = post('texto', 4000);
    if ($txt === '') redirecionar('./?p=lead&id=' . $l['id'], '!Escreva o que aconteceu.');
    atividade($l['id'], $l['cliente_id'], $tipo, $txt);
    q('UPDATE leads SET atualizado_em = ? WHERE id = ?', [agora(), $l['id']]);
    redirecionar('./?p=lead&id=' . $l['id'], 'Registro salvo.');

case 'nova_tarefa':
    $lead = (int)($_POST['lead_id'] ?? 0) ?: null; $cli = (int)($_POST['cliente_id'] ?? 0) ?: null;
    $data = data_valida($_POST['vence_em'] ?? '') ?: hoje();
    if (post('titulo') === '') redirecionar($lead ? './?p=lead&id=' . $lead : './?p=tarefas', '!Informe a tarefa.');
    tarefa($lead, $cli, post('titulo', 200), $data);
    redirecionar($lead ? './?p=lead&id=' . $lead : './?p=cliente&id=' . $cli, 'Tarefa adicionada.');

case 'tarefa_feita':
    q('UPDATE tarefas SET feita = 1, feita_em = ? WHERE id = ?', [agora(), (int)($_POST['id'] ?? 0)]);
    redirecionar(voltar_seguro('./?p=tarefas'), 'Tarefa concluída.');

case 'tarefa_editar':
    $t = um('SELECT * FROM tarefas WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    if ($t) { $d = data_valida($_POST['vence_em'] ?? '') ?: $t['vence_em']; $tit = post('titulo', 200) ?: $t['titulo'];
        q('UPDATE tarefas SET vence_em = ?, titulo = ? WHERE id = ?', [$d, $tit, $t['id']]); }
    redirecionar(voltar_seguro('./?p=tarefas'), 'Tarefa atualizada.');

case 'tarefa_adiar':
    $t = um('SELECT * FROM tarefas WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    $dias = max(1, min(30, (int)($_POST['d'] ?? 1)));
    if ($t) q('UPDATE tarefas SET vence_em = ? WHERE id = ?', [date('Y-m-d', strtotime(($t['vence_em'] < hoje() ? hoje() : $t['vence_em']) . " +$dias day")), $t['id']]);
    redirecionar(voltar_seguro('./?p=tarefas'), 'Tarefa adiada em ' . $dias . ($dias == 1 ? ' dia.' : ' dias.'));

case 'tarefa_excluir':
    q('DELETE FROM tarefas WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    redirecionar(voltar_seguro('./?p=tarefas'), 'Tarefa excluída.');

case 'salvar_lead':
    $l = lead_ou_sai($_POST['id'] ?? 0);
    $resp = (int)($_POST['responsavel_id'] ?? 0) ?: null;
    if ($resp && !valor('SELECT 1 FROM usuarios WHERE id = ?', [$resp])) $resp = null;
    $origem = in_array($_POST['origem'] ?? '', ORIGENS, true) ? $_POST['origem'] : null;
    q('UPDATE leads SET destino=?, periodo=?, duracao=?, adultos=?, criancas=?, ocasiao=?, orcamento=?, valor_estimado=?, origem=?, data_embarque=?, data_retorno=?, responsavel_id=?, detalhes=?, atualizado_em=? WHERE id=?',
      [post('destino', 150), post('periodo', 100), post('duracao', 60), ($_POST['adultos'] ?? '') === '' ? null : (int)$_POST['adultos'], post('criancas', 200),
       post('ocasiao', 80), post('orcamento', 60), num_valor($_POST['valor_estimado'] ?? ''), $origem, data_valida($_POST['data_embarque'] ?? ''),
       data_valida($_POST['data_retorno'] ?? ''), $resp, post('detalhes', 5000), agora(), $l['id']]);
    redirecionar('./?p=lead&id=' . $l['id'], 'Detalhes salvos.');

case 'excluir_lead':
    $l = lead_ou_sai($_POST['id'] ?? 0);
    q('DELETE FROM leads WHERE id = ?', [$l['id']]);
    registrar('excluiu lead', '#' . $l['id']);
    redirecionar('./?p=cliente&id=' . $l['cliente_id'], 'Lead excluído.');

case 'novo_lead':
    $origem = in_array($_POST['origem'] ?? '', ORIGENS, true) ? $_POST['origem'] : 'Outro';
    $cid = (int)($_POST['cliente_id'] ?? 0);
    db()->beginTransaction();
    if (!$cid || !valor('SELECT 1 FROM clientes WHERE id = ?', [$cid])) {
        if (mb_strlen(post('nome')) < 2) { db()->rollBack(); redirecionar('./?p=novo', '!Informe o nome do cliente.'); }
        $email = filter_var(post('email', 120), FILTER_VALIDATE_EMAIL) ?: '';
        $cid = achar_ou_criar_cliente(post('nome', 120), post('telefone', 40), $email, post('cidade', 80), $origem);
    }
    q('INSERT INTO leads (cliente_id, destino, periodo, duracao, adultos, criancas, detalhes, valor_estimado, origem, etapa, responsavel_id, etapa_desde, criado_em, atualizado_em)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$cid, post('destino', 150), post('periodo', 100), post('duracao', 60), ($_POST['adultos'] ?? '') === '' ? null : (int)$_POST['adultos'], post('criancas', 200),
       post('detalhes', 5000), num_valor($_POST['valor_estimado'] ?? ''), $origem, 'novo', $uid, agora(), agora(), agora()]);
    $lid = (int)db()->lastInsertId();
    atividade($lid, $cid, 'nota', 'Lead cadastrado manualmente (origem: ' . $origem . ').');
    tarefa($lid, $cid, 'Fazer o primeiro contato', hoje());
    db()->commit();
    registrar('criou lead', '#' . $lid);
    redirecionar('./?p=lead&id=' . $lid, 'Lead criado.');

case 'novo_cliente':
case 'salvar_cliente':
    $id = (int)($_POST['id'] ?? 0);
    $volta = $a === 'novo_cliente' ? './?p=novocliente' : './?p=cliente&id=' . $id;
    if (mb_strlen(post('nome')) < 2) redirecionar($volta, '!Informe o nome.');
    $email = post('email', 120);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) redirecionar($volta, '!E-mail inválido.');
    $campos = ['nome' => post('nome', 120), 'telefone' => post('telefone', 40), 'email' => $email, 'nascimento' => data_valida($_POST['nascimento'] ?? ''),
        'cep' => post('cep', 10), 'logradouro' => post('logradouro', 150), 'numero' => post('numero', 20), 'complemento' => post('complemento', 80),
        'bairro' => post('bairro', 80), 'cidade' => post('cidade', 80), 'uf' => in_array($_POST['uf'] ?? '', UFS, true) ? $_POST['uf'] : null,
        'origem' => in_array($_POST['origem'] ?? '', ORIGENS, true) ? $_POST['origem'] : null,
        'preferencias' => post('preferencias', 4000), 'notas' => post('notas', 4000)];
    if ($a === 'novo_cliente') {
        $docs = docs_do_post('');
        if (is_string($docs)) {
            $_SESSION['antigo_cliente'] = array_intersect_key($_POST, array_flip(['nome','telefone','email','nascimento','cep','logradouro','numero','complemento','bairro','cidade','uf','origem','preferencias','notas','cpf','rg','passaporte','passaporte_emissao','passaporte_validade']));
            redirecionar($volta, '!' . $docs);
        }
        if ($docs['cpf_hash'] && ($dup = valor('SELECT id FROM clientes WHERE cpf_hash = ?', [$docs['cpf_hash']]))) redirecionar('./?p=cliente&id=' . $dup, '!Já existe um cliente com este CPF.');
        if (($dup = crm_achar_cliente($email, $campos['telefone']))) redirecionar('./?p=cliente&id=' . $dup, '!Já existe um cliente com este e-mail ou telefone. Confira o cadastro abaixo.');
        $campos += $docs + ['criado_em' => agora(), 'atualizado_em' => agora()];
        q('INSERT INTO clientes (' . implode(',', array_keys($campos)) . ') VALUES (' . implode(',', array_fill(0, count($campos), '?')) . ')', array_values($campos));
        $id = (int)db()->lastInsertId();
        registrar('cadastrou cliente', '#' . $id);
        redirecionar('./?p=cliente&id=' . $id, 'Cliente cadastrado.');
    }
    $campos['atualizado_em'] = agora();
    q('UPDATE clientes SET ' . implode(' = ?, ', array_keys($campos)) . ' = ? WHERE id = ?', [...array_values($campos), $id]);
    redirecionar($volta, 'Cadastro salvo.');

case 'salvar_documentos':
    $id = (int)($_POST['id'] ?? 0);
    if (!valor('SELECT 1 FROM clientes WHERE id = ?', [$id])) redirecionar('./?p=clientes', '!Cliente não encontrado.');
    $docs = docs_do_post('c_');
    if (is_string($docs)) redirecionar('./?p=documentos&id=' . $id, '!Titular: ' . $docs);
    if ($docs['cpf_hash'] && valor('SELECT 1 FROM clientes WHERE cpf_hash = ? AND id <> ?', [$docs['cpf_hash'], $id])) redirecionar('./?p=documentos&id=' . $id, '!Este CPF já está cadastrado em outro cliente.');
    db()->beginTransaction();
    q('UPDATE clientes SET cpf_enc=?, cpf_hash=?, rg_enc=?, passaporte_enc=?, passaporte_emissao=?, passaporte_validade=?, atualizado_em=? WHERE id=?',
      [$docs['cpf_enc'], $docs['cpf_hash'], $docs['rg_enc'], $docs['passaporte_enc'], $docs['passaporte_emissao'], $docs['passaporte_validade'], agora(), $id]);
    foreach (todos('SELECT id, nome FROM viajantes WHERE cliente_id = ?', [$id]) as $v) {
        $dv = docs_do_post('v' . $v['id'] . '_');
        if (is_string($dv)) { db()->rollBack(); redirecionar('./?p=documentos&id=' . $id, '!' . $v['nome'] . ': ' . $dv); }
        q('UPDATE viajantes SET cpf_enc=?, rg_enc=?, passaporte_enc=?, passaporte_emissao=?, passaporte_validade=? WHERE id=?',
          [$dv['cpf_enc'], $dv['rg_enc'], $dv['passaporte_enc'], $dv['passaporte_emissao'], $dv['passaporte_validade'], $v['id']]);
    }
    db()->commit();
    registrar('alterou documentos', '#' . $id);
    redirecionar('./?p=cliente&id=' . $id, 'Documentos salvos.');

case 'nova_viagem':
    $cid = (int)($_POST['cliente_id'] ?? 0);
    if (!valor('SELECT 1 FROM clientes WHERE id = ?', [$cid])) redirecionar('./?p=clientes', '!Cliente não encontrado.');
    if (post('destino') === '' || !data_valida($_POST['ida'] ?? '')) redirecionar('./?p=cliente&id=' . $cid, '!Informe destino e data de ida.');
    q('INSERT INTO viagens (cliente_id, destino, ida, volta, passageiros, hospedagem, fornecedor, valor, observacoes, fonte, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
      [$cid, post('destino', 150), data_valida($_POST['ida']), data_valida($_POST['volta'] ?? ''), post('passageiros', 150), post('hospedagem', 150),
       post('fornecedor', 120), num_valor($_POST['valor'] ?? ''), post('observacoes', 1000), 'manual', agora()]);
    redirecionar('./?p=cliente&id=' . $cid, 'Viagem registrada no histórico.');

case 'excluir_viagem':
    $v = um('SELECT * FROM viagens WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    if ($v) { q('DELETE FROM viagens WHERE id = ?', [$v['id']]); registrar('removeu viagem do histórico', '#' . $v['id']); }
    redirecionar('./?p=cliente&id=' . ($v['cliente_id'] ?? 0), 'Viagem removida.');

case 'importar_enviar':
    $f = $_FILES['arquivo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) redirecionar('./?p=importar', '!Não recebi o arquivo. Tente de novo.');
    if ($f['size'] > 5 * 1024 * 1024) redirecionar('./?p=importar', '!Arquivo maior que 5 MB.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'csv'], true)) redirecionar('./?p=importar', '!Envie um arquivo .xlsx ou .csv.');
    try { $linhas = analisar_planilha($f['tmp_name'], $ext); }
    catch (Throwable $e) { redirecionar('./?p=importar', '!' . ($e instanceof RuntimeException ? $e->getMessage() : 'Não consegui ler a planilha.')); }
    if (!$linhas) redirecionar('./?p=importar', '!A planilha não tem linhas preenchidas.');
    foreach (glob(dirname(crm_caminho_banco()) . '/importacao-*.json') as $velho) if (filemtime($velho) < time() - 86400) unlink($velho);
    $token = bin2hex(random_bytes(12));
    file_put_contents(arquivo_importacao($token), json_encode(['arquivo' => mb_substr($f['name'], 0, 120), 'usuario' => $uid, 'linhas' => $linhas], JSON_UNESCAPED_UNICODE));
    redirecionar('./?p=importar&t=' . $token);

case 'importar_confirmar':
    $arq = arquivo_importacao($_POST['t'] ?? '');
    $dados = is_file($arq) ? json_decode(file_get_contents($arq), true) : null;
    if (!$dados || (int)$dados['usuario'] !== (int)$uid) redirecionar('./?p=importar', '!Importação expirada. Envie a planilha de novo.');
    $cache = []; $nv = 0; $nc = 0; $dupl = 0;
    db()->beginTransaction();
    foreach ($dados['linhas'] as $r) {
        if ($r['erros']) continue;
        [$cid] = cliente_da_linha($r, $cache);
        if (!$cid) {
            q('INSERT INTO clientes (nome, email, telefone, nascimento, cpf_enc, cpf_hash, origem, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?,?,?)',
              [$r['nome'], $r['email'], $r['telefone'], $r['nascimento'], cripto(cpf_formatado($r['cpf'])), cpf_hash($r['cpf']), 'Cliente recorrente', agora(), agora()]);
            $cid = (int)db()->lastInsertId(); $nc++;
        }
        foreach (array_filter(['cpf:' . so_digitos($r['cpf']) => $r['cpf'] !== '', 'email:' . mb_strtolower($r['email']) => $r['email'] !== '', 'tel:' . substr(so_digitos($r['telefone']), -9) => strlen(so_digitos($r['telefone'])) >= 8, 'nome:' . mb_strtolower($r['nome']) => $r['cpf'] === '' && $r['email'] === '' && $r['telefone'] === '']) as $k => $_) $cache[$k] = $cid;
        if (valor('SELECT 1 FROM viagens WHERE cliente_id = ? AND lower(destino) = lower(?) AND ida IS ?', [$cid, $r['destino'], $r['ida']])) { $dupl++; continue; }
        q('INSERT INTO viagens (cliente_id, destino, ida, volta, passageiros, hospedagem, fornecedor, valor, observacoes, fonte, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
          [$cid, mb_substr($r['destino'], 0, 150), $r['ida'], $r['volta'], mb_substr($r['passageiros'], 0, 150), mb_substr($r['hospedagem'], 0, 150),
           mb_substr($r['fornecedor'], 0, 120), $r['valor'], mb_substr($r['observacoes'], 0, 1000), 'importacao', agora()]);
        $nv++;
    }
    db()->commit();
    @unlink($arq);
    registrar('importou histórico de viagens', "$nv viagens, $nc clientes novos");
    redirecionar('./?p=viagens', "Importação concluída: $nv viagens registradas, $nc clientes novos" . ($dupl ? ", $dupl já existiam e foram ignoradas" : '') . '.');

case 'excluir_cliente':
    $id = (int)($_POST['id'] ?? 0);
    q('DELETE FROM clientes WHERE id = ?', [$id]);
    crm_publicar_depoimentos();
    registrar('excluiu cliente (LGPD)', '#' . $id);
    redirecionar('./?p=clientes', 'Cliente e todo o histórico excluídos.');

case 'novo_viajante':
    $cid = (int)($_POST['cliente_id'] ?? 0);
    if (post('nome') !== '' && valor('SELECT 1 FROM clientes WHERE id = ?', [$cid]))
        q('INSERT INTO viajantes (cliente_id, nome, parentesco, nascimento, observacoes) VALUES (?,?,?,?,?)',
          [$cid, post('nome', 120), post('parentesco', 60), data_valida($_POST['nascimento'] ?? ''), post('observacoes', 300)]);
    redirecionar('./?p=cliente&id=' . $cid, 'Acompanhante adicionado.');

case 'excluir_viajante':
    q('DELETE FROM viajantes WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    redirecionar('./?p=cliente&id=' . (int)($_POST['cliente_id'] ?? 0), 'Acompanhante removido.');

case 'novo_usuario':
    exigir_admin();
    if (!filter_var(post('email'), FILTER_VALIDATE_EMAIL) || mb_strlen(post('nome')) < 2) redirecionar('./?p=usuarios', '!Informe nome e e-mail válidos.');
    if (strlen($_POST['senha'] ?? '') < 10) redirecionar('./?p=usuarios', '!A senha precisa ter pelo menos 10 caracteres.');
    if (valor('SELECT 1 FROM usuarios WHERE email = ?', [post('email')])) redirecionar('./?p=usuarios', '!Já existe um usuário com esse e-mail.');
    q('INSERT INTO usuarios (nome, email, senha_hash, papel, criado_em) VALUES (?,?,?,?,?)',
      [post('nome', 80), post('email', 120), password_hash($_POST['senha'], PASSWORD_DEFAULT), ($_POST['papel'] ?? '') === 'admin' ? 'admin' : 'usuario', agora()]);
    if (!empty($_POST['financeiro']) || ($_POST['papel'] ?? '') === 'admin') q('UPDATE usuarios SET acesso_financeiro = 1 WHERE id = ?', [db()->lastInsertId()]);
    registrar('adicionou usuário', post('email'));
    redirecionar('./?p=usuarios', 'Pessoa adicionada à equipe.');

case 'alternar_usuario':
    exigir_admin();
    $id = (int)($_POST['id'] ?? 0);
    if ($id !== (int)$uid) { q('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?', [$id]); registrar('alterou acesso de usuário', '#' . $id); }
    redirecionar('./?p=usuarios', 'Acesso atualizado.');

case 'alternar_financeiro':
    exigir_admin();
    q("UPDATE usuarios SET acesso_financeiro = 1 - acesso_financeiro WHERE id = ? AND papel <> 'admin'", [(int)($_POST['id'] ?? 0)]);
    registrar('alterou acesso ao financeiro', '#' . (int)($_POST['id'] ?? 0));
    redirecionar('./?p=usuarios', 'Acesso ao financeiro atualizado.');

case 'voucher_criar':
    if (mb_strlen(post('nome')) < 2) redirecionar('./?p=vouchernovo', '!Informe o nome do cliente.');
    db()->beginTransaction();
    $email = filter_var(post('email', 120), FILTER_VALIDATE_EMAIL) ?: '';
    $cid = crm_achar_cliente($email, post('telefone', 40));
    if (!$cid) {
        q('INSERT INTO clientes (nome, email, telefone, nascimento, origem, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?)',
          [post('nome', 120), $email, post('telefone', 40), data_valida($_POST['nascimento'] ?? ''), 'Cliente recorrente', agora(), agora()]);
        $cid = (int)db()->lastInsertId();
    } elseif (data_valida($_POST['nascimento'] ?? '')) {
        q("UPDATE clientes SET nascimento = COALESCE(NULLIF(nascimento,''), ?) WHERE id = ?", [data_valida($_POST['nascimento']), $cid]);
    }
    // cria o "lead" marcado como entrega (fora do funil); código VV é gerado pelo gatilho normalmente
    q("INSERT INTO leads (cliente_id, destino, origem, etapa, etapa_desde, data_embarque, data_retorno, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?,?,?)",
      [$cid, post('destino', 150), 'Entrega avulsa', 'entrega', agora(), data_valida($_POST['data_embarque'] ?? ''), data_valida($_POST['data_retorno'] ?? ''), agora(), agora()]);
    $nid = (int)db()->lastInsertId();
    db()->commit();
    registrar('criou entrega de vouchers', '#' . $nid);
    redirecionar('./?p=voucher&id=' . $nid, 'Entrega criada. Anexe os vouchers abaixo.');

case 'voucher_salvar':
    $l = um("SELECT * FROM leads WHERE id = ? AND etapa = 'entrega'", [(int)($_POST['id'] ?? 0)]) ?: redirecionar('./?p=vouchers', '!Entrega não encontrada.');
    q('UPDATE leads SET destino = ?, data_embarque = ?, data_retorno = ?, atualizado_em = ? WHERE id = ?',
      [post('destino', 150), data_valida($_POST['data_embarque'] ?? ''), data_valida($_POST['data_retorno'] ?? ''), agora(), $l['id']]);
    redirecionar('./?p=voucher&id=' . $l['id'], 'Dados salvos.');

case 'voucher_historico':
    $l = um("SELECT * FROM leads WHERE id = ? AND etapa = 'entrega'", [(int)($_POST['id'] ?? 0)]) ?: redirecionar('./?p=vouchers', '!Entrega não encontrada.');
    if (!valor('SELECT 1 FROM viagens WHERE lead_id = ?', [$l['id']]))
        q('INSERT INTO viagens (cliente_id, lead_id, destino, ida, volta, fonte, criado_em) VALUES (?,?,?,?,?,?,?)',
          [$l['cliente_id'], $l['id'], $l['destino'] ?: 'Viagem', $l['data_embarque'], $l['data_retorno'], 'manual', agora()]);
    redirecionar('./?p=voucher&id=' . $l['id'], 'Registrada no histórico de viagens.');

case 'voucher_excluir':
    $l = um("SELECT * FROM leads WHERE id = ? AND etapa = 'entrega'", [(int)($_POST['id'] ?? 0)]) ?: redirecionar('./?p=vouchers', '!Entrega não encontrada.');
    foreach (todos('SELECT arquivo FROM viagem_docs WHERE lead_id = ?', [$l['id']]) as $d) @unlink(pasta_docs() . '/' . $d['arquivo']);
    q('DELETE FROM leads WHERE id = ?', [$l['id']]);
    registrar('excluiu entrega de vouchers', '#' . $l['id']);
    redirecionar('./?p=vouchers', 'Entrega excluída.');

case 'doc_enviar':
    $l = lead_ou_sai($_POST['lead_id'] ?? 0);
    $f = $_FILES['arquivo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) redirecionar('./?p=lead&id=' . $l['id'] . '#documentos', '!Não recebi o arquivo.');
    $maxMb = (int)cfg('doc_max_mb', 10);
    if ($f['size'] > $maxMb * 1024 * 1024) redirecionar('./?p=lead&id=' . $l['id'] . '#documentos', '!Arquivo maior que ' . $maxMb . ' MB.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf','jpg','jpeg','png','html','htm'], true)) redirecionar('./?p=lead&id=' . $l['id'] . '#documentos', '!Formato não aceito. Use PDF, imagem ou HTML.');
    $cat = isset(CATEGORIAS_DOC[$_POST['categoria'] ?? '']) ? $_POST['categoria'] : 'outros';
    $nomeArq = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], pasta_docs() . '/' . $nomeArq)) redirecionar('./?p=lead&id=' . $l['id'] . '#documentos', '!Não consegui salvar o arquivo.');
    q('INSERT INTO viagem_docs (lead_id, nome, categoria, arquivo, ext, tamanho, inicio, fim, hora, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$l['id'], post('nome', 150), $cat, $nomeArq, $ext, (int)$f['size'], data_valida($_POST['inicio'] ?? ''), data_valida($_POST['fim'] ?? ''), preg_match('/^\d{1,2}:\d{2}$/', $_POST['hora'] ?? '') ? $_POST['hora'] : null, agora()]);
    registrar('subiu documento da viagem', post('nome', 150) . ' · lead #' . $l['id']);
    redirecionar('./?p=lead&id=' . $l['id'] . '#documentos', 'Documento adicionado.');

case 'doc_excluir':
    $d = um('SELECT * FROM viagem_docs WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
    if ($d) { @unlink(pasta_docs() . '/' . $d['arquivo']); q('DELETE FROM viagem_docs WHERE id = ?', [$d['id']]); }
    redirecionar('./?p=lead&id=' . ($d['lead_id'] ?? 0) . '#documentos', 'Documento removido.');

case 'portal_criar':
    $l = lead_ou_sai($_POST['lead_id'] ?? 0);
    $tipo = ($_POST['tipo'] ?? '') === 'pesquisa' ? 'pesquisa' : 'docs';
    q("UPDATE portais SET revogado = 1 WHERE lead_id = ? AND tipo = ?", [$l['id'], $tipo]);
    q("DELETE FROM portais WHERE lead_id = ? AND tipo = ?", [$l['id'], $tipo]);
    portal_do_lead($l['id'], $tipo, true);
    redirecionar('./?p=lead&id=' . $l['id'] . ($tipo === 'pesquisa' ? '#pesquisa' : '#documentos'), 'Link gerado.');

case 'portal_revogar':
    $l = lead_ou_sai($_POST['lead_id'] ?? 0);
    $tipo = ($_POST['tipo'] ?? '') === 'pesquisa' ? 'pesquisa' : 'docs';
    q("UPDATE portais SET revogado = 1 WHERE lead_id = ? AND tipo = ?", [$l['id'], $tipo]);
    registrar('revogou link do cliente', $tipo . ' · lead #' . $l['id']);
    redirecionar('./?p=lead&id=' . $l['id'] . ($tipo === 'pesquisa' ? '#pesquisa' : '#documentos'), 'Link revogado.');

case 'ia_roteiro_salvar':
    $o = um('SELECT o.*, p.status, p.lead_id, p.titulo FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [(int)($_POST['opcao_id'] ?? 0)]) ?: redirecionar('./?p=funil', '!Opção não encontrada.');
    $volta = './?p=proposta&id=' . $o['proposta_id'];
    if ($o['status'] !== 'rascunho') redirecionar($volta, '!A proposta já foi enviada.');
    $html = ia_limpar_html((string)($_POST['html'] ?? ''));
    if (stripos($html, '<html') === false) redirecionar($volta, '!O conteúdo gerado não parece HTML válido. Gere de novo.');
    $tok = $o['roteiro_token'] ?: bin2hex(random_bytes(12));
    file_put_contents(pasta_roteiros() . '/' . $tok . '.html', $html);
    $nome = titulo_do_html(pasta_roteiros() . '/' . $tok . '.html') ?: ($o['titulo'] ?: 'Roteiro');
    q('UPDATE proposta_opcoes SET roteiro_token = ?, roteiro_nome = ? WHERE id = ?', [$tok, $nome, $o['id']]);
    nota_lead($o['lead_id'], valor('SELECT cliente_id FROM leads WHERE id = ?', [$o['lead_id']]), 'Roteiro da ' . $o['nome'] . ' gerado pelo Claude (automático).');
    redirecionar($volta, 'Roteiro gerado e anexado à ' . $o['nome'] . '.');

case 'salvar_ia':
    exigir_admin();
    if (isset(MODELOS_IA[$_POST['ia_modelo'] ?? ''])) set_cfg('ia_modelo', $_POST['ia_modelo']);
    set_cfg('ia_teto_dia', max(1, min(500, (int)($_POST['ia_teto_dia'] ?? 30))));
    $nova = trim((string)($_POST['ia_chave'] ?? ''));
    if ($nova !== '') { set_cfg('ia_chave_enc', cripto($nova)); registrar('salvou chave da API'); }
    redirecionar('./?p=usuarios', 'Preferências de IA salvas.');

case 'salvar_config':
    exigir_admin();
    $mb = max(1, min(50, (int)($_POST['doc_max_mb'] ?? 10)));
    $dias = max(1, min(365, (int)($_POST['doc_retencao_dias'] ?? 7)));
    set_cfg('doc_max_mb', $mb); set_cfg('doc_retencao_dias', $dias);
    registrar('alterou configuracoes', "doc_max_mb=$mb, retencao=$dias");
    redirecionar('./?p=usuarios', 'Configurações salvas.');

case 'redefinir_senha':
    exigir_admin();
    $id = (int)($_POST['id'] ?? 0);
    if (strlen($_POST['senha'] ?? '') < 10) redirecionar('./?p=usuarios', '!A senha provisória precisa ter pelo menos 10 caracteres.');
    q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($_POST['senha'], PASSWORD_DEFAULT), $id]);
    registrar('redefiniu senha de usuário', '#' . $id);
    redirecionar('./?p=usuarios', 'Senha provisória definida. Peça para a pessoa trocar em "Conta" no próximo acesso.');

case 'trocar_senha':
    $u = usuario();
    if (!password_verify((string)($_POST['atual'] ?? ''), $u['senha_hash'])) { sleep(1); redirecionar('./?p=conta', '!Senha atual incorreta.'); }
    if (strlen($_POST['nova'] ?? '') < 10) redirecionar('./?p=conta', '!A nova senha precisa ter pelo menos 10 caracteres.');
    q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($_POST['nova'], PASSWORD_DEFAULT), $u['id']]);
    registrar('trocou a senha');
    redirecionar('./?p=conta', 'Senha alterada.');

case 'msg_respondida': case 'msg_reabrir': case 'msg_nao_publicar': case 'msg_despublicar':
    $m = msg_ou_sai($_POST['id'] ?? 0);
    if (in_array($a, ['msg_nao_publicar', 'msg_despublicar'], true)) exigir_admin();
    $st = ['msg_respondida' => 'respondida', 'msg_reabrir' => 'nova', 'msg_nao_publicar' => 'nao_publicado', 'msg_despublicar' => 'nao_publicado'][$a];
    q('UPDATE mensagens SET status = ? WHERE id = ?', [$st, $m['id']]);
    if ($a === 'msg_despublicar') { crm_publicar_depoimentos(); registrar('tirou elogio do site', '#' . $m['id']); }
    redirecionar(voltar_seguro('./?p=mensagens'), 'Mensagem atualizada.');

case 'msg_publicar':
    exigir_admin();
    $m = msg_ou_sai($_POST['id'] ?? 0);
    if ($m['tipo'] !== 'elogio' || !$m['autoriza_publicar']) redirecionar('./?p=mensagens', '!Este elogio não pode ser publicado.');
    q("UPDATE mensagens SET status = 'publicado', nome_publico = ?, viagem = ?, texto_publico = ?, publicado_em = ? WHERE id = ?",
      [post('nome_publico', 80), post('viagem', 120), post('texto_publico', 3000), agora(), $m['id']]);
    crm_publicar_depoimentos();
    registrar('publicou elogio no site', '#' . $m['id']);
    redirecionar(voltar_seguro('./?p=mensagens'), 'Elogio publicado no site.');

case 'msg_excluir':
    $m = msg_ou_sai($_POST['id'] ?? 0);
    q('DELETE FROM mensagens WHERE id = ?', [$m['id']]);
    if ($m['status'] === 'publicado') crm_publicar_depoimentos();
    registrar('excluiu mensagem', '#' . $m['id']);
    redirecionar(voltar_seguro('./?p=mensagens'), 'Mensagem excluída.');

case 'msg_lead':
    $m = msg_ou_sai($_POST['id'] ?? 0);
    db()->beginTransaction();
    $cid = $m['cliente_id'] ?: achar_ou_criar_cliente($m['nome'], $m['telefone'], $m['email'], '', 'Site');
    q('INSERT INTO leads (cliente_id, destino, detalhes, origem, etapa, responsavel_id, etapa_desde, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?,?,?)',
      [$cid, $m['viagem'], 'Dúvida enviada pelo site: ' . $m['texto'], 'Site', 'novo', $uid, agora(), agora(), agora()]);
    $lid = (int)db()->lastInsertId();
    q("UPDATE mensagens SET cliente_id = ?, lead_id = ?, status = 'respondida' WHERE id = ?", [$cid, $lid, $m['id']]);
    atividade($lid, $cid, 'nota', 'Lead criado a partir de uma dúvida enviada pelo site.');
    tarefa($lid, $cid, 'Responder a dúvida e entender a viagem', hoje());
    db()->commit();
    redirecionar('./?p=lead&id=' . $lid, 'Lead criado a partir da mensagem.');

default:
    http_response_code(404); echo 'Ação desconhecida.';
}
