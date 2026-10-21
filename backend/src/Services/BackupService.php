<?php

namespace App\Services;

use App\Support\Database;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class BackupService
{
    private const DATABASE_NAME = 'e_parking_ukk_app';
    private const FORMAT = 'e-parking-backup';
    private const VERSION = 1;
    private const TABLES = [
        'tb_user' => [
            'id_user', 'nama_lengkap', 'username', 'password', 'role', 'status_aktif', 'created_at', 'updated_at',
        ],
        'tb_tarif' => [
            'id_tarif', 'jenis_kendaraan', 'tarif_per_jam', 'status_aktif', 'created_at', 'updated_at',
        ],
        'tb_area_parkir' => [
            'id_area', 'nama_area', 'kapasitas', 'terisi', 'status_aktif', 'created_at', 'updated_at',
        ],
        'tb_kendaraan' => [
            'id_kendaraan', 'plat_nomor', 'jenis_kendaraan', 'warna', 'pemilik',
            'dibuat_oleh_user_id', 'created_at', 'updated_at',
        ],
        'tb_transaksi' => [
            'id_parkir', 'id_kendaraan', 'id_area', 'id_tarif', 'petugas_masuk_id',
            'petugas_keluar_id', 'waktu_masuk', 'waktu_keluar', 'plat_nomor_snapshot',
            'jenis_kendaraan_snapshot', 'warna_snapshot', 'tarif_per_jam_snapshot',
            'durasi_jam', 'biaya_total', 'status', 'status_pembayaran', 'metode_pembayaran',
            'uang_diterima', 'uang_kembalian', 'dibayar_oleh_user_id', 'dibayar_pada',
            'created_at', 'updated_at',
        ],
        'tb_log_aktivitas' => [
            'id_log', 'id_user', 'aktivitas', 'metadata', 'ip_address',
            'waktu_aktivitas', 'created_at', 'updated_at',
        ],
    ];

    public function createBackup(): string
    {
        $this->assertSafeDatabase();
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        try {
            $tables = [];
            foreach (self::TABLES as $table => $columns) {
                $quotedColumns = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
                $primaryKey = $this->quoteIdentifier($columns[0]);
                $quotedTable = $this->quoteIdentifier($table);
                $stmt = $pdo->query("SELECT {$quotedColumns} FROM {$quotedTable} ORDER BY {$primaryKey} ASC");
                $tables[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $backup = [
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'database' => self::DATABASE_NAME,
                'created_at' => date(DATE_ATOM),
                'tables' => $tables,
            ];
            $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($json) > 50 * 1024 * 1024) {
                throw new RuntimeException('Ukuran backup melebihi batas unduhan 50 MB.');
            }
            $pdo->commit();
            return $json;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function restoreBackup(string $json, int $adminUserId): void
    {
        $this->assertSafeDatabase();
        if (strlen($json) > 50 * 1024 * 1024) {
            throw new InvalidArgumentException('Ukuran file backup maksimal 50 MB.', 413);
        }

        try {
            $backup = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('File backup bukan JSON valid.', 422, $e);
        }

        $this->validateBackup($backup, $adminUserId);
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            foreach (['tb_log_aktivitas', 'tb_transaksi', 'tb_kendaraan', 'tb_tarif', 'tb_area_parkir', 'tb_user'] as $table) {
                $pdo->exec('DELETE FROM ' . $this->quoteIdentifier($table));
            }

            foreach (self::TABLES as $table => $columns) {
                $this->insertRows($pdo, $table, $columns, $backup['tables'][$table]);
            }

            AuditService::log(
                $adminUserId,
                'Admin memulihkan database dari file backup',
                ['backup_created_at' => $backup['created_at'], 'format_version' => self::VERSION]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Pemulihan gagal; perubahan database dibatalkan. Periksa bahwa struktur database sesuai dengan versi aplikasi.', 500, $e);
        }
    }

    private function validateBackup(mixed $backup, int $adminUserId): void
    {
        if (!is_array($backup)
            || ($backup['format'] ?? null) !== self::FORMAT
            || ($backup['version'] ?? null) !== self::VERSION
            || ($backup['database'] ?? null) !== self::DATABASE_NAME
            || !is_string($backup['created_at'] ?? null)
            || !is_array($backup['tables'] ?? null)
        ) {
            throw new InvalidArgumentException('Format backup tidak didukung atau bukan backup database aplikasi ini.', 422);
        }

        if (array_diff(array_keys(self::TABLES), array_keys($backup['tables'])) !== []
            || array_diff(array_keys($backup['tables']), array_keys(self::TABLES)) !== []
        ) {
            throw new InvalidArgumentException('Isi file backup tidak sesuai dengan tabel aplikasi.', 422);
        }

        $foundActiveAdmin = false;
        $totalRows = 0;
        foreach (self::TABLES as $table => $columns) {
            $rows = $backup['tables'][$table];
            if (!is_array($rows)) {
                throw new InvalidArgumentException("Data tabel {$table} tidak valid.", 422);
            }
            $totalRows += count($rows);
            if ($totalRows > 250000) {
                throw new InvalidArgumentException('Backup berisi terlalu banyak baris untuk dipulihkan melalui halaman admin.', 422);
            }

            foreach ($rows as $row) {
                if (!is_array($row) || array_is_list($row)) {
                    throw new InvalidArgumentException("Baris pada tabel {$table} tidak valid.", 422);
                }
                $keys = array_keys($row);
                sort($keys);
                $expected = $columns;
                sort($expected);
                if ($keys !== $expected) {
                    throw new InvalidArgumentException("Struktur baris tabel {$table} tidak cocok dengan versi aplikasi.", 422);
                }

                if ($table === 'tb_user'
                    && (int) $row['id_user'] === $adminUserId
                    && $row['role'] === 'admin'
                    && (int) $row['status_aktif'] === 1
                ) {
                    $foundActiveAdmin = true;
                }
            }
        }

        if (!$foundActiveAdmin) {
            throw new InvalidArgumentException('Backup harus memuat akun admin aktif yang sedang digunakan agar sesi pemulihan tetap sah.', 422);
        }
    }

    private function insertRows(PDO $pdo, string $table, array $columns, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $columnSql = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
        $placeholders = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));
        $stmt = $pdo->prepare('INSERT INTO ' . $this->quoteIdentifier($table) . " ({$columnSql}) VALUES ({$placeholders})");

        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }

    private function assertSafeDatabase(): void
    {
        $configuredDatabase = (string) (require __DIR__ . '/../../config/database.php')['mysql']['database'];
        if ($configuredDatabase !== self::DATABASE_NAME) {
            throw new RuntimeException(
                'Backup dan pemulihan hanya diaktifkan untuk database e_parking_ukk_app. Periksa DB_NAME; database lain tidak akan diubah.',
                403
            );
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[a-z_]+$/', $identifier)) {
            throw new RuntimeException('Nama kolom internal tidak valid.');
        }
        return '`' . $identifier . '`';
    }
}
