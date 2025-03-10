<?php
require_once 'config.php';
require_once 'includes/logger.php';
require_once 'includes/SignatureManager.php';
require_once 'includes/UserManager.php';

// Initialize managers
$signatureManager = new SignatureManager($db, Logger::getInstance());
$userManager = new UserManager($db, Logger::getInstance());

// Fetch all users
$users = $userManager->getAllUsers();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çoklu İmza Test Sayfası</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
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

        .select-container {
            flex: 1;
            min-width: 300px;
            margin-bottom: 10px;
        }

        .signer-select {
            width: 100%;
        }

        /* Fix Select2 container width */
        .select2 {
            width: 100% !important;
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

        /* Select2 customization */
        .select2-container--bootstrap-5 .select2-selection {
            border-color: #d1d5db;
            min-height: 42px;
        }

        .select2-container--bootstrap-5 .select2-selection--single {
            padding-top: 5px;
        }

        /* Status styles */
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
    </style>
</head>

<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-file-signature me-2"></i>
                PDF İmzalama Sistemi
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>
                            Ana Sayfa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="sign_document.php">
                            <i class="fas fa-file-signature me-1"></i>
                            İmza Bekleyenler
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="test_multi_signature.php">
                            <i class="fas fa-users me-1"></i>
                            Çoklu İmza
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <h1>Çoklu İmza Test Sayfası</h1>

        <div class="step">
            <h2>PDF Yükle ve İmza Sürecini Başlat</h2>
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
                    <br>
                    <label>
                        <input type="radio" name="signature_type" value="mixed"> Karışık İmza
                        <span class="tooltip">(Gruplar sıralı, grup içi paralel imzalama)</span>
                    </label>
                </div>

                <div id="signatureGroups">
                    <strong>İmza Grupları:</strong><br>
                    <div class="group" data-group="1">
                        <h3>Grup 1:</h3>
                        <div class="signers">
                            <div class="select-container mb-2">
                                <select name="groups[1][]" class="form-select signer-select" required>
                                    <option value="">İmzacı Seçin</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= htmlspecialchars($user['tckn']) ?>">
                                            <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['tckn']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="addSigner">
                                <i class="fas fa-plus me-1"></i> İmzacı Ekle
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="addGroup">
                        <i class="fas fa-layer-group me-1"></i> Yeni Grup Ekle
                    </button>
                    <input type="submit" name="init_process" value="İmza Sürecini Başlat" class="button">
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Initialize Select2
                        // Function to create and initialize a new select container
                        function createSignerSelect(groupNum) {
                            const container = document.createElement('div');
                            container.className = 'select-container mb-2';
                            container.innerHTML = `
                                <select name="groups[${groupNum}][]" class="form-select signer-select" required>
                                    <option value="">İmzacı Seçin</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= htmlspecialchars($user['tckn']) ?>">
                                            <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['tckn']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            `;

                            const select = container.querySelector('select');
                            $(select).select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                placeholder: 'İmzacı Seçin',
                                dropdownParent: container
                            });

                            return container;
                        }

                        // Initialize initial select element
                        document.querySelectorAll('.signer-select').forEach(select => {
                            const existingContainer = select.closest('.select-container');
                            if (existingContainer) return; // Skip if already in a container

                            // Create container and move select into it
                            const container = document.createElement('div');
                            container.className = 'select-container mb-2';
                            select.parentNode.insertBefore(container, select);
                            container.appendChild(select);

                            // Initialize Select2
                            $(select).select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                placeholder: 'İmzacı Seçin',
                                dropdownParent: container
                            });
                        });

                        // İmza tipi seçimi değiştiğinde
                        document.querySelectorAll('input[name="signature_type"]').forEach(function(radio) {
                            radio.addEventListener('change', function(e) {
                                const signatureType = e.target.value;
                                const groups = document.querySelectorAll('.group');

                                groups.forEach(function(group) {
                                    const groupTitle = group.querySelector('h3');
                                    const groupNum = group.dataset.group;

                                    let title = `Grup ${groupNum}:`;
                                    if (signatureType === 'parallel') {
                                        title = `Paralel Grup ${groupNum}:`;
                                    } else if (signatureType === 'mixed') {
                                        title = `Karma Grup ${groupNum}:`;
                                    }
                                    groupTitle.textContent = title;
                                });
                            });
                        });

                        // Empty - these functions are no longer needed

                        // Handle add signer button clicks
                        document.addEventListener('click', function(e) {
                            if (e.target.classList.contains('addSigner')) {
                                const group = e.target.closest('.group');
                                if (!group) return;

                                const signersDiv = group.querySelector('.signers');
                                if (!signersDiv) return;

                                const groupNum = group.dataset.group;
                                const container = createSignerSelect(groupNum);
                                
                                signersDiv.insertBefore(container, e.target);
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

                            let groupTitle = `Grup ${newGroupNum}:`;
                            if (signatureType === 'parallel') {
                                groupTitle = `Paralel Grup ${newGroupNum}:`;
                            } else if (signatureType === 'mixed') {
                                groupTitle = `Karma Grup ${newGroupNum}:`;
                            }

                            // Create group structure
                            groupDiv.innerHTML = `
                                <h3>${groupTitle}</h3>
                                <div class="signers">
                                    <button type="button" class="addSigner">
                                        <i class="fas fa-plus me-1"></i> İmzacı Ekle
                                    </button>
                                </div>
                            `;

                            // Add to DOM
                            document.querySelector('#signatureGroups').appendChild(groupDiv);

                            // Add initial select
                            const signersDiv = groupDiv.querySelector('.signers');
                            const addButton = groupDiv.querySelector('.addSigner');
                            const container = createSignerSelect(newGroupNum);
                            signersDiv.insertBefore(container, addButton);
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
                    
                    $signerNames = [];
                    foreach ($group['signers'] as $tckn) {
                        foreach ($users as $user) {
                            if ($user['tckn'] === $tckn) {
                                $signerNames[] = $user['full_name'] . ' (' . $user['tckn'] . ')';
                                break;
                            }
                        }
                    }
                    
                    echo "<p>İmzacılar: " . implode(', ', $signerNames) . "</p>";
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
                        $signerName = '';

                        // İmzacı adını bul
                        foreach ($users as $user) {
                            if ($user['tckn'] === $signer) {
                                $signerName = $user['full_name'];
                                break;
                            }
                        }

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
                        echo "<strong>" . $signerName . "</strong> (TC: " . $signer . ")";
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
            <div class="card">
                <div class="card-header">
                    <h2 class="h5 mb-0">
                        <i class="fas fa-search me-2"></i>
                        İmza Durumu Kontrol Et
                    </h2>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="check_status" class="form-label">
                                <i class="fas fa-file-signature me-1"></i>
                                Dosya Adı
                            </label>
                            <input type="text"
                                class="form-control"
                                id="check_status"
                                name="check_status"
                                placeholder="İmza durumunu kontrol etmek istediğiniz dosyanın adını girin"
                                required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>
                                Durumu Kontrol Et
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</body>

</html>