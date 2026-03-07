<?php
// pages/profile.php — Edit profil & ganti password admin yang sedang login

$flash = flash_get();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash_set('danger', 'Token keamanan tidak valid.');
        header('Location: index.php?p=profile');
        exit;
    }

    $action = $_POST['_action'] ?? '';

    // ---- Simpan informasi akun ----
    if ($action === 'save_profile') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');

        $errors = [];
        if ($name  === '') $errors[] = 'Nama wajib diisi.';
        if ($email === '') $errors[] = 'Email wajib diisi.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';

        if (!$errors && $email !== $admin['email'] ?? '') {
            // Cek duplikat email
            $taken = dbval('SELECT COUNT(*) FROM admin_users WHERE email = ? AND id != ?', [$email, $admin['id']]);
            if ($taken) $errors[] = 'Email sudah dipakai akun lain.';
        }

        if ($errors) {
            flash_set('danger', implode('<br>', array_map('h', $errors)));
        } else {
            dbq('UPDATE admin_users SET name = ?, email = ? WHERE id = ?', [$name, $email, $admin['id']]);
            audit_log('update_profile', $role, $admin['id'], null, 'admin_users', $admin['id']);
            // Sinkron session
            $_SESSION['admin']['name'] = $name;
            flash_set('success', 'Profil berhasil diperbarui.');
        }
        header('Location: index.php?p=profile');
        exit;
    }

    // ---- Ganti password (superadmin only) ----
    if ($action === 'change_password' && $role !== 'superadmin') {
        flash_set('danger', 'Hanya superadmin yang dapat mengganti password.');
        header('Location: index.php?p=profile');
        exit;
    }
    if ($action === 'change_password') {
        $cur = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password']     ?? '');
        $rep = (string)($_POST['repeat_password']  ?? '');

        $errors = [];
        if ($cur === '') $errors[] = 'Password lama wajib diisi.';
        if (strlen($new) < 8) $errors[] = 'Password baru minimal 8 karakter.';
        if ($new !== $rep) $errors[] = 'Konfirmasi password tidak sama.';

        if (!$errors) {
            $row = dbrow('SELECT password_hash FROM admin_users WHERE id = ?', [$admin['id']]);
            if (!$row || !password_verify($cur, $row['password_hash'])) {
                $errors[] = 'Password lama salah.';
            }
        }

        if ($errors) {
            flash_set('danger', implode('<br>', array_map('h', $errors)));
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            dbq('UPDATE admin_users SET password_hash = ? WHERE id = ?', [$hash, $admin['id']]);
            audit_log('change_password', $role, $admin['id'], null, 'admin_users', $admin['id']);
            flash_set('success', 'Password berhasil diubah.');
        }
        header('Location: index.php?p=profile');
        exit;
    }
}

// Ambil data terbaru dari DB
$me = dbrow('SELECT * FROM admin_users WHERE id = ?', [$admin['id']]);
$facultyName = null;
if ($me['faculty_id']) {
    $facultyName = dbval('SELECT name FROM faculties WHERE id = ?', [$me['faculty_id']]);
}

$roleLabel = $me['role'] === 'superadmin' ? 'Superadmin' : 'Admin Fakultas';
$roleBadge = $me['role'] === 'superadmin' ? 'bg-label-primary' : 'bg-label-info';
?>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex align-items-center gap-2 mb-4">
        <span class="avatar-initial rounded bg-label-primary">
            <i class="bx bx-id-card"></i>
        </span>
        <div>
            <h4 class="mb-0">Profil Akun</h4>
            <small class="text-muted">Pengaturan akun yang sedang login</small>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible mb-4">
            <?php echo $flash['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Kartu identitas -->
        <div class="col-12 col-xl-3">
            <div class="card">
                <div class="card-body text-center py-4">
                    <span class="avatar-initial rounded-circle bg-label-primary mb-3"
                          style="width:80px;height:80px;display:inline-flex;align-items:center;justify-content:center;">
                        <i class="bx bx-user" style="font-size:36px;"></i>
                    </span>
                    <h5 class="mb-1"><?php echo h($me['name']); ?></h5>
                    <div class="mb-2">
                        <span class="badge <?php echo $roleBadge; ?>"><?php echo h($roleLabel); ?></span>
                        <?php if ($facultyName): ?>
                            <span class="badge bg-label-secondary ms-1"><?php echo h($facultyName); ?></span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted d-block"><?php echo h($me['email'] ?? '—'); ?></small>
                    <?php if ($me['last_login_at']): ?>
                        <small class="text-muted d-block mt-1">
                            Login terakhir: <?php echo date('d M Y H:i', strtotime($me['last_login_at'])); ?> WIB
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="col-12 col-xl-9">
            <div class="row g-4">

                <!-- Edit nama & email -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Informasi Akun</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="_action" value="save_profile">

                                <div class="col-md-6">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input class="form-control" name="name"
                                           value="<?php echo h($me['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input class="form-control" type="email" name="email"
                                           value="<?php echo h($me['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Username</label>
                                    <input class="form-control" value="<?php echo h($me['username']); ?>" readonly>
                                    <small class="text-muted">Username tidak dapat diubah di sini.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Role</label>
                                    <input class="form-control" value="<?php echo h($roleLabel); ?>" readonly>
                                </div>

                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="bx bx-save me-1"></i> Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Ganti password — superadmin only -->
                <?php if ($role === 'superadmin'): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Ganti Password</h5>
                            <small class="text-muted">Minimal 8 karakter</small>
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3" autocomplete="off">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="_action" value="change_password">

                                <div class="col-md-4">
                                    <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input class="form-control" type="password" name="current_password" id="curPass" required>
                                        <button class="btn btn-outline-secondary" type="button" data-toggle-pass="#curPass">
                                            <i class="bx bx-show"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input class="form-control" type="password" name="new_password" id="newPass"
                                               minlength="8" required>
                                        <button class="btn btn-outline-secondary" type="button" data-toggle-pass="#newPass">
                                            <i class="bx bx-show"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ulangi Password Baru <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input class="form-control" type="password" name="repeat_password" id="repPass"
                                               minlength="8" required>
                                        <button class="btn btn-outline-secondary" type="button" data-toggle-pass="#repPass">
                                            <i class="bx bx-show"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <button class="btn btn-outline-primary" type="submit">
                                        <i class="bx bx-lock-open-alt me-1"></i> Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script>
(function () {
    document.querySelectorAll('[data-toggle-pass]').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.querySelector(btn.getAttribute('data-toggle-pass'));
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) { icon.classList.toggle('bx-show'); icon.classList.toggle('bx-hide'); }
        });
    });
})();
</script>
