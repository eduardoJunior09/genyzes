<?php
/**
 * Configurações do Sistema Genesys Finance
 * 
 * Este arquivo contém todas as configurações necessárias para o funcionamento
 * do sistema de pagamentos integrado com a API Genesys Finance
 */

// Configurações da API Genesys Finance
define('GENESYS_API_BASE_URL', 'https://api.genesys.finance');
define('GENESYS_API_SECRET', 'sk_287f21090554a43e90fd796a10cb3c228ed51a8b755729b4eb6f2ee606d032cf6fcb41f676c5b43f5cc084329cf24dcb23f906a1e3a712df60b0dd32eb53f249');

// Configurações de Webhook
define('WEBHOOK_BASE_URL', 'https://api.xtracky.com');
define('WEBHOOK_PATH', '/api/integrations/genesys_api');



// Configurações de Arquivos e Diretórios
define('LOG_DIR', '../logs');
define('LOGS_DIR', LOG_DIR); // Compatibilidade
define('DEPOSITOS_FILE', LOG_DIR . '/depositos_genesys.txt');

// Configurações de Log
define('LOG_REQUESTS', LOG_DIR . '/genesys_requests.log');
define('LOG_WEBHOOKS', LOG_DIR . '/genesys_webhooks.log');
define('LOG_CONSULTAS', LOG_DIR . '/genesys_consultas.log');


// Configurações de Timeout
define('API_TIMEOUT', 30); // segundos
define('STATUS_CHECK_INTERVAL', 5); // segundos para verificação automática

// Configurações de Comissão
define('DEFAULT_COMMISSION_RATE', 0.05); // 5% de comissão padrão

// Configurações de Webhook
define('WEBHOOK_TIMEOUT', 30); // segundos

// Mapeamento de Status
$GENESYS_STATUS_MAP = [
    'AUTHORIZED' => 'APPROVED',
    'PENDING' => 'PENDING',
    'FAILED' => 'FAILED',
    'CHARGEBACK' => 'CHARGEBACK',
    'IN_DISPUTE' => 'IN_DISPUTE'
];



// Configurações de Ambiente
define('ENVIRONMENT', 'production'); // 'development' ou 'production'
define('DEBUG_MODE', ENVIRONMENT === 'development');

// Configurações de Segurança
define('ALLOWED_IPS', []); // IPs permitidos para webhooks (vazio = todos)
define('WEBHOOK_SECRET', ''); // Secret para validação de webhooks (opcional)

// Configurações de Timezone
date_default_timezone_set('America/Sao_Paulo');

/**
 * Função para obter configuração da API Genesys
 */
function getGenesysConfig() {
    return [
        'base_url' => GENESYS_API_BASE_URL,
        'api_secret' => GENESYS_API_SECRET,
        'timeout' => API_TIMEOUT
    ];
}



/**
 * Função para obter mapeamento de status
 */
function getStatusMap($type = 'genesys') {
    global $GENESYS_STATUS_MAP;
    
    switch ($type) {
        case 'genesys':
            return $GENESYS_STATUS_MAP;
        default:
            return [];
    }
}



/**
 * Função para criar diretórios necessários
 */
function createRequiredDirectories() {
    $directories = [
        LOGS_DIR
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * Função para validar configurações
 */
function validateConfig() {
    $errors = [];
    
    // Verificar API Secret da Genesys
    if (GENESYS_API_SECRET === 'SEU_API_SECRET_AQUI' || empty(GENESYS_API_SECRET)) {
        $errors[] = 'API Secret da Genesys Finance não configurado';
    }
    

    
    // Verificar se diretórios podem ser criados
    if (!is_writable(dirname(LOGS_DIR))) {
        $errors[] = 'Diretório de logs não pode ser criado ou não tem permissão de escrita';
    }
    
    return $errors;
}

/**
 * Função para log de debug
 */
function debugLog($message, $data = null) {
    if (!DEBUG_MODE) {
        return;
    }
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message
    ];
    
    if ($data !== null) {
        $log_entry['data'] = $data;
    }
    
    $log_file = LOGS_DIR . '/debug.log';
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Função para verificar se IP está permitido
 */
function isIpAllowed($ip) {
    $allowed_ips = ALLOWED_IPS;
    
    // Se não há restrição de IP, permitir todos
    if (empty($allowed_ips)) {
        return true;
    }
    
    return in_array($ip, $allowed_ips);
}

/**
 * Função para obter IP do cliente
 */
function getClientIp() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Se há múltiplos IPs, pegar o primeiro
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            return $ip;
        }
    }
    
    return '0.0.0.0';
}

/**
 * Função para sanitizar dados de entrada
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Função para validar CPF
 */
function isValidCpf($cpf) {
    // Remove caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) !== 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    
    // Validação dos dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

/**
 * Função para validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Função para formatar valor monetário
 */
function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Função para converter valor brasileiro para float
 */
function parseValueFromBrazilianFormat($value) {
    // Remove espaços e caracteres não numéricos exceto vírgula e ponto
    $value = preg_replace('/[^0-9,.]/', '', $value);
    
    // Se contém vírgula, assumir formato brasileiro (123.456,78)
    if (strpos($value, ',') !== false) {
        $value = str_replace('.', '', $value); // Remove pontos de milhares
        $value = str_replace(',', '.', $value); // Converte vírgula para ponto decimal
    }
    
    return floatval($value);
}

// Criar diretórios necessários na inicialização
createRequiredDirectories();

// Verificar configurações em modo debug
if (DEBUG_MODE) {
    $config_errors = validateConfig();
    if (!empty($config_errors)) {
        debugLog('Erros de configuração encontrados', $config_errors);
    }
}
?>