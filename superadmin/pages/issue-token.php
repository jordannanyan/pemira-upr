<?php
// pages/issue-token.php — Admin Fakultas: Verifikasi & Issue Token

if ($role !== 'admin_faculty') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4 class='mb-2'>403 - Forbidden</h4>
            <p class='mb-0'>Halaman ini khusus Admin TPU.</p>
          </div></div></div>";
    return;
}

$myFacultyId = (int)($admin['faculty_id'] ?? 0);
$facultyRow  = $myFacultyId ? dbrow('SELECT * FROM faculties WHERE id = ?', [$myFacultyId]) : null;
$facultyName = $facultyRow['name'] ?? 'Fakultas tidak ditemukan';

$election = active_election();

$flash     = flash_get();
$searchNim = trim($_GET['nim'] ?? '');
$found     = null;

// ====== HANDLE POST ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash_set('danger', 'Token keamanan tidak valid.');
        header('Location: index.php?p=issue-token');
        exit;
    }

    $action = $_POST['_action'] ?? '';
    $nim    = trim($_POST['nim'] ?? '');

    if ($action === 'search') {
        $searchNim = $nim;
        $found = $nim ? dbrow(
            'SELECT v.*, f.name AS faculty_name FROM voters v
             JOIN faculties f ON f.id = v.faculty_id
             WHERE v.nim = ? AND v.faculty_id = ?',
            [$nim, $myFacultyId]
        ) : null;
        if (!$found && $nim !== '') {
            flash_set('warning', "NIM $nim tidak ditemukan di daftar pemilih fakultas ini.");
        }
        $flash = flash_get();
    }

    if ($action === 'mark_present') {
        if (!$nim) {
            flash_set('danger', 'NIM wajib diisi.');
        } else {
            $voter = dbrow('SELECT * FROM voters WHERE nim = ? AND faculty_id = ?', [$nim, $myFacultyId]);
            if (!$voter) {
                flash_set('danger', "NIM $nim tidak ditemukan di fakultas ini.");
            } elseif ($voter['is_present']) {
                flash_set('info', "Pemilih NIM $nim sudah ditandai hadir sebelumnya.");
            } else {
                dbq(
                    'UPDATE voters SET is_present = 1, present_at = NOW(), present_by = ? WHERE id = ?',
                    [$admin['id'], $voter['id']]
                );
                audit_log('mark_present', 'admin_faculty', $admin['id'], $nim, 'voters', $voter['id']);
                flash_set('success', "Pemilih $nim berhasil ditandai hadir TPU.");
            }
        }
        $searchNim = $nim;
        header('Location: index.php?p=issue-token&nim=' . urlencode($nim));
        exit;
    }

    if ($action === 'issue_token') {
        if (!$election) {
            flash_set('danger', 'Tidak ada periode pemilihan aktif.');
        } elseif (!$nim) {
            flash_set('danger', 'NIM wajib diisi.');
        } else {
            $voter = dbrow('SELECT * FROM voters WHERE nim = ? AND faculty_id = ? AND is_active = 1', [$nim, $myFacultyId]);
            if (!$voter) {
                flash_set('danger', "NIM $nim tidak ditemukan atau tidak aktif di fakultas ini.");
            } elseif (!$voter['is_present']) {
                flash_set('danger', 'Pemilih belum diverifikasi hadir. Tandai hadir dulu.');
            } elseif ($voter['has_voted']) {
                flash_set('danger', 'Pemilih sudah memilih. Token tidak bisa diterbitkan.');
            } else {
                // Cek token aktif yang belum dipakai
                $existingToken = dbrow(
                    'SELECT * FROM tokens WHERE voter_id = ? AND election_id = ?
                     AND used_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()
                     LIMIT 1',
                    [$voter['id'], $election['id']]
                );
                if ($existingToken) {
                    flash_set('warning', "NIM $nim sudah punya token aktif: <b>{$existingToken['token']}</b> (berlaku hingga " . date('d M Y H:i', strtotime($existingToken['expires_at'])) . " WIB). Cabut dulu jika ingin ganti.");
                } else {
                    $token     = create_unique_token(6);
                    $ttl       = (int)$election['token_ttl_minutes'];
                    $expiresAt = date('Y-m-d H:i:s', time() + $ttl * 60);

                    dbq(
                        'INSERT INTO tokens (voter_id, election_id, token, issued_by, expires_at)
                         VALUES (?,?,?,?,?)',
                        [$voter['id'], $election['id'], $token, $admin['id'], $expiresAt]
                    );
                    audit_log('issue_token', 'admin_faculty', $admin['id'], $nim, 'tokens', null, "token=$token");
                    flash_set('token_ok', json_encode([
                        'token'   => $token,
                        'expires' => date('d M Y H:i', strtotime($expiresAt)) . ' WIB',
                        'nim'     => $nim,
                        'name'    => $voter['name'],
                    ]));
                }
            }
        }
        $searchNim = $nim;
        header('Location: index.php?p=issue-token&nim=' . urlencode($nim));
        exit;
    }

    if ($action === 'revoke_token') {
        $tokenId = (int)($_POST['token_id'] ?? 0);
        $tkn = $tokenId ? dbrow(
            'SELECT t.*, v.nim FROM tokens t JOIN voters v ON v.id = t.voter_id
             WHERE t.id = ? AND v.faculty_id = ?',
            [$tokenId, $myFacultyId]
        ) : null;
        if (!$tkn) {
            flash_set('danger', 'Token tidak ditemukan.');
        } else {
            dbq('UPDATE tokens SET revoked_at = NOW(), revoked_by = ? WHERE id = ?', [$admin['id'], $tokenId]);
            audit_log('revoke_token', 'admin_faculty', $admin['id'], $tkn['nim'], 'tokens', $tokenId);
            flash_set('success', 'Token ' . $tkn['token'] . ' berhasil dicabut.');
        }
        header('Location: index.php?p=issue-token');
        exit;
    }
}

