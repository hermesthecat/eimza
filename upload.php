<?php

/**
 * PDF Dosya Yükleme İşleyicisi
 * @author A. Kerem Gök
 */

// Hata raporlamayı ayarla
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Yapılandırma
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_TYPES', ['application/pdf']);

// Yanıt fonksiyonu
function sendResponse($success, $message, $data = null)
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Yükleme dizinini kontrol et ve oluştur
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        sendResponse(false, 'Yükleme dizini oluşturulamadı');
    }
}

// POST isteği kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Geçersiz istek metodu');
}

// Dosya kontrolü
if (!isset($_FILES['pdf_file'])) {
    sendResponse(false, 'Dosya yüklenemedi');
}

$file = $_FILES['pdf_file'];

// Hata kontrolü
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Dosya boyutu PHP yapılandırmasında izin verilenden büyük',
        UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitinden büyük',
        UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi',
        UPLOAD_ERR_NO_FILE => 'Dosya yüklenmedi',
        UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı',
        UPLOAD_ERR_CANT_WRITE => 'Disk yazma hatası',
        UPLOAD_ERR_EXTENSION => 'PHP uzantısı dosya yüklemesini durdurdu'
    ];

    sendResponse(false, $errorMessages[$file['error']] ?? 'Bilinmeyen yükleme hatası');
}

// Boyut kontrolü
if ($file['size'] > MAX_FILE_SIZE) {
    sendResponse(false, 'Dosya boyutu çok büyük (maksimum 10MB)');
}

// Dosya tipi kontrolü
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, ALLOWED_TYPES)) {
    sendResponse(false, 'Sadece PDF dosyaları yüklenebilir');
}

// Güvenli dosya adı oluştur
$fileName = uniqid('pdf_') . '.pdf';
$filePath = UPLOAD_DIR . $fileName;

// Dosyayı taşı
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    sendResponse(false, 'Dosya kaydedilemedi');
}

// Dosya URL'ini oluştur
$fileUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
    $_SERVER['HTTP_HOST'] .
    dirname($_SERVER['PHP_SELF']) . '/' .
    $filePath;

// Başarılı yanıt
sendResponse(true, 'Dosya başarıyla yüklendi', [
    'url' => $fileUrl,
    'name' => $fileName,
    'size' => $file['size']
]);
