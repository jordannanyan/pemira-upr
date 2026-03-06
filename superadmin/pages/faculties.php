<?php
// pages/faculties.php — Superadmin: Kelola Fakultas

if ($role !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>403 - Akses ditolak</h4><p>Superadmin only.</p></div></div></div>";
    return;
}

$flash = flash_get();
$csrf  = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash_set('danger', 'Token keamanan tidak valid.');
        header('Location: index.php?p=faculties');
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($name === '' || $code === '') {
            flash_set('danger', 'Nama dan kode wajib diisi.');
        } elseif (dbval('SELECT COUNT(*) FROM faculties WHERE code = ?', [$code])) {
            flash_set('danger', "Kode $code sudah dipakai.");
        } else {
            dbq('INSERT INTO faculties (name, code) VALUES (?,?)', [$name, $code]);
            flash_set('success', "Fakultas $name berhasil ditambahkan.");
        }
        header('Location: index.php?p=faculties');
        exit;
    }

    if ($action === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($id && $name !== '' && $code !== '') {
            dbq('UPDATE faculties SET name = ?, code = ? WHERE id = ?', [$name, $code, $id]);
            flash_set('success', 'Fakultas berhasil diupdate.');
        } else {
            flash_set('danger', 'Data tidak lengkap.');
        }
        header('Location: index.php?p=faculties');
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $f  = $id ? dbrow('SELECT * FROM faculties WHERE id = ?', [$id]) : null;
        if ($f) {
            dbq('UPDATE faculties SET is_active = ? WHERE id = ?', [$f['is_active'] ? 0 : 1, $id]);
            flash_set('success', 'Status fakultas berhasil diubah.');
        }
        header('Location: index.php?p=faculties');
        exit;
    }
}

$rows = dbrows(
    'SELECT f.*, COUNT(v.id) AS total_voters
     FROM faculties f
     LEFT JOIN voters v ON v.faculty_id = f.id AND v.is_active = 1
     GROUP BY f.id ORDER BY f.name ASC'
);

$editId  = (int)($_GET['edit'] ?? 0);
$editRow = $editId ? dbrow('SELECT * FROM faculties WHERE id = ?', [$editId]) : null;
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">Kelola Fakultas / TPS</h4>
            <div class="text-muted">Superadmin · <?php echo count($rows); ?> fakultas</div>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="bx bx-plus me-1"></i> Tambah Fakultas
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible mb-3">
            <?php echo $flash['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($editRow): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Fakultas</h5>
                <a href="index.php?p=faculties" class="btn btn-outline-secondary btn-sm">Batal</a>
            </div>
            <div class="card-body">
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $editRow['id']; ?>">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Nama Fakultas</label>
                            <input type="text" class="form-control" name="name"
                                   value="<?php echo h($editRow['name']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Kode</label>
                            <input type="text" class="form-control" name="code"
                                   value="<?php echo h($editRow['code']); ?>" required maxlength="20">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Fakultas</th>
                            <th class="text-end">Pemilih</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $f): ?>
                            <tr>
                                <td><span class="badge bg-label-primary"><?php echo h($f['code']); ?></span></td>
                                <td class="fw-semibold"><?php echo h($f['name']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$f['total_voters']); ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo $f['is_active'] ? 'bg-label-success' : 'bg-label-secondary'; ?>">
                                        <?php echo $f['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="index.php?p=faculties&edit=<?php echo $f['id']; ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bx bx-edit-alt"></i>
                                    </a>
                                    <form method="post" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $f['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                            <i class="bx <?php echo $f['is_active'] ? 'bx-pause' : 'bx-play'; ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Fakultas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Nama Fakultas</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Kode</label>
                        <input type="text" class="form-control" name="code" required maxlength="20"
                               placeholder="Contoh: FT, FKIP, FEB">
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
