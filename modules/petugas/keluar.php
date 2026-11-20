<?php
/**
 * ========================================================
 * Modul Petugas: Transaksi Keluar (Check-Out Kendaraan)
 * Menjalankan kalkulasi durasi & tarif otomatis,
 * decrement terisi tb_area_parkir, dan audit log
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/log.php';
require_once __DIR__ . '/../../functions/parkir.php';

require_role('petugas');

$pdo = get_db_connection();
$current_user = get_logged_in_user();

$search_plat = trim($_GET['plat'] ?? '');
$id_parkir_param = (int)($_GET['id'] ?? 0);
$selected_transaksi = null;
$kalkulasi = null;
$error = '';

// Cari transaksi aktif berdasarkan id atau plat nomor
if ($id_parkir_param > 0) {
    $stmt = $pdo->prepare("
        SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
               a.nama_area, tf.tarif_per_jam, tf.jenis_kendaraan AS tarif_jenis
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        JOIN tb_tarif tf ON t.id_tarif = tf.id_tarif
        WHERE t.id_parkir = :id AND t.status = 'masuk'
        LIMIT 1
    ");
    $stmt->execute([':id' => $id_parkir_param]);
    $selected_transaksi = $stmt->fetch();
} elseif (!empty($search_plat)) {
    $stmt = $pdo->prepare("
        SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
               a.nama_area, tf.tarif_per_jam, tf.jenis_kendaraan AS tarif_jenis
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
        JOIN tb_area_parkir a ON t.id_area = a.id_area
        JOIN tb_tarif tf ON t.id_tarif = tf.id_tarif
        WHERE (k.plat_nomor = :plat OR t.id_parkir = :id_alt) AND t.status = 'masuk'
        ORDER BY t.id_parkir DESC
        LIMIT 1
    ");
    $stmt->execute([':plat' => strtoupper($search_plat), ':id_alt' => (int)$search_plat]);
    $selected_transaksi = $stmt->fetch();
    
    if (!$selected_transaksi) {
        $error = "Kendaraan aktif dengan nomor plat/tiket '$search_plat' tidak ditemukan atau sudah check-out.";
    }
}

// Jika transaksi ditemukan, lakukan kalkulasi biaya otomatis real-time
if ($selected_transaksi) {
    $waktu_keluar_sekarang = date('Y-m-d H:i:s');
    $kalkulasi = hitung_durasi_dan_biaya(
        $selected_transaksi['waktu_masuk'],
        $waktu_keluar_sekarang,
        $selected_transaksi['tarif_per_jam']
    );
}

// --------------------------------------------------------
// PROSES KONFIRMASI CHECK-OUT KENDARAAN
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_confirm_checkout'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan CSRF gagal. Silakan coba lagi.';
    } else {
        $post_id_parkir = (int)($_POST['id_parkir'] ?? 0);
        $waktu_keluar_final = date('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            // Lock record transaksi aktif
            $stmt_check = $pdo->prepare("
                SELECT t.*, k.plat_nomor, tf.tarif_per_jam
                FROM tb_transaksi t
                JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
                JOIN tb_tarif tf ON t.id_tarif = tf.id_tarif
                WHERE t.id_parkir = :id AND t.status = 'masuk'
                FOR UPDATE
            ");
            $stmt_check->execute([':id' => $post_id_parkir]);
            $trans = $stmt_check->fetch();

            if (!$trans) {
                $pdo->rollBack();
                $error = 'Transaksi tidak valid atau kendaraan sudah diproses keluar sebelumnya.';
            } else {
                // LOGIKA KHUSUS: Hitung durasi_jam dari selisih waktu_keluar dan waktu_masuk,
                // kemudian kalikan dengan tarif_per_jam untuk mendapatkan biaya_total
                $calc = hitung_durasi_dan_biaya(
                    $trans['waktu_masuk'],
                    $waktu_keluar_final,
                    $trans['tarif_per_jam']
                );

                $durasi_jam_final  = $calc['durasi_jam'];
                $biaya_total_final = $calc['biaya_total'];

                // Update tb_transaksi
                $stmt_update_trans = $pdo->prepare("
                    UPDATE tb_transaksi 
                    SET waktu_keluar = :waktu_keluar,
                        durasi_jam = :durasi_jam,
                        biaya_total = :biaya_total,
                        status = 'keluar'
                    WHERE id_parkir = :id_parkir
                ");
                $stmt_update_trans->execute([
                    ':waktu_keluar' => $waktu_keluar_final,
                    ':durasi_jam'   => $durasi_jam_final,
                    ':biaya_total'  => $biaya_total_final,
                    ':id_parkir'    => $post_id_parkir
                ]);

                // LOGIKA KHUSUS: Saat transaksi keluar, kurangi angka terisi pada tb_area_parkir
                $stmt_dec_area = $pdo->prepare("
                    UPDATE tb_area_parkir 
                    SET terisi = GREATEST(0, terisi - 1) 
                    WHERE id_area = :id_area
                ");
                $stmt_dec_area->execute([':id_area' => $trans['id_area']]);

                // LOGIKA KHUSUS: Catat log aktivitas transaksi keluar
                catat_log(
                    $pdo, 
                    $current_user['id_user'], 
                    "Transaksi Keluar: Plat {$trans['plat_nomor']} (Durasi: {$durasi_jam_final} jam, Biaya: " . format_rupiah($biaya_total_final) . ")"
                );

                $pdo->commit();

                set_flash('success', "Kendaraan '{$trans['plat_nomor']}' berhasil check-out. Total pembayaran: " . format_rupiah($biaya_total_final));
                redirect(BASE_URL . '/modules/petugas/cetak.php?id=' . $post_id_parkir . '&type=keluar');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Gagal memproses check-out: ' . $e->getMessage();
        }
    }
}

// Daftar 5 Kendaraan Sedang Parkir untuk Quick Pick
$active_parked_list = $pdo->query("
    SELECT t.id_parkir, t.waktu_masuk, k.plat_nomor, k.jenis_kendaraan, a.nama_area
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    WHERE t.status = 'masuk'
    ORDER BY t.waktu_masuk ASC
    LIMIT 6
")->fetchAll();

$page_title = 'Pencatatan Transaksi Keluar (Check-Out)';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-4xl mx-auto space-y-6">
                
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Check-Out & Pembayaran Parkir</h2>
                        <p class="text-xs sm:text-sm text-slate-500">Hitung durasi dan kalkulasi total biaya parkir secara otomatis</p>
                    </div>
                    <a href="<?= BASE_URL ?>/modules/petugas/transaksi.php" class="text-xs font-semibold text-slate-500 hover:text-slate-800">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Riwayat Transaksi
                    </a>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm flex items-start space-x-3">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 text-base mt-0.5"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Search Input Bar -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
                    <form method="GET" class="flex flex-col sm:flex-row gap-3 items-center">
                        <div class="relative flex-1 w-full">
                            <i class="fa-solid fa-magnifying-glass absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                            <input type="text" name="plat" value="<?= htmlspecialchars($search_plat) ?>" placeholder="Masukkan nomor plat atau ID tiket parkir (Contoh: B 1234 ABC)..."
                                class="w-full pl-11 pr-4 py-3 text-sm bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase font-mono-code font-bold">
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition-all flex items-center justify-center">
                            <i class="fa-solid fa-search mr-2"></i> Cari Kendaraan
                        </button>
                    </form>
                </div>

                <!-- Preview Perhitungan & Konfirmasi Pembayaran -->
                <?php if ($selected_transaksi && $kalkulasi): ?>
                    <div class="bg-white rounded-2xl border-2 border-indigo-500/40 shadow-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-slate-900 to-indigo-950 px-6 py-4 text-white flex justify-between items-center">
                            <div class="flex items-center space-x-3">
                                <span class="px-3 py-1 bg-indigo-500/30 border border-indigo-400/40 rounded-lg text-xs font-mono-code font-bold">
                                    Tiket #<?= $selected_transaksi['id_parkir'] ?>
                                </span>
                                <h3 class="text-sm font-bold">Rincian Perhitungan Biaya Parkir</h3>
                            </div>
                            <span class="text-xs text-indigo-300 flex items-center">
                                <i class="fa-solid fa-calculator mr-1.5"></i> Kalkulasi Otomatis
                            </span>
                        </div>

                        <div class="p-6 sm:p-8 space-y-6">
                            <!-- Info Kendaraan Grid -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pb-6 border-b border-slate-100">
                                <div>
                                    <p class="text-[11px] font-bold text-slate-400 uppercase">Plat Nomor</p>
                                    <p class="text-base font-extrabold font-mono-code text-slate-900 mt-0.5"><?= htmlspecialchars($selected_transaksi['plat_nomor']) ?></p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-bold text-slate-400 uppercase">Jenis Kendaraan</p>
                                    <p class="text-sm font-bold text-slate-800 capitalize mt-0.5"><?= htmlspecialchars($selected_transaksi['jenis_kendaraan']) ?></p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-bold text-slate-400 uppercase">Area Parkir</p>
                                    <p class="text-sm font-bold text-slate-800 mt-0.5"><?= htmlspecialchars($selected_transaksi['nama_area']) ?></p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-bold text-slate-400 uppercase">Pemilik</p>
                                    <p class="text-sm font-semibold text-slate-700 mt-0.5"><?= htmlspecialchars($selected_transaksi['pemilik']) ?></p>
                                </div>
                            </div>

                            <!-- Waktu Masuk vs Waktu Keluar -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200/80">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg">
                                        <i class="fa-solid fa-right-to-bracket"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase text-slate-400">Waktu Masuk</p>
                                        <p class="text-xs font-bold text-slate-800"><?= format_tanggal_indo($selected_transaksi['waktu_masuk'], true) ?></p>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-lg">
                                        <i class="fa-solid fa-right-from-bracket"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase text-slate-400">Waktu Keluar (Saat Ini)</p>
                                        <p class="text-xs font-bold text-slate-800"><?= format_tanggal_indo($waktu_keluar_sekarang, true) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Kalkulasi Detail Biaya -->
                            <div class="bg-indigo-50/60 p-5 rounded-2xl border border-indigo-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                <div>
                                    <div class="flex items-center space-x-3 text-xs text-indigo-900 mb-1">
                                        <span>Durasi: <strong><?= $kalkulasi['durasi_menit'] ?> Menit</strong></span>
                                        <span>&bull;</span>
                                        <span>Dihitung: <strong><?= $kalkulasi['durasi_jam'] ?> Jam</strong> (Pembulatan ke atas)</span>
                                    </div>
                                    <p class="text-[11px] text-indigo-700">
                                        Rumus: <?= $kalkulasi['durasi_jam'] ?> Jam &times; <?= format_rupiah($kalkulasi['tarif_per_jam']) ?> / jam
                                    </p>
                                </div>

                                <div class="text-right w-full sm:w-auto">
                                    <p class="text-[11px] uppercase font-bold text-indigo-500">Total Biaya Parkir</p>
                                    <p class="text-3xl font-black text-indigo-900"><?= format_rupiah($kalkulasi['biaya_total']) ?></p>
                                </div>
                            </div>

                            <!-- Form Konfirmasi Checkout -->
                            <form action="" method="POST" class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="id_parkir" value="<?= $selected_transaksi['id_parkir'] ?>">
                                <input type="hidden" name="btn_confirm_checkout" value="1">

                                <a href="<?= BASE_URL ?>/modules/petugas/keluar.php" class="px-4 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                                    Batal
                                </a>
                                <button type="submit" class="px-6 py-2.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-lg shadow-emerald-600/30 transition-all flex items-center">
                                    <i class="fa-solid fa-circle-check mr-2"></i> Konfirmasi Pembayaran & Cetak Struk
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Pick: Kendaraan yang Sedang Terparkir -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6">
                    <h3 class="text-sm font-bold text-slate-900 mb-1">Pilih Cepat Kendaraan Terparkir</h3>
                    <p class="text-xs text-slate-500 mb-4">Klik pada kendaraan untuk langsung memproses check-out</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <?php if (empty($active_parked_list)): ?>
                            <div class="col-span-full py-6 text-center text-xs text-slate-400">
                                Tidak ada kendaraan yang sedang parkir saat ini.
                            </div>
                        <?php else: ?>
                            <?php foreach ($active_parked_list as $apl): ?>
                                <a href="<?= BASE_URL ?>/modules/petugas/keluar.php?id=<?= $apl['id_parkir'] ?>"
                                    class="p-3 rounded-xl border border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/30 transition-all flex items-center justify-between group">
                                    <div>
                                        <span class="text-xs font-mono-code font-bold text-slate-900 group-hover:text-indigo-600">
                                            <?= htmlspecialchars($apl['plat_nomor']) ?>
                                        </span>
                                        <p class="text-[10px] text-slate-500 mt-0.5">
                                            <?= htmlspecialchars($apl['nama_area']) ?> &bull; <?= date('H:i', strtotime($apl['waktu_masuk'])) ?>
                                        </p>
                                    </div>
                                    <i class="fa-solid fa-chevron-right text-xs text-slate-300 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
