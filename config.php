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

// Load required files
require_once __DIR__ . '/includes/SecurityHelper.php';

// Set security headers
SecurityHelper::setSecurityHeaders();

// Directory paths
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('TEMP_DIR', __DIR__ . '/temp/');

// File settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB in bytes
define('ALLOWED_EXTENSIONS', ['pdf']);
define('ALLOWED_MIME_TYPES', ['application/pdf']);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'eimza_db');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');

// Rate limiting
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_ATTEMPTS_PERIOD', 900); // 15 minutes
define('API_RATE_LIMIT', 100);
define('API_RATE_PERIOD', 3600); // 1 hour

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci"
    ]);
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('Database connection failed. Please try again later.');
    }
}

// Error handling function
function handleError($message, $redirect = true)
{
    $_SESSION['error'] = SecurityHelper::sanitizeString($message);
    if ($redirect) {
        header('Location: index.php');
        exit;
    }
}

// Success handling function
function handleSuccess($message, $redirect = true)
{
    $_SESSION['success'] = SecurityHelper::sanitizeString($message);
    if ($redirect) {
        header('Location: index.php');
        exit;
    }
}

// Validate file extension
function isAllowedExtension($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_EXTENSIONS);
}

// Clean filename
function sanitizeFilename($filename)
{
    return SecurityHelper::sanitizeFilename($filename);
}

// Create required directories if they don't exist
foreach ([UPLOAD_DIR, TEMP_DIR] as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Initialize rate limiting session storage if needed
if (!isset($_SESSION['rate_limits'])) {
    $_SESSION['rate_limits'] = [];
}
