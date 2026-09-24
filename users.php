<?php
// Modul admin untuk mengelola akun, password, dan role pengguna.
require_once __DIR__ . '/includes/header.php';

require_role('admin');

$pdo = get_db_connection();

// Proses pembaruan akun dan cegah admin menurunkan role dirinya sendiri.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $target_user_id = (int)($_POST['user_id'] ?? 0);
    $new_username = sanitize($_POST['username'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $new_role = sanitize($_POST['role'] ?? '');

    if ($target_user_id <= 0 || empty($new_username) || !in_array($new_role, ['admin', 'bendahara', 'member'])) {
        set_flash('error', "Data akun pengguna tidak valid!");
    } else {
        // Username baru tidak boleh bentrok dengan akun lain.
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt_check->execute([$new_username, $target_user_id]);
        if ($stmt_check->fetch()) {
            set_flash('error', "Username '{$new_username}' sudah digunakan oleh akun lain!");
        } else {
            // Admin aktif harus tetap memiliki akses admin setelah menyimpan form.
            if ($target_user_id === $user['id'] && $new_role !== 'admin') {
                set_flash('error', "Anda tidak dapat mengubah role akun Anda sendiri menjadi non-admin!");
            } else {
                if (!empty($new_password)) {
                    // Update username, role, dan password baru (hashed)
                    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->execute([$new_username, $hashed, $new_role, $target_user_id]);
                    set_flash('success', "Akun <strong>{$new_username}</strong> (Username, Password & Role) berhasil diperbarui!");
                } else {
                    // Update username dan role saja
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                    $stmt->execute([$new_username, $new_role, $target_user_id]);
                    set_flash('success', "Akun <strong>{$new_username}</strong> (Username & Role) berhasil diperbarui!");
                }

                // Sinkronkan session jika akun yang diedit adalah akun yang sedang login.
                if ($target_user_id === $user['id']) {
                    $_SESSION['user']['username'] = $new_username;
                    $_SESSION['user']['role'] = $new_role;
                }
            }
        }
    }
    header("Location: users.php");
    exit();
}

// Gabungkan akun dengan profil anggota agar relasi yang belum terhubung terlihat.
$stmt = $pdo->query("
    SELECT u.id, u.username, u.role, u.created_at, m.name AS member_name, m.phone
    FROM users u
    LEFT JOIN members m ON m.user_id = u.id
    ORDER BY u.id ASC
");
$users_list = $stmt->fetchAll();

// Muat data akun untuk mengisi form edit jika parameter tersedia.
$edit_user_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt_e = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_e->execute([$edit_id]);
    $edit_user_data = $stmt_e->fetch();
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">⚙️ Kelola Pengguna (User, Password & Role)</h1>
        <p class="page-subtitle">Kelola username, reset password, dan hak akses peran (Admin, Bendahara, Member)</p>
    </div>
</div>

<!-- Info Banner Penjelasan Role -->
<div class="card" style="margin-bottom: 1.5rem; background: #f8fafc; border-left: 4px solid var(--primary);">
    <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--primary);">💡 Panduan Pengelolaan Akun & Hak Akses:</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; font-size: 0.875rem;">
        <div>🛡️ <strong>Admin:</strong> Kelola akun pengguna, edit username & reset password, master data, & transaksi.</div>
        <div>💼 <strong>Bendahara:</strong> Mengelola data anggota, input setor/tarik tabungan, & laporan.</div>
        <div>👤 <strong>Member:</strong> Anggota PKK (hanya melihat saldo & mutasi transaksi pribadi).</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">

    <!-- Form Card (Edit Akun Pengguna) -->
    <?php if ($edit_user_data): ?>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.15rem; font-weight: 700;">✏️ Edit Akun #<?= $edit_user_data['id'] ?></h2>
            <a href="users.php" class="btn btn-secondary btn-sm">❌ Batal</a>
        </div>

        <form action="users.php" method="POST">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" value="<?= $edit_user_data['id'] ?>">

            <div class="form-group">
                <label for="username" class="form-label">Username *</label>
                <input type="text" name="username" id="username" class="form-control" value="<?= htmlspecialchars($edit_user_data['username']) ?>" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password Baru (Reset)</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                <small style="color: var(--text-muted); font-size: 0.8rem;">Isi kolom ini jika ingin mereset password pengguna.</small>
            </div>

            <div class="form-group">
                <label for="role" class="form-label">Hak Akses (Role) *</label>
                <select name="role" id="role" class="form-control" required>
                    <option value="member" <?= $edit_user_data['role'] === 'member' ? 'selected' : '' ?>>Member (Anggota)</option>
                    <option value="bendahara" <?= $edit_user_data['role'] === 'bendahara' ? 'selected' : '' ?>>Bendahara</option>
                    <option value="admin" <?= $edit_user_data['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">💾 SIMPAN PERUBAHAN AKUN</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Table Card -->
    <div class="card" style="<?= $edit_user_data ? '' : 'grid-column: 1 / -1;' ?>">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
            <h2 style="font-size: 1.15rem; font-weight: 700;">📋 Daftar Pengguna Sistem</h2>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Username</th>
                        <th>Profil Anggota PKK</th>
                        <th>Role Saat Ini</th>
                        <th>Tanggal Terdaftar</th>
                        <th style="width: 140px;">Aksi Pengaturan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($users_list as $u): ?>
                    <tr <?= ($edit_user_data && $edit_user_data['id'] == $u['id']) ? 'style="background-color: #f0fdf4;"' : '' ?>>
                        <td><?= $no++ ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 1.1rem;">👤</span>
                                <strong><?= htmlspecialchars($u['username']) ?></strong>
                            </div>
                        </td>
                        <td>
                            <?php if ($u['member_name']): ?>
                                <span style="color: var(--primary); font-weight: 600;"><?= htmlspecialchars($u['member_name']) ?></span>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 0.85rem; font-style: italic;">(Belum ditautkan)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $role_icon = ($u['role'] === 'admin') ? '🛡️' : (($u['role'] === 'bendahara') ? '💼' : '👤');
                            ?>
                            <span class="badge badge-role-<?= htmlspecialchars($u['role']) ?>">
                                <?= $role_icon ?> <?= strtoupper(htmlspecialchars($u['role'])) ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                        <td>
                            <a href="users.php?edit=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">
                                ✏️ Edit Akun
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
