-- Additive migration for payment tracking and per-transaction correction snapshots.
-- Review against the real schema first; existing installations may use different column names.
-- Existing transactions receive legacy_unknown so they are not incorrectly marked paid or unpaid.
-- DO NOT run this against a database containing user data without an approved backup and explicit authorization.

ALTER TABLE `tb_transaksi`
  ADD COLUMN `plat_nomor_snapshot` VARCHAR(20) NULL AFTER `waktu_keluar`,
  ADD COLUMN `jenis_kendaraan_snapshot` VARCHAR(50) NULL AFTER `plat_nomor_snapshot`,
  ADD COLUMN `warna_snapshot` VARCHAR(50) NULL AFTER `jenis_kendaraan_snapshot`,
  ADD COLUMN `status_pembayaran` ENUM('belum_lunas', 'lunas', 'legacy_unknown')
    NOT NULL DEFAULT 'legacy_unknown' AFTER `status`,
  ADD COLUMN `metode_pembayaran` ENUM('tunai') NULL AFTER `status_pembayaran`,
  ADD COLUMN `uang_diterima` DECIMAL(12,2) NULL AFTER `metode_pembayaran`,
  ADD COLUMN `uang_kembalian` DECIMAL(12,2) NULL AFTER `uang_diterima`,
  ADD COLUMN `dibayar_oleh_user_id` BIGINT UNSIGNED NULL AFTER `uang_kembalian`,
  ADD COLUMN `dibayar_pada` DATETIME NULL AFTER `dibayar_oleh_user_id`,
  ADD INDEX `idx_transaksi_pembayaran` (`status_pembayaran`, `dibayar_pada`),
  ADD CONSTRAINT `fk_transaksi_dibayar_oleh`
    FOREIGN KEY (`dibayar_oleh_user_id`) REFERENCES `tb_user` (`id_user`)
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `tb_transaksi`
  ALTER COLUMN `status_pembayaran` SET DEFAULT 'belum_lunas';
