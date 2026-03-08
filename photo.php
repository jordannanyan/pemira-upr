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
  <link rel="icon" type="image/jpeg" href="img-logo.jpeg" />
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css" />
  <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
  <script src="models/hands/hands.js"></script>
  <style>
    .cam-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:14px}
    .cam-box{border:1px solid rgba(148,163,184,.35);border-radius:16px;background:rgba(255,255,255,.62);padding:12px}
    video, canvas, img{width:100%;border-radius:14px;border:1px solid rgba(148,163,184,.25);background:#0b1220}
    #faceOverlay{background:transparent;border:none;}
    /* Mirror live video (selfie feel, supaya natural seperti kaca) */
    #video{transform:scaleX(-1)}
    .btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:10px}
    .btn-row .btn{min-height:44px;flex:1;min-width:120px}
    .pill-sm{font-size:12px;padding:6px 10px;border-radius:999px;border:1px solid rgba(148,163,184,.35);background:rgba(255,255,255,.5)}
    /* Portrait phones: stack */
    @media (max-width: 640px){
      .cam-grid{grid-template-columns:1fr}
      .cam-box{padding:10px}
    }
    /* Overlay rotate hint — hanya muncul di portrait pada layar kecil (HP) */
    #rotateHint{
      display:none;
      position:fixed;inset:0;z-index:9999;
      background:rgba(15,23,42,.88);
      backdrop-filter:blur(6px);
      flex-direction:column;align-items:center;justify-content:center;gap:16px;
      color:#fff;text-align:center;padding:24px;
    }
    #rotateHint .rotate-icon{font-size:56px;animation:spin90 1.2s ease-in-out infinite alternate}
    @keyframes spin90{from{transform:rotate(0deg)}to{transform:rotate(90deg)}}
    @media (max-width:768px) and (orientation:portrait){
      #rotateHint{display:flex}
    }
    /* Landscape phone (lebar tapi pendek): 2 kolom compact */
    @media (orientation:landscape) and (max-height:520px){
      .cam-grid{grid-template-columns:1fr 1fr;gap:8px}
      .cam-box{padding:8px}
      #video,#previewImg{max-height:38vh;object-fit:cover}
      .card-head h1{font-size:1rem;margin-bottom:2px}
      .card-head p{display:none}
      .topbar{padding:6px 16px}
    }
  </style>
</head>
<body>
  <!-- Overlay: minta putar HP ke landscape (hanya muncul di portrait mobile via CSS) -->
  <div id="rotateHint" role="alertdialog" aria-label="Putar HP ke Landscape">
    <i class="bx bx-mobile rotate-icon"></i>
    <div>
      <div style="font-size:1.15rem;font-weight:700;margin-bottom:6px;">Putar HP ke Landscape</div>
      <div style="font-size:.9rem;opacity:.85;">Untuk kemudahan mengambil foto wajah + KTM,<br>putar HP ke posisi <b>horizontal (landscape)</b>.</div>
    </div>
  </div>

  <div class="bg-blobs" aria-hidden="true">
    <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
  </div>

  <main class="page">
    <header class="topbar">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true"><img src="img-logo.jpeg" alt="Logo PEMIRA UPR" style="width:36px;height:36px;object-fit:contain;border-radius:50%;"></span>
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
            <div class="cam-box" id="camBoxLeft">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <b>Webcam</b>
                <span class="pill-sm" id="camStatus">Menghubungkan…</span>
              </div>

              <div style="margin-top:10px;position:relative;">
                <video id="video" playsinline autoplay muted></video>
                <canvas id="faceOverlay" style="position:absolute;top:0;left:0;width:100%;height:100%;border-radius:14px;pointer-events:none;transform:scaleX(-1);"></canvas>
                <canvas id="canvas" style="display:none;"></canvas>
              </div>

              <div id="faceStatus" style="margin-top:8px;font-size:12px;padding:6px 10px;border-radius:8px;background:rgba(251,191,36,.15);border:1px solid rgba(251,191,36,.4);color:#92400e;display:flex;align-items:center;gap:6px;">
                <i class="bx bx-loader-alt bx-spin"></i> <span id="faceStatusText">Memuat model deteksi wajah…</span>
              </div>

              <div style="margin-top:10px;font-size:12px;color:rgba(107,114,128,.95);">
                Tips: pegang KTM dekat wajah, pastikan NIM terbaca, jangan blur, pencahayaan cukup.
              </div>

              <div style="margin-top:10px;border-top:1px solid rgba(148,163,184,.25);padding-top:10px;">
                <div style="font-size:11px;font-weight:600;color:rgba(107,114,128,.8);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em;">Contoh Foto Yang Benar</div>
                <img src="photo-example.jpg" alt="Contoh foto: wajah + KTM terlihat jelas" style="width:100%;max-width:220px;border-radius:10px;border:1px solid rgba(148,163,184,.3);display:block;">
              </div>
            </div>

            <div class="cam-box" id="camBoxRight">
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

              <div class="btn-row">
                <button class="btn btn-primary" type="button" id="btnCapture">
                  <i class="bx bx-camera"></i> Ambil Foto
                </button>
                <button class="btn btn-outline" type="button" id="btnRetake" disabled>
                  <i class="bx bx-revision"></i> Ambil Ulang
                </button>
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
        <div class="muted">© <script>document.write(new Date().getFullYear())</script>, made by <strong>Phytech</strong></div>
      </footer>
    </section>
  </main>

