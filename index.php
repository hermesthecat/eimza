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
                                <input type="text" id="single-pdf" class="form-control" value="<?php echo htmlspecialchars($pdfUrl); ?>">
                            </div>

                            <div id="file-input-single" class="form-group mb-3" style="display:none;">
                                <label class="form-label" for="single-pdf-file">PDF Dosyası:</label>
                                <input type="file" id="single-pdf-file" class="form-control" accept=".pdf">
                                <div class="upload-progress mt-2" style="display:none;">
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"></div>
                                    </div>
                                    <small class="text-muted progress-text mt-1 d-block">Yükleniyor... 0%</small>
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
                                    <div class="input-group">
                                        <input type="file" name="pdf_files[]" class="form-control" accept=".pdf">
                                        <button type="button" class="btn btn-danger" onclick="this.parentElement.parentElement.remove()">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                <div class="input-group">
                    <input type="file" name="pdf_files[]" class="form-control" accept=".pdf">
                    <button type="button" class="btn btn-danger" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-trash"></i>
                    </button>
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
    </script>
</body>

</html>