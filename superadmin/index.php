<?php
declare(strict_types=1);
ob_start();
require_once __DIR__ . '/../config/auth.php';
require_admin_login('login.php');
$admin = admin_me();
$role  = admin_role();
?>
<!DOCTYPE html>
<html
  lang="id"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>Panel Admin - PEMIRA UPR</title>

    <meta name="description" content="Panel Administrasi PEMIRA UPR" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon/favicon.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/vendor/libs/apex-charts/apexcharts.js"></script>


    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="assets/js/config.js"></script>
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        <?php
        include_once('partials/_sidebar.php')
          ?>

        <!-- Layout container -->
        <div class="layout-page">
          <?php
          include_once('partials/_navbar.php')
            ?>
          <!-- Content wrapper -->
          <div class="content-wrapper">

          <?php
          // =============================
          // Dynamic page loader via ?p=...
          // =============================

          // Default page
          $page = $_GET['p'] ?? 'dashboard';
          $page = trim($page);

          // Normalize: remove leading/trailing slashes
          $page = trim($page, "/");

          // Allow only safe characters (letters, numbers, underscore, dash, slash)
          if ($page === '' || !preg_match('/^[a-zA-Z0-9_\-\/]+$/', $page)) {
            $page = 'dashboard';
          }

          // Build target file path
          $pagesDir = __DIR__ . DIRECTORY_SEPARATOR . 'pages';
          $target   = $pagesDir . DIRECTORY_SEPARATOR . $page . '.php';

          // Resolve real paths to prevent traversal
          $pagesDirReal = realpath($pagesDir);
          $targetReal   = realpath($target);

          // If target exists and is inside /pages, include it. Else show 404.
          if ($targetReal && $pagesDirReal && strpos($targetReal, $pagesDirReal) === 0 && is_file($targetReal)) {
            include_once $targetReal;
          } else {
            // Prefer a custom 404 page if available
            $notFound = $pagesDir . DIRECTORY_SEPARATOR . '404.php';
            if (is_file($notFound)) {
              include_once $notFound;
            } else {
              http_response_code(404);
              echo "<div class='container-xxl flex-grow-1 container-p-y'>";
              echo "  <div class='card'><div class='card-body'>";
              echo "    <h4 class='mb-2'>404 - Halaman tidak ditemukan</h4>";
              echo "    <p class='mb-0'>Page <b>" . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . "</b> tidak tersedia.</p>";
              echo "  </div></div>";
              echo "</div>";
            }
          }
          ?>

          <?php
          include_once('partials/_footer.php')
            ?>

            <div class="content-backdrop fade"></div>
          </div>
          <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->
  
    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="assets/vendor/libs/jquery/jquery.js"></script>
    <script src="assets/vendor/libs/popper/popper.js"></script>
    <script src="assets/vendor/js/bootstrap.js"></script>
    <script src="assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="assets/vendor/js/menu.js"></script>
    <!-- endbuild -->


    <!-- Main JS -->
    <script src="assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="assets/js/dashboards-analytics.js"></script>

  </body>
</html>
<?php ob_end_flush(); ?>