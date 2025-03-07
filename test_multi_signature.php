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
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .step { border: 1px solid #ddd; padding: 20px; margin: 10px 0; background: white; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .button { padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; border-radius: 3px; }
        .button:hover { background: #45a049; }
        
        /* Grup stilleri */
        .group { border: 1px solid #eee; padding: 15px; margin: 10px 0; border-radius: 3px; }
        .group h3 { margin-top: 0; color: #333; }
        .signers { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .signers input { padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .addSigner, .addGroup {
            padding: 5px 10px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .addSigner:hover, .addGroup:hover { background: #1976D2; }
        
        /* İmza durumu stilleri */
        .groups-status { display: flex; flex-wrap: wrap; gap: 20px; }
        .group-status {
            flex: 1;
            min-width: 300px;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 3px;
            background: #f9f9f9;
        }
        .group-status h4 { margin-top: 0; color: #333; }
        .group-status ul { list-style: none; padding: 0; }
        .group-status li { padding: 5px 0; }
        .group-status li:not(:last-child) { border-bottom: 1px solid #eee; }
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
            <div id="signatureGroups">
                <strong>İmza Grupları:</strong><br>
                <div class="group" data-group="1">
                    <h3>Grup 1:</h3>
                    <div class="signers">
                        <input type="text" name="groups[1][]" placeholder="TC Kimlik No" required>
                        <button type="button" class="addSigner">+ İmzacı Ekle</button>
                    </div>
                </div>
                <button type="button" class="addGroup">+ Yeni Grup Ekle</button>
            </div>
            <input type="submit" name="init_process" value="İmza Sürecini Başlat" class="button">

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Yeni imzacı ekleme
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('addSigner')) {
                        const group = e.target.closest('.group');
                        const signersDiv = group.querySelector('.signers');
                        const groupNum = group.dataset.group;
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.name = `groups[${groupNum}][]`;
                        input.placeholder = 'TC Kimlik No';
                        signersDiv.insertBefore(input, e.target);
                    }
                });

                // Yeni grup ekleme
                document.querySelector('.addGroup').addEventListener('click', function() {
                    const groups = document.querySelectorAll('.group');
                    const newGroupNum = groups.length + 1;
                    
                    const groupDiv = document.createElement('div');
                    groupDiv.className = 'group';
                    groupDiv.dataset.group = newGroupNum;
                    
                    groupDiv.innerHTML = `
                        <h3>Grup ${newGroupNum}:</h3>
                        <div class="signers">
                            <input type="text" name="groups[${newGroupNum}][]" placeholder="TC Kimlik No" required>
                            <button type="button" class="addSigner">+ İmzacı Ekle</button>
                        </div>
                    `;
                    
                    document.querySelector('#signatureGroups').insertBefore(
                        groupDiv,
                        this
                    );
                });
            });
            </script>
        </form>
    </div>

    <?php
    if (isset($_POST['init_process']) && isset($_FILES['pdf_file'])) {
        $file = $_FILES['pdf_file'];
        $groups = $_POST['groups'];

        // Grupları düzenle
        $signatureGroups = [];
        foreach ($groups as $groupIndex => $signers) {
            $signers = array_filter($signers); // Boş değerleri kaldır
            if (!empty($signers)) {
                $signatureGroups[] = [
                    'group_id' => $groupIndex,
                    'signers' => array_values($signers)
                ];
            }
        }

        if (count($signatureGroups) > 0) {
            $fileInfo = [
                'filename' => uniqid() . '.pdf',
                'original_name' => $file['name'],
                'size' => $file['size']
            ];

            // PDF'i uploads klasörüne taşı
            move_uploaded_file($file['tmp_name'], 'uploads/' . $fileInfo['filename']);

            // Karma imza sürecini başlat
            $id = $signatureManager->initSignatureProcess(
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
                $signatureGroups
            );

            // Başarılı mesajı göster
            echo "<div class='step'>";
            echo "<h3>İmza Süreci Başlatıldı</h3>";
            echo "<p>Dosya ID: " . $id . "</p>";
            
            foreach ($signatureGroups as $index => $group) {
                echo "<div class='group-info'>";
                echo "<h4>Grup " . ($index + 1) . ":</h4>";
                echo "<p>İmzacılar: " . implode(', ', $group['signers']) . "</p>";
                echo "</div>";
            }
            
            echo "<p>İlk Grup İmzacıları: " . implode(', ', $signatureGroups[0]['signers']) . "</p>";
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
            echo "<p>Genel Durum: " . $record['status'] . "</p>";

            $signatureGroups = json_decode($record['signature_groups'], true);
            $groupSignatures = json_decode($record['group_signatures'], true);
            $groupStatus = json_decode($record['group_status'], true);
            $currentGroup = $record['current_group'];

            echo "<div class='groups-status'>";
            foreach ($signatureGroups as $index => $group) {
                $groupNum = $index + 1;
                echo "<div class='group-status'>";
                echo "<h4>Grup " . $groupNum . ":</h4>";
                echo "<p>Durum: " . $groupStatus[$groupNum] . "</p>";
                
                // İmzacıları göster
                echo "<p>İmzacılar:</p>";
                echo "<ul>";
                foreach ($group['signers'] as $signer) {
                    $signed = false;
                    $signatureDate = '';
                    
                    // İmza kontrolü
                    if (isset($groupSignatures[$groupNum])) {
                        foreach ($groupSignatures[$groupNum] as $signature) {
                            if ($signature['certificateSerialNumber'] === $signer) {
                                $signed = true;
                                $signatureDate = $signature['signatureDate'];
                                break;
                            }
                        }
                    }
                    
                    echo "<li>";
                    echo "TC: " . $signer;
                    if ($signed) {
                        echo " ✓ (" . $signatureDate . ")";
                    } else if ($groupNum === $currentGroup) {
                        echo " (Bekleniyor)";
                    }
                    echo "</li>";
                }
                echo "</ul>";
                
                // Aktif grup bilgisi
                if ($groupNum === $currentGroup) {
                    echo "<p><strong>Aktif Grup</strong></p>";
                }
                
                echo "</div>";
            }
            echo "</div>";
            
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
