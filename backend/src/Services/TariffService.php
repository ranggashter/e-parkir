<?php

namespace App\Services;

use App\Support\Database;
use App\Support\Session;
use InvalidArgumentException;
use RuntimeException;

class TariffService
{
    public function listTariffs(bool $activeOnly = false): array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT id_tarif, jenis_kendaraan, tarif_per_jam, status_aktif FROM tb_tarif";
        if ($activeOnly) {
            $sql .= " WHERE status_aktif = 1";
        }
        $sql .= " ORDER BY id_tarif ASC";
        return $pdo->query($sql)->fetchAll();
    }

    public function getTariff(int $id): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM tb_tarif WHERE id_tarif = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Tarif dengan ID {$id} tidak ditemukan.", 404);
        }
        return $row;
    }

    public function getActiveTariffByVehicle(string $jenisKendaraan): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM tb_tarif 
            WHERE LOWER(jenis_kendaraan) = LOWER(:jenis) AND status_aktif = 1
            LIMIT 1
        ");
        $stmt->execute(['jenis' => $jenisKendaraan]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Tarif untuk jenis kendaraan '{$jenisKendaraan}' tidak ditemukan.", 404);
        }
        return $row;
    }

    public function createTariff(array $data): array
    {
        $jenis = strtolower(trim($data['jenis_kendaraan'] ?? ''));
        $tarif = $data['tarif_per_jam'] ?? null;

        if ($jenis === '' || $tarif === null) {
            throw new InvalidArgumentException("Jenis kendaraan dan tarif per jam wajib diisi.", 422);
        }
        if (!in_array($jenis, ['motor', 'mobil', 'lainnya'], true)) {
            throw new InvalidArgumentException("Jenis kendaraan tidak valid.", 422);
        }

        if (!is_numeric($tarif) || (float)$tarif < 0) {
            throw new InvalidArgumentException("Tarif per jam harus berupa angka non-negatif.", 422);
        }

        $pdo = Database::getConnection();
        $check = $pdo->prepare("SELECT id_tarif FROM tb_tarif WHERE LOWER(jenis_kendaraan) = :j");
        $check->execute(['j' => $jenis]);
        if ($check->fetch()) {
            throw new InvalidArgumentException("Tarif untuk jenis kendaraan '{$jenis}' sudah terdaftar.", 409);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tb_tarif (jenis_kendaraan, tarif_per_jam)
            VALUES (:jenis, :tarif)
        ");
        $stmt->execute([
            'jenis' => $jenis,
            'tarif' => number_format((float)$tarif, 2, '.', ''),
        ]);

        $id = (int) $pdo->lastInsertId();
        $currentUser = Session::getUser();
        AuditService::log(
            $currentUser['id_user'] ?? null,
            "Admin menambahkan tarif baru untuk {$jenis}: Rp" . number_format((float)$tarif, 2)
        );

        return $this->getTariff($id);
    }

    public function updateTariff(int $id, array $data): array
    {
        $existing = $this->getTariff($id);
        $tarif = $data['tarif_per_jam'] ?? $existing['tarif_per_jam'];

        if (!is_numeric($tarif) || (float)$tarif < 0) {
            throw new InvalidArgumentException("Tarif per jam harus berupa angka non-negatif.", 422);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE tb_tarif 
            SET tarif_per_jam = :tarif
            WHERE id_tarif = :id
        ");
        $stmt->execute([
            'tarif' => number_format((float)$tarif, 2, '.', ''),
            'id' => $id,
        ]);

        $currentUser = Session::getUser();
        AuditService::log(
            $currentUser['id_user'] ?? null,
            "Admin mengubah tarif ID {$id} ({$existing['jenis_kendaraan']}): Rp" . number_format((float)$tarif, 2)
        );

        return $this->getTariff($id);
    }
}
