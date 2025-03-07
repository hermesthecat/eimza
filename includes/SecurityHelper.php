<?php
class SecurityHelper {
    /**
     * XSS için string temizleme
     */
    public static function sanitizeString($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Dosya adı temizleme
     */
    public static function sanitizeFilename($filename) {
        // Remove any character that is not alphanumeric, dot, dash or underscore
        $filename = preg_replace("/[^a-zA-Z0-9.-_]/", "", $filename);
        // Remove any runs of dots
        $filename = preg_replace("/([.-]){2,}/", "$1", $filename);
        return $filename;
    }

    /**
     * IP adresi kontrol
     */
    public static function getClientIP() {
        $ipAddress = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }
        
        return filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : 'unknown';
    }

    /**
     * MIME type kontrol
     */
    public static function validateMimeType($file, $allowedTypes) {
        if (!function_exists('finfo_open')) {
            throw new Exception('fileinfo extension is not installed');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);

        return in_array($mimeType, $allowedTypes);
    }

    /**
     * Dosya boyutu kontrol
     */
    public static function validateFileSize($fileSize, $maxSize) {
        return $fileSize > 0 && $fileSize <= $maxSize;
    }

    /**
     * Path traversal kontrol - Yeniden düzenlenmiş versiyonu
     */
    public static function isValidPath($path) {
        // Dosya adında tehlikeli karakterler var mı kontrol et
        if (preg_match('/[<>:"\\|?*]/', $path)) {
            return false;
        }

        // Path traversal girişimi var mı kontrol et
        if (strpos($path, '../') !== false || strpos($path, '..\\') !== false) {
            return false;
        }

        // Dosya adı çok uzun mu kontrol et
        if (strlen(basename($path)) > 255) {
            return false;
        }

        return true;
    }

    /**
     * Token oluşturma
     */
    public static function generateToken($length = 32) {
        try {
            return bin2hex(random_bytes($length));
        } catch (Exception $e) {
            return bin2hex(openssl_random_pseudo_bytes($length));
        }
    }

    /**
     * Rate limiting kontrol
     */
    public static function checkRateLimit($key, $limit, $period = 3600) {
        $currentTime = time();
        $attempts = isset($_SESSION['rate_limits'][$key]) ? $_SESSION['rate_limits'][$key] : [];
        
        // Süresi dolmuş girişimleri temizle
        $attempts = array_filter($attempts, function($timestamp) use ($currentTime, $period) {
            return $currentTime - $timestamp < $period;
        });
        
        if (count($attempts) >= $limit) {
            return false;
        }
        
        $attempts[] = $currentTime;
        $_SESSION['rate_limits'][$key] = $attempts;
        
        return true;
    }

    /**
     * Güçlü şifre kontrolü
     */
    public static function isStrongPassword($password) {
        // En az 8 karakter
        if (strlen($password) < 8) {
            return false;
        }
        
        // En az bir büyük harf
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // En az bir küçük harf
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // En az bir rakam
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // En az bir özel karakter
        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            return false;
        }
        
        return true;
    }

    /**
     * İçerik güvenliği politikası header'ı
     */
    public static function setSecurityHeaders() {
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net code.jquery.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; font-src 'self' cdnjs.cloudflare.com; img-src 'self' data:;");
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Type Options
        header('X-Content-Type-Options: nosniff');
        
        // Frame Options
        header('X-Frame-Options: SAMEORIGIN');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Remove PHP Version
        header_remove('X-Powered-By');
    }
}