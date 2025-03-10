<?php
require_once 'config.php';
require_once 'includes/logger.php';
require_once 'includes/SignatureManager.php';
require_once 'includes/UserManager.php';

// Initialize managers
$signatureManager = new SignatureManager($db, Logger::getInstance());
$userManager = new UserManager($db, Logger::getInstance());

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current user's TCKN
$userId = $_SESSION['user_id'];
$currentUser = $userManager->getUserById($userId);

// Log user information
$logger = Logger::getInstance();
$logger->info('User accessing waiting_sign.php', [
    'user_id' => $userId,
    'tckn' => $currentUser['tckn']
]);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İmza Bekleyen Belgelerim</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .documents-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
        }

        .document-item {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .document-item:last-child {
            border-bottom: none;
        }

        .document-info {
            flex: 1;
        }

        .document-title {
            font-weight: 500;
            color: #111827;
            margin-bottom: 4px;
        }

        .document-meta {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .sign-button {
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sign-button:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .no-documents {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-left: 8px;
        }

        .status-badge.waiting {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.current {
            background: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>

<body class="bg-light">
    <?php
    require_once 'navbar.php';
    ?>

    <div class="container py-4">
        <h1 class="mb-4">İmza Bekleyen Belgelerim</h1>

        <?php
        // Get pending signatures for current user
        $pendingDocuments = $signatureManager->getPendingSignatures($currentUser['tckn']);

        // Log pending documents information
        $logger->info('Pending documents for user', [
            'user_id' => $userId,
            'tckn' => $currentUser['tckn'],
            'pending_count' => count($pendingDocuments)
        ]);

        if (!empty($pendingDocuments)): ?>
            <div class="documents-list">
                <?php foreach ($pendingDocuments as $document): ?>
                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-title">
                                <?= htmlspecialchars($document['original_filename']) ?>
                                <?php if ($document['current_group_signer']): ?>
                                    <span class="status-badge current">Sıra Sizde</span>
                                <?php else: ?>
                                    <span class="status-badge waiting">Bekliyor</span>
                                <?php endif; ?>
                            </div>
                            <div class="document-meta">
                                <span><i class="far fa-calendar me-1"></i><?= date('d.m.Y H:i', strtotime($document['created_at'])) ?></span>
                                <span class="ms-3"><i class="fas fa-layer-group me-1"></i>Grup <?= $document['group_num'] ?></span>
                            </div>
                        </div>
                        <?php if ($document['current_group_signer']): ?>
                            <button class="sign-button" onclick="location.href='complete_signature.php?id=<?= $document['id'] ?>'">
                                <i class="fas fa-signature me-1"></i>
                                İmzala
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="documents-list">
                <div class="no-documents">
                    <i class="far fa-check-circle fa-3x mb-3"></i>
                    <p class="mb-0">İmza bekleyen belgeniz bulunmamaktadır.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto refresh the page every 30 seconds to show new documents
        setInterval(function() {
            location.reload();
        }, 30000);

        // Show confirmation dialog before signing
        document.addEventListener('click', function(e) {
            if (e.target.closest('.sign-button')) {
                if (!confirm('Bu belgeyi imzalamak istediğinize emin misiniz?')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>

</html>