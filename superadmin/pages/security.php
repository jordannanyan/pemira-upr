<?php
// pages/security.php
// Superadmin – Security Settings
// URL: index.php?p=security&user=superadmin

$user = strtolower(trim($_GET['user'] ?? 'admin'));
if ($user !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'>
            <div class='card'><div class='card-body'>
              <h4 class='mb-2'>403 - Akses ditolak</h4>
              <p class='mb-0'>Halaman ini hanya untuk <b>superadmin</b>.</p>
            </div></div>
          </div>";
    return;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== Dummy security config (ganti DB nanti) =====
$sec = [
  // Login protection
  'login_rate_limit_enabled' => 1,
  'login_rate_limit_per_min' => 15,   // max request/minute per IP (demo)
  'login_lockout_enabled'    => 1,
  'login_max_attempts'       => 5,    // attempts before lockout
  'login_lockout_minutes'    => 10,

  // Session
  'session_idle_minutes'     => 30,
  'session_force_logout_on_role_change' => 1,

  // Token policy
  'token_length'             => 6,
  'token_chars'              => 'NUMERIC', // NUMERIC|ALNUM
  'token_one_time'           => 1,
  'token_ttl_minutes'        => 10,

  // Voting guard
  'prevent_multi_device'     => 0, // placeholder: nanti pakai fingerprint/session bound
  'enable_csrf'              => 1,

  // Photo upload guard
  'photo_max_size_mb'        => 2,
  'photo_allowed_mime'       => 'image/jpeg,image/png',
  'photo_store_days'         => 30,

  // IP allowlist (optional)
  'ip_allowlist_enabled'     => 0,
  'ip_allowlist'             => "127.0.0.1\n192.168.1.0/24",
];

// ===== Dummy audit ringkas =====
$audit = [
  ['time' => '2026-01-10 08:02', 'actor' => 'superadmin', 'action' => 'Update security settings', 'ip' => '127.0.0.1'],
  ['time' => '2026-01-10 08:05', 'actor' => 'admin_ft',    'action' => 'Issue token to NIM 2101xxxx', 'ip' => '192.168.1.10'],
  ['time' => '2026-01-10 08:06', 'actor' => 'admin_fh',    'action' => 'Issue token to NIM 2102xxxx', 'ip' => '192.168.1.11'],
  ['time' => '2026-01-10 08:08', 'actor' => 'voter',       'action' => 'Login attempt failed (token)', 'ip' => '192.168.1.22'],
];

$flash = null;
$flashType = 'success';
$rotateMsg = null;

// ===== POST handler (demo only) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? 'save';

    if ($action === 'rotate_secret') {
        // dummy rotate
        $rotateMsg = 'Secret key berhasil di-rotate (demo). Nanti implementasi: simpan secret baru + invalidasi token/sesi tertentu.';
        $flashType = 'warning';
        $flash = $rotateMsg;
    } else {
        // Save settings
        $sec['login_rate_limit_enabled'] = isset($_POST['login_rate_limit_enabled']) ? 1 : 0;
        $sec['login_rate_limit_per_min'] = max(1, (int)($_POST['login_rate_limit_per_min'] ?? $sec['login_rate_limit_per_min']));

        $sec['login_lockout_enabled'] = isset($_POST['login_lockout_enabled']) ? 1 : 0;
        $sec['login_max_attempts'] = max(1, (int)($_POST['login_max_attempts'] ?? $sec['login_max_attempts']));
        $sec['login_lockout_minutes'] = max(1, (int)($_POST['login_lockout_minutes'] ?? $sec['login_lockout_minutes']));

        $sec['session_idle_minutes'] = max(5, (int)($_POST['session_idle_minutes'] ?? $sec['session_idle_minutes']));
        $sec['session_force_logout_on_role_change'] = isset($_POST['session_force_logout_on_role_change']) ? 1 : 0;

        $sec['token_length'] = max(4, min(12, (int)($_POST['token_length'] ?? $sec['token_length'])));
        $sec['token_chars'] = in_array($_POST['token_chars'] ?? '', ['NUMERIC','ALNUM'], true) ? $_POST['token_chars'] : $sec['token_chars'];
        $sec['token_one_time'] = isset($_POST['token_one_time']) ? 1 : 0;
        $sec['token_ttl_minutes'] = max(1, (int)($_POST['token_ttl_minutes'] ?? $sec['token_ttl_minutes']));

        $sec['prevent_multi_device'] = isset($_POST['prevent_multi_device']) ? 1 : 0;
        $sec['enable_csrf'] = isset($_POST['enable_csrf']) ? 1 : 0;

        $sec['photo_max_size_mb'] = max(1, min(10, (int)($_POST['photo_max_size_mb'] ?? $sec['photo_max_size_mb'])));
        $sec['photo_allowed_mime'] = trim($_POST['photo_allowed_mime'] ?? $sec['photo_allowed_mime']);
        $sec['photo_store_days'] = max(1, (int)($_POST['photo_store_days'] ?? $sec['photo_store_days']));

        $sec['ip_allowlist_enabled'] = isset($_POST['ip_allowlist_enabled']) ? 1 : 0;
        $sec['ip_allowlist'] = trim($_POST['ip_allowlist'] ?? $sec['ip_allowlist']);

        // simple validation
        if ($sec['login_rate_limit_per_min'] < 1) {
            $flash = 'Rate limit per menit minimal 1.';
            $flashType = 'danger';
        } else {
            $flash = 'Security settings tersimpan (demo). Nanti ganti ke DB + logging audit.';
            $flashType = 'success';
        }
    }
}
?>

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-2">
      <span class="avatar-initial rounded bg-label-danger">
        <i class="bx bx-shield"></i>
      </span>
      <div>
        <h4 class="mb-0">Security</h4>
        <small class="text-muted">Kontrol keamanan login, token, sesi, dan pembatasan akses</small>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="index.php?p=election-settings&user=superadmin" class="btn btn-outline-secondary">
        <i class="bx bx-cog me-1"></i> Election Settings
      </a>
      <a href="index.php?p=live-count&user=superadmin" class="btn btn-outline-primary">
        <i class="bx bx-bar-chart-alt-2 me-1"></i> Live Count
      </a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?php echo h($flashType); ?> mb-4">
      <?php echo h($flash); ?>
    </div>
  <?php endif; ?>

  <form method="post" class="row g-4">
    <input type="hidden" name="_action" value="save">

    <!-- LEFT: Settings -->
    <div class="col-12 col-xl-8">

      <!-- Login protection -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Proteksi Login</h5>
          <small class="text-muted">Cegah brute force pada login NIM + token</small>
        </div>
        <div class="card-body">

          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="login_rate_limit_enabled"
                       name="login_rate_limit_enabled" <?php echo $sec['login_rate_limit_enabled'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="login_rate_limit_enabled">Aktifkan rate limit</label>
              </div>
              <small class="text-muted d-block">Batasi request login per IP per menit.</small>
            </div>

            <div class="col-md-6">
              <label class="form-label">Rate limit (request/menit)</label>
              <input type="number" min="1" class="form-control"
                     name="login_rate_limit_per_min" value="<?php echo (int)$sec['login_rate_limit_per_min']; ?>">
            </div>

            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="login_lockout_enabled"
                       name="login_lockout_enabled" <?php echo $sec['login_lockout_enabled'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="login_lockout_enabled">Aktifkan lockout</label>
              </div>
              <small class="text-muted d-block">Kunci sementara jika gagal berulang.</small>
            </div>

            <div class="col-md-3">
              <label class="form-label">Maks gagal</label>
              <input type="number" min="1" class="form-control"
                     name="login_max_attempts" value="<?php echo (int)$sec['login_max_attempts']; ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label">Lockout (menit)</label>
              <input type="number" min="1" class="form-control"
                     name="login_lockout_minutes" value="<?php echo (int)$sec['login_lockout_minutes']; ?>">
            </div>
          </div>

          <div class="alert alert-info mt-3 mb-0">
            <strong>Implementasi nanti:</strong> simpan counter per IP/NIM, timestamp, dan blokir sementara.
          </div>

        </div>
      </div>

      <!-- Token policy -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Kebijakan Token</h5>
          <small class="text-muted">Token diterbitkan admin TPU untuk 1 pemilih</small>
        </div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Panjang token</label>
              <input type="number" min="4" max="12" class="form-control"
                     name="token_length" value="<?php echo (int)$sec['token_length']; ?>">
              <small class="text-muted">4–12</small>
            </div>

            <div class="col-md-4">
              <label class="form-label">Karakter</label>
              <select class="form-select" name="token_chars">
                <option value="NUMERIC" <?php echo $sec['token_chars']==='NUMERIC'?'selected':''; ?>>Numeric (0-9)</option>
                <option value="ALNUM" <?php echo $sec['token_chars']==='ALNUM'?'selected':''; ?>>Alphanumeric (A-Z0-9)</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">TTL token (menit)</label>
              <input type="number" min="1" class="form-control"
                     name="token_ttl_minutes" value="<?php echo (int)$sec['token_ttl_minutes']; ?>">
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="token_one_time"
                       name="token_one_time" <?php echo $sec['token_one_time'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="token_one_time">Token sekali pakai</label>
              </div>
              <small class="text-muted d-block">
                Wajib ON untuk mencegah token dipakai ulang oleh orang lain.
              </small>
            </div>
          </div>
        </div>
      </div>

      <!-- Session & CSRF -->
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Session & Proteksi Form</h5>
          <small class="text-muted">Aturan sesi admin/superadmin dan anti CSRF</small>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Session idle timeout (menit)</label>
              <input type="number" min="5" class="form-control"
                     name="session_idle_minutes" value="<?php echo (int)$sec['session_idle_minutes']; ?>">
              <small class="text-muted">Logout otomatis jika tidak aktif.</small>
            </div>

            <div class="col-md-6 d-flex align-items-center">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="session_force_logout_on_role_change"
                       name="session_force_logout_on_role_change" <?php echo $sec['session_force_logout_on_role_change'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="session_force_logout_on_role_change">
                  Paksa logout jika role berubah
                </label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="enable_csrf"
                       name="enable_csrf" <?php echo $sec['enable_csrf'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="enable_csrf">Aktifkan CSRF protection</label>
              </div>
              <small class="text-muted d-block">Wajib untuk form admin.</small>
            </div>

            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="prevent_multi_device"
                       name="prevent_multi_device" <?php echo $sec['prevent_multi_device'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="prevent_multi_device">Cegah multi-device (placeholder)</label>
              </div>
              <small class="text-muted d-block">Nanti butuh fingerprint/browser binding.</small>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- RIGHT: IP allowlist + Photo policy + Actions -->
    <div class="col-12 col-xl-4">

      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">IP Allowlist</h5>
          <small class="text-muted">Opsional, batasi akses admin dari jaringan kampus</small>
        </div>
        <div class="card-body">
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="ip_allowlist_enabled"
                   name="ip_allowlist_enabled" <?php echo $sec['ip_allowlist_enabled'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="ip_allowlist_enabled">Aktifkan allowlist</label>
          </div>

          <label class="form-label">Daftar IP / CIDR (1 per baris)</label>
          <textarea class="form-control" rows="5" name="ip_allowlist"
                    placeholder="Contoh:
127.0.0.1
192.168.1.0/24"><?php echo h($sec['ip_allowlist']); ?></textarea>

          <small class="text-muted d-block mt-2">
            Jika aktif, akses ditolak bila IP tidak ada di daftar.
          </small>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Kebijakan Foto</h5>
          <small class="text-muted">Batasi ukuran & tipe file</small>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Ukuran maksimum (MB)</label>
            <input type="number" min="1" max="10" class="form-control"
                   name="photo_max_size_mb" value="<?php echo (int)$sec['photo_max_size_mb']; ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">MIME yang diizinkan</label>
            <input type="text" class="form-control" name="photo_allowed_mime"
                   value="<?php echo h($sec['photo_allowed_mime']); ?>">
            <small class="text-muted">Contoh: image/jpeg,image/png</small>
          </div>

          <div class="mb-0">
            <label class="form-label">Simpan foto (hari)</label>
            <input type="number" min="1" class="form-control"
                   name="photo_store_days" value="<?php echo (int)$sec['photo_store_days']; ?>">
            <small class="text-muted d-block mt-1">
              Setelah lewat masa simpan, foto bisa dihapus otomatis (cron job).
            </small>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Aksi Cepat</h5>
          <small class="text-muted">Operasi keamanan (demo)</small>
        </div>
        <div class="card-body d-grid gap-2">
          <button class="btn btn-primary" type="submit">
            <i class="bx bx-save me-1"></i> Simpan Security Settings
          </button>

          <button class="btn btn-outline-danger" type="submit" name="_action" value="rotate_secret">
            <i class="bx bx-key me-1"></i> Rotate Secret Key (demo)
          </button>

          <a class="btn btn-outline-secondary" href="index.php?p=dashboard&user=superadmin">
            <i class="bx bx-left-arrow-alt me-1"></i> Kembali
          </a>
        </div>
      </div>

    </div>

    <!-- Audit ringkas -->
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <h5 class="mb-0">Audit Ringkas</h5>
            <small class="text-muted">Contoh log, nanti sumber dari tabel audit</small>
          </div>
          <a class="btn btn-sm btn-outline-primary" href="index.php?p=votes&user=superadmin">
            <i class="bx bx-receipt me-1"></i> Ke Data Suara & Audit
          </a>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th style="width: 160px;">Waktu</th>
                  <th>Actor</th>
                  <th>Aksi</th>
                  <th style="width: 160px;">IP</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($audit as $r): ?>
                <tr>
                  <td class="text-muted"><?php echo h($r['time']); ?></td>
                  <td><span class="badge bg-label-primary"><?php echo h($r['actor']); ?></span></td>
                  <td><?php echo h($r['action']); ?></td>
                  <td class="text-muted"><?php echo h($r['ip']); ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <small class="text-muted">
            Untuk versi DB: buat tabel <code>audit_logs</code> (actor_id, action, metadata_json, ip, created_at).
          </small>
        </div>
      </div>
    </div>

  </form>
</div>

<script>
(function(){
  // UX: kalau allowlist OFF, textarea disable
  const cb = document.getElementById('ip_allowlist_enabled');
  const ta = document.querySelector('textarea[name="ip_allowlist"]');
  function sync(){
    const on = cb && cb.checked;
    if (ta) ta.disabled = !on;
  }
  if (cb) cb.addEventListener('change', sync);
  sync();
})();
</script>
