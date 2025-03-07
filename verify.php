<?php
require_once 'config.php';
require_once 'includes/logger.php';
require_once 'includes/SignatureManager.php';

try {
    // Initialize signature manager
    $signatureManager = new SignatureManager($db, Logger::getInstance());

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

    // Extract filename from source URL
    $sourceUrl = $resource['source'];
    $filename = basename(parse_url($sourceUrl, PHP_URL_PATH));

    // Update signature record in database
    $signatureManager->updateSignatureResult($filename, [
        'certificateName' => $response['certificateName'] ?? null,
        'certificateIssuer' => $response['certificateIssuer'] ?? null,
        'certificateSerialNumber' => $response['certificateSerialNumber'] ?? null,
        'createdAt' => $response['createdAt'] ?? date('Y-m-d H:i:s'),
        'signature' => $resource['signature']
    ]);

    // Get updated signature record
    $signatureRecord = $signatureManager->getSignatureRecord($filename);

    if (!$signatureRecord) {
        throw new Exception('İmza kaydı bulunamadı');
    }

    // Store signature details in session for verification
    session_start();
    $_SESSION['signature_info'] = [
        'id' => $signatureRecord['id'],
        'filename' => $signatureRecord['filename'],
        'original_filename' => $signatureRecord['original_filename'],
        'certificate_name' => $signatureRecord['certificate_name'],
        'certificate_issuer' => $signatureRecord['certificate_issuer'],
        'certificate_serial_number' => $signatureRecord['certificate_serial_number'],
        'signature_date' => $signatureRecord['signature_date'],
        'signature_location' => $signatureRecord['signature_location'],
        'signature_reason' => $signatureRecord['signature_reason']
    ];

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'İmza başarıyla alındı',
        'info' => [
            'name' => $signatureRecord['certificate_name'],
            'issuer' => $signatureRecord['certificate_issuer'],
            'date' => $signatureRecord['signature_date']
        ]
    ]);
} catch (Exception $e) {
    // Log error
    Logger::getInstance()->error('Signature verification error: ' . $e->getMessage());

    // Mark signature as failed if filename is available
    if (isset($signatureManager) && isset($filename)) {
        $signatureManager->markAsFailed($filename, $e->getMessage());
    }

    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
