<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_voter_login('index.php');

// Logout
if (isset($_GET['logout'])) {
    voter_logout();
    header('Location: index.php');
    exit;
}

// Kalau sudah submit, langsung ke done
if (!empty($_SESSION['voter_flow']['submitted_at'])) {
    header('Location: done.php');
    exit;
}

$v = voter_me();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Pemilih - Verifikasi | PEMIRA UPR</title>
    <link rel="icon" type="image/jpeg" href="img-logo.jpeg" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css" />
<style>
    /* Watermark */
    body::after {
        content: "<?php echo h($v['nim']); ?> · <?php echo h($v['name']); ?>";
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 2rem;
        color: rgba(0,0,0,0.06);
        white-space: nowrap;
        pointer-events: none;
        z-index: 9999;
        user-select: none;
    }
    /* Blur overlay when page not focused */
    #screen-guard {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.92);
        z-index: 99999;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        color: #fff;
        font-family: sans-serif;
        text-align: center;
        gap: 12px;
    }
    #screen-guard.active { display: flex; }
</style>
</head>
<body>
    <!-- Screen guard overlay -->
    <div id="screen-guard">
        <div style="font-size:3rem;">🔒</div>
        <div style="font-size:1.2rem;font-weight:bold;">Halaman disembunyikan</div>
        <div style="font-size:0.9rem;opacity:0.7;">Klik atau kembali ke halaman ini untuk melanjutkan.</div>
    </div>
    <div class="bg-blobs" aria-hidden="true">
        <span class="blob b1"></span>
        <span class="blob b2"></span>
        <span class="blob b3"></span>
    </div>

    <main class="page">
        <header class="topbar">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true"><img src="img-logo.jpeg" alt="Logo PEMIRA UPR" style="width:36px;height:36px;object-fit:contain;border-radius:50%;"></span>
                <div class="brand-text">
                    <div class="brand-title">PEMIRA UPR</div>
                    <div class="brand-sub">Halaman Pemilih</div>
                </div>
            </div>
            <div class="topbar-right">
                <a class="link" href="?logout=1"><i class="bx bx-log-out"></i> Keluar</a>
            </div>
        </header>

        <section class="shell">
            <section class="card">
                <div class="card-head">
                    <h1>Login Berhasil</h1>
                    <p class="muted">Identitas kamu terverifikasi. Lanjutkan ke proses foto dan pemilihan.</p>
                </div>

                <div class="form" style="padding-top:10px;">
                    <div class="alert alert-info" style="margin:0;">
                        <i class="bx bx-user"></i>
                        <div>
                            <b>Identitas Pemilih</b>
                            <div class="muted">
                                Nama: <b><?php echo h($v['name']); ?></b><br>
                                NIM: <b><?php echo h($v['nim']); ?></b><br>
                                Fakultas: <b><?php echo h($v['faculty']); ?></b>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <button class="btn btn-primary" type="button" onclick="location.href='photo.php'">
                            <i class="bx bx-camera"></i> Lanjut Verifikasi Foto
                        </button>
                    </div>

                    <div style="margin-top:12px; font-size:12px; color:rgba(107,114,128,.95);">
                        Langkah selanjutnya: ambil foto wajah + KTM di bilik, lalu pilih kandidat.
                    </div>
                </div>
            </section>

            <footer class="footer">
                <div class="muted">© <script>document.write(new Date().getFullYear())</script>, made by <strong>Phytech</strong></div>
            </footer>
        </section>
    </main>
<script>
    const guard = document.getElementById('screen-guard');
    document.addEventListener('visibilitychange', () => {
        guard.classList.toggle('active', document.hidden);
    });
    window.addEventListener('blur', () => guard.classList.add('active'));
    window.addEventListener('focus', () => guard.classList.remove('active'));
</script>
</body>
</html>
