<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_voter_login('index.php');

if (empty($_SESSION['voter_flow']['submitted_at'])) {
    header('Location: ballot.php');
    exit;
}

$v       = voter_me();
$receipt = $_SESSION['voter_flow']['receipt'] ?? '-';

// Selesai & keluar
if (isset($_GET['finish'])) {
    voter_logout();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pemilih - Selesai | PEMIRA UPR</title>
  <link rel="icon" type="image/jpeg" href="img-logo.jpeg" />
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="bg-blobs" aria-hidden="true">
    <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
  </div>

  <main class="page">
    <header class="topbar">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true"><img src="img-logo.jpeg" alt="Logo PEMIRA UPR" style="width:36px;height:36px;object-fit:contain;border-radius:50%;"></span>
        <div class="brand-text">
          <div class="brand-title">PEMIRA UPR</div>
          <div class="brand-sub">Selesai</div>
        </div>
      </div>
    </header>

    <section class="shell">
      <section class="card">
        <div class="card-head">
          <h1>Terima kasih!</h1>
          <p class="muted">
            Suaramu telah berhasil tercatat. Kamu bisa keluar dari bilik.
          </p>
        </div>

        <div class="form">
          <div class="alert alert-success" style="margin:0;">
            <i class="bx bx-check-circle"></i>
            <div>
              <b>Berhasil Submit</b>
              <div class="muted">
                Nama: <b><?php echo h($v['name']); ?></b> · NIM: <b><?php echo h($v['nim']); ?></b><br>
                Kode bukti: <b><?php echo h(strtoupper($receipt)); ?></b>
              </div>
            </div>
          </div>

          <div style="margin-top:14px;">
            <a class="btn btn-primary" href="done.php?finish=1">
              <i class="bx bx-log-out"></i> Selesai &amp; Keluar
            </a>
          </div>

          <div style="margin-top:10px; font-size:12px; color:rgba(107,114,128,.95);">
            Privasi: pilihan kandidat tidak ditampilkan di sini dan tidak dikaitkan dengan identitasmu.
          </div>
        </div>
      </section>

      <footer class="footer">
        <div class="muted">© <script>document.write(new Date().getFullYear())</script>, made by <strong>Phytech</strong></div>
      </footer>
    </section>
  </main>
</body>
</html>
