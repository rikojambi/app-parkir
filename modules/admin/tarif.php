<?php
/**
 * ========================================================
 * Modul Admin: Manajemen Tarif Parkir (CRUD tb_tarif)
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/log.php';
require_once __DIR__ . '/../../functions/parkir.php';

require_role('admin');

$pdo = get_db_connection();
$current_user = get_logged_in_user();
$action = $_GET['action'] ?? 'list';

// --------------------------------------------------------
// TAMBAH TARIF (CREATE)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_add_tarif'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid!');
        redirect(BASE_URL . '/modules/admin/tarif.php');
    }

    $jenis_kendaraan = trim($_POST['jenis_kendaraan'] ?? '');
    $tarif_per_jam   = (float)($_POST['tarif_per_jam'] ?? 0);

    if (!in_array($jenis_kendaraan, ['motor', 'mobil', 'lainnya'])) {
        set_flash('danger', 'Jenis kendaraan tidak valid.');
    } elseif ($tarif_per_jam <= 0) {
        set_flash('danger', 'Tarif per jam harus lebih besar dari 0.');
    } else {
        // Cek apakah jenis kendaraan ini sudah ada di tabel
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tb_tarif WHERE jenis_kendaraan = :jenis");
        $stmt_check->execute([':jenis' => $jenis_kendaraan]);
        if ($stmt_check->fetchColumn() > 0) {
            set_flash('danger', "Tarif untuk jenis kendaraan '$jenis_kendaraan' sudah ada. Silakan edit data yang sudah ada.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO tb_tarif (jenis_kendaraan, tarif_per_jam) VALUES (:jenis, :tarif)");
            if ($stmt->execute([':jenis' => $jenis_kendaraan, ':tarif' => $tarif_per_jam])) {
                catat_log($pdo, $current_user['id_user'], "Menambahkan tarif baru: $jenis_kendaraan (" . format_rupiah($tarif_per_jam) . "/jam)");
                set_flash('success', "Tarif untuk $jenis_kendaraan berhasil ditambahkan.");
            } else {
                set_flash('danger', 'Gagal menambahkan tarif.');
            }
        }
    }
    redirect(BASE_URL . '/modules/admin/tarif.php');
}

// --------------------------------------------------------
// EDIT TARIF (UPDATE)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_edit_tarif'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid!');
        redirect(BASE_URL . '/modules/admin/tarif.php');
    }

    $id_tarif        = (int)($_POST['id_tarif'] ?? 0);
    $jenis_kendaraan = trim($_POST['jenis_kendaraan'] ?? '');
    $tarif_per_jam   = (float)($_POST['tarif_per_jam'] ?? 0);

    if (!in_array($jenis_kendaraan, ['motor', 'mobil', 'lainnya']) || $tarif_per_jam <= 0) {
        set_flash('danger', 'Input data tarif tidak valid.');
    } else {
        $stmt = $pdo->prepare("UPDATE tb_tarif SET jenis_kendaraan = :jenis, tarif_per_jam = :tarif WHERE id_tarif = :id_tarif");
        if ($stmt->execute([':jenis' => $jenis_kendaraan, ':tarif' => $tarif_per_jam, ':id_tarif' => $id_tarif])) {
            catat_log($pdo, $current_user['id_user'], "Memperbarui tarif: $jenis_kendaraan (" . format_rupiah($tarif_per_jam) . "/jam)");
            set_flash('success', "Tarif untuk $jenis_kendaraan berhasil diperbarui.");
        } else {
            set_flash('danger', 'Gagal memperbarui tarif.');
        }
    }
    redirect(BASE_URL . '/modules/admin/tarif.php');
}

// --------------------------------------------------------
// HAPUS TARIF (DELETE)
// --------------------------------------------------------
if ($action === 'delete') {
    $id_del = (int)($_GET['id'] ?? 0);
    $stmt_find = $pdo->prepare("SELECT jenis_kendaraan FROM tb_tarif WHERE id_tarif = :id");
    $stmt_find->execute([':id' => $id_del]);
    $del = $stmt_find->fetch();

    if ($del) {
        try {
            $stmt = $pdo->prepare("DELETE FROM tb_tarif WHERE id_tarif = :id");
            $stmt->execute([':id' => $id_del]);
            catat_log($pdo, $current_user['id_user'], "Menghapus tarif: " . $del['jenis_kendaraan']);
            set_flash('success', "Tarif {$del['jenis_kendaraan']} berhasil dihapus.");
        } catch (PDOException $e) {
            set_flash('warning', "Tarif ini tidak dapat dihapus karena sudah tercatat dalam riwayat transaksi parkir.");
        }
    }
    redirect(BASE_URL . '/modules/admin/tarif.php');
}

// --------------------------------------------------------
// READ SEMUA TARIF
// --------------------------------------------------------
$stmt_tarif = $pdo->query("SELECT * FROM tb_tarif ORDER BY id_tarif ASC");
$daftar_tarif = $stmt_tarif->fetchAll();

$page_title = 'Kelola Tarif Parkir';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-5xl mx-auto space-y-6">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Tarif Parkir</h2>
                        <p class="text-xs sm:text-sm text-slate-500">Konfigurasi nominal tarif per jam berdasarkan klasifikasi jenis kendaraan</p>
                    </div>
                    <?php if (count($daftar_tarif) < 3): ?>
                        <button type="button" onclick="openModal('modalAddTarif')" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition-all">
                            <i class="fa-solid fa-plus mr-2"></i> Tambah Tarif
                        </button>
                    <?php endif; ?>
                </div>

                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Tarif Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <?php foreach ($daftar_tarif as $t): 
                        $icon = 'fa-car-side';
                        $accentColor = 'indigo';
                        if ($t['jenis_kendaraan'] === 'motor') {
                            $icon = 'fa-motorcycle';
                            $accentColor = 'emerald';
                        } elseif ($t['jenis_kendaraan'] === 'lainnya') {
                            $icon = 'fa-truck';
                            $accentColor = 'amber';
                        }
                    ?>
                        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 relative overflow-hidden flex flex-col justify-between">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-<?= $accentColor ?>-50 text-<?= $accentColor ?>-600 flex items-center justify-center text-2xl">
                                    <i class="fa-solid <?= $icon ?>"></i>
                                </div>
                                <span class="uppercase tracking-wider font-bold text-[11px] px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">
                                    <?= htmlspecialchars($t['jenis_kendaraan']) ?>
                                </span>
                            </div>

                            <div class="my-3">
                                <p class="text-xs text-slate-400 font-semibold uppercase">Tarif Per Jam</p>
                                <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><?= format_rupiah($t['tarif_per_jam']) ?></h3>
                                <p class="text-[11px] text-slate-500 mt-1">Dihitung otomatis saat check-out</p>
                            </div>

                            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                                <button type="button" 
                                    onclick='editTarif(<?= json_encode($t) ?>)'
                                    class="px-3 py-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                </button>
                                <button type="button" 
                                    onclick="confirmDelete('<?= BASE_URL ?>/modules/admin/tarif.php?action=delete&id=<?= $t['id_tarif'] ?>', 'Hapus tarif <?= $t['jenis_kendaraan'] ?>?')"
                                    class="px-3 py-1.5 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors">
                                    <i class="fa-solid fa-trash-can mr-1"></i> Hapus
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- MODAL TAMBAH TARIF -->
<div id="modalAddTarif" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-sm w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-sm font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-tags text-indigo-600 mr-2"></i> Tambah Tarif
            </h3>
            <button type="button" onclick="closeModal('modalAddTarif')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="btn_add_tarif" value="1">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kendaraan</label>
                <select name="jenis_kendaraan" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="motor">Motor</option>
                    <option value="mobil">Mobil</option>
                    <option value="lainnya">Lainnya (Bus/Truk)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tarif Per Jam (Rp)</label>
                <input type="number" name="tarif_per_jam" required min="500" step="500" placeholder="Contoh: 3000"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddTarif')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition-all">
                    Simpan Tarif
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT TARIF -->
<div id="modalEditTarif" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-sm w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-sm font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-pen-to-square text-indigo-600 mr-2"></i> Edit Tarif
            </h3>
            <button type="button" onclick="closeModal('modalEditTarif')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="btn_edit_tarif" value="1">
            <input type="hidden" id="edit_id_tarif" name="id_tarif" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kendaraan</label>
                <input type="text" id="edit_jenis_kendaraan" name="jenis_kendaraan" readonly
                    class="w-full px-3 py-2 text-xs bg-slate-100 border border-slate-300 rounded-xl text-slate-500 font-bold uppercase cursor-not-allowed">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tarif Per Jam (Rp)</label>
                <input type="number" id="edit_tarif_per_jam" name="tarif_per_jam" required min="500" step="500"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modalEditTarif')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
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
function editTarif(t) {
    document.getElementById('edit_id_tarif').value = t.id_tarif;
    document.getElementById('edit_jenis_kendaraan').value = t.jenis_kendaraan;
    document.getElementById('edit_tarif_per_jam').value = t.tarif_per_jam;
    openModal('modalEditTarif');
}
</script>
