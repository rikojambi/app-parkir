-- ========================================================
-- SISTEM MANAJEMEN PARKIR TERPADU (APLIKASI PARKIR)
-- Software House Enterprise Standard
-- Stack: PHP & MySQL (InnoDB, Prepared Statements, UTF8MB4)
-- ========================================================

CREATE DATABASE IF NOT EXISTS `db_parkir` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_parkir`;

-- --------------------------------------------------------
-- 1. TABEL: tb_user
-- Menyimpan kredensial pengguna dan role sistem
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tb_log_aktivitas`;
DROP TABLE IF EXISTS `tb_transaksi`;
DROP TABLE IF EXISTS `tb_kendaraan`;
DROP TABLE IF EXISTS `tb_area_parkir`;
DROP TABLE IF EXISTS `tb_tarif`;
DROP TABLE IF EXISTS `tb_user`;

CREATE TABLE `tb_user` (
  `id_user` INT(11) NOT NULL AUTO_INCREMENT,
  `nama_lengkap` VARCHAR(50) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'petugas', 'owner') NOT NULL,
  `status_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `idx_role_status` (`role`, `status_aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. TABEL: tb_tarif
-- Menyimpan tarif parkir flat per jam sesuai jenis kendaraan
-- --------------------------------------------------------
CREATE TABLE `tb_tarif` (
  `id_tarif` INT(11) NOT NULL AUTO_INCREMENT,
  `jenis_kendaraan` ENUM('motor', 'mobil', 'lainnya') NOT NULL,
  `tarif_per_jam` DECIMAL(10,0) NOT NULL,
  PRIMARY KEY (`id_tarif`),
  UNIQUE KEY `uq_jenis_kendaraan` (`jenis_kendaraan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. TABEL: tb_area_parkir
-- Menyimpan zonasi parkir beserta kapasitas dan kuota terisi
-- --------------------------------------------------------
CREATE TABLE `tb_area_parkir` (
  `id_area` INT(11) NOT NULL AUTO_INCREMENT,
  `nama_area` VARCHAR(50) NOT NULL,
  `kapasitas` INT(5) NOT NULL,
  `terisi` INT(5) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_area`),
  KEY `idx_nama_area` (`nama_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. TABEL: tb_kendaraan
-- Menyimpan master data kendaraan yang terdaftar dalam sistem
-- --------------------------------------------------------
CREATE TABLE `tb_kendaraan` (
  `id_kendaraan` INT(11) NOT NULL AUTO_INCREMENT,
  `plat_nomor` VARCHAR(15) NOT NULL,
  `jenis_kendaraan` VARCHAR(20) NOT NULL,
  `warna` VARCHAR(20) NOT NULL,
  `pemilik` VARCHAR(100) NOT NULL,
  `id_user` INT(11) NOT NULL,
  PRIMARY KEY (`id_kendaraan`),
  UNIQUE KEY `uq_plat_nomor` (`plat_nomor`),
  KEY `fk_kendaraan_user` (`id_user`),
  CONSTRAINT `fk_kendaraan_user` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. TABEL: tb_transaksi
-- Mencatat siklus parkir (waktu masuk, keluar, durasi, biaya)
-- --------------------------------------------------------
CREATE TABLE `tb_transaksi` (
  `id_parkir` INT(11) NOT NULL AUTO_INCREMENT,
  `id_kendaraan` INT(11) NOT NULL,
  `waktu_masuk` DATETIME NOT NULL,
  `waktu_keluar` DATETIME DEFAULT NULL,
  `id_tarif` INT(11) NOT NULL,
  `durasi_jam` INT(5) DEFAULT NULL,
  `biaya_total` DECIMAL(10,0) DEFAULT NULL,
  `status` ENUM('masuk', 'keluar') NOT NULL DEFAULT 'masuk',
  `id_user` INT(11) NOT NULL,
  `id_area` INT(11) NOT NULL,
  PRIMARY KEY (`id_parkir`),
  KEY `fk_transaksi_kendaraan` (`id_kendaraan`),
  KEY `fk_transaksi_tarif` (`id_tarif`),
  KEY `fk_transaksi_user` (`id_user`),
  KEY `fk_transaksi_area` (`id_area`),
  KEY `idx_status_masuk` (`status`, `waktu_masuk`),
  KEY `idx_waktu_keluar` (`waktu_keluar`),
  CONSTRAINT `fk_transaksi_kendaraan` FOREIGN KEY (`id_kendaraan`) REFERENCES `tb_kendaraan` (`id_kendaraan`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_transaksi_tarif` FOREIGN KEY (`id_tarif`) REFERENCES `tb_tarif` (`id_tarif`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_transaksi_area` FOREIGN KEY (`id_area`) REFERENCES `tb_area_parkir` (`id_area`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. TABEL: tb_log_aktivitas
-- Menyimpan jejak audit sistem secara otomatis
-- --------------------------------------------------------
CREATE TABLE `tb_log_aktivitas` (
  `id_log` INT(11) NOT NULL AUTO_INCREMENT,
  `id_user` INT(11) NOT NULL,
  `aktivitas` VARCHAR(100) NOT NULL,
  `waktu_aktivitas` DATETIME NOT NULL,
  PRIMARY KEY (`id_log`),
  KEY `fk_log_user` (`id_user`),
  KEY `idx_waktu_aktivitas` (`waktu_aktivitas`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- DATA SEED AWAL (INITIAL SEED DATA)
-- Password default semua user: password123
-- Hash Bcrypt: $2y$10$VxcoV2ROArvyF1qQBwMiLOvq6cktHwjVHW6kv1aFbk/sYdBTleI4S
-- ========================================================

-- Insert Data User (Admin, Petugas, Owner)
INSERT INTO `tb_user` (`id_user`, `nama_lengkap`, `username`, `password`, `role`, `status_aktif`) VALUES
(1, 'Administrator Utama', 'admin', '$2y$10$VxcoV2ROArvyF1qQBwMiLOvq6cktHwjVHW6kv1aFbk/sYdBTleI4S', 'admin', 1),
(2, 'Budi Santoso (Petugas)', 'petugas', '$2y$10$VxcoV2ROArvyF1qQBwMiLOvq6cktHwjVHW6kv1aFbk/sYdBTleI4S', 'petugas', 1),
(3, 'Siti Rahma (Petugas 2)', 'petugas2', '$2y$10$VxcoV2ROArvyF1qQBwMiLOvq6cktHwjVHW6kv1aFbk/sYdBTleI4S', 'petugas', 1),
(4, 'Hendra Wijaya (Owner)', 'owner', '$2y$10$VxcoV2ROArvyF1qQBwMiLOvq6cktHwjVHW6kv1aFbk/sYdBTleI4S', 'owner', 1);

-- Insert Data Tarif Parkir
INSERT INTO `tb_tarif` (`id_tarif`, `jenis_kendaraan`, `tarif_per_jam`) VALUES
(1, 'motor', 2000),
(2, 'mobil', 5000),
(3, 'lainnya', 10000);

-- Insert Data Area Parkir
INSERT INTO `tb_area_parkir` (`id_area`, `nama_area`, `kapasitas`, `terisi`) VALUES
(1, 'Gedung A - Basement Motor', 50, 1),
(2, 'Gedung B - Lobby Utama Mobil', 30, 1),
(3, 'Area Terbuka - Truk & Bus', 10, 0);

-- Insert Data Kendaraan
INSERT INTO `tb_kendaraan` (`id_kendaraan`, `plat_nomor`, `jenis_kendaraan`, `warna`, `pemilik`, `id_user`) VALUES
(1, 'B 1234 ABC', 'motor', 'Hitam', 'Ahmad Dhani', 1),
(2, 'B 5678 XYZ', 'mobil', 'Putih', 'Rudi Pratama', 1),
(3, 'B 9999 BOS', 'mobil', 'Hitam Metalik', 'Hendra Wijaya', 1),
(4, 'D 4321 JKT', 'motor', 'Merah', 'Dewi Lestari', 2);

-- Insert Data Transaksi (Satu selesai keluar, dua masih aktif di dalam)
INSERT INTO `tb_transaksi` (`id_parkir`, `id_kendaraan`, `waktu_masuk`, `waktu_keluar`, `id_tarif`, `durasi_jam`, `biaya_total`, `status`, `id_user`, `id_area`) VALUES
(1, 1, DATE_SUB(NOW(), INTERVAL 3 HOUR), NOW(), 1, 3, 6000, 'keluar', 2, 1),
(2, 2, DATE_SUB(NOW(), INTERVAL 2 HOUR), NULL, 2, NULL, NULL, 'masuk', 2, 2),
(3, 4, DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, 1, NULL, NULL, 'masuk', 2, 1);

-- Insert Log Aktivitas Awal
INSERT INTO `tb_log_aktivitas` (`id_log`, `id_user`, `aktivitas`, `waktu_aktivitas`) VALUES
(1, 1, 'Inisialisasi sistem dan data master', NOW()),
(2, 2, 'Transaksi Masuk Kendaraan Plat: B 1234 ABC', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, 2, 'Transaksi Keluar Kendaraan Plat: B 1234 ABC (Biaya: Rp 6.000)', NOW()),
(4, 2, 'Transaksi Masuk Kendaraan Plat: B 5678 XYZ', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(5, 2, 'Transaksi Masuk Kendaraan Plat: D 4321 JKT', DATE_SUB(NOW(), INTERVAL 1 HOUR));
