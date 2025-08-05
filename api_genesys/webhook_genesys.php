<?php
/**
 * Webhook para API Genesys Finance
 * 
 * Este script recebe notificações de mudança de status dos pagamentos
 * da API Genesys Finance e atualiza os registros locais
 */

// Incluir configurações
require_once('config_genesys.php');

// Headers para resposta
header('Content-Type: application/json');



// Recebe os dados do webhook
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Caminhos dos arquivos de log
$logFile = '../logs/depositos_genesys.txt';
$webhookLogFile = '../logs/webhook_genesys.log';

// Log da requisição de webhook para debug
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'payload' => $data,
    'headers' => getallheaders(),
    'raw_input' => $input
];

// Criar diretório de logs se não existir
$log_dir = dirname($webhookLogFile);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Salva o log do webhook recebido
file_put_contents($webhookLogFile, json_encode($logData) . "\n", FILE_APPEND);

// Verificar se os dados necessários estão presentes
// Baseado na documentação da API Genesys
if (isset($data['id']) && isset($data['status'])) {
    $transactionId = $data['id'];
    $status = $data['status'];
    $externalId = $data['external_id'] ?? null;
    $totalAmount = $data['total_amount'] ?? null;
    $paymentMethod = $data['payment_method'] ?? 'PIX';
    
    // Mapear status da API Genesys para status interno
    $statusMap = [
        'AUTHORIZED' => 'APPROVED',
        'PENDING' => 'PENDING',
        'FAILED' => 'FAILED',
        'CHARGEBACK' => 'CHARGEBACK',
        'IN_DISPUTE' => 'IN_DISPUTE'
    ];
    
    $internalStatus = isset($statusMap[$status]) ? $statusMap[$status] : $status;
    
    // Verificar se o arquivo de depósitos existe
    if (!file_exists($logFile)) {
        file_put_contents($logFile, "");
    }
    
    // Ler o conteúdo do arquivo
    $fileContent = file_get_contents($logFile);
    
    // Extrair objetos JSON do arquivo
    preg_match_all('/\{[^{}]*\}/s', $fileContent, $matches);
    $entries = [];
    $updated = false;
    $payment_data = null;
    
    if (isset($matches[0]) && is_array($matches[0])) {
        foreach ($matches[0] as $jsonString) {
            $entry = json_decode($jsonString, true);
            
            // Procurar pela transação usando transaction_id ou external_id
            if ($entry && (
                (isset($entry['transaction_id']) && $entry['transaction_id'] === $transactionId) ||
                (isset($entry['external_id']) && $entry['external_id'] === $externalId)
            )) {
                // Atualizar o status
                $entry['status'] = $internalStatus;
                $entry['updated_at'] = date('Y-m-d H:i:s');
                
                // Adicionar dados adicionais do webhook se disponíveis
                if ($totalAmount !== null) {
                    $entry['valor'] = $totalAmount;
                }
                
                if ($paymentMethod !== null) {
                    $entry['metodo'] = $paymentMethod;
                }
                
                // Se o pagamento foi aprovado, adicionar timestamp
                if ($internalStatus === 'APPROVED') {
                    $entry['approved_at'] = date('Y-m-d H:i:s');
                }
                
                $payment_data = $entry;
                $updated = true;
            }
            
            $entries[] = json_encode($entry, JSON_UNESCAPED_UNICODE);
        }
    }
    
    // Escrever de volta no arquivo
    if (!empty($entries)) {
        file_put_contents($logFile, implode("\n", $entries) . "\n");
    }
    
    // Se encontrou e atualizou o pagamento
    if ($updated && $payment_data) {

        
        // Resposta de sucesso
        echo json_encode([
            "status" => "success",
            "message" => "Status atualizado para " . $internalStatus,
            "transaction_id" => $transactionId,
            "external_id" => $externalId
        ]);
        
    } else if ($updated) {
        // Atualizado
        echo json_encode([
            "status" => "success",
            "message" => "Status atualizado para " . $internalStatus,
            "transaction_id" => $transactionId,
            "external_id" => $externalId
        ]);
        
    } else {
        // Transação não encontrada nos registros locais
        echo json_encode([
            "status" => "warning",
            "message" => "Transação não encontrada nos registros locais",
            "transaction_id" => $transactionId,
            "external_id" => $externalId
        ]);
    }
    
} else {
    // Dados do webhook incompletos
    echo json_encode([
        "status" => "error",
        "message" => "Dados do webhook incompletos ou inválidos",
        "received_data" => $data
    ]);
}



// Responder com status 200 para confirmar recebimento
http_response_code(200);
?>