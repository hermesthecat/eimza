<?php
require_once '../config.php';
require_once '../includes/logger.php';
require_once '../includes/SignatureManager.php';
require_once 'auth.php';

// Yetkilendirme kontrolü
requireAdmin();

// Generate CSRF token for forms
$csrf_token = generateCsrfToken();

// Initialize signature manager
$signatureManager = new SignatureManager($db, Logger::getInstance());

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get signatures with pagination
$signatures = $signatureManager->getRecentSignatures($perPage, $offset);
$totalSignatures = $signatureManager->getTotalSignatures();
$totalPages = ceil($totalSignatures / $perPage);
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İmza Kayıtları - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.85em;
        }

        .group-status {
            border-left: 3px solid;
            padding-left: 10px;
            margin: 5px 0;
        }

        .group-status.pending {
            border-color: #ffc107;
        }

        .group-status.completed {
            border-color: #198754;
        }

        .tab-content {
            padding: 20px 0;
        }
        .bg-indigo {
            background-color: #6610f2;
        }
    </style>
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-lock me-2"></i>Admin Paneli</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user me-2"></i><?= htmlspecialchars(getAdminUsername()) ?>
                        </span>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Çıkış
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="card shadow">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-file-signature me-2"></i>İmza Kayıtları
                </h4>
                <span class="badge bg-primary">
                    Toplam: <?= number_format($totalSignatures) ?> kayıt
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="signaturesTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Dosya Adı</th>
                                <th>İmza Tipi</th>
                                <th>Format</th>
                                <th>İmza Durumu</th>
                                <th>Grup Durumu</th>
                                <th>Tarih</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($signatures as $signature): ?>
                                <tr>
                                    <td><?= htmlspecialchars($signature['id']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($signature['original_filename']) ?>
                                        <?php if ($signature['status'] === 'completed'): ?>
                                            <a href="../uploads/<?= htmlspecialchars($signature['filename']) ?>"
                                                target="_blank" class="text-primary ms-2">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $signatureType = $signature['signature_type'] ?? 'single';
                                        $typeInfo = [
                                            'chain' => ['text' => 'Zincir İmza', 'class' => 'indigo', 'icon' => 'link'],
                                            'parallel' => ['text' => 'Paralel İmza', 'class' => 'dark', 'icon' => 'users'],
                                            'mixed' => ['text' => 'Karışık İmza', 'class' => 'primary', 'icon' => 'random'],
                                            'single' => ['text' => 'Tekli İmza', 'class' => 'secondary', 'icon' => 'user']
                                        ][$signatureType];
                                        ?>
                                        <span class="badge bg-<?= $typeInfo['class'] ?>">
                                            <i class="fas fa-<?= $typeInfo['icon'] ?> me-1"></i>
                                            <?= $typeInfo['text'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($signature['signature_format']) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'pending' => 'primary',
                                            'completed' => 'success',
                                            'failed' => 'danger'
                                        ][$signature['status']];
                                        $statusText = [
                                            'pending' => 'Bekliyor',
                                            'completed' => 'Tamamlandı',
                                            'failed' => 'Başarısız'
                                        ][$signature['status']];
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($signature['signature_groups'])) {
                                            $groups = json_decode($signature['signature_groups'], true);
                                            $groupStatus = json_decode($signature['group_status'], true);
                                            $currentGroup = $signature['current_group'];

                                            foreach ($groups as $index => $group) {
                                                $groupNum = $index + 1;
                                                $status = $groupStatus[$groupNum] ?? 'Bekliyor';
                                        ?>
                                                <div class="group-status <?= $status ?>">
                                                    Grup <?= $groupNum ?>:
                                                    <?php
                                                    // Grup durumuna göre badge rengini belirle
                                                    $badgeClass = 'danger'; // Varsayılan: Kırmızı (bekleyen gruplar için)
                                                    $statusText = 'Bekliyor';
                                                    
                                                    if ($status === 'completed') {
                                                        $badgeClass = 'success'; // Yeşil (tamamlanan gruplar)
                                                        $statusText = 'Tamamlandı';
                                                    } elseif ($groupNum === $currentGroup) {
                                                        $badgeClass = 'primary'; // Mavi (aktif grup)
                                                        $statusText = 'Aktif Grup';
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?= $badgeClass ?>">
                                                        <?= $statusText ?>
                                                    </span>
                                                </div>
                                        <?php
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?= date('d.m.Y H:i', strtotime($signature['created_at'])) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info"
                                            onclick="showDetails(<?= htmlspecialchars(json_encode($signature)) ?>)">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                        <?php if ($signature['status'] === 'failed'): ?>
                                            <form method="POST" action="retry.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($signature['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="İmza kayıtları sayfaları">
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">İmza Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#generalInfo">
                                Genel Bilgiler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#groupInfo">
                                Grup Bilgileri
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#signatureInfo">
                                İmza Bilgileri
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content mt-3">
                        <!-- Genel Bilgiler -->
                        <div class="tab-pane fade show active" id="generalInfo">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Dosya Bilgileri</h6>
                                    <dl>
                                        <dt>Orijinal Dosya Adı</dt>
                                        <dd id="origFilename">-</dd>
                                        <dt>Dosya Boyutu</dt>
                                        <dd id="fileSize">-</dd>
                                        <dt>İmza Formatı</dt>
                                        <dd id="sigFormat">-</dd>
                                        <dt>IP Adresi</dt>
                                        <dd id="ipAddress">-</dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <h6>İmza Durumu</h6>
                                    <dl>
                                        <dt>İmza Tipi</dt>
                                        <dd id="signatureType">-</dd>
                                        <dt>Genel Durum</dt>
                                        <dd id="generalStatus">-</dd>
                                        <dt>Son Güncelleme</dt>
                                        <dd id="lastUpdate">-</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <!-- Grup Bilgileri -->
                        <div class="tab-pane fade" id="groupInfo">
                            <div id="groupsContainer">
                                <!-- Grup bilgileri dinamik olarak eklenecek -->
                            </div>
                        </div>

                        <!-- İmza Bilgileri -->
                        <div class="tab-pane fade" id="signatureInfo">
                            <div id="signaturesContainer">
                                <!-- İmza bilgileri dinamik olarak eklenecek -->
                            </div>
                        </div>
                    </div>

                    <div id="errorMessage" class="alert alert-danger d-none mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#signaturesTable').DataTable({
                order: [
                    [0, 'desc']
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
                },
                pageLength: <?= $perPage ?>,
                searching: true,
                stateSave: true
            });
        });

        function showDetails(signature) {
            // Genel bilgileri güncelle
            $('#origFilename').text(signature.original_filename);
            $('#fileSize').text(formatFileSize(signature.file_size));
            $('#sigFormat').text(signature.signature_format);
            $('#ipAddress').text(signature.ip_address);

            // İmza tipi ve durumu
            const typeMap = {
                'chain': 'Zincir İmza (gruplar sırayla imzalar)',
                'parallel': 'Paralel İmza (tüm gruplar aynı anda imzalar)',
                'mixed': 'Karışık İmza (gruplar sıralı, grup içi paralel)',
                'single': 'Tekli İmza'
            };
            $('#signatureType').text(typeMap[signature.signature_type || 'single']);

            const statusText = {
                'pending': 'Bekliyor',
                'completed': 'Tamamlandı',
                'failed': 'Başarısız'
            } [signature.status];
            $('#generalStatus').text(statusText);
            $('#lastUpdate').text(new Date(signature.created_at).toLocaleString('tr-TR'));

            // Grup bilgilerini güncelle
            if (signature.signature_groups) {
                const groups = JSON.parse(signature.signature_groups);
                const groupStatus = JSON.parse(signature.group_status);
                const groupSignatures = JSON.parse(signature.group_signatures);
                let groupsHtml = '';

                groups.forEach((group, index) => {
                    const groupNum = index + 1;
                    const status = groupStatus[groupNum];
                    const signatures = groupSignatures[groupNum] || [];

                    groupsHtml += `
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    ${signature.signature_type === 'parallel' ? 'Paralel' :
                                      signature.signature_type === 'mixed' ? 'Karma' : ''}
                                    Grup ${groupNum}
                                </h6>
                                <span class="badge bg-${status === 'completed' ? 'success' : 'primary'}">
                                    ${status === 'completed' ? 'Tamamlandı' : 'Bekliyor'}
                                </span>
                            </div>
                            <div class="card-body">
                                <h6>İmzacılar:</h6>
                                <ul class="list-unstyled">
                                    ${group.signers.map(signer => {
                                        const signed = signatures.find(s => s.certificateSerialNumber === signer);
                                        return `
                                            <li class="mb-2">
                                                <i class="fas ${signed ? 'fa-check-circle text-success' : 'fa-circle text-primary'}"></i>
                                                TC: ${signer}
                                                ${signed ? `
                                                    <br>
                                                    <small class="text-muted">
                                                        İmzalayan: ${signed.certificateName}<br>
                                                        Tarih: ${new Date(signed.signatureDate).toLocaleString('tr-TR')}
                                                    </small>
                                                ` : ''}
                                            </li>
                                        `;
                                    }).join('')}
                                </ul>
                            </div>
                        </div>
                    `;
                });

                $('#groupsContainer').html(groupsHtml);
            } else {
                $('#groupsContainer').html('<p class="text-muted">Bu dosya için grup bilgisi bulunmuyor.</p>');
            }

            // İmza geçmişini güncelle
            if (signature.signature_groups) {
                const groupSignatures = JSON.parse(signature.group_signatures);
                let signaturesHtml = '';

                Object.values(groupSignatures).flat().forEach((sig, index) => {
                    signaturesHtml += `
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6>${index + 1}. İmza</h6>
                                <dl class="mb-0">
                                    <dt>İmzalayan</dt>
                                    <dd>${sig.certificateName}</dd>
                                    <dt>Sertifika No</dt>
                                    <dd>${sig.certificateSerialNumber}</dd>
                                    <dt>Tarih</dt>
                                    <dd>${new Date(sig.signatureDate).toLocaleString('tr-TR')}</dd>
                                </dl>
                            </div>
                        </div>
                    `;
                });

                $('#signaturesContainer').html(signaturesHtml || '<p class="text-muted">Henüz imza bilgisi bulunmuyor.</p>');
            } else {
                $('#signaturesContainer').html('<p class="text-muted">Bu dosya için imza bilgisi bulunmuyor.</p>');
            }

            // Hata mesajı
            if (signature.status === 'failed') {
                $('#errorMessage')
                    .text(signature.error_message)
                    .removeClass('d-none');
            } else {
                $('#errorMessage').addClass('d-none');
            }

            // Modal'ı göster
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }

        function formatFileSize(bytes) {
            if (!bytes) return '-';
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`;
        }
    </script>
</body>

</html>