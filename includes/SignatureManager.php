<?php
class SignatureManager
{
    private $db;
    private $logger;

    public function __construct(PDO $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Karma imza sürecini başlat (Zincir + Paralel)
     */
    public function initSignatureProcess($fileInfo, $signatureOptions, $signatureGroups, $signatureType = 'chain')
    {
        try {
            $totalSignatures = 0;
            foreach ($signatureGroups as $group) {
                $totalSignatures += count($group['signers']);
            }

            // İmza tipi kontrolü
            $signatureType = in_array($signatureType, ['chain', 'parallel', 'mixed']) ? $signatureType : 'chain';

            $sql = "INSERT INTO signatures (
                filename, original_filename, file_size, signature_format,
                pdf_signature_pos_x, pdf_signature_pos_y,
                pdf_signature_width, pdf_signature_height,
                signature_location, signature_reason,
                ip_address, status,
                signature_groups, current_group,
                group_signatures, group_status,
                signature_type
            ) VALUES (
                :filename, :original_filename, :file_size, :signature_format,
                :pos_x, :pos_y, :width, :height,
                :location, :reason,
                :ip_address, 'pending',
                :signature_groups, 1,
                :group_signatures, :group_status,
                :signature_type
            )";

            // Grup verilerini doğrula
            foreach ($signatureGroups as $group) {
                if (!isset($group['signers']) || !is_array($group['signers'])) {
                    throw new Exception('Geçersiz grup yapısı.');
                }
            }

            // Her grup için boş imza dizisi oluştur (1'den başlayan indekslerle)
            $groupSignatures = [];
            $groupStatus = [];
            foreach ($signatureGroups as $index => $group) {
                $groupNum = $index + 1;
                $groupSignatures[$groupNum] = [];
                $groupStatus[$groupNum] = 'pending';
            }

            // JSON verilerinin geçerliliğini kontrol et
            $jsonGroups = json_encode($signatureGroups);
            $jsonSignatures = json_encode($groupSignatures);
            $jsonStatus = json_encode($groupStatus);

            if ($jsonGroups === false || $jsonSignatures === false || $jsonStatus === false) {
                throw new Exception('Grup verilerini JSON\'a dönüştürme hatası.');
            }

            $stmt = $this->db->prepare($sql);
            try {
                $stmt->execute([
                    'filename' => $fileInfo['filename'],
                    'original_filename' => $fileInfo['original_name'],
                    'file_size' => $fileInfo['size'],
                    'signature_format' => $signatureOptions['format'],
                    'pos_x' => $signatureOptions['x'],
                    'pos_y' => $signatureOptions['y'],
                    'width' => $signatureOptions['width'],
                    'height' => $signatureOptions['height'],
                    'location' => $signatureOptions['location'],
                    'reason' => $signatureOptions['reason'],
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'signature_groups' => $jsonGroups,
                    'group_signatures' => $jsonSignatures,
                    'group_status' => $jsonStatus,
                    'signature_type' => $signatureType
                ]);
            } catch (PDOException $e) {
                $this->logger->error('Database insert error:', [
                    'error' => $e->getMessage(),
                    'groups' => $signatureGroups,
                    'signatures' => $groupSignatures,
                    'status' => $groupStatus
                ]);
                throw new Exception('İmza süreci başlatılamadı: Veritabanı hatası.');
            }

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            $this->logger->error('Database error while initializing signature chain: ' . $e->getMessage());
            throw new Exception('İmza zinciri başlatılamadı.');
        }
    }

