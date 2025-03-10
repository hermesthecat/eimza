<?php
require_once '../config.php';
require_once '../includes/logger.php';
require_once '../includes/SignatureManager.php';
require_once '../includes/SecurityHelper.php';
require_once 'auth.php';

// Yetkilendirme kontrolü
requireAdmin();

try {
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || !SecurityHelper::validateCsrfToken($_POST['csrf_token'])) {
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

    // Validate file path
    if (!SecurityHelper::isValidPath($signature['filename'])) {
        throw new Exception('Geçersiz dosya yolu');
    }

    // Get server protocol and host
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // Create file URL
    //$fileUrl = $protocol . $host . '/uploads/' . $signature['filename'];
    $fileUrl = $domain . '/uploads/' . $signature['filename'];

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
                    'signatureName' => $_SESSION['full_name'], // Use authenticated user's name
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

    // Log retry attempt with full context
    Logger::getInstance()->info("Signature retry initiated", [
        'signature_id' => $signatureId,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'tckn' => $_SESSION['tckn'],
        'ip' => SecurityHelper::getClientIP(),
        'filename' => $signature['filename']
    ]);

    // Store signature details in session
    $_SESSION['pending_signature'] = [
        'id' => $signatureId,
        'filename' => $signature['filename'],
        'url' => $signUrl
    ];

    // Redirect with success message
    $_SESSION['success'] = 'İmzalama işlemi yeniden başlatıldı';
    header('Location: signatures.php');
    exit;
} catch (Exception $e) {
    // Log error with full context
    Logger::getInstance()->error('Signature retry error', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'ip' => SecurityHelper::getClientIP(),
        'signature_id' => $signatureId ?? null
    ]);

    $_SESSION['error'] = $e->getMessage();
    header('Location: signatures.php');
    exit;
}
