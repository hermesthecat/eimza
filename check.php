<?php
require_once 'config.php';
require_once 'includes/logger.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is an admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: error.php?code=403');
    exit;
}

$checks = [];

// PHP Version
$checks['PHP Version'] = [
    'required' => '7.4.0',
    'current' => PHP_VERSION,
    'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'critical' => true
];

// Extensions
$requiredExtensions = ['curl', 'json', 'fileinfo'];
foreach ($requiredExtensions as $ext) {
    $checks["PHP Extension: $ext"] = [
        'required' => 'Installed',
        'current' => extension_loaded($ext) ? 'Installed' : 'Not Installed',
        'status' => extension_loaded($ext),
        'critical' => true
    ];
}

// Directory Permissions
$directories = [
    UPLOAD_DIR => '0777',
    TEMP_DIR => '0777',
    dirname(Logger::getInstance()->getLogPath()) => '0777'
];

foreach ($directories as $dir => $required_perms) {
    $real_path = realpath($dir) ?: $dir;
    $exists = file_exists($real_path);
    $writable = is_writable($real_path);
    $perms = $exists ? substr(sprintf('%o', fileperms($real_path)), -4) : 'N/A';

    $checks["Directory: " . basename($dir)] = [
        'required' => $required_perms,
        'current' => $exists ? ($writable ? "Writable ($perms)" : "Not writable ($perms)") : 'Does not exist',
        'status' => $exists && $writable,
        'critical' => true
    ];
}

// PHP Settings
$settings = [
    'upload_max_filesize' => '10M',
    'post_max_size' => '10M',
    'max_execution_time' => '300',
    'max_input_time' => '300'
];

foreach ($settings as $setting => $required_value) {
    $current_value = ini_get($setting);
    $checks["PHP Setting: $setting"] = [
        'required' => $required_value,
        'current' => $current_value,
        'status' => compare_php_values($current_value, $required_value),
        'critical' => true
    ];
}

function compare_php_values($current, $required)
{
    $current_bytes = convert_php_value_to_bytes($current);
    $required_bytes = convert_php_value_to_bytes($required);
    return $current_bytes >= $required_bytes;
}

function convert_php_value_to_bytes($value)
{
    $value = trim($value);
    $last = strtolower($value[strlen($value) - 1]);
    $value = (int)$value;

    switch ($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }

    return $value;
}

$allPassed = true;
$criticalFailed = false;

foreach ($checks as $check) {
    if (!$check['status']) {
        $allPassed = false;
        if ($check['critical']) {
            $criticalFailed = true;
        }
    }
}

// Log system check
Logger::getInstance()->info("System check performed", [
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'all_passed' => $allPassed,
    'critical_failed' => $criticalFailed
]);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Kontrol - PDF İmzalama Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
                    <li class="nav-item">
                        <a class="nav-link active" href="admin/signatures.php">
                            <i class="fas fa-cogs me-1"></i>
                            Yönetim Paneli
                        </a>
                    </li>
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
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-cogs me-2"></i>Sistem Kontrol
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($criticalFailed): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Kritik Hata!</strong> Bazı zorunlu gereksinimler karşılanmıyor.
                            </div>
                        <?php elseif (!$allPassed): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Uyarı!</strong> Bazı önerilen gereksinimler karşılanmıyor.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Başarılı!</strong> Tüm sistem gereksinimleri karşılanıyor.
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Kontrol</th>
                                        <th>Gerekli</th>
                                        <th>Mevcut</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($checks as $name => $check): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($name); ?>
                                                <?php if ($check['critical']): ?>
                                                    <span class="badge bg-danger ms-2">Zorunlu</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($check['required']); ?></td>
                                            <td><?php echo htmlspecialchars($check['current']); ?></td>
                                            <td>
                                                <?php if ($check['status']): ?>
                                                    <span class="text-success">
                                                        <i class="fas fa-check-circle"></i>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-danger">
                                                        <i class="fas fa-times-circle"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-end mt-4">
                            <a href="admin/signatures.php" class="btn btn-secondary me-2">
                                <i class="fas fa-arrow-left me-2"></i>Yönetim Paneli
                            </a>
                            <a href="check.php" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-2"></i>Yenile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>