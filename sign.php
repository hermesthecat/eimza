<?php
require_once 'config.php';
require_once 'includes/logger.php';

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate file upload
    if (!isset($_FILES['pdfFile']) || $_FILES['pdfFile']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = isset($_FILES['pdfFile']) ? 
            'Dosya yükleme hatası: ' . $_FILES['pdfFile']['error'] : 
            'Dosya yüklenemedi';
        throw new Exception($errorMessage);
    }

    $file = $_FILES['pdfFile'];

    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('Dosya boyutu çok büyük. Maksimum boyut: 10MB');
    }

    // Validate file type
    if ($file['type'] !== 'application/pdf' || !isAllowedExtension($file['name'])) {
        throw new Exception('Sadece PDF dosyaları kabul edilmektedir.');
    }

    // Generate safe filename
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeFilename = sanitizeFilename($originalName);
    $filename = $safeFilename . '_' . uniqid() . '.pdf';
    $uploadPath = UPLOAD_DIR . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Dosya kaydedilemedi.');
    }

    // Get server URL for the uploaded file
    $fileUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . 
               $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/uploads/' . $filename;

    // Prepare sign protocol URL
    $request = [
        'resources' => [
            [
                'source' => $fileUrl,
                'format' => $_POST['signatureFormat'] ?? 'PadesBes',
                'pdfOptions' => [
                    'x' => (int)($_POST['posX'] ?? 10),
                    'y' => (int)($_POST['posY'] ?? 10),
                    'width' => (int)($_POST['width'] ?? 200),
                    'height' => (int)($_POST['height'] ?? 50),
                    'signatureName' => 'Elektronik İmza',
                    'reason' => 'Belge İmzalama',
                    'location' => 'Türkiye'
                ]
            ]
        ],
        'responseUrl' => 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . 
                        $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/verify.php'
    ];

    // Generate sign protocol URL
    $signUrl = 'sign://?xsjson=' . base64_encode(json_encode($request));

    // Return success response with sign URL
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'signUrl' => $signUrl
    ]);
    exit;

} catch (Exception $e) {
    // Clean up uploaded file if exists
    if (isset($uploadPath) && file_exists($uploadPath)) {
        @unlink($uploadPath);
    }

    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}