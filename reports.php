<?php
// Reports.php - FR-08 Cetak Laporan (Filter Date Range & Export)
require_once __DIR__ . '/includes/header.php';

require_role(['admin', 'bendahara']);

$pdo = get_db_connection();

// Filter parameters
$start_date = sanitize($_GET['start_date'] ?? date('Y-m-01'));
$end_date = sanitize($_GET['end_date'] ?? date('Y-m-d'));
$member_id = isset($_GET['member_id']) && $_GET['member_id'] !== '' ? (int)$_GET['member_id'] : null;
$type = sanitize($_GET['type'] ?? 'all');

// Build query
$where = ["DATE(t.created_at) BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if ($member_id) {
    $where[] = "t.member_id = ?";
    $params[] = $member_id;
}

if (in_array($type, ['setor', 'tarik'])) {
    $where[] = "t.type = ?";
    $params[] = $type;
}

$where_clause = implode(" AND ", $where);

$stmt = $pdo->prepare("
    SELECT t.*, m.name AS member_name, u.username AS petugas_name
    FROM transactions t
    JOIN members m ON m.id = t.member_id
    JOIN users u ON u.id = t.user_id
    WHERE {$where_clause}
    ORDER BY t.created_at ASC
");
$stmt->execute($params);
$report_data = $stmt->fetchAll();

// Calculate totals for report
$total_setor = 0;
$total_tarik = 0;
foreach ($report_data as $row) {
    if ($row['type'] === 'setor') {
        $total_setor += (float)$row['amount'];
    } else {
        $total_tarik += (float)$row['amount'];
    }
}
$net_total = $total_setor - $total_tarik;

// Members dropdown for filter
$members_list = $pdo->query("SELECT id, name FROM members ORDER BY name ASC")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📑 Laporan Keuangan & Tabungan</h1>
        <p class="page-subtitle">Filter dan unduh rekapitulasi mutasi transaksi tabungan PKK</p>
    </div>
</div>

<!-- Filter Card -->
<div class="card" style="margin-bottom: 2rem;">
    <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">🔍 Filter Laporan</h2>

    <form action="reports.php" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
        
        <div class="form-group" style="margin-bottom: 0;">
            <label for="start_date" class="form-label">Tanggal Mulai</label>
            <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="end_date" class="form-label">Tanggal Sampai</label>
            <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="member_id" class="form-label">Filter Anggota</label>
            <select name="member_id" id="member_id" class="form-control">
                <option value="">-- Semua Anggota --</option>
                <?php foreach ($members_list as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($member_id == $m['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="type" class="form-label">Jenis Transaksi</label>
            <select name="type" id="type" class="form-control">
                <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Semua Jenis</option>
                <option value="setor" <?= $type === 'setor' ? 'selected' : '' ?>>Setoran Sahaja</option>
                <option value="tarik" <?= $type === 'tarik' ? 'selected' : '' ?>>Penarikan Sahaja</option>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">🔍 Tampilkan</button>
            <a href="reports.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Report Summary & Preview Card -->
<div class="card">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.2rem; font-weight: 700;">📋 Rekapitulasi Laporan</h2>
            <div style="font-size: 0.9rem; color: var(--text-muted);">
                Periode: <strong><?= date('d/m/Y', strtotime($start_date)) ?></strong> s/d <strong><?= date('d/m/Y', strtotime($end_date)) ?></strong>
            </div>
        </div>

        <!-- Export Buttons -->
        <div style="display: flex; gap: 0.5rem;" class="no-print">
            <?php
            $export_params = http_build_query([
                'start_date' => $start_date,
                'end_date' => $end_date,
                'member_id' => $member_id,
                'type' => $type
            ]);
            ?>
            <a href="export_print.php?<?= $export_params ?>" target="_blank" class="btn btn-secondary">🖨️ Cetak Laporan (PDF)</a>
            <a href="export_excel.php?<?= $export_params ?>" class="btn btn-primary">📊 Export Excel (CSV)</a>
        </div>
    </div>

    <!-- Summary Box -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; padding: 1.25rem; background: #f8fafc; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
        <div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">TOTAL SETORAN:</span>
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--success);"><?= format_rupiah($total_setor) ?></div>
        </div>
        <div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">TOTAL PENARIKAN:</span>
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--danger);"><?= format_rupiah($total_tarik) ?></div>
        </div>
        <div>
            <span style="font-size: 0.85rem; color: var(--text-muted);">SELISIH / NET:</span>
            <div style="font-size: 1.25rem; font-weight: 700; color: var(--primary);"><?= format_rupiah($net_total) ?></div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal & Waktu</th>
                    <th>Anggota</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Keterangan</th>
                    <th>Petugas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($report_data)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">Tidak ada transaksi pada periode yang dipilih.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($report_data as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                        <td><strong><?= htmlspecialchars($row['member_name']) ?></strong></td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($row['type']) ?>">
                                <?= strtoupper(htmlspecialchars($row['type'])) ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: <?= $row['type'] === 'setor' ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= ($row['type'] === 'setor' ? '+' : '-') . ' ' . format_rupiah($row['amount']) ?>
                            </strong>
                        </td>
                        <td><?= htmlspecialchars($row['description'] ?? '-') ?></td>
                        <td><span style="font-size: 0.85rem; color: var(--text-muted);">👤 <?= htmlspecialchars($row['petugas_name']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
