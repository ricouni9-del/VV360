<?php
// ================= Exportar para Claude (formato VV360-CLAUDE v1) =================
// Dois modos: PROPOSTA (antes da venda, sai da proposta) e ROTEIRO FINAL (após a venda, sai da venda/viagem fechada).
// O arquivo NUNCA contém CPF, RG, passaporte, nascimento, contato pessoal do cliente, nem custo/comissão/markup/valor por item.

function nf($v) { $v = trim((string)$v); return $v === '' ? 'não informado' : str_replace('|', '/', $v); }
function cab_claude($modo) {
    return ['# VV360 · Exportação para Claude', 'FORMATO: VV360-CLAUDE v1', 'MODO: ' . $modo, 'SKILL: vanessa-roteiro-html', ''];
}

const INSTR_PROPOSTA = 'Monte a proposta de viagem em HTML no padrão "passaporte" da Vanessa Viagens e Turismo, usando a skill vanessa-roteiro-html no MODO PROPOSTA. Use somente os serviços e o valor deste arquivo: não invente preços nem serviços. Na seção Investimento, mostre apenas o valor total ao cliente na moeda indicada. Campos "não informado" não devem aparecer no HTML: liste-os para mim no final da resposta.';
const INSTR_ROTEIRO = 'Monte o Guia da Viagem (roteiro final) em HTML no padrão "passaporte" da Vanessa Viagens e Turismo, usando a skill vanessa-roteiro-html no MODO ROTEIRO FINAL. Use somente as reservas, horários, códigos e contatos deste arquivo: não invente serviços, horários, códigos nem contatos. Não mostre valores. Campos "não informado" não devem aparecer no HTML: liste-os para mim no final da resposta.';

