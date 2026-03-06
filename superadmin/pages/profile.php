<?php
// pages/profile.php
// URL: index.php?p=profile&user=superadmin | admin
// Catatan: jangan panggil session_start() di sini, biarkan di index.php kalau memang dipakai global.

$user = strtolower(trim($_GET['user'] ?? 'admin'));
if (!in_array($user, ['superadmin', 'admin'], true)) {
    $user = 'admin';
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== Dummy "current user" (nanti ganti dari session/DB) =====
$me = [
  'role' => $user,
  'name' => $user === 'superadmin' ? 'Superadmin Utama' : 'Admin Fakultas Teknik',
  'email' => $user === 'superadmin' ? 'superadmin@pemira.test' : 'admin-ft@pemira.test',
  'phone' => $user === 'superadmin' ? '0812-0000-0000' : '0813-1111-1111',
  'faculty' => $user === 'admin' ? 'Fakultas Teknik' : null,
  'last_login' => date('d M Y, H:i') . ' WIB',
  // contoh path foto (dummy). kalau kosong, pakai icon
  'photo' => '', // contoh: 'uploads/users/1.jpg'
];

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'save_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '' || $email === '') {
            $flashType = 'danger';
            $flash = 'Nama dan email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flashType = 'danger';
            $flash = 'Format email tidak valid.';
        } else {
            // demo update local
            $me['name'] = $name;
            $me['email'] = $email;
            $me['phone'] = $phone;

            $flashType = 'success';
            $flash = 'Profil tersimpan (demo). Nanti ganti ke update DB + audit log.';
        }
    }

    if ($action === 'change_password') {
        $cur = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $rep = (string)($_POST['repeat_password'] ?? '');

        if (strlen($new) < 8) {
            $flashType = 'danger';
            $flash = 'Password baru minimal 8 karakter.';
        } elseif ($new !== $rep) {
            $flashType = 'danger';
            $flash = 'Konfirmasi password tidak sama.';
        } else {
            // demo only, tidak benar-benar mengecek current password
            $flashType = 'success';
            $flash = 'Password berhasil diganti (demo). Nanti implementasi: verifikasi password lama + hash bcrypt.';
        }
    }

    if ($action === 'upload_photo') {
        // Demo: kita tidak benar-benar simpan file.
        // Nanti implementasi: move_uploaded_file -> uploads/users/{id}.jpg dan simpan path di DB
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $flashType = 'danger';
            $flash = 'Upload gagal (demo). Pastikan memilih file gambar.';
        } else {
            $flashType = 'success';
            $flash = 'Foto profil terupload (demo). Nanti simpan file + update DB.';
        }
    }
}

$roleLabel = $user === 'superadmin' ? 'Superadmin' : 'Admin';
$roleIcon  = $user === 'superadmin' ? 'bx-shield-quarter' : 'bx-user-pin';
$roleBadge = $user === 'superadmin' ? 'bg-label-primary' : 'bg-label-info';

$backTo = $user === 'superadmin'
    ? 'index.php?p=dashboard&user=superadmin'
    : 'index.php?p=dashboard&user=admin';
