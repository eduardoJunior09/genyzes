<?php
/**
 * Verificação de Pagamentos - API Genesys Finance
 * 
 * Este script verifica o status de pagamentos tanto nos registros locais
 * quanto diretamente na API Genesys Finance
 */

// Headers para permitir requisições AJAX
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir configurações
require_once('config_genesys.php');

// Configurações da API Genesys
$api_base_url = GENESYS_API_BASE_URL;
$api_secret = GENESYS_API_SECRET;



// Caminho do arquivo de registros
$arquivo = '../logs/depositos_genesys.txt';

// Receber dados da requisição
$input = file_get_contents('php://input');
$inputData = json_decode($input, true);

// Aceitar parâmetros via POST/JSON ou GET
$transaction_id = $inputData['transaction_id'] ?? $_GET['transaction_id'] ?? null;
$external_id = $inputData['external_id'] ?? $_GET['external_id'] ?? null;
$force_check = $inputData['force_check'] ?? $_GET['force_check'] ?? false;

// Verificar se pelo menos um ID foi fornecido
if (!$transaction_id && !$external_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID de transação (transaction_id ou external_id) não fornecido'
    ]);
    exit;
}

// Primeiro verificar localmente
$localStatus = verificarStatusLocal($transaction_id, $external_id, $arquivo);

// Se não encontrou localmente ou se force_check é true e status é PENDING
if ($localStatus === false || ($force_check && $localStatus['status'] === 'PENDING')) {
    $apiStatus = verificarStatusAPIGenesys($transaction_id, $external_id);
    
    // Se encontrou na API, atualizar localmente e retornar
    if ($apiStatus !== false) {
        atualizarStatusLocal($transaction_id, $external_id, $apiStatus, $arquivo);
        

        
        echo json_encode($apiStatus);
        exit;
    }
    
    // Se não encontrou na API mas tem localmente
    if ($localStatus !== false) {
        echo json_encode($localStatus);
        exit;
    }
    
    // Não encontrou em lugar nenhum
    echo json_encode([
        'status' => 'NOT_FOUND',
        'message' => 'Transação não encontrada'
    ]);
    exit;
}



// Mapear status para compatibilidade com frontend
if ($localStatus['status'] === 'APPROVED') {
    $localStatus['status'] = 'paid';
}

// Retornar status encontrado localmente
echo json_encode($localStatus);

/**
 * Verificar status no arquivo local
 */
function verificarStatusLocal($transaction_id, $external_id, $arquivo) {
    if (!file_exists($arquivo)) {
        return false;
    }
    
    $conteudo = file_get_contents($arquivo);
    preg_match_all('/\{[^{}]*\}/s', $conteudo, $matches);
    
    if (isset($matches[0]) && is_array($matches[0])) {
        foreach ($matches[0] as $jsonString) {
            $dados = json_decode($jsonString, true);
            
            if ($dados && (
                ($transaction_id && isset($dados['transaction_id']) && $dados['transaction_id'] === $transaction_id) ||
                ($external_id && isset($dados['external_id']) && $dados['external_id'] === $external_id)
            )) {
                return [
                    'status' => $dados['status'] ?? 'UNKNOWN',
                    'transaction_id' => $dados['transaction_id'] ?? null,
                    'external_id' => $dados['external_id'] ?? null,
                    'valor' => $dados['valor'] ?? null,
                    'data' => $dados['data'] ?? null,
                    'metodo' => $dados['metodo'] ?? 'PIX',
                    'pix_payload' => $dados['pix_payload'] ?? null,

                    'fonte' => 'local'
                ];
            }
        }
    }
    
    return false;
}

/**
 * Verificar status na API Genesys Finance
 */
