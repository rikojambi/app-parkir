<?php
/**
 * ========================================================
 * Component: Sidebar Navigation (Role-Based Menu)
 * ========================================================
 */
require_once __DIR__ . '/../functions/auth.php';
$user = get_logged_in_user();
$role = $user['role'] ?? '';

// Deteksi script aktif untuk highlight navigasi
$current_script = basename($_SERVER['SCRIPT_NAME']);
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));

function is_active($target_file, $target_dir = '') {
    global $current_script, $current_dir;
    if (!empty($target_dir)) {
        return ($current_script === $target_file && $current_dir === $target_dir);
    }
    return ($current_script === $target_file);
}
?>

<!-- Mobile Sidebar Backdrop Overlay -->
<div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden"></div>

<!-- Sidebar Container -->
<aside id="app-sidebar" class="no-print fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 flex flex-col transition-transform duration-300 transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0 border-r border-slate-800">
    <!-- Brand Logo in Sidebar -->
    <div class="h-16 flex items-center px-6 border-b border-slate-800 space-x-3 bg-slate-950/50">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-400 flex items-center justify-center text-white font-black text-lg shadow-lg shadow-indigo-500/20">
            <i class="fa-solid fa-square-parking"></i>
        </div>
        <div>
            <span class="text-white font-extrabold text-base tracking-wide">PARKIR PRO</span>
            <span class="block text-[10px] text-indigo-400 font-semibold uppercase tracking-wider">Enterprise v<?= APP_VERSION ?></span>
        </div>
    </div>

    <!-- Navigation Items -->
    <div class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
        
        <?php if ($role === 'admin'): ?>
            <!-- Menu Khusus Admin -->
            <div>
                <p class="px-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Menu Utama</p>
                <div class="space-y-1">
                    <a href="<?= BASE_URL ?>/modules/admin/index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('index.php', 'admin') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-chart-pie w-6 text-base"></i>
                        <span>Dashboard Admin</span>
                    </a>
                </div>
            </div>

            <div>
                <p class="px-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Master Data</p>
                <div class="space-y-1">
                    <a href="<?= BASE_URL ?>/modules/admin/users.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('users.php') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-users-gear w-6 text-base"></i>
                        <span>Manajemen User</span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/admin/tarif.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('tarif.php') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-tags w-6 text-base"></i>
                        <span>Tarif Parkir</span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/admin/area.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('area.php') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-map-location-dot w-6 text-base"></i>
                        <span>Area Parkir</span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/admin/kendaraan.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('kendaraan.php') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-car-side w-6 text-base"></i>
                        <span>Data Kendaraan</span>
                    </a>
                </div>
            </div>

            <div>
                <p class="px-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Audit & Keamanan</p>
                <div class="space-y-1">
                    <a href="<?= BASE_URL ?>/modules/admin/log.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('log.php') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-clock-rotate-left w-6 text-base"></i>
                        <span>Log Aktivitas</span>
                    </a>
                </div>
            </div>

        <?php elseif ($role === 'petugas'): ?>
            <!-- Menu Khusus Petugas -->
            <div>
                <p class="px-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Operasional</p>
                <div class="space-y-1">
                    <a href="<?= BASE_URL ?>/modules/petugas/index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('index.php', 'petugas') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-gauge-high w-6 text-base"></i>
                        <span>Dashboard Petugas</span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/petugas/transaksi.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('transaksi.php') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-receipt w-6 text-base"></i>
                        <span>Semua Transaksi</span>
                    </a>
                </div>
            </div>

            <div>
                <p class="px-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Pintu Masuk & Keluar</p>
                <div class="space-y-1">
                    <a href="<?= BASE_URL ?>/modules/petugas/masuk.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('masuk.php') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-right-to-bracket w-6 text-base text-emerald-400"></i>
                        <span>Check-In (Masuk)</span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/petugas/keluar.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('keluar.php') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-right-from-bracket w-6 text-base text-rose-400"></i>
                        <span>Check-Out (Keluar)</span>
                    </a>
                </div>
            </div>

        <?php elseif ($role === 'owner'): ?>
            <!-- Menu Khusus Owner -->
            <div>
                <p class="px-3 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Eksekutif</p>
                <div class="space-y-1">
                    <a href="<?= BASE_URL ?>/modules/owner/index.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('index.php', 'owner') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-chart-line w-6 text-base"></i>
                        <span>Dashboard Owner</span>
                    </a>
                    <a href="<?= BASE_URL ?>/modules/owner/laporan.php" class="flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all <?= is_active('laporan.php') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'hover:bg-slate-800/80 hover:text-white' ?>">
                        <i class="fa-solid fa-file-invoice-dollar w-6 text-base"></i>
                        <span>Rekapitulasi Laporan</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- User Badge in Sidebar Bottom -->
    <div class="p-4 border-t border-slate-800 bg-slate-950/40">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3 overflow-hidden">
                <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-slate-300 font-bold text-xs shrink-0">
                    <?= substr($user['nama_lengkap'] ?? 'U', 0, 1) ?>
                </div>
                <div class="truncate">
                    <p class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($user['nama_lengkap'] ?? '') ?></p>
                    <p class="text-[10px] text-slate-400 truncate">@<?= htmlspecialchars($user['username'] ?? '') ?></p>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="text-slate-400 hover:text-rose-400 p-1.5 transition-colors" title="Logout">
                <i class="fa-solid fa-power-off text-sm"></i>
            </a>
        </div>
    </div>
</aside>
