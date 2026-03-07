<?php
// pages/voters.php — Superadmin: Kelola Pemilih

if ($role !== 'superadmin') {
    http_response_code(403);
    echo "<div class='container-xxl flex-grow-1 container-p-y'><div class='card'><div class='card-body'>
            <h4>403 - Akses ditolak</h4><p>Halaman ini hanya untuk Superadmin.</p>
          </div></div></div>";
    return;
}

$flash = flash_get();
$csrf  = csrf_token();

// Faculties for dropdown
$faculties = dbrows('SELECT id, name, code FROM faculties WHERE is_active = 1 ORDER BY name ASC');

// Filters
$q             = trim($_GET['q'] ?? '');
$facultyFilter = trim($_GET['faculty'] ?? '');
$statusFilter  = trim($_GET['status'] ?? '');  // 'present'|'voted'|'not_voted'
$page          = max(1, (int)($_GET['pg'] ?? 1));
$perPage       = 30;
$offset        = ($page - 1) * $perPage;

// Build WHERE
$where  = ['v.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[]  = '(v.nim LIKE ? OR v.name LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($facultyFilter !== '') {
    $where[]  = 'v.faculty_id = ?';
    $params[] = (int)$facultyFilter;
}
if ($statusFilter === 'present') {
    $where[] = 'v.is_present = 1';
} elseif ($statusFilter === 'voted') {
    $where[] = 'v.has_voted = 1';
} elseif ($statusFilter === 'not_voted') {
    $where[] = 'v.has_voted = 0';
}

$whereStr = implode(' AND ', $where);

$total = (int)dbval("SELECT COUNT(*) FROM voters v WHERE $whereStr", $params);
$rows  = dbrows(
    "SELECT v.*, f.name AS faculty_name
     FROM voters v
     JOIN faculties f ON f.id = v.faculty_id
     WHERE $whereStr
     ORDER BY v.name ASC
     LIMIT $perPage OFFSET $offset",
    $params
);
$pages = max(1, (int)ceil($total / $perPage));

// ============================================================
// Handle POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) {
        flash_set('danger', 'Token keamanan tidak valid.');
        header('Location: index.php?p=voters');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ---- Tambah satu pemilih ----
    if ($action === 'add') {
        $nim       = trim($_POST['nim'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $errors    = [];

        if ($nim === '')  $errors[] = 'NIM wajib diisi.';
        if ($name === '') $errors[] = 'Nama wajib diisi.';
        if (!$facultyId)  $errors[] = 'Pilih fakultas.';

        if (!$errors && dbval('SELECT COUNT(*) FROM voters WHERE nim = ?', [$nim])) {
            $errors[] = "NIM $nim sudah terdaftar.";
        }
        if ($errors) {
            flash_set('danger', implode('<br>', array_map('h', $errors)));
        } else {
            dbq('INSERT INTO voters (nim, name, faculty_id) VALUES (?,?,?)', [$nim, $name, $facultyId]);
            audit_log('add_voter', 'superadmin', $admin['id'], $nim, 'voters');
            flash_set('success', "Pemilih $nim berhasil ditambahkan.");
        }
        header('Location: index.php?p=voters');
        exit;
    }

    // ---- Toggle aktif ----
    if ($action === 'toggle_active') {
        $voterId = (int)($_POST['voter_id'] ?? 0);
        $v = $voterId ? dbrow('SELECT * FROM voters WHERE id = ?', [$voterId]) : null;
        if ($v) {
            $newState = $v['is_active'] ? 0 : 1;
            dbq('UPDATE voters SET is_active = ? WHERE id = ?', [$newState, $voterId]);
            audit_log($newState ? 'activate_voter' : 'deactivate_voter', 'superadmin', $admin['id'], $v['nim'], 'voters', $voterId);
            flash_set('success', 'Status pemilih berhasil diubah.');
        }
        header('Location: index.php?p=voters&q=' . urlencode($q) . '&faculty=' . urlencode($facultyFilter) . '&status=' . urlencode($statusFilter));
        exit;
    }

    // ---- Import CSV ----
    if ($action === 'import_csv') {
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash_set('danger', 'File CSV tidak ditemukan atau gagal diupload.');
            header('Location: index.php?p=voters');
            exit;
        }

        // Filter opsional
        $filterBayar = isset($_POST['filter_bayar']);   // hanya Status Bayar = Aktif
        $filterKrs   = isset($_POST['filter_krs']);     // hanya Status KRS = Sudah KRS

        // Fallback: faculty_id tetap (format lama)
        $fallbackFacultyId = (int)($_POST['import_faculty_id'] ?? 0);

        // Build faculty lookup map: lowercase name => id
        $allFacRows  = dbrows('SELECT id, name FROM faculties');
        $facultyMap  = [];
        foreach ($allFacRows as $f) {
            $facultyMap[mb_strtolower(trim($f['name']))] = (int)$f['id'];
        }

        $handle = fopen($file['tmp_name'], 'r');

        // Deteksi encoding UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $added         = 0;
        $skipped       = 0;
        $unknownFac    = [];
        $lineNo        = 0;

        // Baca header baris pertama
        $header = fgetcsv($handle, 2000, ',');
        if (!$header) {
            flash_set('danger', 'File CSV kosong atau tidak dapat dibaca.');
            fclose($handle);
            header('Location: index.php?p=voters');
            exit;
        }

        // Normalize header
        $headerNorm = array_map(fn($h) => mb_strtolower(trim($h)), $header);

        // Deteksi format: SIAkad (ada "nama mahasiswa") atau format sederhana (nim,nama)
        $isSiakad = in_array('nama mahasiswa', $headerNorm, true);

        if ($isSiakad) {
            // Cari posisi kolom
            $colNim     = array_search('nim',             $headerNorm);
            $colNama    = array_search('nama mahasiswa',  $headerNorm);
            $colFak     = array_search('fakultas',        $headerNorm);
            $colBayar   = array_search('status bayar',    $headerNorm);
            $colKrs     = array_search('status krs',      $headerNorm);
            $colGender  = array_search('jenis kelamin',   $headerNorm);
            $colAngkatan= array_search('angkatan',        $headerNorm);
            $colProdi   = array_search('prodi',           $headerNorm);

            if ($colNim === false || $colNama === false) {
                flash_set('danger', 'Kolom NIM atau Nama Mahasiswa tidak ditemukan di header CSV.');
                fclose($handle);
                header('Location: index.php?p=voters');
                exit;
            }

            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                $lineNo++;

                $nim  = trim($row[$colNim]  ?? '');
                $name = trim($row[$colNama] ?? '');
                if ($nim === '' || $name === '') { $skipped++; continue; }

                // Filter Status Bayar
                if ($filterBayar && $colBayar !== false) {
                    $bayar = mb_strtolower(trim($row[$colBayar] ?? ''));
                    if ($bayar !== 'aktif') { $skipped++; continue; }
                }

                // Filter Status KRS
                if ($filterKrs && $colKrs !== false) {
                    $krs = mb_strtolower(trim($row[$colKrs] ?? ''));
                    if ($krs !== 'sudah krs') { $skipped++; continue; }
                }

                // Resolve faculty
                $facId = $fallbackFacultyId;
                if ($colFak !== false) {
                    $facName = mb_strtolower(trim($row[$colFak] ?? ''));
                    if ($facName !== '' && isset($facultyMap[$facName])) {
                        $facId = $facultyMap[$facName];
                    } elseif ($facName !== '') {
                        // Coba partial match (trim whitespace / typo minor)
                        foreach ($facultyMap as $mapName => $mapId) {
                            if (str_contains($mapName, $facName) || str_contains($facName, $mapName)) {
                                $facId = $mapId;
                                break;
                            }
                        }
                    }
                }

                if (!$facId) {
                    $unknownFac[] = $row[$colFak] ?? '(kosong)';
                    $skipped++;
                    continue;
                }

                // Cek duplikat
                if (dbval('SELECT COUNT(*) FROM voters WHERE nim = ?', [$nim])) {
                    $skipped++;
                    continue;
                }

                // Extra fields dari SIAkad
                $gender   = null;
                if ($colGender !== false) {
                    $g = strtoupper(trim($row[$colGender] ?? ''));
                    if ($g === 'L' || $g === 'P') $gender = $g;
                }
                $angkatan = null;
                if ($colAngkatan !== false) {
                    $a = (int)trim($row[$colAngkatan] ?? '');
                    if ($a >= 1990 && $a <= 2100) $angkatan = $a;
                }
                $prodi = null;
                if ($colProdi !== false) {
                    $p = trim($row[$colProdi] ?? '');
                    if ($p !== '') $prodi = mb_substr($p, 0, 100);
                }

                dbq(
                    'INSERT INTO voters (nim, name, faculty_id, gender, angkatan, prodi) VALUES (?,?,?,?,?,?)',
                    [$nim, $name, $facId, $gender, $angkatan, $prodi]
                );
                $added++;
            }

        } else {
            // Format sederhana: nim,nama  (kolom 0 = nim, kolom 1 = nama)
            // Cek apakah baris header sudah dilewati atau belum
            $nimFirst = mb_strtolower(trim($header[0] ?? ''));
            if ($nimFirst !== 'nim') {
                // Baris pertama bukan header, proses sebagai data
                $nim  = trim($header[0] ?? '');
                $name = trim($header[1] ?? '');
                if ($nim !== '' && $name !== '' && $fallbackFacultyId) {
                    if (!dbval('SELECT COUNT(*) FROM voters WHERE nim = ?', [$nim])) {
                        dbq('INSERT INTO voters (nim, name, faculty_id) VALUES (?,?,?)', [$nim, $name, $fallbackFacultyId]);
                        $added++;
                    } else {
                        $skipped++;
                    }
                }
            }

            if (!$fallbackFacultyId) {
                flash_set('danger', 'Untuk format sederhana (nim,nama), pilih Fakultas terlebih dahulu.');
                fclose($handle);
                header('Location: index.php?p=voters');
                exit;
            }

            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                $nim  = trim($row[0] ?? '');
                $name = trim($row[1] ?? '');
                if ($nim === '' || $name === '') { $skipped++; continue; }

                if (dbval('SELECT COUNT(*) FROM voters WHERE nim = ?', [$nim])) {
                    $skipped++;
                    continue;
                }

                dbq('INSERT INTO voters (nim, name, faculty_id) VALUES (?,?,?)', [$nim, $name, $fallbackFacultyId]);
                $added++;
            }
        }

        fclose($handle);

        $msg = "Import selesai: <b>$added</b> ditambahkan, <b>$skipped</b> dilewati.";
        if ($unknownFac) {
            $uniq = array_unique($unknownFac);
            $msg .= '<br><span class="text-warning">Fakultas tidak dikenali (' . count($uniq) . '): '
                  . implode(', ', array_map('h', $uniq)) . '</span>';
        }
        flash_set($added > 0 ? 'success' : 'warning', $msg);
        header('Location: index.php?p=voters');
        exit;
    }
}
?>

