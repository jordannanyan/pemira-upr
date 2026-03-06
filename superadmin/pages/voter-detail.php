<?php
// pages/voter-detail.php — Detail Pemilih (Superadmin / Admin Fakultas)

$nim = trim($_GET['nim'] ?? '');
if ($nim === '') {
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>400 - NIM diperlukan</h4>
            <p>Contoh: <code>?p=voter-detail&amp;nim=2022101001</code></p>
          </div></div></div>";
    return;
}

$voter = dbrow(
    'SELECT v.*, f.name AS faculty_name
     FROM voters v
     JOIN faculties f ON f.id = v.faculty_id
     WHERE v.nim = ?',
    [$nim]
);

if (!$voter) {
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>404 - Pemilih tidak ditemukan</h4>
            <p>NIM <b>" . h($nim) . "</b> tidak terdaftar.</p>
            <a href='index.php?p=voters' class='btn btn-outline-secondary'>Kembali</a>
          </div></div></div>";
    return;
}

// Admin Fakultas: hanya bisa lihat pemilih dari fakultasnya
if ($role === 'admin_faculty' && (int)$voter['faculty_id'] !== (int)($admin['faculty_id'] ?? 0)) {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>403 - Akses ditolak</h4></div></div></div>";
    return;
}

// Handle toggle active (superadmin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'superadmin') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash_set('danger', 'Token keamanan tidak valid.');
        header('Location: index.php?p=voter-detail&nim=' . urlencode($nim));
        exit;
    }
    if (($_POST['action'] ?? '') === 'toggle_active') {
        $newStatus = $voter['is_active'] ? 0 : 1;
        dbq('UPDATE voters SET is_active = ? WHERE id = ?', [$newStatus, $voter['id']]);
        audit_log('toggle_voter', 'superadmin', $admin['id'], $voter['nim'], 'voters', $voter['id']);
        flash_set('success', 'Status pemilih berhasil diubah.');
        header('Location: index.php?p=voter-detail&nim=' . urlencode($nim));
        exit;
    }
}

$flash = flash_get();

// Reload voter after possible update
$voter = dbrow(
    'SELECT v.*, f.name AS faculty_name FROM voters v
     JOIN faculties f ON f.id = v.faculty_id WHERE v.nim = ?',
    [$nim]
);

// Token history
$tokens = dbrows(
    'SELECT t.*, a.name AS issued_by_name, r.name AS revoked_by_name, e.name AS election_name
     FROM tokens t
     JOIN election_periods e ON e.id = t.election_id
     JOIN admin_users a ON a.id = t.issued_by
     LEFT JOIN admin_users r ON r.id = t.revoked_by
     WHERE t.voter_id = ?
     ORDER BY t.issued_at DESC',
    [$voter['id']]
);

// Latest photo
$photo = dbrow(
    'SELECT * FROM voter_photos WHERE voter_id = ? ORDER BY taken_at DESC LIMIT 1',
    [$voter['id']]
);

// Audit log
$auditLogs = dbrows(
    'SELECT * FROM audit_log WHERE actor_nim = ? ORDER BY created_at DESC LIMIT 50',
    [$voter['nim']]
);

// Who marked present
$presentByName = $voter['present_by']
    ? dbval('SELECT name FROM admin_users WHERE id = ?', [$voter['present_by']])
    : null;
