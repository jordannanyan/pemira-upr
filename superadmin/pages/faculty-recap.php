<?php
// pages/faculty-recap.php — Rekap Fakultas (Superadmin only)

if ($role !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'>
            <div class='card'><div class='card-body'>
              <h4 class='mb-2'>403 - Akses ditolak</h4>
              <p class='mb-0'>Halaman ini hanya untuk <b>superadmin</b>.</p>
            </div></div>
          </div>";
    return;
}

$myFacultyId = null;

// Superadmin can pick a faculty via ?faculty_id=X
if ($role === 'superadmin') {
    $myFacultyId = (int)($_GET['faculty_id'] ?? 0);
    if (!$myFacultyId) {
        // Show faculty picker for superadmin
        $allFaculties = dbrows('SELECT id, name FROM faculties WHERE is_active=1 ORDER BY name ASC');
        ?>
        <div class="container-xxl flex-grow-1 container-p-y">
            <h4 class="mb-3">Rekap Fakultas — Pilih Fakultas</h4>
            <div class="row g-3">
                <?php foreach ($allFaculties as $f): ?>
                    <div class="col-md-4 col-lg-3">
                        <a href="index.php?p=faculty-recap&faculty_id=<?php echo $f['id']; ?>"
                           class="card d-block text-decoration-none text-body border">
                            <div class="card-body">
                                <i class="bx bx-buildings fs-4 mb-2 text-primary d-block"></i>
                                <div class="fw-semibold"><?php echo h($f['name']); ?></div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return;
    }
}

if (!$myFacultyId) {
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='alert alert-warning'>Fakultas tidak ditemukan.</div></div>";
    return;
}

$faculty = dbrow('SELECT * FROM faculties WHERE id = ?', [$myFacultyId]);
if (!$faculty) {
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='alert alert-danger'>Fakultas tidak valid.</div></div>";
    return;
}

$election = active_election();

// KPI stats
$presentCount = (int)dbval('SELECT COUNT(*) FROM voters WHERE faculty_id=? AND is_present=1', [$myFacultyId]);
$votedCount   = (int)dbval('SELECT COUNT(*) FROM voters WHERE faculty_id=? AND has_voted=1', [$myFacultyId]);
$notVoted     = max(0, $presentCount - $votedCount);
$turnout      = $presentCount > 0 ? round($votedCount / $presentCount * 100, 1) : 0;
$totalVoters  = (int)dbval('SELECT COUNT(*) FROM voters WHERE faculty_id=? AND is_active=1', [$myFacultyId]);

$activeTokens = $election ? (int)dbval(
    'SELECT COUNT(*) FROM tokens t
     JOIN voters v ON v.id = t.voter_id
     WHERE v.faculty_id=? AND t.election_id=? AND t.used_at IS NULL AND t.revoked_at IS NULL AND t.expires_at > NOW()',
    [$myFacultyId, $election['id']]
) : 0;

// Presma live count (faculty only)
$presmaRows = $election ? dbrows(
    'SELECT c.no, c.name, COUNT(v.id) AS votes
     FROM candidates c
     LEFT JOIN votes v ON v.candidate_id=c.id AND v.election_id=c.election_id AND v.voter_faculty_id=?
     WHERE c.election_id=? AND c.type="presma" AND c.is_active=1
     GROUP BY c.id ORDER BY c.no ASC',
    [$myFacultyId, $election['id']]
) : [];
$presmaSeries = array_column($presmaRows, 'votes');
$presmaLabels = array_map(fn($r) => 'No. ' . $r['no'] . ' - ' . $r['name'], $presmaRows);
$presmaMeta   = array_map(fn($r) => ['no' => (int)$r['no'], 'name' => $r['name']], $presmaRows);
$presmaSeries = array_map('intval', $presmaSeries);
$totalPresma  = array_sum($presmaSeries);

