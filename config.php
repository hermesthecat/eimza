<?php
/**
 * Veritabanı Yapılandırması
 * @author A. Kerem Gök
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'kolayimza_db');
define('DB_USER', 'root');     // Güvenlik için değiştirin
define('DB_PASS', '');         // Güvenlik için değiştirin

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug modu (Geliştirme ortamında true, canlı ortamda false olmalı)
define('DEBUG_MODE', true);

// Zaman dilimi ayarı
date_default_timezone_set('Europe/Istanbul'); 