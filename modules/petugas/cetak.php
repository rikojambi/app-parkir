<?php
/**
 * ========================================================
 * Modul Petugas: Cetak Struk & Karcis Parkir
 * Layout dioptimalkan untuk printer thermal POS & standar
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/parkir.php';

require_role(['admin', 'petugas', 'owner']);

$pdo = get_db_connection();
$id_parkir = (int)($_GET['id'] ?? 0);
$type      = trim($_GET['type'] ?? 'keluar'); // 'masuk' atau 'keluar'

if ($id_parkir <= 0) {
    die("ID Transaksi tidak valid.");
}

// Ambil data transaksi lengkap
$stmt = $pdo->prepare("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
           a.nama_area, tf.tarif_per_jam, u.nama_lengkap AS nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_tarif tf ON t.id_tarif = tf.id_tarif
    JOIN tb_user u ON t.id_user = u.id_user
    WHERE t.id_parkir = :id
    LIMIT 1
");
$stmt->execute([':id' => $id_parkir]);
$trans = $stmt->fetch();

if (!$trans) {
    die("Data transaksi parkir tidak ditemukan.");
}

$page_title = ($type === 'masuk') ? 'Karcis Masuk #' . $trans['id_parkir'] : 'Struk Pembayaran #' . $trans['id_parkir'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-slate-100 flex flex-col items-center justify-center min-h-screen p-4 text-slate-800">

    <!-- Action Toolbar (Hidden When Printing) -->
    <div class="no-print w-full max-w-sm mb-4 flex items-center justify-between">
        <button type="button" onclick="window.history.back()" class="px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali
        </button>
        <div class="space-x-2">
            <button type="button" onclick="window.print()" class="px-4 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold shadow hover:bg-indigo-700 transition-colors">
                <i class="fa-solid fa-print mr-1"></i> Cetak Struk
            </button>
        </div>
    </div>

    <!-- Printable Thermal Receipt Paper -->
    <div class="printable-receipt bg-white w-full max-w-xs p-6 rounded-2xl shadow-xl border border-slate-200 font-mono-code text-xs">
        
        <!-- Header Struk -->
        <div class="text-center pb-4 border-b border-dashed border-slate-300">
            <h1 class="text-sm font-black tracking-wider uppercase text-slate-900"><?= APP_NAME ?></h1>
            <p class="text-[10px] text-slate-500 mt-0.5">Sistem Manajemen Parkir Modern</p>
            <p class="text-[10px] text-slate-400">Jl. Teknologi No. 42, Jakarta</p>
            <div class="mt-2 inline-block px-2 py-0.5 bg-slate-100 rounded text-[10px] font-bold uppercase tracking-wider text-slate-700">
                <?= ($type === 'masuk') ? '--- KARCIS MASUK ---' : '--- BUKTI PEMBAYARAN ---' ?>
            </div>
        </div>

        <!-- Nomor Tiket & Barcode Dummy -->
        <div class="text-center py-3 border-b border-dashed border-slate-300">
            <div class="text-base font-black text-slate-900 tracking-widest">
                #<?= str_pad($trans['id_parkir'], 6, '0', STR_PAD_LEFT) ?>
            </div>
            <!-- Barcode visual simulation -->
            <div class="flex justify-center items-center space-x-1 my-1.5 h-8">
                <span class="w-1 h-7 bg-slate-900"></span>
                <span class="w-2 h-7 bg-slate-900"></span>
                <span class="w-0.5 h-7 bg-slate-900"></span>
                <span class="w-3 h-7 bg-slate-900"></span>
                <span class="w-1 h-7 bg-slate-900"></span>
                <span class="w-2 h-7 bg-slate-900"></span>
                <span class="w-0.5 h-7 bg-slate-900"></span>
                <span class="w-2 h-7 bg-slate-900"></span>
                <span class="w-1 h-7 bg-slate-900"></span>
                <span class="w-3 h-7 bg-slate-900"></span>
                <span class="w-0.5 h-7 bg-slate-900"></span>
            </div>
            <p class="text-[9px] text-slate-400"><?= format_tiket_parkir($trans['id_parkir']) ?></p>
        </div>

        <!-- Detail Kendaraan & Parkir -->
        <div class="py-3 space-y-2 border-b border-dashed border-slate-300">
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Plat Nomor:</span>
                <span class="font-extrabold text-sm text-slate-900"><?= htmlspecialchars($trans['plat_nomor']) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Jenis:</span>
                <span class="font-semibold uppercase text-slate-800"><?= htmlspecialchars($trans['jenis_kendaraan']) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Area:</span>
                <span class="font-semibold text-slate-800"><?= htmlspecialchars($trans['nama_area']) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Waktu Masuk:</span>
                <span class="font-medium text-slate-800"><?= date('d/m/Y H:i', strtotime($trans['waktu_masuk'])) ?></span>
            </div>

            <?php if ($type === 'keluar' && $trans['waktu_keluar']): ?>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Waktu Keluar:</span>
                    <span class="font-medium text-slate-800"><?= date('d/m/Y H:i', strtotime($trans['waktu_keluar'])) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Durasi:</span>
                    <span class="font-semibold text-slate-800"><?= $trans['durasi_jam'] ?> Jam</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Tarif / Jam:</span>
                    <span class="text-slate-800"><?= format_rupiah($trans['tarif_per_jam']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Total Biaya (Khusus Struk Keluar) -->
        <?php if ($type === 'keluar' && $trans['waktu_keluar']): ?>
            <div class="py-3 border-b border-dashed border-slate-300">
                <div class="flex justify-between items-center text-sm font-bold">
                    <span>TOTAL BIAYA:</span>
                    <span class="text-base text-slate-950 font-black"><?= format_rupiah($trans['biaya_total']) ?></span>
                </div>
                <div class="flex justify-between items-center text-[10px] text-slate-500 mt-1">
                    <span>Status Bayar:</span>
                    <span class="font-bold text-emerald-600 uppercase">LUNAS</span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer Struk -->
        <div class="pt-4 text-center space-y-1 text-[10px] text-slate-500">
            <p>Petugas: <strong class="text-slate-700"><?= htmlspecialchars($trans['nama_petugas']) ?></strong></p>
            <?php if ($type === 'masuk'): ?>
                <p class="text-[9px] text-slate-400 mt-2">
                    * Simpan karcis ini dengan baik.<br>
                    * Kehilangan karcis dikenakan denda sesuai ketentuan.<br>
                    * Jangan meninggalkan barang berharga di kendaraan.
                </p>
            <?php else: ?>
                <p class="text-[10px] text-slate-600 font-semibold mt-2">
                    Terima kasih atas kunjungan Anda.<br>
                    Hati-hati di perjalanan!
                </p>
            <?php endif; ?>
        </div>

    </div>

    <!-- Auto Print Script -->
    <script>
        window.addEventListener('load', () => {
            // Uncomment line di bawah jika ingin langsung memicu dialog printer secara otomatis
            // window.print();
        });
    </script>
</body>
</html>
