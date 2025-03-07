<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF İmzalama Uygulaması</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-file-signature me-2"></i>
                PDF İmzalama Sistemi
            </a>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0 text-white">
                            <i class="fas fa-file-signature me-2"></i>PDF İmzalama
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="sign.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="mb-4">
                                <label for="pdfFile" class="form-label">
                                    <i class="fas fa-file-pdf me-2"></i>PDF Dosyası Seçin
                                </label>
                                <div class="input-group">
                                    <input type="file" class="form-control" id="pdfFile" name="pdfFile" accept=".pdf" required>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Maksimum dosya boyutu: 10MB
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-signature me-2"></i>İmza Formatı
                                </label>
                                <select class="form-select" name="signatureFormat" required>
                                    <option value="PadesBes">PadesBes (Basit Elektronik İmza)</option>
                                    <option value="PadesT">PadesT (Zaman Damgalı)</option>
                                </select>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    PadesT formatı zaman damgası içerir ve daha güvenlidir.
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5 class="mb-3">
                                    <i class="fas fa-cog me-2"></i>İmza Görünüm Ayarları
                                </h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">X Pozisyonu (px)</label>
                                        <input type="number" class="form-control" name="posX" value="10" min="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Y Pozisyonu (px)</label>
                                        <input type="number" class="form-control" name="posY" value="10" min="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Genişlik (px)</label>
                                        <input type="number" class="form-control" name="width" value="200" min="50">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Yükseklik (px)</label>
                                        <input type="number" class="form-control" name="height" value="50" min="20">
                                    </div>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Sıfırla
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-signature me-2"></i>İmzala
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div class="modal fade" id="progressModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <h5>İmzalama işlemi devam ediyor...</h5>
                    <p class="text-muted mb-0">Lütfen bekleyin...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-3 mt-5">
        <div class="container">
            <small>
                &copy; <?php echo date('Y'); ?> PDF İmzalama Sistemi
                <i class="fas fa-code mx-2"></i>
                Tüm hakları saklıdır.
            </small>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Show loading modal on form submit
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            // Basic client-side validation
            const fileInput = document.getElementById('pdfFile');
            const maxSize = 10 * 1024 * 1024; // 10MB in bytes

            if (fileInput.files[0].size > maxSize) {
                e.preventDefault();
                alert('Dosya boyutu çok büyük! Maksimum boyut: 10MB');
                return;
            }

            if (!fileInput.files[0].type.includes('pdf')) {
                e.preventDefault();
                alert('Lütfen sadece PDF dosyası yükleyin!');
                return;
            }

            var myModal = new bootstrap.Modal(document.getElementById('progressModal'));
            myModal.show();
        });

        // Reset form function
        function resetForm() {
            document.getElementById('uploadForm').reset();
        }

        // File size validation on change
        document.getElementById('pdfFile').addEventListener('change', function(e) {
            const maxSize = 10 * 1024 * 1024; // 10MB in bytes
            if (this.files[0].size > maxSize) {
                this.value = ''; // Clear the input
                alert('Dosya boyutu çok büyük! Maksimum boyut: 10MB');
            }
        });
    </script>
</body>
</html>