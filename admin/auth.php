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
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Admin girişi yoksa login sayfasına yönlendirir
 */
function requireAdmin()
{
    global $userManager;
    
    if (!isAdminLoggedIn()) {
        // Log unauthorized access attempt
        Logger::getInstance()->warning('Unauthorized admin access attempt', [
            'ip' => SecurityHelper::getClientIP(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ]);
        
        if (!isset($_SESSION['user_id'])) {
            // Not logged in at all
            header('Location: /login.php');
        } else {
            // Logged in but not admin
            header('Location: /error.php?code=403');
        }
        exit;
    }

    // Verify admin still exists and has admin role
    $adminUser = $userManager->getUserById($_SESSION['user_id']);
    if (!$adminUser || $adminUser['role'] !== 'admin') {
        // Log potential security issue
        Logger::getInstance()->warning('Invalid admin session detected', [
            'user_id' => $_SESSION['user_id'],
            'ip' => SecurityHelper::getClientIP()
        ]);
        
        // Force logout
        session_destroy();
        header('Location: /login.php');
        exit;
    }
}

/**
 * Admin kullanıcı adını döndürür
 * @return string|null
 */
function getAdminUsername()
{
    return isset($_SESSION['username']) ?
        SecurityHelper::sanitizeString($_SESSION['username']) : null;
}

/**
 * CSRF token oluşturur
 * @return string
 */
function generateCsrfToken()
{
    return SecurityHelper::generateCsrfToken();
}

/**
 * CSRF token doğrular
 * @param string $token
 * @return bool
 */
function validateCsrfToken($token)
{
    return SecurityHelper::validateCsrfToken($token);
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
    return SecurityHelper::checkRateLimit(
        'login_' . SecurityHelper::sanitizeString($username),
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
    SecurityHelper::recordFailedLogin($username);
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
    Logger::getInstance()->warning("Security Event", [
        'action' => $action,
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'anonymous',
        'role' => $_SESSION['role'] ?? null,
        'ip' => SecurityHelper::getClientIP(),
        'details' => $details
    ]);
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
        try {
            // Generate a random 16-character password
            $initialAdminPassword = bin2hex(random_bytes(8));
            
            // Create initial admin user
            $admin = $userManager->createUser(
                'admin',
                $initialAdminPassword,
                'System Administrator',
                'admin@example.com',
                'admin',
                '11111111111' // Default TCKN - should be changed immediately
            );

            if ($admin) {
                Logger::getInstance()->info(
                    'Initial admin user created. Please change password immediately.',
                    [
                        'username' => 'admin',
                        'password' => $initialAdminPassword,
                        'tckn' => '11111111111'
                    ]
                );
                return true;
            }
        } catch (Exception $e) {
            Logger::getInstance()->error('Failed to create initial admin user', [
                'error' => $e->getMessage()
            ]);
        }
        return false;
    }

    return true;
}
