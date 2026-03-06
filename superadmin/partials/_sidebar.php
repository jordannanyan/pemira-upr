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
        <svg width="25" viewBox="0 0 25 42" version="1.1" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <path d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z" id="path-1"></path>
          </defs>
          <use fill="#696cff" href="#path-1"></use>
        </svg>
      </span>
      <span class="app-brand-text demo menu-text fw-bolder ms-2">PEMIRA UPR</span>
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

    <li class="menu-item <?php echo $active('faculty-recap'); ?>">
      <a href="<?php echo h($u('faculty-recap')); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
        <div>Rekap Fakultas</div>
      </a>
    </li>
    <?php endif; ?>

    <!-- SUPERADMIN MENU -->
    <?php if ($role === 'superadmin'): ?>
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
