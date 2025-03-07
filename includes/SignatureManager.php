<?php
class SignatureManager {
    private $db;
    private $logger;

    public function __construct(PDO $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * İmza işlemini veritabanına kaydet
     */
    public function createSignatureRecord($fileInfo, $signatureOptions) {
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
    public function updateSignatureResult($filename, $signatureData) {
        try {
            $sql = "UPDATE signatures SET 
                status = 'completed',
                certificate_name = :cert_name,
                certificate_issuer = :cert_issuer,
                certificate_serial_number = :cert_serial,
                signature_date = :sig_date,
                signature_data = :sig_data
                WHERE filename = :filename";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'cert_name' => $signatureData['certificateName'],
                'cert_issuer' => $signatureData['certificateIssuer'],
                'cert_serial' => $signatureData['certificateSerialNumber'],
                'sig_date' => date('Y-m-d H:i:s', strtotime($signatureData['createdAt'])),
                'sig_data' => $signatureData['signature'],
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
    public function markAsFailed($filename, $errorMessage) {
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
    public function getSignatureRecord($filename) {
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
    public function getRecentSignatures($limit = 10, $offset = 0) {
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
    public function getTotalSignatures() {
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
    public function searchSignatures($filters = [], $limit = 10, $offset = 0) {
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