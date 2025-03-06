<?php
/**
 * KolayImza API Entegrasyonu
 * @author A. Kerem Gök
 */

require_once 'config.php';

class KolayImza {
    private $baseUrl;
    private $db;
    
    public function __construct() {
        $this->baseUrl = "sign://";
        $this->connectDB();
    }
    
    /**
     * Veritabanı bağlantısını kurar
     */
    private function connectDB() {
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }
    
    /**
     * İmza geçmişine yeni kayıt ekler
     * @param int $imzaId İmza kaydının ID'si
     * @param string $islemTipi İşlem tipi (olusturuldu, imzalandi, hata, iptal_edildi)
     * @param string $aciklama İşlem açıklaması
     * @return bool
     */
    private function addHistory($imzaId, $islemTipi, $aciklama = '') {
        $stmt = $this->db->prepare("
            INSERT INTO imza_gecmisi (imza_id, islem_tipi, aciklama, ip_adresi, kullanici_bilgisi)
            VALUES (:imza_id, :islem_tipi, :aciklama, :ip_adresi, :kullanici_bilgisi)
        ");
        
        return $stmt->execute([
            'imza_id' => $imzaId,
            'islem_tipi' => $islemTipi,
            'aciklama' => $aciklama,
            'ip_adresi' => $_SERVER['REMOTE_ADDR'] ?? null,
            'kullanici_bilgisi' => json_encode([
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ])
        ]);
    }
    
    /**
     * Belirli bir imzanın geçmiş kayıtlarını getirir
     * @param int $imzaId İmza kaydının ID'si
     * @return array
     */
    public function getSignHistory($imzaId) {
        $stmt = $this->db->prepare("
            SELECT 
                ig.*,
                ik.belge_url,
                ik.sertifika_sahibi
            FROM imza_gecmisi ig
            LEFT JOIN imza_kayitlari ik ON ig.imza_id = ik.id
            WHERE ig.imza_id = :imza_id
            ORDER BY ig.olusturma_zamani DESC
        ");
        
        $stmt->execute(['imza_id' => $imzaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Yeni bir imza kaydı oluşturur
     * @param string $pdfUrl İmzalanacak PDF'in URL'i
     * @return int Oluşturulan kaydın ID'si
     */
    public function createSignRecord($pdfUrl) {
        $stmt = $this->db->prepare("
            INSERT INTO imza_kayitlari (belge_url, durum)
            VALUES (:belge_url, 'bekliyor')
        ");
        
        $stmt->execute(['belge_url' => $pdfUrl]);
        $recordId = $this->db->lastInsertId();
        
        // Geçmişe kayıt ekle
        $this->addHistory($recordId, 'olusturuldu', 'İmza talebi oluşturuldu');
        
        return $recordId;
    }
    
    /**
     * PDF dosyasını imzalamak için gerekli URL'i oluşturur
     * @param string $pdfUrl İmzalanacak PDF'in URL'i
     * @param string $responseUrl İmza sonucunun gönderileceği URL
     * @return string
     */
    public function createSignUrl($pdfUrl, $responseUrl) {
        // Önce veritabanına kayıt oluştur
        $recordId = $this->createSignRecord($pdfUrl);
        
        $request = [
            "resources" => [
                [
                    "source" => $pdfUrl,
                    "targetUrl" => $pdfUrl
                ]
            ],
            "responseUrl" => $responseUrl . "?record_id=" . $recordId
        ];
        
        $base64Json = base64_encode(json_encode($request));
        return $this->baseUrl . "?xsjson=" . $base64Json;
    }
    
    /**
     * İmza sonucunu işler ve veritabanını günceller
     * @param string $jsonResponse İmza sonucu JSON
     * @param int $recordId Kayıt ID
     * @return array
     */
    public function handleSignatureResponse($jsonResponse, $recordId) {
        $response = json_decode($jsonResponse, true);
        
        $data = [
            'success' => !empty($response['certificate']),
            'certificateName' => $response['certificateName'] ?? null,
            'certificateIssuer' => $response['certificateIssuer'] ?? null,
            'createdAt' => $response['createdAt'] ?? null,
            'signature' => $response['resources'][0]['signature'] ?? null
        ];
        
        // Veritabanını güncelle
        $stmt = $this->db->prepare("
            UPDATE imza_kayitlari 
            SET 
                sertifika_sahibi = :certificateName,
                sertifika_kurumu = :certificateIssuer,
                imza_zamani = :createdAt,
                imza_data = :signature,
                durum = :durum
            WHERE id = :id
        ");
        
        $stmt->execute([
            'certificateName' => $data['certificateName'],
            'certificateIssuer' => $data['certificateIssuer'],
            'createdAt' => $data['createdAt'],
            'signature' => $data['signature'],
            'durum' => $data['success'] ? 'imzalandi' : 'hata',
            'id' => $recordId
        ]);
        
        // Geçmişe kayıt ekle
        $this->addHistory(
            $recordId, 
            $data['success'] ? 'imzalandi' : 'hata',
            $data['success'] ? 'İmza başarıyla tamamlandı' : 'İmza işlemi başarısız oldu'
        );
        
        return $data;
    }
    
    /**
     * İmza kayıtlarını listeler
     * @return array
     */
    public function listSignRecords() {
        $stmt = $this->db->query("
            SELECT * FROM imza_kayitlari 
            ORDER BY olusturma_zamani DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Yeni bir imza grubu oluşturur
     * @param string $grupAdi Grup adı
     * @param string $aciklama Grup açıklaması
     * @return int Oluşturulan grubun ID'si
     */
    public function createSignGroup($grupAdi, $aciklama = '') {
        $stmt = $this->db->prepare("
            INSERT INTO imza_gruplari (grup_adi, aciklama, durum)
            VALUES (:grup_adi, :aciklama, 'bekliyor')
        ");
        
        $stmt->execute([
            'grup_adi' => $grupAdi,
            'aciklama' => $aciklama
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * İmza grubuna PDF ekler
     * @param int $grupId Grup ID
     * @param array $pdfUrls PDF URL'lerinin dizisi
     * @return array Eklenen kayıtların ID'leri
     */
    public function addPDFsToGroup($grupId, $pdfUrls) {
        $recordIds = [];
        $siraNo = 0;
        
        foreach ($pdfUrls as $pdfUrl) {
            // Önce imza kaydı oluştur
            $imzaId = $this->createSignRecord($pdfUrl);
            
            // Gruba ekle
            $stmt = $this->db->prepare("
                INSERT INTO imza_grubu_belgeleri (grup_id, imza_id, sira_no)
                VALUES (:grup_id, :imza_id, :sira_no)
            ");
            
            $stmt->execute([
                'grup_id' => $grupId,
                'imza_id' => $imzaId,
                'sira_no' => $siraNo++
            ]);
            
            $recordIds[] = $imzaId;
        }
        
        return $recordIds;
    }
    
    /**
     * Çoklu PDF imzalama için URL oluşturur
     * @param array $pdfUrls PDF URL'lerinin dizisi
     * @param string $responseUrl Yanıt URL'i
     * @param string $grupAdi Grup adı (opsiyonel)
     * @param string $aciklama Grup açıklaması (opsiyonel)
     * @return string
     */
    public function createMultiSignUrl($pdfUrls, $responseUrl, $grupAdi = null, $aciklama = '') {
        // Grup oluştur
        $grupId = $this->createSignGroup(
            $grupAdi ?? 'Toplu İmza ' . date('Y-m-d H:i:s'),
            $aciklama
        );
        
        // PDF'leri gruba ekle
        $recordIds = $this->addPDFsToGroup($grupId, $pdfUrls);
        
        // İmza isteği oluştur
        $request = [
            "resources" => [],
            "responseUrl" => $responseUrl . "?group_id=" . $grupId
        ];
        
        // Her PDF için kaynak ekle
        foreach ($pdfUrls as $index => $pdfUrl) {
            $request["resources"][] = [
                "source" => $pdfUrl,
                "targetUrl" => $pdfUrl,
                "order" => $index + 1
            ];
        }
        
        $base64Json = base64_encode(json_encode($request));
        return $this->baseUrl . "?xsjson=" . $base64Json;
    }
    
    /**
     * Grup durumunu günceller
     * @param int $grupId Grup ID
     */
    private function updateGroupStatus($grupId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as toplam,
                SUM(CASE WHEN durum = 'imzalandi' THEN 1 ELSE 0 END) as imzalanan,
                SUM(CASE WHEN durum = 'hata' THEN 1 ELSE 0 END) as hatali
            FROM imza_kayitlari ik
            INNER JOIN imza_grubu_belgeleri igb ON ik.id = igb.imza_id
            WHERE igb.grup_id = :grup_id
        ");
        
        $stmt->execute(['grup_id' => $grupId]);
        $sonuc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $durum = 'bekliyor';
        if ($sonuc['imzalanan'] == $sonuc['toplam']) {
            $durum = 'tamamlandi';
        } elseif ($sonuc['imzalanan'] > 0) {
            $durum = 'kismen_tamamlandi';
        } elseif ($sonuc['hatali'] > 0) {
            $durum = 'hata';
        }
        
        $stmt = $this->db->prepare("
            UPDATE imza_gruplari 
            SET durum = :durum 
            WHERE id = :id
        ");
        
        $stmt->execute([
            'durum' => $durum,
            'id' => $grupId
        ]);
    }
    
    /**
     * İmza gruplarını listeler
     * @return array
     */
    public function listSignGroups() {
        $stmt = $this->db->query("
            SELECT 
                ig.*,
                COUNT(igb.id) as toplam_belge,
                SUM(CASE WHEN ik.durum = 'imzalandi' THEN 1 ELSE 0 END) as imzalanan_belge
            FROM imza_gruplari ig
            LEFT JOIN imza_grubu_belgeleri igb ON ig.id = igb.grup_id
            LEFT JOIN imza_kayitlari ik ON igb.imza_id = ik.id
            GROUP BY ig.id
            ORDER BY ig.olusturma_zamani DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Gruptaki belgeleri listeler
     * @param int $grupId Grup ID
     * @return array
     */
    public function listGroupDocuments($grupId) {
        $stmt = $this->db->prepare("
            SELECT 
                ik.*,
                igb.sira_no
            FROM imza_kayitlari ik
            INNER JOIN imza_grubu_belgeleri igb ON ik.id = igb.imza_id
            WHERE igb.grup_id = :grup_id
            ORDER BY igb.sira_no ASC
        ");
        
        $stmt->execute(['grup_id' => $grupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Örnek Kullanım
$kolayImza = new KolayImza();

// İmza sonucu işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_response'])) {
    $recordId = $_GET['record_id'] ?? null;
    $groupId = $_GET['group_id'] ?? null;
    
    $result = $kolayImza->handleSignatureResponse(
        $_POST['sign_response'],
        $recordId,
        $groupId
    );
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// İmza geçmişi görüntüleme
$selectedId = isset($_GET['history_id']) ? (int)$_GET['history_id'] : null;
$history = $selectedId ? $kolayImza->getSignHistory($selectedId) : null;

// Grup detayı görüntüleme
$selectedGroupId = isset($_GET['group_detail']) ? (int)$_GET['group_detail'] : null;
$groupDocuments = $selectedGroupId ? $kolayImza->listGroupDocuments($selectedGroupId) : null;
?>

<!DOCTYPE html>
<html>
<head>
    <title>KolayImza PDF İmzalama</title>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        .success { color: green; }
        .error { color: red; }
        .pending { color: orange; }
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
            background-color: rgba(0,0,0,0.5);
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
        .status-bekliyor { background-color: #f39c12; }
        .status-tamamlandi { background-color: #27ae60; }
        .status-kismen_tamamlandi { background-color: #2980b9; }
        .status-hata { background-color: #c0392b; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tabs { margin-bottom: 20px; }
        .tab-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            cursor: pointer;
        }
        .tab-btn.active {
            background: #4CAF50;
            color: white;
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
        <?php
        $pdfUrl = "http://example.com/belge.pdf";
        $responseUrl = "http://example.com/kolayimza.php";
        $signUrl = $kolayImza->createSignUrl($pdfUrl, $responseUrl);
        ?>
        <a href="<?php echo htmlspecialchars($signUrl); ?>" class="imzala">PDF'i İmzala</a>
        
        <h2>Tekli İmza Kayıtları</h2>
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
    
    <div id="coklu" class="tab-content">
        <form method="post" action="" id="multiSignForm">
            <div id="pdfUrls">
                <div class="pdf-input">
                    <input type="text" name="pdf_urls[]" placeholder="PDF URL'i" required>
                </div>
            </div>
            <button type="button" onclick="addPdfInput()">+ PDF Ekle</button>
            <input type="text" name="grup_adi" placeholder="Grup Adı (Opsiyonel)">
            <textarea name="aciklama" placeholder="Açıklama (Opsiyonel)"></textarea>
            <button type="submit">Toplu İmzala</button>
        </form>
    </div>
    
    <div id="gruplar" class="tab-content">
        <h2>İmza Grupları</h2>
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
            const pdfUrls = Array.from(formData.getAll('pdf_urls[]'));
            const grupAdi = formData.get('grup_adi');
            const aciklama = formData.get('aciklama');

            // AJAX ile sunucuya gönder ve imza URL'ini al
            // Bu kısmı kendi sunucu yapınıza göre uyarlayın
            fetch('kolayimza.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.signUrl) {
                    window.location.href = data.signUrl;
                }
            });
        };
    </script>
</body>
</html> 