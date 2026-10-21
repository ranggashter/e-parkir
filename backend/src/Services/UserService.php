<?php

namespace App\Services;

use App\Support\Database;
use App\Support\Session;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class UserService
{
    public function listUsers(int $page = 1, int $perPage = 10, ?string $search = null): array
    {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        $whereClause = "";
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $whereClause = "WHERE username LIKE :s OR nama_lengkap LIKE :s2";
            $params['s'] = '%' . trim($search) . '%';
            $params['s2'] = '%' . trim($search) . '%';
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tb_user {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT id_user, nama_lengkap, username, role, status_aktif
            FROM tb_user
            {$whereClause}
            ORDER BY id_user DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage)),
            'items' => $stmt->fetchAll(),
        ];
    }

    public function getUser(int $id): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT id_user, nama_lengkap, username, role, status_aktif
            FROM tb_user
            WHERE id_user = :id
        ");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException("User dengan ID {$id} tidak ditemukan.", 404);
        }
        return $user;
    }

    public function createUser(array $data): array
    {
        $namaLengkap = trim($data['nama_lengkap'] ?? '');
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $role = trim($data['role'] ?? '');

        if ($namaLengkap === '' || $username === '' || $password === '' || $role === '') {
            throw new InvalidArgumentException("Nama lengkap, username, password, dan role wajib diisi.", 422);
        }

        if (!in_array($role, ['admin', 'petugas', 'owner'], true)) {
            throw new InvalidArgumentException("Role harus salah satu dari: admin, petugas, owner.", 422);
        }

        $pdo = Database::getConnection();
        $checkStmt = $pdo->prepare("SELECT id_user FROM tb_user WHERE username = :u");
        $checkStmt->execute(['u' => $username]);
        if ($checkStmt->fetch()) {
            throw new InvalidArgumentException("Username '{$username}' sudah digunakan.", 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO tb_user (nama_lengkap, username, password, role, status_aktif)
            VALUES (:nama, :user, :pass, :role, 1)
        ");
        $stmt->execute([
            'nama' => $namaLengkap,
            'user' => $username,
            'pass' => $hash,
            'role' => $role,
        ]);

        $newId = (int) $pdo->lastInsertId();
        $currentUser = Session::getUser();
        AuditService::log(
            $currentUser['id_user'] ?? null,
            "Admin membuat user baru: '{$username}' dengan role '{$role}'"
        );

        return $this->getUser($newId);
    }

    public function updateUser(int $id, array $data): array
    {
        $existing = $this->getUser($id);
        $namaLengkap = trim($data['nama_lengkap'] ?? $existing['nama_lengkap']);
        $role = trim($data['role'] ?? $existing['role']);
        $statusAktif = isset($data['status_aktif']) ? (int) $data['status_aktif'] : (int) $existing['status_aktif'];

        if (!in_array($role, ['admin', 'petugas', 'owner'], true)) {
            throw new InvalidArgumentException("Role harus salah satu dari: admin, petugas, owner.", 422);
        }

        $pdo = Database::getConnection();

        $now = date('Y-m-d H:i:s');

        // Update password jika diberikan
        if (!empty($data['password'])) {
            $hash = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                UPDATE tb_user 
                SET nama_lengkap = :nama, role = :role, status_aktif = :status, password = :pass
                WHERE id_user = :id
            ");
            $stmt->execute([
                'nama' => $namaLengkap,
                'role' => $role,
                'status' => $statusAktif,
                'pass' => $hash,
                'id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE tb_user 
                SET nama_lengkap = :nama, role = :role, status_aktif = :status
                WHERE id_user = :id
            ");
            $stmt->execute([
                'nama' => $namaLengkap,
                'role' => $role,
                'status' => $statusAktif,
                'id' => $id,
            ]);
        }

        $currentUser = Session::getUser();
        AuditService::log(
            $currentUser['id_user'] ?? null,
            "Admin memperbarui data user ID {$id} ({$existing['username']})"
        );

        return $this->getUser($id);
    }

    public function toggleStatus(int $id): array
    {
        $user = $this->getUser($id);
        $currentUser = Session::getUser();

        // Mencegah admin menonaktifkan akun sendiri jika itu satu-satunya admin aktif
        if ($currentUser && (int)$currentUser['id_user'] === $id && (int)$user['status_aktif'] === 1) {
            $pdo = Database::getConnection();
            $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM tb_user WHERE role = 'admin' AND status_aktif = 1");
            $activeAdminCount = (int) $adminCountStmt->fetchColumn();
            if ($activeAdminCount <= 1) {
                throw new RuntimeException("Tidak dapat menonaktifkan akun diri sendiri karena merupakan admin aktif terakhir.", 409);
            }
        }

        $newStatus = (int)$user['status_aktif'] === 1 ? 0 : 1;
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE tb_user SET status_aktif = :status WHERE id_user = :id");
        $stmt->execute(['status' => $newStatus, 'id' => $id]);

        $statusLabel = $newStatus === 1 ? "diaktifkan" : "dinonaktifkan";
        AuditService::log(
            $currentUser['id_user'] ?? null,
            "Status user ID {$id} ({$user['username']}) diubah menjadi {$statusLabel}"
        );

        return $this->getUser($id);
    }
}
