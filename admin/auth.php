<?php
require_once __DIR__ . '/../includes/SecurityHelper.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Admin girişini kontrol eder
 * @return bool
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

/**
 * Admin girişi yoksa login sayfasına yönlendirir
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        // Log unauthorized access attempt
        Logger::getInstance()->warning('Unauthorized access attempt from IP: ' . SecurityHelper::getClientIP());
        header('Location: login.php');
        exit;
    }
}

/**
 * Admin kullanıcı adını döndürür
 * @return string|null
 */
function getAdminUsername() {
    return isset($_SESSION['admin_username']) ? 
        SecurityHelper::sanitizeString($_SESSION['admin_username']) : null;
}

/**
 * CSRF token oluşturur
 * @return string
 */
function generateCsrfToken() {
    return $_SESSION['csrf_token'] = SecurityHelper::generateToken();
}

/**
 * CSRF token doğrular
 * @param string $token
 * @return bool
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * IP adresini alır
 * @return string
 */
function getClientIP() {
    return SecurityHelper::getClientIP();
}

/**
 * Başarısız giriş denemelerini kontrol eder
 * @param string $username
 * @return bool
 */
function checkLoginAttempts($username) {
    $username = SecurityHelper::sanitizeString($username);
    return SecurityHelper::checkRateLimit(
        'login_' . $username, 
        LOGIN_ATTEMPTS_LIMIT, 
        LOGIN_ATTEMPTS_PERIOD
    );
}

/**
 * Başarısız giriş denemesini kaydeder
 * @param string $username
 */
function recordFailedLogin($username) {
    $username = SecurityHelper::sanitizeString($username);
    $_SESSION['rate_limits']['login_' . $username] = 
        ($_SESSION['rate_limits']['login_' . $username] ?? []);
    $_SESSION['rate_limits']['login_' . $username][] = time();
}

/**
 * Başarısız giriş denemelerini sıfırlar
 * @param string $username
 */
function resetLoginAttempts($username) {
    $username = SecurityHelper::sanitizeString($username);
    $_SESSION['rate_limits']['login_' . $username] = [];
}

/**
 * Admin şifresini doğrular
 * @param string $password
 * @return bool
 */
function validateAdminPassword($password) {
    // TODO: Veritabanından şifre kontrolü yapılacak
    // Şimdilik sabit şifre kontrolü
    return $password === 'admin123' && SecurityHelper::isStrongPassword($password);
}

/**
 * Güvenlik loglarını kaydeder
 * @param string $action
 * @param string $details
 */
function logSecurityEvent($action, $details = '') {
    Logger::getInstance()->warning(sprintf(
        "Security Event - Action: %s, User: %s, IP: %s, Details: %s",
        $action,
        getAdminUsername() ?? 'anonymous',
        SecurityHelper::getClientIP(),
        $details
    ));
}