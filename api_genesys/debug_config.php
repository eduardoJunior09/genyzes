<?php
/**
 * Arquivo de debug para verificar configurações
 */

require_once('config_genesys.php');

echo "=== DEBUG CONFIGURAÇÕES ===\n";
echo "WEBHOOK_BASE_URL: " . (defined('WEBHOOK_BASE_URL') ? WEBHOOK_BASE_URL : 'NÃO DEFINIDO') . "\n";
echo "WEBHOOK_PATH: " . (defined('WEBHOOK_PATH') ? WEBHOOK_PATH : 'NÃO DEFINIDO') . "\n";
echo "URL COMPLETA: " . (defined('WEBHOOK_BASE_URL') && defined('WEBHOOK_PATH') ? WEBHOOK_BASE_URL . WEBHOOK_PATH : 'ERRO') . "\n";
echo "ENVIRONMENT: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'NÃO DEFINIDO') . "\n";
echo "DEBUG_MODE: " . (defined('DEBUG_MODE') ? (DEBUG_MODE ? 'TRUE' : 'FALSE') : 'NÃO DEFINIDO') . "\n";

// Verificar se há redefinições
if (function_exists('get_defined_constants')) {
    $constants = get_defined_constants(true);
    if (isset($constants['user'])) {
        echo "\n=== CONSTANTES DEFINIDAS PELO USUÁRIO ===\n";
        foreach ($constants['user'] as $name => $value) {
            if (strpos($name, 'WEBHOOK') !== false) {
                echo "$name: $value\n";
            }
        }
    }
}

echo "\n=== VARIÁVEIS DE SERVIDOR RELEVANTES ===\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NÃO DEFINIDO') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'NÃO DEFINIDO') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NÃO DEFINIDO') . "\n";
?>