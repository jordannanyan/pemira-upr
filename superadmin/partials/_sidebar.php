<?php
// _sidebar.php — pakai $role dari session (set di index.php)
$page = trim($_GET['p'] ?? 'dashboard');
$page = trim($page, '/');
if ($page === '' || !preg_match('/^[a-zA-Z0-9_\-\/]+$/', $page)) $page = 'dashboard';

$active = fn(string $p): string => $page === $p ? 'active' : '';
$isAny  = fn(array $pages): bool => in_array($page, $pages, true);
$open   = fn(array $pages): string => $isAny($pages) ? 'open active' : '';
$u      = fn(string $p): string => 'index.php?p=' . urlencode($p);

$roleLabel      = $role === 'superadmin' ? 'Superadmin' : 'Admin Fakultas';
$roleBadgeClass = $role === 'superadmin' ? 'bg-label-danger' : 'bg-label-primary';
$roleIcon       = $role === 'superadmin' ? 'bx-shield-quarter' : 'bx-user-check';
?>

<!-- Menu -->
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
  <div class="app-brand demo">
    <a href="<?php echo h($u('dashboard')); ?>" class="app-brand-link">
      <span class="app-brand-logo demo">
        <img src="../img-logo.jpeg" alt="Logo PEMIRA UPR" style="width:32px;height:32px;object-fit:contain;border-radius:50%;">
      </span>
      <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform:none">PEMIRA UPR</span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
      <i class="bx bx-chevron-left bx-sm align-middle"></i>
    </a>
  </div>

  <div class="px-3 pb-2">
    <div class="d-flex align-items-center gap-2">
      <span class="badge <?php echo $roleBadgeClass; ?>">
        <i class="bx <?php echo $roleIcon; ?> me-1"></i><?php echo h($roleLabel); ?>
      </span>
      <small class="text-muted"><?php echo h($admin['username'] ?? ''); ?></small>
    </div>
  </div>

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">

    <!-- DASHBOARD -->
    <li class="menu-item <?php echo $active('dashboard'); ?>">
      <a href="<?php echo h($u('dashboard')); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-home-circle"></i>
        <div>Dashboard</div>
      </a>
    </li>

    <!-- LIVE COUNT (superadmin only) -->
    <?php if ($role === 'superadmin'): ?>
    <li class="menu-item <?php echo $active('live-count'); ?>">
      <a href="<?php echo h($u('live-count')); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-pie-chart-alt-2"></i>
        <div>Live Count</div>
      </a>
    </li>
    <?php endif; ?>

    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">Operasional</span>
    </li>

    <!-- ADMIN FAKULTAS MENU -->
    <?php if ($role === 'admin_faculty'): ?>
    <li class="menu-item <?php echo $active('issue-token'); ?>">
      <a href="<?php echo h($u('issue-token')); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-key"></i>
        <div>Verifikasi &amp; Token</div>
      </a>
    </li>

    <li class="menu-item <?php echo $active('voters-present'); ?>">
      <a href="<?php echo h($u('voters-present')); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-user-check"></i>
        <div>Pemilih Hadir</div>
      </a>
    </li>

    <li class="menu-item <?php echo $active('tokens-active'); ?>">
      <a href="<?php echo h($u('tokens-active')); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-timer"></i>
        <div>Token Aktif</div>
      </a>
    </li>

    <?php endif; ?>

    <!-- SUPERADMIN MENU -->
    <?php if ($role === 'superadmin'): ?>
    <li class="menu-item <?php echo $active('faculty-recap'); ?>">
      <a href="<?php echo h($u('faculty-recap')); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
        <div>Rekap Fakultas</div>
      </a>
    </li>

    <li class="menu-item <?php echo $open(['candidates','voters','votes','faculties','admins']); ?>">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx bx-cog"></i>
        <div>Manajemen</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item <?php echo $active('candidates'); ?>">
          <a href="<?php echo h($u('candidates')); ?>" class="menu-link">
            <div>Kelola Calon</div>
          </a>
        </li>
        <li class="menu-item <?php echo $active('voters'); ?>">
          <a href="<?php echo h($u('voters')); ?>" class="menu-link">
            <div>Kelola Pemilih</div>
          </a>
        </li>
        <li class="menu-item <?php echo $active('votes'); ?>">
          <a href="<?php echo h($u('votes')); ?>" class="menu-link">
            <div>Data Suara &amp; Audit</div>
          </a>
        </li>
        <li class="menu-item <?php echo $active('faculties'); ?>">
          <a href="<?php echo h($u('faculties')); ?>" class="menu-link">
            <div>Fakultas / TPS</div>
          </a>
        </li>
        <li class="menu-item <?php echo $active('admins'); ?>">
          <a href="<?php echo h($u('admins')); ?>" class="menu-link">
            <div>Akun Admin</div>
          </a>
        </li>
      </ul>
    </li>

    <li class="menu-item <?php echo $open(['election-settings','security','settings']); ?>">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx bx-wrench"></i>
        <div>Pengaturan</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item <?php echo $active('election-settings'); ?>">
          <a href="<?php echo h($u('election-settings')); ?>" class="menu-link">
            <div>Pengaturan Pemilu</div>
          </a>
        </li>
        <li class="menu-item <?php echo $active('security'); ?>">
          <a href="<?php echo h($u('security')); ?>" class="menu-link">
            <div>Keamanan &amp; Token</div>
          </a>
        </li>
        <li class="menu-item <?php echo $active('settings'); ?>">
          <a href="<?php echo h($u('settings')); ?>" class="menu-link">
            <div>Umum</div>
          </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>

    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">Akun</span>
    </li>

    <li class="menu-item <?php echo $active('profile'); ?>">
      <a href="<?php echo h($u('profile')); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-user"></i>
        <div>Profil</div>
      </a>
    </li>

    <li class="menu-item">
      <a href="logout.php" class="menu-link">
        <i class="menu-icon tf-icons bx bx-power-off"></i>
        <div>Logout</div>
      </a>
    </li>

  </ul>
</aside>
<!-- / Menu -->
