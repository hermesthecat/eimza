<?php
require_once 'config.php';
require_once 'includes/logger.php';
require_once 'includes/SignatureManager.php';

// Initialize signature manager
$signatureManager = new SignatureManager($db, Logger::getInstance());

// İmzalama işlemi
if (isset($_POST['sign']) && isset($_POST['filename'])) {
    try {
        $filename = $_POST['filename'];
        $certificateNo = $_POST['certificate_no'];

        // Debug: İmza kaydını kontrol et
        $record = $signatureManager->getSignatureRecord($filename);
        Logger::getInstance()->debug('İmza verileri:', [
            'filename' => $filename,
            'certificate' => $certificateNo,
            'signature_groups' => json_decode($record['signature_groups'], true),
            'current_group' => $record['current_group'],
            'group_signatures' => json_decode($record['group_signatures'], true),
            'group_status' => json_decode($record['group_status'], true)
        ]);

        // İmza yetkisi kontrolü
        if ($signatureManager->checkSignaturePermission($filename, $certificateNo)) {
            // Base URL'i belirle
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://");
            $host = $_SERVER['HTTP_HOST'];

            // Callback URL'i oluştur
            $callbackUrl = $protocol . $host . dirname($_SERVER['PHP_SELF']) . "/complete_signature.php";

            // Dosya URL'i oluştur
            $fileUrl = $protocol . $host . dirname($_SERVER['PHP_SELF']) . "/uploads/" . $filename;

            // İmza isteği hazırla
            $request = [
                'resources' => [
                    [
                        'source' => $fileUrl,
                        'format' => 'PadesBes',
                        'pdfOptions' => [
                            'x' => 10,
                            'y' => 10,
                            'width' => 190,
                            'height' => 50,
                            'signatureName' => 'Elektronik İmza',
                            'reason' => 'Belge İmzalama',
                            'location' => 'Türkiye'
                        ]
                    ]
                ],
                'responseUrl' => $callbackUrl
            ];

            // İmza URL'i oluştur
            $signUrl = 'sign://?xsjson=' . base64_encode(json_encode($request));

            // Modal içeriğini hazırla
?>
            <div class="modal fade" id="signModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-signature me-2"></i>
                                İmza İşlemi
                            </h5>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <h6>İmza işlemi başlatılıyor...</h6>
                            <p class="text-muted small">İmzalama uygulaması otomatik olarak açılacaktır.</p>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Modal'ı göster
                    const modal = new bootstrap.Modal(document.getElementById('signModal'));
                    modal.show();

                    // 1 saniye sonra imzalama protokolünü başlat
                    setTimeout(() => {
                        window.location.href = '<?php echo $signUrl; ?>';
                    }, 1000);
                });
            </script>
<?php

            $success = 'İmza işlemi başlatılıyor...';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// İmza listesini al
$signatures = $signatureManager->getRecentSignatures(50);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İmza Bekleyen Belgeler - PDF İmzalama Sistemi</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            min-width: 100px;
        }

        .table> :not(caption)>*>* {
            vertical-align: middle;
        }

        .bg-indigo {
            background-color: #6610f2;
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
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="sign_document.php">
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
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <h1 class="mb-4">
            <i class="fas fa-file-signature me-2"></i>
            İmza Bekleyen Belgeler
        </h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Belge Adı</th>
                                <th>Yükleme Tarihi</th>
                                <th>İmza Durumu</th>
                                <th>İmza Tipi</th>
                                <th>Mevcut Grup</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($signatures as $signature): ?>
                                <?php
                                $groups = json_decode($signature['signature_groups'], true);
                                $groupStatus = json_decode($signature['group_status'], true);
                                $currentGroup = $signature['current_group'];
                                ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-pdf text-danger me-2"></i>
                                        <?= htmlspecialchars($signature['original_filename']) ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-calendar-alt text-muted me-2"></i>
                                        <?= date('d.m.Y H:i', strtotime($signature['created_at'])) ?>
                                    </td>
                                    <td>
                                        <?php if ($signature['status'] === 'completed'): ?>
                                            <span class="badge bg-success status-badge">
                                                <i class="fas fa-check me-1"></i>
                                                Tamamlandı
                                            </span>
                                        <?php elseif ($signature['status'] === 'failed'): ?>
                                            <span class="badge bg-danger status-badge">
                                                <i class="fas fa-times me-1"></i>
                                                Başarısız
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-primary status-badge">
                                                <i class="fas fa-clock me-1"></i>
                                                Bekliyor
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $signatureType = 'Belirsiz';
                                        $iconType = 'minus';
                                        $badgeClass = 'bg-secondary';
                                        $totalSigners = 0;

                                        if (!empty($groups)) {
                                            $totalGroups = count($groups);
                                            foreach ($groups as $group) {
                                                $totalSigners += count($group['signers']);
                                            }

                                            switch ($signature['signature_type']) {
                                                case 'chain':
                                                    $signatureType = 'Zincir İmza';
                                                    $iconType = 'link';
                                                    $badgeClass = 'bg-indigo';
                                                    break;
                                                case 'parallel':
                                                    $signatureType = 'Paralel İmza';
                                                    $iconType = 'users';
                                                    $badgeClass = 'bg-dark';
                                                    break;
                                                case 'mixed':
                                                    $signatureType = 'Karışık İmza';
                                                    $iconType = 'random';
                                                    $badgeClass = 'bg-primary';
                                                    break;
                                                default:
                                                    $signatureType = 'Tek İmza';
                                                    $iconType = 'user';
                                                    $badgeClass = 'bg-primary';
                                            }
                                        }
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <i class="fas fa-<?= $iconType ?> me-1"></i>
                                            <?= $signatureType ?>
                                        </span>
                                        <?php if ($totalSigners > 0): ?>
                                            <span class="badge bg-secondary ms-1" title="Toplam İmzacı">
                                                <i class="fas fa-users me-1"></i>
                                                <?= $totalSigners ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($groups)): ?>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <?php foreach ($groups as $index => $group): ?>
                                                    <?php
                                                    $groupNum = $index + 1;
                                                    $badgeClass = 'bg-danger'; // Varsayılan: Bekleyen (kırmızı)

                                                    if ($groupStatus[$groupNum] === 'completed') {
                                                        $badgeClass = 'bg-success'; // Tamamlandı (yeşil)
                                                    } elseif ($groupNum === $currentGroup) {
                                                        if ($signature['signature_type'] === 'mixed') {
                                                            $badgeClass = 'bg-primary'; // Karışık imzada aktif grup (mavi)
                                                        } else {
                                                            $badgeClass = 'bg-primary'; // Normal aktif grup (sarı)
                                                        }
                                                    }
                                                    ?>
                                                    <span class="badge <?= $badgeClass ?>">
                                                        <?php
                                                        $groupPrefix = "Grup";
                                                        switch ($signature['signature_type']) {
                                                            case 'parallel':
                                                                $groupPrefix = "Paralel Grup";
                                                                break;
                                                            case 'mixed':
                                                                $groupPrefix = "Karma Grup";
                                                                break;
                                                        }
                                                        echo $groupPrefix . " " . $groupNum;
                                                        if ($groupStatus[$groupNum] === 'completed') {
                                                            echo ' <i class="fas fa-check"></i>';
                                                        } elseif ($groupNum === $currentGroup) {
                                                            echo ' <i class="fas fa-clock"></i>';
                                                        }
                                                        ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($signature['status'] === 'pending'): ?>
                                            <button type="button"
                                                class="btn btn-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#signModal<?= $signature['id'] ?>">
                                                <i class="fas fa-signature me-1"></i>
                                                İmzala
                                            </button>

                                            <!-- İmzalama Modal -->
                                            <div class="modal fade" id="signModal<?= $signature['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                <i class="fas fa-file-signature me-2"></i>
                                                                Belge İmzala
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <strong>Belge:</strong>
                                                                <?= htmlspecialchars($signature['original_filename']) ?>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>İmza Tipi:</strong>
                                                                <?php
                                                                $tipAciklama = '';
                                                                switch ($signature['signature_type']) {
                                                                    case 'chain':
                                                                        $tipAciklama = 'Zincir İmza (gruplar sırayla imzalar)';
                                                                        break;
                                                                    case 'parallel':
                                                                        $tipAciklama = 'Paralel İmza (tüm gruplar aynı anda imzalar)';
                                                                        break;
                                                                    case 'mixed':
                                                                        $tipAciklama = 'Karışık İmza (gruplar sıralı, grup içi paralel imza)';
                                                                        break;
                                                                }
                                                                echo $tipAciklama;
                                                                ?>
                                                            </div>
                                                            <div class="mb-3">
                                                                <strong>Mevcut Grup:</strong>
                                                                <?= $currentGroup ?>
                                                                <?php if ($signature['signature_type'] === 'mixed'): ?>
                                                                    <small class="text-muted d-block">Bu gruptaki tüm imzacılar aynı anda imzalayabilir.</small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <form method="post">
                                                                <input type="hidden" name="filename"
                                                                    value="<?= htmlspecialchars($signature['filename']) ?>">
                                                                <div class="mb-3">
                                                                    <label for="certificate_no" class="form-label">
                                                                        TC Kimlik No
                                                                    </label>
                                                                    <input type="text" class="form-control"
                                                                        id="certificate_no" name="certificate_no" required>
                                                                </div>
                                                                <button type="submit" name="sign" class="btn btn-primary">
                                                                    <i class="fas fa-signature me-1"></i>
                                                                    İmzala
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-signature me-1"></i>
                                                İmzala
                                            </button>
                                        <?php endif; ?>

                                        <a href="verify.php?file=<?= urlencode($signature['filename']) ?>"
                                            class="btn btn-info btn-sm">
                                            <i class="fas fa-search me-1"></i>
                                            Detay
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="test_multi_signature.php" class="btn btn-outline-primary">
                <i class="fas fa-plus me-1"></i>
                Yeni İmza Süreci Başlat
            </a>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>