// DPM live count (faculty only)
$dpmRows = $election ? dbrows(
    'SELECT c.no, c.name, COUNT(v.id) AS votes
     FROM candidates c
     LEFT JOIN votes v ON v.candidate_id=c.id AND v.election_id=c.election_id AND v.voter_faculty_id=?
     WHERE c.election_id=? AND c.type="dpm" AND c.is_active=1
     GROUP BY c.id ORDER BY c.no ASC',
    [$myFacultyId, $election['id']]
) : [];
$dpmSeries = array_map('intval', array_column($dpmRows, 'votes'));
$dpmLabels = array_map(fn($r) => 'No. ' . $r['no'] . ' - ' . $r['name'], $dpmRows);
$dpmMeta   = array_map(fn($r) => ['no' => (int)$r['no'], 'name' => $r['name']], $dpmRows);
$totalDpm  = array_sum($dpmSeries);
?>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div class="d-flex align-items-center gap-2">
            <span class="avatar-initial rounded bg-label-primary">
                <i class="bx bx-buildings"></i>
            </span>
            <div>
                <h4 class="mb-0">Rekap Fakultas</h4>
                <small class="text-muted">
                    <?php echo h($faculty['name']); ?> · <?php echo date('d M Y'); ?>
                    <?php if ($election): ?>
                        · <span class="badge bg-label-success"><?php echo h($election['name']); ?></span>
                    <?php else: ?>
                        · <span class="badge bg-label-warning">Belum ada periode aktif</span>
                    <?php endif; ?>
                </small>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($role === 'superadmin'): ?>
                <a href="index.php?p=faculty-recap" class="btn btn-outline-secondary btn-sm">
                    <i class="bx bx-arrow-back me-1"></i> Semua Fakultas
                </a>
            <?php endif; ?>
            <a href="index.php?p=voters-present" class="btn btn-outline-primary btn-sm">
                <i class="bx bx-list-check me-1"></i> Pemilih Hadir
            </a>
            <?php if ($role === 'admin_faculty'): ?>
                <a href="index.php?p=issue-token" class="btn btn-primary btn-sm">
                    <i class="bx bx-key me-1"></i> Issue Token
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Terdaftar</small>
                    <h4 class="mb-0"><?php echo number_format($totalVoters); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Hadir</small>
                    <h4 class="mb-0 text-info"><?php echo number_format($presentCount); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Sudah Memilih</small>
                    <h4 class="mb-0 text-success"><?php echo number_format($votedCount); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Belum Memilih</small>
                    <h4 class="mb-0 text-warning"><?php echo number_format($notVoted); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Turnout</small>
                    <h4 class="mb-0 text-primary"><?php echo $turnout; ?>%</h4>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card">
                <div class="card-body py-3">
                    <small class="text-muted d-block">Token Aktif</small>
                    <h4 class="mb-0 text-warning"><?php echo number_format($activeTokens); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-4">
        <!-- Presma -->
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Live Count Presma — <?php echo h($faculty['name']); ?></h5>
                    <small class="text-muted">
                        Total suara presma: <b id="totalPresmaEl"><?php echo number_format($totalPresma); ?></b>
                        · Auto-refresh 15 dtk
                    </small>
                </div>
                <div class="card-body">
                    <?php if (empty($presmaRows)): ?>
                        <div class="text-center text-muted py-4">Belum ada calon presma atau data suara.</div>
                    <?php else: ?>
                        <div id="presmaDonut" style="min-height:320px;"></div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>Calon</th><th class="text-end">Suara</th><th class="text-end">%</th></tr></thead>
                                <tbody id="presmaTbody">
                                    <?php foreach ($presmaMeta as $i => $m): ?>
                                        <tr class="pr-row">
                                            <td><div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-label-danger">#<?php echo $m['no']; ?></span>
                                                <span class="fw-semibold"><?php echo h($m['name']); ?></span>
                                            </div></td>
                                            <td class="text-end prVotes"><?php echo number_format($presmaSeries[$i]); ?></td>
                                            <td class="text-end prPct">
                                                <?php echo $totalPresma ? round($presmaSeries[$i] / $totalPresma * 100, 1) : 0; ?>%
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

        <!-- DPM -->
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Live Count DPM — <?php echo h($faculty['name']); ?></h5>
                    <small class="text-muted">
                        Total: <b id="totalDpmEl"><?php echo number_format($totalDpm); ?></b>
                        · Auto-refresh 15 dtk
                    </small>
                </div>
                <div class="card-body">
                    <?php if (empty($dpmRows)): ?>
                        <div class="text-center text-muted py-4">
                            Belum ada calon DPM untuk fakultas ini.
                            <?php if ($role === 'superadmin'): ?>
                                <div class="mt-2">
                                    <a href="index.php?p=candidates" class="btn btn-sm btn-outline-primary">Tambah Calon DPM</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div id="dpmDonut" style="min-height:320px;"></div>
                        <div class="table-responsive mt-3">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>Calon</th><th class="text-end">Suara</th><th class="text-end">%</th></tr></thead>
                                <tbody id="dpmTbody">
                                    <?php foreach ($dpmMeta as $i => $m): ?>
                                        <tr class="dp-row">
                                            <td><div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-label-info">#<?php echo $m['no']; ?></span>
                                                <span class="fw-semibold"><?php echo h($m['name']); ?></span>
                                            </div></td>
                                            <td class="text-end dpVotes"><?php echo number_format($dpmSeries[$i]); ?></td>
                                            <td class="text-end dpPct">
                                                <?php echo $totalDpm ? round($dpmSeries[$i] / $totalDpm * 100, 1) : 0; ?>%
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
    </div>

</div>

