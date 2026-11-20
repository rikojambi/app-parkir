<?php
/**
 * ========================================================
 * Modul Admin: Manajemen Area Parkir (CRUD tb_area_parkir)
 * ========================================================
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../functions/auth.php';
require_once __DIR__ . '/../../functions/log.php';

require_role('admin');

$pdo = get_db_connection();
$current_user = get_logged_in_user();
$action = $_GET['action'] ?? 'list';

// --------------------------------------------------------
// TAMBAH AREA PARKIR (CREATE)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_add_area'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid!');
        redirect(BASE_URL . '/modules/admin/area.php');
    }

    $nama_area = trim($_POST['nama_area'] ?? '');
    $kapasitas = (int)($_POST['kapasitas'] ?? 0);

    if (empty($nama_area) || $kapasitas <= 0) {
        set_flash('danger', 'Nama area dan kapasitas (minimal 1) wajib diisi.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO tb_area_parkir (nama_area, kapasitas, terisi) VALUES (:nama_area, :kapasitas, 0)");
        if ($stmt->execute([':nama_area' => $nama_area, ':kapasitas' => $kapasitas])) {
            catat_log($pdo, $current_user['id_user'], "Menambahkan area parkir: $nama_area (Kapasitas: $kapasitas)");
            set_flash('success', "Area parkir '$nama_area' berhasil ditambahkan.");
        } else {
            set_flash('danger', 'Gagal menambahkan area parkir.');
        }
    }
    redirect(BASE_URL . '/modules/admin/area.php');
}

// --------------------------------------------------------
// EDIT AREA PARKIR (UPDATE)
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_edit_area'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid!');
        redirect(BASE_URL . '/modules/admin/area.php');
    }

    $id_area   = (int)($_POST['id_area'] ?? 0);
    $nama_area = trim($_POST['nama_area'] ?? '');
    $kapasitas = (int)($_POST['kapasitas'] ?? 0);

    // Ambil data kapasitas dan terisi saat ini
    $stmt_curr = $pdo->prepare("SELECT terisi FROM tb_area_parkir WHERE id_area = :id");
    $stmt_curr->execute([':id' => $id_area]);
    $curr_terisi = (int)$stmt_curr->fetchColumn();

    if (empty($nama_area) || $kapasitas <= 0) {
        set_flash('danger', 'Nama area dan kapasitas harus valid.');
    } elseif ($kapasitas < $curr_terisi) {
        set_flash('danger', "Kapasitas baru ($kapasitas) tidak boleh lebih kecil dari kendaraan yang sedang parkir saat ini ($curr_terisi slot terisi).");
    } else {
        $stmt = $pdo->prepare("UPDATE tb_area_parkir SET nama_area = :nama_area, kapasitas = :kapasitas WHERE id_area = :id_area");
        if ($stmt->execute([':nama_area' => $nama_area, ':kapasitas' => $kapasitas, ':id_area' => $id_area])) {
            catat_log($pdo, $current_user['id_user'], "Memperbarui area parkir: $nama_area (Kapasitas: $kapasitas)");
            set_flash('success', "Area parkir '$nama_area' berhasil diperbarui.");
        } else {
            set_flash('danger', 'Gagal memperbarui area parkir.');
        }
    }
    redirect(BASE_URL . '/modules/admin/area.php');
}

// --------------------------------------------------------
// HAPUS AREA PARKIR (DELETE)
// --------------------------------------------------------
if ($action === 'delete') {
    $id_del = (int)($_GET['id'] ?? 0);
    $stmt_find = $pdo->prepare("SELECT nama_area, terisi FROM tb_area_parkir WHERE id_area = :id");
    $stmt_find->execute([':id' => $id_del]);
    $area = $stmt_find->fetch();

    if ($area) {
        if ($area['terisi'] > 0) {
            set_flash('danger', "Tidak dapat menghapus area '{$area['nama_area']}' karena masih ada {$area['terisi']} kendaraan terparkir di dalamnya.");
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM tb_area_parkir WHERE id_area = :id");
                $stmt->execute([':id' => $id_del]);
                catat_log($pdo, $current_user['id_user'], "Menghapus area parkir: " . $area['nama_area']);
                set_flash('success', "Area parkir '{$area['nama_area']}' berhasil dihapus.");
            } catch (PDOException $e) {
                set_flash('warning', "Area parkir tidak dapat dihapus karena sudah memiliki riwayat transaksi parkir.");
            }
        }
    }
    redirect(BASE_URL . '/modules/admin/area.php');
}

// --------------------------------------------------------
// READ SEMUA AREA
// --------------------------------------------------------
$stmt_areas = $pdo->query("SELECT * FROM tb_area_parkir ORDER BY id_area ASC");
$areas = $stmt_areas->fetchAll();

$page_title = 'Kelola Area Parkir';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-6xl mx-auto space-y-6">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Area & Kuota Parkir</h2>
                        <p class="text-xs sm:text-sm text-slate-500">Pantau zonasi, kapasitas total, dan sisa slot ketersediaan parkir</p>
                    </div>
                    <button type="button" onclick="openModal('modalAddArea')" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/20 transition-all">
                        <i class="fa-solid fa-plus mr-2"></i> Tambah Area Baru
                    </button>
                </div>

                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Area Grid List -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($areas as $a): 
                        $tersedia = max(0, $a['kapasitas'] - $a['terisi']);
                        $pct = $a['kapasitas'] > 0 ? round(($a['terisi'] / $a['kapasitas']) * 100, 1) : 0;
                        $statusBadge = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                        $barColor = 'bg-emerald-500';

                        if ($a['terisi'] >= $a['kapasitas']) {
                            $statusBadge = 'bg-rose-100 text-rose-700 border-rose-200';
                            $barColor = 'bg-rose-500';
                        } elseif ($pct >= 75) {
                            $statusBadge = 'bg-amber-100 text-amber-700 border-amber-200';
                            $barColor = 'bg-amber-500';
                        }
                    ?>
                        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider <?= $statusBadge ?>">
                                        <?= ($a['terisi'] >= $a['kapasitas']) ? 'Penuh' : 'Tersedia' ?>
                                    </span>
                                    <span class="text-xs font-mono-code font-bold text-slate-400">ID: <?= $a['id_area'] ?></span>
                                </div>

                                <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($a['nama_area']) ?></h3>
                                
                                <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                                    <div class="p-2 bg-slate-50 rounded-xl">
                                        <p class="text-[10px] uppercase font-bold text-slate-400">Kapasitas</p>
                                        <p class="text-base font-bold text-slate-800 mt-0.5"><?= $a['kapasitas'] ?></p>
                                    </div>
                                    <div class="p-2 bg-indigo-50/60 rounded-xl">
                                        <p class="text-[10px] uppercase font-bold text-indigo-500">Terisi</p>
                                        <p class="text-base font-bold text-indigo-700 mt-0.5"><?= $a['terisi'] ?></p>
                                    </div>
                                    <div class="p-2 bg-emerald-50/60 rounded-xl">
                                        <p class="text-[10px] uppercase font-bold text-emerald-600">Sisa Slot</p>
                                        <p class="text-base font-bold text-emerald-700 mt-0.5"><?= $tersedia ?></p>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div class="mt-4">
                                    <div class="flex justify-between text-[11px] font-semibold text-slate-500 mb-1">
                                        <span>Okupansi</span>
                                        <span><?= $pct ?>%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                        <div class="<?= $barColor ?> h-2.5 rounded-full transition-all duration-500" style="width: <?= min(100, $pct) ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-5 mt-5 border-t border-slate-100 flex items-center justify-end space-x-2">
                                <button type="button" 
                                    onclick='editArea(<?= json_encode($a) ?>)'
                                    class="px-3 py-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                </button>
                                <button type="button" 
                                    onclick="confirmDelete('<?= BASE_URL ?>/modules/admin/area.php?action=delete&id=<?= $a['id_area'] ?>', 'Hapus area parkir <?= htmlspecialchars($a['nama_area']) ?>?')"
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

<!-- MODAL TAMBAH AREA -->
<div id="modalAddArea" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-sm w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-sm font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-map-location-dot text-indigo-600 mr-2"></i> Tambah Area Parkir
            </h3>
            <button type="button" onclick="closeModal('modalAddArea')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="btn_add_area" value="1">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Area Parkir</label>
                <input type="text" name="nama_area" required placeholder="Contoh: Gedung C - VIP"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kapasitas Maksimal (Slot)</label>
                <input type="number" name="kapasitas" required min="1" max="9999" placeholder="Contoh: 50"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modalAddArea')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-600/20 transition-all">
                    Simpan Area
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT AREA -->
<div id="modalEditArea" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-sm w-full overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-sm font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-pen-to-square text-indigo-600 mr-2"></i> Edit Area Parkir
            </h3>
            <button type="button" onclick="closeModal('modalEditArea')" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="btn_edit_area" value="1">
            <input type="hidden" id="edit_id_area" name="id_area" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Area Parkir</label>
                <input type="text" id="edit_nama_area" name="nama_area" required
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kapasitas Maksimal (Slot)</label>
                <input type="number" id="edit_kapasitas" name="kapasitas" required min="1" max="9999"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modalEditArea')" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
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
function editArea(a) {
    document.getElementById('edit_id_area').value = a.id_area;
    document.getElementById('edit_nama_area').value = a.nama_area;
    document.getElementById('edit_kapasitas').value = a.kapasitas;
    openModal('modalEditArea');
}
</script>
