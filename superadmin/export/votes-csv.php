<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/auth.php';
require_superadmin('login.php');

// ── Format: csv | excel ───────────────────────────────────────────────────────
$format = in_array($_GET['format'] ?? '', ['csv', 'excel'], true)
    ? $_GET['format']
    : 'csv';

// ── Filters ───────────────────────────────────────────────────────────────────
$filterFaculty  = (int)($_GET['faculty_id'] ?? 0);
$filterType     = trim($_GET['type'] ?? '');
$filterElection = (int)($_GET['election_id'] ?? 0);

$where  = [];
$params = [];
if ($filterElection) {
    $where[]  = 'v.election_id = ?';
    $params[] = $filterElection;
}
if ($filterFaculty) {
    $where[]  = 'v.voter_faculty_id = ?';
    $params[] = $filterFaculty;
}
if ($filterType) {
    $where[]  = 'v.candidate_type = ?';
    $params[] = $filterType;
}

$rows = dbrows(
    'SELECT v.id, v.election_id, v.candidate_type, v.voter_faculty_id,
            v.voted_at, v.receipt, v.photo_path, v.ip_address, v.user_agent,
            f.name AS faculty_name, e.name AS election_name
     FROM votes v
     JOIN faculties f ON f.id = v.voter_faculty_id
     JOIN election_periods e ON e.id = v.election_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY v.voted_at DESC',
    $params
);

// ── Base URL untuk foto ───────────────────────────────────────────────────────
$scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$timestamp = date('Ymd_His');

// ══════════════════════════════════════════════════════════════════════════════
// CSV
// ══════════════════════════════════════════════════════════════════════════════
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"votes_export_{$timestamp}.csv\"");
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // BOM — agar Excel baca UTF-8 dengan benar

    $out = fopen('php://output', 'w');
    fputcsv($out, ['No', 'Receipt', 'Periode', 'Fakultas', 'Tipe', 'Waktu', 'IP Address', 'URL Foto KTM']);

    $no = 1;
    foreach ($rows as $v) {
        $photoUrl = $v['photo_path']
            ? $baseUrl . '/' . ltrim($v['photo_path'], '/')
            : '';

        fputcsv($out, [
            $no++,
            $v['receipt'],
            $v['election_name'],
            $v['faculty_name'],
            strtoupper($v['candidate_type']),
            $v['voted_at'] ? date('d/m/Y H:i:s', strtotime($v['voted_at'])) : '',
            $v['ip_address'],
            $photoUrl,
        ]);
    }
    fclose($out);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// EXCEL (HTML table → .xls — dibuka langsung oleh Microsoft Excel / LibreOffice)
// ══════════════════════════════════════════════════════════════════════════════
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"votes_export_{$timestamp}.xls\"");
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // BOM

$he = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<!--[if gte mso 9]>
<xml><x:ExcelWorkbook><x:ExcelWorksheets>
<x:ExcelWorksheet><x:Name>Suara</x:Name>
<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml>
<![endif]-->
<style>
    table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11pt; }
    th { background: #1e5799; color: #fff; font-weight: bold; padding: 6px 10px;
         border: 1px solid #aaa; text-align: center; white-space: nowrap; }
    td { padding: 5px 10px; border: 1px solid #ccc; vertical-align: middle; white-space: nowrap; }
    tr:nth-child(even) td { background: #f2f7ff; }
    .badge-presma { color: #c0392b; font-weight: bold; }
    .badge-dpm    { color: #2980b9; font-weight: bold; }
    a { color: #1a73e8; text-decoration: none; }
</style>
</head>
<body>
<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Receipt</th>
            <th>Periode</th>
            <th>Fakultas</th>
            <th>Tipe</th>
            <th>Waktu</th>
            <th>IP Address</th>
            <th>Foto KTM</th>
        </tr>
    </thead>
    <tbody>
<?php
$no = 1;
foreach ($rows as $v):
    $photoUrl  = $v['photo_path'] ? $baseUrl . '/' . ltrim($v['photo_path'], '/') : '';
    $typeClass = $v['candidate_type'] === 'presma' ? 'badge-presma' : 'badge-dpm';
    $waktu     = $v['voted_at'] ? date('d/m/Y H:i:s', strtotime($v['voted_at'])) : '—';
?>
        <tr>
            <td style="text-align:center;"><?php echo $no++; ?></td>
            <td style="font-family:monospace;"><?php echo $he($v['receipt']); ?></td>
            <td><?php echo $he($v['election_name']); ?></td>
            <td><?php echo $he($v['faculty_name']); ?></td>
            <td class="<?php echo $typeClass; ?>" style="text-align:center;"><?php echo strtoupper($he($v['candidate_type'])); ?></td>
            <td style="font-family:monospace;"><?php echo $he($waktu); ?></td>
            <td style="font-family:monospace;"><?php echo $he($v['ip_address'] ?? ''); ?></td>
            <td>
                <?php if ($photoUrl): ?>
                    <a href="<?php echo $he($photoUrl); ?>"><?php echo $he($photoUrl); ?></a>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
<?php exit;
