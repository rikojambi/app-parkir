<?php
/**
 * ========================================================
 * Modul Petugas: Transaksi Masuk (Check-In Kendaraan)
 * Menjalankan validasi kapasitas, atomic update, dan log audit
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

$error = '';

// Proses Form Submit Transaksi Masuk
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Validasi keamanan CSRF gagal. Silakan coba lagi.';
    } else {
        $plat_nomor      = strtoupper(trim($_POST['plat_nomor'] ?? ''));
        $jenis_kendaraan = trim($_POST['jenis_kendaraan'] ?? 'motor');
        $warna           = trim($_POST['warna'] ?? 'Tidak Diketahui');
        $pemilik         = trim($_POST['pemilik'] ?? 'Pengendara Umum');
        $id_area         = (int)($_POST['id_area'] ?? 0);

        if (empty($plat_nomor) || empty($id_area)) {
            $error = 'Plat nomor dan pilihan area parkir wajib diisi.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Cek apakah kendaraan ini SEDANG PARKIR (Status = 'masuk')
                $stmt_cek_aktif = $pdo->prepare("
                    SELECT t.id_parkir, k.plat_nomor, a.nama_area 
                    FROM tb_transaksi t
                    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
                    JOIN tb_area_parkir a ON t.id_area = a.id_area
                    WHERE k.plat_nomor = :plat AND t.status = 'masuk'
                    LIMIT 1
                ");
                $stmt_cek_aktif->execute([':plat' => $plat_nomor]);
                $kendaraan_aktif = $stmt_cek_aktif->fetch();

                if ($kendaraan_aktif) {
                    $pdo->rollBack();
                    $error = "Kendaraan plat nomor $plat_nomor saat ini sudah tercatat parkir di {$kendaraan_aktif['nama_area']} (Tiket #{$kendaraan_aktif['id_parkir']}).";
                } else {
                    // 2. Cek KAPASITAS AREA PARKIR (Gunakan FOR UPDATE untuk concurrency safety)
                    $stmt_area = $pdo->prepare("
                        SELECT id_area, nama_area, kapasitas, terisi 
                        FROM tb_area_parkir 
                        WHERE id_area = :id_area 
                        FOR UPDATE
                    ");
                    $stmt_area->execute([':id_area' => $id_area]);
                    $area = $stmt_area->fetch();

                    if (!$area) {
                        $pdo->rollBack();
                        $error = 'Area parkir yang dipilih tidak ditemukan.';
                    } elseif ($area['terisi'] >= $area['kapasitas']) {
                        // LOGIKA KHUSUS: Jika terisi sama dengan kapasitas, TOLAK transaksi!
                        $pdo->rollBack();
                        $error = "Transaksi DITOLAK! Kapasitas area parkir '{$area['nama_area']}' sudah penuh ({$area['terisi']}/{$area['kapasitas']}). Silakan pilih area lain.";
                    } else {
                        // 3. Cek apakah kendaraan sudah terdaftar di tb_kendaraan, jika belum daftarkan
                        $stmt_k = $pdo->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE plat_nomor = :plat LIMIT 1");
                        $stmt_k->execute([':plat' => $plat_nomor]);
                        $id_kendaraan = $stmt_k->fetchColumn();

                        if (!$id_kendaraan) {
                            $stmt_new_k = $pdo->prepare("
                                INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user)
                                VALUES (:plat, :jenis, :warna, :pemilik, :uid)
                            ");
                            $stmt_new_k->execute([
                                ':plat'    => $plat_nomor,
                                ':jenis'   => $jenis_kendaraan,
                                ':warna'   => $warna,
                                ':pemilik' => $pemilik,
                                ':uid'     => $current_user['id_user']
                            ]);
                            $id_kendaraan = $pdo->lastInsertId();
                        }

                        // 4. Ambil id_tarif yang cocok berdasarkan jenis_kendaraan
                        $stmt_tarif = $pdo->prepare("SELECT id_tarif FROM tb_tarif WHERE jenis_kendaraan = :jenis LIMIT 1");
                        $stmt_tarif->execute([':jenis' => $jenis_kendaraan]);
                        $id_tarif = $stmt_tarif->fetchColumn();

                        if (!$id_tarif) {
                            // Fallback jika belum diset
                            $id_tarif = 1;
                        }

                        // 5. Simpan transaksi masuk baru
                        $stmt_trans = $pdo->prepare("
                            INSERT INTO tb_transaksi (id_kendaraan, waktu_masuk, id_tarif, status, id_user, id_area)
                            VALUES (:id_kendaraan, NOW(), :id_tarif, 'masuk', :id_user, :id_area)
                        ");
                        $stmt_trans->execute([
                            ':id_kendaraan' => $id_kendaraan,
                            ':id_tarif'     => $id_tarif,
                            ':id_user'      => $current_user['id_user'],
                            ':id_area'      => $id_area
                        ]);
                        $new_id_parkir = $pdo->lastInsertId();

                        // 6. LOGIKA KHUSUS: Tambahkan angka terisi di tb_area_parkir
                        $stmt_update_area = $pdo->prepare("
                            UPDATE tb_area_parkir 
                            SET terisi = terisi + 1 
                            WHERE id_area = :id_area
                        ");
                        $stmt_update_area->execute([':id_area' => $id_area]);

                        // 7. LOGIKA KHUSUS: Catat log aktivitas sistem
                        catat_log($pdo, $current_user['id_user'], "Transaksi Masuk: Plat $plat_nomor (Tiket #$new_id_parkir) di {$area['nama_area']}");

                        $pdo->commit();

                        set_flash('success', "Transaksi Masuk berhasil! Kendaraan '$plat_nomor' tercatat di {$area['nama_area']}.");
                        // Redirect langsung ke cetak karcis masuk
                        redirect(BASE_URL . '/modules/petugas/cetak.php?id=' . $new_id_parkir . '&type=masuk');
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
            }
        }
    }
}

// Ambil data pilihan area parkir dengan status slot terkini
$areas = $pdo->query("SELECT id_area, nama_area, kapasitas, terisi, (kapasitas - terisi) AS slot_kosong FROM tb_area_parkir ORDER BY id_area ASC")->fetchAll();

$page_title = 'Pencatatan Transaksi Masuk';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="max-w-3xl mx-auto space-y-6">
                
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Check-In Kendaraan Masuk</h2>
                        <p class="text-xs sm:text-sm text-slate-500">Pencatatan karcis masuk gerbang parkir</p>
                    </div>
                    <a href="<?= BASE_URL ?>/modules/petugas/transaksi.php" class="text-xs font-semibold text-slate-500 hover:text-slate-800">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Transaksi
                    </a>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm flex items-start space-x-3">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 text-base mt-0.5"></i>
                        <div class="flex-1">
                            <strong class="font-bold">Transaksi Gagal:</strong>
                            <p class="mt-0.5"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php require_once __DIR__ . '/../../includes/alerts.php'; ?>

                <!-- Form Card -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8">
                    <form action="" method="POST" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <!-- Plat Nomor Input Utama -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                Plat Nomor Kendaraan <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-car-rear text-base"></i>
                                </div>
                                <input type="text" name="plat_nomor" required autofocus placeholder="Contoh: B 1234 ABC"
                                    class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-lg font-mono-code font-bold uppercase tracking-wider text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Huruf dan angka plat nomor otomatis dijadikan huruf besar.</p>
                        </div>

                        <!-- Jenis Kendaraan & Warna -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Jenis Kendaraan <span class="text-rose-500">*</span>
                                </label>
                                <select name="jenis_kendaraan" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="motor">Sepeda Motor</option>
                                    <option value="mobil" selected>Mobil Penumpang</option>
                                    <option value="lainnya">Lainnya (Bus / Truk)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                    Warna Kendaraan
                                </label>
                                <input type="text" name="warna" placeholder="Contoh: Putih, Hitam, Merah"
                                    class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>

                        <!-- Pemilik Kendaraan -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                Nama Pengendara / Pemilik <span class="text-slate-400 font-normal">(Opsional)</span>
                            </label>
                            <input type="text" name="pemilik" placeholder="Contoh: Pengendara Umum"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <!-- Pemilihan Area Parkir (Dengan Indikator Kuota) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                Pilih Area Parkir <span class="text-rose-500">*</span>
                            </label>
                            <div class="space-y-2">
                                <?php foreach ($areas as $idx => $ar): 
                                    $isFull = ($ar['slot_kosong'] <= 0);
                                ?>
                                    <label class="flex items-center justify-between p-3.5 border rounded-xl cursor-pointer transition-all <?= $isFull ? 'bg-slate-100 border-slate-200 opacity-60 cursor-not-allowed' : 'bg-slate-50 border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/20' ?>">
                                        <div class="flex items-center space-x-3">
                                            <input type="radio" name="id_area" value="<?= $ar['id_area'] ?>" <?= ($idx === 0 && !$isFull) ? 'checked' : '' ?> <?= $isFull ? 'disabled' : '' ?>
                                                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <span class="text-xs font-bold text-slate-900"><?= htmlspecialchars($ar['nama_area']) ?></span>
                                                <p class="text-[11px] text-slate-500">Total kapasitas: <?= $ar['kapasitas'] ?> slot</p>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if ($isFull): ?>
                                                <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-rose-100 text-rose-700 border border-rose-200">
                                                    Penuh (0 Slot)
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                    Tersedia: <?= $ar['slot_kosong'] ?> Slot
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-6 border-t border-slate-100 flex items-center justify-end space-x-3">
                            <a href="<?= BASE_URL ?>/modules/petugas/index.php" class="px-4 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                                Batal
                            </a>
                            <button type="submit" class="px-6 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center">
                                <i class="fa-solid fa-ticket mr-2"></i> Cetak Karcis & Masuk
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </main>

        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
