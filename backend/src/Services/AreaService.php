<?php

namespace App\Services;

use App\Support\Database;
use App\Support\Session;
use InvalidArgumentException;
use RuntimeException;

class AreaService
{
    public function listAreas(bool $activeOnly = false): array
    {
        $pdo = Database::getConnection();
        $sql = "
            SELECT id_area, nama_area, kapasitas, terisi, 
                   (kapasitas - terisi) AS sisa_kapasitas, status_aktif
            FROM tb_area_parkir
            " . ($activeOnly ? 'WHERE status_aktif = 1' : '') . "
            ORDER BY id_area ASC
        ";
        return $pdo->query($sql)->fetchAll();
    }

    public function getArea(int $id): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT id_area, nama_area, kapasitas, terisi, 
                   (kapasitas - terisi) AS sisa_kapasitas, status_aktif
            FROM tb_area_parkir 
            WHERE id_area = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Area parkir dengan ID {$id} tidak ditemukan.", 404);
        }
        return $row;
    }

    public function createArea(array $data): array
    {
        $namaArea = trim($data['nama_area'] ?? '');
        $kapasitas = $data['kapasitas'] ?? null;

        if ($namaArea === '' || $kapasitas === null) {
            throw new InvalidArgumentException("Nama area dan kapasitas wajib diisi.", 422);
        }

        $kapasitasInt = (int) $kapasitas;
        if ($kapasitasInt <= 0) {
            throw new InvalidArgumentException("Kapasitas harus berupa bilangan bulat positif lebih besar dari nol.", 422);
        }

        $pdo = Database::getConnection();
        $check = $pdo->prepare("SELECT id_area FROM tb_area_parkir WHERE LOWER(nama_area) = LOWER(:nama)");
        $check->execute(['nama' => $namaArea]);
        if ($check->fetch()) {
            throw new InvalidArgumentException("Nama area parkir '{$namaArea}' sudah digunakan.", 409);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tb_area_parkir (nama_area, kapasitas, terisi)
            VALUES (:nama, :kapasitas, 0)
        ");
        $stmt->execute([
            'nama' => $namaArea,
            'kapasitas' => $kapasitasInt,
        ]);

        $id = (int) $pdo->lastInsertId();
        $currentUser = Session::getUser();
        AuditService::log(
            $currentUser['id_user'] ?? null,
            "Admin membuat area parkir baru: '{$namaArea}' (kapasitas: {$kapasitasInt})"
        );

        return $this->getArea($id);
    }

    public function updateArea(int $id, array $data): array
    {
        $existing = $this->getArea($id);
        $namaArea = trim($data['nama_area'] ?? $existing['nama_area']);
        $kapasitas = isset($data['kapasitas']) ? (int) $data['kapasitas'] : (int) $existing['kapasitas'];

        if ($kapasitas <= 0) {
            throw new InvalidArgumentException("Kapasitas harus lebih besar dari nol.", 422);
        }

        // Aturan F-04: Kapasitas tidak boleh lebih kecil daripada jumlah terisi
        if ($kapasitas < (int)$existing['terisi']) {
            throw new InvalidArgumentException(
                "Kapasitas baru ({$kapasitas}) tidak boleh lebih kecil daripada jumlah kendaraan yang saat ini sedang terisi ({$existing['terisi']}).",
                422
            );
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            UPDATE tb_area_parkir 
            SET nama_area = :nama, kapasitas = :kapasitas
            WHERE id_area = :id
        ");
        $stmt->execute([
            'nama' => $namaArea,
            'kapasitas' => $kapasitas,
            'id' => $id,
        ]);

        $currentUser = Session::getUser();
        AuditService::log(
            $currentUser['id_user'] ?? null,
            "Admin memperbarui area parkir ID {$id} ({$namaArea}): kapasitas {$kapasitas}"
        );

        return $this->getArea($id);
    }
}
