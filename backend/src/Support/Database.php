<?php

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function setInstance(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require __DIR__ . '/../../config/database.php';
        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            $sqliteConfig = $config['sqlite'];
            $dsn = "sqlite:" . $sqliteConfig['database'];
            try {
                $pdo = new PDO($dsn, null, null, $sqliteConfig['options'] ?? []);
                self::$instance = $pdo;
                return $pdo;
            } catch (PDOException $e) {
                throw new RuntimeException("Koneksi SQLite gagal: " . $e->getMessage(), 500, $e);
            }
        }

        $mysql = $config['mysql'];
        $user = $mysql['username'] ?? '';
        
        // Aturan AGENTS.md: Cegah akun root di environment production.
        // Di environment lokal/pengembangan (XAMPP), izinkan root jika APP_ENV !== 'production' atau DB_ALLOW_ROOT=true.
        $isProduction = strtolower((string) Env::get('APP_ENV', 'local')) === 'production';
        $allowRoot = filter_var(Env::get('DB_ALLOW_ROOT', !$isProduction), FILTER_VALIDATE_BOOLEAN);

        if (strtolower($user) === 'root' && !$allowRoot) {
            throw new RuntimeException(
                "Keamanan dilanggar (AGENTS.md): Aplikasi tidak boleh terhubung menggunakan akun database 'root' di lingkungan production. Harap gunakan akun khusus dengan hak akses terbatas.",
                500
            );
        }

        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $mysql['host'],
            $mysql['port'],
            $mysql['database'],
            $mysql['charset']
        );

        try {
            $pdo = new PDO($dsn, $user, $mysql['password'] ?? '', $mysql['options'] ?? []);
            self::$instance = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException("Koneksi Database gagal: " . $e->getMessage(), 500, $e);
        }
    }

    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    public static function rollBack(): bool
    {
        if (self::getConnection()->inTransaction()) {
            return self::getConnection()->rollBack();
        }
        return false;
    }

    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    public static function isMysql(): bool
    {
        return self::getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    public static function lockForUpdate(): string
    {
        return self::isMysql() ? ' FOR UPDATE' : '';
    }
}
