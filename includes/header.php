<?php
// Layout pembuka halaman privat: autentikasi, navigasi, dan konten utama.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

require_login();
$user = get_logged_user();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabungan PKK - Sistem Keuangan & Tabungan</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Navigasi atas dan identitas pengguna aktif. -->
    <header class="navbar">
        <div class="navbar-brand">
            <span class="logo-icon">💰</span>
            <span>Tabungan PKK</span>
        </div>
        
        <div class="navbar-user">
            <div class="user-badge badge-role-<?= htmlspecialchars($user['role']) ?>">
                👤 <strong><?= htmlspecialchars($user['username']) ?></strong> (<?= strtoupper(htmlspecialchars($user['role'])) ?>)
            </div>
            <a href="logout.php" class="btn btn-secondary btn-sm">🚪 Keluar</a>
        </div>
    </header>

    <div class="app-container">
        <!-- Menu sidebar disaring berdasarkan role pengguna. -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li>
                    <?php if (has_role('member')): ?>
                        <a href="my_savings.php" class="sidebar-link <?= ($current_page == 'dashboard.php' || $current_page == 'my_savings.php') ? 'active' : '' ?>">
                            📊 <span>Dashboard Tabungan</span>
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="sidebar-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                            📊 <span>Dashboard</span>
                        </a>
                    <?php endif; ?>
                </li>
                
                <?php if (has_role(['admin', 'bendahara'])): ?>
                <li>
                    <a href="members.php" class="sidebar-link <?= ($current_page == 'members.php') ? 'active' : '' ?>">
                        👥 <span>Data Anggota</span>
                    </a>
                </li>
                <li>
                    <a href="transactions.php" class="sidebar-link <?= ($current_page == 'transactions.php') ? 'active' : '' ?>">
                        💳 <span>Transaksi Tabungan</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="sidebar-link <?= ($current_page == 'reports.php') ? 'active' : '' ?>">
                        📑 <span>Laporan Keuangan</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_role('admin')): ?>
                <li>
                    <a href="users.php" class="sidebar-link <?= ($current_page == 'users.php') ? 'active' : '' ?>">
                        ⚙️ <span>Kelola Pengguna</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </aside>

        <!-- Halaman pemanggil menyisipkan konten di area ini. -->
        <main class="main-content">
            <?= display_flash() ?>
