<?php
require_once 'config.php';
require_once 'includes/logger.php';
require_once 'includes/UserManager.php';

// Initialize UserManager
$userManager = new UserManager($db, Logger::getInstance());

// Already logged in users should be redirected to index.php
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $domain . '/index.php');
    exit;
}

$error = '';
$success = '';

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // CSRF check
    if (!SecurityHelper::validateCsrfToken($csrf_token)) {
        $error = 'Geçersiz form gönderimi.';
        Logger::getInstance()->warning("CSRF validation failed", [
            'ip' => SecurityHelper::getClientIP(),
            'username' => $username
        ]);
    }
    // Rate limiting check
    elseif (!SecurityHelper::checkRateLimit('login_' . $username, LOGIN_ATTEMPTS_LIMIT, LOGIN_ATTEMPTS_PERIOD)) {
        $error = 'Çok fazla başarısız deneme. Lütfen daha sonra tekrar deneyin.';
        Logger::getInstance()->warning("Rate limit exceeded for login attempts", [
            'username' => $username,
            'ip' => SecurityHelper::getClientIP()
        ]);
    } else {
        $user = $userManager->getUserByUsername($username);

        if ($user && $userManager->verifyPassword($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['tckn'] = $user['tckn'];

            // Update last login time
            $userManager->updateLastLogin($user['id']);

            // Log successful login
            Logger::getInstance()->info("User login successful", [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'ip' => SecurityHelper::getClientIP()
            ]);

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: '.$domain.'/index.php');
            } else {
                header('Location: '.$domain.'/index.php');
            }
            exit;
        } else {
            $error = 'Geçersiz kullanıcı adı veya şifre.';
            SecurityHelper::recordFailedLogin($username);
            Logger::getInstance()->warning("Failed login attempt", [
                'username' => $username,
                'ip' => SecurityHelper::getClientIP()
            ]);
        }
    }
}

// Generate CSRF token
$csrf_token = SecurityHelper::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - PDF İmzalama Sistemi</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
        }

        .login-form {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
        }

        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: #007bff;
            color: white;
            border-bottom: none;
            padding: 1.5rem;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card login-form">
                    <div class="card-header text-center">
                        <h4 class="mb-0">
                            <i class="fas fa-user me-2"></i>Kullanıcı Girişi
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Kullanıcı Adı
                                </label>
                                <input type="text" class="form-control" id="username" name="username"
                                    required autocomplete="off">
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-key me-2"></i>Şifre
                                </label>
                                <input type="password" class="form-control" id="password" name="password"
                                    required autocomplete="off">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Giriş Yap
                            </button>
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