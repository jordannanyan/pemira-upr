<?php
// pages/election-settings.php — Superadmin: Kelola Periode Pemilihan

if ($role !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>403 - Akses ditolak</h4></div></div></div>";
    return;
}

$flash = flash_get();
$csrf  = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash_set('danger', 'Token keamanan tidak valid.');
        header('Location: index.php?p=election-settings');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name      = trim($_POST['name'] ?? '');
        $vStart    = trim($_POST['voting_start'] ?? '');
        $vEnd      = trim($_POST['voting_end'] ?? '');
        $ttl       = max(5, min(60, (int)($_POST['token_ttl_minutes'] ?? 10)));
        $setActive = isset($_POST['is_active']);
        $errors    = [];

        if ($name === '')   $errors[] = 'Nama periode wajib diisi.';
        if (!$vStart)       $errors[] = 'Waktu mulai voting wajib diisi.';
        if (!$vEnd)         $errors[] = 'Waktu selesai voting wajib diisi.';
        if ($vStart && $vEnd && strtotime($vEnd) <= strtotime($vStart)) {
            $errors[] = 'Waktu selesai harus setelah waktu mulai.';
        }

        if ($errors) {
            flash_set('danger', implode('<br>', array_map('h', $errors)));
        } else {
            if ($setActive) {
                dbq('UPDATE election_periods SET is_active = 0');
            }
            dbq(
                'INSERT INTO election_periods (name, voting_start, voting_end, token_ttl_minutes, is_active, created_by)
                 VALUES (?,?,?,?,?,?)',
                [$name, $vStart, $vEnd, $ttl, $setActive ? 1 : 0, $admin['id']]
            );
            audit_log('create_election', 'superadmin', $admin['id'], null, 'election_periods');
            flash_set('success', "Periode '$name' berhasil dibuat.");
        }
        header('Location: index.php?p=election-settings');
        exit;
    }

    if ($action === 'set_active') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            dbq('UPDATE election_periods SET is_active = 0');
            dbq('UPDATE election_periods SET is_active = 1 WHERE id = ?', [$id]);
            audit_log('set_active_election', 'superadmin', $admin['id'], null, 'election_periods', $id);
            flash_set('success', 'Periode pemilihan berhasil diaktifkan.');
        }
        header('Location: index.php?p=election-settings');
        exit;
    }

    if ($action === 'deactivate') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            dbq('UPDATE election_periods SET is_active = 0 WHERE id = ?', [$id]);
            flash_set('info', 'Periode berhasil dinonaktifkan.');
        }
        header('Location: index.php?p=election-settings');
        exit;
    }

    if ($action === 'update_ttl') {
        $id  = (int)($_POST['id'] ?? 0);
        $ttl = max(5, min(60, (int)($_POST['token_ttl_minutes'] ?? 10)));
        if ($id) {
            dbq('UPDATE election_periods SET token_ttl_minutes = ? WHERE id = ?', [$ttl, $id]);
            flash_set('success', "TTL token diupdate menjadi $ttl menit.");
        }
        header('Location: index.php?p=election-settings');
        exit;
    }
}

$periods = dbrows(
    'SELECT e.*, u.name AS created_by_name,
            (SELECT COUNT(*) FROM votes WHERE election_id = e.id) AS total_votes
     FROM election_periods e
     LEFT JOIN admin_users u ON u.id = e.created_by
     ORDER BY e.id DESC'
);

$active = active_election();
?>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">Pengaturan Pemilihan</h4>
            <div class="text-muted">
                <?php if ($active): ?>
                    <span class="badge bg-label-success">
                        <i class="bx bx-radio-circle-marked me-1"></i> Aktif: <?php echo h($active['name']); ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-label-warning">Belum ada periode aktif</span>
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
            <i class="bx bx-plus me-1"></i> Buat Periode Baru
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible mb-3">
            <?php echo $flash['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar Periode Pemilihan</h5>
        </div>
        <div class="card-body">
            <?php if (empty($periods)): ?>
                <div class="text-center text-muted py-4">Belum ada periode pemilihan. Buat yang pertama!</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Voting Mulai</th>
                                <th>Voting Selesai</th>
                                <th class="text-center">TTL Token</th>
                                <th class="text-end">Total Suara</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periods as $p): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo h($p['name']); ?></td>
                                    <td><?php echo date('d M Y H:i', strtotime($p['voting_start'])); ?></td>
                                    <td><?php echo date('d M Y H:i', strtotime($p['voting_end'])); ?></td>
                                    <td class="text-center">
                                        <form method="post" class="d-flex gap-1 align-items-center justify-content-center">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="update_ttl">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <input type="number" class="form-control form-control-sm" name="token_ttl_minutes"
                                                   value="<?php echo (int)$p['token_ttl_minutes']; ?>"
                                                   min="5" max="60" style="width:70px;">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <i class="bx bx-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end"><?php echo number_format((int)$p['total_votes']); ?></td>
                                    <td class="text-center">
                                        <?php if ($p['is_active']): ?>
                                            <span class="badge bg-label-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-label-secondary">Tidak Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!$p['is_active']): ?>
                                            <form method="post" class="d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="set_active">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success"
                                                        onclick="return confirm('Aktifkan periode ini? Periode lain akan dinonaktifkan.')">
                                                    <i class="bx bx-play me-1"></i> Aktifkan
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" class="d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                                        onclick="return confirm('Nonaktifkan periode ini?')">
                                                    <i class="bx bx-pause"></i>
                                                </button>
                                            </form>
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

<!-- Modal Buat Periode -->
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Periode Pemilihan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Nama Periode</label>
                        <input type="text" class="form-control" name="name" required
                               placeholder="Contoh: PEMIRA UPR 2026">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Waktu Mulai Voting</label>
                        <input type="datetime-local" class="form-control" name="voting_start" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Waktu Selesai Voting</label>
                        <input type="datetime-local" class="form-control" name="voting_end" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">TTL Token (menit)</label>
                        <input type="number" class="form-control" name="token_ttl_minutes"
                               value="10" min="5" max="60">
                        <small class="text-muted">Berapa menit token berlaku setelah dibuat</small>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="setActive">
                            <label class="form-check-label" for="setActive">
                                Langsung aktifkan periode ini (periode lain dinonaktifkan)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Buat Periode</button>
                </div>
            </form>
        </div>
    </div>
</div>
