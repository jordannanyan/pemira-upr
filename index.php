<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';

// Sudah login -> langsung ke halaman berikutnya
if (voter_logged_in()) {
    header('Location: vote.php');
    exit;
}

$election = active_election();

$flash     = null;
$flashType = 'info';
$nimInput  = '';
$tokenInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nimInput   = trim((string)($_POST['nim']   ?? ''));
    $tokenInput = strtoupper(trim((string)($_POST['token'] ?? '')));

    if ($nimInput === '' || $tokenInput === '') {
        $flashType = 'danger';
        $flash = 'NIM dan Token wajib diisi.';
    } elseif (!$election) {
        $flashType = 'danger';
        $flash = 'Tidak ada periode pemilihan yang sedang aktif. Hubungi panitia.';
    } else {
        $result = voter_login($nimInput, $tokenInput);
        if ($result['ok']) {
            header('Location: vote.php');
            exit;
        } else {
            $flashType = 'danger';
            $flash = $result['msg'];
        }
    }
}

$alertClass = match($flashType) {
    'success' => 'alert-success',
    'danger'  => 'alert-danger',
    'warning' => 'alert-warning',
    default   => 'alert-info',
};

$ttlMin = $election['token_ttl_minutes'] ?? 10;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pemilih - Login (NIM + Token) | PEMIRA UPR</title>
  <link rel="icon" type="image/jpeg" href="img-logo.jpeg" />
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css" />
</head>

