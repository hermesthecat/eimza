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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 40px;
            background: #f0f2f5;
            color: #1a1a1a;
            line-height: 1.6;
        }

        h1 {
            font-size: 2.4rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 2rem;
            text-align: center;
        }

        .step {
            background: white;
            padding: 30px;
            margin: 20px auto;
            max-width: 800px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .step h2 {
            font-size: 1.5rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 1.5rem;
        }

        .button {
            padding: 12px 24px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .button:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        /* File input styling */
        input[type="file"] {
            width: 100%;
            padding: 12px;
            background: #f9fafb;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        input[type="file"]:hover {
            border-color: #3b82f6;
            background: #f3f4f6;
        }

        /* Grup stilleri */
        .group {
            background: #f8fafc;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .group:hover {
            border-color: #3b82f6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .group h3 {
            font-size: 1.2rem;
            font-weight: 500;
            color: #1f2937;
            margin-top: 0;
            margin-bottom: 1rem;
        }

        .signers {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .signers input {
            flex: 1;
            min-width: 200px;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .signers input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .addSigner,
        .addGroup {
            padding: 8px 16px;
            background: #4b5563;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .addSigner:hover,
        .addGroup:hover {
            background: #374151;
            transform: translateY(-1px);
        }

        /* İmza tipi seçimi stilleri */
        .signature-type-selection {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }

        .signature-type-selection p {
            font-size: 1.1rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 1rem;
        }

        .signature-type-selection label {
            display: block;
            padding: 12px;
            margin: 8px 0;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .signature-type-selection label:hover {
            background: #f3f4f6;
            border-color: #3b82f6;
        }

        .signature-type-selection input[type="radio"] {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .tooltip {
            color: #6b7280;
            font-size: 0.9rem;
            margin-left: 10px;
            font-weight: normal;
        }

        /* İmza durumu stilleri */
        .groups-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }

        .group-status {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }

        .group-status:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .group-status h4 {
            font-size: 1.1rem;
            font-weight: 500;
            color: #1f2937;
            margin: 0 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .group-status p {
            color: #374151;
            margin: 0.5rem 0;
        }

        .group-status ul {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .group-status li {
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #374151;
        }

        .group-status li:not(:last-child) {
            margin-bottom: 8px;
        }

        .group-status li.signed {
            background: #dcfce7;
            color: #166534;
        }

        .group-status li.waiting {
            background: #dbeafe;
            color: #1e40af;
        }

        .group-info {
            background: #fff;
            padding: 16px;
            border-radius: 8px;
            margin: 12px 0;
            border: 1px solid #e5e7eb;
        }

        .group-info h4 {
            color: #1f2937;
            margin: 0 0 8px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .status-badge.completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.active {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .signature-date {
            font-size: 0.875rem;
            color: #166534;
        }

        .signature-status {
            font-size: 0.875rem;
            color: #1e40af;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            gap: 20px;
        }
    </style>
</head>

<body>
    <h1>Çoklu İmza Test Sayfası</h1>

    <div class="step">
        <h2>Adım 1: PDF Yükle ve İmza Sürecini Başlat</h2>
        <form method="post" enctype="multipart/form-data">
            <p>
                <input type="file" name="pdf_file" accept=".pdf" required>
            </p>
            
            <!-- İmza Tipi Seçimi -->
            <div class="signature-type-selection">
                <p><strong>İmza Tipi Seçin:</strong></p>
                <label>
                    <input type="radio" name="signature_type" value="chain" checked> İmza Zinciri
                    <span class="tooltip">(İmzalar sırayla, bir grup diğerinden sonra)</span>
                </label>
                <br>
                <label>
                    <input type="radio" name="signature_type" value="parallel"> Paralel İmza
                    <span class="tooltip">(Tüm gruplar aynı anda imzalayabilir)</span>
                </label>
            </div>

            <div id="signatureGroups">
                <strong>İmza Grupları:</strong><br>
                <div class="group" data-group="1">
                    <h3>Grup 1:</h3>
                    <div class="signers">
                        <input type="text" name="groups[1][]" placeholder="TC Kimlik No" required>
                        <button type="button" class="addSigner">+ İmzacı Ekle</button>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="addGroup">+ Yeni Grup Ekle</button>
                <input type="submit" name="init_process" value="İmza Sürecini Başlat" class="button">
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // İmza tipi seçimi değiştiğinde
                    document.querySelectorAll('input[name="signature_type"]').forEach(function(radio) {
                        radio.addEventListener('change', function(e) {
                            const signatureType = e.target.value;
                            const groups = document.querySelectorAll('.group');
                            
                            groups.forEach(function(group) {
                                const groupTitle = group.querySelector('h3');
                                const groupNum = group.dataset.group;
                                
                                if (signatureType === 'parallel') {
                                    groupTitle.textContent = `Paralel Grup ${groupNum}:`;
                                } else {
                                    groupTitle.textContent = `Grup ${groupNum}:`;
                                }
                            });
                        });
                    });

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
                        const signatureType = document.querySelector('input[name="signature_type"]:checked').value;

                        const groupDiv = document.createElement('div');
                        groupDiv.className = 'group';
                        groupDiv.dataset.group = newGroupNum;

                        const groupTitle = signatureType === 'parallel' ? `Paralel Grup ${newGroupNum}:` : `Grup ${newGroupNum}:`;

                        groupDiv.innerHTML = `
                            <h3>${groupTitle}</h3>
                            <div class="signers">
                                <input type="text" name="groups[${newGroupNum}][]" placeholder="TC Kimlik No" required>
                                <button type="button" class="addSigner">+ İmzacı Ekle</button>
                            </div>
                        `;

                        document.querySelector('#signatureGroups').appendChild(groupDiv);
                    });
                });
            </script>
        </form>
    </div>

    <?php
    if (isset($_POST['init_process']) && isset($_FILES['pdf_file'])) {
        $file = $_FILES['pdf_file'];
        $groups = $_POST['groups'];
        $signatureType = $_POST['signature_type'] ?? 'chain';

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

            // İmza sürecini başlat
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
                $signatureGroups,
                $signatureType
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
                echo "<h4>Grup " . $groupNum . "</h4>";
                
                // Grup durumu badge'i
                $statusClass = '';
                $statusText = '';
                switch ($groupStatus[$groupNum]) {
                    case 'completed':
                        $statusClass = 'completed';
                        $statusText = 'Tamamlandı';
                        break;
                    case 'pending':
                        $statusClass = $groupNum === $currentGroup ? 'active' : 'pending';
                        $statusText = $groupNum === $currentGroup ? 'Aktif Grup' : 'Bekliyor';
                        break;
                }
                echo "<div class='status-badge " . $statusClass . "'>" . $statusText . "</div>";

                // İmzacıları göster
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

                    $class = '';
                    $status = '';
                    if ($signed) {
                        $class = 'signed';
                        $status = '<span class="signature-date">✓ İmzalandı <br>(' . $signatureDate . ')</span>';
                    } else if ($groupNum === $currentGroup) {
                        $class = 'waiting';
                        $status = '<span class="signature-status">İmza Bekleniyor</span>';
                    }

                    echo "<li class='" . $class . "'>";
                    echo "<strong>TC: " . $signer . "</strong>";
                    echo $status;
                    echo "</li>";
                }
                echo "</ul>";
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