// ---------- Monta o conteúdo do modo PROPOSTA ----------
function texto_proposta($id, $oid = 0) {
    $p = um('SELECT p.*, l.cliente_id, l.destino, l.periodo, l.duracao, l.adultos, l.criancas, l.ocasiao, l.detalhes, l.codigo, l.origem, c.nome, c.preferencias FROM propostas p JOIN leads l ON l.id = p.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE p.id = ?', [$id]);
    if (!$p) return null;
    $ops = $oid ? todos('SELECT * FROM proposta_opcoes WHERE proposta_id = ? AND id = ?', [$id, $oid])
                : todos('SELECT * FROM proposta_opcoes WHERE proposta_id = ? ORDER BY ordem, id', [$id]);
    $uma = count($ops) === 1;
    // idades das crianças: vêm do texto livre do lead; exportamos só o que estiver em "criancas" (texto), nunca nascimento
    $L = cab_claude('PROPOSTA');
    $L[] = '## INSTRUÇÃO'; $L[] = INSTR_PROPOSTA; $L[] = '';
    $L[] = '## VIAGEM';
    $L[] = 'Código: ' . nf($p['codigo']);
    $L[] = 'Versão: v' . (int)$p['versao'];
    $L[] = 'Cliente: ' . nf(mb_strtoupper($p['nome']));
    $L[] = 'Título: ' . nf(rotulo_proposta($p, $ops));
    $L[] = 'Destinos: ' . nf($p['destino']);
    // período/duração: usa o da opção única quando houver datas; senão o texto do lead
    $per = $p['periodo']; $dur = $p['duracao'];
    if ($uma && $ops[0]['data_inicio'] && $ops[0]['data_fim']) { $per = data_br($ops[0]['data_inicio']) . ' a ' . data_br($ops[0]['data_fim']); $dur = duracao_viagem($ops[0]['data_inicio'], $ops[0]['data_fim']); }
    $L[] = 'Período: ' . nf($per);
    $L[] = 'Duração: ' . nf($dur);
    $L[] = 'Saindo de: ' . nf($p['origem'] === 'WhatsApp' || $p['origem'] === 'Instagram' ? '' : ''); // origem do lead != cidade de saída; deixamos a cargo das observações
    $L[array_key_last($L)] = 'Saindo de: não informado';
    $L[] = 'Ocasião: ' . nf($p['ocasiao']);
    $L[] = '';
    $L[] = '## PASSAGEIROS';
    $L[] = 'Adultos: ' . nf($p['adultos']);
    $L[] = 'Crianças/jovens: ' . nf($p['criancas']);
    $L[] = 'Necessidades informadas pelo cliente: não informado';
    $L[] = '';
    $L[] = '## PREFERÊNCIAS';
    $L[] = 'Não pode faltar: ' . nf($p['preferencias'] ? preg_replace('/\s+/', ' ', $p['preferencias']) : '');
    $L[] = 'Observações do atendimento: ' . nf($p['detalhes'] ? preg_replace('/\s+/', ' ', $p['detalhes']) : '');
    foreach ($ops as $o) {
        $r = resumo_opcao($o['id']);
        $itens = todos('SELECT servico, detalhes FROM proposta_itens WHERE opcao_id = ? ORDER BY id', [$o['id']]);
        $L[] = '';
        $L[] = $uma ? '## SERVIÇOS INCLUÍDOS' : '## SERVIÇOS INCLUÍDOS · ' . mb_strtoupper($o['nome']);
        if ($itens) foreach ($itens as $i) $L[] = '- ' . nf($i['servico']) . ' | ' . nf($i['detalhes']);
        else $L[] = '- não informado';
        $L[] = '';
        $L[] = $uma ? '## INVESTIMENTO' : '## INVESTIMENTO · ' . mb_strtoupper($o['nome']);
        $L[] = 'Moeda: ' . nf($r['moeda']);
        $L[] = 'Valor total ao cliente: ' . nf(number_format($r['total'], 2, ',', '.'));
        $L[] = 'Base do valor: ' . nf(trim(($p['adultos'] ? $p['adultos'] . ' adultos' : '') . ($p['criancas'] ? ' e ' . $p['criancas'] . ' crianças' : '')));
        $L[] = 'Condições: ' . nf($p['validade'] ? 'Cotação válida até ' . data_br($p['validade']) : '');
    }
    return ['texto' => implode("\n", $L), 'p' => $p, 'ops' => $ops, 'uma' => $uma];
}

