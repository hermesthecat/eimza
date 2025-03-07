<?php
require_once '../config.php';
require_once '../includes/logger.php';

session_start();

// Log the logout
if (isset($_SESSION['admin_username'])) {
    Logger::getInstance()->info("Admin logout: " . $_SESSION['admin_username']);
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
