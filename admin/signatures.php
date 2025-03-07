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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-lock me-2"></i>Admin Paneli
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
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
                                <th>İmzalayan</th>
                                <th>Format</th>
                                <th>Durum</th>
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
                                    <td><?= htmlspecialchars($signature['certificate_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($signature['signature_format']) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'pending' => 'warning',
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
                    <div class="row">
                        <div class="col-md-6">
                            <h6>İmza Bilgileri</h6>
                            <dl>
                                <dt>Sertifika Sahibi</dt>
                                <dd id="certName">-</dd>
                                
                                <dt>Sertifika Sağlayıcı</dt>
                                <dd id="certIssuer">-</dd>
                                
                                <dt>Seri No</dt>
                                <dd id="certSerial">-</dd>
                                
                                <dt>İmza Tarihi</dt>
                                <dd id="sigDate">-</dd>
                            </dl>
                        </div>
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
                    </div>
                    <div id="errorMessage" class="alert alert-danger d-none"></div>
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
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
                },
                pageLength: <?= $perPage ?>,
                searching: true,
                stateSave: true
            });
        });

        function showDetails(signature) {
            // Update modal content
            $('#certName').text(signature.certificate_name || '-');
            $('#certIssuer').text(signature.certificate_issuer || '-');
            $('#certSerial').text(signature.certificate_serial_number || '-');
            $('#sigDate').text(signature.signature_date ? 
                new Date(signature.signature_date).toLocaleString('tr-TR') : '-');
            
            $('#origFilename').text(signature.original_filename);
            $('#fileSize').text(formatFileSize(signature.file_size));
            $('#sigFormat').text(signature.signature_format);
            $('#ipAddress').text(signature.ip_address);

            if (signature.status === 'failed') {
                $('#errorMessage')
                    .text(signature.error_message)
                    .removeClass('d-none');
            } else {
                $('#errorMessage').addClass('d-none');
            }

            // Show modal
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