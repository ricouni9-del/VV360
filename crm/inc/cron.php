<?php
// Tarefas agendadas do CRM (executar pelo "Cron Jobs" do cPanel, nunca pelo navegador)
//   Lembretes diários:  php /home/SEU_USUARIO/public_html/crm/inc/cron.php lembretes
//   Backup diário:      php /home/SEU_USUARIO/public_html/crm/inc/cron.php backup
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/app.php';

$tarefa = $argv[1] ?? '';

if ($tarefa === 'backup') {
    $orig = crm_caminho_banco();
    $pasta = dirname($orig) . '/backups';
    if (!is_dir($pasta)) mkdir($pasta, 0750, true);
    $dest = $pasta . '/crm-' . date('Y-m-d') . '.sqlite';
    db()->exec('VACUUM INTO ' . db()->quote($dest));
    $chave = dirname($orig) . '/chave-documentos.php';
    if (is_file($chave) && !is_file($pasta . '/chave-documentos.php')) copy($chave, $pasta . '/chave-documentos.php');
    $arqs = glob($pasta . '/crm-*.sqlite'); sort($arqs);
    foreach (array_slice($arqs, 0, max(0, count($arqs) - 30)) as $velho) unlink($velho);
    echo "Backup salvo em $dest\n"; exit;
}

