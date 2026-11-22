<?php
// Recebe elogios, dúvidas e reclamações do formulário "Fale com a gente".
require dirname(__DIR__) . '/crm/inc/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder($ok, $msg, $cod = 200) {
    http_response_code($cod);
    echo json_encode(['ok' => $ok, 'mensagem' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método não permitido.', 405);

// Anti-spam: campo invisível e tempo mínimo de preenchimento
if (!empty($_POST['site'])) responder(true, 'Recebido.');
$inicio = (int)($_POST['t'] ?? 0);
if ($inicio && (time() - $inicio) < 3) responder(false, 'Envio muito rápido. Tente novamente.', 429);

$tipos = ['elogio', 'duvida', 'reclamacao'];
$tipo  = $_POST['tipo'] ?? '';
$nome  = trim(mb_substr($_POST['nome'] ?? '', 0, 80));
$cont  = trim(mb_substr($_POST['contato'] ?? '', 0, 120));
$viag  = trim(mb_substr($_POST['viagem'] ?? '', 0, 120));
$msg   = trim(mb_substr($_POST['mensagem'] ?? '', 0, 3000));
$autor = !empty($_POST['autoriza']) && $tipo === 'elogio';

if (!in_array($tipo, $tipos, true)) responder(false, 'Escolha o tipo de mensagem.', 422);
if (mb_strlen($nome) < 2)           responder(false, 'Informe seu nome.', 422);
if (mb_strlen($cont) < 6)           responder(false, 'Informe um e-mail ou WhatsApp para retorno.', 422);
if (mb_strlen($msg) < 5)            responder(false, 'Escreva sua mensagem.', 422);

// Limite: até 5 mensagens por hora do mesmo endereço
if (!limite_formulario('fale:' . ip_hash())) responder(false, 'Muitas mensagens em pouco tempo. Tente mais tarde ou fale pelo WhatsApp.', 429);

try { crm_registrar_mensagem($tipo, $nome, $cont, $viag, $msg, $autor); }
catch (Throwable $e) { responder(false, 'Não conseguimos registrar sua mensagem agora. Fale com a Vanessa pelo WhatsApp.', 500); }

// Aviso por e-mail (se o servidor permitir envio)
$rot = ['elogio' => 'Elogio', 'duvida' => 'Dúvida', 'reclamacao' => 'Reclamação'][$tipo];
$assunto = '=?UTF-8?B?' . base64_encode("[Site] $rot de $nome") . '?=';
$corpo = "Nova mensagem pelo site\n\nTipo: $rot\nNome: $nome\nContato: $cont\n"
       . ($viag ? "Viagem: $viag\n" : '')
       . ($tipo === 'elogio' ? 'Autoriza publicar: ' . ($autor ? 'sim' : 'não') . "\n" : '')
       . "\n$msg\n\nResponda e gerencie no CRM: https://vanessaviagenseturismo.com.br/crm/?p=mensagens\n";
@mail(EMAIL_AVISO, $assunto, $corpo,
      "From: Site Vanessa Viagens e Turismo <" . EMAIL_REMETENTE . ">\r\nContent-Type: text/plain; charset=UTF-8");

$retorno = [
    'elogio'     => 'Obrigada pelo carinho! Sua mensagem chegou para a Vanessa.',
    'duvida'     => 'Recebemos sua dúvida e vamos responder pelo contato que você deixou.',
    'reclamacao' => 'Sentimos muito pelo ocorrido. A Vanessa vai entrar em contato pessoalmente para resolver.',
][$tipo];
responder(true, $retorno);