<script>
(() => {
  const video        = document.getElementById('video');
  const canvas       = document.getElementById('canvas');
  const faceOverlay  = document.getElementById('faceOverlay');
  const ctx          = canvas.getContext('2d');
  const overlayCtx   = faceOverlay.getContext('2d');
  const camStatus    = document.getElementById('camStatus');
  const prevStatus   = document.getElementById('prevStatus');
  const btnCapture   = document.getElementById('btnCapture');
  const btnRetake    = document.getElementById('btnRetake');
  const btnSubmit    = document.getElementById('btnSubmit');
  const imageData    = document.getElementById('imageData');
  const previewImg   = document.getElementById('previewImg');
  const previewEmpty = document.getElementById('previewEmpty');
  const faceStatus   = document.getElementById('faceStatus');
  const faceStatusTx = document.getElementById('faceStatusText');

  let stream         = null;
  let lastDataUrl    = null;
  let captureReady   = false;
  let detectionRunning = false;
  let handsDetector  = null;
  let handPresent    = false;

  const MODEL_URL = 'models';

  // ── Status UI ────────────────────────────────────────────────
  function setStatus(faceCount, hasHand) {
    captureReady = faceCount === 1 && hasHand;
    btnCapture.disabled = !captureReady;
    faceStatus.querySelector('i.bx-loader-alt')?.remove();

    if (faceCount === 0) {
      faceStatus.style.cssText += 'background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.35);color:#7f1d1d;';
      faceStatusTx.innerHTML = '<i class="bx bx-error-circle"></i> Wajah tidak terdeteksi — arahkan wajah ke kamera';
    } else if (faceCount > 1) {
      faceStatus.style.cssText += 'background:rgba(251,191,36,.15);border-color:rgba(251,191,36,.4);color:#92400e;';
      faceStatusTx.innerHTML = `<i class="bx bx-error-circle"></i> Terdeteksi ${faceCount} wajah — pastikan hanya 1 wajah dalam frame`;
    } else if (!hasHand) {
      faceStatus.style.cssText += 'background:rgba(249,115,22,.1);border-color:rgba(249,115,22,.4);color:#7c2d12;';
      faceStatusTx.innerHTML = '<i class="bx bx-id-card"></i> Wajah terdeteksi — tunjukkan KTM ke kamera';
    } else {
      faceStatus.style.cssText += 'background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.4);color:#14532d;';
      faceStatusTx.innerHTML = '<i class="bx bx-check-circle"></i> Wajah + KTM terdeteksi — siap ambil foto';
    }
  }

  // ── Draw face bounding boxes ──────────────────────────────────
  function drawDetections(detections) {
    faceOverlay.width  = video.videoWidth;
    faceOverlay.height = video.videoHeight;
    overlayCtx.clearRect(0, 0, faceOverlay.width, faceOverlay.height);
    detections.forEach(det => {
      const { x, y, width, height } = det.box;
      overlayCtx.strokeStyle = '#22c55e';
      overlayCtx.lineWidth   = 3;
      overlayCtx.strokeRect(x, y, width, height);
    });
  }

  // ── Detection loop ────────────────────────────────────────────
  async function detectLoop() {
    if (!detectionRunning) return;
    if (video.readyState === 4) {
      try {
        const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });
        const detections = await faceapi.detectAllFaces(video, opts);
        if (handsDetector) await handsDetector.send({ image: video });
        drawDetections(detections);
        setStatus(detections.length, handPresent);
      } catch (_) {}
    }
    setTimeout(detectLoop, 300);
  }

  // ── Load models & start loop ──────────────────────────────────
  async function loadDetectors() {
    try {
      faceStatusTx.textContent = 'Memuat model deteksi wajah…';
      await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);

      faceStatusTx.textContent = 'Memuat model deteksi tangan…';
      const hands = new Hands({
        locateFile: (file) => `models/hands/${file}`
      });
      hands.setOptions({ maxNumHands: 2, modelComplexity: 0, minDetectionConfidence: 0.5, minTrackingConfidence: 0.5 });
      hands.onResults((res) => {
        handPresent = !!(res.multiHandLandmarks && res.multiHandLandmarks.length > 0);
      });
      await hands.initialize();
      handsDetector = hands;

      faceStatusTx.textContent = 'Mendeteksi wajah dan tangan…';
      detectionRunning = true;
      detectLoop();
    } catch (e) {
      console.error(e);
      faceStatusTx.textContent = 'Gagal memuat model — tombol tetap aktif.';
      btnCapture.disabled = false;
    }
  }

  // ── Camera ───────────────────────────────────────────────────
  async function startCam() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: "user", width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false
      });
      video.srcObject = stream;
      camStatus.textContent = '✅ Kamera aktif';
      camStatus.style.borderColor = 'rgba(34,197,94,.4)';
      video.addEventListener('playing', loadDetectors, { once: true });
    } catch (err) {
      camStatus.textContent = '❌ Kamera ditolak';
      camStatus.style.borderColor = 'rgba(239,68,68,.5)';
      alert('Kamera tidak bisa diakses.\n1) Pakai https atau localhost\n2) Izinkan kamera di browser\n3) Pastikan tidak dipakai aplikasi lain');
    }
  }

  // ── Capture ───────────────────────────────────────────────────
  function capture() {
    if (!captureReady) { alert('Pastikan wajah dan KTM terlihat jelas di kamera.'); return; }
    if (!video.videoWidth || !video.videoHeight) return;

    detectionRunning = false;
    overlayCtx.clearRect(0, 0, faceOverlay.width, faceOverlay.height);

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.save();
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    ctx.restore();

    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    lastDataUrl = dataUrl;
    previewImg.src = dataUrl;
    previewImg.style.display = '';
    previewEmpty.style.display = 'none';
    prevStatus.textContent = '✅ Siap disimpan';
    btnSubmit.disabled = false;
    btnRetake.disabled = false;

    document.getElementById('camBoxLeft').style.display = 'none';
    document.getElementById('camBoxRight').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // ── Retake ────────────────────────────────────────────────────
  function retake() {
    lastDataUrl = null;
    previewImg.removeAttribute('src');
    previewImg.style.display = 'none';
    previewEmpty.style.display = '';
    prevStatus.textContent = '⏳ Belum ada';
    btnSubmit.disabled = true;
    document.getElementById('camBoxLeft').style.display = '';
    detectionRunning = true;
    detectLoop();
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

  btnCapture.disabled = true;
  startCam();

  window.addEventListener('beforeunload', () => {
    detectionRunning = false;
    try { if (stream) stream.getTracks().forEach(t => t.stop()); } catch (e) {}
  });
})();
</script>
</body>
</html>
