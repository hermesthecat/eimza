<?php
require_once 'config.php';
require_once 'includes/logger.php';

try {
    // Get POST data
    $data = file_get_contents('php://input');
    $response = json_decode($data, true);

    if (!$response) {
        throw new Exception('Geçersiz imza yanıtı');
    }

    // Log the signature response
    Logger::getInstance()->info('Signature response received: ' . $data);

    // Validate signature response
    if (!isset($response['resources']) || empty($response['resources'])) {
        throw new Exception('İmza yanıtında kaynak bulunamadı');
    }

    $resource = $response['resources'][0];
    
    // Validate signature
    if (!isset($resource['signature'])) {
        throw new Exception('İmza bilgisi bulunamadı');
    }

    // Store signature details in session for verification
    session_start();
    $_SESSION['signature_info'] = [
        'certificate' => $response['certificate'] ?? null,
        'certificateName' => $response['certificateName'] ?? null,
        'certificateIssuer' => $response['certificateIssuer'] ?? null,
        'certificateSerialNumber' => $response['certificateSerialNumber'] ?? null,
        'createdAt' => $response['createdAt'] ?? null,
        'signature' => $resource['signature'],
        'source' => $resource['source']
    ];

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'İmza başarıyla alındı',
        'info' => [
            'name' => $response['certificateName'],
            'issuer' => $response['certificateIssuer'],
            'date' => $response['createdAt']
        ]
    ]);

} catch (Exception $e) {
    Logger::getInstance()->error('Signature verification error: ' . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}