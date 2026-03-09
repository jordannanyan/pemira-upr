<?php
// pages/dashboard.php — connect ke DB

$election = active_election();

if ($role === 'superadmin') {
    // ====== SUPERADMIN DATA ======
    $electionName = $election['name'] ?? 'Belum ada periode aktif';

    $totalVoters  = (int)dbval('SELECT COUNT(*) FROM voters WHERE is_active = 1');
    $totalPresent = (int)dbval('SELECT COUNT(*) FROM voters WHERE is_present = 1');
    $totalVoted   = (int)dbval('SELECT COUNT(*) FROM voters WHERE has_voted = 1');
    $totalNotVoted = $totalVoters - $totalVoted;

    // Live count presma
    $live = [];
    if ($election) {
        $live = dbrows(
            'SELECT c.id, c.no, c.name, c.type,
                    COUNT(v.id) AS votes
             FROM candidates c
             LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = c.election_id
             WHERE c.election_id = ? AND c.type = ? AND c.is_active = 1
             GROUP BY c.id
             ORDER BY c.no ASC',
            [$election['id'], 'presma']
        );
    }

    $totalVotes = array_sum(array_column($live, 'votes'));
    $percent    = fn($v) => $totalVotes > 0 ? round(($v / $totalVotes) * 100, 1) : 0;

    $labels = [];
    $series = [];
    $meta   = [];
    foreach ($live as $c) {
        $labels[] = 'No. ' . $c['no'] . ' - ' . $c['name'];
        $series[] = (int)$c['votes'];
        $meta[]   = ['no' => (int)$c['no'], 'name' => $c['name'], 'icon' => 'bx-user'];
    }

    // Live count DPM
    $liveDpm = [];
    if ($election) {
        $liveDpm = dbrows(
            'SELECT c.id, c.no, c.name, c.type,
                    COUNT(v.id) AS votes
             FROM candidates c
             LEFT JOIN votes v ON v.candidate_id = c.id AND v.election_id = c.election_id
             WHERE c.election_id = ? AND c.type = ? AND c.is_active = 1
             GROUP BY c.id
             ORDER BY c.no ASC',
            [$election['id'], 'dpm']
        );
    }

    $totalVotesDpm = array_sum(array_column($liveDpm, 'votes'));
    $percentDpm    = fn($v) => $totalVotesDpm > 0 ? round(($v / $totalVotesDpm) * 100, 1) : 0;

    $labelsDpm = [];
    $seriesDpm = [];
    $metaDpm   = [];
    foreach ($liveDpm as $c) {
        $labelsDpm[] = 'No. ' . $c['no'] . ' - ' . $c['name'];
        $seriesDpm[] = (int)$c['votes'];
        $metaDpm[]   = ['no' => (int)$c['no'], 'name' => $c['name']];
    }

    // Rekap per fakultas
    $facultyStats = dbrows(
        'SELECT f.name AS faculty, f.code,
                COUNT(v.id) AS total,
                SUM(v.is_present) AS present,
                SUM(v.has_voted)  AS voted
         FROM faculties f
         LEFT JOIN voters v ON v.faculty_id = f.id AND v.is_active = 1
         WHERE f.is_active = 1
         GROUP BY f.id
         ORDER BY f.name ASC'
    );

} else {
    // ====== ADMIN FAKULTAS DATA ======
    $myFacultyId = (int)($admin['faculty_id'] ?? 0);

    $facultyRow  = $myFacultyId
        ? dbrow('SELECT * FROM faculties WHERE id = ?', [$myFacultyId])
        : null;
    $facultyName = $facultyRow['name'] ?? 'Fakultas tidak ditemukan';
    $today       = date('d M Y');

    $presentToday = (int)dbval(
        'SELECT COUNT(*) FROM voters WHERE faculty_id = ? AND is_present = 1',
        [$myFacultyId]
    );
    $votedToday = (int)dbval(
        'SELECT COUNT(*) FROM voters WHERE faculty_id = ? AND has_voted = 1',
        [$myFacultyId]
    );
    $notVotedYet = max($presentToday - $votedToday, 0);
    $turnout     = $presentToday ? round(($votedToday / $presentToday) * 100, 1) : 0;

    $tokensIssued = $election
        ? (int)dbval(
            'SELECT COUNT(*) FROM tokens t
             JOIN voters v ON v.id = t.voter_id
             WHERE v.faculty_id = ? AND t.election_id = ?',
            [$myFacultyId, $election['id']]
          )
        : 0;

    $tokensActive = $election
        ? (int)dbval(
            'SELECT COUNT(*) FROM tokens t
             JOIN voters v ON v.id = t.voter_id
             WHERE v.faculty_id = ? AND t.election_id = ?
               AND t.used_at IS NULL AND t.revoked_at IS NULL AND t.expires_at > NOW()',
            [$myFacultyId, $election['id']]
          )
        : 0;
}
?>

