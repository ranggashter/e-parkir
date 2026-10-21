<?php

namespace App\Http\Middleware;

use App\Support\Session;
use RuntimeException;

class AuthMiddleware
{
    public function handle(): void
    {
        $user = Session::getUser();
        if (!$user) {
            throw new RuntimeException('Autentikasi diperlukan. Silakan login terlebih dahulu.', 401);
        }
    }
}