<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">Kelola Pemilih</h4>
            <div class="text-muted">Superadmin · Total terdaftar: <b><?php echo number_format($total); ?></b></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalImport">
                <i class="bx bx-upload me-1"></i> Import CSV
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdd">
                <i class="bx bx-plus me-1"></i> Tambah Pemilih
            </button>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?> alert-dismissible mb-3">
            <?php echo $flash['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form class="d-flex flex-wrap gap-2" method="get" action="index.php">
                <input type="hidden" name="p" value="voters">
                <input type="text" class="form-control" name="q" value="<?php echo h($q); ?>"
                       placeholder="Cari NIM / Nama..." style="max-width:260px;">
                <select class="form-select" name="faculty" style="max-width:240px;">
                    <option value="">Semua Fakultas</option>
                    <?php foreach ($faculties as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo $facultyFilter == $f['id'] ? 'selected' : ''; ?>>
                            <?php echo h($f['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select" name="status" style="max-width:180px;">
                    <option value="">Semua Status</option>
                    <option value="present"   <?php echo $statusFilter === 'present'   ? 'selected' : ''; ?>>Hadir</option>
                    <option value="voted"     <?php echo $statusFilter === 'voted'     ? 'selected' : ''; ?>>Sudah Memilih</option>
                    <option value="not_voted" <?php echo $statusFilter === 'not_voted' ? 'selected' : ''; ?>>Belum Memilih</option>
                </select>
                <button class="btn btn-outline-primary" type="submit">
                    <i class="bx bx-search me-1"></i> Filter
                </button>
                <a class="btn btn-outline-secondary" href="index.php?p=voters">Reset</a>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th>Fakultas</th>
                            <th class="text-center">Hadir</th>
                            <th class="text-center">Sudah Memilih</th>
                            <th style="width:100px;" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data yang cocok.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $v): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($v['nim']); ?></td>
                                <td>
                                    <?php echo h($v['name']); ?>
                                    <?php if (!$v['is_active']): ?>
                                        <span class="badge bg-label-secondary ms-1">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($v['faculty_name']); ?></td>
                                <td class="text-center">
                                    <?php if ($v['is_present']): ?>
                                        <span class="badge bg-label-success">Ya</span>
                                    <?php else: ?>
                                        <span class="badge bg-label-secondary">Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($v['has_voted']): ?>
                                        <span class="badge bg-label-success">Ya</span>
                                    <?php else: ?>
                                        <span class="badge bg-label-danger">Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="index.php?p=voter-detail&nim=<?php echo urlencode($v['nim']); ?>"
                                       class="btn btn-sm btn-outline-primary" title="Detail">
                                        <i class="bx bx-show-alt"></i>
                                    </a>
                                    <form method="post" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="voter_id" value="<?php echo $v['id']; ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?php echo $v['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                                title="<?php echo $v['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                            <i class="bx <?php echo $v['is_active'] ? 'bx-user-minus' : 'bx-user-plus'; ?>"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1):
                $pgBase = 'index.php?p=voters&q=' . urlencode($q) . '&faculty=' . urlencode($facultyFilter) . '&status=' . urlencode($statusFilter);
                // Window: current page, 2 before, 3 after
                $window = [];
                for ($i = max(1, $page - 2); $i <= min($pages, $page + 3); $i++) {
                    $window[] = $i;
                }
            ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-end mb-0">
                        <!-- Prev -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $pgBase; ?>&pg=<?php echo $page - 1; ?>">«</a>
                        </li>

                        <!-- First page + ellipsis -->
                        <?php if (!in_array(1, $window)): ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $pgBase; ?>&pg=1">1</a></li>
                            <?php if ($window[0] > 2): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Window pages -->
                        <?php foreach ($window as $i): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $pgBase; ?>&pg=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endforeach; ?>

                        <!-- Ellipsis + last page -->
                        <?php if (!in_array($pages, $window)): ?>
                            <?php if ($window[count($window) - 1] < $pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $pgBase; ?>&pg=<?php echo $pages; ?>"><?php echo $pages; ?></a></li>
                        <?php endif; ?>

                        <!-- Next -->
                        <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $pgBase; ?>&pg=<?php echo $page + 1; ?>">»</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tambah Pemilih -->
<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Pemilih</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label">NIM</label>
                        <input type="text" class="form-control" name="nim" required inputmode="numeric">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Fakultas</label>
                        <select class="form-select" name="faculty_id" required>
                            <option value="">-- Pilih Fakultas --</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo h($f['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import CSV -->
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Pemilih dari CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="import_csv">
                <div class="modal-body">

                    <!-- Format info -->
                    <div class="alert alert-info mb-3">
                        <div class="fw-semibold mb-1"><i class="bx bx-info-circle me-1"></i>Format yang didukung</div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="fw-semibold text-primary">Format SIAkad / Lengkap</div>
                                <small class="text-muted">Header harus ada kolom:</small>
                                <ul class="small mb-0 mt-1">
                                    <li><code>NIM</code> — nomor induk mahasiswa</li>
                                    <li><code>Nama Mahasiswa</code> — nama lengkap</li>
                                    <li><code>Fakultas</code> — akan di-mapping otomatis ke DB</li>
                                    <li><code>Status Bayar</code> &amp; <code>Status KRS</code> — opsional untuk filter</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="fw-semibold text-secondary">Format Sederhana</div>
                                <small class="text-muted">Hanya 2 kolom, pilih fakultas manual:</small>
                                <pre class="mb-0 mt-1" style="font-size:.78rem;background:#f8f8f8;padding:6px;border-radius:4px;">nim,nama
193010201015,Risna Dwi Purniaty
193010201016,Ahmad Fauzi</pre>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">File CSV <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="csv_file" accept=".csv,.txt" required>
                            <small class="text-muted">
                                Export dari Excel: <b>File → Save As → CSV UTF-8 (Comma delimited)</b>.
                                Format kolom NIM harus <b>Text</b> agar angka 0 di depan tidak hilang.
                            </small>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Fallback Fakultas
                                <small class="text-muted">(untuk format sederhana atau jika kolom Fakultas tidak ada)</small>
                            </label>
                            <select class="form-select" name="import_faculty_id">
                                <option value="">-- Otomatis dari kolom Fakultas --</option>
                                <?php foreach ($faculties as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"><?php echo h($f['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Filter (khusus format SIAkad)</label>
                            <div class="d-flex gap-4 flex-wrap mt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="filter_bayar" id="filterBayar" checked>
                                    <label class="form-check-label" for="filterBayar">
                                        Hanya <b>Status Bayar = Aktif</b>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="filter_krs" id="filterKrs" checked>
                                    <label class="form-check-label" for="filterKrs">
                                        Hanya <b>Status KRS = Sudah KRS</b>
                                    </label>
                                </div>
                            </div>
                            <small class="text-muted">Mahasiswa yang tidak memenuhi filter akan dilewati.</small>
                        </div>
                    </div>

                    <!-- Mapping preview -->
                    <div class="alert alert-secondary mt-3 mb-0" style="font-size:.82rem;">
                        <div class="fw-semibold mb-1">Mapping Nama Fakultas → Database</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($faculties as $f): ?>
                                <span class="badge bg-label-secondary"><?php echo h($f['name']); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-1 text-muted">Nama di kolom Fakultas harus persis sama (case-insensitive).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-upload me-1"></i> Import Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
