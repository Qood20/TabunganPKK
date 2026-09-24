<?php
// Helper umum untuk sanitasi output, format rupiah, notifikasi, dan statistik.

require_once __DIR__ . '/../config/database.php';

function sanitize($data) {
    // Normalisasi input sederhana sebelum dipakai atau ditampilkan kembali.
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function format_rupiah($amount) {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_' . $type] = $message;
}

function display_flash() {
    // Flash message hanya ditampilkan sekali, lalu dihapus dari session.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $types = ['success' => 'alert-success', 'error' => 'alert-danger', 'info' => 'alert-info'];
    $output = '';
    
    foreach ($types as $key => $class) {
        if (isset($_SESSION['flash_' . $key])) {
            $msg = $_SESSION['flash_' . $key];
            unset($_SESSION['flash_' . $key]);
            
            $icon = ($key === 'success') ? '✅' : (($key === 'error') ? '⚠️' : 'ℹ️');
            $output .= "<div class='alert {$class}' role='alert'><span>{$icon}</span> <div>{$msg}</div><button type='button' class='alert-close' onclick='this.parentElement.remove()'>&times;</button></div>";
        }
    }
    
    return $output;
}

// Hitung saldo bersih satu anggota: total setor dikurangi total tarik.
function get_member_balance($member_id) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'setor' THEN amount ELSE 0 END), 0) - 
            COALESCE(SUM(CASE WHEN type = 'tarik' THEN amount ELSE 0 END), 0) AS balance
        FROM transactions 
        WHERE member_id = ?
    ");
    $stmt->execute([$member_id]);
    $res = $stmt->fetch();
    return (float)($res['balance'] ?? 0);
}

// Hitung saldo bersih seluruh transaksi sebagai total kas PKK.
function get_total_kas() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'setor' THEN amount ELSE 0 END), 0) - 
            COALESCE(SUM(CASE WHEN type = 'tarik' THEN amount ELSE 0 END), 0) AS total_kas
        FROM transactions
    ");
    $res = $stmt->fetch();
    return (float)($res['total_kas'] ?? 0);
}

// Total Setoran Bulan Ini
function get_monthly_deposits($month = null, $year = null) {
    $pdo = get_db_connection();
    $month = $month ?: date('m');
    $year = $year ?: date('Y');
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total 
        FROM transactions 
        WHERE type = 'setor' 
          AND MONTH(created_at) = ? 
          AND YEAR(created_at) = ?
    ");
    $stmt->execute([$month, $year]);
    $res = $stmt->fetch();
    return (float)($res['total'] ?? 0);
}

// Total Penarikan Bulan Ini
function get_monthly_withdrawals($month = null, $year = null) {
    $pdo = get_db_connection();
    $month = $month ?: date('m');
    $year = $year ?: date('Y');
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total 
        FROM transactions 
        WHERE type = 'tarik' 
          AND MONTH(created_at) = ? 
          AND YEAR(created_at) = ?
    ");
    $stmt->execute([$month, $year]);
    $res = $stmt->fetch();
    return (float)($res['total'] ?? 0);
}

// Jumlah Anggota Aktif
function get_active_members_count() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM members");
    $res = $stmt->fetch();
    return (int)($res['total'] ?? 0);
}
