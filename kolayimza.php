<?php

/**
 * KolayImza API Entegrasyonu
 * @author A. Kerem Gök
 */

require_once 'config.php';
require_once 'KolayImzaException.php';

class KolayImza
{
    private $baseUrl;
    private $db;
    private $hatalar = [];

    public function __construct()
    {
        $this->baseUrl = "sign://";
        $this->connectDB();
    }

    /**
     * PDF URL'sini doğrular
     * @param string $url PDF URL'i
     * @throws KolayImzaException URL geçersizse
     */
    private function validatePdfUrl($url)
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new KolayImzaException(
                'Geçersiz PDF URL\'i',
                KolayImzaException::HATA_PDF_URL,
                ['url' => $url]
            );
        }

        // PDF'in erişilebilir olduğunu kontrol et
        $headers = get_headers($url);
        if (!$headers || strpos($headers[0], '200') === false) {
            throw new KolayImzaException(
                'PDF dosyasına erişilemiyor',
                KolayImzaException::HATA_PDF_URL,
                ['url' => $url, 'headers' => $headers]
            );
        }
    }

    /**
     * ID'nin geçerliliğini kontrol eder
     * @param int $id Kontrol edilecek ID
     * @throws KolayImzaException ID geçersizse
     */
    private function validateId($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new KolayImzaException(
                'Geçersiz ID değeri',
                KolayImzaException::HATA_GECERSIZ_ID,
                ['id' => $id]
            );
        }
    }

    /**
     * Veritabanı bağlantısını kurar
     * @throws KolayImzaException Bağlantı hatası durumunda
     */
    private function connectDB()
    {
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new KolayImzaException(
                'Veritabanı bağlantı hatası',
                KolayImzaException::HATA_VERITABANI_BAGLANTI,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Veritabanı sorgusunu güvenli şekilde çalıştırır
     * @param string $sql SQL sorgusu
     * @param array $params Sorgu parametreleri
     * @return PDOStatement
     * @throws KolayImzaException Sorgu hatası durumunda
     */
    private function executeQuery($sql, $params = [])
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new KolayImzaException(
                'Veritabanı sorgu hatası',
                KolayImzaException::HATA_VERITABANI_SORGU,
                [
                    'sql' => $sql,
                    'params' => $params,
                    'error' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * İmza yanıtını doğrular
     * @param array $response İmza yanıtı
     * @throws KolayImzaException Yanıt geçersizse
     */
    private function validateSignatureResponse($response)
    {
        if (!is_array($response)) {
            throw new KolayImzaException(
                'Geçersiz imza yanıtı formatı',
                KolayImzaException::HATA_IMZA_YANIT,
                ['response' => $response]
            );
        }

        $requiredFields = ['certificate', 'certificateName', 'certificateIssuer', 'createdAt'];
        $missingFields = array_diff($requiredFields, array_keys($response));

        if (!empty($missingFields)) {
            throw new KolayImzaException(
                'Eksik imza yanıt alanları',
                KolayImzaException::HATA_IMZA_YANIT,
                ['missing_fields' => $missingFields]
            );
        }
    }

    /**
     * İmza geçmişine yeni kayıt ekler
     * @param int $imzaId İmza kaydının ID'si
     * @param string $islemTipi İşlem tipi (olusturuldu, imzalandi, hata, iptal_edildi)
     * @param string $aciklama İşlem açıklaması
     * @return bool
     */
    private function addHistory($imzaId, $islemTipi, $aciklama = '')
    {
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
    public function getSignHistory($imzaId)
    {
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
     * @throws KolayImzaException
     */
    public function createSignRecord($pdfUrl)
    {
        $this->validatePdfUrl($pdfUrl);

        $stmt = $this->executeQuery(
            "INSERT INTO imza_kayitlari (belge_url, durum) VALUES (:belge_url, 'bekliyor')",
            ['belge_url' => $pdfUrl]
        );

        $recordId = $this->db->lastInsertId();
        $this->addHistory($recordId, 'olusturuldu', 'İmza talebi oluşturuldu');

        return $recordId;
    }

    /**
     * PDF dosyasını imzalamak için gerekli URL'i oluşturur
     * @param string $pdfUrl İmzalanacak PDF'in URL'i
     * @param string $responseUrl İmza sonucunun gönderileceği URL
     * @return string
     */
    public function createSignUrl($pdfUrl, $responseUrl)
    {
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
     * @throws KolayImzaException
     */
    public function handleSignatureResponse($jsonResponse, $recordId)
    {
        $this->validateId($recordId);

        $response = json_decode($jsonResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new KolayImzaException(
                'Geçersiz JSON formatı',
                KolayImzaException::HATA_IMZA_YANIT,
                ['json_error' => json_last_error_msg()]
            );
        }

        $this->validateSignatureResponse($response);

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
    public function listSignRecords()
    {
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
    public function createSignGroup($grupAdi, $aciklama = '')
    {
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
    public function addPDFsToGroup($grupId, $pdfUrls)
    {
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
    public function createMultiSignUrl($pdfUrls, $responseUrl, $grupAdi = null, $aciklama = '')
    {
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
    private function updateGroupStatus($grupId)
    {
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
    public function listSignGroups()
    {
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
    public function listGroupDocuments($grupId)
    {
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

    /**
     * Son hata mesajını döndürür
     * @return string|null
     */
    public function getSonHata()
    {
        return end($this->hatalar) ?: null;
    }

    /**
     * Tüm hataları döndürür
     * @return array
     */
    public function getHatalar()
    {
        return $this->hatalar;
    }
}

// Hata yönetimi için yardımcı fonksiyon
function hataYonet($callback)
{
    try {
        return $callback();
    } catch (KolayImzaException $e) {
        $response = [
            'success' => false,
            'message' => $e->getKullaniciMesaji(),
            'code' => $e->getCode()
        ];

        if (DEBUG_MODE) {
            $response['details'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'details' => $e->getHataDetaylari()
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// API İsteklerini İşle
header('Content-Type: application/json');

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $kolayImza = new KolayImza();

    // İmza sonucu işleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_response'])) {
        $recordId = $_GET['record_id'] ?? null;
        $groupId = $_GET['group_id'] ?? null;

        if (!$recordId && !$groupId) {
            throw new KolayImzaException(
                'Kayıt ID veya Grup ID gerekli',
                KolayImzaException::HATA_GECERSIZ_ID
            );
        }

        $result = $kolayImza->handleSignatureResponse(
            $_POST['sign_response'],
            $recordId,
            $groupId
        );

        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    }

    // Çoklu imza formu işleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_urls'])) {
        $pdfUrls = $_POST['pdf_urls'];
        $grupAdi = $_POST['grup_adi'] ?? null;
        $aciklama = $_POST['aciklama'] ?? '';

        if (empty($pdfUrls)) {
            throw new KolayImzaException(
                'En az bir PDF URL\'i gerekli',
                KolayImzaException::HATA_PDF_URL
            );
        }

        $responseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
            $_SERVER['HTTP_HOST'] .
            dirname($_SERVER['PHP_SELF']) . '/kolayimza.php';

        $signUrl = $kolayImza->createMultiSignUrl($pdfUrls, $responseUrl, $grupAdi, $aciklama);

        echo json_encode(['success' => true, 'signUrl' => $signUrl]);
        exit;
    }

    // Geçmiş kayıtları getirme
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['history_id'])) {
        $history = $kolayImza->getSignHistory((int)$_GET['history_id']);
        echo json_encode(['success' => true, 'data' => $history]);
        exit;
    }

    // Grup detaylarını getirme
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['group_detail'])) {
        $documents = $kolayImza->listGroupDocuments((int)$_GET['group_detail']);
        echo json_encode(['success' => true, 'data' => $documents]);
        exit;
    }

    // Geçersiz istek
    throw new KolayImzaException(
        'Geçersiz API isteği',
        KolayImzaException::HATA_GECERSIZ_DURUM
    );
} catch (KolayImzaException $e) {
    $response = [
        'success' => false,
        'message' => $e->getKullaniciMesaji(),
        'code' => $e->getCode()
    ];

    if (DEBUG_MODE) {
        $response['details'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'details' => $e->getHataDetaylari()
        ];
    }

    echo json_encode($response);
    exit;
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Beklenmeyen bir hata oluştu',
        'code' => 500
    ]);
    exit;
}

// Örnek Kullanım
$kolayImza = new KolayImza();

// İmza sonucu işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_response'])) {
    hataYonet(function () use ($kolayImza) {
        $recordId = $_GET['record_id'] ?? null;
        $groupId = $_GET['group_id'] ?? null;

        if (!$recordId && !$groupId) {
            throw new KolayImzaException(
                'Kayıt ID veya Grup ID gerekli',
                KolayImzaException::HATA_GECERSIZ_ID
            );
        }

        $result = $kolayImza->handleSignatureResponse(
            $_POST['sign_response'],
            $recordId,
            $groupId
        );

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    });
}

// Çoklu imza formu işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pdf_urls'])) {
    hataYonet(function () use ($kolayImza) {
        $pdfUrls = $_POST['pdf_urls'];
        $grupAdi = $_POST['grup_adi'] ?? null;
        $aciklama = $_POST['aciklama'] ?? '';

        if (empty($pdfUrls)) {
            throw new KolayImzaException(
                'En az bir PDF URL\'i gerekli',
                KolayImzaException::HATA_PDF_URL
            );
        }

        $responseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
            $_SERVER['HTTP_HOST'] .
            parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $signUrl = $kolayImza->createMultiSignUrl($pdfUrls, $responseUrl, $grupAdi, $aciklama);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'signUrl' => $signUrl]);
        exit;
    });
}

// İmza geçmişi görüntüleme
$selectedId = isset($_GET['history_id']) ? (int)$_GET['history_id'] : null;
$history = $selectedId ? $kolayImza->getSignHistory($selectedId) : null;

// Grup detayı görüntüleme
$selectedGroupId = isset($_GET['group_detail']) ? (int)$_GET['group_detail'] : null;
$groupDocuments = $selectedGroupId ? $kolayImza->listGroupDocuments($selectedGroupId) : null;
