<?php
require_once '../config.php';
require_once '../includes/logger.php';
require_once '../includes/UserManager.php';
require_once '../includes/SecurityHelper.php';
require_once 'auth.php';

// Initialize UserManager
$userManager = new UserManager($db, Logger::getInstance());

// Require admin login
requireAdmin();

$error = '';
$success = '';

// Generate CSRF token
$csrf_token = SecurityHelper::generateCsrfToken();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SecurityHelper::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Geçersiz form gönderimi';
        Logger::getInstance()->warning("CSRF token validation failed in users.php", [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'ip' => SecurityHelper::getClientIP()
        ]);
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['email']) || empty($_POST['tckn'])) {
                    $error = 'Tüm alanları doldurun';
                } else {
                    $username = $_POST['username'];
                    $password = $_POST['password'];
                    $email = $_POST['email'];
                    $fullName = $_POST['full_name'] ?? '';
                    $tckn = $_POST['tckn'];
                    $role = $_POST['role'] ?? 'user';

                    if (!SecurityHelper::isStrongPassword($password)) {
                        $error = 'Şifre güvenli değil. En az 8 karakter uzunluğunda olmalı ve büyük/küçük harf, rakam ve özel karakter içermeli.';
                    } else {
                        try {
                            $result = $userManager->createUser($username, $password, $fullName, $email, $role, $tckn);
                            if ($result) {
                                $success = 'Kullanıcı başarıyla oluşturuldu';
                                Logger::getInstance()->info("New user created", [
                                    'created_user' => $username,
                                    'role' => $role,
                                    'admin_id' => $_SESSION['user_id'],
                                    'admin_username' => $_SESSION['username']
                                ]);
                            } else {
                                $error = 'Kullanıcı oluşturulurken bir hata oluştu';
                            }
                        } catch (Exception $e) {
                            $error = 'Kullanıcı oluşturulurken bir hata oluştu: ' . $e->getMessage();
                            Logger::getInstance()->error("Error creating user", [
                                'error' => $e->getMessage(),
                                'username' => $username,
                                'admin_id' => $_SESSION['user_id']
                            ]);
                        }
                    }
                }
                break;

            case 'update':
                $userId = $_POST['user_id'] ?? null;
                if (!$userId) {
                    $error = 'Kullanıcı ID gerekli';
                } else {
                    $updates = [];
                    if (!empty($_POST['email'])) {
                        $updates['email'] = $_POST['email'];
                    }
                    if (!empty($_POST['full_name'])) {
                        $updates['full_name'] = $_POST['full_name'];
                    }
                    if (!empty($_POST['role'])) {
                        $updates['role'] = $_POST['role'];
                    }
                    if (!empty($_POST['tckn'])) {
                        $updates['tckn'] = $_POST['tckn'];
                    }
                    
                    try {
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET email = :email, full_name = :full_name, role = :role, tckn = :tckn
                            WHERE id = :id
                        ");
                        
                        $stmt->execute([
                            ':email' => $updates['email'],
                            ':full_name' => $updates['full_name'],
                            ':role' => $updates['role'],
                            ':tckn' => $updates['tckn'],
                            ':id' => $userId
                        ]);
                        
                        $success = 'Kullanıcı bilgileri güncellendi';
                        Logger::getInstance()->info("User updated", [
                            'updated_user_id' => $userId,
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username']
                        ]);
                    } catch (PDOException $e) {
                        $error = 'Kullanıcı güncellenirken bir hata oluştu';
                        Logger::getInstance()->error("Error updating user", [
                            'error' => $e->getMessage(),
                            'user_id' => $userId,
                            'admin_id' => $_SESSION['user_id']
                        ]);
                    }
                }
                break;

            case 'change_password':
                $userId = $_POST['user_id'] ?? null;
                $newPassword = $_POST['new_password'] ?? '';
                
                if (!$userId || empty($newPassword)) {
                    $error = 'Kullanıcı ID ve yeni şifre gerekli';
                } elseif (!SecurityHelper::isStrongPassword($newPassword)) {
                    $error = 'Şifre güvenli değil. En az 8 karakter uzunluğunda olmalı ve büyük/küçük harf, rakam ve özel karakter içermeli.';
                } else {
                    if ($userManager->updatePassword($userId, $newPassword)) {
                        $success = 'Şifre başarıyla güncellendi';
                        Logger::getInstance()->info("Password changed", [
                            'user_id' => $userId,
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username']
                        ]);
                    } else {
                        $error = 'Şifre güncellenirken bir hata oluştu';
                    }
                }
                break;

            case 'delete':
                $userId = $_POST['user_id'] ?? null;
                if (!$userId) {
                    $error = 'Kullanıcı ID gerekli';
                } else {
                    // Prevent self-deletion
                    if ($userId == $_SESSION['user_id']) {
                        $error = 'Kendi hesabınızı silemezsiniz';
                    } else {
                        try {
                            $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
                            $stmt->execute([':id' => $userId]);
                            
                            $success = 'Kullanıcı silindi';
                            Logger::getInstance()->info("User deleted", [
                                'deleted_user_id' => $userId,
                                'admin_id' => $_SESSION['user_id'],
                                'admin_username' => $_SESSION['username']
                            ]);
                        } catch (PDOException $e) {
                            $error = 'Kullanıcı silinirken bir hata oluştu';
                            Logger::getInstance()->error("Error deleting user", [
                                'error' => $e->getMessage(),
                                'user_id' => $userId,
                                'admin_id' => $_SESSION['user_id']
                            ]);
                        }
                    }
                }
                break;
        }
    }
}

