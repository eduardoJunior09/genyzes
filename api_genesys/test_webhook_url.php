<?php
/**
 * Teste específico para verificar o problema do webhook URL
 */

header('Content-Type: application/json');

require_once('config_genesys.php');

echo json_encode([
    'WEBHOOK_BASE_URL' => defined('WEBHOOK_BASE_URL') ? WEBHOOK_BASE_URL : 'NÃO DEFINIDO',
    'WEBHOOK_PATH' => defined('WEBHOOK_PATH') ? WEBHOOK_PATH : 'NÃO DEFINIDO',
    'webhook_url_completa' => (defined('WEBHOOK_BASE_URL') && defined('WEBHOOK_PATH')) ? WEBHOOK_BASE_URL . WEBHOOK_PATH : 'ERRO',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'NÃO DEFINIDO',
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'NÃO DEFINIDO',
    'REQUEST_SCHEME' => $_SERVER['REQUEST_SCHEME'] ?? 'NÃO DEFINIDO',
    'HTTPS' => $_SERVER['HTTPS'] ?? 'NÃO DEFINIDO',
    'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'NÃO DEFINIDO'
], JSON_PRETTY_PRINT);
?>