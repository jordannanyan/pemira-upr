<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
admin_logout();
flash_set('info', 'Anda telah logout.');
header('Location: login.php');
exit;
