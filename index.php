<?php

/**
 * KolayImza Web Arayüzü
 * @author A. Kerem Gök
 */

require_once 'kolayimza.php';

// KolayImza örneği oluştur
$kolayImza = new KolayImza();

// İmza geçmişi görüntüleme
$selectedId = isset($_GET['history_id']) ? (int)$_GET['history_id'] : null;
$history = $selectedId ? $kolayImza->getSignHistory($selectedId) : null;

// Grup detayı görüntüleme
$selectedGroupId = isset($_GET['group_detail']) ? (int)$_GET['group_detail'] : null;
$groupDocuments = $selectedGroupId ? $kolayImza->listGroupDocuments($selectedGroupId) : null;

// Varsayılan URL'ler
$pdfUrl = "http://example.com/belge.pdf";
$responseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
    $_SERVER['HTTP_HOST'] .
    dirname($_SERVER['PHP_SELF']) . '/kolayimza.php';
?>
<!DOCTYPE html>
<html>

<head>
    <title>KolayImza PDF İmzalama</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-file-signature me-2"></i>
                KolayImza
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tekli" type="button">
                            <i class="fas fa-file-pdf me-2"></i>Tekli İmzalama
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#coklu" type="button">
                            <i class="fas fa-files me-2"></i>Çoklu İmzalama
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#gruplar" type="button">
                            <i class="fas fa-folder me-2"></i>İmza Grupları
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#toplu-islemler" type="button">
                            <i class="fas fa-history me-2"></i>Toplu İşlem Geçmişi
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tekli">
                        <div class="mb-4">
                            <div class="form-group mb-3">
                                <label class="form-label">PDF Seçeneği:</label>
                                <select id="pdf-source-single" class="form-select" onchange="togglePdfSource('single')">
                                    <option value="url">URL Gir</option>
                                    <option value="file">Dosya Yükle</option>
                                </select>
                            </div>

                            <div id="url-input-single" class="form-group mb-3">
                                <label class="form-label" for="single-pdf">PDF URL'i:</label>
                                <div class="input-group">
                                    <input type="text" id="single-pdf" class="form-control" value="<?php echo htmlspecialchars($pdfUrl); ?>">
                                    <button class="btn btn-secondary" type="button" onclick="showPdfPreview(document.getElementById('single-pdf').value)">
                                        <i class="fas fa-eye"></i> Önizle
                                    </button>
                                </div>
                            </div>

                            <div id="file-input-single" class="form-group mb-3" style="display:none;">
                                <label class="form-label" for="single-pdf-file">PDF Dosyası:</label>
                                <div class="drop-zone">
                                    <div class="drop-zone-content">
                                        <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                                        <p class="mb-2">PDF dosyasını sürükleyip bırakın</p>
                                        <p class="text-muted small">veya</p>
                                        <input type="file" id="single-pdf-file" class="form-control" accept=".pdf" onchange="handleFileSelect(this)">
                                    </div>
                                </div>
                                <div class="upload-progress mt-2" style="display:none;">
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"></div>
                                    </div>
                                    <small class="text-muted progress-text mt-1 d-block">Yükleniyor... 0%</small>
                                </div>
                            </div>

                            <div id="pdf-preview" class="mb-3" style="display:none;">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">PDF Önizleme</h6>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="changePage(-1)">
                                                <i class="fas fa-chevron-left"></i>
                                            </button>
                                            <span class="btn btn-sm btn-outline-secondary disabled" id="page-info">
                                                Sayfa <span id="page-num">1</span> / <span id="page-count">1</span>
                                            </span>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="changePage(1)">
                                                <i class="fas fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <canvas id="pdf-canvas" class="w-100"></canvas>
                                    </div>
                                </div>
                            </div>

                            <button class="btn btn-primary" onclick="handleSingleSign()">
                                <i class="fas fa-signature me-2"></i>PDF'i İmzala
                            </button>
                        </div>

                        <h5 class="mb-3">Tekli İmza Kayıtları</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Belge URL</th>
                                        <th>İmzalayan</th>
                                        <th>Kurum</th>
                                        <th>İmza Zamanı</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kolayImza->listSignRecords() as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                                            <td class="text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($record['belge_url']); ?></td>
                                            <td><?php echo htmlspecialchars($record['sertifika_sahibi']); ?></td>
                                            <td><?php echo htmlspecialchars($record['sertifika_kurumu']); ?></td>
                                            <td><?php echo htmlspecialchars($record['imza_zamani']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $record['durum'] === 'imzalandi' ? 'bg-success' : ($record['durum'] === 'hata' ? 'bg-danger' : 'bg-warning'); ?>">
                                                    <?php echo htmlspecialchars($record['durum']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?history_id=<?php echo $record['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-history"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="coklu">
                        <form id="multiSignForm" class="mb-4">
                            <div class="form-group mb-3">
                                <label class="form-label">PDF Ekleme Yöntemi:</label>
                                <select id="pdf-source-multi" class="form-select" onchange="togglePdfSource('multi')">
                                    <option value="url">URL Gir</option>
                                    <option value="file">Dosya Yükle</option>
                                </select>
                            </div>

                            <div id="pdfUrls">
                                <div class="pdf-input input-group mb-3">
                                    <input type="text" name="pdf_urls[]" class="form-control" placeholder="PDF URL'i" required>
                                    <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <div id="pdfFiles" style="display:none;">
                                <div class="pdf-file-input mb-3">
                                    <div class="drop-zone">
                                        <div class="drop-zone-content">
                                            <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                                            <p class="mb-2">PDF dosyalarını sürükleyip bırakın</p>
                                            <p class="text-muted small">veya</p>
                                            <input type="file" name="pdf_files[]" class="form-control" accept=".pdf" multiple>
                                        </div>
                                    </div>
                                    <div class="upload-progress mt-2" style="display:none;">
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"></div>
                                        </div>
                                        <small class="text-muted progress-text mt-1 d-block">Yükleniyor... 0%</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label" for="grup-adi">Grup Adı:</label>
                                <input type="text" id="grup-adi" name="grup_adi" class="form-control" placeholder="Grup Adı (Opsiyonel)">
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label" for="aciklama">Açıklama:</label>
                                <textarea id="aciklama" name="aciklama" class="form-control" placeholder="Açıklama (Opsiyonel)" rows="3"></textarea>
                            </div>

                            <div class="alert alert-danger" id="errorMessage" style="display:none;"></div>

                            <div class="btn-group">
                                <button type="button" class="btn btn-success" onclick="addInput()">
                                    <i class="fas fa-plus me-2"></i>PDF Ekle
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-signature me-2"></i>Toplu İmzala
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="gruplar">
                        <h5 class="mb-3">İmza Grupları</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Grup Adı</th>
                                        <th>Toplam Belge</th>
                                        <th>İmzalanan</th>
                                        <th>Durum</th>
                                        <th>Oluşturma Zamanı</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kolayImza->listSignGroups() as $group): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($group['id']); ?></td>
                                            <td><?php echo htmlspecialchars($group['grup_adi']); ?></td>
                                            <td><?php echo htmlspecialchars($group['toplam_belge']); ?></td>
                                            <td><?php echo htmlspecialchars($group['imzalanan_belge']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                                        echo $group['durum'] === 'tamamlandi' ? 'success' : ($group['durum'] === 'bekliyor' ? 'warning' : ($group['durum'] === 'kismen_tamamlandi' ? 'info' : 'danger'));
                                                                        ?>">
                                                    <?php echo htmlspecialchars($group['durum']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($group['olusturma_zamani']); ?></td>
                                            <td>
                                                <a href="?group_detail=<?php echo $group['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-info-circle"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="toplu-islemler">
                        <h5 class="mb-3">Toplu İşlem Geçmişi</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>İşlem Tipi</th>
                                        <th>Belge Sayısı</th>
                                        <th>Başarılı</th>
                                        <th>Hatalı</th>
                                        <th>Başlama</th>
                                        <th>Bitiş</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kolayImza->topluIslemGecmisiListele() as $islem): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($islem['id']); ?></td>
                                            <td>
                                                <?php
                                                $islemTipi = [
                                                    'tekli' => '<i class="fas fa-file-signature"></i> Tekli',
                                                    'coklu' => '<i class="fas fa-files"></i> Çoklu',
                                                    'grup' => '<i class="fas fa-folder"></i> Grup'
                                                ][$islem['islem_tipi']] ?? $islem['islem_tipi'];
                                                echo $islemTipi;
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($islem['belge_sayisi']); ?></td>
                                            <td>
                                                <span class="text-success">
                                                    <?php echo htmlspecialchars($islem['basarili_sayisi']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-danger">
                                                    <?php echo htmlspecialchars($islem['hatali_sayisi']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($islem['baslama_zamani']); ?></td>
                                            <td><?php echo $islem['bitis_zamani'] ? htmlspecialchars($islem['bitis_zamani']) : '-'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                                        echo $islem['durum'] === 'tamamlandi' ? 'success' : ($islem['durum'] === 'devam_ediyor' ? 'warning' : ($islem['durum'] === 'hata' ? 'danger' : 'secondary'));
                                                                        ?>">
                                                    <?php echo htmlspecialchars($islem['durum']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info"
                                                    onclick="showTopluIslemDetay(<?php echo $islem['id']; ?>)">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($history): ?>
        <div class="modal fade show" id="historyModal" tabindex="-1" style="display: block;">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">İmza Geçmişi</h5>
                        <button type="button" class="btn-close" onclick="document.getElementById('historyModal').style.display='none'"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>İşlem</th>
                                        <th>Açıklama</th>
                                        <th>IP Adresi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $log): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($log['olusturma_zamani']); ?></td>
                                            <td><?php echo htmlspecialchars($log['islem_tipi']); ?></td>
                                            <td><?php echo htmlspecialchars($log['aciklama']); ?></td>
                                            <td><?php echo htmlspecialchars($log['ip_adresi']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <?php if ($groupDocuments): ?>
        <div class="modal fade show" id="groupDetailModal" tabindex="-1" style="display: block;">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Grup Belgeleri</h5>
                        <button type="button" class="btn-close" onclick="document.getElementById('groupDetailModal').style.display='none'"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sıra</th>
                                        <th>Belge URL</th>
                                        <th>İmzalayan</th>
                                        <th>İmza Zamanı</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groupDocuments as $doc): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($doc['sira_no'] + 1); ?></td>
                                            <td class="text-truncate" style="max-width: 200px;">
                                                <?php echo htmlspecialchars($doc['belge_url']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($doc['sertifika_sahibi']); ?></td>
                                            <td><?php echo htmlspecialchars($doc['imza_zamani']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $doc['durum'] === 'imzalandi' ? 'success' : ($doc['durum'] === 'hata' ? 'danger' : 'warning'); ?>">
                                                    <?php echo htmlspecialchars($doc['durum']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }

        function togglePdfSource(type) {
            const source = document.getElementById(`pdf-source-${type}`).value;
            if (type === 'single') {
                document.getElementById('url-input-single').style.display = source === 'url' ? 'block' : 'none';
                document.getElementById('file-input-single').style.display = source === 'file' ? 'block' : 'none';
            } else {
                document.getElementById('pdfUrls').style.display = source === 'url' ? 'block' : 'none';
                document.getElementById('pdfFiles').style.display = source === 'file' ? 'block' : 'none';
            }
        }

        function addInput() {
            const source = document.getElementById('pdf-source-multi').value;
            if (source === 'url') {
                addPdfInput();
            } else {
                addFileInput();
            }
        }

        function addPdfInput() {
            const div = document.createElement('div');
            div.className = 'pdf-input input-group mb-3';
            div.innerHTML = `
                <input type="text" name="pdf_urls[]" class="form-control" placeholder="PDF URL'i" required>
                <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            document.getElementById('pdfUrls').appendChild(div);
        }

        function addFileInput() {
            const div = document.createElement('div');
            div.className = 'pdf-file-input mb-3';
            div.innerHTML = `
                <div class="drop-zone">
                    <div class="drop-zone-content">
                        <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                        <p class="mb-2">PDF dosyalarını sürükleyip bırakın</p>
                        <p class="text-muted small">veya</p>
                        <input type="file" name="pdf_files[]" class="form-control" accept=".pdf" multiple>
                    </div>
                </div>
                <div class="upload-progress mt-2" style="display:none;">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"></div>
                    </div>
                    <small class="text-muted progress-text mt-1 d-block">Yükleniyor... 0%</small>
                </div>
            `;
            document.getElementById('pdfFiles').appendChild(div);
        }

        async function uploadFile(file, progressElement) {
            const formData = new FormData();
            formData.append('pdf_file', file);

            try {
                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData,
                    onUploadProgress: (progressEvent) => {
                        const progress = (progressEvent.loaded / progressEvent.total) * 100;
                        progressElement.querySelector('.progress-bar').style.width = progress + '%';
                        progressElement.querySelector('.progress-text').textContent = `Yükleniyor... ${Math.round(progress)}%`;
                    }
                });

                const result = await response.json();
                if (result.success) {
                    return result.url;
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                throw new Error('Dosya yükleme hatası: ' + error.message);
            }
        }

        async function handleSingleSign() {
            const source = document.getElementById('pdf-source-single').value;
            let pdfUrl;

            try {
                if (source === 'url') {
                    pdfUrl = document.getElementById('single-pdf').value;
                } else {
                    const fileInput = document.getElementById('single-pdf-file');
                    if (!fileInput.files.length) {
                        throw new Error('Lütfen bir PDF dosyası seçin');
                    }

                    const progressElement = fileInput.parentElement.querySelector('.upload-progress');
                    progressElement.style.display = 'block';

                    pdfUrl = await uploadFile(fileInput.files[0], progressElement);
                }

                const response = await fetch('kolayimza.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `pdf_url=${encodeURIComponent(pdfUrl)}`
                });

                const data = await response.json();
                if (data.success && data.signUrl) {
                    window.location.href = data.signUrl;
                } else {
                    throw new Error(data.message || 'Bir hata oluştu');
                }
            } catch (error) {
                const errorDiv = document.getElementById('errorMessage');
                errorDiv.textContent = error.message;
                errorDiv.style.display = 'block';
            }
        }

        document.getElementById('multiSignForm').onsubmit = async function(e) {
            e.preventDefault();
            const formData = new FormData();
            const source = document.getElementById('pdf-source-multi').value;
            const errorDiv = document.getElementById('errorMessage');

            try {
                if (source === 'url') {
                    const urls = Array.from(this.querySelectorAll('input[name="pdf_urls[]"]')).map(input => input.value);
                    urls.forEach(url => formData.append('pdf_urls[]', url));
                } else {
                    const fileInputs = this.querySelectorAll('input[type="file"]');
                    for (const input of fileInputs) {
                        if (input.files.length) {
                            const progressElement = input.parentElement.querySelector('.upload-progress');
                            progressElement.style.display = 'block';
                            const url = await uploadFile(input.files[0], progressElement);
                            formData.append('pdf_urls[]', url);
                        }
                    }
                }

                formData.append('grup_adi', this.querySelector('[name="grup_adi"]').value);
                formData.append('aciklama', this.querySelector('[name="aciklama"]').value);

                const response = await fetch('kolayimza.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success && data.signUrl) {
                    window.location.href = data.signUrl;
                } else {
                    throw new Error(data.message || 'Bir hata oluştu');
                }
            } catch (error) {
                errorDiv.textContent = error.message;
                errorDiv.style.display = 'block';
            }
        };

        // Sürükle-Bırak işlemleri
        function setupDragDrop() {
            const dropZones = document.querySelectorAll('.drop-zone');

            dropZones.forEach(zone => {
                zone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    zone.classList.add('drag-over');
                });

                zone.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    zone.classList.remove('drag-over');
                });

                zone.addEventListener('drop', async (e) => {
                    e.preventDefault();
                    zone.classList.remove('drag-over');

                    const files = Array.from(e.dataTransfer.files).filter(file =>
                        file.type === 'application/pdf' ||
                        file.name.toLowerCase().endsWith('.pdf')
                    );

                    if (files.length === 0) {
                        showError('Lütfen sadece PDF dosyası yükleyin');
                        return;
                    }

                    // Tekli veya çoklu yükleme kontrolü
                    const isMultiple = zone.closest('#pdfFiles') !== null;

                    if (isMultiple) {
                        handleMultipleFiles(files, zone);
                    } else {
                        handleSingleFile(files[0], zone);
                    }
                });

                // Dosya seçici için de aynı işlemi yap
                const fileInput = zone.querySelector('input[type="file"]');
                fileInput.addEventListener('change', (e) => {
                    const files = Array.from(e.target.files).filter(file =>
                        file.type === 'application/pdf' ||
                        file.name.toLowerCase().endsWith('.pdf')
                    );

                    if (files.length === 0) {
                        showError('Lütfen sadece PDF dosyası yükleyin');
                        return;
                    }

                    const isMultiple = zone.closest('#pdfFiles') !== null;

                    if (isMultiple) {
                        handleMultipleFiles(files, zone);
                    } else {
                        handleSingleFile(files[0], zone);
                    }
                });
            });
        }

        function handleSingleFile(file, zone) {
            const progressElement = zone.parentElement.querySelector('.upload-progress');
            progressElement.style.display = 'block';

            uploadFile(file, progressElement).then(url => {
                // URL'i gizli input'a kaydet
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'pdf_url';
                hiddenInput.value = url;
                zone.appendChild(hiddenInput);

                // Başarılı yükleme göstergesi
                showSuccess(zone, file.name);
            }).catch(error => {
                showError(error.message);
                progressElement.style.display = 'none';
            });
        }

        function handleMultipleFiles(files, zone) {
            const container = zone.closest('.pdf-file-input');
            const progressElement = container.querySelector('.upload-progress');

            // Tüm dosyaları yükle
            Promise.all(files.map(file => {
                progressElement.style.display = 'block';
                return uploadFile(file, progressElement);
            })).then(urls => {
                // URL'leri gizli input'lara kaydet
                urls.forEach(url => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'pdf_urls[]';
                    hiddenInput.value = url;
                    zone.appendChild(hiddenInput);
                });

                // Başarılı yükleme göstergesi
                showSuccess(zone, `${files.length} dosya yüklendi`);
            }).catch(error => {
                showError(error.message);
                progressElement.style.display = 'none';
            });
        }

        function showSuccess(zone, message) {
            const content = zone.querySelector('.drop-zone-content');
            content.innerHTML = `
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <p class="text-success mb-0">${message}</p>
            `;
            zone.classList.add('success');
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';

            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }

        // Sayfa yüklendiğinde sürükle-bırak özelliğini aktifleştir
        document.addEventListener('DOMContentLoaded', setupDragDrop);

        async function showTopluIslemDetay(islemId) {
            try {
                const response = await fetch(`kolayimza.php?toplu_islem_detay=${islemId}`);
                const data = await response.json();

                if (data.success) {
                    const islem = data.islem;

                    // Modal oluştur
                    const modal = document.createElement('div');
                    modal.className = 'modal fade show';
                    modal.id = 'topluIslemDetayModal';
                    modal.style.display = 'block';

                    // Modal içeriği
                    modal.innerHTML = `
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Toplu İşlem Detayı #${islem.id}</h5>
                                    <button type="button" class="btn-close" onclick="closeTopluIslemDetayModal()"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>İşlem Tipi:</strong> ${islem.islem_tipi}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Durum:</strong> 
                                            <span class="badge bg-${
                                                islem.durum === 'tamamlandi' ? 'success' : 
                                                (islem.durum === 'devam_ediyor' ? 'warning' : 
                                                (islem.durum === 'hata' ? 'danger' : 'secondary'))
                                            }">${islem.durum}</span>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <strong>Toplam Belge:</strong> ${islem.belge_sayisi}
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Başarılı:</strong> 
                                            <span class="text-success">${islem.basarili_sayisi}</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Hatalı:</strong> 
                                            <span class="text-danger">${islem.hatali_sayisi}</span>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Başlama:</strong> ${islem.baslama_zamani}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Bitiş:</strong> ${islem.bitis_zamani || '-'}
                                        </div>
                                    </div>
                                    ${islem.hata_mesaji ? `
                                        <div class="alert alert-danger">
                                            <strong>Hata Mesajı:</strong><br>
                                            ${islem.hata_mesaji}
                                        </div>
                                    ` : ''}
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>IP Adresi:</strong> ${islem.ip_adresi}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Oluşturma:</strong> ${islem.olusturma_zamani}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-backdrop fade show"></div>
                    `;

                    document.body.appendChild(modal);
                } else {
                    showError(data.message || 'İşlem detayı alınamadı');
                }
            } catch (error) {
                showError('İşlem detayı alınamadı: ' + error.message);
            }
        }

        function closeTopluIslemDetayModal() {
            const modal = document.getElementById('topluIslemDetayModal');
            if (modal) {
                modal.remove();
                document.querySelector('.modal-backdrop').remove();
            }
        }

        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.5;

        async function showPdfPreview(url) {
            try {
                // PDF yükleme göstergesi
                document.getElementById('pdf-preview').style.display = 'block';
                const canvas = document.getElementById('pdf-canvas');
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#f8f9fa';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#6c757d';
                ctx.font = '14px Arial';
                ctx.fillText('PDF yükleniyor...', 10, 50);

                // PDF'i yükle
                const loadingTask = pdfjsLib.getDocument(url);
                pdfDoc = await loadingTask.promise;

                // Sayfa bilgilerini güncelle
                document.getElementById('page-count').textContent = pdfDoc.numPages;
                pageNum = 1;

                // İlk sayfayı göster
                renderPage(pageNum);
            } catch (error) {
                showError('PDF yüklenemedi: ' + error.message);
            }
        }

        async function renderPage(num) {
            pageRendering = true;

            try {
                // Sayfayı al
                const page = await pdfDoc.getPage(num);

                // Canvas boyutunu ayarla
                const viewport = page.getViewport({
                    scale
                });
                const canvas = document.getElementById('pdf-canvas');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                // Sayfayı render et
                const renderContext = {
                    canvasContext: canvas.getContext('2d'),
                    viewport: viewport
                };

                await page.render(renderContext).promise;
                pageRendering = false;

                // Bekleyen sayfa varsa onu göster
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }

                // Sayfa numarasını güncelle
                document.getElementById('page-num').textContent = num;
            } catch (error) {
                pageRendering = false;
                showError('Sayfa gösterilemiyor: ' + error.message);
            }
        }

        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        function changePage(offset) {
            if (!pdfDoc) return;

            const newPage = pageNum + offset;
            if (newPage >= 1 && newPage <= pdfDoc.numPages) {
                pageNum = newPage;
                queueRenderPage(pageNum);
            }
        }

        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();

                reader.onload = function(e) {
                    showPdfPreview(e.target.result);
                };

                reader.readAsDataURL(file);
            }
        }
    </script>
</body>

</html>