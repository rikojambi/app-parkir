<?php
/**
 * ========================================================
 * Modul Admin: Manajemen Kendaraan (CRUD tb_kendaraan)
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/log.php';
require_once __DIR__ . '/../../functions/pagination.php';

require_role('admin');

$pdo = get_db_connection();
$current_user = get_logged_in_user();
$action = $_GET['action'] ?? 'list';

// --------------------------------------------------------
// TAMBAH KENDARAAN (CREATE)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_add_kendaraan'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid!');
        redirect(BASE_URL . '/modules/admin/kendaraan.php');
    }

    $plat_nomor      = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $jenis_kendaraan = trim($_POST['jenis_kendaraan'] ?? 'motor');
    $warna           = trim($_POST['warna'] ?? '');
    $pemilik         = trim($_POST['pemilik'] ?? '');
    $id_user         = (int)($_POST['id_user'] ?? $current_user['id_user']);

    if (empty($plat_nomor) || empty($warna) || empty($pemilik)) {
        set_flash('danger', 'Semua kolom wajib diisi.');
    } else {
        // Cek duplikasi plat nomor
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tb_kendaraan WHERE plat_nomor = :plat");
        $stmt_check->execute([':plat' => $plat_nomor]);
        if ($stmt_check->fetchColumn() > 0) {
            set_flash('danger', "Kendaraan dengan plat nomor '$plat_nomor' sudah terdaftar.");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user)
                VALUES (:plat, :jenis, :warna, :pemilik, :id_user)
            ");
            $exec = $stmt->execute([
                ':plat'    => $plat_nomor,
                ':jenis'   => $jenis_kendaraan,
                ':warna'   => $warna,
                ':pemilik' => $pemilik,
                ':id_user' => $id_user
            ]);

            if ($exec) {
                catat_log($pdo, $current_user['id_user'], "Mendaftarkan kendaraan baru: $plat_nomor ($pemilik)");
                set_flash('success', "Kendaraan '$plat_nomor' berhasil didaftarkan.");
            } else {
                set_flash('danger', 'Gagal mendaftarkan kendaraan.');
            }
        }
    }
    redirect(BASE_URL . '/modules/admin/kendaraan.php');
}

// --------------------------------------------------------
// EDIT KENDARAAN (UPDATE)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_edit_kendaraan'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid!');
        redirect(BASE_URL . '/modules/admin/kendaraan.php');
    }

    $id_kendaraan    = (int)($_POST['id_kendaraan'] ?? 0);
    $plat_nomor      = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $jenis_kendaraan = trim($_POST['jenis_kendaraan'] ?? 'motor');
    $warna           = trim($_POST['warna'] ?? '');
    $pemilik         = trim($_POST['pemilik'] ?? '');

    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tb_kendaraan WHERE plat_nomor = :plat AND id_kendaraan != :id");
    $stmt_check->execute([':plat' => $plat_nomor, ':id' => $id_kendaraan]);
    if ($stmt_check->fetchColumn() > 0) {
        set_flash('danger', "Plat nomor '$plat_nomor' sudah digunakan pada data kendaraan lain.");
    } else {
        $stmt = $pdo->prepare("
            UPDATE tb_kendaraan 
            SET plat_nomor = :plat, jenis_kendaraan = :jenis, warna = :warna, pemilik = :pemilik
            WHERE id_kendaraan = :id
        ");
        $exec = $stmt->execute([
            ':plat'    => $plat_nomor,
            ':jenis'   => $jenis_kendaraan,
            ':warna'   => $warna,
            ':pemilik' => $pemilik,
            ':id'      => $id_kendaraan
        ]);

        if ($exec) {
            catat_log($pdo, $current_user['id_user'], "Memperbarui kendaraan: $plat_nomor (ID: $id_kendaraan)");
            set_flash('success', "Data kendaraan '$plat_nomor' berhasil diperbarui.");
        } else {
            set_flash('danger', 'Gagal memperbarui data kendaraan.');
        }
    }
    redirect(BASE_URL . '/modules/admin/kendaraan.php');
}

// --------------------------------------------------------
// HAPUS KENDARAAN (DELETE)
// --------------------------------------------------------
if ($action === 'delete') {
    $id_del = (int)($_GET['id'] ?? 0);
    $stmt_find = $pdo->prepare("SELECT plat_nomor FROM tb_kendaraan WHERE id_kendaraan = :id");
    $stmt_find->execute([':id' => $id_del]);
    $ken = $stmt_find->fetch();

    if ($ken) {
        try {
            $stmt = $pdo->prepare("DELETE FROM tb_kendaraan WHERE id_kendaraan = :id");
            $stmt->execute([':id' => $id_del]);
            catat_log($pdo, $current_user['id_user'], "Menghapus data kendaraan: " . $ken['plat_nomor']);
            set_flash('success', "Kendaraan {$ken['plat_nomor']} berhasil dihapus.");
        } catch (PDOException $e) {
            set_flash('warning', "Kendaraan ini tidak dapat dihapus karena memiliki riwayat transaksi parkir.");
        }
    }
    redirect(BASE_URL . '/modules/admin/kendaraan.php');
}

// --------------------------------------------------------
// READ LIST KENDARAAN (PAGINATION DENGAN LIMIT)
// --------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$jenis_filter = trim($_GET['jenis'] ?? '');
$page = (int)($_GET['page'] ?? 1);
$per_page = 10;

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(k.plat_nomor LIKE :search OR k.pemilik LIKE :search OR k.warna LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($jenis_filter)) {
    $where_clauses[] = "k.jenis_kendaraan = :jenis";
    $params[':jenis'] = $jenis_filter;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tb_kendaraan k $where_sql");
$stmt_count->execute($params);
$total_records = (int)$stmt_count->fetchColumn();

$pagination = paginate($total_records, $page, $per_page);

$query_kendaraan = "
    SELECT k.*, u.nama_lengkap AS nama_petugas
    FROM tb_kendaraan k
    LEFT JOIN tb_user u ON k.id_user = u.id_user
    $where_sql
    ORDER BY k.id_kendaraan DESC
    LIMIT :limit OFFSET :offset
";
$stmt_k = $pdo->prepare($query_kendaraan);
foreach ($params as $key => $val) {
    $stmt_k->bindValue($key, $val);
}
$stmt_k->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt_k->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt_k->execute();
$daftar_kendaraan = $stmt_k->fetchAll();

$page_title = 'Data Master Kendaraan';
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
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Master Data Kendaraan</h2>
                        <p class="text-xs sm:text-sm text-slate-500">Kelola database identitas kendaraan dan pemilik yang terdaftar</p>
                    </div>
                    <button type="button" onclick="openModal('modalAddKendaraan')" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition-all">
                        <i class="fa-solid fa-car-side mr-2"></i> Daftarkan Kendaraan
                    </button>
                </div>

                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Filter & Search Bar -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row gap-3 items-center justify-between">
                    <form method="GET" class="flex flex-1 flex-col sm:flex-row gap-3 w-full">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari plat nomor, nama pemilik, atau warna..."
                                class="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <select name="jenis" class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Semua Jenis</option>
                            <option value="motor" <?= $jenis_filter === 'motor' ? 'selected' : '' ?>>Motor</option>
                            <option value="mobil" <?= $jenis_filter === 'mobil' ? 'selected' : '' ?>>Mobil</option>
                            <option value="lainnya" <?= $jenis_filter === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-xs font-semibold rounded-xl hover:bg-slate-900 transition-colors">
                            Filter
                        </button>
                        <?php if (!empty($search) || !empty($jenis_filter)): ?>
                            <a href="<?= BASE_URL ?>/modules/admin/kendaraan.php" class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-medium rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center">
                                Reset
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Kendaraan Table -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200/80 text-slate-500 uppercase font-semibold">
                                    <th class="py-3 px-4">#</th>
                                    <th class="py-3 px-4">Plat Nomor</th>
                                    <th class="py-3 px-4">Jenis</th>
                                    <th class="py-3 px-4">Warna</th>
                                    <th class="py-3 px-4">Pemilik</th>
                                    <th class="py-3 px-4">Petugas Pendaftar</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($daftar_kendaraan)): ?>
                                    <tr>
                                        <td colspan="7" class="py-8 text-center text-slate-400">
                                            Tidak ditemukan data kendaraan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $no = $pagination['start_record'];
                                    foreach ($daftar_kendaraan as $k): 
                                    ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="py-3.5 px-4 font-medium text-slate-400"><?= $no++ ?></td>
                                            <td class="py-3.5 px-4">
                                                <span class="font-mono-code font-bold text-slate-900 bg-slate-100 px-2.5 py-1 rounded-md border border-slate-200">
                                                    <?= htmlspecialchars($k['plat_nomor']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <span class="uppercase tracking-wider font-semibold text-[11px] text-slate-700">
                                                    <?= htmlspecialchars($k['jenis_kendaraan']) ?>
                                                </span>
                                            </td>
                                            <td class="py-3.5 px-4 text-slate-600"><?= htmlspecialchars($k['warna']) ?></td>
                                            <td class="py-3.5 px-4 font-semibold text-slate-800"><?= htmlspecialchars($k['pemilik']) ?></td>
                                            <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($k['nama_petugas'] ?? 'Admin') ?></td>
                                            <td class="py-3.5 px-4 text-center">
                                                <div class="inline-flex items-center space-x-2">
                                                    <button type="button" 
                                                        onclick='editKendaraan(<?= json_encode($k) ?>)'
                                                        class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Edit Data">
                                                        <i class="fa-solid fa-pen-to-square text-sm"></i>
                                                    </button>
                                                    <button type="button" 
                                                        onclick="confirmDelete('<?= BASE_URL ?>/modules/admin/kendaraan.php?action=delete&id=<?= $k['id_kendaraan'] ?>', 'Hapus kendaraan plat <?= htmlspecialchars($k['plat_nomor']) ?>?')"
                                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus Kendaraan">
                                                        <i class="fa-solid fa-trash-can text-sm"></i>
                                                    </button>
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
                    $base_pagination_url = BASE_URL . '/modules/admin/kendaraan.php' . (!empty($params_url) ? '?' . http_build_query($params_url) : '');
                    echo render_pagination($pagination, $base_pagination_url);
                    ?>
                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- MODAL TAMBAH KENDARAAN -->
<div id="modalAddKendaraan" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-sm font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-car text-indigo-600 mr-2"></i> Daftarkan Kendaraan Baru
            </h3>
            <button type="button" onclick="closeModal('modalAddKendaraan')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="btn_add_kendaraan" value="1">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Plat Nomor Kendaraan</label>
                <input type="text" name="plat_nomor" required placeholder="Contoh: B 1234 ABC" style="text-transform: uppercase;"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none uppercase font-mono-code font-bold">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kendaraan</label>
                    <select name="jenis_kendaraan" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="motor">Motor</option>
                        <option value="mobil">Mobil</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Warna</label>
                    <input type="text" name="warna" required placeholder="Contoh: Hitam"
                        class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pemilik / Pengendara</label>
                <input type="text" name="pemilik" required placeholder="Contoh: Ahmad Rizki"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddKendaraan')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition-all">
                    Simpan Kendaraan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT KENDARAAN -->
<div id="modalEditKendaraan" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-sm font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-pen-to-square text-indigo-600 mr-2"></i> Edit Data Kendaraan
            </h3>
            <button type="button" onclick="closeModal('modalEditKendaraan')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="btn_edit_kendaraan" value="1">
            <input type="hidden" id="edit_id_kendaraan" name="id_kendaraan" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Plat Nomor Kendaraan</label>
                <input type="text" id="edit_plat_nomor" name="plat_nomor" required style="text-transform: uppercase;"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none uppercase font-mono-code font-bold">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kendaraan</label>
                    <select id="edit_jenis_kendaraan" name="jenis_kendaraan" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="motor">Motor</option>
                        <option value="mobil">Mobil</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Warna</label>
                    <input type="text" id="edit_warna" name="warna" required
                        class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pemilik / Pengendara</label>
                <input type="text" id="edit_pemilik" name="pemilik" required
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modalEditKendaraan')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition-all">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editKendaraan(k) {
    document.getElementById('edit_id_kendaraan').value = k.id_kendaraan;
    document.getElementById('edit_plat_nomor').value = k.plat_nomor;
    document.getElementById('edit_jenis_kendaraan').value = k.jenis_kendaraan;
    document.getElementById('edit_warna').value = k.warna;
    document.getElementById('edit_pemilik').value = k.pemilik;
    openModal('modalEditKendaraan');
}
</script>