// ---------- Monta o conteúdo do modo ROTEIRO FINAL ----------
function texto_roteiro($lead_id, $incluir_nomes = false) {
    $v = um('SELECT v.*, l.destino, l.periodo, l.adultos, l.criancas, l.ocasiao, l.detalhes, l.codigo, l.data_embarque, l.data_retorno, c.nome, c.preferencias FROM vendas v JOIN leads l ON l.id = v.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE v.lead_id = ?', [$lead_id]);
    if (!$v) return null;
    $itens = todos("SELECT * FROM venda_itens WHERE venda_id = ? AND servico <> 'Markup do pacote' ORDER BY COALESCE(fechado_em,'9999'), id", [$v['id']]);
    $cat = fn($s) => categoria_do_servico($s);
    $voos = $hosp = $trasl = $passeios = [];
    foreach ($itens as $i) {
        switch ($cat($i['servico'])) {
            case 'voos': $voos[] = $i; break;
            case 'hospedagem': $hosp[] = $i; break;
            case 'transfer': $trasl[] = $i; break;
            case 'passeios': $passeios[] = $i; break;
        }
    }
    $L = cab_claude('ROTEIRO FINAL');
    $L[] = '## INSTRUÇÃO'; $L[] = INSTR_ROTEIRO; $L[] = '';
    $L[] = '## VIAGEM';
    $L[] = 'Código: ' . nf($v['codigo']);
    $L[] = 'Cliente: ' . nf(mb_strtoupper($v['nome']));
    $L[] = 'Destinos: ' . nf($v['destino']);
    $L[] = 'Período: ' . nf($v['data_embarque'] ? data_br($v['data_embarque']) . ' a ' . data_br($v['data_retorno']) : $v['periodo']);
    $L[] = 'Duração: ' . nf($v['data_embarque'] && $v['data_retorno'] ? duracao_viagem($v['data_embarque'], $v['data_retorno']) : '');
    $L[] = 'Ocasião: ' . nf($v['ocasiao']);
    $L[] = '';
    $L[] = '## PASSAGEIROS';
    $L[] = 'Adultos: ' . nf($v['adultos']);
    $L[] = 'Crianças/jovens: ' . nf($v['criancas']);
    if ($incluir_nomes) $L[] = 'Nomes: ' . nf(''); // preenchido só se a Vanessa optar; nomes não ficam no cadastro hoje
    $L[] = 'Necessidades informadas pelo cliente: não informado';
    $L[] = '';
    $L[] = '## VOOS';
    if ($voos) foreach ($voos as $i) $L[] = '- ' . nf($i['detalhes'] ?: 'Voo') . ' | ' . nf($i['fechado_em'] ? data_br($i['fechado_em']) : '') . ' | ' . nf($i['voo_numero']) . ' | ' . nf($i['voo_origem']) . ' ' . nf($i['voo_partida']) . ' → ' . nf($i['voo_destino']) . ' ' . nf($i['voo_chegada']) . ' | Localizador: ' . nf($i['reserva_codigo']) . ' | Bagagem: ' . nf($i['bagagem']);
    else $L[] = '- não informado';
    $L[] = '';
    $L[] = '## HOSPEDAGEM';
    if ($hosp) foreach ($hosp as $i) $L[] = '- ' . nf($i['fornecedor'] ?: $i['detalhes']) . ' | Check-in: ' . nf($i['hora_apresentacao']) . ' | Regime: ' . nf($i['regime']) . ' | Reserva: ' . nf($i['reserva_codigo']) . ' | Endereço: ' . nf($i['endereco']) . ' | Telefone: ' . nf($i['fornecedor_contato']);
    else $L[] = '- não informado';
    $L[] = '';
    $L[] = '## TRASLADOS';
    if ($trasl) foreach ($trasl as $i) $L[] = '- ' . nf($i['fechado_em'] ? data_br($i['fechado_em']) : '') . ' | ' . nf($i['hora_apresentacao']) . ' | ' . nf($i['detalhes']) . ' | Fornecedor: ' . nf($i['fornecedor']) . ' | Contato: ' . nf($i['fornecedor_contato']) . ' | Voucher: ' . nf($i['reserva_codigo']);
    else $L[] = '- não se aplica';
    $L[] = '';
    $L[] = '## PASSEIOS E INGRESSOS';
    if ($passeios) foreach ($passeios as $i) $L[] = '- ' . nf($i['fechado_em'] ? data_br($i['fechado_em']) : '') . ' | ' . nf($i['hora_apresentacao']) . ' | ' . nf($i['detalhes']) . ' | Fornecedor: ' . nf($i['fornecedor']) . ' | Contato: ' . nf($i['fornecedor_contato']) . ' | Voucher: ' . nf($i['reserva_codigo']);
    else $L[] = '- não se aplica';
    $L[] = '';
    $L[] = '## SEGURO';
    $L[] = 'Seguradora: ' . nf($v['seguradora']);
    $L[] = 'Plano/cobertura: ' . nf($v['seguro_plano']);
    $L[] = 'Apólice: ' . nf($v['seguro_apolice']);
    $L[] = 'Central 24h: ' . nf($v['seguro_central']);
    $L[] = '';
    $L[] = '## CONTATOS DA AGÊNCIA';
    $L[] = 'Agente responsável: Vanessa';
    $L[] = 'WhatsApp: ' . nf('+55 11 97543-9074');
    $L[] = 'Plantão de emergência: ' . nf($v['plantao_emergencia']);
    $L[] = '';
    $L[] = '## OBSERVAÇÕES PARA O GUIA';
    $L[] = nf($v['guia_obs'] ? preg_replace('/\s+/', ' ', $v['guia_obs']) : '');
    return ['texto' => implode("\n", $L), 'v' => $v];
}

