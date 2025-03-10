<?php
require_once '../config.php';
require_once '../includes/logger.php';
require_once 'auth.php';

// If already logged in, check role and redirect appropriately
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        // Admin user is already logged in
        header('Location: signatures.php');
        exit;
    } else {
        // Non-admin user tried to access admin area
        header('Location: ../error.php?code=403');
        exit;
    }
}

// Not logged in, redirect to main login page with return URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$return_url = urlencode($protocol . $host . '/admin/signatures.php');
header('Location: ../login.php?return=' . $return_url);
exit;