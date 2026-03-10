<?php
declare(strict_types=1);

// ============================================================
// config/auth.php — Authentication & Session Helpers
// ============================================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // set true jika HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';

// ============================================================
// HTML escape
// ============================================================
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ============================================================
// CSRF
// ============================================================
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf'];
}
function csrf_check(?string $token): bool {
    return !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], (string)$token);
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

// ============================================================
// Flash messages
// ============================================================
function flash_set(string $type, string $msg): void {
    $_SESSION['_flash'] = ['type' => $type, 'msg' => $msg];
}
function flash_get(): ?array {
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f;
}

// ============================================================
// ADMIN AUTH
// ============================================================

/** Cek apakah admin sudah login (dan session token masih valid di DB) */
function admin_logged_in(): bool {
    if (empty($_SESSION['admin']['id'])) {
        return false;
    }
    // Verifikasi session token ke DB (cached per-request)
    static $verified = null;
    if ($verified !== null) return $verified;

    $token = $_SESSION['admin']['session_token'] ?? null;
    if (!$token) {
        $verified = false;
        return false;
    }
    $row = dbrow(
        'SELECT session_token FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1',
        [$_SESSION['admin']['id']]
    );
    $verified = ($row !== null && hash_equals((string)($row['session_token'] ?? ''), $token));
    return $verified;
}

/** Ambil data admin yang login */
function admin_me(): ?array {
    return $_SESSION['admin'] ?? null;
}

/** Role admin: 'superadmin' atau 'admin_faculty' */
function admin_role(): string {
    return $_SESSION['admin']['role'] ?? '';
}

/** Wajib login admin, redirect ke login jika tidak */
function require_admin_login(string $loginUrl = 'login.php'): void {
    if (!admin_logged_in()) {
        // Jika ada data sesi tapi token tidak cocok, berarti ada login dari perangkat lain
        if (!empty($_SESSION['admin']['id'])) {
            unset($_SESSION['admin']);
            flash_set('warning', 'Sesi Anda diakhiri karena akun ini login dari perangkat lain.');
        }
        header('Location: ' . $loginUrl);
        exit;
    }
}

/** Wajib role superadmin */
function require_superadmin(string $loginUrl = 'login.php'): void {
    require_admin_login($loginUrl);
    if (admin_role() !== 'superadmin') {
        http_response_code(403);
        exit('<p style="font-family:sans-serif;padding:20px">403 - Akses ditolak. Hanya untuk Superadmin.</p>');
    }
}

/** Login admin: verifikasi username + password, set session.
 *  $error diisi pesan jika gagal. */
function admin_login(string $username, string $password, &$error = '', bool $force = false): bool {
    $user = dbrow(
        'SELECT * FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1',
        [$username]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'Username atau password salah, atau akun tidak aktif.';
        return false;
    }

    // Blokir jika ada sesi aktif di perangkat lain
    if (!empty($user['session_token'])) {
        if (!$force) {
            $error = 'Akun ini sedang digunakan dari perangkat lain. Silakan logout terlebih dahulu, atau centang "Paksa Login" untuk mengakhiri sesi tersebut.';
            return false;
        }
        // Force login: hapus sesi lama
        dbq('UPDATE admin_users SET session_token = NULL WHERE id = ?', [$user['id']]);
    }

    // Generate session token unik untuk mencegah login dari 2 perangkat
    $sessionToken = bin2hex(random_bytes(32));

    // Update last_login dan simpan session token ke DB
    dbq('UPDATE admin_users SET last_login_at = NOW(), session_token = ? WHERE id = ?', [$sessionToken, $user['id']]);

    // Set session
    $_SESSION['admin'] = [
        'id'           => (int)$user['id'],
        'username'     => $user['username'],
        'name'         => $user['name'],
        'role'         => $user['role'],
        'faculty_id'   => $user['faculty_id'] ? (int)$user['faculty_id'] : null,
        'login_at'     => time(),
        'session_token' => $sessionToken,
    ];

    // Audit
    audit_log('admin_login', 'superadmin', (int)$user['id']);

    return true;
}

/** Logout admin */
function admin_logout(): void {
    if (admin_logged_in()) {
        $adminId = admin_me()['id'] ?? null;
        if ($adminId) {
            // Hapus session token dari DB agar sesi lain juga gugur
            dbq('UPDATE admin_users SET session_token = NULL WHERE id = ?', [$adminId]);
        }
        audit_log('admin_logout', 'superadmin', $adminId);
    }
    unset($_SESSION['admin']);
    session_regenerate_id(true);
}

