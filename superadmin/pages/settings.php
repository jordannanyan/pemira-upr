<?php
// pages/settings.php
// Superadmin – General Settings
// URL: index.php?p=settings&user=superadmin

if ($role !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'>
            <div class='card'><div class='card-body'>
              <h4 class='mb-2'>403 - Akses ditolak</h4>
              <p class='mb-0'>Halaman ini hanya untuk <b>superadmin</b>.</p>
            </div></div>
          </div>";
    return;
}

// ===== Dummy settings (nanti ganti DB) =====
$settings = [
  'app_name' => 'PEMIRA UPR',
  'app_short' => 'PEMIRA',
  'election_name' => 'Pemilihan Umum Kampus 2026',
  'organizer' => 'KPU Mahasiswa',
  'campus_name' => 'Universitas Palangka Raya',

  // schedule
  'start_at' => '2026-01-12 08:00',
  'end_at'   => '2026-01-12 16:00',
  'timezone' => 'Asia/Pontianak',

  // public mode
  'public_voting_enabled' => 1,  // kalau OFF, voter login ditolak
  'public_livecount_enabled' => 1, // kalau OFF, live count publik disembunyikan
  'maintenance_mode' => 0,

  // live count display
  'livecount_auto_refresh_sec' => 5,
  'livecount_show_percent' => 1,
  'livecount_show_total' => 1,
  'livecount_fullpage_default' => 0,

  // UI branding
  'brand_primary_hex' => '#696cff',
  'brand_logo_text' => 'Sneat', // nanti bisa ganti text saja (template)
];

// ===== Flash handler (demo only) =====
$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? 'save';

    if ($action === 'reset_demo') {
        $flashType = 'warning';
        $flash = 'Reset data berhasil (demo). Nanti implementasi: truncate tabel tertentu + audit log.';
    } else {
        // save
        $settings['app_name'] = trim($_POST['app_name'] ?? $settings['app_name']);
        $settings['app_short'] = trim($_POST['app_short'] ?? $settings['app_short']);
        $settings['election_name'] = trim($_POST['election_name'] ?? $settings['election_name']);
        $settings['organizer'] = trim($_POST['organizer'] ?? $settings['organizer']);
        $settings['campus_name'] = trim($_POST['campus_name'] ?? $settings['campus_name']);

        $settings['start_at'] = trim($_POST['start_at'] ?? $settings['start_at']);
        $settings['end_at'] = trim($_POST['end_at'] ?? $settings['end_at']);
        $settings['timezone'] = trim($_POST['timezone'] ?? $settings['timezone']);

        $settings['public_voting_enabled'] = isset($_POST['public_voting_enabled']) ? 1 : 0;
        $settings['public_livecount_enabled'] = isset($_POST['public_livecount_enabled']) ? 1 : 0;
        $settings['maintenance_mode'] = isset($_POST['maintenance_mode']) ? 1 : 0;

        $settings['livecount_auto_refresh_sec'] = max(2, min(60, (int)($_POST['livecount_auto_refresh_sec'] ?? $settings['livecount_auto_refresh_sec'])));
        $settings['livecount_show_percent'] = isset($_POST['livecount_show_percent']) ? 1 : 0;
        $settings['livecount_show_total'] = isset($_POST['livecount_show_total']) ? 1 : 0;
        $settings['livecount_fullpage_default'] = isset($_POST['livecount_fullpage_default']) ? 1 : 0;

        $settings['brand_primary_hex'] = trim($_POST['brand_primary_hex'] ?? $settings['brand_primary_hex']);
        $settings['brand_logo_text'] = trim($_POST['brand_logo_text'] ?? $settings['brand_logo_text']);

        // simple validation
        if ($settings['app_name'] === '' || $settings['election_name'] === '') {
            $flashType = 'danger';
            $flash = 'Nama aplikasi dan nama pemilihan wajib diisi.';
        } else {
            $flashType = 'success';
            $flash = 'Settings tersimpan (demo). Nanti ganti ke DB + audit log.';
        }
    }
}
?>

