<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-file-signature me-2"></i>
            PDF İmzalama Sistemi
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $domain; ?>/index.php">
                        <i class="fas fa-home me-1"></i>
                        Ana Sayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $domain; ?>/test_multi_signature.php">
                        <i class="fas fa-file-signature me-1"></i>
                        Çoklu İmza
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="signDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-file-signature me-1"></i>
                        İmza Paneli
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item active" href="<?php echo $domain; ?>/waiting_sign.php">
                                <i class="fas fa-file-signature me-1"></i>
                                İmzamda Bekleyenler
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $domain; ?>/completed_sign.php">
                                <i class="fas fa-file-signature me-1"></i>
                                Tamamlanan İmzalar
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $domain; ?>/sign_document.php">
                                <i class="fas fa-file-signature me-1"></i>
                                İmzada Bekleyenler v2
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs me-1"></i>
                        Yönetim Paneli
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item active" href="<?php echo $domain; ?>/admin/index.php">
                                <i class="fas fa-file-signature me-1"></i>
                                İmza Kayıtları
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $domain; ?>/admin/users.php">
                                <i class="fas fa-users me-1"></i>
                                Kullanıcılar
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $domain; ?>/check.php">
                                <i class="fas fa-tasks me-1"></i>
                                Sistem Kontrol
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $domain; ?>/create_test_pdf.php">
                                <i class="fas fa-file-pdf me-1"></i>
                                Test PDF Oluştur
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i>
                        <?= htmlspecialchars($_SESSION['full_name']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <h6 class="dropdown-header">TCKN: <?= htmlspecialchars($_SESSION['tckn']) ?></h6>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo $domain; ?>/logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>
                                Çıkış Yap
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>