function verificarStatusAPIGenesys($transaction_id, $external_id) {
    global $api_base_url, $api_secret;
    
    // Usar transaction_id se disponível, senão usar external_id
    $id_para_consulta = $transaction_id ?? $external_id;
    
    if (!$id_para_consulta) {
        return false;
    }
    
    // Configurar cURL para consultar transação
    $ch = curl_init($api_base_url . '/v1/transactions/' . urlencode($id_para_consulta));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-secret: ' . $api_secret
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log da consulta
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'consulta_api',
        'transaction_id' => $id_para_consulta,
        'http_code' => $httpCode,
        'response' => $response
    ];
    
    $log_dir = '../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents('../logs/genesys_consultas.log', json_encode($log_data) . "\n", FILE_APPEND);
    
    if ($httpCode == 200 && !empty($response)) {
        $data = json_decode($response, true);
        
        if ($data && isset($data['id'])) {
            // Mapear status da API para status interno
            $statusMap = [
                'AUTHORIZED' => 'APPROVED',
                'PENDING' => 'PENDING',
                'FAILED' => 'FAILED',
                'CHARGEBACK' => 'CHARGEBACK',
                'IN_DISPUTE' => 'IN_DISPUTE'
            ];
            
            $apiStatus = $data['status'] ?? 'UNKNOWN';
            $internalStatus = $statusMap[$apiStatus] ?? $apiStatus;
            
            return [
                'status' => $internalStatus,
                'transaction_id' => $data['id'],
                'external_id' => $data['external_id'] ?? null,
                'valor' => $data['amount'] ?? null,
                'data' => $data['created_at'] ?? null,
                'metodo' => $data['payment_method'] ?? 'PIX',
                'fonte' => 'api'
            ];
        }
    }
    
    return false;
}

/**
 * Atualizar status no arquivo local
 */
function atualizarStatusLocal($transaction_id, $external_id, $statusData, $arquivo) {
    if (!file_exists($arquivo)) {
        return false;
    }
    
    $conteudo = file_get_contents($arquivo);
    preg_match_all('/\{[^{}]*\}/s', $conteudo, $matches);
    $entries = [];
    $updated = false;
    
    if (isset($matches[0]) && is_array($matches[0])) {
        foreach ($matches[0] as $jsonString) {
            $dados = json_decode($jsonString, true);
            
            if ($dados && (
                ($transaction_id && isset($dados['transaction_id']) && $dados['transaction_id'] === $transaction_id) ||
                ($external_id && isset($dados['external_id']) && $dados['external_id'] === $external_id)
            )) {
                // Atualizar dados com informações da API
                $dados['status'] = $statusData['status'];
                if (isset($statusData['valor'])) $dados['valor'] = $statusData['valor'];
                if (isset($statusData['metodo'])) $dados['metodo'] = $statusData['metodo'];
                
                $dados['updated_at'] = date('Y-m-d H:i:s');
                
                if ($statusData['status'] === 'APPROVED') {
                    $dados['approved_at'] = date('Y-m-d H:i:s');
                }
                
                $updated = true;
            }
            
            $entries[] = json_encode($dados, JSON_UNESCAPED_UNICODE);
        }
    }
    
    if ($updated) {
        file_put_contents($arquivo, implode("\n", $entries) . "\n");
        return true;
    }
    
    return false;
}

/**
 * Obter dados completos de um pagamento
 */
function obterDadosPagamento($transaction_id, $external_id, $arquivo) {
    if (!file_exists($arquivo)) {
        return false;
    }
    
    $conteudo = file_get_contents($arquivo);
    preg_match_all('/\{[^{}]*\}/s', $conteudo, $matches);
    
    if (isset($matches[0]) && is_array($matches[0])) {
        foreach ($matches[0] as $jsonString) {
            $dados = json_decode($jsonString, true);
            
            if ($dados && (
                ($transaction_id && isset($dados['transaction_id']) && $dados['transaction_id'] === $transaction_id) ||
                ($external_id && isset($dados['external_id']) && $dados['external_id'] === $external_id)
            )) {
                return $dados;
            }
        }
    }
    
    return false;
}


?>