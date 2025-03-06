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
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f5f5f5;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }

        .pending {
            color: orange;
        }

        .history-btn {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
        }

        .close {
            float: right;
            cursor: pointer;
            font-size: 28px;
        }

        .group-status {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 12px;
            color: white;
        }

        .status-bekliyor {
            background-color: #f39c12;
        }

        .status-tamamlandi {
            background-color: #27ae60;
        }

        .status-kismen_tamamlandi {
            background-color: #2980b9;
        }

        .status-hata {
            background-color: #c0392b;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .tabs {
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .tab-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            cursor: pointer;
            border-radius: 3px;
            margin-right: 5px;
        }

        .tab-btn.active {
            background: #4CAF50;
            color: white;
        }

        .content-box {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .pdf-input {
            margin-bottom: 10px;
            display: flex;
            gap: 10px;
        }

        .pdf-input input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .pdf-input button {
            background: #ff4444;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-top: 5px;
        }

        .btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
        }

        .btn:hover {
            background: #45a049;
        }

        .error-message {
            color: red;
            padding: 10px;
            background: #ffe6e6;
            border: 1px solid #ffcccc;
            border-radius: 3px;
            margin: 10px 0;
            display: none;
        }
    </style>
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
                <label for="single-pdf">PDF URL'i:</label>
                <input type="text" id="single-pdf" value="<?php echo htmlspecialchars($pdfUrl); ?>">
            </div>
            <a href="<?php echo htmlspecialchars($signUrl); ?>" class="btn imzala">PDF'i İmzala</a>
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
                <div id="pdfUrls">
                    <div class="pdf-input">
                        <input type="text" name="pdf_urls[]" placeholder="PDF URL'i" required>
                    </div>
                </div>
                <button type="button" class="btn" onclick="addPdfInput()">+ PDF Ekle</button>

                <div class="form-group">
                    <label for="grup-adi">Grup Adı:</label>
                    <input type="text" id="grup-adi" name="grup_adi" placeholder="Grup Adı (Opsiyonel)">
                </div>

                <div class="form-group">
                    <label for="aciklama">Açıklama:</label>
                    <textarea id="aciklama" name="aciklama" placeholder="Açıklama (Opsiyonel)"></textarea>
                </div>

                <div class="error-message" id="errorMessage"></div>

                <button type="submit" class="btn">Toplu İmzala</button>
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

        function addPdfInput() {
            const div = document.createElement('div');
            div.className = 'pdf-input';
            div.innerHTML = `
                <input type="text" name="pdf_urls[]" placeholder="PDF URL'i" required>
                <button type="button" onclick="this.parentElement.remove()">Sil</button>
            `;
            document.getElementById('pdfUrls').appendChild(div);
        }

        document.getElementById('multiSignForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const errorDiv = document.getElementById('errorMessage');

            fetch('kolayimza.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.signUrl) {
                        window.location.href = data.signUrl;
                    } else {
                        errorDiv.textContent = data.message || 'Bir hata oluştu';
                        errorDiv.style.display = 'block';
                    }
                })
                .catch(error => {
                    errorDiv.textContent = 'Sunucu hatası oluştu';
                    errorDiv.style.display = 'block';
                });
        };
    </script>
</body>

</html>