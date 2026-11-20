<?php
/**
 * ========================================================
 * Modul Petugas: Daftar & Riwayat Transaksi Parkir
 * Best practice: Query efisien dengan LIMIT & Pagination
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/parkir.php';
require_once __DIR__ . '/../../functions/pagination.php';

require_role(['admin', 'petugas']);

$pdo = get_db_connection();

// Filter & Parameter
$status_filter = trim($_GET['status'] ?? '');
$search        = trim($_GET['search'] ?? '');
$tanggal       = trim($_GET['tanggal'] ?? '');
$page          = (int)($_GET['page'] ?? 1);
$per_page      = 10;

$where_clauses = [];
$params = [];

if (!empty($status_filter) && in_array($status_filter, ['masuk', 'keluar'])) {
    $where_clauses[] = "t.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $where_clauses[] = "(k.plat_nomor LIKE :search OR t.id_parkir = :search_id)";
    $params[':search'] = "%$search%";
    $params[':search_id'] = (int)$search;
}

if (!empty($tanggal)) {
    $where_clauses[] = "(DATE(t.waktu_masuk) = :tanggal OR DATE(t.waktu_keluar) = :tanggal)";
    $params[':tanggal'] = $tanggal;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Hitung total data
$stmt_count = $pdo->prepare("
    SELECT COUNT(*) 
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    $where_sql
");
$stmt_count->execute($params);
$total_records = (int)$stmt_count->fetchColumn();

$pagination = paginate($total_records, $page, $per_page);

// Query utama transaksi dengan LIMIT & OFFSET
$query_transaksi = "
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
           a.nama_area, tf.tarif_per_jam, u.nama_lengkap AS nama_petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
    JOIN tb_area_parkir a ON t.id_area = a.id_area
    JOIN tb_tarif tf ON t.id_tarif = tf.id_tarif
    JOIN tb_user u ON t.id_user = u.id_user
    $where_sql
    ORDER BY t.id_parkir DESC
    LIMIT :limit OFFSET :offset
";
$stmt_trans = $pdo->prepare($query_transaksi);
foreach ($params as $k => $v) {
    $stmt_trans->bindValue($k, $v);
}
$stmt_trans->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt_trans->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt_trans->execute();
$transaksi = $stmt_trans->fetchAll();

$page_title = 'Daftar Transaksi Parkir';
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
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Daftar Transaksi Parkir</h2>
                        <p class="text-xs sm:text-sm text-slate-500">Rekapitulasi seluruh aktivitas masuk dan keluar kendaraan</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <a href="<?= BASE_URL ?>/modules/petugas/masuk.php" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center">
                            <i class="fa-solid fa-right-to-bracket mr-1.5"></i> Kendaraan Masuk
                        </a>
                        <a href="<?= BASE_URL ?>/modules/petugas/keluar.php" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center">
                            <i class="fa-solid fa-right-from-bracket mr-1.5"></i> Kendaraan Keluar
                        </a>
                    </div>
                </div>

                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Filter Bar -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row gap-3 items-center justify-between">
                    <form method="GET" class="flex flex-1 flex-col sm:flex-row gap-3 w-full">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari plat nomor atau ID tiket..."
                                class="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase font-mono-code">
                        </div>
                        <select name="status" class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Status</option>
                            <option value="masuk" <?= $status_filter === 'masuk' ? 'selected' : '' ?>>Sedang Parkir (Masuk)</option>
                            <option value="keluar" <?= $status_filter === 'keluar' ? 'selected' : '' ?>>Selesai (Keluar)</option>
                        </select>
                        <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>"
                            class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-xs font-semibold rounded-xl hover:bg-slate-900 transition-colors">
                            Filter
                        </button>
                        <?php if (!empty($search) || !empty($status_filter) || !empty($tanggal)): ?>
                            <a href="<?= BASE_URL ?>/modules/petugas/transaksi.php" class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-medium rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center">
                                Reset
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table Transaksi -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 uppercase font-semibold">
                                    <th class="py-3 px-4">No. Tiket</th>
                                    <th class="py-3 px-4">Plat Nomor</th>
                                    <th class="py-3 px-4">Jenis & Area</th>
                                    <th class="py-3 px-4">Waktu Masuk</th>
                                    <th class="py-3 px-4">Waktu Keluar</th>
                                    <th class="py-3 px-4">Durasi & Biaya</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($transaksi)): ?>
                                    <tr>
                                        <td colspan="8" class="py-8 text-center text-slate-400">
                                            Tidak ditemukan transaksi parkir.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transaksi as $t): ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="py-3.5 px-4 font-mono-code font-bold text-slate-700">
                                                #<?= $t['id_parkir'] ?>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <span class="font-mono-code font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                                    <?= htmlspecialchars($t['plat_nomor']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <div class="font-semibold text-slate-800 capitalize"><?= htmlspecialchars($t['jenis_kendaraan']) ?></div>
                                                <div class="text-[11px] text-slate-500"><?= htmlspecialchars($t['nama_area']) ?></div>
                                            </td>
                                            <td class="py-3.5 px-4 font-mono-code text-slate-600">
                                                <?= date('d/m/Y H:i', strtotime($t['waktu_masuk'])) ?>
                                            </td>
                                            <td class="py-3.5 px-4 font-mono-code text-slate-600">
                                                <?= $t['waktu_keluar'] ? date('d/m/Y H:i', strtotime($t['waktu_keluar'])) : '<span class="text-amber-500 font-bold">-</span>' ?>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <?php if ($t['status'] === 'keluar'): ?>
                                                    <span class="font-semibold text-slate-800"><?= $t['durasi_jam'] ?> Jam</span>
                                                    <div class="text-[11px] font-bold text-emerald-600"><?= format_rupiah($t['biaya_total']) ?></div>
                                                <?php else: ?>
                                                    <span class="text-slate-400 italic">Sedang berjalan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <?php if ($t['status'] === 'masuk'): ?>
                                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200 uppercase">
                                                        Parkir Aktif
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200 uppercase">
                                                        Selesai Keluar
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3.5 px-4 text-center">
                                                <div class="inline-flex items-center space-x-1.5">
                                                    <?php if ($t['status'] === 'masuk'): ?>
                                                        <a href="<?= BASE_URL ?>/modules/petugas/keluar.php?id=<?= $t['id_parkir'] ?>" class="px-2 py-1 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-xs font-bold transition-colors">
                                                            Check-Out
                                                        </a>
                                                        <a href="<?= BASE_URL ?>/modules/petugas/cetak.php?id=<?= $t['id_parkir'] ?>&type=masuk" target="_blank" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded-lg transition-colors" title="Cetak Karcis Masuk">
                                                            <i class="fa-solid fa-print"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="<?= BASE_URL ?>/modules/petugas/cetak.php?id=<?= $t['id_parkir'] ?>&type=keluar" target="_blank" class="px-2.5 py-1 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg text-xs font-semibold transition-colors flex items-center">
                                                            <i class="fa-solid fa-receipt mr-1"></i> Struk
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
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
                    $base_pagination_url = BASE_URL . '/modules/petugas/transaksi.php' . (!empty($params_url) ? '?' . http_build_query($params_url) : '');
                    echo render_pagination($pagination, $base_pagination_url);
                    ?>
                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
