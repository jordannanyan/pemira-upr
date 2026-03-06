<?php
// _navbar.php — pakai $admin dan $role dari index.php (session)
$page = trim($_GET['p'] ?? 'dashboard');
if ($page === '' || !preg_match('/^[a-zA-Z0-9_\-\/]+$/', $page)) $page = 'dashboard';

$adminName = h($admin['name'] ?? 'Admin');
$adminRole = $role === 'superadmin' ? 'Superadmin' : 'Admin Fakultas';
?>

<!-- Navbar -->
<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
            <i class="bx bx-menu bx-sm"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
        <ul class="navbar-nav flex-row align-items-center ms-auto">

            <!-- Role badge -->
            <li class="nav-item me-3">
                <span class="badge <?php echo $role === 'superadmin' ? 'bg-label-danger' : 'bg-label-primary'; ?> fs-6">
                    <i class="bx <?php echo $role === 'superadmin' ? 'bx-shield-quarter' : 'bx-user-check'; ?> me-1"></i>
                    <?php echo $adminRole; ?>
                </span>
            </li>

            <!-- User Dropdown -->
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                        <span class="avatar-initial rounded-circle bg-label-primary">
                            <i class="bx bx-user fs-4"></i>
                        </span>
                    </div>
                </a>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded-circle bg-label-primary"
                                          style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;">
                                        <i class="bx bx-user fs-4"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <span class="fw-semibold d-block"><?php echo $adminName; ?></span>
                                    <small class="text-muted"><?php echo $adminRole; ?></small>
                                </div>
                            </div>
                        </a>
                    </li>

                    <li><div class="dropdown-divider"></div></li>

                    <li>
                        <a class="dropdown-item" href="index.php?p=profile">
                            <i class="bx bx-user me-2"></i>
                            <span class="align-middle">Profil</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="index.php?p=settings">
                            <i class="bx bx-cog me-2"></i>
                            <span class="align-middle">Pengaturan</span>
                        </a>
                    </li>

                    <li><div class="dropdown-divider"></div></li>

                    <li>
                        <a class="dropdown-item" href="logout.php">
                            <i class="bx bx-power-off me-2"></i>
                            <span class="align-middle">Logout</span>
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
<!-- / Navbar -->
