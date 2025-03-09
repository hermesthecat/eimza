<?php
require_once __DIR__ . '/../includes/SecurityHelper.php';
require_once __DIR__ . '/../includes/UserManager.php';
require_once __DIR__ . '/../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize UserManager
$userManager = new UserManager($db, Logger::getInstance());

/**
 * Admin girişini kontrol eder
 * @return bool
 */
function isAdminLoggedIn()
{
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true && isset($_SESSION['admin_id']);
}

/**
 * Admin girişi yoksa login sayfasına yönlendirir
 */
function requireAdmin()
{
    global $userManager;
    
    if (!isAdminLoggedIn()) {
        // Log unauthorized access attempt
        Logger::getInstance()->warning('Unauthorized access attempt from IP: ' . SecurityHelper::getClientIP());
        header('Location: login.php');
        exit;
    }

    // Verify admin still exists and has admin role
    $adminUser = $userManager->getUserById($_SESSION['admin_id']);
    if (!$adminUser || $adminUser['role'] !== 'admin') {
        // Log potential security issue
        Logger::getInstance()->warning('Invalid admin session detected', [
            'userId' => $_SESSION['admin_id'],
            'ip' => SecurityHelper::getClientIP()
        ]);
        
        // Force logout
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

/**
 * Admin kullanıcı adını döndürür
 * @return string|null
 */
function getAdminUsername()
{
    return isset($_SESSION['admin_username']) ?
        SecurityHelper::sanitizeString($_SESSION['admin_username']) : null;
}

/**
 * CSRF token oluşturur
 * @return string
 */
function generateCsrfToken()
{
    return $_SESSION['csrf_token'] = SecurityHelper::generateToken();
}

/**
 * CSRF token doğrular
 * @param string $token
 * @return bool
 */
function validateCsrfToken($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * IP adresini alır
 * @return string
 */
function getClientIP()
{
    return SecurityHelper::getClientIP();
}

/**
 * Başarısız giriş denemelerini kontrol eder
 * @param string $username
 * @return bool
 */
function checkLoginAttempts($username)
{
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
function recordFailedLogin($username)
{
    $username = SecurityHelper::sanitizeString($username);
    $_SESSION['rate_limits']['login_' . $username] =
        ($_SESSION['rate_limits']['login_' . $username] ?? []);
    $_SESSION['rate_limits']['login_' . $username][] = time();
}

/**
 * Başarısız giriş denemelerini sıfırlar
 * @param string $username
 */
function resetLoginAttempts($username)
{
    $username = SecurityHelper::sanitizeString($username);
    $_SESSION['rate_limits']['login_' . $username] = [];
}

/**
 * Admin şifresini doğrular
 * @param string $username
 * @param string $password
 * @return bool|array False on failure, user data on success
 */
function validateAdminCredentials($username, $password)
{
    global $userManager;
    
    $user = $userManager->getUserByUsername($username);
    
    if (!$user || $user['role'] !== 'admin') {
        return false;
    }

    if (!$userManager->verifyPassword($password, $user['password_hash'])) {
        return false;
    }

    // Update last login time
    $userManager->updateLastLogin($user['id']);
    
    return $user;
}

/**
 * Güvenlik loglarını kaydeder
 * @param string $action
 * @param string $details
 */
function logSecurityEvent($action, $details = '')
{
    Logger::getInstance()->warning(sprintf(
        "Security Event - Action: %s, User: %s, IP: %s, Details: %s",
        $action,
        getAdminUsername() ?? 'anonymous',
        SecurityHelper::getClientIP(),
        $details
    ));
}

/**
 * Creates initial admin user if no admin exists
 * @return bool
 */
function createInitialAdminIfNeeded()
{
    global $userManager;
    
    // Check if any admin user exists
    $stmt = $GLOBALS['db']->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();

    if ($adminCount === 0) {
        // Create initial admin user
        $initialAdminPassword = bin2hex(random_bytes(8)); // Generate a random 16-character password
        $admin = $userManager->createUser(
            'admin',
            $initialAdminPassword,
            'System Administrator',
            'admin@example.com',
            'admin'
        );

        if ($admin) {
            Logger::getInstance()->info(
                'Initial admin user created. Please change password immediately.',
                ['username' => 'admin', 'password' => $initialAdminPassword]
            );
            return true;
        }
        
        Logger::getInstance()->error('Failed to create initial admin user');
        return false;
    }

    return true;
}