if ($tarefa === 'lembretes') {
    // Fotografia diária do termômetro, para a tela Início mostrar quem está esfriando
    $quentesSemContato = [];
    foreach (todos("SELECT l.*, c.nome FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.etapa IN ('novo','conversa','roteiro','proposta','ajustes')") as $x) {
        $t = termometro($x); if (!$t) continue;
        q('UPDATE leads SET temp_snapshot = ?, temp_snapshot_em = ? WHERE id = ?', [$t['nota'], agora(), $x['id']]);
        if ($t['nivel'] === 'quente' && dias_desde($t['ultimo_contato'] ?: $x['criado_em']) >= 2) $quentesSemContato[] = $x;
    }

    $ate = hoje();
    foreach (todos('SELECT * FROM usuarios WHERE ativo = 1') as $u) {
        $tarefas = todos('SELECT t.*, c.nome cliente, l.destino FROM tarefas t LEFT JOIN clientes c ON c.id = t.cliente_id LEFT JOIN leads l ON l.id = t.lead_id
                          WHERE t.feita = 0 AND t.vence_em <= ? AND (CAST(? AS INTEGER) = 1 OR t.usuario_id = ?)
                          ORDER BY t.vence_em', [$ate, $u['papel'] === 'admin' ? 1 : 0, $u['id']]);
        $parados = $u['papel'] === 'admin' ? todos("SELECT l.*, c.nome FROM leads l JOIN clientes c ON c.id = l.cliente_id WHERE l.etapa IN ('proposta','ajustes') AND l.etapa_desde <= ?", [date('Y-m-d H:i:s', strtotime('-5 days'))]) : [];
        $novos = $u['papel'] === 'admin' ? (int)valor("SELECT COUNT(*) FROM leads WHERE etapa = 'novo'") : 0;
        $msgs = (int)valor("SELECT COUNT(*) FROM mensagens WHERE status = 'nova'");
        $fin = [];
        if ($u['papel'] === 'admin' || $u['acesso_financeiro']) {
            foreach (todos("SELECT p.valor, p.vencimento, c.nome FROM venda_parcelas p JOIN vendas v ON v.id = p.venda_id JOIN clientes c ON c.id = v.cliente_id WHERE p.pago_em IS NULL AND p.vencimento <= ? ORDER BY p.vencimento", [date('Y-m-d', strtotime('+3 days'))]) as $x)
                $fin[] = 'Receber ' . reais($x['valor']) . ' de ' . h($x['nome']) . ' (' . data_br($x['vencimento']) . ($x['vencimento'] < $ate ? ', vencida' : '') . ')';
            foreach (todos("SELECT i.custo, i.fornecedor, i.vencimento_fornecedor, c.nome FROM venda_itens i JOIN vendas v ON v.id = i.venda_id JOIN clientes c ON c.id = v.cliente_id WHERE i.pago_direto = 0 AND i.custo > 0 AND i.pago_fornecedor_em IS NULL AND i.vencimento_fornecedor <= ?", [date('Y-m-d', strtotime('+3 days'))]) as $x)
                $fin[] = 'Pagar ' . reais($x['custo']) . ' a ' . h($x['fornecedor']) . ' (' . h($x['nome']) . ', até ' . data_br($x['vencimento_fornecedor']) . ')';
            foreach (todos("SELECT i.comissao, i.fornecedor, i.comissao_prevista FROM venda_itens i WHERE i.comissao > 0 AND i.comissao_recebida_em IS NULL AND i.comissao_prevista < ?", [$ate]) as $x)
                $fin[] = 'Cobrar comissão de ' . reais($x['comissao']) . ' de ' . h($x['fornecedor']) . ' (prevista para ' . data_br($x['comissao_prevista']) . ')';
        }
        $cot = todos("SELECT p.versao, p.validade, c.nome FROM propostas p JOIN leads l ON l.id = p.lead_id JOIN clientes c ON c.id = l.cliente_id WHERE p.status = 'enviada' AND p.validade <= ? AND l.etapa IN ('proposta','ajustes') AND (CAST(? AS INTEGER) = 1 OR l.responsavel_id = ? OR l.responsavel_id IS NULL)", [date('Y-m-d', strtotime('+3 days')), $u['papel'] === 'admin' ? 1 : 0, $u['id']]);
        $hot = array_filter($quentesSemContato, fn($x) => $u['papel'] === 'admin' || (int)$x['responsavel_id'] === (int)$u['id'] || !$x['responsavel_id']);
        if (!$tarefas && !$parados && !$novos && !$msgs && !$fin && !$cot && !$hot) continue;
        $html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:600px;color:#2A2E35">'
              . '<div style="background:#1B2A4A;color:#fff;padding:18px 22px;border-radius:8px 8px 0 0;font-family:Georgia,serif;font-size:21px">Seu dia no CRM · ' . date('d/m') . '</div>'
              . '<div style="border:1px solid #E2E5EA;border-top:none;padding:18px 22px;border-radius:0 0 8px 8px">';
        if ($novos) $html .= "<p><b>$novos lead(s) novo(s)</b> aguardando o primeiro contato.</p>";
        if ($msgs) $html .= "<p><b>$msgs mensagem(ns) do site</b> aguardando resposta (elogios, dúvidas ou reclamações).</p>";
        if ($tarefas) {
            $html .= '<p><b>Tarefas para hoje e atrasadas:</b></p><ul>';
            foreach ($tarefas as $t) $html .= '<li>' . h($t['titulo']) . ($t['cliente'] ? ' · ' . h($t['cliente']) : '') . ($t['destino'] ? ' (' . h($t['destino']) . ')' : '') . ($t['vence_em'] < $ate ? ' <b style="color:#9B1C1C">atrasada</b>' : '') . '</li>';
            $html .= '</ul>';
        }
        if ($parados) {
            $html .= '<p><b>Propostas sem retorno há mais de 5 dias:</b></p><ul>';
            foreach ($parados as $l) $html .= '<li>' . h($l['nome']) . ' · ' . h($l['destino']) . '</li>';
            $html .= '</ul>';
        }
        if ($hot) { $html .= '<p><b>🔥 Leads quentes sem contato há 2 dias ou mais:</b></p><ul>'; foreach ($hot as $x) $html .= '<li>' . h($x['nome']) . ' · ' . h($x['codigo']) . ' · ' . h($x['destino']) . '</li>'; $html .= '</ul>'; }
        if ($cot) { $html .= '<p><b>Cotações vencendo:</b></p><ul>'; foreach ($cot as $x) $html .= '<li>' . h($x['nome']) . ' · proposta v' . (int)$x['versao'] . ' · validade ' . data_br($x['validade']) . '</li>'; $html .= '</ul>'; }
        if ($fin) $html .= '<p><b>Financeiro:</b></p><ul><li>' . implode('</li><li>', $fin) . '</li></ul>';
        $html .= '<p><a href="https://vanessaviagenseturismo.com.br/crm/" style="background:#3D5A8A;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none">Abrir o CRM</a></p></div></div>';
        $cab = "From: CRM Vanessa Viagens e Turismo <" . EMAIL_REMETENTE . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8";
        mail($u['email'], '=?UTF-8?B?' . base64_encode('CRM · Seu dia ' . date('d/m')) . '?=', $html, $cab);
        echo "Lembrete enviado para {$u['email']}\n";
    }
    exit;
}
if ($tarefa === 'senha') {
    // Recuperação de acesso pelo Terminal do cPanel: php cron.php senha email@dominio NovaSenhaForte
    $u = um('SELECT id FROM usuarios WHERE email = ?', [$argv[2] ?? '']);
    if (!$u || strlen($argv[3] ?? '') < 10) { echo "Uso: php cron.php senha email@dominio NovaSenha (mínimo 10 caracteres)\n"; exit(1); }
    q('UPDATE usuarios SET senha_hash = ?, ativo = 1 WHERE id = ?', [password_hash($argv[3], PASSWORD_DEFAULT), $u['id']]);
    q('DELETE FROM tentativas');
    q('INSERT INTO registro (usuario_id, acao, detalhe, criado_em) VALUES (?,?,?,?)', [$u['id'], 'senha redefinida pelo Terminal', '', agora()]);
    echo "Senha redefinida.\n"; exit;
}
if ($tarefa === 'cambio') {
    // Atualiza a cotação (dólar/euro) uma vez por dia. Fonte: AwesomeAPI (BR). Falha silenciosa: mantém a última taxa.
    $url = 'https://economia.awesomeapi.com.br/json/last/USD-BRL,EUR-BRL';
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) { echo "Câmbio: não foi possível consultar agora (mantida a última taxa).\n"; exit; }
    $d = json_decode($raw, true);
    $map = ['USD' => $d['USDBRL']['bid'] ?? null, 'EUR' => $d['EURBRL']['bid'] ?? null];
    foreach ($map as $moeda => $bid) {
        if (!$bid) continue;
        // comercial + ~4% (aproximação de turismo); a Vanessa ajusta na proposta se quiser
        $turismo = round((float)$bid * 1.04, 4);
        q('INSERT INTO cambio (moeda, taxa, atualizado_em) VALUES (?,?,?) ON CONFLICT(moeda) DO UPDATE SET taxa = ?, atualizado_em = ?', [$moeda, $turismo, agora(), $turismo, agora()]);
        echo "Câmbio $moeda: $turismo\n";
    }
    exit;
}
echo "Uso: php cron.php lembretes|backup|senha|cambio\n";
