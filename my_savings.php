<?php
// Dashboard member: menampilkan saldo, total mutasi, dan riwayat pribadi.
require_once __DIR__ . '/includes/header.php';

require_role('member');

$pdo = get_db_connection();

// Profil anggota dicari melalui user_id dari session, bukan dari parameter URL.
$stmt_member = $pdo->prepare("SELECT * FROM members WHERE user_id = ?");
$stmt_member->execute([$user['id']]);
$member = $stmt_member->fetch();

$balance = 0;
$total_setor = 0;
$total_tarik = 0;
$transactions = [];

if ($member) {
    $balance = get_member_balance($member['id']);

    // Hitung total setoran dan penarikan untuk profil yang sedang login.
    $stmt_sum = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'setor' THEN amount ELSE 0 END), 0) AS total_setor,
            COALESCE(SUM(CASE WHEN type = 'tarik' THEN amount ELSE 0 END), 0) AS total_tarik
        FROM transactions
        WHERE member_id = ?
    ");
    $stmt_sum->execute([$member['id']]);
    $sum_res = $stmt_sum->fetch();
    $total_setor = (float)$sum_res['total_setor'];
    $total_tarik = (float)$sum_res['total_tarik'];

    // Ambil hanya transaksi milik anggota tersebut.
    $stmt_history = $pdo->prepare("
        SELECT t.*, u.username AS petugas_name
        FROM transactions t
        JOIN users u ON u.id = t.user_id
        WHERE t.member_id = ?
        ORDER BY t.created_at DESC, t.id DESC
    ");
    $stmt_history->execute([$member['id']]);
    $transactions = $stmt_history->fetchAll();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">👛 Tabungan Saya</h1>
        <p class="page-subtitle">Rincian saldo tabungan dan riwayat mutasi transaksi Anda (<strong><?= htmlspecialchars($member['name'] ?? $user['username']) ?></strong>)</p>
    </div>
</div>

<?php if (!$member): ?>
    <div class="alert alert-info">
        <span>ℹ️</span>
        <div>Akun Anda belum ditautkan ke data profil anggota PKK. Silakan hubungi pengurus/bendahara PKK untuk menautkan akun Anda.</div>
    </div>
<?php else: ?>

    <!-- Ringkasan saldo dan total mutasi member. -->
    <div class="grid-cards">
        <div class="card stat-card">
            <div class="stat-icon icon-teal">💰</div>
            <div class="stat-info">
                <h3>Total Saldo Tabungan Saya</h3>
                <div class="stat-value" style="color: var(--primary); font-size: 1.8rem;"><?= format_rupiah($balance) ?></div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="stat-icon icon-green">📈</div>
            <div class="stat-info">
                <h3>Total Setoran Saya</h3>
                <div class="stat-value" style="color: var(--success);"><?= format_rupiah($total_setor) ?></div>
            </div>
        </div>

        <div class="card stat-card">
            <div class="stat-icon icon-rose">📉</div>
            <div class="stat-info">
                <h3>Total Penarikan Saya</h3>
                <div class="stat-value" style="color: var(--danger);"><?= format_rupiah($total_tarik) ?></div>
            </div>
        </div>
    </div>

    <!-- Detail riwayat transaksi pribadi. -->
    <div class="card" style="margin-top: 1.5rem;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;">📜 Riwayat Mutasi Transaksi Pribadi</h2>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal & Waktu</th>
                        <th>Jenis Transaksi</th>
                        <th>Nominal</th>
                        <th>Keterangan</th>
                        <th>Petugas Pencatat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Belum ada riwayat transaksi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
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
                            <td><span style="font-size: 0.85rem; color: var(--text-muted);">👤 <?= htmlspecialchars($t['petugas_name']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