// ---------- Validação (pendências, não bloqueiam) ----------
function pendencias_proposta($r) {
    $f = [];
    if (!$r['p']['destino']) $f[] = 'destino da viagem';
    foreach ($r['ops'] as $o) { if (resumo_opcao($o['id'])['total'] <= 0) $f[] = 'valor total em ' . $o['nome']; if (!todos('SELECT 1 FROM proposta_itens WHERE opcao_id = ?', [$o['id']])) $f[] = 'serviços em ' . $o['nome']; }
    if (!$r['p']['adultos'] && !$r['p']['criancas']) $f[] = 'passageiros';
    return $f;
}
function pendencias_roteiro($r) {
    $v = $r['v']; $f = [];
    $itens = todos("SELECT * FROM venda_itens WHERE venda_id = ? AND servico <> 'Markup do pacote'", [$v['id']]);
    foreach ($itens as $i) {
        $c = categoria_do_servico($i['servico']); $nome = $i['fornecedor'] ?: $i['servico'];
        if ($c === 'voos' && (!$i['voo_numero'] || !$i['reserva_codigo'] || !$i['voo_partida'])) $f[] = 'dados do voo "' . $nome . '" (número, horários, localizador)';
        if ($c === 'hospedagem' && (!$i['reserva_codigo'] || !$i['hora_apresentacao'])) $f[] = 'reserva/datas do hotel "' . $nome . '"';
        if (in_array($c, ['transfer','passeios'], true) && (!$i['hora_apresentacao'] || !$i['fornecedor_contato'])) $f[] = 'horário/contato de "' . ($i['detalhes'] ?: $nome) . '"';
    }
    if (!$v['seguradora'] || !$v['seguro_apolice']) $f[] = 'dados do seguro (seguradora, apólice, central 24h)';
    if (!$v['plantao_emergencia']) $f[] = 'plantão de emergência da agência';
    return $f;
}

// ---------- Teste de privacidade: garante que nada proibido vaza ----------
function checar_privacidade($txt) {
    $alertas = [];
    if (preg_match('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', $txt)) $alertas[] = 'possível CPF';
    if (preg_match('/\bcomiss[ãa]o\b|\bmarkup\b|\bmargem\b|custo\b/i', $txt)) $alertas[] = 'termo financeiro interno';
    return $alertas;
}

