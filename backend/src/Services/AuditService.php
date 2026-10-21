<?php

namespace App\Services;

use App\Support\Database;
use PDO;

class AuditService
{
    public static function log(?int $userId, string $aktivitas, ?array $metadata = null, ?string $ipAddress = null): void
    {
        $pdo = Database::getConnection();
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $metaJson = $metadata !== null
            ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : null;

        $stmt = $pdo->prepare("
            INSERT INTO tb_log_aktivitas (id_user, aktivitas, metadata, ip_address, waktu_aktivitas)
            VALUES (:id_user, :aktivitas, :metadata, :ip_address, :waktu)
        ");
        $stmt->execute([
            'id_user' => $userId,
            'aktivitas' => $aktivitas,
            'metadata' => $metaJson,
            'ip_address' => $ip,
            'waktu' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function getLogs(int $page = 1, int $perPage = 20): array
    {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        // Jangan menampilkan aktivitas login dan logout di semua role
        $where = "WHERE LOWER(aktivitas) NOT LIKE '%login%' AND LOWER(aktivitas) NOT LIKE '%logout%'";

        $countStmt = $pdo->query("SELECT COUNT(*) FROM tb_log_aktivitas {$where}");
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT l.id_log, l.id_user, u.nama_lengkap, u.username, u.role, 
                   l.aktivitas, l.metadata, l.ip_address, l.waktu_aktivitas
            FROM tb_log_aktivitas l
            LEFT JOIN tb_user u ON l.id_user = u.id_user
            WHERE LOWER(l.aktivitas) NOT LIKE '%login%' AND LOWER(l.aktivitas) NOT LIKE '%logout%'
            ORDER BY l.waktu_aktivitas DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
            'items' => $items,
        ];
    }
}
