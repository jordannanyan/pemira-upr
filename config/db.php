<?php
declare(strict_types=1);

// ============================================================
// config/db.php — PDO Database Connection
// ============================================================

date_default_timezone_set('Asia/Jakarta');

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'pemira_upr');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // Tampilkan pesan umum agar tidak leak detail DB ke user
        error_log('[DB] Connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('<p style="font-family:sans-serif;color:#c00;padding:20px">Koneksi database gagal. Hubungi administrator.</p>');
    }

    return $pdo;
}

// Helper: jalankan query dengan parameter, return PDOStatement
function dbq(string $sql, array $params = []): \PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Helper: ambil satu baris
function dbrow(string $sql, array $params = []): ?array {
    $row = dbq($sql, $params)->fetch();
    return $row === false ? null : $row;
}

// Helper: ambil semua baris
function dbrows(string $sql, array $params = []): array {
    return dbq($sql, $params)->fetchAll();
}

// Helper: scalar (COUNT, dll)
function dbval(string $sql, array $params = []): mixed {
    return dbq($sql, $params)->fetchColumn();
}

// Helper: ambil election aktif
function active_election(): ?array {
    static $cache = null;
    if ($cache !== null) return $cache ?: null;
    $cache = dbrow('SELECT * FROM election_periods WHERE is_active = 1 LIMIT 1') ?? false;
    return $cache ?: null;
}
