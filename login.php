<?php
// Login.php - FR-02 Login & Authentication
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Username dan Password wajib diisi!";
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ];
            
            set_flash('success', "Selamat datang kembali, <strong>" . htmlspecialchars($user['username']) . "</strong>!");
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Username atau Password salah!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Tabungan PKK</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-wrapper">

    <div class="auth-card">
        <div class="auth-header">
            <span style="font-size: 2.5rem;">💰</span>
            <h1>Aplikasi Tabungan PKK</h1>
            <p>Silakan masuk ke akun Anda</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <span>⚠️</span> <div><?= $error ?></div>
            </div>
        <?php endif; ?>

        <?= display_flash() ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">🔑 Masuk Sekarang</button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--text-muted);">
            Belum memiliki akun? <a href="register.php" style="font-weight: 600;">Daftar Mandiri di Sini</a>
        </div>
    </div>

</body>
</html>
