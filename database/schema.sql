-- =============================================================================
-- E-Parking UKK RPL - Database Schema
-- Conforms to DATABASE.md & PROJECT.md
-- Engine: InnoDB, Charset: utf8mb4, Collation: utf8mb4_unicode_ci
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tb_user` (
  `id_user` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama_lengkap` VARCHAR(150) NOT NULL,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'petugas', 'owner') NOT NULL,
  `status_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_username` (`username`),
  INDEX `idx_user_role` (`role`),
  INDEX `idx_user_status` (`status_aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_tarif` (
  `id_tarif` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `jenis_kendaraan` VARCHAR(50) NOT NULL UNIQUE,
  `tarif_per_jam` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tarif_jenis` (`jenis_kendaraan`),
  INDEX `idx_tarif_status` (`status_aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_area_parkir` (
  `id_area` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama_area` VARCHAR(120) NOT NULL UNIQUE,
  `kapasitas` INT UNSIGNED NOT NULL,
  `terisi` INT UNSIGNED NOT NULL DEFAULT 0,
  `status_aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_area_status` (`status_aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_kendaraan` (
  `id_kendaraan` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `plat_nomor` VARCHAR(20) NOT NULL,
  `jenis_kendaraan` VARCHAR(50) NOT NULL,
  `warna` VARCHAR(50) NULL,
  `pemilik` VARCHAR(150) NULL,
  `dibuat_oleh_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_kendaraan_plat` (`plat_nomor`),
  INDEX `idx_kendaraan_jenis` (`jenis_kendaraan`),
  CONSTRAINT `fk_kendaraan_user` FOREIGN KEY (`dibuat_oleh_user_id`)
    REFERENCES `tb_user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_transaksi` (
  `id_parkir` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_kendaraan` BIGINT UNSIGNED NOT NULL,
  `id_area` BIGINT UNSIGNED NOT NULL,
  `id_tarif` BIGINT UNSIGNED NOT NULL,
  `petugas_masuk_id` BIGINT UNSIGNED NOT NULL,
  `petugas_keluar_id` BIGINT UNSIGNED NULL,
  `waktu_masuk` DATETIME NOT NULL,
  `waktu_keluar` DATETIME NULL,
  `plat_nomor_snapshot` VARCHAR(20) NULL,
  `jenis_kendaraan_snapshot` VARCHAR(50) NULL,
  `warna_snapshot` VARCHAR(50) NULL,
  `tarif_per_jam_snapshot` DECIMAL(12,2) NOT NULL,
  `durasi_jam` INT UNSIGNED NULL,
  `biaya_total` DECIMAL(12,2) NULL,
  `status` ENUM('masuk', 'keluar') NOT NULL DEFAULT 'masuk',
  `status_pembayaran` ENUM('belum_lunas', 'lunas', 'legacy_unknown') NOT NULL DEFAULT 'belum_lunas',
  `metode_pembayaran` ENUM('tunai') NULL,
  `uang_diterima` DECIMAL(12,2) NULL,
  `uang_kembalian` DECIMAL(12,2) NULL,
  `dibayar_oleh_user_id` BIGINT UNSIGNED NULL,
  `dibayar_pada` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_transaksi_status_masuk` (`status`, `waktu_masuk`),
  INDEX `idx_transaksi_status_area` (`status`, `id_area`),
  INDEX `idx_transaksi_keluar` (`waktu_keluar`),
  CONSTRAINT `fk_transaksi_kendaraan` FOREIGN KEY (`id_kendaraan`)
    REFERENCES `tb_kendaraan` (`id_kendaraan`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_area` FOREIGN KEY (`id_area`)
    REFERENCES `tb_area_parkir` (`id_area`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_tarif` FOREIGN KEY (`id_tarif`)
    REFERENCES `tb_tarif` (`id_tarif`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_petugas_masuk` FOREIGN KEY (`petugas_masuk_id`)
    REFERENCES `tb_user` (`id_user`) ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_petugas_keluar` FOREIGN KEY (`petugas_keluar_id`)
    REFERENCES `tb_user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_dibayar_oleh` FOREIGN KEY (`dibayar_oleh_user_id`)
    REFERENCES `tb_user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_transaksi_pembayaran` (`status_pembayaran`, `dibayar_pada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_log_aktivitas` (
  `id_log` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_user` BIGINT UNSIGNED NULL,
  `aktivitas` VARCHAR(255) NOT NULL,
  `metadata` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `waktu_aktivitas` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_log_user` 66id_user`),
  INDEX `idx_log_waktu` (`waktu_aktivitas`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`id_user`)
    REFERENCES `tb_user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULTuuuuRSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View agregat laporan rekap transaksi (hanya transaksi selesai 'keluar', tanpa data rahasia user)
CREATE OR REPLACE VIEW `v_rekap_transaksi` AS
SELECT ''
  DATE(t.waktu_keluar) AS tanggal,
  COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan) AS jenis_kendaraan,
  COUNT(t.id_parkir) AS jumlah_transaksi,
  SUM(t.biaya_total) AS total_tagihan,
  SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN t.biaya_total ELSE 0 END) AS total_terbayar,
  SUM(CASE WHEN t.status_pembayaran = 'belum_lunas' THEN t.biaya_total ELSE 0 END) AS total_belum_lunas,
  AVG(t.durasi_jam) AS rata_rata_durasi_jam
FROM `tb_transaksi` t
JOIN `tb_kendaraan` k ON t.id_kendaraan = k.id_kendaraan
WHERE t.status = 'keluar' AND t.waktu_keluar IS NOT NULL
GROUP BY DATE(t.waktu_keluar), COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan);
