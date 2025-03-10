<?php
require_once 'config.php';
require_once 'includes/logger.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
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

<?php
require_once 'navbar.php';
?>

    <div class="container py-5">
        <!-- Alert Messages -->
        <div id="alertArea"></div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-file-signature me-2"></i>PDF İmzalama
                        </h4>
                    </div>
                    <div class="card-body">
                        <form id="uploadForm">
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
                    <h5>İşlem devam ediyor...</h5>
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
        // Show alert function
        function showAlert(message, type = 'danger') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'danger' ? 'exclamation-circle' : 'check-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            document.getElementById('alertArea').innerHTML = alertHtml;
        }

        // Handle form submission
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // Basic validation
            const fileInput = document.getElementById('pdfFile');
            const maxSize = 10 * 1024 * 1024; // 10MB

            if (!fileInput.files.length) {
                showAlert('Lütfen bir PDF dosyası seçin');
                return;
            }

            if (fileInput.files[0].size > maxSize) {
                showAlert('Dosya boyutu çok büyük! Maksimum boyut: 10MB');
                return;
            }

            // Show progress modal
            const progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
            progressModal.show();

            try {
                // Create FormData
                const formData = new FormData(this);

                // Send file to server
                const response = await fetch('sign.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error || 'Beklenmeyen bir hata oluştu');
                }

                // Hide progress modal
                progressModal.hide();

                // Create hidden iframe for sign protocol
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                document.body.appendChild(iframe);
                iframe.src = result.signUrl;

                // Remove iframe after a delay
                setTimeout(() => {
                    document.body.removeChild(iframe);
                }, 1000);

            } catch (error) {
                progressModal.hide();
                showAlert(error.message);
            }
        });

        // Reset form function
        function resetForm() {
            document.getElementById('uploadForm').reset();
            document.getElementById('alertArea').innerHTML = '';
        }

        // File size validation on change
        document.getElementById('pdfFile').addEventListener('change', function(e) {
            const maxSize = 10 * 1024 * 1024; // 10MB
            if (this.files[0] && this.files[0].size > maxSize) {
                this.value = ''; // Clear the input
                showAlert('Dosya boyutu çok büyük! Maksimum boyut: 10MB');
            }
        });
    </script>
</body>

</html>