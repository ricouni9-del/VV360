<?php
require __DIR__ . '/config.php';
date_default_timezone_set('America/Sao_Paulo');

// Compatibilidade caso a extensão mbstring não esteja ativa no servidor
if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return preg_match_all('/./us', (string)$s); }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($s) { return strtolower($s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $ini, $tam = null) {
        preg_match_all('/./us', (string)$s, $m);
        return implode('', array_slice($m[0], $ini, $tam));
    }
}

// Cópia pública dos elogios aprovados (lida pela página inicial do site)
define('ARQ_DEPOIMENTOS', dirname(__DIR__) . '/depoimentos.json');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
