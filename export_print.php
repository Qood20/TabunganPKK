<?php
// Export laporan terfilter dalam tampilan cetak browser atau simpan sebagai PDF.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role(['admin', 'bendahara']);

$pdo = get_db_connection();
$user = get_logged_user();

$start_date = sanitize($_GET['start_date'] ?? date('Y-m-01'));
$end_date = sanitize($_GET['end_date'] ?? date('Y-m-d'));
$member_id = isset($_GET['member_id']) && $_GET['member_id'] !== '' ? (int)$_GET['member_id'] : null;
$type = sanitize($_GET['type'] ?? 'all');

// Bangun kondisi filter menggunakan parameter terikat untuk mencegah injeksi SQL.
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

// Ringkas total transaksi yang akan dicetak.
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Keuangan PKK</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 5px 0 0 0; font-size: 12px; color: #666; }
        .info { margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ccc; }
        th { background: #f2f2f2; padding: 8px; text-align: left; }
        td { padding: 8px; }
        .summary { margin-top: 15px; width: 300px; float: right; }
        .summary table td { font-weight: bold; }
        .footer-sign { margin-top: 60px; display: flex; justify-content: space-between; text-align: center; }
        .sign-box { width: 200px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="no-print" style="margin-bottom: 15px;">
        <button onclick="window.print();" style="padding: 8px 16px; font-weight: bold; cursor: pointer;">🖨️ Cetak / Simpan PDF</button>
        <button onclick="window.close();" style="padding: 8px 16px; margin-left: 10px; cursor: pointer;">Tutup</button>
    </div>

    <div class="header">
        <h2>REKAPITULASI LAPORAN TABUNGAN & KAS PKK</h2>
        <p>Sistem Informasi Pengelolaan Keuangan PKK</p>
    </div>

    <div class="info">
        <strong>Periode Laporan:</strong> <?= date('d/m/Y', strtotime($start_date)) ?> s/d <?= date('d/m/Y', strtotime($end_date)) ?><br>
        <strong>Dicetak Pada:</strong> <?= date('d/m/Y H:i:s') ?><br>
        <strong>Dicetak Oleh:</strong> <?= htmlspecialchars($user['username']) ?> (<?= strtoupper($user['role']) ?>)
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal & Waktu</th>
                <th>Nama Anggota</th>
                <th>Jenis</th>
                <th>Nominal (Rp)</th>
                <th>Keterangan</th>
                <th>Petugas</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($report_data)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">Tidak ada transaksi pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($report_data as $row): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                    <td><?= htmlspecialchars($row['member_name']) ?></td>
                    <td><?= strtoupper(htmlspecialchars($row['type'])) ?></td>
                    <td style="text-align: right;"><?= number_format($row['amount'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['description'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['petugas_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr>
                <td>Total Setoran</td>
                <td style="text-align: right; color: green;">Rp <?= number_format($total_setor, 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Total Penarikan</td>
                <td style="text-align: right; color: red;">Rp <?= number_format($total_tarik, 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Net Saldo Kas</td>
                <td style="text-align: right; color: blue;">Rp <?= number_format($net_total, 0, ',', '.') ?></td>
            </tr>
        </table>
    </div>

    <div style="clear: both;"></div>

    <div class="footer-sign">
        <div class="sign-box">
            <p>Ketua PKK</p>
            <br><br><br>
            <p><strong>( ................................ )</strong></p>
        </div>
        <div class="sign-box">
            <p>Bendahara PKK</p>
            <br><br><br>
            <p><strong>( <?= htmlspecialchars($user['username']) ?> )</strong></p>
        </div>
    </div>

</body>
</html>
