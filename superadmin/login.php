<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';

// Jika sudah login, redirect ke dashboard
if (admin_logged_in()) {
    header('Location: index.php?p=dashboard');
    exit;
}

$flash = flash_get();
$error = null;
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        $error = 'Token keamanan tidak valid. Refresh dan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $usernameVal = $username;

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } elseif (!admin_login($username, $password)) {
            $error = 'Username atau password salah, atau akun tidak aktif.';
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
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/favicon.ico" />
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
                            <svg width="25" viewBox="0 0 25 42" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <path d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z" id="path-1"></path>
                                </defs>
                                <use fill="#696cff" href="#path-1"></use>
                            </svg>
                        </span>
                        <span class="app-brand-text demo fw-bolder ms-2">PEMIRA UPR</span>
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
