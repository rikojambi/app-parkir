<?php
/**
 * ========================================================
 * Modul Autentikasi: Login Terpusat
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';

$pdo = get_db_connection();

// Jika sudah login, langsung arahkan ke dashboard masing-masing
if (is_logged_in()) {
    $user = get_logged_in_user();
    if ($user['role'] === 'admin') redirect(BASE_URL . '/modules/admin/index.php');
    if ($user['role'] === 'petugas') redirect(BASE_URL . '/modules/petugas/index.php');
    if ($user['role'] === 'owner') redirect(BASE_URL . '/modules/owner/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Validasi keamanan CSRF gagal. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $result = login_user($pdo, $username, $password);
        if ($result['success']) {
            set_flash('success', 'Selamat datang kembali, ' . htmlspecialchars($_SESSION['user']['nama_lengkap']) . '!');
            if ($result['role'] === 'admin') {
                redirect(BASE_URL . '/modules/admin/index.php');
            } elseif ($result['role'] === 'petugas') {
                redirect(BASE_URL . '/modules/petugas/index.php');
            } elseif ($result['role'] === 'owner') {
                redirect(BASE_URL . '/modules/owner/index.php');
            }
        } else {
            $error = $result['message'];
        }
    }
}

$page_title = 'Login Sistem Parkir';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="min-h-screen bg-slate-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Background Decorative Gradient Glow -->
    <div class="absolute -top-40 -right-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10 px-4">
        <!-- Logo & Title -->
        <div class="text-center">
            <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-500 items-center justify-center text-white shadow-xl shadow-indigo-500/30 mb-4">
                <i class="fa-solid fa-square-parking text-3xl"></i>
            </div>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Smart Parking System</h2>
            <p class="mt-2 text-sm text-slate-400">Silakan masuk menggunakan kredensial Anda</p>
        </div>

        <!-- Login Card -->
        <div class="mt-8 bg-slate-800/90 backdrop-blur border border-slate-700/80 py-8 px-6 shadow-2xl rounded-2xl sm:px-10">
            <?php if (!empty($error)): ?>
                <div class="mb-5 flex items-center space-x-3 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
                    <i class="fa-solid fa-circle-exclamation text-rose-400 text-base"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

            <form class="space-y-5" action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div>
                    <label for="username" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Username
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-user text-sm"></i>
                        </div>
                        <input id="username" name="username" type="text" autocomplete="username" required
                            class="block w-full pl-10 pr-4 py-3 bg-slate-900/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                            placeholder="Masukkan username" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            class="block w-full pl-10 pr-4 py-3 bg-slate-900/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                            placeholder="Masukkan password">
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-xl shadow-lg shadow-indigo-600/30 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-900 focus:ring-indigo-500 transition-all">
                        <i class="fa-solid fa-right-to-bracket mr-2"></i> Masuk ke Sistem
                    </button>
                </div>
            </form>

            <!-- Quick Demo Credentials Box -->
            <div class="mt-6 pt-6 border-t border-slate-700/60">
                <p class="text-xs text-slate-400 font-semibold mb-2 flex items-center justify-between">
                    <span><i class="fa-solid fa-key mr-1.5 text-amber-400"></i> Akun Pengujian Default:</span>
                    <span class="text-[10px] text-slate-500">(Pass: password123)</span>
                </p>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" onclick="fillCreds('admin')" class="px-2 py-1.5 bg-purple-950/60 hover:bg-purple-900/80 border border-purple-800/40 rounded-lg text-purple-200 text-xs font-medium text-center transition-colors">
                        Admin
                    </button>
                    <button type="button" onclick="fillCreds('petugas')" class="px-2 py-1.5 bg-blue-950/60 hover:bg-blue-900/80 border border-blue-800/40 rounded-lg text-blue-200 text-xs font-medium text-center transition-colors">
                        Petugas
                    </button>
                    <button type="button" onclick="fillCreds('owner')" class="px-2 py-1.5 bg-emerald-950/60 hover:bg-emerald-900/80 border border-emerald-800/40 rounded-lg text-emerald-200 text-xs font-medium text-center transition-colors">
                        Owner
                    </button>
                </div>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-slate-500">
            &copy; <?= date('Y') ?> Software Development Company. Seluruh Hak Cipta Dilindungi.
        </p>
    </div>
</div>

<script>
function fillCreds(role) {
    document.getElementById('username').value = role;
    document.getElementById('password').value = 'password123';
}
</script>

</body>
</html>