    /**
     * İmzalama yetkisi kontrol et
     */
    public function checkSignaturePermission($filename, $certificateSerialNumber)
    {
        try {
            $sql = "SELECT signature_groups, current_group, group_signatures, group_status, signature_type
                   FROM signatures WHERE filename = :filename";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['filename' => $filename]);
            $result = $stmt->fetch();

            if (!$result) {
                throw new Exception('İmza kaydı bulunamadı.');
            }

            $signatureGroups = json_decode($result['signature_groups'], true);
            $currentGroup = $result['current_group'];
            $groupSignatures = json_decode($result['group_signatures'], true);
            $groupStatus = json_decode($result['group_status'], true);

            if (!$signatureGroups || !$groupSignatures || !$groupStatus) {
                throw new Exception('İmza grubu verileri geçersiz.');
            }

            // İmza tipine göre kontrol
            $signatureType = $result['signature_type'];

            // Zincir veya karışık imza ise, önceki grupların hepsi tamamlanmış olmalı
            if ($signatureType === 'chain' || $signatureType === 'mixed') {
                for ($i = 1; $i < $currentGroup; $i++) {
                    if (!isset($groupStatus[$i]) || $groupStatus[$i] !== 'completed') {
                        throw new Exception('Önceki imza grubu henüz tamamlanmamış.');
                    }
                }
            } else if ($signatureType === 'parallel') {
                // Paralel imzada önceki grupların tamamlanması beklenmez
            }

            // Şu anki grubun imzacıları arasında olmalı
            if (
                !isset($signatureGroups[$currentGroup - 1]) ||
                !is_array($signatureGroups[$currentGroup - 1]) ||
                !isset($signatureGroups[$currentGroup - 1]['signers']) ||
                !is_array($signatureGroups[$currentGroup - 1]['signers'])
            ) {

                // Debug bilgisi logla
                $this->logger->error('Group data error:', [
                    'currentGroup' => $currentGroup,
                    'signatureGroups' => $signatureGroups
                ]);
                throw new Exception('İmza grubu bilgisi bulunamadı. (Grup: ' . $currentGroup . ')');
            }

            $currentSigners = $signatureGroups[$currentGroup - 1]['signers'];
            if (!is_array($currentSigners) || !in_array($certificateSerialNumber, $currentSigners)) {
                throw new Exception('Bu belgeyi imzalama yetkiniz yok.');
            }

            // Aynı kişi tekrar imzalayamaz
            $currentGroupSignatures = $groupSignatures[$currentGroup];
            foreach ($currentGroupSignatures as $signature) {
                if ($signature['certificateSerialNumber'] === $certificateSerialNumber) {
                    throw new Exception('Bu belgeyi zaten imzalamışsınız.');
                }
            }

            return true;
        } catch (PDOException $e) {
            $this->logger->error('Database error while checking signature permission: ' . $e->getMessage());
            throw new Exception('İmza yetkisi kontrolü yapılamadı.');
        }
    }

    /**
     * Grup imzasını güncelle
     */
    public function updateGroupSignature($filename, $signatureData)
    {
        try {
            // Mevcut durumu al
            $sql = "SELECT signature_groups, current_group, group_signatures, group_status
                   FROM signatures WHERE filename = :filename";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['filename' => $filename]);
            $current = $stmt->fetch();

            if (!$current) {
                throw new Exception('İmza kaydı bulunamadı.');
            }

            $signatureGroups = json_decode($current['signature_groups'], true);
            $currentGroup = $current['current_group'];
            $groupSignatures = json_decode($current['group_signatures'], true);
            $groupStatus = json_decode($current['group_status'], true);

            if (!$signatureGroups || !$groupSignatures || !$groupStatus) {
                throw new Exception('İmza grubu verileri geçersiz.');
            }

            if (!isset($groupSignatures[$currentGroup])) {
                $groupSignatures[$currentGroup] = [];
            }

            // Yeni imza bilgisini ekle
            $groupSignatures[$currentGroup][] = [
                'certificateName' => $signatureData['certificateName'],
                'certificateIssuer' => $signatureData['certificateIssuer'],
                'certificateSerialNumber' => $signatureData['certificateSerialNumber'],
                'signatureDate' => $signatureData['createdAt'],
                'signature' => $signatureData['signature']
            ];

            // Mevcut grup tamamlandı mı kontrol et
            $currentGroupSigners = $signatureGroups[$currentGroup - 1]['signers'];
            $currentGroupSignatures = $groupSignatures[$currentGroup];
            $isGroupCompleted = count($currentGroupSignatures) >= count($currentGroupSigners);

            if ($isGroupCompleted) {
                $groupStatus[$currentGroup] = 'completed';
                $signatureType = $current['signature_type'];

                // Grup tamamlandığında ne yapılacağına karar ver
                if ($signatureType === 'chain' || $signatureType === 'mixed') {
                    // Zincir veya karışık imzada sırayla ilerle
                    if ($currentGroup < count($signatureGroups)) {
                        $currentGroup++;
                        $status = 'pending';
                    } else {
                        $status = 'completed';
                    }
                } else if ($signatureType === 'parallel') {
                    // Paralel imzada tüm gruplar tamamlandı mı kontrol et
                    $allCompleted = true;
                    foreach ($groupStatus as $status) {
                        if ($status !== 'completed') {
                            $allCompleted = false;
                            break;
                        }
                    }
                    $status = $allCompleted ? 'completed' : 'pending';
                }
            } else {
                $status = 'pending';
            }

            // Güncelle
            $sql = "UPDATE signatures SET
                current_group = :current_group,
                group_signatures = :group_signatures,
                group_status = :group_status,
                status = :status,
                signed_pdf_path = :signed_pdf_path
                WHERE filename = :filename";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'current_group' => $currentGroup,
                'group_signatures' => json_encode($groupSignatures),
                'group_status' => json_encode($groupStatus),
                'status' => $status,
                'signed_pdf_path' => $signatureData['signed_pdf_path'] ?? null,
                'filename' => $filename
            ]);

