<?php

require_once __DIR__ . '/bootstrap.php';

use App\Support\Database;

echo "=== E-Parking Seeding Tool ===" . PHP_EOL;

try {
    $pdo = Database::getConnection();
    $sqlFile = dirname(__DIR__) . '/database/seed.sql';
    
    if (!file_exists($sqlFile)) {
        throw new RuntimeException("File seed.sql tidak ditemukan di: {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($queries as $query) {
        if ($query === '' || str_starts_with($query, '--')) {
            continue;
        }
        $pdo->exec($query);
    }

    echo "[SUCCESS] Seeding data awal berhasil dijalankan." . PHP_EOL;
} catch (Throwable $e) {
    echo "[ERROR] Seeding gagal: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

