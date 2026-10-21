-- =============================================================================
-- Stored Procedure: sp_parkir_keluar
-- Conforms to DATABASE.md section 6
-- =============================================================================

DELIMITER $$

CREATE PROCEDURE `sp_parkir_keluar`(
    IN p_id_parkir BIGINT UNSIGNED,
    IN p_id_user BIGINT UNSIGNED,
    OUT p_status_code INT,
    OUT p_message VARCHAR(255)
)
proc: BEGIN
    DECLARE v_id_area BIGINT UNSIGNED;
    DECLARE v_id_kendaraan BIGINT UNSIGNED;
    DECLARE v_waktu_masuk DATETIME;
    DECLARE v_tarif_snapshot DECIMAL(12,2);
    DECLARE v_status_transaksi VARCHAR(20);
    DECLARE v_waktu_keluar DATETIME;
    DECLARE v_durasi_menit INT;
    DECLARE v_durasi_jam INT;
    DECLARE v_biaya_total DECIMAL(12,2);
    DECLARE v_terisi INT;
    DECLARE v_plat VARCHAR(20);

    -- Kunci baris transaksi
    SELECT id_area, id_kendaraan, waktu_masuk, tarif_per_jam_snapshot, status
    INTO v_id_area, v_id_kendaraan, v_waktu_masuk, v_tarif_snapshot, v_status_transaksi
    FROM tb_transaksi
    WHERE id_parkir = p_id_parkir
    FOR UPDATE;

    IF v_id_area IS NULL THEN
        SET p_status_code = 404;
        SET p_message = 'Transaksi parkir tidak ditemukan.';
        LEAVE proc;
    END IF;

    IF v_status_transaksi != 'masuk' THEN
        SET p_status_code = 409;
        SET p_message = 'Transaksi sudah ditutup sebelumnya.';
        LEAVE proc;
    END IF;

    -- Kunci baris area
    SELECT terisi INTO v_terisi
    FROM tb_area_parkir
    WHERE id_area = v_id_area
    FOR UPDATE;

    -- Hitung durasi dan biaya
    SET v_waktu_keluar = NOW();
    SET v_durasi_menit = TIMESTAMPDIFF(MINUTE, v_waktu_masuk, v_waktu_keluar);
    IF v_durasi_menit < 1 THEN
        SET v_durasi_menit = 1;
    END IF;
    SET v_durasi_jam = CEIL(v_durasi_menit / 60);
    IF v_durasi_jam < 1 THEN
        SET v_durasi_jam = 1;
    END IF;
    SET v_biaya_total = v_durasi_jam * v_tarif_snapshot;

    -- Update transaksi
    UPDATE tb_transaksi
    SET waktu_keluar = v_waktu_keluar,
        durasi_jam = v_durasi_jam,
        biaya_total = v_biaya_total,
        petugas_keluar_id = p_id_user,
        status = 'keluar',
        updated_at = NOW()
    WHERE id_parkir = p_id_parkir;

    -- Kurangi terisi pada area
    UPDATE tb_area_parkir
    SET terisi = GREATEST(0, CAST(terisi AS SIGNED) - 1),
        updated_at = NOW()
    WHERE id_area = v_id_area;

    -- Ambil plat nomor untuk audit log
    SELECT plat_nomor INTO v_plat FROM tb_kendaraan WHERE id_kendaraan = v_id_kendaraan;

    -- Catat log aktivitas
    INSERT INTO tb_log_aktivitas (id_user, aktivitas, metadata, ip_address, waktu_aktivitas)
    VALUES (p_id_user, CONCAT('Kendaraan keluar: ', v_plat, ', durasi ', v_durasi_jam, ' jam, total Rp', FORMAT(v_biaya_total, 2)), 
            JSON_OBJECT('id_parkir', p_id_parkir, 'biaya_total', v_biaya_total, 'durasi_jam', v_durasi_jam),
            '127.0.0.1', NOW());

    SET p_status_code = 200;
    SET p_message = 'Kendaraan berhasil keluar.';
END$$

DELIMITER ;

