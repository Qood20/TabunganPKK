<?php
// Modul master anggota: CRUD profil, pembuatan akun member, dan perhitungan saldo.
require_once __DIR__ . '/includes/header.php';

require_role(['admin', 'bendahara']);

$pdo = get_db_connection();

// Proses aksi CRUD dikirim melalui POST, lalu kembali ke halaman daftar.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = sanitize($_POST['name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        if (empty($name) || empty($username) || empty($password)) {
            set_flash('error', "Nama anggota, username, dan password wajib diisi!");
        } elseif (strlen($password) < 6) {
            set_flash('error', "Password akun member minimal 6 karakter!");
        } else {
            // Pastikan username belum dipakai oleh akun lain.
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt_check->execute([$username]);

            if ($stmt_check->fetch()) {
                set_flash('error', "Username '{$username}' sudah digunakan, silakan pilih username lain!");
            } else {
                try {
                    // Akun dan profil harus berhasil dibuat bersama-sama.
                    $pdo->beginTransaction();

                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'member')");
                    $stmt_user->execute([$username, $hashed_password]);
                    $user_id = $pdo->lastInsertId();

                    $stmt_member = $pdo->prepare("INSERT INTO members (user_id, name, phone, address) VALUES (?, ?, ?, ?)");
                    $stmt_member->execute([$user_id, $name, $phone, $address]);

                    $pdo->commit();
                    set_flash('success', "Anggota <strong>{$name}</strong> berhasil ditambahkan dengan akun <strong>{$username}</strong>.");
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    set_flash('error', "Gagal menambahkan anggota dan akun: " . $e->getMessage());
                }
            }
        }
        header("Location: members.php");
        exit();
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;

        if (empty($name) || $id <= 0) {
            set_flash('error', "Data tidak valid!");
        } else {
            $stmt = $pdo->prepare("UPDATE members SET user_id = ?, name = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->execute([$user_id, $name, $phone, $address, $id]);
            set_flash('success', "Data anggota <strong>{$name}</strong> berhasil diperbarui!");
        }
        header("Location: members.php");
        exit();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', "Data anggota berhasil dihapus!");
        }
        header("Location: members.php");
        exit();
    }
}

// Ambil daftar anggota sekaligus saldo bersih dari seluruh transaksi.
$stmt = $pdo->query("
    SELECT m.*, u.username,
        (COALESCE((SELECT SUM(amount) FROM transactions WHERE member_id = m.id AND type = 'setor'), 0) -
         COALESCE((SELECT SUM(amount) FROM transactions WHERE member_id = m.id AND type = 'tarik'), 0)) AS balance
    FROM members m
    LEFT JOIN users u ON u.id = m.user_id
    ORDER BY m.id DESC
");
$members = $stmt->fetchAll();

// Isi form dengan data anggota ketika URL meminta mode edit.
$edit_member = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt_edit = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt_edit->execute([$edit_id]);
    $edit_member = $stmt_edit->fetch();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">👥 Master Data Anggota PKK</h1>
        <p class="page-subtitle">Kelola informasi anggota dan pantau posisi saldo tabungan mereka</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">
    
    <!-- Form Card (Add / Edit) -->
    <div class="card">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;">
            <?= $edit_member ? '✏️ Edit Data Anggota' : '➕ Tambah Anggota Baru' ?>
        </h2>

        <form action="members.php" method="POST">
            <input type="hidden" name="action" value="<?= $edit_member ? 'update' : 'create' ?>">
            <?php if ($edit_member): ?>
                <input type="hidden" name="id" value="<?= $edit_member['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name" class="form-label">Nama Lengkap *</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="Contoh: Ibu Ani Rahmawati" value="<?= htmlspecialchars($edit_member['name'] ?? '') ?>" required>
            </div>

            <?php if (!$edit_member): ?>
            <div class="form-group">
                <label for="username" class="form-label">Username Akun *</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Username untuk login" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password Akun *</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 6 karakter" minlength="6" required>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="phone" class="form-label">No. Telepon / WhatsApp</label>
                <input type="text" name="phone" id="phone" class="form-control" placeholder="Contoh: 081234567890" value="<?= htmlspecialchars($edit_member['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="address" class="form-label">Alamat Lengkap / RT</label>
                <textarea name="address" id="address" class="form-control" rows="2" placeholder="Contoh: RT 02 / RW 05"><?= htmlspecialchars($edit_member['address'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <?= $edit_member ? '💾 Update Anggota' : '➕ Simpan Anggota' ?>
                </button>
                <?php if ($edit_member): ?>
                    <a href="members.php" class="btn btn-secondary">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Data Table Card -->
    <div class="card" style="grid-column: span 2;">
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;">📋 Daftar Seluruh Anggota</h2>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Anggota</th>
                        <th>Kontak & Alamat</th>
                        <th>Akun User</th>
                        <th>Total Saldo</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">Belum ada data anggota.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $m): ?>
                        <tr>
                            <td>#<?= $m['id'] ?></td>
                            <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                            <td>
                                <div>📞 <?= htmlspecialchars($m['phone'] ?? '-') ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">🏠 <?= htmlspecialchars($m['address'] ?? '-') ?></div>
                            </td>
                            <td>
                                <?php if ($m['username']): ?>
                                    <span class="user-badge badge-role-member">👤 <?= htmlspecialchars($m['username']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.85rem; font-style: italic;">(Belum ada)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--primary); font-size: 1.05rem;">
                                    <?= format_rupiah($m['balance']) ?>
                                </strong>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem;">
                                    <a href="members.php?edit=<?= $m['id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
                                    <form action="members.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm btn-confirm-delete" data-confirm="Hapus data anggota <?= htmlspecialchars($m['name']) ?>? (Semua riwayat transaksi terkait akan terhapus)">🗑️ Hapus</button>
                                    </form>
                                </div>
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
