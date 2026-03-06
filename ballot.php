<?php
declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_voter_login('index.php');

if (empty($_SESSION['voter_flow']['photo_verified_at'])) {
    header('Location: photo.php');
    exit;
}
if (!empty($_SESSION['voter_flow']['submitted_at'])) {
    header('Location: done.php');
    exit;
}

$v          = voter_me();
$electionId = (int)$v['election_id'];
$facultyId  = (int)$v['faculty_id'];

// ====== Ambil kandidat dari DB ======
// Presma: type='presma', aktif, untuk election ini
$presmaList = dbrows(
    'SELECT * FROM candidates
     WHERE election_id = ? AND type = ? AND is_active = 1
     ORDER BY no ASC',
    [$electionId, 'presma']
);

// DPM: type='dpm', fakultas pemilih, aktif
$dpmList = dbrows(
    'SELECT * FROM candidates
     WHERE election_id = ? AND type = ? AND faculty_id = ? AND is_active = 1
     ORDER BY no ASC',
    [$electionId, 'dpm', $facultyId]
);

$hasDpm = count($dpmList) > 0;

$flash     = null;
$flashType = 'info';
$selectedPresma = '';
$selectedDpm    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedPresma = trim((string)($_POST['candidate_presma'] ?? ''));
    $selectedDpm    = $hasDpm ? trim((string)($_POST['candidate_dpm'] ?? '')) : '0';
    $confirm        = isset($_POST['confirm']);

    $validPresmaIds = array_column($presmaList, 'id');
    $validDpmIds    = $hasDpm ? array_column($dpmList, 'id') : [];

    $errors = [];

    if ($selectedPresma === '' || !in_array((int)$selectedPresma, $validPresmaIds, false)) {
        $errors[] = 'Pilih kandidat Presiden Mahasiswa.';
    }
    if ($hasDpm && ($selectedDpm === '' || !in_array((int)$selectedDpm, $validDpmIds, false))) {
        $errors[] = 'Pilih kandidat DPM untuk fakultas kamu.';
    }
    if (!$confirm) {
        $errors[] = 'Centang konfirmasi sebelum submit.';
    }

    if ($errors) {
        $flashType = 'danger';
        $flash     = implode(' ', $errors);
    } else {
        // === Simpan suara ke DB (dalam transaksi) ===
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $receipt    = bin2hex(random_bytes(12));
            $photoPath  = $_SESSION['voter_flow']['photo_path'] ?? null;
            $ip         = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua         = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $now        = date('Y-m-d H:i:s');

            // Cek belum pernah vote (double-check dari DB)
            $alreadyVoted = (int)dbval(
                'SELECT has_voted FROM voters WHERE id = ? FOR UPDATE',
                [$v['voter_id']]
            );
            if ($alreadyVoted) {
                throw new \RuntimeException('Pemilih sudah memilih.');
            }

            // Simpan suara Presma
            dbq(
                'INSERT INTO votes
                 (election_id, candidate_id, candidate_type, voter_faculty_id, receipt, photo_path, ip_address, user_agent)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$electionId, (int)$selectedPresma, 'presma', $facultyId, $receipt . '_p', $photoPath, $ip, $ua]
            );

            // Simpan suara DPM (jika ada)
            if ($hasDpm && (int)$selectedDpm > 0) {
                dbq(
                    'INSERT INTO votes
                     (election_id, candidate_id, candidate_type, voter_faculty_id, receipt, photo_path, ip_address, user_agent)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [$electionId, (int)$selectedDpm, 'dpm', $facultyId, $receipt . '_d', $photoPath, $ip, $ua]
                );
            }

            // Tandai pemilih sudah vote + token sudah dipakai
            dbq(
                'UPDATE voters SET has_voted = 1, voted_at = NOW() WHERE id = ?',
                [$v['voter_id']]
            );
            dbq(
                'UPDATE tokens SET used_at = NOW() WHERE id = ?',
                [$v['token_id']]
            );

            $pdo->commit();

            audit_log('voter_vote', 'voter', null, $v['nim'], 'votes', null, 'election_id=' . $electionId);

            $_SESSION['voter_flow']['submitted_at'] = time();
            $_SESSION['voter_flow']['receipt']      = $receipt;

            header('Location: done.php');
            exit;

        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[ballot] Vote failed: ' . $e->getMessage());
            $flashType = 'danger';
            $flash = $e->getMessage() === 'Pemilih sudah memilih.'
                ? 'Kamu sudah melakukan pemilihan sebelumnya.'
                : 'Terjadi kesalahan saat menyimpan suara. Coba lagi atau hubungi panitia.';
        }
    }
}