// GET: cari voter
if ($searchNim !== '' && !$found) {
    $found = dbrow(
        'SELECT v.*, f.name AS faculty_name FROM voters v
         JOIN faculties f ON f.id = v.faculty_id
         WHERE v.nim = ? AND v.faculty_id = ?',
        [$searchNim, $myFacultyId]
    );
}

// Token aktif untuk voter yang ditemukan
$activeTokensForFound = [];
if ($found && $election) {
    $activeTokensForFound = dbrows(
        'SELECT * FROM tokens WHERE voter_id = ? AND election_id = ?
         AND used_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()
         ORDER BY issued_at DESC',
        [$found['id'], $election['id']]
    );
}

// Daftar token aktif fakultas ini
$activeTokens = $election ? dbrows(
    'SELECT t.*, v.nim, v.name FROM tokens t
     JOIN voters v ON v.id = t.voter_id
     WHERE v.faculty_id = ? AND t.election_id = ?
       AND t.used_at IS NULL AND t.revoked_at IS NULL AND t.expires_at > NOW()
     ORDER BY t.issued_at DESC
     LIMIT 20',
    [$myFacultyId, $election['id']]
) : [];

$csrf = csrf_token();

// Token success flash
$tokenOk = null;
if ($flash && $flash['type'] === 'token_ok') {
    $tokenOk = json_decode($flash['msg'], true);
    $flash   = null;
}
?>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-2">
            <span class="avatar-initial rounded bg-label-info">
                <i class="bx bx-key"></i>
            </span>
            <div>
                <h4 class="mb-0">Verifikasi &amp; Issue Token</h4>
                <small class="text-muted">Admin TPU · <?php echo h($facultyName); ?></small>
            </div>
        </div>
        <a href="index.php?p=dashboard" class="btn btn-outline-secondary">
            <i class="bx bx-left-arrow-alt me-1"></i> Kembali
        </a>
    </div>

    <?php if (!$election): ?>
        <div class="alert alert-warning mb-4">
            <i class="bx bx-error-circle me-1"></i>
            Belum ada periode pemilihan aktif. Hubungi superadmin.
        </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> mb-4 alert-dismissible">
            <?php echo $flash['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($tokenOk): ?>
        <div class="alert alert-success mb-4">
            <div class="d-flex align-items-start gap-2">
                <span class="avatar-initial rounded bg-label-success" style="width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;">
                    <i class="bx bx-check-circle"></i>
                </span>
                <div class="w-100">
                    <div class="fw-semibold mb-1">Token berhasil dibuat</div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="text-muted">Pemilih:</span>
                        <span class="fw-semibold"><?php echo h($tokenOk['name']); ?></span>
                        <span class="text-muted">NIM:</span>
                        <span class="fw-semibold"><?php echo h($tokenOk['nim']); ?></span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="text-muted">Token:</span>
                        <span class="badge bg-label-primary" style="font-size:1.1rem;font-weight:800;letter-spacing:.1em;">
                            <?php echo h($tokenOk['token']); ?>
                        </span>
                        <span class="text-muted">· Berlaku s/d:</span>
                        <span class="fw-bold"><?php echo h($tokenOk['expires']); ?></span>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Berikan token ini ke pemilih untuk login (NIM + token). Token hangus setelah dipakai atau expired.
                    </small>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- LEFT: Search + Verifikasi -->
        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0">Cari Pemilih</h5>
                        <small class="text-muted">Masukkan NIM, cek KTM, lalu terbitkan token.</small>
                    </div>
                    <span class="badge bg-label-secondary">
                        TTL: <?php echo $election ? (int)$election['token_ttl_minutes'] : '—'; ?> menit
                    </span>
                </div>

                <div class="card-body">
                    <form method="post" class="row g-2 align-items-end mb-3">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="_action" value="search">
                        <div class="col-12 col-md-8">
                            <label class="form-label">NIM Pemilih</label>
                            <input class="form-control" name="nim" value="<?php echo h($searchNim); ?>"
                                   placeholder="Contoh: 2021001234" inputmode="numeric" />
                        </div>
                        <div class="col-12 col-md-4 d-grid">
                            <button class="btn btn-primary" type="submit">
                                <i class="bx bx-search me-1"></i> Cari
                            </button>
                        </div>
                    </form>

                    <?php if ($searchNim === ''): ?>
                        <div class="alert alert-info mb-0">Masukkan NIM untuk mencari pemilih dan menerbitkan token.</div>
                    <?php elseif (!$found): ?>
                        <div class="alert alert-warning mb-0">
                            NIM <b><?php echo h($searchNim); ?></b> tidak ditemukan di daftar pemilih fakultas ini.
                        </div>
                    <?php else: ?>
                        <div class="card border shadow-none mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                    <div>
                                        <h5 class="mb-1"><?php echo h($found['name']); ?></h5>
                                        <div class="text-muted">NIM: <span class="fw-semibold"><?php echo h($found['nim']); ?></span></div>
                                        <div class="text-muted">Fakultas: <span class="fw-semibold"><?php echo h($found['faculty_name'] ?? $facultyName); ?></span></div>
                                    </div>
                                    <div class="d-flex align-items-start flex-wrap gap-2">
                                        <span class="badge <?php echo $found['is_present'] ? 'bg-label-success' : 'bg-label-warning'; ?>">
                                            <?php echo $found['is_present'] ? 'Hadir TPU' : 'Belum Hadir'; ?>
                                        </span>
                                        <span class="badge <?php echo $found['has_voted'] ? 'bg-label-secondary' : 'bg-label-info'; ?>">
                                            <?php echo $found['has_voted'] ? 'Sudah Memilih' : 'Belum Memilih'; ?>
                                        </span>
                                        <span class="badge <?php echo !empty($activeTokensForFound) ? 'bg-label-primary' : 'bg-label-danger'; ?>">
                                            <?php echo !empty($activeTokensForFound) ? 'Ada Token Aktif' : 'Belum Ada Token'; ?>
                                        </span>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <div class="row g-2">
                                    <?php if (!$found['is_present']): ?>
                                    <div class="col-12 col-md-6">
                                        <form method="post">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="_action" value="mark_present">
                                            <input type="hidden" name="nim" value="<?php echo h($found['nim']); ?>">
                                            <button class="btn btn-outline-success w-100" type="submit">
                                                <i class="bx bx-check-circle me-1"></i> Tandai Hadir TPU
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($found['is_present'] && !$found['has_voted'] && $election): ?>
                                    <div class="col-12 col-md-6">
                                        <form method="post">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="_action" value="issue_token">
                                            <input type="hidden" name="nim" value="<?php echo h($found['nim']); ?>">
                                            <button class="btn btn-primary w-100" type="submit">
                                                <i class="bx bx-key me-1"></i> Generate Token 1x
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($activeTokensForFound)): ?>
                                    <div class="col-12">
                                        <div class="alert alert-info mb-0 p-2">
                                            <small>Token aktif:
                                                <?php foreach ($activeTokensForFound as $at): ?>
                                                    <b><?php echo h($at['token']); ?></b>
                                                    (berlaku s/d <?php echo date('H:i', strtotime($at['expires_at'])); ?>)
                                                <?php endforeach; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="col-12">
                                        <small class="text-muted">
                                            Aturan: 1 token aktif per NIM. Token expired otomatis dan hangus setelah dipakai.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning mb-0">
                            <div class="fw-semibold mb-1"><i class="bx bx-shield-quarter me-1"></i> Checklist Admin TPU</div>
                            <ol class="mb-0">
                                <li>Cek KTM dan cocokkan NIM</li>
                                <li>Pastikan pemilih ada di daftar (fakultas yang sama)</li>
                                <li>Tandai hadir TPU</li>
                                <li>Terbitkan token, minta pemilih login NIM + token</li>
                                <li>Pastikan foto wajah + NIM dilakukan di bilik</li>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT: Token aktif -->
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-0">Token Aktif Fakultas</h5>
                        <small class="text-muted">Belum dipakai, belum expired (max 20)</small>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="index.php?p=tokens-active">
                        <i class="bx bx-list-ul me-1"></i> Lihat Semua
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($activeTokens)): ?>
                        <div class="text-muted text-center py-4">Belum ada token aktif.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Token</th>
                                        <th>Pemilih</th>
                                        <th class="text-end">Sisa</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeTokens as $t): ?>
                                        <?php
                                        $remain    = max(strtotime($t['expires_at']) - time(), 0);
                                        $remainMin = (int)ceil($remain / 60);
                                        $isSoon    = $remainMin <= 2;
                                        ?>
                                        <tr>
                                            <td class="fw-semibold">
                                                <span class="badge bg-label-primary"><?php echo h($t['token']); ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo h($t['name']); ?></div>
                                                <small class="text-muted">NIM: <?php echo h($t['nim']); ?></small>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge <?php echo $isSoon ? 'bg-label-warning' : 'bg-label-success'; ?>">
                                                    <?php echo $remainMin; ?> mnt
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <form method="post" class="d-inline">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="_action" value="revoke_token">
                                                    <input type="hidden" name="token_id" value="<?php echo (int)$t['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Cabut token">
                                                        <i class="bx bx-x-circle"></i>
                                                    </button>
                                                </form>
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