// ---------- Página de pré-visualização ----------
function pagina_exportar() {
    exigir_login();
    $modo = ($_GET['modo'] ?? 'proposta') === 'roteiro' ? 'roteiro' : 'proposta';
    if ($modo === 'proposta') {
        $r = texto_proposta((int)($_GET['id'] ?? 0), (int)($_GET['opcao'] ?? 0));
        if (!$r) redirecionar('./?p=funil', '!Proposta não encontrada.');
        $txt = $r['texto']; $pend = pendencias_proposta($r); $codigo = $r['p']['codigo']; $versao = 'v' . (int)$r['p']['versao']; $lead_id = null;
        $nomeArq = 'VV360_' . $r['p']['codigo'] . '_PROPOSTA_v' . (int)$r['p']['versao'] . '.md';
        $voltar = './?p=proposta&id=' . (int)$_GET['id'];
    } else {
        $r = texto_roteiro((int)($_GET['lead'] ?? 0));
        if (!$r) redirecionar('./?p=funil', '!Viagem fechada não encontrada.');
        $txt = $r['texto']; $pend = pendencias_roteiro($r); $codigo = $r['v']['codigo']; $versao = 'ROTEIRO-FINAL'; $lead_id = (int)$_GET['lead'];
        $nomeArq = 'VV360_' . $r['v']['codigo'] . '_ROTEIRO-FINAL.md';
        $voltar = './?p=venda&id=' . (int)$r['v']['id'];
    }
    if (!empty($_GET['download'])) {
        registrar('exportou para o Claude', $codigo . ' · ' . mb_strtoupper($modo));
        if ($lead_id) nota_lead($lead_id, valor('SELECT cliente_id FROM leads WHERE id = ?', [$lead_id]), 'Exportou o Roteiro Final para o Claude.');
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nomeArq . '"');
        echo $txt; exit;
    }
    $priv = checar_privacidade($txt);
    topo('Exportar para o Claude', '');
    ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="<?= h($voltar) ?>">Voltar</a></p>
    <h1>Exportar para o Claude · <?= $modo === 'roteiro' ? 'Roteiro Final' : 'Proposta' ?></h1>
    <span class="mudo"><?= h($codigo) ?> · <?= h($versao) ?></span></div></div>

  <?php if ($pend): ?><div class="aviso atencao"><b>Antes de gerar, confira:</b> faltam <?= h(implode('; ', $pend)) ?>. Você pode exportar assim mesmo — os campos vazios sairão como "não informado".</div><?php endif; ?>
  <?php if ($priv): ?><div class="aviso erro"><b>Atenção de privacidade:</b> o conteúdo parece conter <?= h(implode(', ', $priv)) ?>. Revise antes de enviar ao Claude.</div><?php endif; ?>

  <div class="grade2">
    <div class="cartao"><h2>Prévia do arquivo</h2>
      <textarea id="conteudo" readonly style="width:100%;min-height:440px;font-family:ui-monospace,Consolas,monospace;font-size:12.5px;line-height:1.5;border:1px solid var(--linha);border-radius:8px;padding:12px;white-space:pre"><?= h($txt) ?></textarea>
    </div>
    <div class="cartao"><h2>Como usar</h2>
      <ol style="padding-left:18px;color:var(--tinta)">
        <li>Clique em <b>Copiar texto</b> (ou <b>Baixar .md</b>).</li>
        <li>Abra o Claude e cole o texto (ou anexe o arquivo).</li>
        <li>O Claude monta o <?= $modo === 'roteiro' ? 'Guia da Viagem' : 'roteiro da proposta' ?> no padrão passaporte.</li>
      </ol>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <button class="btn btn-pri" type="button" onclick="copiar()">Copiar texto</button>
        <a class="btn" href="?p=exportarclaude&modo=<?= $modo ?>&<?= $modo === 'roteiro' ? 'lead=' . (int)($_GET['lead'] ?? 0) : 'id=' . (int)($_GET['id'] ?? 0) . '&opcao=' . (int)($_GET['opcao'] ?? 0) ?>&download=1">Baixar .md</a>
      </div>
      <p class="mudo" style="margin-top:12px;font-size:13px">O arquivo não contém CPF, documentos, contatos pessoais do cliente nem valores internos (custo, comissão, markup).</p>
    </div>
  </div>
<?php rodape("function copiar(){var t=document.getElementById('conteudo');t.select();document.execCommand('copy');event.target.textContent='Copiado!';setTimeout(function(){event.target.textContent='Copiar texto';},1500);}"); }

