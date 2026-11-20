<?php
/**
 * ========================================================
 * Modul Pencatatan Log Aktivitas Sistem (Audit Trail)
 * ========================================================
 */

/**
 * Mencatat aktivitas pengguna ke dalam tb_log_aktivitas
 * Menggunakan Prepared Statements untuk integritas dan performa tinggi
 * 
 * @param PDO $pdo
 * @param int $id_user
 * @param string $aktivitas
 * @return bool
 */
function catat_log($pdo, $id_user, $aktivitas) {
    if (empty($id_user) || empty($aktivitas)) {
        return false;
    }

    try {
        // Truncate aktivitas jika melebihi panjang kolom VARCHAR(100)
        $aktivitas_clean = mb_substr(trim($aktivitas), 0, 100);

        $stmt = $pdo->prepare("
            INSERT INTO `tb_log_aktivitas` (`id_user`, `aktivitas`, `waktu_aktivitas`)
            VALUES (:id_user, :aktivitas, NOW())
        ");
        
        return $stmt->execute([
            ':id_user'   => (int)$id_user,
            ':aktivitas' => $aktivitas_clean
        ]);
    } catch (PDOException $e) {
        // Jangan hentikan flow aplikasi jika logging audit gagal, namun catat di server error_log
        error_log("Gagal mencatat log aktivitas: " . $e->getMessage());
        return false;
    }
}
