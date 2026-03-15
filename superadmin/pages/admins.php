<?php
// pages/admins.php — Superadmin: Kelola Admin

if ($role !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>403 - Akses ditolak</h4></div></div></div>";
    return;
}

$flash     = flash_get();
$csrf      = csrf_token();
$faculties = dbrows('SELECT id, name FROM faculties WHERE is_active = 1 ORDER BY name ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash_set('danger', 'Token keamanan tidak valid.');
        header('Location: index.php?p=admins');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username   = trim($_POST['username'] ?? '');
        $name       = trim($_POST['name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $roleNew    = $_POST['role'] === 'superadmin' ? 'superadmin' : 'admin_faculty';
        $facultyId  = $roleNew === 'admin_faculty' ? (int)($_POST['faculty_id'] ?? 0) : null;
        $password   = $_POST['password'] ?? '';
        $errors     = [];

        if ($username === '')  $errors[] = 'Username wajib diisi.';
        if ($name === '')      $errors[] = 'Nama wajib diisi.';
        if (strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';
        if ($roleNew === 'admin_faculty' && !$facultyId) $errors[] = 'Pilih fakultas untuk admin.';
        if (!$errors && dbval('SELECT COUNT(*) FROM admin_users WHERE username = ?', [$username])) {
            $errors[] = "Username $username sudah dipakai.";
        }

        if ($errors) {
            flash_set('danger', implode('<br>', array_map('h', $errors)));
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            dbq(
                'INSERT INTO admin_users (username, name, email, password_hash, role, faculty_id)
                 VALUES (?,?,?,?,?,?)',
                [$username, $name, $email, $hash, $roleNew, $facultyId]
            );
            audit_log('create_admin', 'superadmin', $admin['id'], null, 'admin_users');
            flash_set('success', "Admin $username berhasil dibuat.");
        }
        header('Location: index.php?p=admins');
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['admin_id'] ?? 0);
        $a  = $id ? dbrow('SELECT * FROM admin_users WHERE id = ?', [$id]) : null;
        if ($a && $a['id'] !== $admin['id']) { // tidak bisa nonaktifkan diri sendiri
            dbq('UPDATE admin_users SET is_active = ? WHERE id = ?', [$a['is_active'] ? 0 : 1, $id]);
            flash_set('success', 'Status admin berhasil diubah.');
        } else {
            flash_set('danger', 'Tidak bisa menonaktifkan akun sendiri.');
        }
        header('Location: index.php?p=admins');
        exit;
    }

    if ($action === 'force_logout') {
        $id = (int)($_POST['admin_id'] ?? 0);
        if ($id && $id !== (int)$admin['id']) {
            dbq('UPDATE admin_users SET session_token = NULL WHERE id = ?', [$id]);
            flash_set('success', 'Admin berhasil di-logout paksa.');
        } else {
            flash_set('danger', 'Tidak bisa logout akun sendiri.');
        }
        header('Location: index.php?p=admins');
        exit;
    }

    if ($action === 'reset_password') {
        $id       = (int)($_POST['admin_id'] ?? 0);
        $newPass  = $_POST['new_password'] ?? '';
        if ($id && strlen($newPass) >= 8) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            dbq('UPDATE admin_users SET password_hash = ? WHERE id = ?', [$hash, $id]);
            flash_set('success', 'Password berhasil direset.');
        } else {
            flash_set('danger', 'Password minimal 8 karakter.');
        }
        header('Location: index.php?p=admins');
        exit;
    }
}

$admins = dbrows(
    'SELECT a.*, f.name AS faculty_name,
            (a.session_token IS NOT NULL) AS is_online
     FROM admin_users a
     LEFT JOIN faculties f ON f.id = a.faculty_id
     ORDER BY a.role ASC, a.name ASC'
);
?>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">Kelola Akun Admin</h4>
            <div class="text-muted">Superadmin · <?php echo count($admins); ?> akun</div>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">
            <i class="bx bx-plus me-1"></i> Tambah Admin
        </button>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible mb-3">
            <?php echo $flash['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Nama</th>
                            <th>Role</th>
                            <th>Fakultas</th>
                            <th>Login Terakhir</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $a): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($a['username']); ?></td>
                                <td><?php echo h($a['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $a['role'] === 'superadmin' ? 'bg-label-danger' : 'bg-label-primary'; ?>">
                                        <?php echo $a['role'] === 'superadmin' ? 'Superadmin' : 'Admin Fakultas'; ?>
                                    </span>
                                </td>
                                <td><?php echo $a['faculty_name'] ? h($a['faculty_name']) : '<span class="text-muted">—</span>'; ?></td>
                                <td>
                                    <?php echo $a['last_login_at'] ? date('d M Y H:i', strtotime($a['last_login_at'])) : '<span class="text-muted">—</span>'; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $a['is_active'] ? 'bg-label-success' : 'bg-label-secondary'; ?>">
                                        <?php echo $a['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <!-- Toggle status -->
                                    <?php if ((int)$a['id'] !== (int)$admin['id']): ?>
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="admin_id" value="<?php echo $a['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $a['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                <i class="bx <?php echo $a['is_active'] ? 'bx-user-minus' : 'bx-user-plus'; ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Force Logout -->
                                    <?php if ((int)$a['id'] !== (int)$admin['id'] && !empty($a['session_token'])): ?>
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="force_logout">
                                            <input type="hidden" name="admin_id" value="<?php echo $a['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Paksa Logout">
                                                <i class="bx bx-log-out"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Reset Password -->
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#modalReset"
                                            data-id="<?php echo $a['id']; ?>"
                                            data-name="<?php echo h($a['name']); ?>">
                                        <i class="bx bx-key"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Admin -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" id="addRole">
                            <option value="admin_faculty">Admin Fakultas</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="addFacultyWrap">
                        <label class="form-label">Fakultas</label>
                        <select class="form-select" name="faculty_id">
                            <option value="">-- Pilih --</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo h($f['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Password (min. 8 karakter)</label>
                        <input type="password" class="form-control" name="password" required minlength="8">
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

<!-- Modal Reset Password -->
<div class="modal fade" id="modalReset" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="admin_id" id="resetAdminId">
                <div class="modal-body">
                    <p class="mb-2">Reset password untuk: <b id="resetAdminName"></b></p>
                    <label class="form-label">Password Baru (min. 8)</label>
                    <input type="password" class="form-control" name="new_password" required minlength="8">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const addRole = document.getElementById('addRole');
    const addFacultyWrap = document.getElementById('addFacultyWrap');
    if (addRole) {
        addRole.addEventListener('change', () => {
            addFacultyWrap.style.display = addRole.value === 'superadmin' ? 'none' : '';
        });
    }

    const modalReset = document.getElementById('modalReset');
    if (modalReset) {
        modalReset.addEventListener('show.bs.modal', (e) => {
            const btn = e.relatedTarget;
            document.getElementById('resetAdminId').value = btn.dataset.id || '';
            document.getElementById('resetAdminName').textContent = btn.dataset.name || '';
        });
    }
})();
</script>
