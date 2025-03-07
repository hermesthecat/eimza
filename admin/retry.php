<?php
require_once '../config.php';
require_once '../includes/logger.php';
require_once '../includes/SignatureManager.php';
require_once 'auth.php';

// Yetkilendirme kontrolü
requireAdmin();

try {
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        throw new Exception('Geçersiz form gönderimi');
    }

    // ID kontrolü
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception('Geçersiz imza ID');
    }

    $signatureId = (int)$_POST['id'];
    $signatureManager = new SignatureManager($db, Logger::getInstance());

    // İmza kaydını getir
    $sql = "SELECT * FROM signatures WHERE id = ? AND status = 'failed'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$signatureId]);
    $signature = $stmt->fetch();

    if (!$signature) {
        throw new Exception('İmza kaydı bulunamadı veya yeniden deneme yapılamaz');
    }

    // Dosyanın varlığını kontrol et
    $uploadPath = UPLOAD_DIR . $signature['filename'];
    if (!file_exists($uploadPath)) {
        throw new Exception('İmzalanacak dosya bulunamadı');
    }

    // Get server protocol and host
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // Create file URL
    $fileUrl = $protocol . $host . '/uploads/' . $signature['filename'];

    // Prepare sign protocol URL
    $request = [
        'resources' => [
            [
                'source' => $fileUrl,
                'format' => $signature['signature_format'],
                'pdfOptions' => [
                    'x' => $signature['pdf_signature_pos_x'],
                    'y' => $signature['pdf_signature_pos_y'],
                    'width' => $signature['pdf_signature_width'],
                    'height' => $signature['pdf_signature_height'],
                    'signatureName' => 'Elektronik İmza',
                    'reason' => $signature['signature_reason'],
                    'location' => $signature['signature_location']
                ]
            ]
        ],
        'responseUrl' => $protocol . $host . '/verify.php'
    ];

    // Update signature status to pending
    $sql = "UPDATE signatures SET status = 'pending', error_message = NULL WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$signatureId]);

    // Generate sign protocol URL
    $signUrl = 'sign://?xsjson=' . base64_encode(json_encode($request));

    // Log retry attempt
    Logger::getInstance()->info("Signature retry initiated for ID: $signatureId by " . getAdminUsername());

    // Redirect with success message
    $_SESSION['success'] = 'İmzalama işlemi yeniden başlatıldı';
    header('Location: signatures.php');
    exit;
} catch (Exception $e) {
    Logger::getInstance()->error('Signature retry error: ' . $e->getMessage());

    $_SESSION['error'] = $e->getMessage();
    header('Location: signatures.php');
    exit;
}
