<?php
// pages/votes.php — Superadmin: Data Suara & Audit

if ($role !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>403 - Akses ditolak</h4></div></div></div>";
    return;
}

$election  = active_election();
$faculties = dbrows('SELECT id, name FROM faculties ORDER BY name ASC');

// Filters
$filterFaculty  = (int)($_GET['faculty_id'] ?? 0);
$filterType     = trim($_GET['type'] ?? '');        // presma | dpm
$filterElection = (int)($_GET['election_id'] ?? ($election['id'] ?? 0));

// Pagination
$page    = max(1, (int)($_GET['pg'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$elections = dbrows('SELECT id, name FROM election_periods ORDER BY id DESC');

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

$whereStr = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// KPI totals
$totalVotes  = (int)dbval("SELECT COUNT(*) FROM votes v$whereStr", $params);
$totalPresma = (int)dbval(
    'SELECT COUNT(*) FROM votes v WHERE v.candidate_type = "presma"'
    . ($filterElection ? ' AND v.election_id = ?' : '')
    . ($filterFaculty  ? ' AND v.voter_faculty_id = ?' : ''),
    array_filter([$filterElection ?: null, $filterFaculty ?: null])
);
$totalDpm = (int)dbval(
    'SELECT COUNT(*) FROM votes v WHERE v.candidate_type = "dpm"'
    . ($filterElection ? ' AND v.election_id = ?' : '')
    . ($filterFaculty  ? ' AND v.voter_faculty_id = ?' : ''),
    array_filter([$filterElection ?: null, $filterFaculty ?: null])
);

$pages  = max(1, (int)ceil($totalVotes / $perPage));

$rows = dbrows(
    "SELECT v.id, v.election_id, v.candidate_type, v.voter_faculty_id,
            v.voted_at, v.receipt, v.photo_path, v.ip_address, v.user_agent,
            f.name AS faculty_name, e.name AS election_name
     FROM votes v
     JOIN faculties f ON f.id = v.voter_faculty_id
     JOIN election_periods e ON e.id = v.election_id
     $whereStr ORDER BY v.voted_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

// Faculty breakdown
$facultyBreakdown = dbrows(
    'SELECT f.name AS faculty_name, COUNT(DISTINCT CASE WHEN v.candidate_type="presma" THEN v.id END) AS presma_votes,
            COUNT(DISTINCT CASE WHEN v.candidate_type="dpm" THEN v.id END) AS dpm_votes
     FROM faculties f
     LEFT JOIN votes v ON v.voter_faculty_id = f.id'
     . ($filterElection ? ' AND v.election_id = ?' : '')
     . ' GROUP BY f.id ORDER BY f.name ASC',
    $filterElection ? [$filterElection] : []
);
?>

<style>
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
.photo-frame { width:100%; height:260px; border:1px solid rgba(67,89,113,.2); border-radius:12px;
               overflow:hidden; display:flex; align-items:center; justify-content:center; background:rgba(67,89,113,.04); }
.photo-frame img { width:100%; height:100%; object-fit:cover; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h4 class="mb-0">Data Suara & Audit</h4>
            <small class="text-muted">
                Monitoring suara masuk ·
                <span class="badge bg-label-warning">Pilihan calon disembunyikan</span>
            </small>
        </div>
        <div>
            <?php
            $exportParams = http_build_query(array_filter([
                'faculty_id'  => $filterFaculty  ?: null,
                'type'        => $filterType      ?: null,
                'election_id' => $filterElection  ?: null,
            ]));
            ?>
            <a class="btn btn-outline-success"
               href="export/votes-csv.php<?php echo $exportParams ? "?$exportParams" : ''; ?>">
                <i class="bx bx-file me-1"></i> CSV
            </a>
            <a class="btn btn-success ms-1"
               href="export/votes-csv.php?format=excel<?php echo $exportParams ? "&$exportParams" : ''; ?>">
                <i class="bx bx-spreadsheet me-1"></i> Excel
            </a>
        </div>
    </div>

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted">Total Suara</div>
                    <h3 class="mb-0"><?php echo number_format($totalVotes); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted">Suara Presma</div>
                    <h3 class="mb-0 text-danger"><?php echo number_format($totalPresma); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted">Suara DPM</div>
                    <h3 class="mb-0 text-info"><?php echo number_format($totalDpm); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted">Halaman</div>
                    <h3 class="mb-0"><?php echo number_format($page); ?> / <?php echo number_format($pages); ?></h3>
                    <small class="text-muted"><?php echo $perPage; ?> baris/halaman</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
                <input type="hidden" name="p" value="votes">
                <select class="form-select" name="election_id" style="max-width:220px;">
                    <option value="">Semua Periode</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $filterElection == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo h($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select" name="faculty_id" style="max-width:200px;">
                    <option value="">Semua Fakultas</option>
                    <?php foreach ($faculties as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty == $f['id'] ? 'selected' : ''; ?>>
                            <?php echo h($f['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select" name="type" style="max-width:160px;">
                    <option value="">Semua Tipe</option>
                    <option value="presma" <?php echo $filterType === 'presma' ? 'selected' : ''; ?>>Presma</option>
                    <option value="dpm"    <?php echo $filterType === 'dpm'    ? 'selected' : ''; ?>>DPM</option>
                </select>
                <button class="btn btn-outline-primary" type="submit"><i class="bx bx-filter-alt me-1"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="index.php?p=votes">Reset</a>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Main Table -->
        <div class="col-12 col-xl-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Log Suara</h5>
                    <span class="badge bg-label-primary"><?php echo number_format($totalVotes); ?> total</span>
                </div>
                <div class="card-body">
                    <?php if (empty($rows)): ?>
                        <div class="text-center text-muted py-5">Belum ada data suara.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Receipt</th>
                                        <th>Fakultas</th>
                                        <th>Tipe</th>
                                        <th>Waktu</th>
                                        <th class="text-end">Foto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $v): ?>
                                        <tr>
                                            <td class="mono" style="font-size:.82rem;"><?php echo h($v['receipt']); ?></td>
                                            <td><?php echo h($v['faculty_name']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $v['candidate_type'] === 'presma' ? 'bg-label-danger' : 'bg-label-info'; ?>">
                                                    <?php echo strtoupper($v['candidate_type']); ?>
                                                </span>
                                            </td>
                                            <td class="mono" style="font-size:.82rem;">
                                                <?php echo $v['voted_at'] ? date('d/m H:i:s', strtotime($v['voted_at'])) : '—'; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($v['photo_path']): ?>
                                                    <button class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal" data-bs-target="#modalPhoto"
                                                            data-photo="<?php echo h('../' . $v['photo_path']); ?>"
                                                            title="Lihat foto">
                                                        <i class="bx bx-image"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info mb-0 mt-3">
                            <div class="fw-semibold">Privasi suara</div>
                            <small>Pilihan calon tidak ditampilkan untuk menjaga kerahasiaan suara.</small>
                        </div>

                        <?php if ($pages > 1):
                            $pgBase = 'index.php?p=votes'
                                . ($filterElection ? "&election_id=$filterElection" : '')
                                . ($filterFaculty  ? "&faculty_id=$filterFaculty"   : '')
                                . ($filterType     ? '&type=' . urlencode($filterType) : '');
                            $window = [];
                            for ($i = max(1, $page - 2); $i <= min($pages, $page + 3); $i++) {
                                $window[] = $i;
                            }
                        ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-end mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo $pgBase; ?>&pg=<?php echo $page - 1; ?>">«</a>
                                    </li>

                                    <?php if (!in_array(1, $window)): ?>
                                        <li class="page-item"><a class="page-link" href="<?php echo $pgBase; ?>&pg=1">1</a></li>
                                        <?php if ($window[0] > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">…</span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php foreach ($window as $i): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo $pgBase; ?>&pg=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endforeach; ?>

                                    <?php if (!in_array($pages, $window)): ?>
                                        <?php if ($window[count($window) - 1] < $pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">…</span></li>
                                        <?php endif; ?>
                                        <li class="page-item"><a class="page-link" href="<?php echo $pgBase; ?>&pg=<?php echo $pages; ?>"><?php echo $pages; ?></a></li>
                                    <?php endif; ?>

                                    <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo $pgBase; ?>&pg=<?php echo $page + 1; ?>">»</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Faculty Breakdown -->
        <div class="col-12 col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Rekap Per Fakultas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Fakultas</th>
                                    <th class="text-end">Presma</th>
                                    <th class="text-end">DPM</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facultyBreakdown as $fb): ?>
                                    <tr>
                                        <td><?php echo h($fb['faculty_name']); ?></td>
                                        <td class="text-end"><?php echo number_format((int)$fb['presma_votes']); ?></td>
                                        <td class="text-end"><?php echo number_format((int)$fb['dpm_votes']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal Photo -->
<div class="modal fade" id="modalPhoto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Foto Pemilih</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="photo-frame" style="height:480px;">
                    <img id="photoImg" src="" alt="Foto pemilih" style="display:none;"
                         onerror="this.style.display='none';document.getElementById('photoFallback').style.display='flex';">
                    <div id="photoFallback" style="display:flex;flex-direction:column;align-items:center;gap:8px;">
                        <i class="bx bx-user" style="font-size:48px;color:#aaa;"></i>
                        <small class="text-muted">Foto tidak tersedia</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('modalPhoto');
    if (modal) {
        modal.addEventListener('show.bs.modal', (e) => {
            const photo = e.relatedTarget?.dataset.photo || '';
            const img = document.getElementById('photoImg');
            const fb  = document.getElementById('photoFallback');
            if (photo) {
                img.src = photo;
                img.style.display = 'block';
                fb.style.display  = 'none';
            } else {
                img.style.display = 'none';
                fb.style.display  = 'flex';
            }
        });
    }
})();
</script>