// Get list of users
try {
    $stmt = $db->query("
        SELECT id, username, full_name, email, tckn, role, last_login, created_at 
        FROM users 
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Kullanıcı listesi alınırken bir hata oluştu';
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - PDF İmzalama Sistemi</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php
    require_once '../navbar.php';
    ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h2>
                    <i class="fas fa-users me-2"></i>Kullanıcı Yönetimi
                </h2>
            </div>
        </div>

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

        <!-- New User Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>Yeni Kullanıcı Ekle
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="col-md-3">
                        <label class="form-label">Kullanıcı Adı</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Şifre</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">TCKN</label>
                        <input type="text" name="tckn" class="form-control" required pattern="\d{11}" maxlength="11">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">E-posta</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Ad Soyad</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Rol</label>
                        <select name="role" class="form-select" required>
                            <option value="user">Kullanıcı</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Kullanıcı Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-circle me-2"></i>Kullanıcılar
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kullanıcı Adı</th>
                                <th>Ad Soyad</th>
                                <th>TCKN</th>
                                <th>E-posta</th>
                                <th>Rol</th>
                                <th>Son Giriş</th>
                                <th>Oluşturulma</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['tckn']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                        <?= htmlspecialchars($user['role']) ?>
                                    </span>
                                </td>
                                <td><?= $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : '-' ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUser<?= $user['id'] ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUser<?= $user['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Edit User Modal -->
                            <div class="modal fade" id="editUser<?= $user['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Kullanıcı Düzenle</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">E-posta</label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Ad Soyad</label>
                                                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">TCKN</label>
                                                    <input type="text" name="tckn" class="form-control" value="<?= htmlspecialchars($user['tckn']) ?>" required pattern="\d{11}" maxlength="11">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Rol</label>
                                                    <select name="role" class="form-select" required>
                                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Kullanıcı</option>
                                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    </select>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i>Kaydet
                                                </button>
                                            </form>

                                            <hr>

                                            <form method="POST" action="" class="mt-3">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="action" value="change_password">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Yeni Şifre</label>
                                                    <input type="password" name="new_password" class="form-control" required>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-warning">
                                                    <i class="fas fa-key me-2"></i>Şifre Değiştir
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete User Modal -->
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <div class="modal fade" id="deleteUser<?= $user['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Kullanıcı Sil</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Bu kullanıcıyı silmek istediğinizden emin misiniz?</p>
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-trash me-2"></i>Sil
                                                </button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-2"></i>İptal
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#usersTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
                }
            });
        });
    </script>
</body>
</html>