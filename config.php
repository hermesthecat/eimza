<?php
// Application configuration

// Debug mode (set to false in production)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Directory paths
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('TEMP_DIR', __DIR__ . '/temp/');

// File settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB in bytes
define('ALLOWED_EXTENSIONS', ['pdf']);

// Kolay Imza settings
define('KOLAY_IMZA_PATH', 'C:\\Program Files (x86)\\KolayImza\\AltiKare.KolayImza.exe');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
session_start();

// Error handling function
function handleError($message, $redirect = true) {
    $_SESSION['error'] = $message;
    if ($redirect) {
        header('Location: index.php');
        exit;
    }
}

// Success handling function
function handleSuccess($message, $redirect = true) {
    $_SESSION['success'] = $message;
    if ($redirect) {
        header('Location: index.php');
        exit;
    }
}

// Validate file extension
function isAllowedExtension($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_EXTENSIONS);
}

// Clean filename
function sanitizeFilename($filename) {
    // Remove any character that is not alphanumeric, dot, dash or underscore
    $filename = preg_replace("/[^a-zA-Z0-9.-_]/", "", $filename);
    // Remove any runs of dots
    $filename = preg_replace("/([.-]){2,}/", "$1", $filename);
    return $filename;
}

// Create required directories if they don't exist
foreach ([UPLOAD_DIR, TEMP_DIR] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}