// ============================================================
// VOTER AUTH
// ============================================================

function voter_logged_in(): bool {
    return !empty($_SESSION['voter']['nim']);
}

function voter_me(): ?array {
    return $_SESSION['voter'] ?? null;
}

function require_voter_login(string $loginUrl = '../index.php'): void {
    if (!voter_logged_in()) {
        header('Location: ' . $loginUrl);
        exit;
    }
}

/** Login voter: validasi NIM + token dari DB */
function voter_login(string $nim, string $token): array {
    // Cek voter
    $voter = dbrow(
        'SELECT v.*, f.name AS faculty_name, f.code AS faculty_code
         FROM voters v
         JOIN faculties f ON f.id = v.faculty_id
         WHERE v.nim = ? AND v.is_active = 1 LIMIT 1',
        [$nim]
    );
    if (!$voter) {
        return ['ok' => false, 'msg' => 'NIM tidak terdaftar atau tidak aktif.'];
    }
    if ($voter['has_voted']) {
        return ['ok' => false, 'msg' => 'NIM ini sudah melakukan pemilihan.'];
    }

    // Cek token
    $tkn = dbrow(
        'SELECT t.* FROM tokens t
         WHERE t.token = ? AND t.voter_id = ? LIMIT 1',
        [$token, $voter['id']]
    );
    if (!$tkn) {
        return ['ok' => false, 'msg' => 'Token tidak valid atau bukan milik NIM ini.'];
    }
    if ($tkn['used_at'] !== null) {
        return ['ok' => false, 'msg' => 'Token sudah digunakan. Minta token baru ke admin TPS.'];
    }
    if ($tkn['revoked_at'] !== null) {
        return ['ok' => false, 'msg' => 'Token sudah dicabut oleh admin. Minta token baru.'];
    }
    if (strtotime($tkn['expires_at']) <= time()) {
        return ['ok' => false, 'msg' => 'Token sudah expired. Minta token baru ke admin TPS.'];
    }

    // Cek election aktif
    $election = dbrow(
        'SELECT * FROM election_periods WHERE id = ? AND is_active = 1 LIMIT 1',
        [$tkn['election_id']]
    );
    if (!$election) {
        return ['ok' => false, 'msg' => 'Periode pemilihan tidak aktif.'];
    }

    // Set session voter
    $_SESSION['voter'] = [
        'nim'         => $voter['nim'],
        'voter_id'    => (int)$voter['id'],
        'name'        => $voter['name'],
        'faculty_id'  => (int)$voter['faculty_id'],
        'faculty'     => $voter['faculty_name'],
        'token'       => $token,
        'token_id'    => (int)$tkn['id'],
        'election_id' => (int)$tkn['election_id'],
        'login_at'    => time(),
    ];

    audit_log('voter_login', 'voter', null, $nim);

    return ['ok' => true];
}

function voter_logout(): void {
    unset($_SESSION['voter'], $_SESSION['voter_flow']);
    session_regenerate_id(true);
}

// ============================================================
// AUDIT LOG
// ============================================================
function audit_log(
    string  $action,
    string  $actorType,
    ?int    $actorId  = null,
    ?string $actorNim = null,
    ?string $target   = null,
    ?int    $targetId = null,
    ?string $desc     = null
): void {
    try {
        dbq(
            'INSERT INTO audit_log
             (action, actor_type, actor_id, actor_nim, target_table, target_id, description, ip_address)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $action,
                $actorType,
                $actorId,
                $actorNim,
                $target,
                $targetId,
                $desc,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    } catch (\Throwable) {
        // jangan sampai audit gagal crash halaman
    }
}

// ============================================================
// TOKEN GENERATOR
// ============================================================
function gen_token(int $len = 6): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa 0,O,1,I
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

/** Buat token unik (loop sampai tidak collision) */
function create_unique_token(int $len = 6): string {
    $tries = 0;
    do {
        $token = gen_token($len);
        $exists = dbval('SELECT COUNT(*) FROM tokens WHERE token = ?', [$token]);
        $tries++;
    } while ($exists && $tries < 20);
    return $token;
}
