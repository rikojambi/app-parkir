<?php
/**
 * ========================================================
 * Modul Admin: Dashboard Utama
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/parkir.php';

// Pastikan hanya role admin yang boleh mengakses
require_role('admin');

$pdo = get_db_connection();

// 1. Query Efisien: Hitung Total Pengguna
$stmt_users = $pdo->query("SELECT COUNT(*) FROM tb_user");
$total_users = (int)$stmt_users->fetchColumn();

// 2. Query Efisien: Hitung Kendaraan Sedang Parkir Aktif (Status = 'masuk')
$stmt_parkir = $pdo->query("SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk'");
$total_kendaraan_parkir = (int)$stmt_parkir->fetchColumn();

// 3. Query Efisien: Total Pendapatan Hari Ini (Aggregasi SQL SUM, tanpa looping PHP)
$stmt_income = $pdo->query("
    SELECT COALESCE(SUM(biaya_total), 0) 
    FROM tb_transaksi 
    WHERE status = 'keluar' AND DATE(waktu_keluar) = CURDATE()
");
$pendapatan_hari_ini = (float)$stmt_income->fetchColumn();

// 4. Query Efisien: Total Area & Kapasitas Keseluruhan
$stmt_area = $pdo->query("SELECT SUM(kapasitas) AS total_kapasitas, SUM(terisi) AS total_terisi FROM tb_area_parkir");
$area_stat = $stmt_area->fetch() ?: ['total_kapasitas' => 0, 'total_terisi' => 0];
$persen_terisi = $area_stat['total_kapasitas'] > 0 
    ? round(($area_stat['total_terisi'] / $area_stat['total_kapasitas']) * 100, 1) 
    : 0;

// 5. Query Efisien: Daftar Kapasitas Tiap Area
$stmt_list_area = $pdo->query("SELECT id_area, nama_area, kapasitas, terisi FROM tb_area_parkir ORDER BY id_area ASC");
$list_area = $stmt_list_area->fetchAll();

// 6. Query Efisien: Log Aktivitas Terbaru (Dengan LIMIT 6)
$stmt_recent_logs = $pdo->query("
    SELECT l.id_log, l.aktivitas, l.waktu_aktivitas, u.nama_lengkap, u.role
    FROM tb_log_aktivitas l
    JOIN tb_user u ON l.id_user = u.id_user
    ORDER BY l.waktu_aktivitas DESC
    LIMIT 6
");
$recent_logs = $stmt_recent_logs->fetchAll();

$page_title = 'Dashboard Administrator';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Top Navbar -->
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <!-- Content Body -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-indigo-700 via-indigo-600 to-purple-600 rounded-2xl p-6 text-white shadow-xl shadow-indigo-600/10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <span class="inline-block px-2.5 py-1 bg-white/20 backdrop-blur rounded-full text-xs font-semibold mb-2">Panel Kontrol Administrator</span>
                        <h2 class="text-2xl font-extrabold tracking-tight">Selamat Datang, <?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?>!</h2>
                        <p class="text-indigo-100 text-xs sm:text-sm mt-1">Pantau seluruh operasional perparkiran, manajemen akses, dan log aktivitas real-time.</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <a href="<?= BASE_URL ?>/modules/admin/users.php" class="px-4 py-2 bg-white text-indigo-700 text-xs font-bold rounded-xl shadow hover:bg-indigo-50 transition-colors">
                            <i class="fa-solid fa-user-plus mr-1.5"></i> Tambah User
                        </a>
                        <a href="<?= BASE_URL ?>/modules/admin/log.php" class="px-4 py-2 bg-indigo-800/80 text-white text-xs font-bold rounded-xl border border-indigo-500/40 hover:bg-indigo-900 transition-colors">
                            <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Audit Log
                        </a>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Key Performance Indicators (Cards) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <!-- Total Pengguna -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total User</p>
                            <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($total_users) ?></h3>
                            <p class="text-[11px] text-slate-400 mt-1">Admin, Petugas, Owner</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>

                    <!-- Kendaraan Aktif Terparkir -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Parkir Aktif</p>
                            <h3 class="text-2xl font-bold text-indigo-600 mt-1"><?= number_format($total_kendaraan_parkir) ?></h3>
                            <p class="text-[11px] text-slate-400 mt-1">Kendaraan saat ini</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-car-side"></i>
                        </div>
                    </div>

                    <!-- Okupansi Kapasitas -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Okupansi Slot</p>
                            <h3 class="text-2xl font-bold text-amber-600 mt-1"><?= $persen_terisi ?>%</h3>
                            <p class="text-[11px] text-slate-400 mt-1"><?= $area_stat['total_terisi'] ?> dari <?= $area_stat['total_kapasitas'] ?> slot</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                    </div>

                    <!-- Pendapatan Hari Ini -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pendapatan Hari Ini</p>
                            <h3 class="text-xl font-bold text-emerald-600 mt-1"><?= format_rupiah($pendapatan_hari_ini) ?></h3>
                            <p class="text-[11px] text-slate-400 mt-1">Transaksi keluar hari ini</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout: Area Parking Monitor & Recent Log Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Monitoring Kapasitas Tiap Area -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Status Kapasitas Area Parkir</h3>
                                <p class="text-xs text-slate-500">Ketersediaan ruang parkir real-time</p>
                            </div>
                            <a href="<?= BASE_URL ?>/modules/admin/area.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                Kelola Area <i class="fa-solid fa-arrow-right ml-1"></i>
                            </a>
                        </div>

                        <div class="space-y-4">
                            <?php foreach ($list_area as $area): 
                                $pct = $area['kapasitas'] > 0 ? round(($area['terisi'] / $area['kapasitas']) * 100) : 0;
                                $barColor = 'bg-emerald-500';
                                if ($pct >= 90) $barColor = 'bg-rose-500';
                                elseif ($pct >= 70) $barColor = 'bg-amber-500';
                            ?>
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="flex justify-between items-center text-xs font-semibold mb-1.5">
                                        <span class="text-slate-800 flex items-center">
                                            <i class="fa-solid fa-location-dot text-slate-400 mr-2"></i>
                                            <?= htmlspecialchars($area['nama_area']) ?>
                                        </span>
                                        <span class="text-slate-600">
                                            <span class="font-bold text-slate-900"><?= $area['terisi'] ?></span> / <?= $area['kapasitas'] ?> Slot 
                                            <span class="ml-1 text-[11px] font-bold text-slate-500">(<?= $pct ?>%)</span>
                                        </span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                                        <div class="<?= $barColor ?> h-2.5 rounded-full transition-all duration-500" style="width: <?= min(100, $pct) ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Audit Logs -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Log Aktivitas Terkini</h3>
                                <p class="text-xs text-slate-500">Jejak audit otomatis sistem</p>
                            </div>
                            <a href="<?= BASE_URL ?>/modules/admin/log.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                Lihat Semua <i class="fa-solid fa-arrow-right ml-1"></i>
                            </a>
                        </div>

                        <div class="space-y-3">
                            <?php if (empty($recent_logs)): ?>
                                <p class="text-xs text-slate-400 text-center py-6">Belum ada riwayat aktivitas yang tercatat.</p>
                            <?php else: ?>
                                <?php foreach ($recent_logs as $log): ?>
                                    <div class="flex items-start space-x-3 text-xs p-2.5 rounded-xl hover:bg-slate-50 transition-colors border border-transparent hover:border-slate-100">
                                        <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 mt-0.5">
                                            <i class="fa-solid fa-fingerprint text-xs"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-slate-800 truncate"><?= htmlspecialchars($log['aktivitas']) ?></p>
                                            <div class="flex items-center space-x-2 mt-0.5 text-[11px] text-slate-400">
                                                <span class="font-semibold text-slate-600"><?= htmlspecialchars($log['nama_lengkap']) ?></span>
                                                <span>&bull;</span>
                                                <span class="uppercase font-bold text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-600"><?= htmlspecialchars($log['role']) ?></span>
                                                <span>&bull;</span>
                                                <span><?= format_tanggal_indo($log['waktu_aktivitas'], true) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        </main>

        <!-- Global Footer -->
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
