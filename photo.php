<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_voter_login('index.php');

// Kalau sudah submit, redirect done
if (!empty($_SESSION['voter_flow']['submitted_at'])) {
    header('Location: done.php');
    exit;
}

$v = voter_me();

if (!isset($_SESSION['voter_flow'])) $_SESSION['voter_flow'] = [];

// Upload directory
$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$flash     = null;
$flashType = 'info';

// ====== Handle POST ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'capture') {
        $dataUrl = (string)($_POST['image_data'] ?? '');

        if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $dataUrl, $m)) {
            $flashType = 'danger';
            $flash = 'Format gambar tidak valid. Pastikan ambil foto dari webcam.';
        } else {
            $ext    = strtolower($m[1] === 'jpg' ? 'jpeg' : $m[1]);
            $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
            $raw    = base64_decode($base64, true);

            if ($raw === false || strlen($raw) < 10_000) {
                $flashType = 'danger';
                $flash = 'Gagal memproses gambar. Coba ambil ulang (pastikan cukup terang).';
            } else {
                $safeNim  = preg_replace('/[^0-9]/', '', $v['nim']) ?: 'nim';
                $fileName = 'ktm_' . $safeNim . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $abs      = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

                if (file_put_contents($abs, $raw) === false) {
                    $flashType = 'danger';
                    $flash = 'Gagal menyimpan file di server. Cek permission folder uploads.';
                } else {
                    $filePath = 'uploads/' . $fileName;

                    // Simpan record foto ke DB
                    try {
                        dbq(
                            'INSERT INTO voter_photos (voter_id, election_id, file_path, ip_address, user_agent)
                             VALUES (?,?,?,?,?)',
                            [
                                $v['voter_id'],
                                $v['election_id'],
                                $filePath,
                                $_SERVER['REMOTE_ADDR'] ?? null,
                                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                            ]
                        );
                    } catch (\Throwable $e) {
                        error_log('[photo] DB insert failed: ' . $e->getMessage());
                    }

                    $_SESSION['voter_flow']['photo_path']        = $filePath;
                    $_SESSION['voter_flow']['photo_verified_at'] = time();

                    header('Location: ballot.php');
                    exit;
                }
            }
        }
    }

    if ($action === 'reset') {
        $p = $_SESSION['voter_flow']['photo_path'] ?? null;
        if ($p && str_starts_with($p, 'uploads/')) {
            $abs = __DIR__ . DIRECTORY_SEPARATOR . $p;
            if (is_file($abs)) @unlink($abs);
        }
        unset($_SESSION['voter_flow']['photo_path'], $_SESSION['voter_flow']['photo_verified_at']);
        $flashType = 'success';
        $flash = 'Foto direset. Silakan ambil ulang.';
    }
}

