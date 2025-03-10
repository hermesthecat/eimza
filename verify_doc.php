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
    <title>Çoklu İmza Sayfası</title>
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
    <link href="<?php echo $domain; ?>/assets/css/style.css" rel="stylesheet">
    <style>
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
    <?php
    require_once 'navbar.php';
    ?>

    <div class="container py-4">
        <h1>Belge Doğrulama</h1>



        <?php
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