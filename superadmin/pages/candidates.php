<?php
// pages/candidates.php — Superadmin: Kelola Calon

if ($role !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>403 - Akses ditolak</h4></div></div></div>";
    return;
}

$flash     = flash_get();
$faculties = dbrows('SELECT id, name FROM faculties WHERE is_active = 1 ORDER BY name ASC');
$elections = dbrows('SELECT id, name FROM election_periods ORDER BY id DESC');
$active    = active_election();

// ============================================================
// Helper: upload foto calon
// ============================================================
function upload_candidate_photo(array $fileInput, ?string $oldPath = null): array {
    // ['ok' => bool, 'path' => string|null, 'error' => string|null]
    if ($fileInput['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'error' => null]; // tidak ada file baru
    }
    if ($fileInput['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'error' => 'Upload gagal (error code: ' . $fileInput['error'] . ').'];
    }

    $allowed   = ['image/jpeg', 'image/png', 'image/webp'];
    $ext_map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $maxSize   = 2 * 1024 * 1024; // 2 MB

    // Validasi ukuran
    if ($fileInput['size'] > $maxSize) {
        return ['ok' => false, 'path' => null, 'error' => 'Ukuran foto maksimal 2 MB.'];
    }

    // Validasi MIME (baca bytes awal, bukan percaya $_FILES['type'])
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($fileInput['tmp_name']);

    if (!in_array($mime, $allowed, true)) {
        return ['ok' => false, 'path' => null, 'error' => 'Format foto harus JPG, PNG, atau WebP.'];
    }

    // Buat direktori jika belum ada
    $uploadDir = __DIR__ . '/../../uploads/candidates/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Nama file unik
    $ext      = $ext_map[$mime];
    $filename = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($fileInput['tmp_name'], $dest)) {
        return ['ok' => false, 'path' => null, 'error' => 'Gagal menyimpan file. Periksa permission folder uploads/.'];
    }

    // Hapus foto lama jika ada
    if ($oldPath) {
        $oldFull = __DIR__ . '/../../' . ltrim($oldPath, '/');
        if (is_file($oldFull)) {
            @unlink($oldFull);
        }
    }

    return ['ok' => true, 'path' => 'uploads/candidates/' . $filename, 'error' => null];
}

// ============================================================
// POST handler
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash_set('danger', 'Token keamanan tidak valid.');
        header('Location: index.php?p=candidates');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $electionId = (int)($_POST['election_id'] ?? 0);
        $type       = in_array($_POST['type'] ?? '', ['presma', 'dpm']) ? $_POST['type'] : 'presma';
        $facultyId  = $type === 'dpm' ? ((int)($_POST['faculty_id'] ?? 0) ?: null) : null;
        $no         = (int)($_POST['no'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $vision     = trim($_POST['vision'] ?? '');
        $mission    = trim($_POST['mission'] ?? '');
        $isActive   = isset($_POST['is_active']) ? 1 : 0;
        $errors     = [];

        if (!$electionId) $errors[] = 'Pilih periode pemilihan.';
        if ($no <= 0)     $errors[] = 'Nomor urut harus > 0.';
        if ($name === '') $errors[] = 'Nama calon wajib diisi.';
        if ($type === 'dpm' && !$facultyId) $errors[] = 'Pilih fakultas untuk calon DPM.';

        // Cek duplikat nomor
        $editId = $action === 'update' ? (int)($_POST['id'] ?? 0) : 0;
        if (!$errors) {
            $dupSql    = 'SELECT COUNT(*) FROM candidates WHERE election_id=? AND no=? AND type=? AND (faculty_id<=>?)';
            $dupParams = [$electionId, $no, $type, $facultyId];
            if ($editId) { $dupSql .= ' AND id != ?'; $dupParams[] = $editId; }
            if (dbval($dupSql, $dupParams)) {
                $errors[] = 'Nomor urut sudah dipakai untuk tipe/fakultas yang sama.';
            }
        }

        // Upload foto
        $photoPath    = null;   // path baru (atau tetap lama)
        $keepOldPhoto = true;   // default: pertahankan foto lama
        $fileInput    = $_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE];

        if ($fileInput['error'] !== UPLOAD_ERR_NO_FILE) {
            // Ada file baru dikirim
            $oldPath = $editId ? (dbval('SELECT photo FROM candidates WHERE id=?', [$editId]) ?: null) : null;
            $upload  = upload_candidate_photo($fileInput, $oldPath);
            if (!$upload['ok']) {
                $errors[] = $upload['error'];
            } else {
                $photoPath    = $upload['path'];
                $keepOldPhoto = false;
            }
        }

        // Hapus foto jika checkbox remove_photo dicentang
        $removePhoto = isset($_POST['remove_photo']) && $editId;
        if ($removePhoto && !$errors) {
            $oldPath = dbval('SELECT photo FROM candidates WHERE id=?', [$editId]) ?: null;
            if ($oldPath) {
                $full = __DIR__ . '/../../' . ltrim($oldPath, '/');
                if (is_file($full)) @unlink($full);
            }
            $photoPath    = null;
            $keepOldPhoto = false;
        }

        if ($errors) {
            flash_set('danger', implode('<br>', array_map('h', $errors)));
        } elseif ($action === 'create') {
            dbq(
                'INSERT INTO candidates (election_id, no, name, vision, mission, type, faculty_id, photo, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$electionId, $no, $name, $vision ?: null, $mission ?: null, $type, $facultyId, $photoPath, $isActive]
            );
            audit_log('create_candidate', 'superadmin', $admin['id'], null, 'candidates');
            flash_set('success', "Calon \"$name\" berhasil ditambahkan.");
        } else {
            if ($editId) {
                // Jika foto tetap lama, jangan timpa kolom photo
                if ($keepOldPhoto) {
                    dbq(
                        'UPDATE candidates SET election_id=?, no=?, name=?, vision=?, mission=?, type=?, faculty_id=?, is_active=? WHERE id=?',
                        [$electionId, $no, $name, $vision ?: null, $mission ?: null, $type, $facultyId, $isActive, $editId]
                    );
                } else {
                    dbq(
                        'UPDATE candidates SET election_id=?, no=?, name=?, vision=?, mission=?, type=?, faculty_id=?, photo=?, is_active=? WHERE id=?',
                        [$electionId, $no, $name, $vision ?: null, $mission ?: null, $type, $facultyId, $photoPath, $isActive, $editId]
                    );
                }
                audit_log('update_candidate', 'superadmin', $admin['id'], null, 'candidates', $editId);
                flash_set('success', "Calon \"$name\" berhasil diupdate.");
            }
        }
        header('Location: index.php?p=candidates');
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $c  = $id ? dbrow('SELECT * FROM candidates WHERE id = ?', [$id]) : null;
        if ($c) {
            dbq('UPDATE candidates SET is_active = ? WHERE id = ?', [$c['is_active'] ? 0 : 1, $id]);
            flash_set('success', 'Status calon berhasil diubah.');
        }
        header('Location: index.php?p=candidates');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $hasVotes = $id ? dbval('SELECT COUNT(*) FROM votes WHERE candidate_id = ?', [$id]) : 0;
        if ($hasVotes) {
            flash_set('danger', 'Calon tidak dapat dihapus karena sudah ada suara masuk.');
        } elseif ($id) {
            // Hapus file foto jika ada
            $oldPhoto = dbval('SELECT photo FROM candidates WHERE id = ?', [$id]);
            if ($oldPhoto) {
                $full = __DIR__ . '/../../' . ltrim($oldPhoto, '/');
                if (is_file($full)) @unlink($full);
            }
            dbq('DELETE FROM candidates WHERE id = ?', [$id]);
            audit_log('delete_candidate', 'superadmin', $admin['id'], null, 'candidates', $id);
            flash_set('success', 'Calon berhasil dihapus.');
        }
        header('Location: index.php?p=candidates');
        exit;
    }
}