?>

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-2">
      <span class="avatar-initial rounded bg-label-primary">
        <i class="bx bx-id-card"></i>
      </span>
      <div>
        <h4 class="mb-0">Profile</h4>
        <small class="text-muted">Pengaturan akun <?php echo h($roleLabel); ?></small>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="<?php echo h($backTo); ?>" class="btn btn-outline-secondary">
        <i class="bx bx-left-arrow-alt me-1"></i> Kembali
      </a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?php echo h($flashType); ?> mb-4">
      <?php echo h($flash); ?>
    </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- LEFT: Profile card -->
    <div class="col-12 col-xl-4">
      <div class="card h-100">
        <div class="card-body text-center">

          <?php if (!empty($me['photo'])): ?>
            <img src="<?php echo h($me['photo']); ?>" class="rounded-circle mb-3"
                 style="width:96px;height:96px;object-fit:cover;" alt="Profile Photo">
          <?php else: ?>
            <span class="avatar-initial rounded-circle bg-label-primary mb-3"
                  style="width:96px;height:96px;display:inline-flex;align-items:center;justify-content:center;">
              <i class="bx <?php echo h($roleIcon); ?>" style="font-size:42px;"></i>
            </span>
          <?php endif; ?>

          <h5 class="mb-1"><?php echo h($me['name']); ?></h5>
          <div class="d-flex justify-content-center gap-2 mb-3">
            <span class="badge <?php echo h($roleBadge); ?>"><?php echo h($roleLabel); ?></span>
            <?php if ($user === 'admin' && !empty($me['faculty'])): ?>
              <span class="badge bg-label-secondary"><?php echo h($me['faculty']); ?></span>
            <?php endif; ?>
          </div>

          <div class="text-start mt-4">
            <div class="d-flex justify-content-between">
              <span class="text-muted">Email</span>
              <span class="fw-semibold"><?php echo h($me['email']); ?></span>
            </div>
            <div class="d-flex justify-content-between mt-2">
              <span class="text-muted">Telepon</span>
              <span class="fw-semibold"><?php echo h($me['phone']); ?></span>
            </div>
            <div class="d-flex justify-content-between mt-2">
              <span class="text-muted">Last login</span>
              <span class="fw-semibold"><?php echo h($me['last_login']); ?></span>
            </div>
          </div>

          <hr class="my-4">

          <!-- Upload photo -->
          <form method="post" enctype="multipart/form-data" class="text-start">
            <input type="hidden" name="_action" value="upload_photo">

            <label class="form-label">Foto profil</label>
            <input class="form-control" type="file" name="photo" accept="image/*" id="photoInput">

            <div class="mt-3 d-grid gap-2">
              <button class="btn btn-outline-primary" type="submit">
                <i class="bx bx-upload me-1"></i> Upload (Demo)
              </button>
              <small class="text-muted">
                Nanti: simpan file ke <code>uploads/users/</code>, update path di DB.
              </small>
            </div>
          </form>

          <div class="mt-3 d-none" id="photoPreviewWrap">
            <small class="text-muted d-block mb-2">Preview:</small>
            <img id="photoPreview" class="rounded" style="width:100%;max-height:220px;object-fit:cover;" alt="Preview">
          </div>

        </div>
      </div>
    </div>

    <!-- RIGHT -->
    <div class="col-12 col-xl-8">
      <div class="row g-4">

        <!-- Update profile -->
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0">Informasi Akun</h5>
              <small class="text-muted">Edit data dasar akun</small>
            </div>
            <div class="card-body">
              <form method="post" class="row g-3">
                <input type="hidden" name="_action" value="save_profile">

                <div class="col-md-6">
                  <label class="form-label">Nama</label>
                  <input class="form-control" name="name" value="<?php echo h($me['name']); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input class="form-control" type="email" name="email" value="<?php echo h($me['email']); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Telepon</label>
                  <input class="form-control" name="phone" value="<?php echo h($me['phone']); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label">Role</label>
                  <input class="form-control" value="<?php echo h($roleLabel); ?>" readonly>
                </div>

                <?php if ($user === 'admin'): ?>
                  <div class="col-12">
                    <div class="alert alert-info mb-0">
                      Akun admin terikat ke <b>fakultas/TPU</b>. Perubahan fakultas sebaiknya melalui menu
                      <a href="index.php?p=admins&user=superadmin">Admins</a> (superadmin).
                    </div>
                  </div>
                <?php endif; ?>

                <div class="col-12 d-flex gap-2">
                  <button class="btn btn-primary" type="submit">
                    <i class="bx bx-save me-1"></i> Simpan (Demo)
                  </button>
                  <a class="btn btn-outline-secondary" href="<?php echo h($backTo); ?>">
                    Batal
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Change password -->
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0">Ganti Password</h5>
              <small class="text-muted">Minimal 8 karakter, gunakan kombinasi huruf dan angka</small>
            </div>
            <div class="card-body">
              <form method="post" class="row g-3" autocomplete="off">
                <input type="hidden" name="_action" value="change_password">

                <div class="col-md-4">
                  <label class="form-label">Password lama</label>
                  <div class="input-group">
                    <input class="form-control" type="password" name="current_password" id="curPass">
                    <button class="btn btn-outline-secondary" type="button" data-toggle-pass="#curPass">
                      <i class="bx bx-show"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Password baru</label>
                  <div class="input-group">
                    <input class="form-control" type="password" name="new_password" id="newPass">
                    <button class="btn btn-outline-secondary" type="button" data-toggle-pass="#newPass">
                      <i class="bx bx-show"></i>
                    </button>
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Ulangi password baru</label>
                  <div class="input-group">
                    <input class="form-control" type="password" name="repeat_password" id="repPass">
                    <button class="btn btn-outline-secondary" type="button" data-toggle-pass="#repPass">
                      <i class="bx bx-show"></i>
                    </button>
                  </div>
                </div>

                <div class="col-12 d-flex gap-2">
                  <button class="btn btn-outline-primary" type="submit">
                    <i class="bx bx-lock-open-alt me-1"></i> Update Password (Demo)
                  </button>
                  <small class="text-muted align-self-center">
                    Nanti implementasi: cek password lama, hash <code>password_hash()</code>, simpan ke DB.
                  </small>
                </div>
              </form>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>

<script>
(function(){
  // Toggle password show/hide
  document.querySelectorAll('[data-toggle-pass]').forEach(btn => {
    btn.addEventListener('click', () => {
      const sel = btn.getAttribute('data-toggle-pass');
      const input = document.querySelector(sel);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      const icon = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('bx-show');
        icon.classList.toggle('bx-hide');
      }
    });
  });

  // Photo preview
  const input = document.getElementById('photoInput');
  const wrap  = document.getElementById('photoPreviewWrap');
  const img   = document.getElementById('photoPreview');

  if (input && wrap && img) {
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) {
        wrap.classList.add('d-none');
        return;
      }
      if (!file.type.startsWith('image/')) {
        wrap.classList.add('d-none');
        return;
      }
      const url = URL.createObjectURL(file);
      img.src = url;
      wrap.classList.remove('d-none');
    });
  }
})();
</script>