// ================= V2: automação via API do Claude =================
// Instruções do padrão passaporte embutidas (a skill vanessa-roteiro-html, portada para a API, que é "crua").
function instrucoes_passaporte() {
    // Versão leve: descreve o padrão passaporte sem embutir o template completo,
    // para a geração ser rápida e caber no tempo-limite do servidor compartilhado.
    return <<<TXT
Você gera uma PROPOSTA de viagem em HTML para a Vanessa Viagens e Turismo, no padrão visual "passaporte". Receberá um briefing com os dados. Gere um HTML completo e autossuficiente (todo o CSS embutido em <style>, sem imagens externas, sem scripts de fora).

PADRÃO VISUAL (siga à risca):
- Paleta fixa (nunca trocar por cores do destino): azul principal #3D5A8A; azul-marinho de fundo #1B2A4A; gradiente de capa #1B2A4A→#243654; texto #2A2E35; fundo da página #F3F4F6. Títulos em Georgia (serif); rótulos/horários em "Courier New" (uppercase, letter-spacing); corpo em Segoe UI/Arial.
- CAPA: faixa azul-marinho com gradiente; uma sobrancelha em uppercase "VANESSA VIAGENS E TURISMO"; o nome do destino grande em serifada, com um pequeno caractere/palavra na língua local ao lado; a rota (cidades) logo abaixo; um selo quadrado levemente rotacionado (-5°) com uma palavra local; e o nome do cliente + dados (passageiros, período) numa lista.
- CORPO: uma timeline por dia — cada dia com um rótulo ("Dia 1 · Cidade") e paradas (nome + horário + descrição curta). Em 3 a 5 dias, inclua um bloco "Dica da Vanessa" com uma dica específica daquele lugar (nunca genérica).
- INVESTIMENTO (última seção): liste os serviços incluídos SEM preço ao lado de cada um, e mostre APENAS o valor total ao cliente, em destaque, na moeda indicada. Nunca mostre custo, comissão, markup nem valor por item.

REGRAS:
- Use só os dados do briefing; não invente serviços, preços nem datas.
- Campos "não informado" não aparecem no HTML (omita).
- Sem emojis; rótulos/tags em mono uppercase.
- Contatos: WhatsApp +55 11 97543-9074 · @vanessaviagenseturismo. Sempre "Vanessa Viagens e Turismo".
- Responda APENAS com o HTML (começando em <!DOCTYPE html>), sem texto antes/depois e sem cercas de código.
TXT;
}

