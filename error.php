<?php
require_once 'config.php';
require_once 'includes/logger.php';

$errorCodes = [
    400 => ['Bad Request', 'Sunucuya geçersiz bir istek yapıldı.'],
    401 => ['Unauthorized', 'Bu sayfaya erişim izniniz yok.'],
    403 => ['Forbidden', 'Bu sayfaya erişim yasak.'],
    404 => ['Not Found', 'Aradığınız sayfa bulunamadı.'],
    500 => ['Internal Server Error', 'Sunucu hatası oluştu.']
];

$code = isset($_GET['code']) ? (int)$_GET['code'] : 404;
if (!isset($errorCodes[$code])) {
    $code = 404;
}

$error = $errorCodes[$code];

// Log the error with user context if available
$logContext = [
    'url' => $_SERVER['REQUEST_URI'],
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'ip' => SecurityHelper::getClientIP()
];
Logger::getInstance()->error("HTTP Error {$code}", $logContext);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hata <?php echo $code; ?> - PDF İmzalama Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .error-page {
            min-height: calc(100vh - 150px); /* Account for navbar and footer */
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }

        .error-code {
            font-size: 8rem;
            font-weight: bold;
            color: #dc3545;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }

        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 2rem;
        }

        .error-message {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-file-signature me-2"></i>
                PDF İmzalama Sistemi
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (isset($_SESSION['user_id'])): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>
                            Ana Sayfa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sign_document.php">
                            <i class="fas fa-file-signature me-1"></i>
                            İmza Bekleyenler
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="test_multi_signature.php">
                            <i class="fas fa-users me-1"></i>
                            Çoklu İmza
                        </a>
                    </li>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/signatures.php">
                            <i class="fas fa-cogs me-1"></i>
                            Yönetim Paneli
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?= htmlspecialchars($_SESSION['full_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">TCKN: <?= htmlspecialchars($_SESSION['tckn']) ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-1"></i>
                                    Çıkış Yap
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="error-page">
        <div class="container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="error-code"><?php echo $code; ?></div>
            <h1 class="h3 mb-3"><?php echo htmlspecialchars($error[0]); ?></h1>
            <div class="error-message"><?php echo htmlspecialchars($error[1]); ?></div>
            <a href="<?php echo isset($_SESSION['user_id']) ? 'index.php' : 'login.php'; ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-<?php echo isset($_SESSION['user_id']) ? 'home' : 'sign-in-alt'; ?> me-2"></i>
                <?php echo isset($_SESSION['user_id']) ? 'Ana Sayfaya Dön' : 'Giriş Yap'; ?>
            </a>
            <div class="mt-4 text-muted">
                <small>PDF İmzalama Sistemi &copy; <?php echo date('Y'); ?></small>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>