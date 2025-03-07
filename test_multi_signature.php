<?php
require_once 'config.php';
require_once 'includes/logger.php';
require_once 'includes/SignatureManager.php';

// Initialize signature manager
$signatureManager = new SignatureManager($db, Logger::getInstance());

?>
<!DOCTYPE html>
<html>
<head>
    <title>Çoklu İmza Test Sayfası</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .step { border: 1px solid #ccc; padding: 20px; margin: 10px 0; }
        .button { padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Çoklu İmza Test Sayfası</h1>

    <div class="step">
        <h2>Adım 1: PDF Yükle ve İmza Zinciri Başlat</h2>
        <form method="post" enctype="multipart/form-data">
            <p>
                <input type="file" name="pdf_file" accept=".pdf" required>
            </p>
            <p>
                <strong>İmzalayacak Kişiler (TC Kimlik No):</strong><br>
                <input type="text" name="signers[]" placeholder="1. İmzacı TC No" required><br>
                <input type="text" name="signers[]" placeholder="2. İmzacı TC No"><br>
                <input type="text" name="signers[]" placeholder="3. İmzacı TC No">
            </p>
            <input type="submit" name="init_chain" value="İmza Zinciri Başlat" class="button">
        </form>
    </div>

    <?php
    if (isset($_POST['init_chain']) && isset($_FILES['pdf_file'])) {
        $file = $_FILES['pdf_file'];
        $signers = array_filter($_POST['signers']); // Boş olmayan imzacıları al

        if (count($signers) > 0) {
            $fileInfo = [
                'filename' => uniqid() . '.pdf',
                'original_name' => $file['name'],
                'size' => $file['size']
            ];

            // PDF'i uploads klasörüne taşı
            move_uploaded_file($file['tmp_name'], 'uploads/' . $fileInfo['filename']);

            // İmza zincirini başlat
            $id = $signatureManager->initSignatureChain(
                $fileInfo,
                [
                    'format' => 'PadesBes',
                    'x' => 10,
                    'y' => 10,
                    'width' => 190,
                    'height' => 50,
                    'location' => 'Test Location',
                    'reason' => 'Test Reason'
                ],
                $signers
            );

            echo "<div class='step'>";
            echo "<h3>İmza Zinciri Başlatıldı</h3>";
            echo "<p>Dosya ID: " . $id . "</p>";
            echo "<p>Sıradaki İmzacı: " . $signers[0] . "</p>";
            echo "<p>Toplam İmzacı Sayısı: " . count($signers) . "</p>";
            echo "</div>";
        }
    }

    // İmza durumunu göster
    if (isset($_GET['check_status'])) {
        $filename = $_GET['check_status'];
        $record = $signatureManager->getSignatureRecord($filename);

        if ($record) {
            echo "<div class='step'>";
            echo "<h3>İmza Durumu</h3>";
            echo "<p>Dosya: " . $record['original_filename'] . "</p>";
            echo "<p>Durum: " . $record['status'] . "</p>";
            echo "<p>Tamamlanan İmza Sayısı: " . $record['completed_signatures'] . "/" . $record['required_signatures'] . "</p>";
            
            if ($record['signature_chain']) {
                $chain = json_decode($record['signature_chain'], true);
                echo "<h4>İmza Geçmişi:</h4>";
                foreach ($chain as $index => $signature) {
                    echo "<p>" . ($index + 1) . ". İmza:<br>";
                    echo "İmzalayan: " . $signature['certificateName'] . "<br>";
                    echo "Tarih: " . $signature['signatureDate'] . "</p>";
                }
            }
            
            if ($record['status'] !== 'completed') {
                echo "<p>Sıradaki İmzacı: " . $record['next_signer'] . "</p>";
            }
            
            echo "</div>";
        }
    }
    ?>

    <div class="step">
        <h2>İmza Durumu Kontrol Et</h2>
        <form method="get">
            <input type="text" name="check_status" placeholder="Dosya adı" required>
            <input type="submit" value="Durumu Kontrol Et" class="button">
        </form>
    </div>
</body>
</html>
