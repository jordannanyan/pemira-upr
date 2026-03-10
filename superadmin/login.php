<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';

// Jika sudah login, redirect ke dashboard
if (admin_logged_in()) {
    header('Location: index.php?p=dashboard');
    exit;
}

$flash = flash_get();
$error = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        $error = 'Token keamanan tidak valid. Refresh dan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $usernameVal = $username;

        $force = isset($_POST['force_login']);
        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } elseif (!admin_login($username, $password, $error, $force)) {
            // sleep 1 detik untuk mitigasi brute force
            sleep(1);
        } else {
            header('Location: index.php?p=dashboard');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="light-style" data-theme="theme-default" data-assets-path="assets/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login Admin - PEMIRA UPR</title>
    <link rel="icon" type="image/jpeg" href="../img-logo.jpeg" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="assets/vendor/css/core.css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="assets/css/demo.css" />
    <link rel="stylesheet" href="assets/vendor/css/pages/page-auth.css" />
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/js/config.js"></script>
</head>
<body>
<div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
            <div class="card">
                <div class="card-body">
                    <!-- Logo -->
                    <div class="app-brand justify-content-center mb-4">
                        <span class="app-brand-logo demo">
                            <img src="../img-logo.jpeg" alt="Logo PEMIRA UPR" style="width:40px;height:40px;object-fit:contain;border-radius:50%;">
                        </span>
                        <span class="app-brand-text demo fw-bolder ms-2" style="text-transform:none">PEMIRA UPR</span>
                    </div>

                    <h4 class="mb-1">Login Admin</h4>
                    <p class="mb-4 text-muted">Masuk ke panel administrasi PEMIRA UPR</p>

                    <?php if ($flash): ?>
                        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible mb-3" role="alert">
                            <?php echo h($flash['msg']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger mb-3" role="alert">
                            <i class="bx bx-error-circle me-1"></i>
                            <?php echo h($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="" autocomplete="off">
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input
                                type="text"
                                class="form-control"
                                id="username"
                                name="username"
                                value="<?php echo h($usernameVal); ?>"
                                placeholder="Masukkan username"
                                autofocus
                                required
                            />
                        </div>

                        <div class="mb-3 form-password-toggle">
                            <div class="d-flex justify-content-between">
                                <label class="form-label" for="password">Password</label>
                            </div>
                            <div class="input-group input-group-merge">
                                <input
                                    type="password"
                                    id="password"
                                    class="form-control form-control-merge"
                                    name="password"
                                    placeholder="Masukkan password"
                                    required
                                />
                                <span class="input-group-text cursor-pointer">
                                    <i class="bx bx-hide"></i>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="force_login" id="force_login" />
                                <label class="form-check-label text-muted small" for="force_login">
                                    Paksa Login (akhiri sesi aktif di perangkat lain)
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary d-grid w-100">
                                <i class="bx bx-log-in-circle me-1"></i> Login
                            </button>
                        </div>
                    </form>

                    <p class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bx bx-shield-quarter me-1"></i>
                            Akses terbatas untuk admin yang berwenang.
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/vendor/libs/jquery/jquery.js"></script>
<script src="assets/vendor/libs/popper/popper.js"></script>
<script src="assets/vendor/js/bootstrap.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
