<?php
require_once 'config.php';
require_once 'includes/logger.php';
require_once 'includes/SignatureManager.php';
require_once 'includes/SecurityHelper.php';

try {
    // Ensure upload directory exists
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    // Rate limit kontrolü
    if (!SecurityHelper::checkRateLimit('api_sign', API_RATE_LIMIT, API_RATE_PERIOD)) {
        throw new Exception('Çok fazla istek yapıldı. Lütfen daha sonra tekrar deneyin.');
    }

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
    if (!SecurityHelper::validateFileSize($file['size'], MAX_FILE_SIZE)) {
        throw new Exception('Dosya boyutu çok büyük. Maksimum boyut: 10MB');
    }

    // Validate MIME type
    if (!SecurityHelper::validateMimeType($file['tmp_name'], ALLOWED_MIME_TYPES)) {
        throw new Exception('Sadece PDF dosyaları kabul edilmektedir.');
    }

    // Generate safe filename
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeFilename = SecurityHelper::sanitizeFilename($originalName);
    $filename = $safeFilename . '_' . SecurityHelper::generateToken(8) . '.pdf';
    $uploadPath = UPLOAD_DIR . $filename;

    // Basic path validation
    if (!SecurityHelper::isValidPath($filename)) {
        throw new Exception('Geçersiz dosya adı.');
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Dosya kaydedilemedi. Upload dizini yazılabilir olduğundan emin olun.');
    }

    // Double check if file exists and is readable
    if (!file_exists($uploadPath) || !is_readable($uploadPath)) {
        throw new Exception('Yüklenen dosya okunamıyor.');
    }

    // Initialize signature manager
    $signatureManager = new SignatureManager($db, Logger::getInstance());

    // Prepare file info
    $fileInfo = [
        'filename' => $filename,
        'original_name' => $file['name'],
        'size' => $file['size']
    ];

    // Prepare signature options with sanitized inputs
    $signatureOptions = [
        'format' => in_array($_POST['signatureFormat'] ?? 'PadesBes', ['PadesBes', 'PadesT']) 
            ? $_POST['signatureFormat'] : 'PadesBes',
        'x' => filter_var($_POST['posX'] ?? 10, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]) ?: 10,
        'y' => filter_var($_POST['posY'] ?? 10, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]) ?: 10,
        'width' => filter_var($_POST['width'] ?? 200, FILTER_VALIDATE_INT, ["options" => ["min_range" => 50]]) ?: 200,
        'height' => filter_var($_POST['height'] ?? 50, FILTER_VALIDATE_INT, ["options" => ["min_range" => 20]]) ?: 50,
        'location' => SecurityHelper::sanitizeString($_POST['location'] ?? 'Türkiye'),
        'reason' => SecurityHelper::sanitizeString($_POST['reason'] ?? 'Belge İmzalama')
    ];

    // Create signature record in database
    $signatureId = $signatureManager->createSignatureRecord($fileInfo, $signatureOptions);

    // Get server protocol and host
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Create file URL
    $fileUrl = $protocol . $host . '/uploads/' . $filename;

    // Log the generated URL for debugging
    Logger::getInstance()->debug("Generated file URL: $fileUrl");
    
    // Prepare sign protocol URL
    $request = [
        'resources' => [
            [
                'source' => $fileUrl,
                'format' => $signatureOptions['format'],
                'pdfOptions' => [
                    'x' => $signatureOptions['x'],
                    'y' => $signatureOptions['y'],
                    'width' => $signatureOptions['width'],
                    'height' => $signatureOptions['height'],
                    'signatureName' => 'Elektronik İmza',
                    'reason' => $signatureOptions['reason'],
                    'location' => $signatureOptions['location']
                ]
            ]
        ],
        'responseUrl' => $protocol . $host . '/verify.php'
    ];

    // Generate sign protocol URL
    $signUrl = 'sign://?xsjson=' . base64_encode(json_encode($request));

    // Log successful request
    Logger::getInstance()->info("Signature request created for file: $filename, URL: $fileUrl, IP: " . SecurityHelper::getClientIP());

    // Return success response with sign URL
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'signUrl' => $signUrl,
        'signatureId' => $signatureId
    ]);
    exit;

} catch (Exception $e) {
    // Log error with details
    Logger::getInstance()->error(sprintf(
        'Signature error: %s, File: %s, IP: %s',
        $e->getMessage(),
        $file['name'] ?? 'unknown',
        SecurityHelper::getClientIP()
    ));

    // Mark signature as failed if it was created
    if (isset($signatureManager) && isset($filename)) {
        $signatureManager->markAsFailed($filename, $e->getMessage());
    }

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
