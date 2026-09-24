<?php
// Keluar dari aplikasi dengan menghapus session dan cookie session pengguna.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$_SESSION = array();
// Hapus cookie session sebelum menghancurkan data session di server.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
set_flash('info', "Anda telah berhasil keluar dari sistem.");
header("Location: login.php");
exit();
