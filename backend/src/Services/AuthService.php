<?php

namespace App\Services;

use App\Support\Database;
use App\Support\Session;
use InvalidArgumentException;

class AuthService
{
    public function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new InvalidArgumentException("Username dan password wajib diisi.", 422);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM tb_user WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // Security: periksa password dengan password_verify() (Bcrypt)
        // Serta dukung migrasi transparan jika database lama masih menyimpan SHA-256, lalu upgrade otomatis ke Bcrypt
        $isValid = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $isValid = true;
            } elseif (strlen($user['password']) === 64 && hash('sha256', $password) === strtolower($user['password'])) {
                $isValid = true;
                // Upgrade otomatis ke password_hash() Bcrypt sesuai aturan AGENTS.md
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $upStmt = $pdo->prepare("UPDATE tb_user SET password = :p WHERE id_user = :id");
                $upStmt->execute(['p' => $newHash, 'id' => $user['id_user']]);
            }
        }

        if (!$user || !$isValid) {
            throw new InvalidArgumentException("Username atau password salah.", 401);
        }

        if ((int)$user['status_aktif'] !== 1) {
            throw new InvalidArgumentException("Akun Anda telah dinonaktifkan. Hubungi administrator.", 403);
        }

        // Regenerate session untuk proteksi session fixation
        Session::regenerate();

        $userData = [
            'id_user' => (int) $user['id_user'],
            'nama_lengkap' => $user['nama_lengkap'],
            'username' => $user['username'],
            'role' => $user['role'],
            'status_aktif' => (int) $user['status_aktif'],
        ];

        Session::setUser($userData);

        return $userData;
    }

    public function logout(): void
    {
        Session::destroy();
    }
}

