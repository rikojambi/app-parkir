<?php
/**
 * ========================================================
 * Modul Inti Logika Bisnis & Perhitungan Parkir
 * ========================================================
 */

/**
 * Menghitung durasi parkir (dalam jam pembulatan ke atas) dan total biaya
 * Berdasarkan selisih waktu masuk dan keluar dikalikan tarif per jam
 * 
 * @param string $waktu_masuk  Format 'Y-m-d H:i:s'
 * @param string $waktu_keluar Format 'Y-m-d H:i:s'
 * @param float|int $tarif_per_jam Tarif dari tb_tarif
 * @return array ['durasi_jam' => int, 'durasi_menit' => int, 'biaya_total' => float]
 */
function hitung_durasi_dan_biaya($waktu_masuk, $waktu_keluar, $tarif_per_jam) {
    $t_masuk = strtotime($waktu_masuk);
    $t_keluar = strtotime($waktu_keluar);

    // Antisipasi jika waktu keluar lebih awal dari waktu masuk karena sinkronisasi jam
    if ($t_keluar < $t_masuk) {
        $t_keluar = $t_masuk;
    }

    $selisih_detik = max(0, $t_keluar - $t_masuk);
    $selisih_menit = (int)ceil($selisih_detik / 60);

    // Kebijakan parkir komersial standar: pembulatan ke atas per jam penuh
    // Durasi minimum dihitung 1 jam (misal 5 menit tetap 1 jam)
    $durasi_jam = (int)ceil($selisih_detik / 3600);
    if ($durasi_jam < 1) {
        $durasi_jam = 1;
    }

    $biaya_total = $durasi_jam * (float)$tarif_per_jam;

    return [
        'selisih_detik' => $selisih_detik,
        'durasi_menit'  => $selisih_menit,
        'durasi_jam'    => $durasi_jam,
        'tarif_per_jam' => (float)$tarif_per_jam,
        'biaya_total'   => $biaya_total
    ];
}

/**
 * Memeriksa status kapasitas area parkir
 * 
 * @param PDO $pdo
 * @param int $id_area
 * @return array|false Informasi ketersediaan area
 */
function cek_kapasitas_area($pdo, $id_area) {
    $stmt = $pdo->prepare("
        SELECT id_area, nama_area, kapasitas, terisi, 
               (kapasitas - terisi) AS sisa_slot
        FROM tb_area_parkir 
        WHERE id_area = :id_area 
        LIMIT 1
    ");
    $stmt->execute([':id_area' => (int)$id_area]);
    $area = $stmt->fetch();

    if (!$area) {
        return false;
    }

    $area['is_penuh'] = ($area['terisi'] >= $area['kapasitas']);
    $area['persentase'] = $area['kapasitas'] > 0 
        ? round(($area['terisi'] / $area['kapasitas']) * 100, 1) 
        : 0;

    return $area;
}

/**
 * Mengubah jumlah kendaraan terisi pada tb_area_parkir secara atomik
 * 
 * @param PDO $pdo
 * @param int $id_area
 * @param string $aksi 'masuk' atau 'keluar'
 * @return bool Berhasil atau gagal
 */
function update_kapasitas_area($pdo, $id_area, $aksi) {
    if ($aksi === 'masuk') {
        // Query atomic increment dengan validasi guard terisi < kapasitas
        $stmt = $pdo->prepare("
            UPDATE tb_area_parkir 
            SET terisi = terisi + 1 
            WHERE id_area = :id_area AND terisi < kapasitas
        ");
        $stmt->execute([':id_area' => (int)$id_area]);
        return ($stmt->rowCount() > 0);
    } elseif ($aksi === 'keluar') {
        // Query atomic decrement menjaga agar terisi tidak negatif
        $stmt = $pdo->prepare("
            UPDATE tb_area_parkir 
            SET terisi = GREATEST(0, terisi - 1) 
            WHERE id_area = :id_area
        ");
        return $stmt->execute([':id_area' => (int)$id_area]);
    }
    return false;
}

/**
 * Format mata uang Rupiah Indonesia
 * @param float|int $nominal
 * @return string
 */
function format_rupiah($nominal) {
    return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
}

/**
 * Format tanggal dan jam bahasa Indonesia
 * @param string $datetime
 * @param bool $show_time
 * @return string
 */
function format_tanggal_indo($datetime, $show_time = true) {
    if (empty($datetime)) return '-';
    
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $time = strtotime($datetime);
    $hari = date('d', $time);
    $bln  = $bulan[(int)date('m', $time)];
    $thn  = date('Y', $time);

    $hasil = "$hari $bln $thn";
    if ($show_time) {
        $hasil .= ' ' . date('H:i:s', $time) . ' WIB';
    }
    return $hasil;
}

/**
 * Generate nomor tiket parkir unik yang mudah dibaca dan di-scan
 * @param int $id_transaksi
 * @return string
 */
function format_tiket_parkir($id_transaksi) {
    return 'PKR-' . date('Ymd') . '-' . str_pad($id_transaksi, 5, '0', STR_PAD_LEFT);
}
