<?php
// pages/live-count.php — Full-page Live Count (Superadmin)

if ($role !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>403 - Akses ditolak</h4></div></div></div>";
    return;
}

$election = active_election();

// Helper: fetch live data per type
function lcFetch(int $electionId, string $type): array {
    $rows = dbrows(
        'SELECT c.no, c.name, COUNT(v.id) AS votes
         FROM candidates c
         LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = c.election_id
         WHERE c.election_id = ? AND c.type = ? AND c.is_active = 1
         GROUP BY c.id ORDER BY c.no ASC',
        [$electionId, $type]
    );
    $series = $labels = $meta = [];
    foreach ($rows as $r) {
        $series[] = (int)$r['votes'];
        $labels[] = 'No. ' . $r['no'] . ' - ' . $r['name'];
        $meta[]   = ['no' => (int)$r['no'], 'name' => $r['name']];
    }
    return compact('series', 'labels', 'meta');
}

$dataPresma = $dataDpm = ['series' => [], 'labels' => [], 'meta' => []];
if ($election) {
    $dataPresma = lcFetch($election['id'], 'presma');
    $dataDpm    = lcFetch($election['id'], 'dpm');
}

$totalPresma = array_sum($dataPresma['series']);
$totalDpm    = array_sum($dataDpm['series']);

// Active type from query string (default presma)
$activeType = ($_GET['type'] ?? 'presma') === 'dpm' ? 'dpm' : 'presma';
?>

