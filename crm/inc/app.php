<?php
// Vanessa Viagens e Turismo · CRM — núcleo
require dirname(__DIR__, 2) . '/dados/lib.php';

const CRM_VERSAO = 17;
const MODELOS_IA = ['claude-haiku-5-5' => 'Claude Haiku (rápido e barato)', 'claude-sonnet-5-5' => 'Claude Sonnet (melhor qualidade)'];
const ETAPA_ENTREGA = 'entrega'; // entrega de vouchers avulsa, fora do funil
const MOEDAS = ['BRL' => 'Real (R$)', 'USD' => 'Dólar (US$)', 'EUR' => 'Euro (€)'];
const SIMBOLO_MOEDA = ['BRL' => 'R$', 'USD' => 'US$', 'EUR' => '€'];
const CATEGORIAS_DOC = ['hospedagem' => 'Hospedagem', 'voos' => 'Voos', 'passeios' => 'Passeios e ingressos', 'transfer' => 'Transfer', 'seguro' => 'Seguro', 'roteiro' => 'Roteiro', 'outros' => 'Outros'];
const CONFIG_PADRAO = ['doc_max_mb' => 10, 'doc_retencao_dias' => 7];
const NIVEIS_TEMP = ['quente' => ['🔥', 'Quente'], 'morno' => ['🌤', 'Morno'], 'frio' => ['❄️', 'Frio']];
const ETAPAS_ABERTAS = ['novo', 'conversa', 'roteiro', 'proposta', 'ajustes', 'aprovada'];
const STATUS_PROPOSTA = ['rascunho' => 'Rascunho', 'enviada' => 'Enviada', 'substituida' => 'Substituída', 'aprovada' => 'Aprovada', 'recusada' => 'Recusada'];
const FORMAS_PAGAMENTO = ['Pix', 'Boleto', 'Transferência', 'Cartão (pela operadora)', 'Cartão (maquininha da agência)', 'Dinheiro', 'Outro'];
const SERVICOS = ['Aéreo', 'Hospedagem', 'Pacote / operadora', 'Ingressos e passeios', 'Traslados', 'Locação de carro', 'Seguro viagem', 'Cruzeiro', 'Trem', 'Outro'];
const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
const TIPOS_MENSAGEM = ['elogio' => 'Elogio', 'duvida' => 'Dúvida', 'reclamacao' => 'Reclamação'];
const STATUS_MENSAGEM = ['nova' => 'Nova', 'respondida' => 'Respondida', 'publicado' => 'Publicado no site', 'nao_publicado' => 'Não publicado'];
const ETAPAS = [
    'novo'     => 'Novo lead',
    'conversa' => 'Conversa inicial',
    'roteiro'  => 'Montando roteiro',
    'proposta' => 'Proposta enviada',
    'ajustes'  => 'Ajustes',
    'aprovada' => 'Aprovada pelo Cliente/Aguardando fechamento',
    'fechado'  => 'Fechado',
    'viagem'   => 'Em viagem',
    'pos'      => 'Pós-viagem',
    'perdido'  => 'Perdido',
];
const ORIGENS = ['Site', 'Google Ads', 'Busca Google', 'Instagram', 'Facebook', 'WhatsApp', 'Indicação', 'Cliente recorrente', 'Outro'];
const TIPOS_ATIVIDADE = ['nota' => 'Nota', 'ligacao' => 'Ligação', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'reuniao' => 'Reunião', 'proposta' => 'Proposta enviada'];
const MOTIVOS_PERDA = ['Preço', 'Adiou a viagem', 'Desistiu da viagem', 'Fechou com outra agência', 'Sem retorno do cliente', 'Outro'];

// ---------- Banco de dados ----------
function crm_caminho_banco() {
    $pasta = CRM_PASTA_DADOS;
    if (!is_dir($pasta)) @mkdir($pasta, 0750, true);
    if (!is_dir($pasta) || !is_writable($pasta)) $pasta = dirname(__DIR__, 2) . '/dados';
    return $pasta . '/crm.sqlite';
}

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . crm_caminho_banco());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    crm_migrar($pdo);
    return $pdo;
}

