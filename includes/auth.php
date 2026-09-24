<?php
// Helper autentikasi: mengelola session, login guard, dan pembatasan role.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function get_logged_user() {
    return is_logged_in() ? $_SESSION['user'] : null;
}

function require_login() {
    // Halaman privat mengarahkan pengguna anonim ke login.
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Silakan login terlebih dahulu untuk mengakses halaman tersebut.";
        header("Location: login.php");
        exit();
    }
}

function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    $user_role = $_SESSION['user']['role'] ?? '';
    if (is_array($roles)) {
        return in_array($user_role, $roles);
    }
    
    return $user_role === $roles;
}

function require_role($roles) {
    // Setelah login, pastikan role pengguna diizinkan mengakses halaman.
    require_login();
    
    if (!has_role($roles)) {
        $_SESSION['flash_error'] = "Anda tidak memiliki hak akses (role) untuk membuka halaman tersebut.";
        header("Location: dashboard.php");
        exit();
    }
}