// List dengan filter
$filterElection = (int)($_GET['election_id'] ?? ($active['id'] ?? 0));
$filterType     = trim($_GET['type'] ?? '');
$filterFaculty  = (int)($_GET['faculty_id'] ?? 0);

$where  = [];
$params = [];
if ($filterElection) { $where[] = 'c.election_id = ?'; $params[] = $filterElection; }
if ($filterType)     { $where[] = 'c.type = ?';        $params[] = $filterType; }
if ($filterFaculty)  { $where[] = 'c.faculty_id = ?';  $params[] = $filterFaculty; }

$rows = dbrows(
    'SELECT c.*, f.name AS faculty_name, e.name AS election_name,
            (SELECT COUNT(*) FROM votes WHERE candidate_id = c.id) AS vote_count
     FROM candidates c
     LEFT JOIN faculties f ON f.id = c.faculty_id
     JOIN election_periods e ON e.id = c.election_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY c.election_id DESC, c.type ASC, c.no ASC',
    $params
);

// Edit prefill
$editId  = (int)($_GET['edit'] ?? 0);
$editRow = $editId ? dbrow('SELECT * FROM candidates WHERE id = ?', [$editId]) : null;
?>

<style>
.cand-photo {
    width: 40px; height: 40px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid rgba(67,89,113,.2);
}
.cand-photo-placeholder {
    width: 40px; height: 40px;
    border-radius: 8px;
    background: rgba(67,89,113,.08);
    display: inline-flex; align-items: center; justify-content: center;
    color: #a0aec0;
}
.photo-preview-wrap { position: relative; display: inline-block; }
.photo-preview-wrap img { border-radius: 8px; object-fit: cover; max-height: 160px; }
</style>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">Kelola Calon</h4>
            <div class="text-muted">Superadmin · <?php echo count($rows); ?> calon</div>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="bx bx-plus me-1"></i> Tambah Calon
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible mb-3">
            <?php echo $flash['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($editRow): ?>
        <!-- Edit Form -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Calon</h5>
                <a href="index.php?p=candidates" class="btn btn-outline-secondary btn-sm">Batal</a>
            </div>
            <form method="post" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo $editRow['id']; ?>">
                <?php
                $fElectionId = $editRow['election_id'];
                $fType       = $editRow['type'];
                $fFacultyId  = (int)$editRow['faculty_id'];
                $fNo         = (int)$editRow['no'];
                $fName       = $editRow['name'];
                $fVision     = $editRow['vision'];
                $fMission    = $editRow['mission'];
                $fIsActive   = (int)$editRow['is_active'];
                $fPhoto      = $editRow['photo'];
                ?>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Periode Pemilihan</label>
                            <select class="form-select" name="election_id" required>
                                <?php foreach ($elections as $e): ?>
                                    <option value="<?php echo $e['id']; ?>" <?php echo $fElectionId == $e['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($e['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tipe</label>
                            <select class="form-select edit-type-select" name="type" required>
                                <option value="presma" <?php echo $fType === 'presma' ? 'selected' : ''; ?>>Presma</option>
                                <option value="dpm"    <?php echo $fType === 'dpm'    ? 'selected' : ''; ?>>DPM</option>
                            </select>
                        </div>
                        <div class="col-md-3 edit-faculty-wrap" <?php echo $fType === 'presma' ? 'style="display:none"' : ''; ?>>
                            <label class="form-label">Fakultas (DPM)</label>
                            <select class="form-select" name="faculty_id">
                                <option value="">-- Pilih --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?php echo $f['id']; ?>" <?php echo $fFacultyId == $f['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($f['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nomor Urut</label>
                            <input type="number" class="form-control" name="no" value="<?php echo $fNo; ?>" min="1" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Calon / Paslon</label>
                            <input type="text" class="form-control" name="name" value="<?php echo h($fName); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Visi</label>
                            <textarea class="form-control" name="vision" rows="3"><?php echo h($fVision ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Misi</label>
                            <textarea class="form-control" name="mission" rows="3"><?php echo h($fMission ?? ''); ?></textarea>
                        </div>

                        <!-- Foto -->
                        <div class="col-12">
                            <label class="form-label">Foto Calon</label>
                            <div class="d-flex align-items-start gap-3 flex-wrap">
                                <?php if ($fPhoto): ?>
                                    <div class="photo-preview-wrap" id="editPhotoPreviewWrap">
                                        <img src="<?php echo h('../' . $fPhoto); ?>"
                                             alt="Foto <?php echo h($fName); ?>"
                                             style="width:120px;height:120px;border-radius:10px;object-fit:cover;border:1px solid rgba(67,89,113,.2);"
                                             id="editPhotoPreview">
                                    </div>
                                <?php else: ?>
                                    <div id="editPhotoPreviewWrap" style="display:none;">
                                        <img src="" alt="" id="editPhotoPreview"
                                             style="width:120px;height:120px;border-radius:10px;object-fit:cover;border:1px solid rgba(67,89,113,.2);">
                                    </div>
                                <?php endif; ?>

                                <div class="flex-grow-1">
                                    <input type="file" class="form-control mb-2" name="photo"
                                           accept="image/jpeg,image/png,image/webp"
                                           id="editPhotoInput">
                                    <small class="text-muted d-block mb-2">JPG / PNG / WebP · Maks 2 MB. Kosongkan jika tidak ingin ganti foto.</small>
                                    <?php if ($fPhoto): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remove_photo" id="removePhoto">
                                            <label class="form-check-label text-danger" for="removePhoto">
                                                Hapus foto yang ada
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-flex align-items-center justify-content-between">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editActive"
                                       <?php echo $fIsActive ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="editActive">Aktif</label>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="index.php?p=candidates" class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save me-1"></i> Simpan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
                <input type="hidden" name="p" value="candidates">
                <select class="form-select" name="election_id" style="max-width:220px;">
                    <option value="">Semua Periode</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo $filterElection == $e['id'] ? 'selected' : ''; ?>>
                            <?php echo h($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select" name="type" style="max-width:160px;">
                    <option value="">Semua Tipe</option>
                    <option value="presma" <?php echo $filterType === 'presma' ? 'selected' : ''; ?>>Presma</option>
                    <option value="dpm"    <?php echo $filterType === 'dpm'    ? 'selected' : ''; ?>>DPM</option>
                </select>
                <select class="form-select" name="faculty_id" style="max-width:200px;">
                    <option value="">Semua Fakultas</option>
                    <?php foreach ($faculties as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo $filterFaculty == $f['id'] ? 'selected' : ''; ?>>
                            <?php echo h($f['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary" type="submit"><i class="bx bx-filter-alt me-1"></i> Filter</button>
                <a class="btn btn-outline-secondary" href="index.php?p=candidates">Reset</a>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <div class="text-center text-muted py-5">Belum ada calon. Tambah yang pertama!</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Foto</th>
                                <th>Calon</th>
                                <th>Tipe</th>
                                <th>Periode</th>
                                <th>Fakultas</th>
                                <th class="text-end">Suara</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $c): ?>
                                <tr>
                                    <td><span class="badge bg-label-primary">#<?php echo (int)$c['no']; ?></span></td>
                                    <td>
                                        <?php if ($c['photo']): ?>
                                            <img src="<?php echo h('../' . $c['photo']); ?>"
                                                 alt="<?php echo h($c['name']); ?>"
                                                 class="cand-photo"
                                                 data-bs-toggle="modal" data-bs-target="#modalPhotoView"
                                                 data-photo="<?php echo h('../' . $c['photo']); ?>"
                                                 data-name="<?php echo h($c['name']); ?>"
                                                 style="cursor:pointer;"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                            <span class="cand-photo-placeholder" style="display:none;">
                                                <i class="bx bx-user"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="cand-photo-placeholder">
                                                <i class="bx bx-user"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo h($c['name']); ?></div>
                                        <?php if ($c['vision']): ?>
                                            <small class="text-muted"><?php echo h(mb_strimwidth($c['vision'], 0, 55, '...')); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $c['type'] === 'presma' ? 'bg-label-danger' : 'bg-label-info'; ?>">
                                            <?php echo strtoupper($c['type']); ?>
                                        </span>
                                    </td>
                                    <td><small><?php echo h($c['election_name']); ?></small></td>
                                    <td><?php echo $c['faculty_name'] ? h($c['faculty_name']) : '<span class="text-muted">—</span>'; ?></td>
                                    <td class="text-end fw-semibold"><?php echo number_format((int)$c['vote_count']); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $c['is_active'] ? 'bg-label-success' : 'bg-label-secondary'; ?>">
                                            <?php echo $c['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="index.php?p=candidates&edit=<?php echo $c['id']; ?>"
                                           class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bx bx-edit-alt"></i>
                                        </a>
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?php echo $c['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                                    title="<?php echo $c['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                                <i class="bx <?php echo $c['is_active'] ? 'bx-pause' : 'bx-play'; ?>"></i>
                                            </button>
                                        </form>
                                        <?php if (!(int)$c['vote_count']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" data-bs-target="#modalDelete"
                                                    data-id="<?php echo $c['id']; ?>"
                                                    data-name="<?php echo h($c['name']); ?>"
                                                    title="Hapus">
                                                <i class="bx bx-trash"></i>
                                            </button>
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

<!-- Modal Tambah Calon -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Calon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Periode Pemilihan</label>
                        <select class="form-select" name="election_id" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($elections as $e): ?>
                                <option value="<?php echo $e['id']; ?>" <?php echo ($active && $active['id'] == $e['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($e['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipe</label>
                        <select class="form-select" name="type" id="addTypeSelect" required>
                            <option value="presma">Presma</option>
                            <option value="dpm">DPM</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="addFacultyWrap" style="display:none">
                        <label class="form-label">Fakultas (DPM)</label>
                        <select class="form-select" name="faculty_id">
                            <option value="">-- Pilih --</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo h($f['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">No Urut</label>
                        <input type="number" class="form-control" name="no" min="1" required>
                    </div>
                    <div class="col-10">
                        <label class="form-label">Nama Calon / Paslon</label>
                        <input type="text" class="form-control" name="name" required placeholder="Contoh: Andi Pratama & Sari Dewi">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Visi</label>
                        <textarea class="form-control" name="vision" rows="3" placeholder="Visi..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Misi</label>
                        <textarea class="form-control" name="mission" rows="3" placeholder="Misi..."></textarea>
                    </div>

                    <!-- Foto -->
                    <div class="col-12">
                        <label class="form-label">Foto Calon <small class="text-muted">(opsional)</small></label>
                        <div class="d-flex align-items-start gap-3">
                            <div id="addPhotoPreviewWrap" style="display:none;">
                                <img src="" alt="" id="addPhotoPreview"
                                     style="width:100px;height:100px;border-radius:10px;object-fit:cover;border:1px solid rgba(67,89,113,.2);">
                            </div>
                            <div class="flex-grow-1">
                                <input type="file" class="form-control" name="photo"
                                       accept="image/jpeg,image/png,image/webp"
                                       id="addPhotoInput">
                                <small class="text-muted">JPG / PNG / WebP · Maks 2 MB</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="addIsActive" checked>
                            <label class="form-check-label" for="addIsActive">Aktif (tampil di halaman pemilih)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete -->
<div class="modal fade" id="modalDelete" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Calon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delCandId">
                <div class="modal-body">
                    <p>Yakin hapus <b id="delCandName"></b>?</p>
                    <small class="text-muted">Foto calon juga akan ikut terhapus.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="bx bx-trash me-1"></i> Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal View Foto -->
<div class="modal fade" id="modalPhotoView" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="photoViewName">Foto Calon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="photoViewImg" src="" alt="Foto calon"
                     class="img-fluid rounded" style="max-height:480px;">
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // ---- Preview foto sebelum upload ----
    function bindPreview(inputId, previewId, wrapId) {
        const input   = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const wrap    = document.getElementById(wrapId);
        if (!input || !preview) return;

        input.addEventListener('change', () => {
            const file = input.files[0];
            if (file && file.type.startsWith('image/')) {
                const url = URL.createObjectURL(file);
                preview.src = url;
                if (wrap) wrap.style.display = '';
            } else {
                if (wrap) wrap.style.display = 'none';
                preview.src = '';
            }
        });
    }
    bindPreview('addPhotoInput',  'addPhotoPreview',  'addPhotoPreviewWrap');
    bindPreview('editPhotoInput', 'editPhotoPreview', 'editPhotoPreviewWrap');

    // ---- Checkbox "Hapus foto": disable input file ----
    const removePhotoChk = document.getElementById('removePhoto');
    const editPhotoInput = document.getElementById('editPhotoInput');
    if (removePhotoChk && editPhotoInput) {
        removePhotoChk.addEventListener('change', () => {
            editPhotoInput.disabled = removePhotoChk.checked;
            if (removePhotoChk.checked) {
                const wrap = document.getElementById('editPhotoPreviewWrap');
                if (wrap) wrap.style.opacity = '0.3';
            } else {
                const wrap = document.getElementById('editPhotoPreviewWrap');
                if (wrap) wrap.style.opacity = '1';
            }
        });
    }

    // ---- Add modal: toggle faculty selector ----
    const addTypeSelect  = document.getElementById('addTypeSelect');
    const addFacultyWrap = document.getElementById('addFacultyWrap');
    if (addTypeSelect) {
        addTypeSelect.addEventListener('change', () => {
            addFacultyWrap.style.display = addTypeSelect.value === 'dpm' ? '' : 'none';
        });
    }

    // ---- Edit form: toggle faculty selector ----
    const editTypeSel = document.querySelector('.edit-type-select');
    const editFacWrap = document.querySelector('.edit-faculty-wrap');
    if (editTypeSel && editFacWrap) {
        editTypeSel.addEventListener('change', () => {
            editFacWrap.style.display = editTypeSel.value === 'dpm' ? '' : 'none';
        });
    }

    // ---- Delete modal ----
    const delModal = document.getElementById('modalDelete');
    if (delModal) {
        delModal.addEventListener('show.bs.modal', (e) => {
            document.getElementById('delCandId').value       = e.relatedTarget?.dataset.id   || '';
            document.getElementById('delCandName').textContent = e.relatedTarget?.dataset.name || 'calon ini';
        });
    }

    // ---- View foto modal ----
    const photoModal = document.getElementById('modalPhotoView');
    if (photoModal) {
        photoModal.addEventListener('show.bs.modal', (e) => {
            document.getElementById('photoViewImg').src          = e.relatedTarget?.dataset.photo || '';
            document.getElementById('photoViewName').textContent = e.relatedTarget?.dataset.name  || 'Foto Calon';
        });
    }
})();
</script>
