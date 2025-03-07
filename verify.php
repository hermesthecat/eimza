<?php
require_once 'config.php';
require_once 'includes/logger.php';
require_once 'includes/SignatureManager.php';
require_once('tcpdf/tcpdf.php');

class SignedPDF extends TCPDF {
    protected $signatureInfo = [];
    
    public function setSignatureInfo($info) {
        $this->signatureInfo = $info;
    }
    
    public function Footer() {
        if (!empty($this->signatureInfo)) {
            $this->SetY(-30);
            $this->SetFont('helvetica', '', 8);
            $this->Cell(0, 10, 'İmzalayan: ' . ($this->signatureInfo['certificate_name'] ?? ''), 0, 1, 'L');
            $this->Cell(0, 10, 'İmza Tarihi: ' . ($this->signatureInfo['signature_date'] ?? ''), 0, 1, 'L');
            $this->Cell(0, 10, 'Sertifika No: ' . ($this->signatureInfo['certificate_serial_number'] ?? ''), 0, 1, 'L');
        }
    }
}

try {
    // Initialize signature manager
    $signatureManager = new SignatureManager($db, Logger::getInstance());

    // Get POST data
    $data = file_get_contents('php://input');
    $response = json_decode($data, true);

    if (!$response) {
        throw new Exception('Geçersiz imza yanıtı');
    }

    // Log the signature response
    Logger::getInstance()->info('Signature response received: ' . $data);

    // Validate signature response
    if (!isset($response['resources']) || empty($response['resources'])) {
        throw new Exception('İmza yanıtında kaynak bulunamadı');
    }

    $resource = $response['resources'][0];

    // Validate signature
    if (!isset($resource['signature'])) {
        throw new Exception('İmza bilgisi bulunamadı');
    }

    // Extract filename from source URL
    $sourceUrl = $resource['source'];
    $filename = basename(parse_url($sourceUrl, PHP_URL_PATH));

    // Update signature record in database
    $signatureManager->updateSignatureResult($filename, [
        'certificateName' => $response['certificateName'] ?? null,
        'certificateIssuer' => $response['certificateIssuer'] ?? null,
        'certificateSerialNumber' => $response['certificateSerialNumber'] ?? null,
        'createdAt' => $response['createdAt'] ?? date('Y-m-d H:i:s'),
        'signature' => $resource['signature']
    ]);

    // Get updated signature record
    $signatureRecord = $signatureManager->getSignatureRecord($filename);

    if (!$signatureRecord) {
        throw new Exception('İmza kaydı bulunamadı');
    }

    // Store signature details in session for verification
    session_start();
    $_SESSION['signature_info'] = [
        'id' => $signatureRecord['id'],
        'filename' => $signatureRecord['filename'],
        'original_filename' => $signatureRecord['original_filename'],
        'certificate_name' => $signatureRecord['certificate_name'],
        'certificate_issuer' => $signatureRecord['certificate_issuer'],
        'certificate_serial_number' => $signatureRecord['certificate_serial_number'],
        'signature_date' => $signatureRecord['signature_date'],
        'signature_location' => $signatureRecord['signature_location'],
        'signature_reason' => $signatureRecord['signature_reason']
    ];

    // Create signed PDF
    try {
        // Download original PDF
        $pdfContent = file_get_contents($sourceUrl);
        if ($pdfContent === false) {
            throw new Exception('Orijinal PDF indirilemedi');
        }

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tempFile, $pdfContent);

        // Initialize PDF
        $pdf = new SignedPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('TCPDF');
        $pdf->SetAuthor($signatureRecord['certificate_name']);
        $pdf->SetTitle('İmzalı Belge');

        // Set signature information for footer
        $pdf->setSignatureInfo([
            'certificate_name' => $signatureRecord['certificate_name'],
            'signature_date' => $signatureRecord['signature_date'],
            'certificate_serial_number' => $signatureRecord['certificate_serial_number']
        ]);

        // Add content from original PDF
        $originalPdf = file_get_contents($tempFile);
        $pdf->AddPage();
        $pdf->Image('@'.$originalPdf, 0, 0, 210); // A4 genişliği 210mm

        // Create signed directory if not exists
        if (!is_dir('signed')) {
            mkdir('signed', 0777, true);
        }

        // Save signed PDF
        $signedPdfPath = 'signed/' . pathinfo($filename, PATHINFO_FILENAME) . '_signed.pdf';
        $pdf->Output($signedPdfPath, 'F');

        // Clean up temporary file
        unlink($tempFile);

        // Return success response with signed PDF path
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'İmza başarıyla alındı ve PDF oluşturuldu',
            'info' => [
                'name' => $signatureRecord['certificate_name'],
                'issuer' => $signatureRecord['certificate_issuer'],
                'date' => $signatureRecord['signature_date'],
                'signed_pdf' => $signedPdfPath
            ]
        ]);
    } catch (Exception $e) {
        // Log PDF creation error
        Logger::getInstance()->error('PDF creation error: ' . $e->getMessage());
        
        // Return error response but still indicate successful signature
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'İmza başarıyla alındı fakat PDF oluşturulamadı',
            'error' => $e->getMessage(),
            'info' => [
                'name' => $signatureRecord['certificate_name'],
                'issuer' => $signatureRecord['certificate_issuer'],
                'date' => $signatureRecord['signature_date']
            ]
        ]);
    }
} catch (Exception $e) {
    // Log error
    Logger::getInstance()->error('Signature verification error: ' . $e->getMessage());

    // Mark signature as failed if filename is available
    if (isset($signatureManager) && isset($filename)) {
        $signatureManager->markAsFailed($filename, $e->getMessage());
    }

    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
