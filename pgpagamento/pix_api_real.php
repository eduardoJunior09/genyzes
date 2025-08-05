<?php
/**
 * API de integração PIX para o frontend
 * 
 * Este script serve como ponte entre o frontend e a API Genesys Finance
 * Permite gerar pagamentos PIX e verificar status
 */

// Headers para permitir requisições AJAX
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir API Genesys
require_once('../api_genesys/config_genesys.php');

// Determinar a ação com base no método e parâmetros
$action = $_GET['action'] ?? 'generate';

// Verificar status de pagamento
if ($action === 'check_status') {
    $transaction_id = $_GET['transaction_id'] ?? null;
    
    if (!$transaction_id) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ID da transação não fornecido'
        ]);
        exit;
    }
    
    // Chamar a API de verificação de pagamento
    $api_url = "../api_genesys/verificar_pagamento_genesys.php?transaction_id={$transaction_id}";
    $response = file_get_contents($api_url);
    
    if ($response === false) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao verificar status do pagamento'
        ]);
        exit;
    }
    
    $data = json_decode($response, true);
    
    // Mapear status para o formato esperado pelo frontend
    if ($data && isset($data['status'])) {
        // Converter APPROVED para paid para compatibilidade com o frontend
        if ($data['status'] === 'APPROVED') {
            $data['status'] = 'paid';
        }
        
        echo json_encode($data);
    } else {
        echo json_encode([
            'status' => 'pending',
            'message' => 'Status desconhecido'
        ]);
    }
    
    exit;
}

// Gerar novo pagamento PIX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Receber dados do cliente do POST
    $input = file_get_contents('php://input');
    $postData = json_decode($input, true);
    
    // Obter valor do pagamento do POST ou usar valor padrão
    $value = $postData['value'] ?? 37.90; // Usar valor do POST ou padrão
    
    // Preparar dados do cliente
    $cliente_nome = $postData['name'] ?? 'Cliente';
    $cliente_documento = $postData['cpf'] ?? '11144477735'; // CPF válido para teste
    $cliente_email = $postData['email'] ?? 'cliente@exemplo.com';
    $cliente_telefone = $postData['phone'] ?? '11999999999';
    
    // Simular dados POST para gerar_genesys.php
    $_POST['value'] = $value;
    $_POST['nome'] = $cliente_nome;
    $_POST['documento'] = $cliente_documento;
    $_POST['email'] = $cliente_email;
    $_POST['telefone'] = $cliente_telefone;
    
    // Capturar output do gerar_genesys.php
    ob_start();
    include '../api_genesys/gerar_genesys.php';
    $response = ob_get_clean();
    
    if (empty($response)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao gerar pagamento PIX',
            'details' => 'Resposta vazia da API'
        ]);
        exit;
    }
    
    $responseData = json_decode($response, true);
    
    // Verificar se a geração foi bem-sucedida
    if ($responseData && isset($responseData['status']) && $responseData['status'] === 'success') {
        // Retornar dados para o frontend no formato esperado
        echo json_encode([
            'status' => 'success',
            'transactionId' => $responseData['transaction_id'],
            'transaction_id' => $responseData['transaction_id'], // Manter compatibilidade
            'pixKey' => $responseData['pix_payload'],
            'pix_payload' => $responseData['pix_payload'], // Manter compatibilidade
            'qrCodeUrl' => $responseData['qr_code_data'],
            'qr_code_data' => $responseData['qr_code_data'], // Manter compatibilidade
            'customerName' => $cliente_nome,
            'customerCpf' => $cliente_documento,
            'amount' => $value * 100, // Converter para centavos como esperado pelo frontend
            'expirationDate' => date('c', strtotime('+1 hour')) // Data de expiração em 1 hora
        ]);
    } else {
        // Retornar erro
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao gerar pagamento PIX',
            'details' => $responseData['message'] ?? 'Erro desconhecido'
        ]);
    }
    
    exit;
}

// Se chegou aqui, método não suportado
echo json_encode([
    'status' => 'error',
    'message' => 'Método não suportado'
]);
?>