<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-2">
      <span class="avatar-initial rounded bg-label-primary">
        <i class="bx bx-cog"></i>
      </span>
      <div>
        <h4 class="mb-0">Settings</h4>
        <small class="text-muted">Pengaturan umum aplikasi pemira</small>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="index.php?p=security&user=superadmin" class="btn btn-outline-danger">
        <i class="bx bx-shield me-1"></i> Security
      </a>
      <a href="index.php?p=election-settings&user=superadmin" class="btn btn-outline-secondary">
        <i class="bx bx-wrench me-1"></i> Election Settings
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

    <!-- LEFT -->
    <div class="col-12 col-xl-8">

      <!-- App Identity -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Identitas</h5>
          <small class="text-muted">Nama aplikasi, pemilihan, dan penyelenggara</small>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label">Nama aplikasi</label>
              <input class="form-control" name="app_name" value="<?php echo h($settings['app_name']); ?>">
            </div>
            <div class="col-md-5">
              <label class="form-label">Nama singkat</label>
              <input class="form-control" name="app_short" value="<?php echo h($settings['app_short']); ?>">
            </div>

            <div class="col-md-12">
              <label class="form-label">Nama pemilihan</label>
              <input class="form-control" name="election_name" value="<?php echo h($settings['election_name']); ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Penyelenggara</label>
              <input class="form-control" name="organizer" value="<?php echo h($settings['organizer']); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nama kampus</label>
              <input class="form-control" name="campus_name" value="<?php echo h($settings['campus_name']); ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Schedule -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Jadwal Pemilihan</h5>
          <small class="text-muted">Start & end waktu pemilihan</small>
        </div>
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Time zone</label>
              <input class="form-control" name="timezone" value="<?php echo h($settings['timezone']); ?>">
              <small class="text-muted">Contoh: Asia/Pontianak</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Mulai</label>
              <input class="form-control" name="start_at" value="<?php echo h($settings['start_at']); ?>">
              <small class="text-muted">Format: YYYY-MM-DD HH:MM</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Selesai</label>
              <input class="form-control" name="end_at" value="<?php echo h($settings['end_at']); ?>">
            </div>
          </div>

          <div class="alert alert-info mt-3 mb-0">
            <strong>Tip:</strong> Nanti validasi wajib: <code>start_at &lt; end_at</code>. Jika sudah lewat end, voting otomatis ditutup.
          </div>
        </div>
      </div>

      <!-- Public & Maintenance -->
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Mode Publik</h5>
          <small class="text-muted">Kontrol akses voter dan live count publik</small>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="public_voting_enabled"
                       name="public_voting_enabled" <?php echo $settings['public_voting_enabled'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="public_voting_enabled">Voting dibuka</label>
              </div>
              <small class="text-muted d-block">Jika OFF, login voter ditolak.</small>
            </div>

            <div class="col-md-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="public_livecount_enabled"
                       name="public_livecount_enabled" <?php echo $settings['public_livecount_enabled'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="public_livecount_enabled">Live count publik ditampilkan</label>
              </div>
              <small class="text-muted d-block">Jika OFF, live count hanya untuk admin/superadmin.</small>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="maintenance_mode"
                       name="maintenance_mode" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="maintenance_mode">Maintenance mode</label>
              </div>
              <small class="text-muted d-block">
                Jika ON, tampilkan halaman maintenance untuk publik (admin tetap bisa akses).
              </small>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- RIGHT -->
    <div class="col-12 col-xl-4">

      <!-- Livecount Display -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Tampilan Live Count</h5>
          <small class="text-muted">Pengaturan UI chart</small>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Auto refresh (detik)</label>
            <input type="number" min="2" max="60" class="form-control"
                   name="livecount_auto_refresh_sec" value="<?php echo (int)$settings['livecount_auto_refresh_sec']; ?>">
          </div>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="livecount_show_percent"
                   name="livecount_show_percent" <?php echo $settings['livecount_show_percent'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="livecount_show_percent">Tampilkan persentase</label>
          </div>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="livecount_show_total"
                   name="livecount_show_total" <?php echo $settings['livecount_show_total'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="livecount_show_total">Tampilkan total suara</label>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="livecount_fullpage_default"
                   name="livecount_fullpage_default" <?php echo $settings['livecount_fullpage_default'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="livecount_fullpage_default">Default ke full page</label>
          </div>
        </div>
      </div>

      <!-- Branding -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Branding</h5>
          <small class="text-muted">Warna utama & text logo</small>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Primary color (hex)</label>
            <input class="form-control" name="brand_primary_hex" value="<?php echo h($settings['brand_primary_hex']); ?>">
            <small class="text-muted">Contoh: #696cff</small>
          </div>
          <div class="mb-0">
            <label class="form-label">Text logo</label>
            <input class="form-control" name="brand_logo_text" value="<?php echo h($settings['brand_logo_text']); ?>">
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Aksi</h5>
          <small class="text-muted">Operasi admin (demo)</small>
        </div>
        <div class="card-body d-grid gap-2">
          <button class="btn btn-primary" type="submit">
            <i class="bx bx-save me-1"></i> Simpan Settings
          </button>

          <button class="btn btn-outline-warning" type="submit" name="_action" value="reset_demo"
                  onclick="return confirm('Reset demo ini belum hapus DB beneran. Lanjut?');">
            <i class="bx bx-reset me-1"></i> Reset Demo Data
          </button>

          <a class="btn btn-outline-secondary" href="index.php?p=dashboard&user=superadmin">
            <i class="bx bx-left-arrow-alt me-1"></i> Kembali
          </a>
        </div>
      </div>

    </div>
  </form>

</div>

<script>
(function(){
  // UX kecil: kalau maintenance ON, matikan voting/public toggle di UI (visual only)
  const maint = document.getElementById('maintenance_mode');
  const voting = document.getElementById('public_voting_enabled');
  const live = document.getElementById('public_livecount_enabled');

  function sync(){
    const on = maint && maint.checked;
    if (voting) voting.disabled = on;
    if (live) live.disabled = on;
  }
  if (maint) maint.addEventListener('change', sync);
  sync();
})();
</script>
