<?php
require_once 'config.php';
require_once 'includes/logger.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is an admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: error.php?code=403');
    exit;
}

$message = '';
$messageType = '';

if (isset($_POST['create'])) {
    try {
        require_once __DIR__ . '/tcpdf/tcpdf.php';

        // Initialize TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('PDF İmzalama Sistemi');
        $pdf->SetAuthor($_SESSION['full_name']);
        $pdf->SetTitle('Test PDF');

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 16);

        // Add some content
        $pdf->Cell(0, 10, 'Bu bir test PDF dosyasıdır', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell(0, 10, 'Bu PDF dosyası, imzalama özelliğini test etmek için oluşturulmuştur. İmzalandıktan sonra, bu metnin altında imza bilgileri görünmelidir.', 0, 'L');

        // Save PDF
        $output_file = UPLOAD_DIR . 'test.pdf';
        $pdf->Output($output_file, 'F');

        Logger::getInstance()->info("Test PDF created", [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'file' => $output_file
        ]);

        $message = 'Test PDF başarıyla oluşturuldu: ' . htmlspecialchars($output_file);
        $messageType = 'success';
    } catch (Exception $e) {
        Logger::getInstance()->error("Test PDF creation failed", [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'error' => $e->getMessage()
        ]);

        $message = 'PDF oluşturulurken bir hata oluştu: ' . $e->getMessage();
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test PDF Oluştur - PDF İmzalama Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php
    require_once 'navbar.php';
    ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-file-pdf me-2"></i>
                            Test PDF Oluştur
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $messageType ?>">
                                <i class="fas fa-<?= $messageType === 'success' ? 'check' : 'exclamation' ?>-circle me-2"></i>
                                <?= $message ?>
                            </div>
                        <?php endif; ?>

                        <p class="lead">Bu sayfayı kullanarak imzalama testleri için örnek bir PDF dosyası oluşturabilirsiniz.</p>
                        <p class="text-muted">Oluşturulan PDF dosyası uploads dizininde "test.pdf" adıyla kaydedilecektir.</p>

                        <form method="post" class="mt-4">
                            <div class="d-flex justify-content-between">
                                <a href="admin/signatures.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Yönetim Paneli
                                </a>
                                <button type="submit" name="create" class="btn btn-primary">
                                    <i class="fas fa-file-pdf me-2"></i>
                                    PDF Oluştur
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>