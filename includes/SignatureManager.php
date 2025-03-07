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
     * İmza zinciri başlat
     */
    public function initSignatureChain($fileInfo, $signatureOptions, $requiredSigners)
    {
        try {
            $sql = "INSERT INTO signatures (
                filename, original_filename, file_size, signature_format,
                pdf_signature_pos_x, pdf_signature_pos_y,
                pdf_signature_width, pdf_signature_height,
                signature_location, signature_reason,
                ip_address, status,
                required_signatures, next_signer,
                signature_chain
            ) VALUES (
                :filename, :original_filename, :file_size, :signature_format,
                :pos_x, :pos_y, :width, :height,
                :location, :reason,
                :ip_address, 'pending',
                :required_signatures, :next_signer,
                :signature_chain
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
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'required_signatures' => count($requiredSigners),
                'next_signer' => $requiredSigners[0],
                'signature_chain' => json_encode([])
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            $this->logger->error('Database error while initializing signature chain: ' . $e->getMessage());
            throw new Exception('İmza zinciri başlatılamadı.');
        }
    }

    /**
     * Sıradaki imzacıyı kontrol et
     */
    public function checkNextSigner($filename, $certificateSerialNumber)
    {
        try {
            $sql = "SELECT next_signer FROM signatures WHERE filename = :filename";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['filename' => $filename]);
            $result = $stmt->fetch();

            if (!$result) {
                throw new Exception('İmza kaydı bulunamadı.');
            }

            if ($result['next_signer'] !== $certificateSerialNumber) {
                throw new Exception('Bu dosyayı imzalama sırası sizde değil.');
            }

            return true;
        } catch (PDOException $e) {
            $this->logger->error('Database error while checking next signer: ' . $e->getMessage());
            throw new Exception('Sıradaki imzacı kontrolü yapılamadı.');
        }
    }

    /**
     * İmza zincirini güncelle
     */
    public function updateSignatureChain($filename, $signatureData, $nextSigner = null)
    {
        try {
            // Mevcut imza zincirini al
            $sql = "SELECT signature_chain, completed_signatures, required_signatures FROM signatures WHERE filename = :filename";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['filename' => $filename]);
            $current = $stmt->fetch();

            if (!$current) {
                throw new Exception('İmza kaydı bulunamadı.');
            }

            // Yeni imza bilgisini ekle
            $chain = json_decode($current['signature_chain'], true) ?: [];
            $chain[] = [
                'certificateName' => $signatureData['certificateName'],
                'certificateIssuer' => $signatureData['certificateIssuer'],
                'certificateSerialNumber' => $signatureData['certificateSerialNumber'],
                'signatureDate' => $signatureData['createdAt'],
                'signature' => $signatureData['signature']
            ];

            // Completed signatures sayısını artır
            $completedSignatures = $current['completed_signatures'] + 1;
            
            // Tüm imzalar tamamlandı mı kontrol et
            $status = ($completedSignatures >= $current['required_signatures']) ? 'completed' : 'pending';

            // Güncelle
            $sql = "UPDATE signatures SET
                signature_chain = :chain,
                completed_signatures = :completed,
                next_signer = :next_signer,
                status = :status
                WHERE filename = :filename";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'chain' => json_encode($chain),
                'completed' => $completedSignatures,
                'next_signer' => $nextSigner,
                'status' => $status,
                'filename' => $filename
            ]);

            return $completedSignatures >= $current['required_signatures'];
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