<style>
.lc-modal {
    position: fixed; inset: 0;
    display: none; z-index: 11000;
    background: rgba(0,0,0,.6);
    backdrop-filter: blur(2px);
}
.lc-modal.show { display: block; }
.lc-modal .lc-dialog {
    position: absolute; inset: 16px;
    background: #fff; border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 10px 35px rgba(0,0,0,.3);
}
.lc-modal .lc-header {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(0,0,0,.06);
    display: flex; align-items: center;
    justify-content: space-between; gap: 10px;
}
.lc-modal .lc-body {
    padding: 12px 16px;
    height: calc(100% - 58px); overflow: auto;
}
#liveCountPie, #liveCountPieFull { width: 100%; min-height: 520px; }
@media (max-width: 991px) {
    #liveCountPie, #liveCountPieFull { min-height: 380px; }
}
.lc-kpi { font-size: 44px; line-height: 1; letter-spacing: -0.5px; }
.lc-kpi-sub { font-size: 12px; color: #6c757d; }
.lc-color-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.lc-row.table-active { outline: 2px solid rgba(105,108,255,.25); }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="row">
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h4 class="mb-1" id="lcPageTitle">
                            Live Count — <?php echo $activeType === 'dpm' ? 'DPM' : 'Presiden Mahasiswa'; ?>
                        </h4>
                        <div class="text-muted">
                            <?php if ($election): ?>
                                <span class="fw-semibold"><?php echo h($election['name']); ?></span>
                                <span class="badge bg-label-success ms-2">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-label-warning">Belum ada periode aktif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <!-- Toggle Presma / DPM -->
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm <?php echo $activeType === 'presma' ? 'btn-primary' : 'btn-outline-primary'; ?>"
                                    id="btnSwitchPresma">
                                <i class="bx bx-crown me-1"></i> Presma
                            </button>
                            <button type="button" class="btn btn-sm <?php echo $activeType === 'dpm' ? 'btn-info text-white' : 'btn-outline-info'; ?>"
                                    id="btnSwitchDpm">
                                <i class="bx bx-group me-1"></i> DPM
                            </button>
                        </div>

                        <span class="badge fs-6" id="lcTotalBadge"
                              style="background: <?php echo $activeType === 'dpm' ? 'var(--bs-info)' : 'var(--bs-primary)'; ?>; color:#fff;">
                            Total: <span id="lcTotalVotes"><?php echo number_format($activeType === 'dpm' ? $totalDpm : $totalPresma); ?></span>
                        </span>
                        <button type="button" class="btn btn-outline-secondary" id="btnRefreshLive">
                            <i class="bx bx-refresh me-1"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-primary" id="btnOpenFull">
                            <i class="bx bx-fullscreen me-1"></i> Full Page
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <?php if (empty($dataPresma['series']) && empty($dataDpm['series'])): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bx bx-pie-chart-alt-2 fs-1 d-block mb-2"></i>
                            Belum ada calon atau data suara.
                            <div class="mt-2">
                                <a href="index.php?p=candidates" class="btn btn-outline-primary btn-sm">
                                    Kelola Calon
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row g-4 align-items-stretch">
                            <div class="col-12 col-xl-8">
                                <div class="card border shadow-none h-100">
                                    <div class="card-body">
                                        <div id="liveCountPie"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-xl-4">
                                <div class="card border shadow-none h-100">
                                    <div class="card-body">
                                        <div class="text-muted lc-kpi-sub mb-1">TOTAL SUARA MASUK</div>
                                        <div class="lc-kpi fw-bold mb-3" id="lcBigTotal">
                                            <?php echo number_format($activeType === 'dpm' ? $totalDpm : $totalPresma); ?>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead>
                                                    <tr><th>Calon</th><th class="text-end">Suara</th><th class="text-end">%</th></tr>
                                                </thead>
                                                <tbody id="liveCountTbody">
                                                    <?php
                                                    $initData  = $activeType === 'dpm' ? $dataDpm : $dataPresma;
                                                    $initTotal = array_sum($initData['series']);
                                                    foreach ($initData['meta'] as $i => $m):
                                                    ?>
                                                        <tr class="lc-row">
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <span class="badge bg-label-primary">#<?php echo $m['no']; ?></span>
                                                                    <span class="fw-semibold"><?php echo h($m['name']); ?></span>
                                                                </div>
                                                            </td>
                                                            <td class="text-end fw-semibold lcVotes"><?php echo number_format($initData['series'][$i]); ?></td>
                                                            <td class="text-end lcPct">
                                                                <?php echo $initTotal ? round($initData['series'][$i] / $initTotal * 100, 1) : 0; ?>%
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <small class="text-muted d-block">Auto-refresh tiap 10 detik.</small>
                                        <div id="lcLastUpdate" class="text-muted" style="font-size:.78rem;"></div>

                                        <hr class="my-3">
                                        <div class="d-grid gap-2">
                                            <a class="btn btn-outline-primary" href="index.php?p=candidates">
                                                <i class="bx bx-id-card me-1"></i> Kelola Calon
                                            </a>
                                            <a class="btn btn-outline-primary" href="index.php?p=votes">
                                                <i class="bx bx-receipt me-1"></i> Data Suara & Audit
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Full-page Modal -->
<div class="lc-modal" id="lcFullModal">
    <div class="lc-dialog">
        <div class="lc-header">
            <div>
                <div class="fw-semibold" id="lcFullTitle">Live Count — Full Page</div>
                <small class="text-muted"><?php echo $election ? h($election['name']) : '—'; ?></small>
            </div>
            <div class="d-flex gap-2">
                <span class="badge fs-6" id="lcTotalBadgeFull"
                      style="background:var(--bs-primary);color:#fff;">
                    Total: <span id="lcTotalVotesFull"><?php echo number_format($activeType === 'dpm' ? $totalDpm : $totalPresma); ?></span>
                </span>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnRefreshFull">
                    <i class="bx bx-refresh me-1"></i> Refresh
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="btnCloseFull">
                    <i class="bx bx-exit-fullscreen me-1"></i> Tutup
                </button>
            </div>
        </div>
        <div class="lc-body">
            <div class="row g-4">
                <div class="col-12 col-xl-8">
                    <div class="card border shadow-none">
                        <div class="card-body">
                            <div id="liveCountPieFull"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card border shadow-none">
                        <div class="card-body">
                            <div class="lc-kpi-sub">TOTAL SUARA</div>
                            <div class="lc-kpi fw-bold mb-3" id="lcBigTotalFull">
                                <?php echo number_format($activeType === 'dpm' ? $totalDpm : $totalPresma); ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Calon</th><th class="text-end">Suara</th><th class="text-end">%</th></tr></thead>
                                    <tbody id="liveCountTbodyFull">
                                        <?php foreach ($initData['meta'] as $i => $m): ?>
                                            <tr class="lc-row">
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-label-primary">#<?php echo $m['no']; ?></span>
                                                        <span class="fw-semibold"><?php echo h($m['name']); ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-end fw-semibold lcVotesFull"><?php echo number_format($initData['series'][$i]); ?></td>
                                                <td class="text-end lcPctFull">
                                                    <?php echo $initTotal ? round($initData['series'][$i] / $initTotal * 100, 1) : 0; ?>%
                                                </td>
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
    </div>
</div>

<script>
(function () {
    if (typeof ApexCharts === 'undefined') return;

    // ── Data dari server ──────────────────────────────────────────────────────
    const DB = {
        presma: {
            series: <?php echo json_encode($dataPresma['series'], JSON_UNESCAPED_UNICODE); ?>,
            labels: <?php echo json_encode($dataPresma['labels'], JSON_UNESCAPED_UNICODE); ?>,
            meta:   <?php echo json_encode($dataPresma['meta'],   JSON_UNESCAPED_UNICODE); ?>,
        },
        dpm: {
            series: <?php echo json_encode($dataDpm['series'], JSON_UNESCAPED_UNICODE); ?>,
            labels: <?php echo json_encode($dataDpm['labels'], JSON_UNESCAPED_UNICODE); ?>,
            meta:   <?php echo json_encode($dataDpm['meta'],   JSON_UNESCAPED_UNICODE); ?>,
        },
    };

    let activeType = <?php echo json_encode($activeType); ?>;

    function cur() { return DB[activeType]; }

    function generateColors(n) {
        return Array.from({length: n}, (_, i) => `hsl(${Math.round(360 / Math.max(n, 1) * i)} 70% 55%)`);
    }
    function fmt(n) { return (n || 0).toLocaleString('id-ID'); }
    function sum(a) { return a.reduce((s, v) => s + (Number(v) || 0), 0); }

    let colors = generateColors(cur().series.length);

    // ── Helpers ───────────────────────────────────────────────────────────────
    function highlightRow(idx) {
        ['liveCountTbody', 'liveCountTbodyFull'].forEach(id => {
            document.querySelectorAll(`#${id} tr.lc-row`).forEach((r, i) => {
                r.classList.toggle('table-active', i === idx);
            });
        });
    }

    function applyDots(tbodyId) {
        document.querySelectorAll(`#${tbodyId} tr`).forEach((tr, idx) => {
            const wrap = tr.querySelector('.d-flex');
            if (!wrap) return;
            let dot = wrap.querySelector('.lc-color-dot');
            if (!dot) { dot = document.createElement('span'); dot.className = 'lc-color-dot'; wrap.prepend(dot); }
            dot.style.background = colors[idx] || '#999';
        });
    }

    function buildOpts(height) {
        const d = cur();
        return {
            chart: {
                type: 'donut', height,
                toolbar: { show: false },
                animations: { enabled: true },
                events: {
                    dataPointSelection: (e, ctx, cfg) => {
                        if (typeof cfg.dataPointIndex === 'number') highlightRow(cfg.dataPointIndex);
                    }
                }
            },
            series: d.series.slice(),
            labels: d.labels.slice(),
            colors,
            stroke: { width: 3 },
            dataLabels: {
                enabled: true,
                formatter: (val, opts) => `#${d.meta[opts.seriesIndex]?.no ?? (opts.seriesIndex + 1)} ${val.toFixed(1)}%`
            },
            legend: {
                show: true, position: 'bottom', fontSize: '13px',
                formatter: (name, opts) => {
                    const m = d.meta[opts.seriesIndex];
                    const v = opts.w.globals.series[opts.seriesIndex] ?? 0;
                    return `#${m?.no ?? ''} ${m?.name ?? name} (${fmt(v)})`;
                }
            },
            plotOptions: {
                pie: { donut: { size: '72%', labels: {
                    show: true,
                    name:  { show: true, fontSize: '14px' },
                    value: { show: true, fontSize: '28px', formatter: v => fmt(parseInt(v || 0, 10)) },
                    total: { show: true, label: 'Total Suara', fontSize: '14px',
                             formatter: w => fmt(sum(w.globals.series)) }
                }}}
            },
            tooltip: {
                custom: ({ series, seriesIndex, w }) => {
                    const v = series[seriesIndex] ?? 0;
                    const t = sum(series);
                    const pct = t > 0 ? (v / t * 100).toFixed(1) : '0.0';
                    const m = cur().meta[seriesIndex] || {};
                    const c = w.globals.colors[seriesIndex];
                    return `<div class="p-2" style="min-width:220px;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span style="width:10px;height:10px;border-radius:50%;background:${c};display:inline-block;"></span>
                            <span class="badge bg-label-primary">#${m.no}</span>
                            <span class="fw-semibold">${m.name ?? ''}</span>
                        </div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Suara</span><span class="fw-semibold">${fmt(v)}</span></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">%</span><span class="fw-semibold">${pct}%</span></div>
                    </div>`;
                }
            }
        };
    }

    // ── Render charts ─────────────────────────────────────────────────────────
    const elMain = document.getElementById('liveCountPie');
    const elFull = document.getElementById('liveCountPieFull');
    let chartMain = null, chartFull = null;

    if (elMain && cur().series.length) {
        chartMain = new ApexCharts(elMain, buildOpts(520));
        chartMain.render().then(() => applyDots('liveCountTbody'));
    }

    // ── Rebuild tables ────────────────────────────────────────────────────────
    function rebuildTable(tbodyId, series, meta) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        const total = sum(series);
        tbody.innerHTML = '';
        meta.forEach((m, i) => {
            const v = series[i] || 0;
            const p = total > 0 ? (v / total * 100).toFixed(1) : '0.0';
            const vCls = tbodyId === 'liveCountTbody' ? 'lcVotes' : 'lcVotesFull';
            const pCls = tbodyId === 'liveCountTbody' ? 'lcPct'   : 'lcPctFull';
            tbody.insertAdjacentHTML('beforeend', `
                <tr class="lc-row">
                    <td><div class="d-flex align-items-center gap-2">
                        <span class="badge bg-label-primary">#${m.no}</span>
                        <span class="fw-semibold">${m.name}</span>
                    </div></td>
                    <td class="text-end fw-semibold ${vCls}">${fmt(v)}</td>
                    <td class="text-end ${pCls}">${p}%</td>
                </tr>`);
        });
        applyDots(tbodyId);
    }

    // ── Switch type (Presma ↔ DPM) ────────────────────────────────────────────
    function switchType(type) {
        if (activeType === type) return;
        activeType = type;
        colors = generateColors(cur().series.length);

        const isDpm   = type === 'dpm';
        const accent  = isDpm ? 'var(--bs-info)' : 'var(--bs-primary)';
        const total   = sum(cur().series);

        // Title
        document.getElementById('lcPageTitle').textContent =
            'Live Count — ' + (isDpm ? 'DPM' : 'Presiden Mahasiswa');
        document.getElementById('lcFullTitle').textContent =
            'Live Count — ' + (isDpm ? 'DPM' : 'Presiden Mahasiswa') + ' (Full Page)';

        // Toggle button styles
        document.getElementById('btnSwitchPresma').className =
            'btn btn-sm ' + (isDpm ? 'btn-outline-primary' : 'btn-primary');
        document.getElementById('btnSwitchDpm').className =
            'btn btn-sm ' + (isDpm ? 'btn-info text-white' : 'btn-outline-info');

        // Badge color
        ['lcTotalBadge', 'lcTotalBadgeFull'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.background = accent;
        });

        // Totals
        ['lcTotalVotes', 'lcTotalVotesFull', 'lcBigTotal', 'lcBigTotalFull'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = fmt(total);
        });

        // Rebuild tables
        rebuildTable('liveCountTbody',     cur().series, cur().meta);
        rebuildTable('liveCountTbodyFull', cur().series, cur().meta);

        // Rebuild charts
        if (chartMain) {
            chartMain.destroy();
            chartMain = new ApexCharts(elMain, buildOpts(520));
            chartMain.render().then(() => applyDots('liveCountTbody'));
        }
        if (chartFull) {
            chartFull.destroy();
            chartFull = new ApexCharts(elFull, buildOpts(580));
            chartFull.render().then(() => applyDots('liveCountTbodyFull'));
        }
    }

    document.getElementById('btnSwitchPresma')?.addEventListener('click', () => switchType('presma'));
    document.getElementById('btnSwitchDpm')?.addEventListener('click',    () => switchType('dpm'));

    // ── Apply fresh data from AJAX ────────────────────────────────────────────
    function applyData(data) {
        if (!data) return;
        // Update store
        if (Array.isArray(data.series))     { DB.presma.series = data.series;     DB.presma.labels = data.labels;     DB.presma.meta = data.meta; }
        if (Array.isArray(data.series_dpm)) { DB.dpm.series    = data.series_dpm; DB.dpm.labels    = data.labels_dpm; DB.dpm.meta    = data.meta_dpm; }

        const total = sum(cur().series);
        ['lcTotalVotes','lcTotalVotesFull','lcBigTotal','lcBigTotalFull'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = fmt(total);
        });

        [['liveCountTbody','lcVotes','lcPct'],['liveCountTbodyFull','lcVotesFull','lcPctFull']].forEach(([tbId, vCls, pCls]) => {
            document.querySelectorAll(`#${tbId} tr.lc-row`).forEach((tr, i) => {
                const v = cur().series[i] || 0;
                const p = total > 0 ? (v / total * 100).toFixed(1) : '0.0';
                const vEl = tr.querySelector(`.${vCls}`);
                const pEl = tr.querySelector(`.${pCls}`);
                if (vEl) vEl.textContent = fmt(v);
                if (pEl) pEl.textContent = p + '%';
            });
        });

        if (chartMain) chartMain.updateSeries(cur().series.slice());
        if (chartFull)  chartFull.updateSeries(cur().series.slice());

        const ts = document.getElementById('lcLastUpdate');
        if (ts) ts.textContent = 'Update: ' + new Date().toLocaleTimeString('id-ID');
    }

    function fetchData() {
        fetch('../ajax/live-count.php')
            .then(r => r.ok ? r.json() : null)
            .then(data => { if (data) applyData(data); })
            .catch(() => {});
    }

    document.getElementById('btnRefreshLive')?.addEventListener('click', fetchData);
    document.getElementById('btnRefreshFull')?.addEventListener('click', fetchData);
    setInterval(fetchData, 10000);

    // ── Full-page modal ───────────────────────────────────────────────────────
    const modal = document.getElementById('lcFullModal');
    document.getElementById('btnOpenFull')?.addEventListener('click', () => {
        modal?.classList.add('show');
        if (!chartFull && elFull) {
            chartFull = new ApexCharts(elFull, buildOpts(580));
            chartFull.render().then(() => {
                chartFull.updateSeries(cur().series.slice());
                applyDots('liveCountTbodyFull');
            });
        } else if (chartFull) {
            chartFull.updateSeries(cur().series.slice());
        }
        setTimeout(() => window.dispatchEvent(new Event('resize')), 120);
    });
    document.getElementById('btnCloseFull')?.addEventListener('click', () => modal?.classList.remove('show'));
    modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal?.classList.contains('show')) modal.classList.remove('show');
    });
})();
</script>