<div class="container-xxl flex-grow-1 container-p-y">

  <?php if ($role === 'superadmin'): ?>
    <!-- ========== SUPERADMIN VIEW ========== -->

    <div class="row">
      <div class="col-12 mb-4">
        <div class="card">
          <div class="d-flex align-items-end row">
            <div class="col-md-8">
              <div class="card-body">
                <h5 class="card-title text-primary mb-1">Dashboard Superadmin</h5>
                <p class="mb-2">
                  <?php if ($election): ?>
                    Periode: <span class="fw-semibold"><?php echo h($electionName); ?></span>
                    · Voting <?php echo date('d M Y H:i', strtotime($election['voting_start'])); ?>
                    s/d <?php echo date('d M Y H:i', strtotime($election['voting_end'])); ?>
                  <?php else: ?>
                    <span class="text-warning">Belum ada periode pemilihan aktif.</span>
                    <a href="index.php?p=election-settings" class="ms-2">Aktifkan sekarang</a>
                  <?php endif; ?>
                </p>
              </div>
            </div>
            <div class="col-md-4 text-center text-md-end">
              <div class="card-body pb-0 px-0 px-md-4">
                <span class="avatar-initial rounded-circle bg-label-primary"
                  style="width:64px;height:64px;display:inline-flex;align-items:center;justify-content:center;">
                  <i class="bx bx-shield-quarter" style="font-size:28px;"></i>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 mb-4">
        <div class="card">
          <div class="card-body">
            <span class="fw-semibold d-block mb-1">Total Pemilih</span>
            <h3 class="card-title mb-0"><?php echo number_format($totalVoters); ?></h3>
            <small class="text-muted">Seluruh fakultas</small>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 mb-4">
        <div class="card">
          <div class="card-body">
            <span class="fw-semibold d-block mb-1">Hadir (TPS)</span>
            <h3 class="card-title mb-0"><?php echo number_format($totalPresent); ?></h3>
            <small class="text-muted">Terverifikasi admin</small>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 mb-4">
        <div class="card">
          <div class="card-body">
            <span class="fw-semibold d-block mb-1">Sudah Memilih</span>
            <h3 class="card-title mb-0"><?php echo number_format($totalVoted); ?></h3>
            <small class="text-success fw-semibold">
              <?php echo $totalVoters ? round(($totalVoted / $totalVoters) * 100, 1) : 0; ?>% turnout
            </small>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 mb-4">
        <div class="card">
          <div class="card-body">
            <span class="fw-semibold d-block mb-1">Belum Memilih</span>
            <h3 class="card-title mb-0"><?php echo number_format($totalNotVoted); ?></h3>
            <small class="text-danger fw-semibold">Perlu monitoring</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Live Count Presma -->
    <?php if (!empty($live)): ?>
    <div class="row">
      <div class="col-12 mb-4">
        <div class="card" id="lcNormalCard">
          <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
              <h4 class="mb-0">Live Count Presiden Mahasiswa</h4>
              <small class="text-muted">Update realtime (refresh setiap 10 detik)</small>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <span class="badge bg-label-primary fs-6">
                Total: <span id="lcTotalVotes"><?php echo number_format($totalVotes); ?></span>
              </span>
              <a href="index.php?p=live-count" class="btn btn-primary btn-sm">
                <i class="bx bx-fullscreen me-1"></i> Full Page
              </a>
            </div>
          </div>

          <div class="card-body">
            <div class="row g-4 align-items-stretch">
              <div class="col-12 col-xl-7">
                <div class="card border shadow-none h-100">
                  <div class="card-body">
                    <div id="liveCountPie" style="min-height:360px;"></div>
                  </div>
                </div>
              </div>
              <div class="col-12 col-xl-5">
                <div class="card border shadow-none h-100">
                  <div class="card-body">
                    <h5 class="mb-3">Rincian Per Calon</h5>
                    <div class="table-responsive">
                      <table class="table table-hover align-middle">
                        <thead><tr><th>Calon</th><th class="text-end">Suara</th><th class="text-end">%</th></tr></thead>
                        <tbody id="liveCountTbody">
                          <?php foreach ($live as $row): ?>
                            <tr class="lc-row">
                              <td>
                                <div class="d-flex align-items-center gap-2">
                                  <span class="badge bg-label-primary">#<?php echo (int)$row['no']; ?></span>
                                  <span class="fw-semibold"><?php echo h($row['name']); ?></span>
                                </div>
                              </td>
                              <td class="text-end fw-semibold lcVotes"><?php echo number_format((int)$row['votes']); ?></td>
                              <td class="text-end fw-semibold lcPct"><?php echo $percent((int)$row['votes']); ?>%</td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                      <a class="btn btn-outline-primary btn-sm" href="index.php?p=candidates">
                        <i class="bx bx-id-card me-1"></i> Kelola Calon
                      </a>
                      <a class="btn btn-outline-primary btn-sm" href="index.php?p=votes">
                        <i class="bx bx-receipt me-1"></i> Data Suara &amp; Audit
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Live Count DPM -->
    <?php if (!empty($liveDpm)): ?>
    <div class="row">
      <div class="col-12 mb-4">
        <div class="card">
          <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
              <h4 class="mb-0">Live Count DPM</h4>
              <small class="text-muted">Update realtime (refresh setiap 10 detik)</small>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <span class="badge bg-label-info fs-6">
                Total: <span id="lcDpmTotalVotes"><?php echo number_format($totalVotesDpm); ?></span>
              </span>
              <a href="index.php?p=live-count" class="btn btn-info btn-sm text-white">
                <i class="bx bx-fullscreen me-1"></i> Full Page
              </a>
            </div>
          </div>

          <div class="card-body">
            <div class="row g-4 align-items-stretch">
              <div class="col-12 col-xl-7">
                <div class="card border shadow-none h-100">
                  <div class="card-body">
                    <div id="liveCountDpmPie" style="min-height:360px;"></div>
                  </div>
                </div>
              </div>
              <div class="col-12 col-xl-5">
                <div class="card border shadow-none h-100">
                  <div class="card-body">
                    <h5 class="mb-3">Rincian Per Calon</h5>
                    <div class="table-responsive">
                      <table class="table table-hover align-middle">
                        <thead><tr><th>Calon</th><th class="text-end">Suara</th><th class="text-end">%</th></tr></thead>
                        <tbody id="lcDpmTbody">
                          <?php foreach ($liveDpm as $row): ?>
                            <tr class="lc-dpm-row">
                              <td>
                                <div class="d-flex align-items-center gap-2">
                                  <span class="badge bg-label-info">#<?php echo (int)$row['no']; ?></span>
                                  <span class="fw-semibold"><?php echo h($row['name']); ?></span>
                                </div>
                              </td>
                              <td class="text-end fw-semibold lcDpmVotes"><?php echo number_format((int)$row['votes']); ?></td>
                              <td class="text-end fw-semibold lcDpmPct"><?php echo $percentDpm((int)$row['votes']); ?>%</td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                      <a class="btn btn-outline-info btn-sm" href="index.php?p=candidates">
                        <i class="bx bx-id-card me-1"></i> Kelola Calon
                      </a>
                      <a class="btn btn-outline-info btn-sm" href="index.php?p=votes&type=dpm">
                        <i class="bx bx-receipt me-1"></i> Data Suara DPM
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Rekap per Fakultas -->
    <?php if (!empty($facultyStats)): ?>
    <div class="row">
      <div class="col-12 mb-4">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Rekap Per Fakultas</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Fakultas</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Hadir</th>
                    <th class="text-end">Sudah Memilih</th>
                    <th class="text-end">Turnout</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($facultyStats as $fs): ?>
                    <?php $to = $fs['present'] > 0 ? round(($fs['voted'] / $fs['present']) * 100, 1) : 0; ?>
                    <tr>
                      <td><span class="fw-semibold"><?php echo h($fs['faculty']); ?></span></td>
                      <td class="text-end"><?php echo number_format((int)$fs['total']); ?></td>
                      <td class="text-end"><?php echo number_format((int)$fs['present']); ?></td>
                      <td class="text-end"><?php echo number_format((int)$fs['voted']); ?></td>
                      <td class="text-end">
                        <span class="badge <?php echo $to >= 70 ? 'bg-label-success' : ($to >= 40 ? 'bg-label-warning' : 'bg-label-danger'); ?>">
                          <?php echo $to; ?>%
                        </span>
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
    <?php endif; ?>

    <?php if (!empty($live) || !empty($liveDpm)): ?>
    <script>
    (function () {
      function genColors(n) {
        return Array.from({length:n}, (_,i) => `hsl(${Math.round(360/Math.max(n,1)*i)} 70% 55%)`);
      }
      function fmt(n) { return (n||0).toLocaleString('id-ID'); }
      function sum(a) { return a.reduce((s,v) => s+(Number(v)||0), 0); }

      function buildChart(elId, initSeries, initLabels, meta, badgeClass) {
        const el = document.getElementById(elId);
        if (!el || typeof ApexCharts === 'undefined') return null;
        const colors = genColors(initSeries.length);
        const chart = new ApexCharts(el, {
          chart: { type:'donut', height:360, toolbar:{show:false} },
          series: initSeries.slice(),
          labels: initLabels.slice(),
          colors,
          stroke: { width:3 },
          dataLabels: { enabled:true, formatter:(val,opts) => `#${meta[opts.seriesIndex]?.no} ${val.toFixed(1)}%` },
          legend: { position:'bottom' },
          plotOptions: { pie: { donut: { size:'70%', labels:{ show:true,
            value:{ show:true, fontSize:'24px', formatter: v => fmt(parseInt(v||0)) },
            total:{ show:true, label:'Total Suara', formatter: w => fmt(sum(w.globals.series)) }
          }}}},
          tooltip: { custom: ({series, seriesIndex, w}) => {
            const v   = series[seriesIndex]??0;
            const tot = sum(series);
            const pct = tot>0?(v/tot)*100:0;
            const m   = meta[seriesIndex]||{no:seriesIndex+1,name:'Calon'};
            const clr = w.globals.colors[seriesIndex];
            return `<div class="p-2" style="min-width:200px;"><div class="d-flex align-items-center gap-2 mb-1"><span style="width:10px;height:10px;border-radius:50%;background:${clr};display:inline-block;"></span><span class="badge ${badgeClass}">#${m.no}</span><span class="fw-semibold">${m.name}</span></div><div class="d-flex justify-content-between"><span class="text-muted">Suara</span><span class="fw-semibold">${fmt(v)}</span></div><div class="d-flex justify-content-between"><span class="text-muted">%</span><span class="fw-semibold">${pct.toFixed(1)}%</span></div></div>`;
          }}
        });
        chart.render();
        return chart;
      }

      function syncTable(tbodyId, totalElId, arr) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        const total = sum(arr);
        tbody.querySelectorAll('tr').forEach((tr, i) => {
          const v   = arr[i] || 0;
          const pct = total > 0 ? (v/total)*100 : 0;
          const vtd = tr.querySelector('[class*="Votes"]');
          const ptd = tr.querySelector('[class*="Pct"]');
          if (vtd) vtd.textContent = fmt(v);
          if (ptd) ptd.textContent = pct.toFixed(1) + '%';
        });
        const tel = document.getElementById(totalElId);
        if (tel) tel.textContent = fmt(total);
      }

      // ── Presma ────────────────────────────────────────────────────────────
      <?php if (!empty($live)): ?>
      let lcSeries = <?php echo json_encode($series, JSON_UNESCAPED_UNICODE); ?>;
      const lcMeta = <?php echo json_encode($meta,   JSON_UNESCAPED_UNICODE); ?>;
      const chartPresma = buildChart('liveCountPie',
        lcSeries, <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>,
        lcMeta, 'bg-label-primary');
      <?php endif; ?>

      // ── DPM ───────────────────────────────────────────────────────────────
      <?php if (!empty($liveDpm)): ?>
      let lcSeriesDpm = <?php echo json_encode($seriesDpm, JSON_UNESCAPED_UNICODE); ?>;
      const lcMetaDpm = <?php echo json_encode($metaDpm,   JSON_UNESCAPED_UNICODE); ?>;
      const chartDpm  = buildChart('liveCountDpmPie',
        lcSeriesDpm, <?php echo json_encode($labelsDpm, JSON_UNESCAPED_UNICODE); ?>,
        lcMetaDpm, 'bg-label-info');
      <?php endif; ?>

      // ── Polling ───────────────────────────────────────────────────────────
      async function refreshFromServer() {
        try {
          const res  = await fetch('../ajax/live-count.php');
          const data = await res.json();
          <?php if (!empty($live)): ?>
          if (Array.isArray(data.series) && chartPresma) {
            lcSeries = data.series;
            chartPresma.updateSeries(lcSeries, true);
            syncTable('liveCountTbody', 'lcTotalVotes', lcSeries);
          }
          <?php endif; ?>
          <?php if (!empty($liveDpm)): ?>
          if (Array.isArray(data.series_dpm) && chartDpm) {
            lcSeriesDpm = data.series_dpm;
            chartDpm.updateSeries(lcSeriesDpm, true);
            syncTable('lcDpmTbody', 'lcDpmTotalVotes', lcSeriesDpm);
          }
          <?php endif; ?>
        } catch(e) {}
      }
      setInterval(refreshFromServer, 10000);
    })();
    </script>
    <?php endif; ?>


  <?php else: ?>
    <!-- ========== ADMIN FAKULTAS VIEW ========== -->
    <div class="row">
      <div class="col-12 mb-4">
        <div class="card">
          <div class="d-flex align-items-end row">
            <div class="col-md-8">
              <div class="card-body">
                <h5 class="card-title text-primary mb-1">Dashboard Admin Fakultas</h5>
                <p class="mb-2">
                  <span class="fw-semibold"><?php echo h($facultyName); ?></span>
                  · <?php echo h($today); ?>
                </p>
                <?php if (!$election): ?>
                  <small class="text-warning">Belum ada periode pemilihan aktif.</small>
                <?php else: ?>
                  <small class="text-muted">Periode: <?php echo h($election['name']); ?></small>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-md-4 text-center text-md-end">
              <div class="card-body pb-0 px-0 px-md-4">
                <span class="avatar-initial rounded-circle bg-label-primary"
                  style="width:64px;height:64px;display:inline-flex;align-items:center;justify-content:center;">
                  <i class="bx bx-user" style="font-size:28px;"></i>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 col-md-6 mb-4">
        <div class="card"><div class="card-body">
          <span class="fw-semibold d-block mb-1">Hadir (Terverifikasi)</span>
          <h3 class="card-title mb-0"><?php echo number_format($presentToday); ?></h3>
          <small class="text-muted">Pemilih masuk kotak</small>
        </div></div>
      </div>

      <div class="col-lg-3 col-md-6 mb-4">
        <div class="card"><div class="card-body">
          <span class="fw-semibold d-block mb-1">Token Aktif</span>
          <h3 class="card-title mb-0"><?php echo number_format($tokensActive); ?></h3>
          <small class="text-muted">Belum dipakai/expired</small>
        </div></div>
      </div>

      <div class="col-lg-3 col-md-6 mb-4">
        <div class="card"><div class="card-body">
          <span class="fw-semibold d-block mb-1">Sudah Memilih</span>
          <h3 class="card-title mb-0"><?php echo number_format($votedToday); ?></h3>
          <small class="text-success fw-semibold"><?php echo $turnout; ?>% dari hadir</small>
        </div></div>
      </div>

      <div class="col-lg-3 col-md-6 mb-4">
        <div class="card"><div class="card-body">
          <span class="fw-semibold d-block mb-1">Belum Memilih</span>
          <h3 class="card-title mb-0"><?php echo number_format($notVotedYet); ?></h3>
          <small class="text-danger fw-semibold">Perlu diarahkan</small>
        </div></div>
      </div>
    </div>

    <div class="row">
      <div class="col-12 col-lg-7 mb-4">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Operasional TPS</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6"><div class="card border"><div class="card-body">
                <h6 class="mb-1">Verifikasi &amp; Buat Token</h6>
                <p class="text-muted mb-2">Cari NIM, cek KTM, generate token 1x.</p>
                <a href="index.php?p=issue-token" class="btn btn-primary btn-sm">Buka</a>
              </div></div></div>
              <div class="col-md-6"><div class="card border"><div class="card-body">
                <h6 class="mb-1">Daftar Pemilih Hadir</h6>
                <p class="text-muted mb-2">Yang sudah diverifikasi admin.</p>
                <a href="index.php?p=voters-present" class="btn btn-outline-primary btn-sm">Buka</a>
              </div></div></div>
              <div class="col-md-6"><div class="card border"><div class="card-body">
                <h6 class="mb-1">Token Aktif</h6>
                <p class="text-muted mb-2">Pantau token belum dipakai/expired.</p>
                <a href="index.php?p=tokens-active" class="btn btn-outline-primary btn-sm">Buka</a>
              </div></div></div>
              <div class="col-md-6"><div class="card border"><div class="card-body">
                <h6 class="mb-1">Rekap Fakultas</h6>
                <p class="text-muted mb-2">Progress voting untuk fakultas ini.</p>
                <a href="index.php?p=faculty-recap" class="btn btn-outline-primary btn-sm">Buka</a>
              </div></div></div>
            </div>
            <hr class="my-4">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="mb-0">Catatan keamanan</h6>
                <small class="text-muted">Token berlaku <?php echo $election ? (int)$election['token_ttl_minutes'] : '—'; ?> menit.</small>
              </div>
              <span class="badge bg-label-primary">Issued: <?php echo number_format($tokensIssued); ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-5 mb-4">
        <div class="card h-100">
          <div class="card-header"><h5 class="mb-0">Perhatian</h5></div>
          <div class="card-body">
            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Token aktif <span class="badge bg-label-warning"><?php echo number_format($tokensActive); ?></span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Hadir belum memilih <span class="badge bg-label-danger"><?php echo number_format($notVotedYet); ?></span>
              </li>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                Turnout <span class="badge bg-label-success"><?php echo $turnout; ?>%</span>
              </li>
            </ul>
            <div class="mt-4">
              <h6 class="mb-2">Checklist Admin</h6>
              <ol class="mb-0 text-muted">
                <li>Cek KTM pemilih</li>
                <li>Generate token 1x per NIM</li>
                <li>Arahkan login NIM + token</li>
                <li>Pastikan pemilih ambil foto (wajah + NIM)</li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>

</div>
