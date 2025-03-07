<?php
require_once 'config.php';

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

    // Validate signature format
    $allowedFormats = ['PadesBes', 'PadesT'];
    $signatureFormat = $_POST['signatureFormat'] ?? 'PadesBes';
    if (!in_array($signatureFormat, $allowedFormats)) {
        throw new Exception('Geçersiz imza formatı.');
    }

    // Validate position values
    $posX = filter_var($_POST['posX'] ?? 10, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]);
    $posY = filter_var($_POST['posY'] ?? 10, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]);
    $width = filter_var($_POST['width'] ?? 200, FILTER_VALIDATE_INT, ["options" => ["min_range" => 50]]);
    $height = filter_var($_POST['height'] ?? 50, FILTER_VALIDATE_INT, ["options" => ["min_range" => 20]]);

    if ($posX === false || $posY === false || $width === false || $height === false) {
        throw new Exception('Geçersiz imza pozisyon değerleri.');
    }

    // Prepare signature request
    $request = [
        'resources' => [
            [
                'source' => realpath($uploadPath),
                'sourceType' => 'Binary',
                'format' => $signatureFormat,
                'pdfOptions' => [
                    'x' => $posX,
                    'y' => $posY,
                    'width' => $width,
                    'height' => $height,
                    'signatureName' => 'Elektronik İmza',
                    'reason' => 'Belge İmzalama',
                    'location' => 'Türkiye'
                ]
            ]
        ],
        'timestamp' => [
            'url' => 'http://zd.kamusm.gov.tr',
        ]
    ];

    // Save request to temporary JSON file
    $requestFile = TEMP_DIR . uniqid() . '.json';
    if (file_put_contents($requestFile, json_encode($request, JSON_PRETTY_PRINT)) === false) {
        throw new Exception('İmza talebi oluşturulamadı.');
    }

    // Execute Kolay Imza
    $command = sprintf(
        '"%s" "%s"',
        KOLAY_IMZA_PATH,
        $requestFile
    );

    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        throw new Exception('İmzalama işlemi başarısız oldu. Hata kodu: ' . $returnCode);
    }

    // Check if signed file exists
    $signedFile = str_replace('.pdf', '_signed.pdf', $uploadPath);
    if (!file_exists($signedFile)) {
        throw new Exception('İmzalı dosya oluşturulamadı.');
    }

    // Clean up temporary files
    @unlink($requestFile);
    @unlink($uploadPath);

    // Set appropriate headers for file download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '_imzali.pdf"');
    header('Content-Length: ' . filesize($signedFile));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file and clean up
    readfile($signedFile);
    @unlink($signedFile);
    exit;

} catch (Exception $e) {
    // Log error if in debug mode
    if (DEBUG_MODE) {
        error_log("PDF Signing Error: " . $e->getMessage());
    }

    // Clean up any temporary files
    if (isset($requestFile) && file_exists($requestFile)) {
        @unlink($requestFile);
    }
    if (isset($uploadPath) && file_exists($uploadPath)) {
        @unlink($uploadPath);
    }
    if (isset($signedFile) && file_exists($signedFile)) {
        @unlink($signedFile);
    }

    handleError($e->getMessage());
}