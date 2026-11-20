<?php
/**
 * ========================================================
 * Application Global Configuration & Setup
 * ========================================================
 */

// Zona Waktu Baku Indonesia
date_default_timezone_set('Asia/Jakarta');

// Start Session aman jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Nama & Versi Aplikasi
define('APP_NAME', 'Smart Parking System');
define('APP_SUBTITLE', 'Sistem Manajemen Parkir Modern & Terpadu');
define('APP_VERSION', '1.0.0');

/**
 * Mendeteksi base URL secara dinamis sesuai struktur folder
 * @return string
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Hitung relative path dari document root ke root direktori proyek
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    
    // Cari root direktori proyek
    // Jika kita berada di dalam subfolder seperti /modules/admin, pangkas path-nya
    $parts = explode('/', trim($scriptDir, '/'));
    $projectFolder = '';
    
    // Deteksi jika berada di root server (e.g. php -S localhost:8000) atau subfolder (e.g. localhost/proyek4)
    if (!empty($parts[0])) {
        if ($parts[0] === 'modules' || $parts[0] === 'config' || $parts[0] === 'functions') {
            $projectFolder = '';
        } else {
            $projectFolder = '/' . $parts[0];
        }
    }

    return rtrim($protocol . $host . $projectFolder, '/');
}

define('BASE_URL', get_base_url());