// Helper
function alertClass(string $t): string {
    return match($t) { 'danger' => 'alert-danger', 'warning' => 'alert-warning', default => 'alert-info' };
}
function alertIcon(string $t): string {
    return match($t) { 'danger', 'warning' => 'bx-error-circle', default => 'bx-info-circle' };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pemilih - Pilih Kandidat | PEMIRA UPR</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css" />
  <style>
    .cand-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:12px;}
    .cand-grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-top:12px;}
    .cand{--cand-color:#6366f1;position:relative;border:1.5px solid rgba(148,163,184,.35);background:rgba(255,255,255,.70);border-radius:18px;padding:14px;cursor:pointer;transition:transform .14s ease,box-shadow .14s ease,border-color .14s ease;overflow:hidden;min-height:220px;user-select:none;}
    .cand:hover{transform:translateY(-2px);box-shadow:0 14px 35px rgba(2,6,23,.10);}
    .cand input{display:none}
    .cand-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;}
    .badgeNo{display:inline-flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:16px;background:rgba(255,255,255,.72);border:1px solid rgba(148,163,184,.30);font-weight:900;color:#0f172a;flex:0 0 auto;box-shadow:0 6px 18px rgba(2,6,23,.06);}
    .cand-photo{width:100%;height:110px;border-radius:16px;border:1px solid rgba(148,163,184,.25);display:flex;align-items:center;justify-content:center;margin-bottom:12px;background:radial-gradient(120px 80px at 25% 20%,rgba(255,255,255,.95),rgba(255,255,255,.0)),linear-gradient(180deg,rgba(255,255,255,.75),rgba(255,255,255,.35));}
    .cand-photo img{width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid rgba(148,163,184,.35);}
    .cand-photo i{font-size:70px;color:var(--cand-color);opacity:.98;}
    .cand-name{font-weight:900;font-size:17px;line-height:1.15;margin-bottom:4px;color:#0f172a}
    .cand-desc{color:rgba(107,114,128,.95);font-size:13px;line-height:1.3}
    .cand-check{position:absolute;top:12px;right:12px;width:38px;height:38px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.92);border:1px solid rgba(148,163,184,.35);opacity:.25;transform:scale(.95);transition:opacity .14s ease,transform .14s ease;}
    .cand-check i{font-size:20px;color:rgba(15,23,42,.75)}
    .cand::before{content:"";position:absolute;left:0;right:0;bottom:0;height:0px;background:linear-gradient(90deg,rgba(0,0,0,0),var(--cand-color),rgba(0,0,0,0));transition:height .14s ease;}
    .cand.selected{border-color:rgba(0,0,0,0);outline:3px solid var(--cand-color);transform:translateY(-2px) scale(1.03);box-shadow:0 14px 38px rgba(2,6,23,.14);}
    .cand.selected::before{height:6px}
    .cand.selected .cand-check{opacity:1;transform:scale(1.08);}
    .cand.selected .cand-check i{color:var(--cand-color);}
    .section-label{font-weight:700;font-size:15px;margin:18px 0 4px;color:#0f172a}
    @media (max-width: 1100px){.cand-grid{grid-template-columns:repeat(2,1fr)}}
    @media (max-width: 720px){.cand-grid,.cand-grid-2{grid-template-columns:1fr}.cand{min-height:auto}}
    .actions-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-top:14px;padding-top:12px;border-top:1px solid rgba(148,163,184,.25);}
    .hint-mini{font-size:12px;color:rgba(107,114,128,.95)}
  </style>
</head>
<body>
  <div class="bg-blobs" aria-hidden="true">
    <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
  </div>

  <main class="page">
    <header class="topbar">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true"><i class="bx bx-check-shield"></i></span>
        <div class="brand-text">
          <div class="brand-title">PEMIRA UPR</div>
          <div class="brand-sub">Pilih Kandidat</div>
        </div>
      </div>
      <div class="topbar-right">
        <a class="link" href="photo.php"><i class="bx bx-left-arrow-alt"></i> Kembali</a>
      </div>
    </header>

    <section class="shell">
      <section class="card">
        <div class="card-head">
          <h1>Surat Suara</h1>
          <p class="muted">
            Pilih <b>1 kandidat Presiden Mahasiswa</b><?php echo $hasDpm ? ' dan <b>1 kandidat DPM</b> untuk fakultasmu' : ''; ?>.
            Setelah submit, proses selesai dan token hangus.
          </p>
        </div>

        <?php if ($flash): ?>
          <div class="alert <?php echo h(alertClass($flashType)); ?>" style="margin:16px 22px 0;">
            <i class="bx <?php echo h(alertIcon($flashType)); ?>"></i>
            <div>
              <b><?php echo $flashType === 'danger' ? 'Perhatian' : 'Info'; ?></b>
              <div class="muted"><?php echo h($flash); ?></div>
            </div>
          </div>
        <?php endif; ?>

        <div class="form">
          <div class="alert alert-info" style="margin:0 0 12px 0;">
            <i class="bx bx-lock-alt"></i>
            <div>
              <b>Privasi Suara</b>
              <div class="muted">Pilihan kamu tidak akan diketahui admin.</div>
            </div>
          </div>

          <form method="post" id="ballotForm">

            <!-- ====== PRESMA ====== -->
            <div class="section-label">
              <i class="bx bx-shield-alt-2"></i> Presiden Mahasiswa
            </div>
            <?php if (empty($presmaList)): ?>
              <div class="alert alert-warning" style="margin-top:8px;">Belum ada kandidat Presma yang ditetapkan untuk periode ini.</div>
            <?php else: ?>
              <div class="cand-grid" id="candGridPresma">
                <?php foreach ($presmaList as $c): ?>
                  <?php
                    $isSel = ((string)$selectedPresma === (string)$c['id']);
                    $color = '#6366f1';
                    $colors = ['#2563eb','#16a34a','#dc2626','#d97706','#7c3aed','#0891b2'];
                    $color = $colors[((int)$c['no'] - 1) % count($colors)];
                  ?>
                  <label class="cand <?php echo $isSel ? 'selected' : ''; ?>" data-id="<?php echo h((string)$c['id']); ?>" style="--cand-color:<?php echo $color; ?>;">
                    <input type="radio" name="candidate_presma" value="<?php echo h((string)$c['id']); ?>" <?php echo $isSel ? 'checked' : ''; ?> />
                    <div class="cand-check" aria-hidden="true"><i class="bx bx-check"></i></div>
                    <div class="cand-head">
                      <div class="badgeNo">#<?php echo (int)$c['no']; ?></div>
                    </div>
                    <div class="cand-photo" aria-hidden="true">
                      <?php if ($c['photo'] && is_file(__DIR__ . '/' . $c['photo'])): ?>
                        <img src="<?php echo h($c['photo']); ?>" alt="Foto <?php echo h($c['name']); ?>">
                      <?php else: ?>
                        <i class="bx bx-user-circle"></i>
                      <?php endif; ?>
                    </div>
                    <div class="cand-name"><?php echo h($c['name']); ?></div>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <!-- ====== DPM ====== -->
            <?php if ($hasDpm): ?>
              <div class="section-label" style="margin-top:24px;">
                <i class="bx bx-group"></i> DPM — <?php echo h($v['faculty']); ?>
              </div>
              <div class="cand-grid-2" id="candGridDpm">
                <?php foreach ($dpmList as $c): ?>
                  <?php
                    $isSel = ((string)$selectedDpm === (string)$c['id']);
                    $colors2 = ['#0891b2','#16a34a','#d97706','#7c3aed','#dc2626','#2563eb'];
                    $color2  = $colors2[((int)$c['no'] - 1) % count($colors2)];
                  ?>
                  <label class="cand <?php echo $isSel ? 'selected' : ''; ?>" data-id="<?php echo h((string)$c['id']); ?>" data-group="dpm" style="--cand-color:<?php echo $color2; ?>;">
                    <input type="radio" name="candidate_dpm" value="<?php echo h((string)$c['id']); ?>" <?php echo $isSel ? 'checked' : ''; ?> />
                    <div class="cand-check" aria-hidden="true"><i class="bx bx-check"></i></div>
                    <div class="cand-head">
                      <div class="badgeNo">#<?php echo (int)$c['no']; ?></div>
                    </div>
                    <div class="cand-photo" aria-hidden="true">
                      <?php if ($c['photo'] && is_file(__DIR__ . '/' . $c['photo'])): ?>
                        <img src="<?php echo h($c['photo']); ?>" alt="Foto <?php echo h($c['name']); ?>">
                      <?php else: ?>
                        <i class="bx bx-user"></i>
                      <?php endif; ?>
                    </div>
                    <div class="cand-name"><?php echo h($c['name']); ?></div>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="row2" style="margin-top:16px;">
              <label class="checkbox">
                <input type="checkbox" name="confirm" value="1" />
                <span>Saya yakin dengan pilihan saya dan siap submit.</span>
              </label>
            </div>

            <div class="actions-bar">
              <button class="btn btn-primary" type="submit">
                <i class="bx bx-send"></i> Vote Suara
              </button>
              <div class="hint-mini">
                Setelah vote, token dianggap sudah dipakai dan tidak bisa digunakan lagi.
              </div>
            </div>
          </form>
        </div>
      </section>

      <footer class="footer">
        <div class="muted">© 2026 PEMIRA UPR</div>
      </footer>
    </section>
  </main>

  <script>
    (function () {
      function initGrid(gridId) {
        const grid = document.getElementById(gridId);
        if (!grid) return;
        const cards = grid.querySelectorAll('.cand');
        cards.forEach((card) => {
          card.addEventListener('click', () => {
            cards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            const radio = card.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
          });
          const radio = card.querySelector('input[type="radio"]');
          if (radio?.checked) card.classList.add('selected');
        });
      }
      initGrid('candGridPresma');
      initGrid('candGridDpm');
    })();
  </script>
</body>
</html>
