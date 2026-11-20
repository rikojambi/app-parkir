<?php
/**
 * ========================================================
 * Modul Petugas: Dashboard Operasional
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/parkir.php';

require_role('petugas');

$pdo = get_db_connection();
$current_user = get_logged_in_user();

// 1. Total Parkir Aktif saat ini
$stmt_aktif = $pdo->query("SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk'");
$total_aktif = (int)$stmt_aktif->fetchColumn();

// 2. Transaksi Hari Ini oleh Petugas Ini
$stmt_trans_petugas = $pdo->prepare("
    SELECT COUNT(*) FROM tb_transaksi 
    WHERE id_user = :uid AND (DATE(waktu_masuk) = CURDATE() OR DATE(waktu_keluar) = CURDATE())
");
$stmt_trans_petugas->execute([':uid' => $current_user['id_user']]);
$total_trans_petugas = (int)$stmt_trans_petugas->fetchColumn();

// 3. Status Okupansi per Area
$stmt_areas = $pdo->query("SELECT * FROM tb_area_parkir ORDER BY id_area ASC");
$areas = $stmt_areas->fetchAll();

// 4. Daftar Kendaraan Terparkir Terbaru (LIMIT 6)
$stmt_recent_parked = $pdo->query("
    SELECT t.id_parkir, t.waktu_masuk, k.plat_nomor, k.jenis_kendaraan, a.nama_area
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    WHERE t.status = 'masuk'
    ORDER BY t.waktu_masuk DESC
    LIMIT 6
");
$recent_parked = $stmt_recent_parked->fetchAll();

$page_title = 'Dashboard Petugas Operasional';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                
                <!-- Action Header Banner -->
                <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-indigo-800 rounded-2xl p-6 text-white shadow-xl shadow-blue-600/10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <span class="inline-block px-2.5 py-1 bg-white/20 backdrop-blur rounded-full text-xs font-semibold mb-2">Pintu Gerbang & Kasir Parkir</span>
                        <h2 class="text-2xl font-extrabold tracking-tight">Halo Petugas, <?= htmlspecialchars($current_user['nama_lengkap']) ?>!</h2>
                        <p class="text-blue-100 text-xs sm:text-sm mt-1">Lakukan pencatatan check-in kendaraan masuk dan validasi check-out pembayaran parkir.</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a href="<?= BASE_URL ?>/modules/petugas/masuk.php" class="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-500/20 transition-all flex items-center">
                            <i class="fa-solid fa-right-to-bracket mr-2 text-sm"></i> Check-In (Masuk)
                        </a>
                        <a href="<?= BASE_URL ?>/modules/petugas/keluar.php" class="px-4 py-2.5 bg-white text-indigo-700 hover:bg-blue-50 text-xs font-bold rounded-xl shadow transition-all flex items-center">
                            <i class="fa-solid fa-right-from-bracket mr-2 text-sm"></i> Check-Out (Keluar)
                        </a>
                    </div>
                </div>

                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Status Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kendaraan Terparkir Saat Ini</p>
                            <h3 class="text-3xl font-extrabold text-indigo-600 mt-1"><?= number_format($total_aktif) ?></h3>
                            <p class="text-[11px] text-slate-400 mt-1">Menempati slot parkir yang sedang aktif</p>
                        </div>
                        <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl shadow-inner">
                            <i class="fa-solid fa-car-tunnel"></i>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Operasional Anda Hari Ini</p>
                            <h3 class="text-3xl font-extrabold text-blue-600 mt-1"><?= number_format($total_trans_petugas) ?></h3>
                            <p class="text-[11px] text-slate-400 mt-1">Total transaksi masuk/keluar yang ditangani</p>
                        </div>
                        <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl shadow-inner">
                            <i class="fa-solid fa-clipboard-user"></i>
                        </div>
                    </div>
                </div>

                <!-- Two Column Section: Real-time Quota by Area & Recent Active Parked -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Kapasitas Area Real-Time -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Ketersediaan Slot Area Parkir</h3>
                                <p class="text-xs text-slate-500">Kapasitas diperiksa otomatis sebelum check-in</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <?php foreach ($areas as $a): 
                                $sisa = max(0, $a['kapasitas'] - $a['terisi']);
                                $pct = $a['kapasitas'] > 0 ? round(($a['terisi'] / $a['kapasitas']) * 100) : 0;
                                $isFull = ($a['terisi'] >= $a['kapasitas']);
                                $barColor = $isFull ? 'bg-rose-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-emerald-500');
                            ?>
                                <div class="p-3.5 rounded-xl border <?= $isFull ? 'bg-rose-50/50 border-rose-200' : 'bg-slate-50 border-slate-100' ?>">
                                    <div class="flex justify-between items-center mb-1.5">
                                        <span class="text-xs font-bold text-slate-800 flex items-center">
                                            <i class="fa-solid fa-map-pin text-indigo-500 mr-2"></i>
                                            <?= htmlspecialchars($a['nama_area']) ?>
                                        </span>
                                        <div class="text-xs">
                                            <?php if ($isFull): ?>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 uppercase">PENUH</span>
                                            <?php else: ?>
                                                <span class="text-emerald-600 font-bold">Sisa: <?= $sisa ?></span> / <?= $a['kapasitas'] ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                        <div class="<?= $barColor ?> h-2 rounded-full transition-all duration-300" style="width: <?= min(100, $pct) ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Kendaraan Sedang Parkir Terbaru -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Kendaraan Baru Masuk</h3>
                                <p class="text-xs text-slate-500">Antrean parkir aktif</p>
                            </div>
                            <a href="<?= BASE_URL ?>/modules/petugas/transaksi.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                                Lihat Semua <i class="fa-solid fa-arrow-right ml-1"></i>
                            </a>
                        </div>

                        <div class="space-y-3">
                            <?php if (empty($recent_parked)): ?>
                                <p class="text-xs text-slate-400 text-center py-8">Tidak ada kendaraan yang sedang parkir saat ini.</p>
                            <?php else: ?>
                                <?php foreach ($recent_parked as $rp): ?>
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-9 h-9 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-bold">
                                                <i class="fa-solid <?= $rp['jenis_kendaraan'] === 'motor' ? 'fa-motorcycle' : 'fa-car' ?>"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-mono-code font-bold text-slate-900"><?= htmlspecialchars($rp['plat_nomor']) ?></p>
                                                <p class="text-[11px] text-slate-500"><?= htmlspecialchars($rp['nama_area']) ?> &bull; <?= date('H:i', strtotime($rp['waktu_masuk'])) ?> WIB</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <a href="<?= BASE_URL ?>/modules/petugas/cetak.php?id=<?= $rp['id_parkir'] ?>&type=masuk" target="_blank" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg text-xs" title="Cetak Karcis Masuk">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/modules/petugas/keluar.php?id=<?= $rp['id_parkir'] ?>" class="px-2.5 py-1 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-xs font-bold transition-colors">
                                                Proses Keluar
                                            </a>
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