<script>
(function () {
    if (typeof ApexCharts === 'undefined') return;

    const presmaSeries = <?php echo json_encode($presmaSeries, JSON_UNESCAPED_UNICODE); ?>;
    const presmaLabels = <?php echo json_encode($presmaLabels, JSON_UNESCAPED_UNICODE); ?>;
    const presmaMeta   = <?php echo json_encode($presmaMeta,   JSON_UNESCAPED_UNICODE); ?>;
    const dpmSeries    = <?php echo json_encode($dpmSeries,    JSON_UNESCAPED_UNICODE); ?>;
    const dpmLabels    = <?php echo json_encode($dpmLabels,    JSON_UNESCAPED_UNICODE); ?>;
    const dpmMeta      = <?php echo json_encode($dpmMeta,      JSON_UNESCAPED_UNICODE); ?>;

    function genColors(n) {
        return Array.from({length: n}, (_, i) => `hsl(${Math.round(360/Math.max(n,1)*i)} 70% 55%)`);
    }
    function fmt(n) { return (n||0).toLocaleString('id-ID'); }
    function sum(a) { return a.reduce((s,v) => s+(Number(v)||0), 0); }

    function buildDonut(series, labels, meta, elId, height) {
        const el = document.getElementById(elId);
        if (!el || !series.length) return null;
        const colors = genColors(series.length);
        const chart = new ApexCharts(el, {
            chart: { type: 'donut', height, toolbar: { show: false }, animations: { enabled: true } },
            series: series.slice(), labels: labels.slice(), colors,
            stroke: { width: 3 },
            dataLabels: {
                enabled: true,
                formatter: (val, opts) => `#${meta[opts.seriesIndex]?.no ?? opts.seriesIndex+1} ${val.toFixed(1)}%`
            },
            legend: {
                show: true, position: 'bottom',
                formatter: (name, opts) => {
                    const m = meta[opts.seriesIndex];
                    const v = opts.w.globals.series[opts.seriesIndex] ?? 0;
                    return `#${m?.no} ${m?.name ?? name} (${fmt(v)})`;
                }
            },
            plotOptions: {
                pie: { donut: { size: '68%', labels: {
                    show: true,
                    value: { show: true, fontSize: '26px', formatter: v => fmt(parseInt(v||0,10)) },
                    total: { show: true, label: 'Total', formatter: w => fmt(sum(w.globals.series)) }
                }}}
            },
            tooltip: {
                custom: ({ series: s, seriesIndex: si, w }) => {
                    const v = s[si] ?? 0;
                    const t = sum(s);
                    const m = meta[si] || {};
                    const c = w.globals.colors[si];
                    return `<div class="p-2" style="min-width:200px;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span style="width:10px;height:10px;border-radius:50%;background:${c};display:inline-block;"></span>
                            <span class="fw-semibold">#${m.no} ${m.name ?? ''}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Suara</span><span class="fw-semibold">${fmt(v)}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">%</span>
                            <span class="fw-semibold">${t > 0 ? (v/t*100).toFixed(1) : '0.0'}%</span>
                        </div>
                    </div>`;
                }
            }
        });
        chart.render();
        return chart;
    }

    const presmaChart = buildDonut(presmaSeries, presmaLabels, presmaMeta, 'presmaDonut', 320);
    const dpmChart    = buildDonut(dpmSeries, dpmLabels, dpmMeta, 'dpmDonut', 320);

    const facultyId = <?php echo (int)$myFacultyId; ?>;

    // Auto-refresh both charts every 15 seconds (faculty-filtered)
    function refreshLive() {
        fetch('../ajax/live-count.php?faculty_id=' + facultyId)
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;

                // Presma
                if (data.series?.length) {
                    const total = sum(data.series);
                    const el = document.getElementById('totalPresmaEl');
                    if (el) el.textContent = fmt(total);
                    document.querySelectorAll('#presmaTbody tr.pr-row').forEach((tr, i) => {
                        const v = data.series[i] || 0;
                        const p = total > 0 ? (v/total*100).toFixed(1) : '0.0';
                        const vEl = tr.querySelector('.prVotes');
                        const pEl = tr.querySelector('.prPct');
                        if (vEl) vEl.textContent = fmt(v);
                        if (pEl) pEl.textContent = p + '%';
                    });
                    if (presmaChart) presmaChart.updateSeries(data.series.slice());
                }

                // DPM
                if (data.series_dpm?.length) {
                    const totalD = sum(data.series_dpm);
                    const elD = document.getElementById('totalDpmEl');
                    if (elD) elD.textContent = fmt(totalD);
                    document.querySelectorAll('#dpmTbody tr.dp-row').forEach((tr, i) => {
                        const v = data.series_dpm[i] || 0;
                        const p = totalD > 0 ? (v/totalD*100).toFixed(1) : '0.0';
                        const vEl = tr.querySelector('.dpVotes');
                        const pEl = tr.querySelector('.dpPct');
                        if (vEl) vEl.textContent = fmt(v);
                        if (pEl) pEl.textContent = p + '%';
                    });
                    if (dpmChart) dpmChart.updateSeries(data.series_dpm.slice());
                }
            }).catch(() => {});
    }
    setInterval(refreshLive, 15000);

})();
</script>
