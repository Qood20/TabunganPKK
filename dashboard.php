<?php
// Dashboard pengurus: menampilkan statistik kas dan lima transaksi terbaru.
require_once __DIR__ . '/includes/header.php';

$role = $user['role'];

// Member memiliki dashboard khusus yang hanya menampilkan tabungannya sendiri.
if ($role === 'member') {
    header("Location: my_savings.php");
    exit();
}

// Statistik agregat untuk admin dan bendahara.
$total_kas = get_total_kas();
$setoran_bulan_ini = get_monthly_deposits();
$penarikan_bulan_ini = get_monthly_withdrawals();
$total_anggota = get_active_members_count();

$pdo = get_db_connection();

// Ambil aktivitas terbaru untuk ringkasan dashboard.
$stmt_recent = $pdo->query("
    SELECT t.*, m.name AS member_name
    FROM transactions t
    JOIN members m ON m.id = t.member_id
    ORDER BY t.created_at DESC, t.id DESC
    LIMIT 5
");
$recent_transactions = $stmt_recent->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📊 Dashboard Keuangan PKK</h1>
        <p class="page-subtitle">Ringkasan statistik kas, setoran, penarikan, dan aktivitas anggota PKK</p>
    </div>
    <div>
        <a href="transactions.php" class="btn btn-primary">💳 Input Transaksi Baru</a>
    </div>
</div>

<!-- Ringkasan angka utama kas dan anggota. -->
<div class="grid-cards">
    <div class="card stat-card">
        <div class="stat-icon icon-teal">💰</div>
        <div class="stat-info">
            <h3>Total Kas Terkumpul</h3>
            <div class="stat-value" style="color: var(--primary); font-size: 1.6rem;"><?= format_rupiah($total_kas) ?></div>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-icon icon-green">📈</div>
        <div class="stat-info">
            <h3>Setoran Bulan Ini</h3>
            <div class="stat-value" style="color: var(--success);"><?= format_rupiah($setoran_bulan_ini) ?></div>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-icon icon-rose">📉</div>
        <div class="stat-info">
            <h3>Penarikan Bulan Ini</h3>
            <div class="stat-value" style="color: var(--danger);"><?= format_rupiah($penarikan_bulan_ini) ?></div>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-icon icon-indigo">👥</div>
        <div class="stat-info">
            <h3>Jumlah Anggota Aktif</h3>
            <div class="stat-value"><?= $total_anggota ?> Orang</div>
        </div>
    </div>
</div>

<!-- Aktivitas terbaru dan tautan aksi yang sering digunakan. -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
    
    <div class="card" style="grid-column: span 2;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.15rem; font-weight: 700;">🕒 5 Transaksi Terakhir</h2>
            <a href="transactions.php" style="font-size: 0.85rem; font-weight: 600;">Lihat Semua &rarr;</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Anggota</th>
                        <th>Jenis</th>
                        <th>Nominal</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_transactions)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Belum ada data transaksi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_transactions as $t): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                            <td><strong><?= htmlspecialchars($t['member_name']) ?></strong></td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($t['type']) ?>">
                                    <?= strtoupper(htmlspecialchars($t['type'])) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: <?= $t['type'] === 'setor' ? 'var(--success)' : 'var(--danger)' ?>;">
                                    <?= ($t['type'] === 'setor' ? '+' : '-') . ' ' . format_rupiah($t['amount']) ?>
                                </strong>
                            </td>
                            <td><?= htmlspecialchars($t['description'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Shortcuts Card -->
    <div class="card">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;">⚡ Akses Cepat</h2>
        
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <a href="members.php" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;">
                <span>👥</span> <div><strong>Master Anggota</strong><div style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">Kelola profil & saldo anggota</div></div>
            </a>

            <a href="transactions.php" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;">
                <span>💳</span> <div><strong>Input Transaksi</strong><div style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">Setor / Tarik uang tabungan</div></div>
            </a>

            <a href="reports.php" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;">
                <span>📑</span> <div><strong>Laporan & Export</strong><div style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">Cetak PDF / Export Excel rekapitulasi</div></div>
            </a>

            <?php if ($role === 'admin'): ?>
            <a href="users.php" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;">
                <span>⚙️</span> <div><strong>Kelola Pengguna</strong><div style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">Promosi role Admin & Bendahara</div></div>
            </a>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
