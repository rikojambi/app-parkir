<?php
/**
 * ========================================================
 * Modul Autentikasi: Logout
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';

$pdo = get_db_connection();
logout_user($pdo);

set_flash('success', 'Anda telah berhasil keluar dari sistem.');
redirect(BASE_URL . '/modules/auth/login.php');
