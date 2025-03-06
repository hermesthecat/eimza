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
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <h1>PDF İmzalama</h1>

    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('tekli')">Tekli İmzalama</button>
        <button class="tab-btn" onclick="showTab('coklu')">Çoklu İmzalama</button>
        <button class="tab-btn" onclick="showTab('gruplar')">İmza Grupları</button>
    </div>

    <div id="tekli" class="tab-content active">
        <div class="content-box">
            <?php
            $signUrl = $kolayImza->createSignUrl($pdfUrl, $responseUrl);
            ?>
            <div class="form-group">
                <label>PDF Seçeneği:</label>
                <select id="pdf-source-single" class="form-control" onchange="togglePdfSource('single')">
                    <option value="url">URL Gir</option>
                    <option value="file">Dosya Yükle</option>
                </select>
            </div>

            <div id="url-input-single" class="form-group">
                <label for="single-pdf">PDF URL'i:</label>
                <input type="text" id="single-pdf" value="<?php echo htmlspecialchars($pdfUrl); ?>">
            </div>

            <div id="file-input-single" class="form-group" style="display:none;">
                <label for="single-pdf-file">PDF Dosyası:</label>
                <input type="file" id="single-pdf-file" accept=".pdf">
                <div class="upload-progress" style="display:none;">
                    <div class="progress-bar"></div>
                    <span class="progress-text">Yükleniyor... 0%</span>
                </div>
            </div>

            <button class="btn imzala" onclick="handleSingleSign()">PDF'i İmzala</button>
        </div>

        <h2>Tekli İmza Kayıtları</h2>
        <div class="content-box">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Belge URL</th>
                    <th>İmzalayan</th>
                    <th>Kurum</th>
                    <th>İmza Zamanı</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                </tr>
                <?php foreach ($kolayImza->listSignRecords() as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['id']); ?></td>
                        <td><?php echo htmlspecialchars($record['belge_url']); ?></td>
                        <td><?php echo htmlspecialchars($record['sertifika_sahibi']); ?></td>
                        <td><?php echo htmlspecialchars($record['sertifika_kurumu']); ?></td>
                        <td><?php echo htmlspecialchars($record['imza_zamani']); ?></td>
                        <td class="<?php echo $record['durum'] === 'imzalandi' ? 'success' : ($record['durum'] === 'hata' ? 'error' : 'pending'); ?>">
                            <?php echo htmlspecialchars($record['durum']); ?>
                        </td>
                        <td>
                            <a href="?history_id=<?php echo $record['id']; ?>" class="history-btn">Geçmiş</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div id="coklu" class="tab-content">
        <div class="content-box">
            <form method="post" action="kolayimza.php" id="multiSignForm">
                <div class="form-group">
                    <label>PDF Ekleme Yöntemi:</label>
                    <select id="pdf-source-multi" class="form-control" onchange="togglePdfSource('multi')">
                        <option value="url">URL Gir</option>
                        <option value="file">Dosya Yükle</option>
                    </select>
                </div>

                <div id="pdfUrls">
                    <div class="pdf-input">
                        <input type="text" name="pdf_urls[]" placeholder="PDF URL'i" required>
                        <button type="button" onclick="this.parentElement.remove()">Sil</button>
                    </div>
                </div>

                <div id="pdfFiles" style="display:none;">
                    <div class="pdf-file-input">
                        <input type="file" name="pdf_files[]" accept=".pdf">
                        <button type="button" onclick="this.parentElement.remove()">Sil</button>
                        <div class="upload-progress" style="display:none;">
                            <div class="progress-bar"></div>
                            <span class="progress-text">Yükleniyor... 0%</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="grup-adi">Grup Adı:</label>
                    <input type="text" id="grup-adi" name="grup_adi" placeholder="Grup Adı (Opsiyonel)">
                </div>

                <div class="form-group">
                    <label for="aciklama">Açıklama:</label>
                    <textarea id="aciklama" name="aciklama" placeholder="Açıklama (Opsiyonel)"></textarea>
                </div>

                <div class="error-message" id="errorMessage"></div>

                <button type="button" class="btn" onclick="addInput()">+ PDF Ekle</button>
            </form>
        </div>
    </div>

    <div id="gruplar" class="tab-content">
        <h2>İmza Grupları</h2>
        <div class="content-box">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Grup Adı</th>
                    <th>Toplam Belge</th>
                    <th>İmzalanan</th>
                    <th>Durum</th>
                    <th>Oluşturma Zamanı</th>
                    <th>İşlemler</th>
                </tr>
                <?php foreach ($kolayImza->listSignGroups() as $group): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($group['id']); ?></td>
                        <td><?php echo htmlspecialchars($group['grup_adi']); ?></td>
                        <td><?php echo htmlspecialchars($group['toplam_belge']); ?></td>
                        <td><?php echo htmlspecialchars($group['imzalanan_belge']); ?></td>
                        <td>
                            <span class="group-status status-<?php echo htmlspecialchars($group['durum']); ?>">
                                <?php echo htmlspecialchars($group['durum']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($group['olusturma_zamani']); ?></td>
                        <td>
                            <a href="?group_detail=<?php echo $group['id']; ?>" class="history-btn">Detay</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <?php if ($history): ?>
        <div id="historyModal" class="modal" style="display: block;">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('historyModal').style.display='none'">&times;</span>
                <h3>İmza Geçmişi</h3>
                <table>
                    <tr>
                        <th>Tarih</th>
                        <th>İşlem</th>
                        <th>Açıklama</th>
                        <th>IP Adresi</th>
                    </tr>
                    <?php foreach ($history as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['olusturma_zamani']); ?></td>
                            <td><?php echo htmlspecialchars($log['islem_tipi']); ?></td>
                            <td><?php echo htmlspecialchars($log['aciklama']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_adresi']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($groupDocuments): ?>
        <div id="groupDetailModal" class="modal" style="display: block;">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('groupDetailModal').style.display='none'">&times;</span>
                <h3>Grup Belgeleri</h3>
                <table>
                    <tr>
                        <th>Sıra</th>
                        <th>Belge URL</th>
                        <th>İmzalayan</th>
                        <th>İmza Zamanı</th>
                        <th>Durum</th>
                    </tr>
                    <?php foreach ($groupDocuments as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['sira_no'] + 1); ?></td>
                            <td><?php echo htmlspecialchars($doc['belge_url']); ?></td>
                            <td><?php echo htmlspecialchars($doc['sertifika_sahibi']); ?></td>
                            <td><?php echo htmlspecialchars($doc['imza_zamani']); ?></td>
                            <td class="<?php echo $doc['durum'] === 'imzalandi' ? 'success' : ($doc['durum'] === 'hata' ? 'error' : 'pending'); ?>">
                                <?php echo htmlspecialchars($doc['durum']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    <?php endif; ?>

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
            div.className = 'pdf-input';
            div.innerHTML = `
                <input type="text" name="pdf_urls[]" placeholder="PDF URL'i" required>
                <button type="button" onclick="this.parentElement.remove()">Sil</button>
            `;
            document.getElementById('pdfUrls').appendChild(div);
        }

        function addFileInput() {
            const div = document.createElement('div');
            div.className = 'pdf-file-input';
            div.innerHTML = `
                <input type="file" name="pdf_files[]" accept=".pdf">
                <button type="button" onclick="this.parentElement.remove()">Sil</button>
                <div class="upload-progress" style="display:none;">
                    <div class="progress-bar"></div>
                    <span class="progress-text">Yükleniyor... 0%</span>
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