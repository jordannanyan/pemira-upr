<?php
// pages/tokens-active.php

$myFacultyId = $role === 'admin_faculty' ? (int)($admin['faculty_id'] ?? 0) : null;
$election    = active_election();
$csrf        = csrf_token();
$flash       = flash_get();

// Revoke token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash_set('danger', 'Token keamanan tidak valid.');
        header('Location: index.php?p=tokens-active');
        exit;
    }

    $tokenId = (int)($_POST['token_id'] ?? 0);
    if ($tokenId) {
        $cond = ['t.id = ?'];
        $params = [$tokenId];
        if ($myFacultyId) { // admin hanya bisa cabut token fakultasnya
            $cond[]   = 'v.faculty_id = ?';
            $params[] = $myFacultyId;
        }
        $tkn = dbrow(
            'SELECT t.*, v.nim FROM tokens t JOIN voters v ON v.id = t.voter_id WHERE ' . implode(' AND ', $cond),
            $params
        );
        if ($tkn) {
            dbq('UPDATE tokens SET revoked_at = NOW(), revoked_by = ? WHERE id = ?', [$admin['id'], $tokenId]);
            audit_log('revoke_token', $role === 'superadmin' ? 'superadmin' : 'admin_faculty', $admin['id'], $tkn['nim'], 'tokens', $tokenId);
            flash_set('success', 'Token ' . $tkn['token'] . ' berhasil dicabut.');
        } else {
            flash_set('danger', 'Token tidak ditemukan.');
        }
    }
    header('Location: index.php?p=tokens-active');
    exit;
}

// Query tokens aktif
$params = [];
$where  = ['t.used_at IS NULL', 't.revoked_at IS NULL', 't.expires_at > NOW()'];
if ($election) {
    $where[]  = 't.election_id = ?';
    $params[] = $election['id'];
}
if ($myFacultyId) {
    $where[]  = 'v.faculty_id = ?';
    $params[] = $myFacultyId;
}

$rows = dbrows(
    'SELECT t.*, v.nim, v.name AS voter_name, f.name AS faculty_name
     FROM tokens t
     JOIN voters v ON v.id = t.voter_id
     JOIN faculties f ON f.id = v.faculty_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY t.expires_at ASC',
    $params
);
?>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0">Token Aktif</h4>
            <small class="text-muted">
                Token yang belum dipakai dan belum expired ·
                <?php echo count($rows); ?> token
            </small>
        </div>
        <a href="index.php?p=issue-token" class="btn btn-outline-primary">
            <i class="bx bx-key me-1"></i> Issue Token
        </a>
    </div>

    <?php if (!$election): ?>
        <div class="alert alert-warning mb-4">Belum ada periode pemilihan aktif.</div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible mb-3">
            <?php echo $flash['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bx bx-check-shield fs-1 d-block mb-2"></i>
                    Tidak ada token aktif saat ini.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Pemilih</th>
                                <?php if ($role === 'superadmin'): ?>
                                    <th>Fakultas</th>
                                <?php endif; ?>
                                <th>Dibuat</th>
                                <th class="text-end">Sisa</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $t): ?>
                                <?php
                                $remain    = max(strtotime($t['expires_at']) - time(), 0);
                                $remainMin = (int)ceil($remain / 60);
                                $isSoon    = $remainMin <= 2;
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-label-primary"
                                              style="font-size:.95rem;letter-spacing:.08em;">
                                            <?php echo h($t['token']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo h($t['voter_name']); ?></div>
                                        <small class="text-muted">NIM: <?php echo h($t['nim']); ?></small>
                                    </td>
                                    <?php if ($role === 'superadmin'): ?>
                                        <td><?php echo h($t['faculty_name']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo date('H:i:s', strtotime($t['issued_at'])); ?></td>
                                    <td class="text-end">
                                        <span class="badge <?php echo $isSoon ? 'bg-label-warning' : 'bg-label-success'; ?>">
                                            <?php echo $remainMin; ?> mnt
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="token_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    title="Cabut token"
                                                    onclick="return confirm('Cabut token <?php echo h($t['token']); ?>?')">
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
