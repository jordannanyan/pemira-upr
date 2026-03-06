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
                <span class="brand-mark" aria-hidden="true"><i class="bx bx-check-shield"></i></span>
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
                <div class="muted">© 2026 PEMIRA UPR</div>
            </footer>
        </section>
    </main>
</body>
</html>
