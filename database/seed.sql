-- =============================================================================
-- E-Parking UKK RPL - Initial Seed Data
-- =============================================================================

-- Default Users
-- admin / admin123
-- petugas1 / petugas123
-- owner1 / owner123
INSERT INTO `tb_user` (`id_user`, `nama_lengkap`, `username`, `password`, `role`, `status_aktif`, `created_at`, `updated_at`) VALUES
(1, 'Administrator Sistem', 'admin', '$2y$10$5rL2K.B8I5uauIr18ywJMu5ZmIchTivXjXkeDhVTyd6C.qCPQzagq', 'admin', 1, NOW(), NOW()),
(2, 'Petugas Loket 1', 'petugas1', '$2y$10$WWip4BOk6Y4ZhYKNgmuIqeeM6UCRPlSnTD9u79mehNCNKIcn5FABq', 'petugas', 1, NOW(), NOW()),
(3, 'Owner / Pimpinan', 'owner1', '$2y$10$pJ1vRM2wlZvl4HinoZ2mBuHiHvL9xrsQ0EmYmWZf6yr4CcG6av9de', 'owner', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `nama_lengkap` = VALUES(`nama_lengkap`);

-- Default Tariffs
INSERT INTO `tb_tarif` (`id_tarif`, `jenis_kendaraan`, `tarif_per_jam`, `status_aktif`, `created_at`, `updated_at`) VALUES
(1, 'motor', 2000.00, 1, NOW(), NOW()),
(2, 'mobil', 5000.00, 1, NOW(), NOW()),
(3, 'lainnya', 10000.00, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `tarif_per_jam` = VALUES(`tarif_per_jam`);

-- Default Parking Areas
INSERT INTO `tb_area_parkir` (`id_area`, `nama_area`, `kapasitas`, `terisi`, `status_aktif`, `created_at`, `updated_at`) VALUES
(1, 'Area A - Motor', 50, 0, 1, NOW(), NOW()),
(2, 'Area B - Mobil', 30, 0, 1, NOW(), NOW()),
(3, 'Area C - Umum', 20, 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `kapasitas` = VALUES(`kapasitas`);

-- Initial Audit Log
INSERT INTO `tb_log_aktivitas` (`id_user`, `aktivitas`, `metadata`, `ip_address`, `waktu_aktivitas`) VALUES
(1, 'Sistem diinisialisasi dengan data default', '{"action": "system_init"}', '127.0.0.1', NOW());

