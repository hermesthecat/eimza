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
}

// Örnek Kullanım
$kolayImza = new KolayImza();

// İmza sonucu işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_response'])) {
    $recordId = $_GET['record_id'] ?? null;
    if ($recordId) {
        $result = $kolayImza->handleSignatureResponse($_POST['sign_response'], $recordId);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}

// İmza geçmişi görüntüleme
$selectedId = isset($_GET['history_id']) ? (int)$_GET['history_id'] : null;
$history = $selectedId ? $kolayImza->getSignHistory($selectedId) : null;
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
    </style>
</head>
<body>
    <h1>PDF İmzalama</h1>
    
    <?php
    $pdfUrl = "http://example.com/belge.pdf"; // İmzalanacak PDF'in URL'i
    $responseUrl = "http://example.com/kolayimza.php"; // İmza sonucunun gönderileceği URL
    $signUrl = $kolayImza->createSignUrl($pdfUrl, $responseUrl);
    ?>
    
    <a href="<?php echo htmlspecialchars($signUrl); ?>" class="imzala">PDF'i İmzala</a>
    
    <h2>İmza Kayıtları</h2>
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

</body>
</html> 