function crm_migrar(PDO $pdo) {
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= CRM_VERSAO) return;
    if ($v < 1) $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS usuarios (
  id INTEGER PRIMARY KEY, nome TEXT NOT NULL, email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  senha_hash TEXT NOT NULL, papel TEXT NOT NULL DEFAULT 'usuario', ativo INTEGER NOT NULL DEFAULT 1,
  criado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS clientes (
  id INTEGER PRIMARY KEY, nome TEXT NOT NULL, email TEXT, telefone TEXT, cidade TEXT,
  origem TEXT, aniversario TEXT, preferencias TEXT, notas TEXT,
  criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS viajantes (
  id INTEGER PRIMARY KEY, cliente_id INTEGER NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
  nome TEXT NOT NULL, parentesco TEXT, nascimento TEXT, observacoes TEXT);
CREATE TABLE IF NOT EXISTS leads (
  id INTEGER PRIMARY KEY, cliente_id INTEGER NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
  destino TEXT, periodo TEXT, duracao TEXT, adultos INTEGER, criancas TEXT, orcamento TEXT,
  ocasiao TEXT, detalhes TEXT, origem TEXT, etapa TEXT NOT NULL DEFAULT 'novo',
  valor_estimado REAL, motivo_perda TEXT, responsavel_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  data_embarque TEXT, data_retorno TEXT,
  etapa_desde TEXT NOT NULL, criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS atividades (
  id INTEGER PRIMARY KEY, lead_id INTEGER REFERENCES leads(id) ON DELETE CASCADE,
  cliente_id INTEGER REFERENCES clientes(id) ON DELETE CASCADE,
  usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  tipo TEXT NOT NULL, texto TEXT NOT NULL, criado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS tarefas (
  id INTEGER PRIMARY KEY, lead_id INTEGER REFERENCES leads(id) ON DELETE CASCADE,
  cliente_id INTEGER REFERENCES clientes(id) ON DELETE CASCADE,
  usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  titulo TEXT NOT NULL, vence_em TEXT NOT NULL, feita INTEGER NOT NULL DEFAULT 0,
  feita_em TEXT, criado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS importados (msg_id TEXT PRIMARY KEY, lead_id INTEGER, importado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS tentativas (chave TEXT NOT NULL, t INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS registro (
  id INTEGER PRIMARY KEY, usuario_id INTEGER, acao TEXT NOT NULL, detalhe TEXT, ip TEXT, criado_em TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS ix_leads_etapa ON leads(etapa);
CREATE INDEX IF NOT EXISTS ix_leads_cliente ON leads(cliente_id);
CREATE INDEX IF NOT EXISTS ix_tarefas_vence ON tarefas(feita, vence_em);
CREATE INDEX IF NOT EXISTS ix_ativ_lead ON atividades(lead_id);
SQL);
    if ($v < 2) $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS mensagens (
  id INTEGER PRIMARY KEY, tipo TEXT NOT NULL, nome TEXT NOT NULL, contato TEXT, email TEXT, telefone TEXT,
  viagem TEXT, texto TEXT NOT NULL, autoriza_publicar INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'nova',
  nome_publico TEXT, texto_publico TEXT, publicado_em TEXT,
  cliente_id INTEGER REFERENCES clientes(id) ON DELETE CASCADE, lead_id INTEGER REFERENCES leads(id) ON DELETE SET NULL,
  criado_em TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS ix_msg_status ON mensagens(status, tipo);
SQL);
    if ($v < 3) {
        foreach (['cpf_enc TEXT', 'cpf_hash TEXT', 'rg_enc TEXT', 'passaporte_enc TEXT', 'passaporte_emissao TEXT', 'passaporte_validade TEXT',
                  'nascimento TEXT', 'cep TEXT', 'logradouro TEXT', 'numero TEXT', 'complemento TEXT', 'bairro TEXT', 'uf TEXT'] as $col)
            $pdo->exec("ALTER TABLE clientes ADD COLUMN $col");
        foreach (['cpf_enc TEXT', 'rg_enc TEXT', 'passaporte_enc TEXT', 'passaporte_emissao TEXT', 'passaporte_validade TEXT'] as $col)
            $pdo->exec("ALTER TABLE viajantes ADD COLUMN $col");
        $pdo->exec(<<<SQL
CREATE UNIQUE INDEX IF NOT EXISTS ux_clientes_cpf ON clientes(cpf_hash) WHERE cpf_hash IS NOT NULL;
CREATE TABLE IF NOT EXISTS viagens (
  id INTEGER PRIMARY KEY, cliente_id INTEGER NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
  lead_id INTEGER REFERENCES leads(id) ON DELETE SET NULL,
  destino TEXT NOT NULL, ida TEXT, volta TEXT, passageiros TEXT, hospedagem TEXT, fornecedor TEXT,
  valor REAL, observacoes TEXT, fonte TEXT NOT NULL DEFAULT 'manual', criado_em TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS ix_viagens_cliente ON viagens(cliente_id, ida);
SQL);
    }
    if ($v < 4) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN acesso_financeiro INTEGER NOT NULL DEFAULT 0');
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS vendas (
  id INTEGER PRIMARY KEY, lead_id INTEGER UNIQUE REFERENCES leads(id) ON DELETE SET NULL,
  cliente_id INTEGER NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
  descricao TEXT, data_venda TEXT NOT NULL, observacoes TEXT, criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS venda_itens (
  id INTEGER PRIMARY KEY, venda_id INTEGER NOT NULL REFERENCES vendas(id) ON DELETE CASCADE,
  fornecedor TEXT NOT NULL, servico TEXT, pago_direto INTEGER NOT NULL DEFAULT 0,
  valor_cliente REAL NOT NULL DEFAULT 0, custo REAL NOT NULL DEFAULT 0, comissao_pct REAL, comissao REAL NOT NULL DEFAULT 0,
  vencimento_fornecedor TEXT, pago_fornecedor_em TEXT, comissao_prevista TEXT, comissao_recebida_em TEXT, observacoes TEXT);
CREATE TABLE IF NOT EXISTS venda_parcelas (
  id INTEGER PRIMARY KEY, venda_id INTEGER NOT NULL REFERENCES vendas(id) ON DELETE CASCADE,
  descricao TEXT, vencimento TEXT NOT NULL, valor REAL NOT NULL, forma TEXT, pago_em TEXT);
CREATE INDEX IF NOT EXISTS ix_parc_venc ON venda_parcelas(pago_em, vencimento);
CREATE INDEX IF NOT EXISTS ix_itens_venda ON venda_itens(venda_id);
SQL);
        $pdo->exec("UPDATE usuarios SET acesso_financeiro = 1 WHERE papel = 'admin'");
    }
    if ($v < 5) $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS propostas (
  id INTEGER PRIMARY KEY, lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
  versao INTEGER NOT NULL, titulo TEXT, status TEXT NOT NULL DEFAULT 'rascunho', validade TEXT,
  enviada_em TEXT, decidida_em TEXT, roteiro_url TEXT, roteiro_token TEXT UNIQUE, roteiro_nome TEXT,
  observacoes TEXT, origem TEXT NOT NULL DEFAULT 'manual', criado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL, UNIQUE (lead_id, versao));
CREATE TABLE IF NOT EXISTS proposta_opcoes (
  id INTEGER PRIMARY KEY, proposta_id INTEGER NOT NULL REFERENCES propostas(id) ON DELETE CASCADE,
  nome TEXT NOT NULL, ordem INTEGER NOT NULL DEFAULT 1, escolhida INTEGER NOT NULL DEFAULT 0);
CREATE TABLE IF NOT EXISTS proposta_itens (
  id INTEGER PRIMARY KEY, opcao_id INTEGER NOT NULL REFERENCES proposta_opcoes(id) ON DELETE CASCADE,
  fornecedor TEXT NOT NULL, servico TEXT, pago_direto INTEGER NOT NULL DEFAULT 0,
  valor_cliente REAL NOT NULL DEFAULT 0, custo REAL NOT NULL DEFAULT 0, comissao_pct REAL, comissao REAL NOT NULL DEFAULT 0, observacoes TEXT);
CREATE INDEX IF NOT EXISTS ix_prop_lead ON propostas(lead_id, versao);
CREATE INDEX IF NOT EXISTS ix_prop_status ON propostas(status, validade);
SQL);
    if ($v < 6) {
        foreach (['codigo TEXT', 'temp_manual TEXT', 'temp_manual_em TEXT', 'temp_snapshot INTEGER', 'temp_snapshot_em TEXT', 'gclid TEXT'] as $col)
            $pdo->exec("ALTER TABLE leads ADD COLUMN $col");
        $seq = [];
        foreach ($pdo->query('SELECT id, criado_em FROM leads ORDER BY criado_em, id')->fetchAll() as $r) {
            $ano = substr($r['criado_em'], 0, 4); $seq[$ano] = ($seq[$ano] ?? 0) + 1;
            $pdo->prepare('UPDATE leads SET codigo = ? WHERE id = ?')->execute([sprintf('VV-%s-%04d', $ano, $seq[$ano]), $r['id']]);
        }
        $pdo->exec(<<<SQL
CREATE UNIQUE INDEX IF NOT EXISTS ux_leads_codigo ON leads(codigo);
CREATE TRIGGER IF NOT EXISTS trg_lead_codigo AFTER INSERT ON leads WHEN NEW.codigo IS NULL BEGIN
  UPDATE leads SET codigo = 'VV-' || substr(NEW.criado_em, 1, 4) || '-' || printf('%04d',
    (SELECT COALESCE(MAX(CAST(substr(codigo, 9) AS INTEGER)), 0) + 1 FROM leads WHERE codigo LIKE 'VV-' || substr(NEW.criado_em, 1, 4) || '-%'))
  WHERE id = NEW.id;
END;
CREATE TABLE IF NOT EXISTS roteiro_acessos (
  id INTEGER PRIMARY KEY, proposta_id INTEGER NOT NULL REFERENCES propostas(id) ON DELETE CASCADE,
  acessado_em TEXT NOT NULL, ip_hash TEXT);
CREATE INDEX IF NOT EXISTS ix_acessos ON roteiro_acessos(proposta_id, acessado_em);
SQL);
    }
    if ($v < 7) {
        foreach (['roteiro_token TEXT', 'roteiro_nome TEXT', 'roteiro_url TEXT'] as $col)
            $pdo->exec("ALTER TABLE proposta_opcoes ADD COLUMN $col");
        $pdo->exec('ALTER TABLE roteiro_acessos ADD COLUMN opcao_id INTEGER');
        // migra o roteiro que hoje está na proposta para a primeira opção de cada uma
        foreach ($pdo->query("SELECT id, roteiro_token, roteiro_nome, roteiro_url FROM propostas WHERE roteiro_token IS NOT NULL OR (roteiro_url IS NOT NULL AND roteiro_url <> '')")->fetchAll() as $pr) {
            $oid = $pdo->query('SELECT id FROM proposta_opcoes WHERE proposta_id = ' . (int)$pr['id'] . ' ORDER BY ordem, id LIMIT 1')->fetchColumn();
            if (!$oid) continue;
            $st = $pdo->prepare('UPDATE proposta_opcoes SET roteiro_token = ?, roteiro_nome = ?, roteiro_url = ? WHERE id = ?');
            $st->execute([$pr['roteiro_token'] ?: null, $pr['roteiro_nome'] ?: null, $pr['roteiro_url'] ?: null, $oid]);
            if ($pr['roteiro_token']) $pdo->exec('UPDATE roteiro_acessos SET opcao_id = ' . (int)$oid . ' WHERE proposta_id = ' . (int)$pr['id'] . ' AND opcao_id IS NULL');
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_opcao_token ON proposta_opcoes(roteiro_token) WHERE roteiro_token IS NOT NULL');
        $pdo->exec('CREATE INDEX IF NOT EXISTS ix_acessos_opcao ON roteiro_acessos(opcao_id, acessado_em)');
    }
    if ($v < 8) {
        foreach (['aprovada_cliente_em TEXT', 'aprovada_cliente_nome TEXT', 'aprovada_cliente_ip TEXT'] as $col)
            $pdo->exec("ALTER TABLE proposta_opcoes ADD COLUMN $col");
    }
    if ($v < 9) {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS config (chave TEXT PRIMARY KEY, valor TEXT);
CREATE TABLE IF NOT EXISTS viagem_docs (
  id INTEGER PRIMARY KEY, lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
  nome TEXT NOT NULL, categoria TEXT NOT NULL DEFAULT 'outros', arquivo TEXT NOT NULL, ext TEXT, tamanho INTEGER,
  inicio TEXT, fim TEXT, hora TEXT, criado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS portais (
  id INTEGER PRIMARY KEY, lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
  tipo TEXT NOT NULL, token TEXT NOT NULL UNIQUE, criado_em TEXT NOT NULL, expira_em TEXT, revogado INTEGER NOT NULL DEFAULT 0);
CREATE TABLE IF NOT EXISTS pesquisas (
  id INTEGER PRIMARY KEY, lead_id INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
  cliente_id INTEGER REFERENCES clientes(id) ON DELETE CASCADE,
  nota_geral INTEGER, recomenda TEXT, comentario TEXT, autoriza_publicar INTEGER NOT NULL DEFAULT 0,
  respondida_em TEXT, criado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS pesquisa_itens (
  id INTEGER PRIMARY KEY, pesquisa_id INTEGER NOT NULL REFERENCES pesquisas(id) ON DELETE CASCADE,
  servico TEXT NOT NULL, fornecedor TEXT, nota INTEGER);
CREATE INDEX IF NOT EXISTS ix_docs_lead ON viagem_docs(lead_id);
CREATE INDEX IF NOT EXISTS ix_portais_token ON portais(token);
CREATE INDEX IF NOT EXISTS ix_pesq_lead ON pesquisas(lead_id);
SQL);
    }
    if ($v < 10) {
        foreach (['prox_destino TEXT', 'prox_quando TEXT', 'prox_quem TEXT', 'prox_contato TEXT', 'prox_obs TEXT', 'prox_lead_id INTEGER'] as $col)
            $pdo->exec("ALTER TABLE pesquisas ADD COLUMN $col");
    }
    if ($v < 11) {
        // orçamento comercial: moeda, taxa e markup na opção; serviço+detalhes+valor no item
        foreach (['moeda TEXT NOT NULL DEFAULT \'BRL\'', 'taxa_cambio REAL', 'markup REAL NOT NULL DEFAULT 0'] as $col)
            $pdo->exec("ALTER TABLE proposta_opcoes ADD COLUMN $col");
        foreach (['detalhes TEXT'] as $col) $pdo->exec("ALTER TABLE proposta_itens ADD COLUMN $col");
        // converte itens antigos: o 'fornecedor' antigo vira detalhe textual; valor_cliente permanece
        $pdo->exec("UPDATE proposta_itens SET detalhes = TRIM(COALESCE(fornecedor,'') || CASE WHEN observacoes IS NOT NULL AND observacoes <> '' THEN ' · ' || observacoes ELSE '' END) WHERE detalhes IS NULL");
        // cotações de câmbio (atualizadas 1x/dia pela rotina)
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS cambio (moeda TEXT PRIMARY KEY, taxa REAL NOT NULL, atualizado_em TEXT NOT NULL);
SQL);
        // fechamento por serviço: custo em moeda própria, taxa e data do serviço na venda
        foreach (['custo_moeda TEXT', 'custo_taxa REAL', 'fechado_em TEXT', 'detalhes TEXT'] as $col)
            $pdo->exec("ALTER TABLE venda_itens ADD COLUMN $col");
    }
    if ($v < 12) {
        foreach (['data_inicio TEXT', 'data_fim TEXT'] as $col)
            $pdo->exec("ALTER TABLE proposta_opcoes ADD COLUMN $col");
    }
    if ($v < 13) { /* entregas avulsas usam leads.etapa = 'entrega'; sem alteração de esquema */ }
    if ($v < 14) {
        $pdo->exec("ALTER TABLE proposta_opcoes ADD COLUMN aprovada_cliente_obs TEXT");
    }
    if ($v < 15) {
        // campos operacionais de reserva, para o Roteiro Final (Guia da Viagem)
        foreach (['reserva_codigo TEXT','voo_numero TEXT','voo_partida TEXT','voo_chegada TEXT','voo_origem TEXT','voo_destino TEXT','bagagem TEXT','fornecedor_contato TEXT','hora_apresentacao TEXT','endereco TEXT','regime TEXT','reserva_obs TEXT'] as $col)
            $pdo->exec("ALTER TABLE venda_itens ADD COLUMN $col");
        // seguro e contatos de emergência por viagem (na venda)
        foreach (['seguradora TEXT','seguro_plano TEXT','seguro_apolice TEXT','seguro_central TEXT','plantao_emergencia TEXT','guia_obs TEXT'] as $col)
            $pdo->exec("ALTER TABLE vendas ADD COLUMN $col");
    }
    if ($v < 16) { /* V2: chave da API e config de IA ficam na tabela config (cifradas) */ }
    if ($v < 17) {
        // Título da proposta deixou de ser pré-preenchido com o destino do lead (ficava colado
        // em todas as versões). Limpa os títulos que nunca foram personalizados — isto é, os que
        // ainda são exatamente iguais ao destino do lead — para que o rótulo passe a cair no
        // nome da opção. Títulos que a Vanessa renomeou (diferentes do destino) são preservados.
        $pdo->exec("UPDATE propostas SET titulo = '' WHERE titulo IS NOT NULL AND titulo <> '' AND titulo = (SELECT destino FROM leads WHERE leads.id = propostas.lead_id)");
        // Módulo de Grupos — Fase 1 (núcleo): o grupo/excursão e os participantes na jornada
        // Interesse → Confirmando → Confirmado. Roda em instalação nova e em upgrade.
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS grupos (
  id INTEGER PRIMARY KEY, codigo TEXT,
  titulo TEXT NOT NULL, destino TEXT,
  data_inicio TEXT, data_fim TEXT, periodo TEXT,
  vagas_total INTEGER NOT NULL DEFAULT 0, vagas_minimo INTEGER NOT NULL DEFAULT 0,
  valor REAL NOT NULL DEFAULT 0, moeda TEXT NOT NULL DEFAULT 'BRL',
  descricao TEXT, roteiro_url TEXT,
  status TEXT NOT NULL DEFAULT 'planejamento',
  responsavel_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS grupo_participantes (
  id INTEGER PRIMARY KEY, grupo_id INTEGER NOT NULL REFERENCES grupos(id) ON DELETE CASCADE,
  cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
  nome TEXT NOT NULL, contato TEXT, pessoas INTEGER NOT NULL DEFAULT 1,
  etapa TEXT NOT NULL DEFAULT 'interesse', obs TEXT,
  criado_em TEXT NOT NULL, atualizado_em TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS ix_grupo_part ON grupo_participantes(grupo_id);
SQL);
    }
    $pdo->exec('PRAGMA user_version = ' . CRM_VERSAO);
}

/**
 * Rótulo de exibição da proposta: usa o Título quando a Vanessa o definiu; senão cai no nome
 * da opção (a escolhida, ou a primeira), que é onde o destino real de cada versão vive; por fim
 * o destino do lead. Evita o antigo problema do título congelado no destino inicial do lead.
 */
function rotulo_proposta($p, $ops = null) {
    if (!empty($p['titulo'])) return $p['titulo'];
    if ($ops === null && !empty($p['id']))
        $ops = todos('SELECT nome, escolhida, ordem FROM proposta_opcoes WHERE proposta_id = ? ORDER BY ordem, id', [$p['id']]);
    if ($ops) {
        foreach ($ops as $o) if (!empty($o['escolhida']) && trim((string)$o['nome']) !== '') return $o['nome'];
        foreach ($ops as $o) if (trim((string)$o['nome']) !== '') return $o['nome'];
    }
    return $p['destino'] ?? '' ?: 'Proposta';
}

// ---------- Grupos / Excursões (Fase 1 · núcleo) ----------
const GRUPO_ETAPAS = ['interesse' => 'Interesse', 'confirmando' => 'Confirmando', 'confirmado' => 'Confirmado', 'desistiu' => 'Desistiu'];
const GRUPO_STATUS = ['planejamento' => 'Planejamento', 'aberto' => 'Vendas abertas', 'confirmado' => 'Grupo confirmado', 'fechado' => 'Fechado', 'cancelado' => 'Cancelado'];

/** Painel de viabilidade de um grupo: vagas, pessoas por etapa e receita projetada. */
function grupo_resumo($g) {
    $gid = (int)(is_array($g) ? $g['id'] : $g);
    $por = []; foreach (array_keys(GRUPO_ETAPAS) as $e) $por[$e] = 0;
    foreach (todos('SELECT etapa, COALESCE(SUM(pessoas),0) n FROM grupo_participantes WHERE grupo_id = ? GROUP BY etapa', [$gid]) as $r)
        $por[$r['etapa']] = (int)$r['n'];
    $valor = is_array($g) ? (float)$g['valor'] : (float)valor('SELECT valor FROM grupos WHERE id = ?', [$gid]);
    $total = is_array($g) ? (int)$g['vagas_total'] : (int)valor('SELECT vagas_total FROM grupos WHERE id = ?', [$gid]);
    $minimo = is_array($g) ? (int)$g['vagas_minimo'] : (int)valor('SELECT vagas_minimo FROM grupos WHERE id = ?', [$gid]);
    $conf = $por['confirmado']; $prov = $por['confirmando']; $inter = $por['interesse'];
    return [
        'por' => $por, 'confirmados' => $conf, 'confirmando' => $prov, 'interesse' => $inter,
        'vagas_total' => $total, 'vagas_minimo' => $minimo,
        'vagas_restantes' => max(0, $total - $conf),
        'falta_minimo' => max(0, $minimo - $conf),
        'viavel' => $minimo > 0 && $conf >= $minimo,
        'perto' => $minimo > 0 && $conf < $minimo && ($conf + $prov) >= $minimo,
        'lotado' => $total > 0 && $conf >= $total,
        'ocup_pct' => $total > 0 ? min(100, round($conf / $total * 100)) : 0,
        'receita_confirmada' => $conf * $valor,
        'receita_potencial' => ($conf + $prov) * $valor,
        'moeda' => is_array($g) ? ($g['moeda'] ?? 'BRL') : (valor('SELECT moeda FROM grupos WHERE id = ?', [$gid]) ?: 'BRL'),
    ];
}

function grupo_proximo_codigo() {
    $ano = date('Y');
    $seq = (int)valor("SELECT COALESCE(MAX(CAST(substr(codigo,10) AS INTEGER)),0) FROM grupos WHERE codigo LIKE ?", ['GRP-' . $ano . '-%']) + 1;
    return sprintf('GRP-%s-%03d', $ano, $seq);
}

// ---------- Documentos: criptografia e validação ----------
function crm_chave() {
    static $k = null;
    if ($k !== null) return $k;
    $arq = dirname(crm_caminho_banco()) . '/chave-documentos.php';
    if (!is_file($arq)) {
        $nova = base64_encode(random_bytes(32));
        file_put_contents($arq, "<?php\n// NÃO APAGUE nem altere: sem esta chave os documentos dos clientes ficam ilegíveis.\n// Guarde uma cópia junto com os backups do CRM.\nreturn '$nova';\n", LOCK_EX);
        @chmod($arq, 0640);
    }
    return $k = base64_decode(require $arq);
}
function cripto($txt) {
    $txt = trim((string)$txt);
    if ($txt === '') return null;
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($txt, 'aes-256-gcm', crm_chave(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $ct);
}
function decripto($enc) {
    if (!$enc || strpos($enc, 'v1:') !== 0) return '';
    $b = base64_decode(substr($enc, 3));
    $r = openssl_decrypt(substr($b, 28), 'aes-256-gcm', crm_chave(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
    return $r === false ? '' : $r;
}
function cpf_hash($cpf) { $d = so_digitos($cpf); return strlen($d) === 11 ? hash_hmac('sha256', $d, crm_chave()) : null; }
function cpf_valido($cpf) {
    $c = so_digitos($cpf);
    if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) return false;
    for ($t = 9; $t < 11; $t++) {
        $s = 0; for ($i = 0; $i < $t; $i++) $s += (int)$c[$i] * (($t + 1) - $i);
        if ((int)$c[$t] !== ((10 * $s) % 11) % 10) return false;
    }
    return true;
}
function cpf_formatado($cpf) { $c = so_digitos($cpf); return strlen($c) === 11 ? substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9) : $cpf; }
function mascarar($txt, $visiveis = 3) { $t = (string)$txt; if ($t === '') return ''; return str_repeat('•', max(3, mb_strlen($t) - $visiveis)) . mb_substr($t, -$visiveis); }
function situacao_passaporte($validade) {
    if (!$validade) return ['', ''];
    $dias = (int)floor((strtotime($validade) - strtotime(hoje())) / 86400);
    if ($dias < 0) return ['perdido', 'Vencido'];
    if ($dias < 183) return ['proposta', 'Vence em menos de 6 meses'];
    return ['fechado', 'Válido'];
}

function duracao_viagem($ini, $fim) {
    if (!$ini || !$fim) return '';
    $n = (int)round((strtotime($fim) - strtotime($ini)) / 86400);
    if ($n < 0) return '';
    $dias = $n + 1;
    return $dias . ($dias == 1 ? ' dia' : ' dias') . ($n > 0 ? ' / ' . $n . ($n == 1 ? ' noite' : ' noites') : '');
}
function moeda_fmt($v, $moeda = 'BRL') {
    $s = SIMBOLO_MOEDA[$moeda] ?? 'R$';
    return $v === null || $v === '' ? '' : $s . ' ' . number_format((float)$v, 2, ',', '.');
}
function cambio_sugestao($moeda) {
    if ($moeda === 'BRL') return 1.0;
    $r = um('SELECT taxa FROM cambio WHERE moeda = ?', [$moeda]);
    return $r ? (float)$r['taxa'] : null;
}
function cfg($chave, $padrao = null) {
    static $c = null;
    if ($c === null) { $c = []; foreach (todos('SELECT chave, valor FROM config') as $r) $c[$r['chave']] = $r['valor']; }
    if (array_key_exists($chave, $c)) return $c[$chave];
    return $padrao !== null ? $padrao : (CONFIG_PADRAO[$chave] ?? null);
}
function set_cfg($chave, $valor) { q('INSERT INTO config (chave, valor) VALUES (?,?) ON CONFLICT(chave) DO UPDATE SET valor = ?', [$chave, $valor, $valor]); }
function ia_chave() { return decripto(cfg('ia_chave_enc', '')); }           // chave da API em claro (só no servidor)
function ia_tem_chave() { return cfg('ia_chave_enc', '') !== ''; }
function ia_modelo_roteiro() { $m = cfg('ia_modelo', 'claude-sonnet-5-5'); return isset(MODELOS_IA[$m]) ? $m : 'claude-sonnet-5-5'; }
function ia_teto_dia() { return (int)cfg('ia_teto_dia', 30); }
function ia_uso_hoje() { return (int)valor("SELECT COUNT(*) FROM registro WHERE acao = 'gerou roteiro com IA' AND criado_em >= ?", [hoje() . ' 00:00:00']); }
function pasta_docs() { $p = dirname(crm_caminho_banco()) . '/docs'; if (!is_dir($p)) mkdir($p, 0750, true); return $p; }

function agora() { return date('Y-m-d H:i:s'); }
function hoje() { return date('Y-m-d'); }
function q($sql, $p = []) { $s = db()->prepare($sql); $s->execute($p); return $s; }
function um($sql, $p = []) { return q($sql, $p)->fetch(); }
function todos($sql, $p = []) { return q($sql, $p)->fetchAll(); }
function valor($sql, $p = []) { return q($sql, $p)->fetchColumn(); }

// ---------- Sessão e acesso ----------
function crm_iniciar_sessao() {
    session_name('vvcrm');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Strict', 'path' => dirname($_SERVER['SCRIPT_NAME']) . '/']);
    session_start();
    if (!empty($_SESSION['uid']) && (time() - ($_SESSION['ultimo'] ?? 0)) > 8 * 3600) { session_unset(); }
    $_SESSION['ultimo'] = time();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function usuario() {
    static $u = false;
    if ($u !== false) return $u;
    $u = empty($_SESSION['uid']) ? null : um('SELECT * FROM usuarios WHERE id = ? AND ativo = 1', [$_SESSION['uid']]);
    return $u ?: null;
}
function exigir_login() { if (!usuario()) { header('Location: ./?p=entrar'); exit; } }
function pode_financeiro() { $u = usuario(); return $u && ($u['papel'] === 'admin' || !empty($u['acesso_financeiro'])); }
function exigir_financeiro() { exigir_login(); if (!pode_financeiro()) { http_response_code(403); exit('Acesso restrito à equipe financeira.'); } }
function exigir_admin() { exigir_login(); if (usuario()['papel'] !== 'admin') { http_response_code(403); exit('Acesso restrito ao administrador.'); } }
function csrf_campo() { return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">'; }
function checar_csrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''))) {
        http_response_code(400); exit('Sessão expirada. Volte e recarregue a página.');
    }
}
function ip_hash() { return substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'vvcrm'), 0, 16); }
function registrar($acao, $detalhe = '') {
    q('INSERT INTO registro (usuario_id, acao, detalhe, ip, criado_em) VALUES (?,?,?,?,?)',
      [usuario()['id'] ?? null, $acao, mb_substr($detalhe, 0, 500), ip_hash(), agora()]);
}
function bloqueado($chave) {
    q('DELETE FROM tentativas WHERE chave = ? AND t < ?', [$chave, time() - 900]);
    return valor('SELECT COUNT(*) FROM tentativas WHERE chave = ?', [$chave]) >= 5;
}
function falhou($chave) { q('INSERT INTO tentativas (chave, t) VALUES (?, ?)', [$chave, time()]); }

// ---------- Utilidades ----------
function redirecionar($url, $aviso = null) { if ($aviso) $_SESSION['aviso'] = $aviso; header('Location: ' . $url); exit; }
function aviso() { $a = $_SESSION['aviso'] ?? null; unset($_SESSION['aviso']); return $a; }
function post($k, $max = 500) { return trim(mb_substr((string)($_POST[$k] ?? ''), 0, $max)); }
function data_br($d, $hora = false) { if (!$d) return ''; $t = strtotime($d); return $t ? date($hora ? 'd/m/Y H:i' : 'd/m/Y', $t) : h($d); }
function data_valida($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null; }
function reais($v) { if ($v === null || $v === '') return ''; $f = (float)$v; return 'R$ ' . number_format($f, fmod($f, 1) ? 2 : 0, ',', '.'); }
function num_valor($s) { $s = preg_replace('/[^\d,\.]/', '', (string)$s); if ($s === '') return null; $s = str_replace('.', '', $s); return (float)str_replace(',', '.', $s); }
function primeiro_nome($n) { return explode(' ', trim($n))[0]; }
function so_digitos($t) { return preg_replace('/\D/', '', (string)$t); }
function link_whats($tel, $texto = '') {
    $d = so_digitos($tel); if (strlen($d) < 10) return '';
    if (strlen($d) <= 11) $d = '55' . $d;
    return 'https://wa.me/' . $d . ($texto !== '' ? '?text=' . rawurlencode($texto) : '');
}
function dias_desde($d) { return $d ? (int)floor((time() - strtotime($d)) / 86400) : 0; }

// ---------- Financeiro ----------
/** Totais de uma venda. Receita da agência por item = (valor ao cliente − custo, se o cliente paga à agência) + comissão. */
function resumo_venda($vid) {
    $r = um("SELECT COALESCE(SUM(valor_cliente),0) total_cliente,
                    COALESCE(SUM(CASE WHEN pago_direto = 0 THEN valor_cliente ELSE 0 END),0) a_receber_cliente,
                    COALESCE(SUM(CASE WHEN pago_direto = 0 THEN custo ELSE 0 END),0) a_pagar,
                    COALESCE(SUM(CASE WHEN pago_direto = 0 AND pago_fornecedor_em IS NOT NULL THEN custo ELSE 0 END),0) pago_fornecedores,
                    COALESCE(SUM(comissao),0) comissoes,
                    COALESCE(SUM(CASE WHEN comissao_recebida_em IS NOT NULL THEN comissao ELSE 0 END),0) comissoes_recebidas,
                    COALESCE(SUM(CASE WHEN pago_direto = 0 THEN valor_cliente - custo ELSE 0 END) + SUM(comissao),0) receita
             FROM venda_itens WHERE venda_id = ?", [$vid]);
    $p = um("SELECT COALESCE(SUM(valor),0) parcelado, COALESCE(SUM(CASE WHEN pago_em IS NOT NULL THEN valor ELSE 0 END),0) recebido,
                    MIN(CASE WHEN pago_em IS NULL THEN vencimento END) proxima FROM venda_parcelas WHERE venda_id = ?", [$vid]);
    $r += $p;
    $r['margem'] = $r['total_cliente'] > 0 ? $r['receita'] / $r['total_cliente'] : null;
    return array_map(fn($x) => is_numeric($x) ? (float)$x : $x, $r);
}
/** Trilha visual das etapas do funil (stepper) para o topo da ficha. */
const JORNADA = ['novo', 'conversa', 'roteiro', 'proposta', 'ajustes', 'aprovada', 'fechado', 'viagem', 'pos'];
function trilha_etapas($l) {
    if ($l['etapa'] === 'perdido') {
        $mot = $l['motivo_perda'] ? ' · motivo: ' . h($l['motivo_perda']) : '';
        return '<div class="trilha-perdido"><span class="tp-ic">✕</span><div><b>Lead perdido</b><span class="mudo">' . $mot . '</span></div></div>';
    }
    $atual = array_search($l['etapa'], JORNADA, true);
    if ($atual === false) return '';
    $passos = '';
    foreach (JORNADA as $i => $k) {
        $cls = $i < $atual ? 'feito' : ($i === $atual ? 'atual' : '');
        $passos .= '<div class="passo ' . $cls . '">' . h(ETAPAS[$k]) . '</div>';
    }
    $n = count(JORNADA);
    $bolinhas = '';
    foreach (JORNADA as $i => $k) $bolinhas .= '<i class="' . ($i <= $atual ? 'on' : '') . '"></i>';
    return '<div class="trilha" role="img" aria-label="Etapa ' . ($atual + 1) . ' de ' . $n . ': ' . h(ETAPAS[$l['etapa']]) . '">' . $passos . '</div>'
         . '<div class="trilha-mini">Etapa ' . ($atual + 1) . ' de ' . $n . '</div>'
         . '<div class="trilha-mob"><div class="tm-top"><span class="tm-at">' . h(ETAPAS[$l['etapa']]) . '</span><span class="tm-n">' . ($atual + 1) . ' de ' . $n . '</span></div><div class="tm-dots">' . $bolinhas . '</div></div>';
}

function resumo_opcao($oid) {
    $o = um('SELECT moeda, taxa_cambio, markup FROM proposta_opcoes WHERE id = ?', [$oid]);
    $itens = um('SELECT COUNT(*) itens, COALESCE(SUM(valor_cliente),0) soma FROM proposta_itens WHERE opcao_id = ?', [$oid]);
    $moeda = $o['moeda'] ?? 'BRL'; $taxa = (float)($o['taxa_cambio'] ?: cambio_sugestao($moeda) ?: 1);
    $markup = (float)($o['markup'] ?? 0);
    $soma = (float)$itens['soma'];
    $total = $soma + $markup;                       // total ao cliente, na moeda
    return [
        'itens' => (int)$itens['itens'], 'moeda' => $moeda, 'taxa' => $taxa, 'markup' => $markup,
        'soma_itens' => $soma, 'total' => $total,
        'total_brl' => $total * $taxa, 'markup_brl' => $markup * $taxa,
        // receita "comercial" prevista = markup (o detalhamento de custos/comissão vem no fechamento)
        'receita' => $markup, 'receita_brl' => $markup * $taxa,
        'margem' => $total > 0 ? $markup / $total : null,
    ];
}
function pasta_roteiros() { $p = dirname(crm_caminho_banco()) . '/roteiros'; if (!is_dir($p)) mkdir($p, 0750, true); return $p; }
/** Copia os itens da opção aprovada para a venda (só se a venda ainda não tiver lançamentos). */
function proposta_para_venda($lead, $oid) {
    $venda = venda_do_lead($lead);
    if (valor('SELECT COUNT(*) FROM venda_itens WHERE venda_id = ?', [$venda['id']])) return [$venda, false];
    $o = um('SELECT moeda, taxa_cambio, markup FROM proposta_opcoes WHERE id = ?', [$oid]);
    $moeda = $o['moeda'] ?? 'BRL'; $taxa = (float)($o['taxa_cambio'] ?: cambio_sugestao($moeda) ?: 1);
    foreach (todos('SELECT * FROM proposta_itens WHERE opcao_id = ? ORDER BY id', [$oid]) as $i) {
        // valor ao cliente em REAL, pela taxa acordada (compromisso com o cliente); custo/comissão serão detalhados no fechamento
        q("INSERT INTO venda_itens (venda_id, fornecedor, servico, detalhes, pago_direto, valor_cliente, custo, comissao, custo_moeda, custo_taxa) VALUES (?,'',?,?,?,?,?,?,?,?)",
          [$venda['id'], $i['servico'], $i['detalhes'], 0, round((float)$i['valor_cliente'] * $taxa, 2), 0, 0, 'BRL', 1]);
    }
    // markup do pacote entra como um item de receita (sem custo), em real
    if ((float)$o['markup'] > 0)
        q("INSERT INTO venda_itens (venda_id, fornecedor, servico, detalhes, pago_direto, valor_cliente, custo, comissao, custo_moeda, custo_taxa) VALUES (?,'',?,?,?,?,?,?,?,?)",
          [$venda['id'], 'Markup do pacote', 'Margem da agência sobre o pacote', 1, round((float)$o['markup'] * $taxa, 2), 0, round((float)$o['markup'] * $taxa, 2), 'BRL', 1]);
    return [$venda, true];
}
function venda_do_lead($lead) {
    $v = um('SELECT * FROM vendas WHERE lead_id = ?', [$lead['id']]);
    if ($v) return $v;
    q('INSERT INTO vendas (lead_id, cliente_id, descricao, data_venda, criado_em, atualizado_em) VALUES (?,?,?,?,?,?)',
      [$lead['id'], $lead['cliente_id'], $lead['destino'] ?: 'Viagem', hoje(), agora(), agora()]);
    return um('SELECT * FROM vendas WHERE id = ?', [db()->lastInsertId()]);
}
function pct($v) { return $v === null ? '—' : number_format($v * 100, 1, ',', '.') . '%'; }

// ---------- Termômetro do lead (cálculo automático + ajuste manual) ----------
function nivel_por_nota($n) { return $n >= 70 ? 'quente' : ($n >= 40 ? 'morno' : 'frio'); }
/** Devolve ['nota' => 0-100, 'auto' => nível calculado, 'nivel' => nível final, 'manual' => bool, 'motivos' => [[+/-pontos, texto]]] */
function termometro($l) {
    if (!in_array($l['etapa'], ETAPAS_ABERTAS, true)) return null;
    $m = []; $n = 50;
    $add = function ($p, $t) use (&$n, &$m) { $n += $p; $m[] = [$p, $t]; };
    if (in_array($l['origem'], ['Indicação', 'Cliente recorrente'], true)) $add(15, $l['origem'] === 'Indicação' ? 'Veio por indicação' : 'Já é cliente');
    elseif ($l['origem'] === 'Google Ads' || $l['origem'] === 'Busca Google') $add(5, 'Procurou ativamente no Google');
    $per = mb_strtolower(trim((string)$l['periodo']));
    if ($per !== '' && !preg_match('/n[aã]o sei|indefinid|a definir/u', $per)) $add(5, 'Já sabe quando quer viajar'); else $add(-5, 'Período da viagem indefinido');
    if (trim((string)$l['orcamento']) !== '') $add(5, 'Informou o orçamento');
    if ($l['data_embarque'] && $l['data_embarque'] >= hoje() && $l['data_embarque'] <= date('Y-m-d', strtotime('+120 days'))) $add(10, 'Viagem nos próximos 4 meses');
    if ($l['etapa'] === 'ajustes') $add(10, 'Está ajustando a proposta');
    if ($l['etapa'] === 'aprovada') $add(25, 'Cliente já aprovou a proposta');
    $ult = valor("SELECT MAX(criado_em) FROM atividades WHERE lead_id = ? AND usuario_id IS NOT NULL AND tipo IN ('ligacao','whatsapp','email','reuniao','proposta')", [$l['id']]);
    $dias = dias_desde($ult ?: $l['criado_em']);
    if ($ult && $dias <= 3) $add(10, 'Contato nos últimos 3 dias');
    elseif ($dias > 14) $add(-20, "Sem contato há $dias dias");
    elseif ($dias > 7) $add(-10, "Sem contato há $dias dias");
    $ac = um('SELECT COUNT(*) total, SUM(a.acessado_em >= ?) recentes, MAX(a.acessado_em) ultimo FROM roteiro_acessos a JOIN propostas p ON p.id = a.proposta_id WHERE p.lead_id = ?', [date('Y-m-d H:i:s', strtotime('-3 days')), $l['id']]);
    if ((int)$ac['recentes'] > 0) $add(15, 'Abriu o roteiro nos últimos 3 dias (' . (int)$ac['recentes'] . ($ac['recentes'] == 1 ? ' vez' : ' vezes') . ')');
    if ((int)$ac['total'] >= 3) $add(5, 'Abriu o roteiro ' . (int)$ac['total'] . ' vezes no total');
    $pr = um("SELECT MAX(versao) maxv, SUM(status = 'recusada') recus FROM propostas WHERE lead_id = ?", [$l['id']]);
    if ((int)$pr['maxv'] >= 2) $add(5, 'Pediu nova versão da proposta');
    if ((int)$pr['recus'] > 0) $add(-5, 'Já recusou uma proposta');
    $env = um("SELECT enviada_em, validade FROM propostas WHERE lead_id = ? AND status = 'enviada' ORDER BY versao DESC LIMIT 1", [$l['id']]);
    if ($env) {
        if ($env['validade'] && $env['validade'] < hoje()) $add(-10, 'Cotação vencida');
        if (dias_desde($env['enviada_em']) > 7 && (!$ult || $ult < $env['enviada_em']) && !(int)$ac['recentes']) $add(-10, 'Proposta sem resposta há mais de 7 dias');
    }
    if (dias_desde($l['etapa_desde']) > 21) $add(-10, 'Parado na mesma etapa há mais de 3 semanas');
    $n = max(0, min(100, $n)); $auto = nivel_por_nota($n);
    $manual = $l['temp_manual'] && isset(NIVEIS_TEMP[$l['temp_manual']]) && dias_desde($l['temp_manual_em']) <= 14;
    return ['nota' => $n, 'auto' => $auto, 'nivel' => $manual ? $l['temp_manual'] : $auto, 'manual' => $manual, 'motivos' => $m, 'ultimo_contato' => $ult, 'ultimo_acesso' => $ac['ultimo']];
}
function selo_temp($t, $comNota = true) {
    if (!$t) return '';
    [$ico, $rot] = NIVEIS_TEMP[$t['nivel']];
    $cor = ['quente' => '#C2410C', 'morno' => '#8A5A00', 'frio' => '#3D5A8A'][$t['nivel']];
    return '<span title="' . h($rot . ($t['manual'] ? ' (ajustado pela equipe)' : '')) . '" style="font-size:12.5px;font-weight:700;color:' . $cor . '">' . $ico . ' ' . $rot . ($comNota ? ' · ' . $t['nota'] : '') . ($t['manual'] ? ' ✎' : '') . '</span>';
}

// ---------- Entrada de dados vindos do site ----------
function limite_formulario($chave, $max = 5, $janela = 3600) {
    q('DELETE FROM tentativas WHERE chave = ? AND t < ?', [$chave, time() - $janela]);
    if (valor('SELECT COUNT(*) FROM tentativas WHERE chave = ?', [$chave]) >= $max) return false;
    q('INSERT INTO tentativas (chave, t) VALUES (?, ?)', [$chave, time()]);
    return true;
}
function crm_achar_cliente($email, $tel) {
    if ($email) { $c = valor('SELECT id FROM clientes WHERE email = ? COLLATE NOCASE', [$email]); if ($c) return (int)$c; }
    $dig = so_digitos($tel);
    if (strlen($dig) >= 8) {
        $c = valor("SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,' ',''),'(',''),')',''),'-',''),'+','') LIKE ?", ['%' . substr($dig, -9)]);
        if ($c) return (int)$c;
    }
    return null;
}
/** Grava um pedido de proposta do site como lead (cria ou reaproveita o cliente). */
function crm_registrar_proposta(array $d, $canal, $via) {
    $pdo = db(); $pdo->beginTransaction();
    $cid = crm_achar_cliente($d['email'], $d['whats']);
    if (!$cid) {
        q('INSERT INTO clientes (nome, email, telefone, origem, criado_em, atualizado_em) VALUES (?,?,?,?,?,?)',
          [$d['nome'], $d['email'], $d['whats'], $canal, agora(), agora()]);
        $cid = (int)$pdo->lastInsertId();
    } else {
        q("UPDATE clientes SET email = COALESCE(NULLIF(email,''), ?), telefone = COALESCE(NULLIF(telefone,''), ?), atualizado_em = ? WHERE id = ?", [$d['email'], $d['whats'], agora(), $cid]);
    }
    $det = array_filter([
        $d['flexibilidade'] ? 'Datas: ' . $d['flexibilidade'] : '', $d['origem'] ? 'Saindo de: ' . $d['origem'] : '',
        $d['estilos'] ? 'Não pode faltar: ' . $d['estilos'] : '', $d['preferencia'] ? 'Prefere retorno por: ' . $d['preferencia'] : '',
        $d['mensagem'] ? 'Observações: ' . $d['mensagem'] : '',
    ]);
    q('INSERT INTO leads (cliente_id, destino, periodo, duracao, adultos, criancas, orcamento, ocasiao, detalhes, origem, gclid, etapa, etapa_desde, criado_em, atualizado_em)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$cid, $d['destino'], $d['periodo'], $d['duracao'], (int)$d['adultos'] ?: null, $d['criancas'], $d['orcamento'], $d['ocasiao'],
       implode("\n", $det), $canal, $d['gclid'] ?? null, 'novo', agora(), agora(), agora()]);
    $lid = (int)$pdo->lastInsertId();
    $como = $via === 'whatsapp' ? 'O cliente escolheu enviar pelo WhatsApp: confira a conversa.' : 'Enviado por e-mail pelo site.';
    q('INSERT INTO atividades (lead_id, cliente_id, tipo, texto, criado_em) VALUES (?,?,?,?,?)', [$lid, $cid, 'nota', "Pedido de proposta recebido pelo site. $como", agora()]);
    q('INSERT INTO tarefas (lead_id, cliente_id, titulo, vence_em, criado_em) VALUES (?,?,?,?,?)', [$lid, $cid, 'Fazer o primeiro contato', hoje(), agora()]);
    $pdo->commit();
    return $lid;
}
/** Grava elogio, dúvida ou reclamação vindo do "Fale com a gente". */
function crm_registrar_mensagem($tipo, $nome, $contato, $viagem, $texto, $autoriza) {
    $email = filter_var($contato, FILTER_VALIDATE_EMAIL) ? $contato : '';
    $tel = $email ? '' : $contato;
    $cid = crm_achar_cliente($email, $tel);
    q('INSERT INTO mensagens (tipo, nome, contato, email, telefone, viagem, texto, autoriza_publicar, cliente_id, criado_em) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$tipo, $nome, $contato, $email, $tel, $viagem, $texto, $autoriza ? 1 : 0, $cid, agora()]);
    $mid = (int)db()->lastInsertId();
    if ($cid) q('INSERT INTO atividades (cliente_id, tipo, texto, criado_em) VALUES (?,?,?,?)', [$cid, 'nota', TIPOS_MENSAGEM[$tipo] . ' recebido pelo site: ' . mb_substr($texto, 0, 300), agora()]);
    if ($tipo === 'reclamacao') q('INSERT INTO tarefas (cliente_id, titulo, vence_em, criado_em) VALUES (?,?,?,?)', [$cid, 'URGENTE: responder reclamação de ' . $nome, hoje(), agora()]);
    return $mid;
}
/** Atualiza a cópia pública (depoimentos.json) com os elogios publicados. */
function crm_publicar_depoimentos() {
    $pub = todos("SELECT COALESCE(NULLIF(nome_publico,''), nome) nome, COALESCE(viagem,'') viagem, COALESCE(NULLIF(texto_publico,''), texto) texto, publicado_em data
                  FROM mensagens WHERE tipo = 'elogio' AND status = 'publicado' ORDER BY publicado_em DESC");
    $tmp = ARQ_DEPOIMENTOS . '.tmp';
    file_put_contents($tmp, json_encode($pub, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    rename($tmp, ARQ_DEPOIMENTOS);
}

// ---------- Mensagens-modelo de WhatsApp ----------
function modelos_whats($cli, $lead = null) {
    $n = primeiro_nome($cli['nome']);
    $dest = $lead && $lead['destino'] ? $lead['destino'] : 'sua viagem';
    return [
        'Primeiro contato' => "Olá, $n! Aqui é a Vanessa, da Vanessa Viagens e Turismo. Recebi seu pedido de roteiro para $dest e vou adorar desenhar essa viagem para vocês. Podemos conversar um pouquinho sobre o que você imagina?",
        'Acompanhar proposta' => "Olá, $n! Tudo bem? Conseguiu ver com calma a proposta de $dest? Fico à disposição para ajustar o que for preciso para deixar a viagem do jeito de vocês.",
        'Boa viagem' => "$n, está chegando o grande dia! Desejo uma viagem maravilhosa. Qualquer coisa durante a viagem, é só me chamar por aqui. Eu cuido de você e da sua família. Apenas relaxe e curta o seu momento.",
        'Pós-viagem' => "Bem-vindos de volta, $n! Como foi a viagem? Se quiser contar como foi, vou amar ler: https://vanessaviagenseturismo.com.br/#contato",
    ];
}
