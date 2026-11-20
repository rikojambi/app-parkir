<?php
/**
 * ========================================================
 * Modul Owner: Rekapitulasi & Laporan Transaksi
 * Fitur: Filter tanggal, agregasi SQL efisien, print-friendly
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/parkir.php';
require_once __DIR__ . '/../../functions/pagination.php';

require_role('owner');

$pdo = get_db_connection();
$current_user = get_logged_in_user();

// Parameter Filter Tanggal & Kategori
$tgl_mulai   = trim($_GET['tgl_mulai'] ?? date('Y-m-01')); // Default awal bulan ini
$tgl_selesai = trim($_GET['tgl_selesai'] ?? date('Y-m-d')); // Default hari ini
$jenis       = trim($_GET['jenis'] ?? '');
$id_area     = (int)($_GET['id_area'] ?? 0);
$is_print    = (isset($_GET['print']) && $_GET['print'] == '1');
$page        = (int)($_GET['page'] ?? 1);
$per_page    = $is_print ? 1000 : 15; // Jika mode cetak, tampilkan semua tanpa terpotong pagination

$where_clauses = ["t.status = 'keluar'"];
$params = [];

if (!empty($tgl_mulai)) {
    $where_clauses[] = "DATE(t.waktu_keluar) >= :tgl_mulai";
    $params[':tgl_mulai'] = $tgl_mulai;
}

if (!empty($tgl_selesai)) {
    $where_clauses[] = "DATE(t.waktu_keluar) <= :tgl_selesai";
    $params[':tgl_selesai'] = $tgl_selesai;
}

if (!empty($jenis) && in_array($jenis, ['motor', 'mobil', 'lainnya'])) {
    $where_clauses[] = "k.jenis_kendaraan = :jenis";
    $params[':jenis'] = $jenis;
}

if ($id_area > 0) {
    $where_clauses[] = "t.id_area = :id_area";
    $params[':id_area'] = $id_area;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// 1. Agregasi Efisien di Database (Best practice: Menghindari looping manual di PHP)
$stmt_summary = $pdo->prepare("
    SELECT 
        COUNT(t.id_parkir) AS total_transaksi,
        COALESCE(SUM(t.biaya_total), 0) AS total_pendapatan,
        COALESCE(AVG(t.durasi_jam), 0) AS rata_durasi
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    $where_sql
");
$stmt_summary->execute($params);
$ringkasan = $stmt_summary->fetch() ?: ['total_transaksi' => 0, 'total_pendapatan' => 0, 'rata_durasi' => 0];

// 2. Pagination dengan LIMIT & OFFSET
$total_records = (int)$ringkasan['total_transaksi'];
$pagination = paginate($total_records, $page, $per_page);

// 3. Query Rincian Data Transaksi Terfilter
$query_laporan = "
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
           a.nama_area, tf.tarif_per_jam, u.nama_lengkap AS nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_tarif tf ON t.id_tarif = tf.id_tarif
    JOIN tb_user u ON t.id_user = u.id_user
    $where_sql
    ORDER BY t.waktu_keluar DESC
    LIMIT :limit OFFSET :offset
";
$stmt_list = $pdo->prepare($query_laporan);
foreach ($params as $k => $v) {
    $stmt_list->bindValue($k, $v);
}
$stmt_list->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt_list->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt_list->execute();
$data_laporan = $stmt_list->fetchAll();

// List Area Parkir untuk Filter Dropdown
$area_dropdown = $pdo->query("SELECT id_area, nama_area FROM tb_area_parkir ORDER BY nama_area ASC")->fetchAll();

$page_title = 'Rekapitulasi Laporan Transaksi';

// Jika Mode Cetak (Print View)
if ($is_print):
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Transaksi Parkir</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        @media print {
            @page { size: A4 landscape; margin: 15mm; }
        }
    </style>
</head>
<body class="bg-white p-8 text-slate-800 antialiased font-sans" onload="window.print()">
    <!-- Kop Laporan Formal -->
    <div class="border-b-2 border-slate-900 pb-4 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-xl font-black uppercase tracking-wider text-slate-900"><?= APP_NAME ?></h1>
                <p class="text-xs text-slate-600">Divisi Operasional & Manajemen Parkir Perusahaan</p>
                <p class="text-[11px] text-slate-400">Jl. Teknologi No. 42, Jakarta - Email: support@smartparkir.co.id</p>
            </div>
            <div class="text-right">
                <h2 class="text-base font-bold text-slate-800">REKAPITULASI PENDAPATAN PARKIR</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Periode: <strong><?= date('d/m/Y', strtotime($tgl_mulai)) ?></strong> s/d <strong><?= date('d/m/Y', strtotime($tgl_selesai)) ?></strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Ringkasan Eksekutif -->
    <div class="grid grid-cols-3 gap-4 mb-6 p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs">
        <div>
            <span class="text-slate-500 font-semibold uppercase">Total Unit Parkir:</span>
            <p class="text-base font-black text-slate-900 mt-0.5"><?= number_format($ringkasan['total_transaksi']) ?> Kendaraan</p>
        </div>
        <div>
            <span class="text-slate-500 font-semibold uppercase">Rata-rata Durasi:</span>
            <p class="text-base font-black text-slate-900 mt-0.5"><?= round($ringkasan['rata_durasi'], 1) ?> Jam / Kendaraan</p>
        </div>
        <div>
            <span class="text-slate-500 font-semibold uppercase">Total Penerimaan Kas:</span>
            <p class="text-lg font-black text-emerald-700 mt-0.5"><?= format_rupiah($ringkasan['total_pendapatan']) ?></p>
        </div>
    </div>

    <!-- Tabel Rincian -->
    <table class="w-full text-left text-xs border border-slate-300 border-collapse mb-8">
        <thead>
            <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-300">
                <th class="p-2 border-r border-slate-300 text-center">No</th>
                <th class="p-2 border-r border-slate-300">No. Tiket</th>
                <th class="p-2 border-r border-slate-300">Plat Nomor</th>
                <th class="p-2 border-r border-slate-300">Jenis</th>
                <th class="p-2 border-r border-slate-300">Area Parkir</th>
                <th class="p-2 border-r border-slate-300">Waktu Masuk</th>
                <th class="p-2 border-r border-slate-300">Waktu Keluar</th>
                <th class="p-2 border-r border-slate-300 text-center">Durasi</th>
                <th class="p-2 border-r border-slate-300 text-right">Tarif/Jam</th>
                <th class="p-2 text-right">Total Biaya</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($data_laporan as $row): 
            ?>
                <tr class="border-b border-slate-200">
                    <td class="p-2 border-r border-slate-200 text-center"><?= $no++ ?></td>
                    <td class="p-2 border-r border-slate-200 font-mono">#<?= $row['id_parkir'] ?></td>
                    <td class="p-2 border-r border-slate-200 font-bold font-mono"><?= htmlspecialchars($row['plat_nomor']) ?></td>
                    <td class="p-2 border-r border-slate-200 capitalize"><?= htmlspecialchars($row['jenis_kendaraan']) ?></td>
                    <td class="p-2 border-r border-slate-200"><?= htmlspecialchars($row['nama_area']) ?></td>
                    <td class="p-2 border-r border-slate-200 font-mono"><?= date('d/m/Y H:i', strtotime($row['waktu_masuk'])) ?></td>
                    <td class="p-2 border-r border-slate-200 font-mono"><?= date('d/m/Y H:i', strtotime($row['waktu_keluar'])) ?></td>
                    <td class="p-2 border-r border-slate-200 text-center"><?= $row['durasi_jam'] ?> Jam</td>
                    <td class="p-2 border-r border-slate-200 text-right"><?= format_rupiah($row['tarif_per_jam']) ?></td>
                    <td class="p-2 text-right font-bold"><?= format_rupiah($row['biaya_total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="bg-slate-100 font-black border-t-2 border-slate-400">
                <td colspan="9" class="p-2 text-right border-r border-slate-300 uppercase">Grand Total Pendapatan:</td>
                <td class="p-2 text-right text-emerald-800 text-sm"><?= format_rupiah($ringkasan['total_pendapatan']) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Tanda Tangan Pengesahan -->
    <div class="flex justify-between items-center text-xs mt-12 px-6">
        <div class="text-center">
            <p>Dicetak Pada: <?= date('d F Y, H:i') ?> WIB</p>
            <p class="mt-1">Petugas Audit Sistem</p>
            <div class="h-16"></div>
            <p class="font-bold border-t border-slate-400 pt-1 w-48 inline-block"><?= htmlspecialchars($current_user['nama_lengkap']) ?></p>
        </div>
        <div class="text-center">
            <p>Mengetahui,</p>
            <p class="mt-1 font-semibold">Direktur Utama / Pemilik (Owner)</p>
            <div class="h-16"></div>
            <p class="font-bold border-t border-slate-400 pt-1 w-48 inline-block">Hendra Wijaya</p>
        </div>
    </div>
</body>
</html>
<?php
exit;
endif;

// Tampilan Normal di Antarmuka Pengguna (Web View)
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Rekapitulasi Laporan Transaksi</h2>
                        <p class="text-xs sm:text-sm text-slate-500">Analisis dan filter pemasukan kas berdasarkan rentang waktu operasional</p>
                    </div>
                    <?php 
                        $print_params = $_GET;
                        $print_params['print'] = '1';
                        $print_url = BASE_URL . '/modules/owner/laporan.php?' . http_build_query($print_params);
                    ?>
                    <a href="<?= htmlspecialchars($print_url) ?>" target="_blank" class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/20 transition-all">
                        <i class="fa-solid fa-print mr-2"></i> Cetak Laporan Formal
                    </a>
                </div>

                <!-- Filter Box -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Dari Tanggal</label>
                            <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>"
                                class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Sampai Tanggal</label>
                            <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>"
                                class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Jenis Kendaraan</label>
                            <select name="jenis" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="">Semua Jenis</option>
                                <option value="motor" <?= $jenis === 'motor' ? 'selected' : '' ?>>Motor</option>
                                <option value="mobil" <?= $jenis === 'mobil' ? 'selected' : '' ?>>Mobil</option>
                                <option value="lainnya" <?= $jenis === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Area Parkir</label>
                            <select name="id_area" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="0">Semua Area</option>
                                <?php foreach ($area_dropdown as $ad): ?>
                                    <option value="<?= $ad['id_area'] ?>" <?= $id_area === (int)$ad['id_area'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ad['nama_area']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="flex-1 py-2 bg-slate-800 text-white text-xs font-semibold rounded-xl hover:bg-slate-900 transition-colors">
                                <i class="fa-solid fa-filter mr-1.5"></i> Terapkan
                            </button>
                            <a href="<?= BASE_URL ?>/modules/owner/laporan.php" class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-semibold rounded-xl hover:bg-slate-200 transition-colors">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Aggregated Summary Metric Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase">Volume Kendaraan Terlayani</p>
                            <h3 class="text-2xl font-black text-slate-900 mt-1"><?= number_format($ringkasan['total_transaksi']) ?> Unit</h3>
                            <p class="text-[11px] text-slate-400 mt-1">Sesuai rentang filter</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-car-side"></i>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase">Rata-rata Durasi Parkir</p>
                            <h3 class="text-2xl font-black text-indigo-600 mt-1"><?= round($ringkasan['rata_durasi'], 1) ?> Jam</h3>
                            <p class="text-[11px] text-slate-400 mt-1">Durasi per kendaraan</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase">Total Pemasukan Kas</p>
                            <h3 class="text-2xl font-black text-emerald-600 mt-1"><?= format_rupiah($ringkasan['total_pendapatan']) ?></h3>
                            <p class="text-[11px] text-slate-400 mt-1">Kas terverifikasi keluar</p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>

                <!-- Laporan Table -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 uppercase font-semibold">
                                    <th class="py-3 px-4">#</th>
                                    <th class="py-3 px-4">No. Tiket</th>
                                    <th class="py-3 px-4">Plat Nomor</th>
                                    <th class="py-3 px-4">Jenis & Area</th>
                                    <th class="py-3 px-4">Waktu Masuk</th>
                                    <th class="py-3 px-4">Waktu Keluar</th>
                                    <th class="py-3 px-4 text-center">Durasi</th>
                                    <th class="py-3 px-4 text-right">Tarif/Jam</th>
                                    <th class="py-3 px-4 text-right">Total Biaya</th>
                                    <th class="py-3 px-4 text-center">Struk</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($data_laporan)): ?>
                                    <tr>
                                        <td colspan="10" class="py-8 text-center text-slate-400">
                                            Tidak ada data transaksi pada rentang waktu atau filter yang dipilih.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $no = $pagination['start_record'];
                                    foreach ($data_laporan as $row): 
                                    ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="py-3 px-4 font-medium text-slate-400"><?= $no++ ?></td>
                                            <td class="py-3 px-4 font-mono-code font-bold text-slate-700">#<?= $row['id_parkir'] ?></td>
                                            <td class="py-3 px-4">
                                                <span class="font-mono-code font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                                    <?= htmlspecialchars($row['plat_nomor']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="font-semibold text-slate-800 capitalize"><?= htmlspecialchars($row['jenis_kendaraan']) ?></div>
                                                <div class="text-[11px] text-slate-500"><?= htmlspecialchars($row['nama_area']) ?></div>
                                            </td>
                                            <td class="py-3 px-4 font-mono-code text-slate-600">
                                                <?= date('d/m/Y H:i', strtotime($row['waktu_masuk'])) ?>
                                            </td>
                                            <td class="py-3 px-4 font-mono-code text-slate-600">
                                                <?= date('d/m/Y H:i', strtotime($row['waktu_keluar'])) ?>
                                            </td>
                                            <td class="py-3 px-4 text-center font-semibold text-slate-800">
                                                <?= $row['durasi_jam'] ?> Jam
                                            </td>
                                            <td class="py-3 px-4 text-right text-slate-600">
                                                <?= format_rupiah($row['tarif_per_jam']) ?>
                                            </td>
                                            <td class="py-3 px-4 text-right font-black text-emerald-700">
                                                <?= format_rupiah($row['biaya_total']) ?>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <a href="<?= BASE_URL ?>/modules/petugas/cetak.php?id=<?= $row['id_parkir'] ?>&type=keluar" target="_blank" class="p-1.5 text-slate-400 hover:text-indigo-600 transition-colors" title="Lihat Struk">
                                                    <i class="fa-solid fa-receipt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php 
                    $params_url = $_GET;
                    unset($params_url['page']);
                    $base_pagination_url = BASE_URL . '/modules/owner/laporan.php' . (!empty($params_url) ? '?' . http_build_query($params_url) : '');
                    echo render_pagination($pagination, $base_pagination_url);
                    ?>
                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