?>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div class="d-flex align-items-center gap-2">
            <span class="avatar-initial rounded bg-label-primary" style="width:40px;height:40px;">
                <i class="bx bx-user" style="font-size:20px;"></i>
            </span>
            <div>
                <h4 class="mb-0"><?php echo h($voter['name']); ?></h4>
                <small class="text-muted">
                    NIM: <b><?php echo h($voter['nim']); ?></b> · <?php echo h($voter['faculty_name']); ?>
                </small>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php?p=voters" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i> Kembali
            </a>
            <?php if ($role === 'superadmin'): ?>
                <form method="post" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <button type="submit"
                            class="btn <?php echo $voter['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                            onclick="return confirm('Ubah status aktif pemilih ini?')">
                        <i class="bx <?php echo $voter['is_active'] ? 'bx-user-minus' : 'bx-user-plus'; ?> me-1"></i>
                        <?php echo $voter['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible mb-3">
            <?php echo $flash['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- LEFT: Status + Token + Audit -->
        <div class="col-12 col-lg-8">

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Status Pemilih</h5>
                    <div class="d-flex gap-2">
                        <span class="badge <?php echo $voter['is_active'] ? 'bg-label-success' : 'bg-label-secondary'; ?>">
                            <?php echo $voter['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted mb-1"><i class="bx bx-check-shield me-1"></i>Kehadiran</div>
                                <div class="fw-semibold">
                                    <?php if ($voter['is_present']): ?>
                                        <span class="badge bg-label-info">Hadir</span>
                                    <?php else: ?>
                                        <span class="badge bg-label-secondary">Belum Hadir</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($voter['present_at']): ?>
                                    <small class="text-muted d-block mt-1">
                                        <?php echo date('d M Y H:i', strtotime($voter['present_at'])); ?>
                                    </small>
                                <?php endif; ?>
                                <?php if ($presentByName): ?>
                                    <small class="text-muted">oleh: <?php echo h($presentByName); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted mb-1"><i class="bx bx-check-circle me-1"></i>Memilih</div>
                                <div class="fw-semibold">
                                    <?php if ($voter['has_voted']): ?>
                                        <span class="badge bg-label-success">Sudah Memilih</span>
                                    <?php else: ?>
                                        <span class="badge bg-label-warning">Belum Memilih</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($voter['voted_at']): ?>
                                    <small class="text-muted d-block mt-1">
                                        <?php echo date('d M Y H:i', strtotime($voter['voted_at'])); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted mb-1"><i class="bx bx-camera me-1"></i>Foto Bukti</div>
                                <?php if ($photo): ?>
                                    <button class="btn btn-sm btn-outline-primary mt-1"
                                            data-bs-toggle="modal" data-bs-target="#modalPhoto"
                                            data-photo="<?php echo h('../' . $photo['file_path']); ?>">
                                        <i class="bx bx-image me-1"></i> Lihat Foto
                                    </button>
                                    <small class="text-muted d-block">
                                        <?php echo date('d M Y H:i', strtotime($photo['taken_at'])); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">Belum ada foto</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Token History -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Riwayat Token (<?php echo count($tokens); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tokens)): ?>
                        <div class="text-muted">Belum pernah mendapat token.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Token</th>
                                        <th>Periode</th>
                                        <th>Diterbitkan</th>
                                        <th>Expired</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tokens as $t):
                                        $now   = time();
                                        $expTs = strtotime($t['expires_at']);
                                        if ($t['used_at']) {
                                            [$tLabel, $tClass] = ['Terpakai', 'bg-label-success'];
                                        } elseif ($t['revoked_at']) {
                                            [$tLabel, $tClass] = ['Dicabut', 'bg-label-danger'];
                                        } elseif ($expTs < $now) {
                                            [$tLabel, $tClass] = ['Expired', 'bg-label-secondary'];
                                        } else {
                                            [$tLabel, $tClass] = ['Aktif', 'bg-label-warning'];
                                        }
                                    ?>
                                        <tr>
                                            <td class="fw-semibold" style="letter-spacing:.05em;"><?php echo h($t['token']); ?></td>
                                            <td><small><?php echo h($t['election_name']); ?></small></td>
                                            <td>
                                                <small><?php echo date('d/m H:i', strtotime($t['issued_at'])); ?></small><br>
                                                <small class="text-muted">oleh <?php echo h($t['issued_by_name']); ?></small>
                                            </td>
                                            <td>
                                                <small class="<?php echo $expTs < $now ? 'text-danger' : ''; ?>">
                                                    <?php echo date('d/m H:i', $expTs); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?php echo $tClass; ?>"><?php echo $tLabel; ?></span>
                                                <?php if ($t['revoked_at'] && $t['revoked_by_name']): ?>
                                                    <br><small class="text-muted">oleh <?php echo h($t['revoked_by_name']); ?></small>
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

            <!-- Audit Log -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Log Aktivitas</h5>
                    <small class="text-muted">Dari audit_log, 50 terbaru</small>
                </div>
                <div class="card-body">
                    <?php if (empty($auditLogs)): ?>
                        <div class="text-muted">Tidak ada log untuk pemilih ini.</div>
                    <?php else: ?>
                        <ul class="timeline mb-0">
                            <?php foreach ($auditLogs as $log): ?>
                                <li class="timeline-item timeline-item-transparent">
                                    <span class="timeline-point timeline-point-primary"></span>
                                    <div class="timeline-event">
                                        <div class="timeline-header mb-1">
                                            <h6 class="mb-0"><?php echo h($log['action']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo date('d M Y H:i:s', strtotime($log['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-0 text-muted small">
                                            <?php if ($log['description']): echo h($log['description']); endif; ?>
                                            <?php if ($log['ip_address']): ?> · IP: <?php echo h($log['ip_address']); ?><?php endif; ?>
                                        </p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- RIGHT: Summary -->
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ringkasan</h5>
                </div>
                <div class="card-body">
                    <dl class="row g-2 mb-3">
                        <dt class="col-5 text-muted">NIM</dt>
                        <dd class="col-7 fw-semibold mb-0"><?php echo h($voter['nim']); ?></dd>
                        <dt class="col-5 text-muted">Nama</dt>
                        <dd class="col-7 mb-0"><?php echo h($voter['name']); ?></dd>
                        <dt class="col-5 text-muted">Fakultas</dt>
                        <dd class="col-7 mb-0"><?php echo h($voter['faculty_name']); ?></dd>
                        <?php if ($voter['prodi']): ?>
                            <dt class="col-5 text-muted">Prodi</dt>
                            <dd class="col-7 mb-0"><?php echo h($voter['prodi']); ?></dd>
                        <?php endif; ?>
                        <?php if ($voter['angkatan']): ?>
                            <dt class="col-5 text-muted">Angkatan</dt>
                            <dd class="col-7 mb-0"><?php echo h((string)$voter['angkatan']); ?></dd>
                        <?php endif; ?>
                        <?php if ($voter['gender']): ?>
                            <dt class="col-5 text-muted">Jenis Kelamin</dt>
                            <dd class="col-7 mb-0">
                                <?php echo $voter['gender'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?>
                            </dd>
                        <?php endif; ?>
                        <dt class="col-5 text-muted">Terdaftar</dt>
                        <dd class="col-7 mb-0">
                            <small><?php echo date('d M Y', strtotime($voter['created_at'])); ?></small>
                        </dd>
                        <dt class="col-5 text-muted">Total Token</dt>
                        <dd class="col-7 mb-0"><?php echo count($tokens); ?></dd>
                    </dl>

                    <?php if ($role === 'admin_faculty' && !$voter['is_present'] && $voter['is_active']): ?>
                        <a href="index.php?p=issue-token" class="btn btn-primary w-100">
                            <i class="bx bx-user-check me-1"></i> Tandai Hadir & Issue Token
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal Photo -->
<div class="modal fade" id="modalPhoto" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Foto Pemilih — <?php echo h($voter['name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="photoModalImg" src="" alt="Foto pemilih" class="img-fluid rounded"
                     style="max-height:520px;"
                     onerror="this.style.display='none';">
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const m = document.getElementById('modalPhoto');
    if (m) {
        m.addEventListener('show.bs.modal', (e) => {
            const img = document.getElementById('photoModalImg');
            img.style.display = 'block';
            img.src = e.relatedTarget?.dataset.photo || '';
        });
    }
})();
</script>