            return $status === 'completed';
        } catch (PDOException $e) {
            $this->logger->error('Database error while updating signature chain: ' . $e->getMessage());
            throw new Exception('İmza zinciri güncellenemedi.');
        }
    }

    /**
     * İmza işlemini veritabanına kaydet
     */
    public function createSignatureRecord($fileInfo, $signatureOptions)
    {
        try {
            $sql = "INSERT INTO signatures (
                filename, original_filename, file_size, signature_format,
                pdf_signature_pos_x, pdf_signature_pos_y, 
                pdf_signature_width, pdf_signature_height,
                signature_location, signature_reason,
                ip_address, status
            ) VALUES (
                :filename, :original_filename, :file_size, :signature_format,
                :pos_x, :pos_y, :width, :height,
                :location, :reason,
                :ip_address, 'pending'
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'filename' => $fileInfo['filename'],
                'original_filename' => $fileInfo['original_name'],
                'file_size' => $fileInfo['size'],
                'signature_format' => $signatureOptions['format'],
                'pos_x' => $signatureOptions['x'],
                'pos_y' => $signatureOptions['y'],
                'width' => $signatureOptions['width'],
                'height' => $signatureOptions['height'],
                'location' => $signatureOptions['location'],
                'reason' => $signatureOptions['reason'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            $this->logger->error('Database error while creating signature record: ' . $e->getMessage());
            throw new Exception('İmza kaydı oluşturulamadı.');
        }
    }

    /**
     * İmza sonucunu güncelle
     */
    public function updateSignatureResult($filename, $signatureData)
    {
        try {
            $sql = "UPDATE signatures SET
                status = 'completed',
                certificate_name = :cert_name,
                certificate_issuer = :cert_issuer,
                certificate_serial_number = :cert_serial,
                signature_date = :sig_date,
                signature_data = :sig_data,
                signed_pdf_path = :signed_pdf_path
                WHERE filename = :filename";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'cert_name' => $signatureData['certificateName'],
                'cert_issuer' => $signatureData['certificateIssuer'],
                'cert_serial' => $signatureData['certificateSerialNumber'],
                'sig_date' => date('Y-m-d H:i:s', strtotime($signatureData['createdAt'])),
                'sig_data' => $signatureData['signature'],
                'signed_pdf_path' => $signatureData['signed_pdf_path'] ?? null,
                'filename' => $filename
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('İmza kaydı bulunamadı.');
            }

            return true;
        } catch (PDOException $e) {
            $this->logger->error('Database error while updating signature result: ' . $e->getMessage());
            throw new Exception('İmza sonucu güncellenemedi.');
        }
    }

    /**
     * İmza hatası kaydet
     */
    public function markAsFailed($filename, $errorMessage)
    {
        try {
            $sql = "UPDATE signatures SET 
                status = 'failed',
                error_message = :error
                WHERE filename = :filename";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'error' => $errorMessage,
                'filename' => $filename
            ]);

            return true;
        } catch (PDOException $e) {
            $this->logger->error('Database error while marking signature as failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * İmza kaydını getir
     */
    public function getSignatureRecord($filename)
    {
        try {
            $sql = "SELECT * FROM signatures WHERE filename = :filename";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['filename' => $filename]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->logger->error('Database error while fetching signature record: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Son imzaları getir (pagination ile)
     */
    public function getRecentSignatures($limit = 10, $offset = 0)
    {
        try {
            $sql = "SELECT * FROM signatures ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->error('Database error while fetching recent signatures: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Toplam imza sayısını getir
     */
    public function getTotalSignatures()
    {
        try {
            $sql = "SELECT COUNT(*) FROM signatures";
            return (int)$this->db->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            $this->logger->error('Database error while getting total signatures: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * İmzaları filtrele
     */
    public function searchSignatures($filters = [], $limit = 10, $offset = 0)
    {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['status'])) {
                $where[] = "status = :status";
                $params['status'] = $filters['status'];
            }

            if (!empty($filters['dateFrom'])) {
                $where[] = "created_at >= :dateFrom";
                $params['dateFrom'] = $filters['dateFrom'];
            }

            if (!empty($filters['dateTo'])) {
                $where[] = "created_at <= :dateTo";
                $params['dateTo'] = $filters['dateTo'];
            }

            if (!empty($filters['search'])) {
                $where[] = "(original_filename LIKE :search OR certificate_name LIKE :search)";
                $params['search'] = "%{$filters['search']}%";
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT * FROM signatures $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->error('Database error while searching signatures: ' . $e->getMessage());
            return [];
        }
    }
}
