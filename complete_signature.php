<?php
require_once 'config.php';
require_once 'includes/logger.php';
require_once 'includes/SignatureManager.php';

// JSON verisini al
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (!$data) {
    http_response_code(400);
    die(json_encode(['error' => 'Geçersiz JSON verisi']));
}

// Gerekli alanları kontrol et
$requiredFields = [
    'documentId',
    'certificateName',
    'certificateIssuer',
    'certificateSerialNumber',
    'signature',
    'createdAt'
];

foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        die(json_encode(['error' => "Eksik alan: $field"]));
    }
}

try {
    // İmza yöneticisini başlat
    $signatureManager = new SignatureManager($db, Logger::getInstance());

    // İmzalı PDF'i kaydet
    $signedPdfPath = 'signed/' . $data['documentId'];
    file_put_contents($signedPdfPath, base64_decode($data['signedPdf']));

    // İmza verilerini ekle
    $signatureData = [
        'certificateName' => $data['certificateName'],
        'certificateIssuer' => $data['certificateIssuer'],
        'certificateSerialNumber' => $data['certificateSerialNumber'],
        'signature' => $data['signature'],
        'createdAt' => $data['createdAt'],
        'signed_pdf_path' => $signedPdfPath
    ];

    $completed = $signatureManager->updateGroupSignature($data['documentId'], $signatureData);

    echo json_encode([
        'success' => true,
        'message' => $completed ? 'İmza süreci tamamlandı' : 'İmza eklendi, diğer imzalar bekleniyor',
        'status' => $completed ? 'completed' : 'pending'
    ]);
} catch (Exception $e) {
    Logger::getInstance()->error('İmza tamamlama hatası: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
