<?php
require_once 'config.php';
require_once 'includes/logger.php';

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
    // Convert shortcuts like "2M" to bytes for comparison
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
                            <a href="index.php" class="btn btn-secondary me-2">
                                <i class="fas fa-arrow-left me-2"></i>Geri
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