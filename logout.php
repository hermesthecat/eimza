<?php
require_once 'config.php';
require_once 'includes/logger.php';

// Log the logout event if user was logged in
if (isset($_SESSION['user_id'])) {
    Logger::getInstance()->info("User logged out", [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'ip' => SecurityHelper::getClientIP()
    ]);
}

// Destroy the session
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page
header('Location: ' . $domain . '/login.php');
exit;
