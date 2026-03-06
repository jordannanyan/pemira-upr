<?php
// pages/voters-present.php — Admin Fakultas: Daftar Pemilih Hadir

$myFacultyId = $role === 'admin_faculty' ? (int)($admin['faculty_id'] ?? 0) : null;

$q      = trim($_GET['q'] ?? '');
$filter = trim($_GET['status'] ?? ''); // 'voted' | 'not_voted'

$where  = ['v.is_present = 1'];
$params = [];

if ($myFacultyId) {
    $where[]  = 'v.faculty_id = ?';
    $params[] = $myFacultyId;
}
if ($q !== '') {
    $where[]  = '(v.nim LIKE ? OR v.name LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($filter === 'voted') {
    $where[] = 'v.has_voted = 1';
} elseif ($filter === 'not_voted') {
    $where[] = 'v.has_voted = 0';
}

$rows = dbrows(
    'SELECT v.*, f.name AS faculty_name
     FROM voters v
     JOIN faculties f ON f.id = v.faculty_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY v.present_at DESC, v.name ASC',
    $params
);

$totalPresent = count($rows);
$totalVoted   = count(array_filter($rows, fn($r) => $r['has_voted']));
?>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0">Pemilih Hadir</h4>
            <small class="text-muted">
                <?php echo $totalPresent; ?> hadir ·
                <?php echo $totalVoted; ?> sudah memilih ·
                <?php echo $totalPresent - $totalVoted; ?> belum memilih
            </small>
        </div>
        <a href="index.php?p=issue-token" class="btn btn-primary">
            <i class="bx bx-key me-1"></i> Issue Token
        </a>
    </div>

    <!-- Filter -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
                <input type="hidden" name="p" value="voters-present">
                <input type="text" class="form-control" name="q" value="<?php echo h($q); ?>"
                       placeholder="Cari NIM / Nama..." style="max-width:260px;">
                <select class="form-select" name="status" style="max-width:200px;">
                    <option value="">Semua</option>
                    <option value="voted"     <?php echo $filter === 'voted'     ? 'selected' : ''; ?>>Sudah Memilih</option>
                    <option value="not_voted" <?php echo $filter === 'not_voted' ? 'selected' : ''; ?>>Belum Memilih</option>
                </select>
                <button class="btn btn-outline-primary" type="submit"><i class="bx bx-search me-1"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="index.php?p=voters-present">Reset</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <div class="text-center text-muted py-5">Belum ada pemilih yang hadir.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>NIM</th>
                                <th>Nama</th>
                                <?php if ($role === 'superadmin'): ?><th>Fakultas</th><?php endif; ?>
                                <th>Hadir Sejak</th>
                                <th class="text-center">Status Memilih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $i => $v): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td class="fw-semibold"><?php echo h($v['nim']); ?></td>
                                    <td><?php echo h($v['name']); ?></td>
                                    <?php if ($role === 'superadmin'): ?>
                                        <td><?php echo h($v['faculty_name']); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php echo $v['present_at'] ? date('H:i:s', strtotime($v['present_at'])) : '—'; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($v['has_voted']): ?>
                                            <span class="badge bg-label-success">Sudah Memilih</span>
                                        <?php else: ?>
                                            <span class="badge bg-label-warning">Belum Memilih</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