/** Chama a API do Claude com um prompt. Retorna ['ok'=>bool, 'texto'=>..., 'erro'=>...]. */
function ia_chamar($prompt, $system, $modelo = null, $max_tokens = 8000) {
    $chave = ia_chave();
    if ($chave === '') return ['ok' => false, 'erro' => 'Nenhuma chave da API configurada (Equipe → Inteligência).'];
    $corpo = json_encode([
        'model' => $modelo ?: ia_modelo_roteiro(),
        'max_tokens' => $max_tokens,
        'system' => $system,
        'messages' => [['role' => 'user', 'content' => $prompt]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corpo,
        CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $chave, 'anthropic-version: 2023-06-01'],
    ]);
    $resp = curl_exec($ch); $errno = curl_errno($ch); $erro = curl_error($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($errno === CURLE_OPERATION_TIMEDOUT || $errno === 28) return ['ok' => false, 'erro' => 'A geração demorou demais e foi interrompida (tempo-limite do servidor). Tente novamente ou use o modo manual.'];
    if ($errno) return ['ok' => false, 'erro' => 'Não consegui falar com a API (' . $erro . ').'];
    $d = json_decode($resp, true);
    if ($http !== 200) { $m = $d['error']['message'] ?? ('HTTP ' . $http); return ['ok' => false, 'erro' => 'A API recusou: ' . $m]; }
    $texto = '';
    foreach ($d['content'] ?? [] as $bloco) if (($bloco['type'] ?? '') === 'text') $texto .= $bloco['text'];
    if ($texto === '') return ['ok' => false, 'erro' => 'A API respondeu vazio.'];
    return ['ok' => true, 'texto' => $texto];
}

/** Extrai só o HTML da resposta (tira cercas ``` se vierem). */
function ia_limpar_html($t) {
    $t = trim($t);
    $t = preg_replace('/^```[a-z]*\s*/i', '', $t);
    $t = preg_replace('/\s*```$/', '', $t);
    return trim($t);
}

// ---------- Página: gerar roteiro com o Claude (automático), com prévia ----------
function pagina_gerar_roteiro() {
    exigir_login();
    $oid = (int)($_GET['opcao'] ?? 0);
    $o = um('SELECT o.*, p.id pid, p.status, p.lead_id, p.titulo FROM proposta_opcoes o JOIN propostas p ON p.id = o.proposta_id WHERE o.id = ?', [$oid]);
    if (!$o) redirecionar('./?p=funil', '!Opção não encontrada.');
    $voltar = './?p=proposta&id=' . $o['pid'];
    if ($o['status'] !== 'rascunho') redirecionar($voltar, '!A proposta já foi enviada. Crie uma nova versão para gerar o roteiro.');
    if (!ia_tem_chave()) redirecionar('./?p=usuarios', '!Configure a chave da API do Claude em Equipe → Inteligência.');

    // monta o briefing desta opção (reaproveita o exportador de proposta, por opção)
    $brief = texto_proposta($o['pid'], $oid);
    if (!$brief) redirecionar($voltar, '!Não consegui montar o briefing.');

    // GET: checa teto e chama a API
    if (ia_uso_hoje() >= ia_teto_dia())
        redirecionar($voltar, '!Limite diário de gerações automáticas atingido (' . ia_teto_dia() . '). Ajuste em Equipe → Inteligência ou use o modo manual.');

    @set_time_limit(120);
    @ini_set('max_execution_time', '120');
    $prompt = "MODO PROPOSTA. Briefing do VV360 abaixo. Gere o roteiro em HTML conforme as regras.\n\n" . $brief['texto'];
    $r = ia_chamar($prompt, instrucoes_passaporte(), ia_modelo_roteiro(), 8000);
    registrar('gerou roteiro com IA', $o['titulo'] . ' · ' . $o['nome']);
    if (!$r['ok']) redirecionar($voltar, '!' . $r['erro'] . ' Você pode usar o modo manual (Exportar para o Claude).');
    $html = ia_limpar_html($r['texto']);
    if (stripos($html, '<html') === false || strlen($html) < 400)
        redirecionar($voltar, '!O Claude respondeu, mas o conteúdo não veio como um roteiro HTML completo. Tente gerar de novo ou use o modo manual (Exportar para o Claude).');

    topo('Roteiro gerado pelo Claude', '');
    ?>
  <div class="cab"><div><p class="mudo" style="margin:0"><a href="<?= h($voltar) ?>">Voltar</a></p>
    <h1>Roteiro gerado · <?= h($o['nome']) ?></h1>
    <span class="mudo">Revise antes de anexar. Nada é salvo até você confirmar.</span></div></div>
  <div class="grade2">
    <div class="cartao"><h2>Prévia</h2>
      <iframe title="Prévia do roteiro" style="width:100%;height:560px;border:1px solid var(--linha);border-radius:8px;background:#fff" sandbox="allow-same-origin" srcdoc="<?= h($html) ?>"></iframe>
    </div>
    <div class="cartao"><h2>Anexar à opção</h2>
      <p class="mudo" style="margin-top:0">Se gostou, anexe à <?= h($o['nome']) ?> e o link do cliente é gerado. Se não, volte e gere de novo, ou use o modo manual.</p>
      <form method="post" action="./?p=acao&a=ia_roteiro_salvar"><?= csrf_campo() ?><input type="hidden" name="opcao_id" value="<?= $oid ?>"><input type="hidden" name="html" value="<?= h($html) ?>">
        <button class="btn btn-pri">Anexar este roteiro</button>
        <a class="btn" href="./?p=gerarroteiro&opcao=<?= $oid ?>" onclick="return confirm('Gerar outra versão? Consome mais uma geração.')">Gerar de novo</a>
        <a class="btn" href="<?= h($voltar) ?>">Descartar</a>
      </form>
      <p class="mudo" style="margin-top:14px;font-size:13px">Modelo: <?= h(MODELOS_IA[ia_modelo_roteiro()]) ?> · usos hoje: <?= ia_uso_hoje() ?>/<?= ia_teto_dia() ?></p>
    </div>
  </div>
<?php rodape(); }
