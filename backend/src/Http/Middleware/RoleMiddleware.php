<?php

namespace App\Http\Middleware;

use App\Support\Session;
use RuntimeException;

class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public static function allow(string ...$roles): \Closure
    {
        return function () use ($roles) {
            $user = Session::getUser();
            if (!$user) {
                throw new RuntimeException('Autentikasi diperlukan. Silakan login terlebih dahulu.', 401);
            }

            $userRole = $user['role'] ?? null;
            if (!in_array($userRole, $roles, true)) {
                throw new RuntimeException("Akses ditolak: role '{$userRole}' tidak diizinkan mengakses resource ini.", 403);
            }
        };
    }

    public function handle(): void
    {
        $user = Session::getUser();
        if (!$user) {
            throw new RuntimeException('Autentikasi diperlukan. Silakan login terlebih dahulu.', 401);
        }

        $userRole = $user['role'] ?? null;
        if (!empty($this->allowedRoles) && !in_array($userRole, $this->allowedRoles, true)) {
            throw new RuntimeException("Akses ditolak: role '{$userRole}' tidak diizinkan mengakses resource ini.", 403);
        }
    }
}
