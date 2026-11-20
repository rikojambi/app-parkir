<?php
/**
 * ========================================================
 * Modul Owner: Dashboard Eksekutif & Analisis Finansial
 * Best practice: Query agregasi SQL langsung tanpa looping PHP
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/parkir.php';

require_role('owner');

$pdo = get_db_connection();
$current_user = get_logged_in_user();

// 1. Total Pendapatan Seluruh Waktu (All-Time Revenue)
$stmt_all = $pdo->query("SELECT COALESCE(SUM(biaya_total), 0) FROM tb_transaksi WHERE status = 'keluar'");
$revenue_all = (float)$stmt_all->fetchColumn();

// 2. Total Pendapatan Bulan Ini
$stmt_month = $pdo->query("
    SELECT COALESCE(SUM(biaya_total), 0) 
    FROM tb_transaksi 
    WHERE status = 'keluar' 
      AND MONTH(waktu_keluar) = MONTH(CURRENT_DATE())
      AND YEAR(waktu_keluar) = YEAR(CURRENT_DATE())
");
$revenue_month = (float)$stmt_month->fetchColumn();

// 3. Total Pendapatan Hari Ini
$stmt_today = $pdo->query("
    SELECT COALESCE(SUM(biaya_total), 0) 
    FROM tb_transaksi 
    WHERE status = 'keluar' AND DATE(waktu_keluar) = CURDATE()
");
$revenue_today = (float)$stmt_today->fetchColumn();

// 4. Total Transaksi Selesai & Aktif
$stmt_trans_stats = $pdo->query("
    SELECT 
        COUNT(*) AS total_transaksi,
        SUM(CASE WHEN status = 'keluar' THEN 1 ELSE 0 END) AS transaksi_selesai,
        SUM(CASE WHEN status = 'masuk' THEN 1 ELSE 0 END) AS parkir_aktif
    FROM tb_transaksi
");
$trans_stats = $stmt_trans_stats->fetch() ?: ['total_transaksi' => 0, 'transaksi_selesai' => 0, 'parkir_aktif' => 0];

// 5. Agregasi Pendapatan per Jenis Kendaraan (Langsung dari SQL GROUP BY, efisien & zero looping overhead)
$stmt_breakdown = $pdo->query("
    SELECT 
        k.jenis_kendaraan,
        COUNT(t.id_parkir) AS total_unit,
        COALESCE(SUM(t.biaya_total), 0) AS total_pendapatan,
        COALESCE(AVG(t.durasi_jam), 0) AS rata_durasi
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    WHERE t.status = 'keluar'
    GROUP BY k.jenis_kendaraan
    ORDER BY total_pendapatan DESC
");
$revenue_breakdown = $stmt_breakdown->fetchAll();

// 6. Transaksi Terbaru Selesai (LIMIT 5)
$stmt_recent_completed = $pdo->query("
    SELECT t.id_parkir, t.waktu_masuk, t.waktu_keluar, t.durasi_jam, t.biaya_total,
           k.plat_nomor, k.jenis_kendaraan, a.nama_area
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    WHERE t.status = 'keluar'
    ORDER BY t.waktu_keluar DESC
    LIMIT 5
");
$recent_completed = $stmt_recent_completed->fetchAll();

$page_title = 'Dashboard Pemilik (Owner)';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                
                <!-- Executive Banner -->
                <div class="bg-gradient-to-r from-emerald-800 via-teal-800 to-slate-900 rounded-2xl p-6 text-white shadow-xl shadow-teal-900/10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <span class="inline-block px-2.5 py-1 bg-white/10 backdrop-blur rounded-full text-xs font-semibold mb-2">Executive Business Overview</span>
                        <h2 class="text-2xl font-extrabold tracking-tight">Selamat Datang, Bapak/Ibu <?= htmlspecialchars($current_user['nama_lengkap']) ?></h2>
                        <p class="text-teal-100 text-xs sm:text-sm mt-1">Laporan kinerja finansial, okupansi lahan parkir, dan rekapitulasi pendapatan terkini.</p>
                    </div>
                    <div>
                        <a href="<?= BASE_URL ?>/modules/owner/laporan.php" class="px-5 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-500/20 transition-all flex items-center">
                            <i class="fa-solid fa-file-invoice-dollar mr-2 text-sm"></i> Buka Rekapitulasi Laporan
                        </a>
                    </div>
                </div>

                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Financial KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    
                    <!-- Pendapatan Hari Ini -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pendapatan Hari Ini</p>
                            <h3 class="text-xl font-black text-slate-900 mt-1"><?= format_rupiah($revenue_today) ?></h3>
                            <p class="text-[11px] text-emerald-600 font-semibold mt-1">
                                <i class="fa-solid fa-calendar-day mr-1"></i> Per <?= date('d M Y') ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                    </div>

                    <!-- Pendapatan Bulan Ini -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pendapatan Bulan Ini</p>
                            <h3 class="text-xl font-black text-emerald-600 mt-1"><?= format_rupiah($revenue_month) ?></h3>
                            <p class="text-[11px] text-slate-400 mt-1">Bulan <?= date('F Y') ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                    </div>

                    <!-- Total Pendapatan Akumulasi -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Pendapatan (All-Time)</p>
                            <h3 class="text-xl font-black text-indigo-600 mt-1"><?= format_rupiah($revenue_all) ?></h3>
                            <p class="text-[11px] text-slate-400 mt-1">Seluruh akumulasi transaksi</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-vault"></i>
                        </div>
                    </div>

                    <!-- Total Transaksi Selesai -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Transaksi</p>
                            <h3 class="text-2xl font-black text-slate-900 mt-1"><?= number_format($trans_stats['transaksi_selesai']) ?></h3>
                            <p class="text-[11px] text-slate-400 mt-1"><?= $trans_stats['parkir_aktif'] ?> kendaraan masih aktif</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                    </div>

                </div>

                <!-- Section: Revenue Breakdown by Vehicle Type & Recent Completed Transactions -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Breakdown Pendapatan per Jenis Kendaraan -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Distribusi Pendapatan Kendaraan</h3>
                                <p class="text-xs text-slate-500">Berdasarkan kategori klasifikasi tarif</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <?php if (empty($revenue_breakdown)): ?>
                                <p class="text-xs text-slate-400 text-center py-8">Belum ada data transaksi yang selesai.</p>
                            <?php else: ?>
                                <?php foreach ($revenue_breakdown as $rb): 
                                    $pctRevenue = $revenue_all > 0 ? round(($rb['total_pendapatan'] / $revenue_all) * 100, 1) : 0;
                                ?>
                                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                                <?= htmlspecialchars($rb['jenis_kendaraan']) ?>
                                                <span class="text-slate-400 font-normal lowercase">(<?= $rb['total_unit'] ?> unit)</span>
                                            </span>
                                            <span class="text-xs font-extrabold text-slate-900"><?= format_rupiah($rb['total_pendapatan']) ?></span>
                                        </div>
                                        <div class="flex justify-between text-[11px] text-slate-400 mb-1.5">
                                            <span>Rata-rata durasi: <?= round($rb['rata_durasi'], 1) ?> Jam</span>
                                            <span>Kontribusi: <?= $pctRevenue ?>%</span>
                                        </div>
                                        <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= min(100, $pctRevenue) ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Transaksi Keluar Terbaru -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Transaksi Selesai Terkini</h3>
                                <p class="text-xs text-slate-500">Pemasukan kas parkir terbaru</p>
                            </div>
                            <a href="<?= BASE_URL ?>/modules/owner/laporan.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-800">
                                Buka Rekapitulasi <i class="fa-solid fa-arrow-right ml-1"></i>
                            </a>
                        </div>

                        <div class="space-y-3">
                            <?php if (empty($recent_completed)): ?>
                                <p class="text-xs text-slate-400 text-center py-8">Belum ada transaksi keluar yang tercatat.</p>
                            <?php else: ?>
                                <?php foreach ($recent_completed as $rc): ?>
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-bold">
                                                <i class="fa-solid fa-check"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-mono-code font-bold text-slate-900"><?= htmlspecialchars($rc['plat_nomor']) ?></p>
                                                <p class="text-[11px] text-slate-500">
                                                    <?= htmlspecialchars($rc['nama_area']) ?> &bull; <?= $rc['durasi_jam'] ?> Jam &bull; <?= date('H:i', strtotime($rc['waktu_keluar'])) ?> WIB
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs font-extrabold text-emerald-700"><?= format_rupiah($rc['biaya_total']) ?></p>
                                            <p class="text-[10px] text-slate-400">#<?= $rc['id_parkir'] ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
