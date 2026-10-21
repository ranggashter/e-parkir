<?php

require_once __DIR__ . '/bootstrap.php';

use App\Support\Database;
use App\Support\Env;

echo "=== E-Parking Migration Tool ===" . PHP_EOL;

try {
    $config = require __DIR__ . '/config/database.php';
    $driver = $config['driver'] ?? 'mysql';

    if ($driver === 'mysql') {
        $mysql = $config['mysql'];
        $host = $mysql['host'];
        $port = $mysql['port'];
        $dbName = $mysql['database'];
        $user = $mysql['username'];
        $pass = $mysql['password'];

        // Cek dan buat database jika belum ada
        try {
            $rootDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $rootPdo = new PDO($rootDsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            echo "[INFO] Basis data '{$dbName}' terverifikasi/dibuat." . PHP_EOL;
        } catch (\Throwable $ex) {
            // Lanjutkan jika koneksi langsung ke dbName berhasil
        }
    }

    $pdo = Database::getConnection();
    $sqlFile = dirname(__DIR__) . '/database/schema.sql';
    
    if (!file_exists($sqlFile)) {
        throw new RuntimeException("File schema.sql tidak ditemukan di: {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);
    
    // Pecah statement berdasarkan delimiter/semicolon
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($queries as $query) {
        if ($query === '' || str_starts_with($query, '--')) {
            continue;
        }
        $pdo->exec($query);
    }

    echo "[SUCCESS] Migrasi skema database berhasil dijalankan." . PHP_EOL;
} catch (Throwable $e) {
    echo "[ERROR] Migrasi gagal: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

