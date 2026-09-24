<?php
// Export laporan transaksi terfilter dalam format CSV yang dapat dibuka Excel.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role(['admin', 'bendahara']);

$pdo = get_db_connection();

$start_date = sanitize($_GET['start_date'] ?? date('Y-m-01'));
$end_date = sanitize($_GET['end_date'] ?? date('Y-m-d'));
$member_id = isset($_GET['member_id']) && $_GET['member_id'] !== '' ? (int)$_GET['member_id'] : null;
$type = sanitize($_GET['type'] ?? 'all');

// Gunakan filter yang sama dengan halaman laporan agar hasil export konsisten.
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

$filename = "Laporan_Tabungan_PKK_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// BOM membantu Excel mengenali karakter UTF-8, termasuk nama beraksara khusus.
fputs($output, "\xEF\xBB\xBF");

// Tulis judul kolom sebelum baris data transaksi.
fputcsv($output, ['No', 'Tanggal', 'Waktu', 'Nama Anggota', 'Jenis Transaksi', 'Nominal (Rp)', 'Keterangan', 'Petugas']);

$no = 1;
foreach ($report_data as $row) {
    fputcsv($output, [
        $no++,
        date('d/m/Y', strtotime($row['created_at'])),
        date('H:i:s', strtotime($row['created_at'])),
        $row['member_name'],
        strtoupper($row['type']),
        $row['amount'],
        $row['description'] ?? '-',
        $row['petugas_name']
    ]);
}

fclose($output);
exit();
