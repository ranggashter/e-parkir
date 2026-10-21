<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use Throwable;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login(): void
    {
        $username = (string) Request::input('username', '');
        $password = (string) Request::input('password', '');

        try {
            $user = $this->authService->login($username, $password);
            Response::json(['user' => $user], 'Login berhasil.', 200);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function csrfToken(): void
    {
        Response::json(['token' => Session::csrfToken()]);
    }

    public function logout(): void
    {
        $this->authService->logout();
        Response::json(null, 'Logout berhasil.', 200);
    }

    public function me(): void
    {
        $user = Session::getUser();
        if (!$user) {
            Response::error('Belum login.', 401);
        }

        Response::json(['user' => $user], 'Data user berhasil diambil.');
    }
}
