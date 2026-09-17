<?php
// Transactions.php - FR-05 & FR-06 Input Transaksi & Validasi Penarikan Saldo (Admin & Bendahara)
require_once __DIR__ . '/includes/header.php';

require_role(['admin', 'bendahara']);

$pdo = get_db_connection();

// Handle New Transaction / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_transaction') {
        $member_id = (int)($_POST['member_id'] ?? 0);
        $type = sanitize($_POST['type'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');

        if ($member_id <= 0 || !in_array($type, ['setor', 'tarik']) || $amount < 1000) {
            set_flash('error', "Input transaksi tidak valid! Nominal minimal Rp 1.000.");
        } else {
            // Check member existence
            $stmt_m = $pdo->prepare("SELECT name FROM members WHERE id = ?");
            $stmt_m->execute([$member_id]);
            $member_data = $stmt_m->fetch();

            if (!$member_data) {
                set_flash('error', "Anggota tidak ditemukan!");
            } else {
                // FR-06: VALIDASI PENARIKAN SALDO
                if ($type === 'tarik') {
                    $current_balance = get_member_balance($member_id);
                    if ($amount > $current_balance) {
                        // Tolak Transaksi Penarikan (FR-06)
                        set_flash('error', "Saldo tidak mencukupi! Saldo terkini <strong>" . htmlspecialchars($member_data['name']) . "</strong> adalah <strong>" . format_rupiah($current_balance) . "</strong>, sedangkan nominal penarikan adalah <strong>" . format_rupiah($amount) . "</strong>.");
                        header("Location: transactions.php");
                        exit();
                    }
                }

                // Simpan Transaksi
                $stmt_ins = $pdo->prepare("
                    INSERT INTO transactions (member_id, user_id, type, amount, description) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt_ins->execute([$member_id, $user['id'], $type, $amount, $description]);

                $label = ($type === 'setor') ? 'Setoran' : 'Penarikan';
                set_flash('success', "{$label} sebesar <strong>" . format_rupiah($amount) . "</strong> untuk <strong>" . htmlspecialchars($member_data['name']) . "</strong> berhasil disimpan!");
            }
        }
        header("Location: transactions.php");
        exit();
    }

    if ($action === 'delete_transaction') {
        $trans_id = (int)($_POST['id'] ?? 0);
        if ($trans_id > 0) {
            $stmt_del = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt_del->execute([$trans_id]);
            set_flash('success', "Riwayat transaksi berhasil dihapus.");
        }
        header("Location: transactions.php");
        exit();
    }
}

// Fetch all active members for dropdown select
$stmt_members = $pdo->query("SELECT id, name FROM members ORDER BY name ASC");
$members_list = $stmt_members->fetchAll();

// Fetch transaction history
$stmt_history = $pdo->query("
    SELECT t.*, m.name AS member_name, u.username AS petugas_name
    FROM transactions t
    JOIN members m ON m.id = t.member_id
    JOIN users u ON u.id = t.user_id
    ORDER BY t.created_at DESC, t.id DESC
");
$transactions = $stmt_history->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">💳 Transaksi Tabungan PKK</h1>
        <p class="page-subtitle">Input setoran/penarikan tabungan anggota dan riwayat mutasi transaksi</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">

    <!-- Form Transaksi (FR-05 & FR-06) -->
    <div class="card">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;">➕ Input Transaksi (Setor / Tarik)</h2>

        <form action="transactions.php" method="POST">
            <input type="hidden" name="action" value="create_transaction">

            <div class="form-group">
                <label for="member_id" class="form-label">Pilih Anggota PKK *</label>
                <select name="member_id" id="member_id" class="form-control" required>
                    <option value="">-- Pilih Anggota --</option>
                    <?php foreach ($members_list as $m): ?>
                        <option value="<?= $m['id'] ?>">
                            <?= htmlspecialchars($m['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Jenis Transaksi *</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="type" value="setor" checked> 🟢 SETOR
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="type" value="tarik"> 🔴 TARIK
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="amount" class="form-label">Nominal (Rp) *</label>
                <input type="number" name="amount" id="amount" class="form-control" min="1000" step="1000" placeholder="Minimal Rp 1.000" required>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Catatan / Keterangan (Opsional)</label>
                <input type="text" name="description" id="description" class="form-control" placeholder="Contoh: Setoran Wajib Bulanan">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">💾 SIMPAN TRANSAKSI</button>
        </form>
    </div>

    <!-- Data Table Transaksi History -->
    <div class="card" style="grid-column: span 2;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;">📜 Riwayat Transaksi Terbaru</h2>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal & Waktu</th>
                        <th>Anggota</th>
                        <th>Jenis</th>
                        <th>Nominal</th>
                        <th>Catatan</th>
                        <th>Petugas</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">Belum ada riwayat transaksi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
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
                            <td><span style="font-size: 0.85rem; color: var(--text-muted);">👤 <?= htmlspecialchars($t['petugas_name']) ?></span></td>
                            <td>
                                <form action="transactions.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_transaction">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm btn-confirm-delete" data-confirm="Hapus transaksi ini? (Saldo anggota akan disesuaikan otomatis)">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
