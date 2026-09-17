<?php
// Users.php - FR-03 Manajemen User & Promosi Role (Khusus Admin)
require_once __DIR__ . '/includes/header.php';

require_role('admin');

$pdo = get_db_connection();

// Handle Role Update (Promosi Role)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $target_user_id = (int)($_POST['user_id'] ?? 0);
    $new_role = sanitize($_POST['role'] ?? '');

    if (!in_array($new_role, ['admin', 'bendahara', 'member'])) {
        set_flash('error', "Role tidak valid!");
    } else if ($target_user_id === $user['id']) {
        set_flash('error', "Anda tidak dapat mengubah role akun Anda sendiri!");
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $target_user_id]);
        set_flash('success', "Role pengguna berhasil diperbarui menjadi <strong>" . strtoupper($new_role) . "</strong>!");
    }
    header("Location: users.php");
    exit();
}

// Fetch all users with associated member profiles if available
$stmt = $pdo->query("
    SELECT u.id, u.username, u.role, u.created_at, m.name AS member_name, m.phone
    FROM users u
    LEFT JOIN members m ON m.user_id = u.id
    ORDER BY u.id ASC
");
$users_list = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">⚙️ Manajemen Pengguna & Promosi Role</h1>
        <p class="page-subtitle">Kelola hak akses akun pengguna untuk menentukan peran Admin, Bendahara, atau Member</p>
    </div>
</div>

<!-- Info Banner Penjelasan Role -->
<div class="card" style="margin-bottom: 1.5rem; background: #f8fafc; border-left: 4px solid var(--primary);">
    <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--primary);">💡 Panduan Hak Akses (Role Pengguna):</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; font-size: 0.875rem;">
        <div>🛡️ <strong>Admin:</strong> Pengelola utama sistem, kelola role user, master data, & transaksi.</div>
        <div>💼 <strong>Bendahara:</strong> Mengelola data anggota, input setor/tarik tabungan, & laporan.</div>
        <div>👤 <strong>Member:</strong> Anggota PKK (hanya melihat saldo & mutasi transaksi pribadi).</div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 60px;">No</th>
                    <th>Akun Pengguna</th>
                    <th>Profil Anggota PKK</th>
                    <th>Role Saat Ini</th>
                    <th>Tanggal Terdaftar</th>
                    <th style="width: 280px;">Ubah Hak Akses (Promosi Role)</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($users_list as $u): ?>
                <tr>
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
                            <span class="text-muted" style="font-size: 0.85rem; font-style: italic;">(Belum ditautkan ke anggota)</span>
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
                        <?php if ($u['id'] === $user['id']): ?>
                            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; padding: 0.25rem 0.5rem; background: #f1f5f9; border-radius: 6px;">
                                🔒 Akun Anda (Aktif)
                            </span>
                        <?php else: ?>
                            <form action="users.php" method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="hidden" name="action" value="update_role">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <select name="role" class="form-control" style="width: auto; padding: 0.4rem 0.6rem; font-size: 0.85rem;">
                                    <option value="member" <?= $u['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                    <option value="bendahara" <?= $u['role'] === 'bendahara' ? 'selected' : '' ?>>Bendahara</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">🔄 Update Role</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