<body>
  <div class="bg-blobs" aria-hidden="true">
    <span class="blob b1"></span>
    <span class="blob b2"></span>
    <span class="blob b3"></span>
  </div>

  <main class="page">
    <header class="topbar">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true">
          <img src="img-logo.jpeg" alt="Logo PEMIRA UPR" style="width:36px;height:36px;object-fit:contain;border-radius:50%;">
        </span>
        <div class="brand-text">
          <div class="brand-title">PEMIRA UPR</div>
          <div class="brand-sub">Portal Pemilih</div>
        </div>
      </div>

      <div class="topbar-right">
        <?php if ($election): ?>
          <span class="pill pill-info">
            <i class="bx bx-time-five"></i>
            Token berlaku <b><?php echo (int)$ttlMin; ?> menit</b>
          </span>
        <?php else: ?>
          <span class="pill pill-warn">
            <i class="bx bx-error-circle"></i>
            Belum ada pemilihan aktif
          </span>
        <?php endif; ?>
      </div>
    </header>

    <section class="shell">
      <div class="grid">
        <!-- LEFT -->
        <section class="card">
          <div class="card-head">
            <h1>Login Pemilih</h1>
            <p class="muted">
              Masukkan <b>NIM</b> dan <b>Token</b> yang diberikan admin TPU.
            </p>
          </div>

          <?php if ($flash): ?>
            <div class="alert <?php echo h($alertClass); ?>" style="margin:16px 22px 0;">
              <i class="bx <?php echo $flashType === 'danger' ? 'bx-error-circle' : 'bx-info-circle'; ?>"></i>
              <div>
                <b><?php echo $flashType === 'danger' ? 'Gagal' : 'Info'; ?></b>
                <div class="muted"><?php echo h($flash); ?></div>
              </div>
            </div>
          <?php else: ?>
            <div class="alert alert-info" style="margin:16px 22px 0;">
              <i class="bx bx-info-circle"></i>
              <div>
                <b>Info</b>
                <div class="muted">Token hanya bisa digunakan 1 kali dan otomatis tidak valid jika expired.</div>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!$election): ?>
            <div class="alert alert-warning" style="margin:16px 22px 0;">
              <i class="bx bx-error-circle"></i>
              <div>
                <b>Pemilihan belum aktif</b>
                <div class="muted">Silakan hubungi panitia atau tunggu periode pemilihan dibuka.</div>
              </div>
            </div>
          <?php endif; ?>

          <form class="form" id="loginForm" method="post" action="">
            <div class="field">
              <label for="nim">NIM</label>
              <div class="input-wrap">
                <i class="bx bx-id-card"></i>
                <input
                  id="nim"
                  name="nim"
                  inputmode="numeric"
                  placeholder="Contoh: 2021001234"
                  required
                  value="<?php echo h($nimInput); ?>"
                  <?php echo !$election ? 'disabled' : ''; ?>
                />
              </div>
              <div class="hint">Gunakan NIM yang terdaftar pada fakultas kamu.</div>
            </div>

            <div class="field">
              <label for="token">Token</label>
              <div class="input-wrap">
                <i class="bx bx-key"></i>
                <input
                  id="token"
                  name="token"
                  placeholder="Contoh: K7Q9F2"
                  maxlength="10"
                  required
                  value="<?php echo h($tokenInput); ?>"
                  <?php echo !$election ? 'disabled' : ''; ?>
                />
                <button class="ghost-btn" type="button" id="btnPaste" title="Tempel token">
                  <i class="bx bx-clipboard"></i>
                </button>
              </div>
              <div class="hint">Token case-insensitive, akan otomatis jadi huruf besar.</div>
            </div>

            <div class="row2">
              <label class="checkbox">
                <input type="checkbox" required />
                <span>Saya memahami token bersifat pribadi dan tidak dibagikan.</span>
              </label>
            </div>

            <button class="btn btn-primary" type="submit" <?php echo !$election ? 'disabled' : ''; ?>>
              <i class="bx bx-log-in-circle"></i>
              Masuk
            </button>
          </form>
        </section>

        <!-- RIGHT -->
        <aside class="card card-soft">
          <div class="card-head">
            <h2>Alur Pemilihan</h2>
            <p class="muted">
              Sistem menjaga privasi. Admin tidak melihat kamu memilih siapa.
            </p>
          </div>

          <ol class="steps">
            <li class="step done">
              <span class="dot"><i class="bx bx-check"></i></span>
              <div class="step-body">
                <div class="step-title">Verifikasi di TPU</div>
                <div class="step-desc">Admin mengecek KTM, menandai hadir, lalu menerbitkan token.</div>
              </div>
            </li>

            <li class="step active">
              <span class="dot"><i class="bx bx-log-in"></i></span>
              <div class="step-body">
                <div class="step-title">Login NIM + Token</div>
                <div class="step-desc">Masuk menggunakan token yang masih aktif.</div>
              </div>
            </li>

            <li class="step">
              <span class="dot"><i class="bx bx-camera"></i></span>
              <div class="step-body">
                <div class="step-title">Verifikasi Foto</div>
                <div class="step-desc">Ambil foto sesuai instruksi (wajah + NIM) di bilik.</div>
              </div>
            </li>

            <li class="step">
              <span class="dot"><i class="bx bx-check-circle"></i></span>
              <div class="step-body">
                <div class="step-title">Pilih &amp; Submit</div>
                <div class="step-desc">Pilih kandidat, konfirmasi, lalu submit.</div>
              </div>
            </li>
          </ol>

          <div class="notice">
            <div class="notice-icon"><i class="bx bx-lock-alt"></i></div>
            <div>
              <div class="notice-title">Privasi Suara</div>
              <div class="notice-desc">
                Admin hanya melihat status, bukan pilihanmu.
              </div>
            </div>
          </div>

          <div class="foot">
            <span class="pill pill-ok"><i class="bx bx-wifi"></i> Online</span>
            <span class="pill pill-warn"><i class="bx bx-error-circle"></i> Jangan refresh saat proses foto</span>
          </div>
        </aside>
      </div>

      <footer class="footer">
        <div class="muted">© <script>document.write(new Date().getFullYear())</script>, made by <strong>Phytech</strong></div>
        <?php if ($election): ?>
          <div class="muted"><?php echo h($election['name']); ?></div>
        <?php endif; ?>
      </footer>
    </section>
  </main>

  <script>
    const btnPaste   = document.getElementById('btnPaste');
    const tokenInput = document.getElementById('token');

    if (tokenInput) {
      tokenInput.addEventListener('input', () => {
        tokenInput.value = (tokenInput.value || '').toUpperCase();
      });
    }

    if (btnPaste && tokenInput) {
      btnPaste.addEventListener('click', async () => {
        try {
          const text = await navigator.clipboard.readText();
          if (text) tokenInput.value = text.trim().toUpperCase();
        } catch (e) {
          alert('Clipboard tidak diizinkan oleh browser.');
        }
      });
    }
  </script>
</body>
</html>
