-- ============================================================
-- PEMIRA UPR - Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop jika sudah ada (untuk re-install)
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS votes;
DROP TABLE IF EXISTS voter_photos;
DROP TABLE IF EXISTS tokens;
DROP TABLE IF EXISTS voters;
DROP TABLE IF EXISTS candidates;
DROP TABLE IF EXISTS election_periods;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS faculties;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. FACULTIES
-- ============================================================
CREATE TABLE faculties (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. ADMIN USERS (superadmin + admin_faculty)
-- ============================================================
CREATE TABLE admin_users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('superadmin','admin_faculty') NOT NULL DEFAULT 'admin_faculty',
    faculty_id      INT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at   TIMESTAMP    NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. ELECTION PERIODS
-- ============================================================
CREATE TABLE election_periods (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100) NOT NULL,
    voting_start        DATETIME     NOT NULL,
    voting_end          DATETIME     NOT NULL,
    token_ttl_minutes   INT          NOT NULL DEFAULT 10,
    is_active           TINYINT(1)   NOT NULL DEFAULT 0,
    created_by          INT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. CANDIDATES
-- ============================================================
-- type 'presma' = Presiden/Wakil Presiden Mahasiswa (global)
-- type 'dpm'    = Anggota DPM (per-faculty)
CREATE TABLE candidates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT          NOT NULL,
    no          INT          NOT NULL,
    name        VARCHAR(100) NOT NULL,
    vision      TEXT         NULL,
    mission     TEXT         NULL,
    photo       VARCHAR(255) NULL,
    type        ENUM('presma','dpm') NOT NULL DEFAULT 'presma',
    faculty_id  INT NULL,   -- hanya untuk DPM
    is_active   TINYINT(1)  NOT NULL DEFAULT 1,
    created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_no_per_election_type (election_id, no, type, faculty_id),
    FOREIGN KEY (election_id) REFERENCES election_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. VOTERS (Daftar Pemilih Tetap)
-- ============================================================
CREATE TABLE voters (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nim             VARCHAR(20)  NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    faculty_id      INT          NOT NULL,
    gender          ENUM('L','P') NULL,      -- dari SIAkad: Jenis Kelamin
    angkatan        SMALLINT     NULL,        -- dari SIAkad: tahun masuk
    prodi           VARCHAR(100) NULL,        -- dari SIAkad: Program Studi
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    is_present      TINYINT(1)   NOT NULL DEFAULT 0,
    present_at      TIMESTAMP    NULL,
    present_by      INT NULL,   -- admin_user id yang menandai hadir
    has_voted       TINYINT(1)   NOT NULL DEFAULT 0,
    voted_at        TIMESTAMP    NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id)  REFERENCES faculties(id),
    FOREIGN KEY (present_by)  REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. TOKENS
-- ============================================================
CREATE TABLE tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    voter_id    INT          NOT NULL,
    election_id INT          NOT NULL,
    token       VARCHAR(20)  NOT NULL UNIQUE,
    issued_by   INT          NOT NULL,
    issued_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  TIMESTAMP    NOT NULL,
    used_at     TIMESTAMP    NULL,
    revoked_at  TIMESTAMP    NULL,
    revoked_by  INT NULL,
    FOREIGN KEY (voter_id)    REFERENCES voters(id) ON DELETE CASCADE,
    FOREIGN KEY (election_id) REFERENCES election_periods(id),
    FOREIGN KEY (issued_by)   REFERENCES admin_users(id),
    FOREIGN KEY (revoked_by)  REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. VOTER PHOTOS (foto bukti kehadiran)
-- ============================================================
CREATE TABLE voter_photos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    voter_id    INT          NOT NULL,
    election_id INT          NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    taken_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address  VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    FOREIGN KEY (voter_id)    REFERENCES voters(id) ON DELETE CASCADE,
    FOREIGN KEY (election_id) REFERENCES election_periods(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. VOTES (PRIVASI: tidak menyimpan voter_id)
-- Untuk menghitung suara tanpa tahu siapa memilih siapa.
-- Relasi pemilih -> sudah_memilih disimpan di voters.has_voted.
-- ============================================================
CREATE TABLE votes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    election_id     INT          NOT NULL,
    candidate_id    INT          NOT NULL,
    candidate_type  ENUM('presma','dpm') NOT NULL,
    voter_faculty_id INT         NOT NULL,   -- untuk rekap per-fakultas, bukan identitas pemilih
    voted_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    receipt         VARCHAR(32)  NOT NULL UNIQUE,
    photo_path      VARCHAR(255) NULL,
    ip_address      VARCHAR(45)  NULL,
    user_agent      VARCHAR(255) NULL,
    FOREIGN KEY (election_id)       REFERENCES election_periods(id),
    FOREIGN KEY (candidate_id)      REFERENCES candidates(id),
    FOREIGN KEY (voter_faculty_id)  REFERENCES faculties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. AUDIT LOG
-- ============================================================
CREATE TABLE audit_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    action      VARCHAR(80)  NOT NULL,
    actor_type  ENUM('superadmin','admin_faculty','voter','system') NOT NULL,
    actor_id    INT NULL,
    actor_nim   VARCHAR(20)  NULL,
    target_table VARCHAR(50) NULL,
    target_id   INT NULL,
    description TEXT         NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INDEXES TAMBAHAN
-- ============================================================
ALTER TABLE tokens      ADD INDEX idx_voter_election (voter_id, election_id);
ALTER TABLE tokens      ADD INDEX idx_active (used_at, revoked_at, expires_at);
ALTER TABLE votes       ADD INDEX idx_election_cand (election_id, candidate_id);
ALTER TABLE votes       ADD INDEX idx_faculty (voter_faculty_id);
ALTER TABLE voters      ADD INDEX idx_faculty (faculty_id);
ALTER TABLE voters      ADD INDEX idx_present (is_present);
ALTER TABLE voters      ADD INDEX idx_voted (has_voted);
ALTER TABLE audit_log   ADD INDEX idx_action (action);
ALTER TABLE audit_log   ADD INDEX idx_created (created_at);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Fakultas UPR
INSERT INTO faculties (name, code) VALUES
('Fakultas Teknik',                 'FT'),
('Fakultas Keguruan dan Ilmu Pendidikan', 'FKIP'),
('Fakultas Ekonomi dan Bisnis',     'FEB'),
('Fakultas Hukum',                  'FH'),
('Fakultas Pertanian',              'FAPERTA'),
('Fakultas Ilmu Sosial dan Ilmu Politik', 'FISIP'),
('Fakultas Kehutanan',              'FAHUT'),
('Fakultas Matematika dan Ilmu Pengetahuan Alam', 'FMIPA'),
('Pascasarjana',                    'PASCA');

-- Superadmin default
-- Password: Superadmin@2026 (ubah setelah install!)
INSERT INTO admin_users (username, name, email, password_hash, role, faculty_id) VALUES
('superadmin', 'Superadmin PEMIRA UPR', 'superadmin@upr.ac.id',
 '$2y$12$XQHnkUGKpuZgYNw1Gh4QNOHE6AoBz4A2cX9I7vXzCjK.cJXQSKM8q',
 'superadmin', NULL);
-- Hash di atas adalah bcrypt dari 'Superadmin@2026'

-- Periode pemilihan awal (inactive, superadmin yang set active)
INSERT INTO election_periods (name, voting_start, voting_end, token_ttl_minutes, is_active, created_by) VALUES
('PEMIRA UPR 2026', '2026-03-15 08:00:00', '2026-03-15 17:00:00', 10, 0, 1);
