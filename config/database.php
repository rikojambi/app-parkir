<?php
/**
 * ========================================================
 * Database Configuration & Connection Provider (PDO)
 * Software Developer Company Best Practices
 * ========================================================
 */

defined('APP_INIT') or define('APP_INIT', true);

// Konfigurasi Database
$db_config = [
    'host'    => getenv('DB_HOST') ?: '127.0.0.1',
    'port'    => getenv('DB_PORT') ?: '3306',
    'dbname'  => getenv('DB_NAME') ?: 'db_parkir',
    'user'    => getenv('DB_USER') ?: 'root',
    'pass'    => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'charset' => 'utf8mb4'
];

/**
 * Mendapatkan instance koneksi PDO secara reusable (Singleton pattern)
 * @return PDO
 */
function get_db_connection() {
    static $pdo = null;
    global $db_config;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db_config['host'],
            $db_config['port'],
            $db_config['dbname'],
            $db_config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], $options);
        } catch (PDOException $e) {
            // Tampilkan pesan error ramah pengguna dan cegah kebocoran kredensial
            die("<strong>Koneksi Database Gagal:</strong> " . htmlspecialchars($e->getMessage()) . "<br><small>Pastikan MySQL aktif dan database <code>db_parkir</code> telah diimpor dari file <code>database.sql</code>.</small>");
        }
    }

    return $pdo;
}
