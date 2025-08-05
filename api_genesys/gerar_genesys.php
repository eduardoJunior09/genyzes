<?php
/**
 * Sistema de Geração de PIX - API Genesys Finance
 * 
 * Este script gera pagamentos PIX utilizando a API Genesys Finance
 * Baseado na documentação: https://api.genesys.finance/integration/docs/api
 */

// Headers para permitir requisições AJAX
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir configurações
require_once('config_genesys.php');

// Usar configurações centralizadas
$api_base_url = GENESYS_API_BASE_URL;
$api_secret = GENESYS_API_SECRET;

// Função para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) return false;
    if (preg_match('/^(\d)\1{10}$/', $cpf)) return false; // CPFs com todos os dígitos iguais
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

// Verificar se o valor foi fornecido (aceita 'value' ou 'valor')
if (isset($_GET['value']) || isset($_POST['value']) || isset($_GET['valor']) || isset($_POST['valor'])) {
    // Aceitar tanto GET quanto POST, e tanto 'value' quanto 'valor'
    $valorInput = $_GET['value'] ?? $_POST['value'] ?? $_GET['valor'] ?? $_POST['valor'];
    
    // Modificar para aceitar valores com vírgula (formato brasileiro)
    $valorInput = str_replace(',', '.', $valorInput);
    $value = floatval($valorInput);
    
    // Validar valor mínimo
    if ($value < 0.01) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Valor mínimo é R$ 0,01'
        ]);
        exit;
    }
    

    
    // Informações do cliente
    $cliente_nome = $_GET['nome'] ?? $_POST['nome'] ?? 'Cliente';
    $cliente_documento = $_GET['documento'] ?? $_POST['documento'] ?? $_GET['cpf'] ?? $_POST['cpf'] ?? '11144477735'; // CPF válido padrão
    $cliente_telefone = $_GET['telefone'] ?? $_POST['telefone'] ?? '11999999999';
    $cliente_email = $_GET['email'] ?? $_POST['email'] ?? 'cliente@email.com';
    $cliente_ip = $_SERVER['REMOTE_ADDR'];
    
    // Validar e limpar CPF
    $cliente_documento = preg_replace('/[^0-9]/', '', $cliente_documento);
    if (!validarCPF($cliente_documento)) {
        $cliente_documento = '11144477735'; // CPF válido padrão se inválido
    }
    
    // Convert value to float to ensure it's a number
    $value = floatval($value);
    
    // Gerar ID externo único
    $external_id = uniqid('genesys_', true);
    
    // Construir webhook_url
    $webhook_url = WEBHOOK_BASE_URL . WEBHOOK_PATH;
    
    // Preparar payload para a API Genesys
    $data = [
        "external_id" => $external_id,
        "total_amount" => $value,
        "payment_method" => "PIX",
        "webhook_url" => $webhook_url,
        "items" => [
            [
                "id" => "item_1",
                "title" => "Pagamento PIX",
                "description" => "Pagamento via PIX",
                "price" => $value,
                "quantity" => 1,
                "is_physical" => false
            ]
        ],
        "ip" => $cliente_ip,
        "customer" => [
            "name" => $cliente_nome,
            "email" => $cliente_email,
            "phone" => $cliente_telefone,
            "document_type" => strlen($cliente_documento) == 11 ? "CPF" : "CNPJ",
            "document" => $cliente_documento
        ]
    ];
    
    $payload = json_encode($data);
    
    // Configuração do cURL para API Genesys
    $ch = curl_init($api_base_url . '/v1/transactions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'api-secret: ' . $api_secret
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log da requisição para debug
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'request' => $data,
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error
    ];
    
    $log_dir = LOG_DIR;
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_dir . '/genesys_requests.log', json_encode($log_data) . "\n", FILE_APPEND);
    
    // Verificar se a requisição foi bem-sucedida (200 OK ou 201 Created)
    if (($httpCode == 200 || $httpCode == 201) && !empty($response)) {
        $responseData = json_decode($response, true);
        
        if ($responseData && !isset($responseData['hasError']) || !$responseData['hasError']) {
            // Extrair dados da resposta
            $transactionId = $responseData['id'];
            $status = $responseData['status'];
            $pixPayload = $responseData['pix']['payload'] ?? '';
            
            // Salvar dados localmente
            date_default_timezone_set('America/Sao_Paulo');
            $dataAtual = date('Y-m-d H:i:s');
            
            $dados = [
                "transaction_id" => $transactionId,
                "external_id" => $external_id,
                "valor" => $value,
                "status" => $status,
                "data" => $dataAtual,
                "metodo" => "PIX",
                "cliente_nome" => $cliente_nome,
                "cliente_email" => $cliente_email,
                "cliente_documento" => $cliente_documento,
                "cliente_telefone" => $cliente_telefone,
                "produto_nome" => "Pagamento PIX",
                "ip" => $cliente_ip,
                "pix_payload" => $pixPayload
            ];
            
            // Salvar no arquivo de depósitos
            $arquivo = LOG_DIR . '/depositos_genesys.txt';
            file_put_contents($arquivo, json_encode($dados) . "\n", FILE_APPEND);
            

            
            // Retornar resposta de sucesso
            echo json_encode([
                'status' => 'success',
                'transaction_id' => $transactionId,
                'external_id' => $external_id,
                'valor' => $value,
                'pix_payload' => $pixPayload,
                'qr_code_data' => $pixPayload // Para gerar QR Code no frontend
            ]);
            
        } else {
            // Erro na resposta da API
            echo json_encode([
                'status' => 'error',
                'message' => 'Erro ao processar pagamento',
                'details' => $responseData ?? 'Resposta inválida da API'
            ]);
        }
    } else {
        // Erro na requisição
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro na comunicação com a API: ' . $httpCode,
            'details' => $error ? $error : ($response ?: 'Sem resposta da API')
        ]);
    }
} else {
    // Valor não fornecido
    echo json_encode([
        'status' => 'error',
        'message' => 'Valor não fornecido. Use ?value=10.50 ou ?valor=10.50 ou envie via POST'
    ]);
}
?>