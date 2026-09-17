<?php
// Register.php - FR-01 Registration (Self-Register)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    if (empty($name) || empty($username) || empty($password)) {
        $error = "Nama Lengkap, Username, dan Password wajib diisi!";
    } else {
        $pdo = get_db_connection();

        // Cek keunikan username
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->execute([$username]);
        if ($stmt_check->fetch()) {
            $error = "Username '{$username}' sudah digunakan, silakan gunakan username lain!";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert ke tabel users (role otomatis 'member')
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'member')");
                $stmt_user->execute([$username, $hashed_password]);
                $user_id = $pdo->lastInsertId();

                // 2. Insert ke tabel members
                $stmt_member = $pdo->prepare("INSERT INTO members (user_id, name, phone, address) VALUES (?, ?, ?, ?)");
                $stmt_member->execute([$user_id, $name, $phone, $address]);

                $pdo->commit();

                set_flash('success', "Pendaftaran berhasil! Silakan masuk dengan akun <strong>{$username}</strong> Anda.");
                header("Location: login.php");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Gagal melakukan pendaftaran: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Mandiri - Tabungan PKK</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-wrapper">

    <div class="auth-card" style="max-width: 500px;">
        <div class="auth-header">
            <span style="font-size: 2.5rem;">📝</span>
            <h1>Pendaftaran Anggota PKK</h1>
            <p>Buat akun mandiri untuk mengakses tabungan Anda</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <span>⚠️</span> <div><?= $error ?></div>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="name" class="form-label">Nama Lengkap *</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="Contoh: Ibu Ani Rahmawati" required>
            </div>

            <div class="form-group">
                <label for="username" class="form-label">Username *</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Username untuk login" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password *</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 6 karakter" required>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">No. WhatsApp / Telepon</label>
                <input type="text" name="phone" id="phone" class="form-control" placeholder="Contoh: 081234567890">
            </div>

            <div class="form-group">
                <label for="address" class="form-label">Alamat / RT / RW</label>
                <textarea name="address" id="address" class="form-control" rows="2" placeholder="Contoh: RT 02 / RW 05"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">✨ Daftar Akun Sekarang</button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--text-muted);">
            Sudah memiliki akun? <a href="login.php" style="font-weight: 600;">Masuk di Sini</a>
        </div>
    </div>

</body>
</html>