$ktmPhoto = $_SESSION['voter_flow']['photo_path'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pemilih - Foto Wajah + KTM | PEMIRA UPR</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css" />
  <style>
    .cam-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:14px}
    .cam-box{border:1px solid rgba(148,163,184,.35);border-radius:16px;background:rgba(255,255,255,.62);padding:12px}
    video, canvas, img{width:100%;border-radius:14px;border:1px solid rgba(148,163,184,.25);background:#0b1220}
    .btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
    .pill-sm{font-size:12px;padding:6px 10px;border-radius:999px;border:1px solid rgba(148,163,184,.35);background:rgba(255,255,255,.5)}
    @media (max-width: 980px){.cam-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="bg-blobs" aria-hidden="true">
    <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
  </div>

  <main class="page">
    <header class="topbar">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true"><i class="bx bx-check-shield"></i></span>
        <div class="brand-text">
          <div class="brand-title">PEMIRA UPR</div>
          <div class="brand-sub">Verifikasi Foto</div>
        </div>
      </div>
      <div class="topbar-right">
        <a class="link" href="vote.php"><i class="bx bx-left-arrow-alt"></i> Kembali</a>
      </div>
    </header>

    <section class="shell">
      <section class="card">
        <div class="card-head">
          <h1>Foto Wajah + Kartu Mahasiswa</h1>
          <p class="muted">
            Wajib ambil foto <b>langsung dari webcam</b> dengan posisi <b>wajah dan KTM</b> terlihat jelas.
            Tidak ada upload file.
          </p>
        </div>

        <?php if ($flash): ?>
          <div class="alert <?php
            echo $flashType === 'success' ? 'alert-success' :
                 ($flashType === 'danger' ? 'alert-danger' :
                 ($flashType === 'warning' ? 'alert-warning' : 'alert-info'));
          ?>" style="margin:16px 22px 0;">
            <i class="bx <?php echo $flashType === 'success' ? 'bx-check-circle' : ($flashType === 'danger' ? 'bx-error-circle' : 'bx-info-circle'); ?>"></i>
            <div>
              <b><?php echo $flashType === 'success' ? 'OK' : ($flashType === 'danger' ? 'Gagal' : 'Info'); ?></b>
              <div class="muted"><?php echo h($flash); ?></div>
            </div>
          </div>
        <?php endif; ?>

        <div class="form">
          <div class="alert alert-info" style="margin:0 0 12px 0;">
            <i class="bx bx-user"></i>
            <div>
              <b><?php echo h($v['name']); ?></b>
              <div class="muted">NIM: <b><?php echo h($v['nim']); ?></b> · <?php echo h($v['faculty']); ?></div>
            </div>
          </div>

          <div class="cam-grid">
            <div class="cam-box">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <b>Webcam</b>
                <span class="pill-sm" id="camStatus">Menghubungkan…</span>
              </div>

              <div style="margin-top:10px;">
                <video id="video" playsinline autoplay muted></video>
                <canvas id="canvas" style="display:none;"></canvas>
              </div>

              <div class="btn-row">
                <button class="btn btn-primary" type="button" id="btnCapture">
                  <i class="bx bx-camera"></i> Ambil Foto
                </button>
                <button class="btn btn-outline" type="button" id="btnRetake" disabled>
                  <i class="bx bx-revision"></i> Ambil Ulang
                </button>
              </div>

              <div style="margin-top:10px;font-size:12px;color:rgba(107,114,128,.95);">
                Tips: pegang KTM dekat wajah, pastikan NIM terbaca, jangan blur, pencahayaan cukup.
              </div>
            </div>

            <div class="cam-box">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <b>Preview</b>
                <span class="pill-sm" id="prevStatus"><?php echo $ktmPhoto ? '✅ Ada (server)' : '⏳ Belum ada'; ?></span>
              </div>

              <div style="margin-top:10px;">
                <img id="previewImg" alt="Preview foto" src="<?php echo $ktmPhoto ? h($ktmPhoto) : ''; ?>"
                     style="<?php echo $ktmPhoto ? '' : 'display:none;'; ?>">
                <div id="previewEmpty" class="muted" style="<?php echo $ktmPhoto ? 'display:none;' : ''; ?>padding:12px 0;">
                  Setelah ambil foto, preview akan muncul di sini.
                </div>
              </div>

              <form method="post" id="captureForm" style="margin-top:10px;">
                <input type="hidden" name="_action" value="capture">
                <input type="hidden" name="image_data" id="imageData">
                <button class="btn btn-primary" type="submit" id="btnSubmit" disabled>
                  <i class="bx bx-upload"></i> Simpan &amp; Lanjut Memilih
                </button>
              </form>

              <form method="post" style="margin-top:10px;">
                <input type="hidden" name="_action" value="reset">
                <button class="btn btn-outline" type="submit">
                  <i class="bx bx-trash"></i> Reset Foto
                </button>
              </form>
            </div>
          </div>
        </div>
      </section>

      <footer class="footer">
        <div class="muted">© 2026 PEMIRA UPR</div>
      </footer>
    </section>
  </main>

<script>
(() => {
  const video        = document.getElementById('video');
  const canvas       = document.getElementById('canvas');
  const ctx          = canvas.getContext('2d');
  const camStatus    = document.getElementById('camStatus');
  const prevStatus   = document.getElementById('prevStatus');
  const btnCapture   = document.getElementById('btnCapture');
  const btnRetake    = document.getElementById('btnRetake');
  const btnSubmit    = document.getElementById('btnSubmit');
  const imageData    = document.getElementById('imageData');
  const previewImg   = document.getElementById('previewImg');
  const previewEmpty = document.getElementById('previewEmpty');

  let stream = null;
  let lastDataUrl = null;

  async function startCam() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: "user", width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false
      });
      video.srcObject = stream;
      camStatus.textContent = '✅ Kamera aktif';
      camStatus.style.borderColor = 'rgba(34,197,94,.4)';
    } catch (err) {
      camStatus.textContent = '❌ Kamera ditolak';
      camStatus.style.borderColor = 'rgba(239,68,68,.5)';
      alert('Kamera tidak bisa diakses.\n1) Pakai https atau localhost\n2) Izinkan kamera di browser\n3) Pastikan tidak dipakai aplikasi lain');
    }
  }

  function capture() {
    if (!video.videoWidth || !video.videoHeight) return;
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    lastDataUrl = dataUrl;
    previewImg.src = dataUrl;
    previewImg.style.display = '';
    previewEmpty.style.display = 'none';
    prevStatus.textContent = '✅ Siap disimpan';
    btnSubmit.disabled = false;
    btnRetake.disabled = false;
  }

  function retake() {
    lastDataUrl = null;
    previewImg.removeAttribute('src');
    previewImg.style.display = 'none';
    previewEmpty.style.display = '';
    prevStatus.textContent = '⏳ Belum ada';
    btnSubmit.disabled = true;
  }

  btnCapture.addEventListener('click', capture);
  btnRetake.addEventListener('click', retake);

  document.getElementById('captureForm').addEventListener('submit', (e) => {
    if (!lastDataUrl) { e.preventDefault(); alert('Ambil foto dulu sebelum menyimpan.'); return; }
    imageData.value = lastDataUrl;
  });

  if (!navigator.mediaDevices?.getUserMedia) {
    camStatus.textContent = '❌ Browser tidak mendukung webcam';
    return;
  }

  startCam();

  window.addEventListener('beforeunload', () => {
    try { if (stream) stream.getTracks().forEach(t => t.stop()); } catch (e) {}
  });
})();
</script>
</body>
</html>
