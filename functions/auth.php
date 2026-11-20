<?php
/**
 * ========================================================
 * Modul Autentikasi & Hak Akses Pengguna (RBAC)
 * ========================================================
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/log.php';

/**
 * Validasi dan proses login pengguna
 * @param PDO $pdo
 * @param string $username
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'role' => string|null]
 */
function login_user($pdo, $username, $password) {
    $username = trim($username);
    $password = trim($password);

    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username dan password wajib diisi.'];
    }

    // Query efisien dengan LIMIT 1 dan index pada kolom username
    $stmt = $pdo->prepare("SELECT id_user, nama_lengkap, username, password, role, status_aktif FROM tb_user WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Username atau password tidak valid.'];
    }

    if ((int)$user['status_aktif'] !== 1) {
        return ['success' => false, 'message' => 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.'];
    }

    // Verifikasi hash password (bcrypt)
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Username atau password tidak valid.'];
    }

    // Regenerate session id untuk mencegah session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id_user'      => (int)$user['id_user'],
        'nama_lengkap' => $user['nama_lengkap'],
        'username'     => $user['username'],
        'role'         => $user['role']
    ];

    // Catat log aktivitas masuk sistem
    catat_log($pdo, (int)$user['id_user'], "Login ke sistem dengan role: " . $user['role']);

    return [
        'success' => true,
        'message' => 'Login berhasil!',
        'role'    => $user['role']
    ];
}

/**
 * Proses logout dan pembersihan session
 * @param PDO $pdo
 * @return void
 */
function logout_user($pdo) {
    if (is_logged_in()) {
        $user = get_logged_in_user();
        catat_log($pdo, $user['id_user'], "Logout dari sistem");
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Memeriksa apakah session user aktif
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id_user']);
}

/**
 * Mendapatkan data user yang sedang login
 * @return array|null
 */
function get_logged_in_user() {
    return is_logged_in() ? $_SESSION['user'] : null;
}

/**
 * Memastikan user sudah login, jika belum arahkan ke halaman login
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('danger', 'Sesi Anda telah berakhir. Silakan login kembali.');
        redirect(BASE_URL . '/modules/auth/login.php');
        exit;
    }
}

/**
 * Membatasi akses berdasarkan role tertentu (RBAC)
 * @param array|string $allowed_roles
 */
function require_role($allowed_roles) {
    require_login();
    $allowed = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    $current_role = $_SESSION['user']['role'];

    if (!in_array($current_role, $allowed)) {
        set_flash('danger', 'Akses ditolak! Anda tidak memiliki izin untuk mengakses halaman ini.');
        
        // Redirect ke dashboard yang sesuai
        if ($current_role === 'admin') {
            redirect(BASE_URL . '/modules/admin/index.php');
        } elseif ($current_role === 'petugas') {
            redirect(BASE_URL . '/modules/petugas/index.php');
        } elseif ($current_role === 'owner') {
            redirect(BASE_URL . '/modules/owner/index.php');
        } else {
            redirect(BASE_URL . '/modules/auth/login.php');
        }
        exit;
    }
}

/**
 * Utility redirect aman
 * @param string $url
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * CSRF Protection Token Generator
 * @return string
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF Token
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set pesan flash notifikasi
 * @param string $type ('success', 'danger', 'warning', 'info')
 * @param string $message
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Cek apakah ada pesan flash
 * @return bool
 */
function has_flash() {
    return isset($_SESSION['flash']);
}

/**
 * Ambil pesan flash lalu bersihkan dari session
 * @return array|null
 */
function get_flash() {
    if (has_flash()) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
