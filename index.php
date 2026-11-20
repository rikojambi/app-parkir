<?php
/**
 * ========================================================
 * Front Router / Dispatcher
 * ========================================================
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/functions/auth.php';

if (!is_logged_in()) {
    redirect(BASE_URL . '/modules/auth/login.php');
}

$user = get_logged_in_user();
switch ($user['role']) {
    case 'admin':
        redirect(BASE_URL . '/modules/admin/index.php');
        break;
    case 'petugas':
        redirect(BASE_URL . '/modules/petugas/index.php');
        break;
    case 'owner':
        redirect(BASE_URL . '/modules/owner/index.php');
        break;
    default:
        redirect(BASE_URL . '/modules/auth/login.php');
        break;
}
