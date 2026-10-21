<?php

namespace App\Http\Middleware;

use App\Support\Request;
use App\Support\Session;
use RuntimeException;

class CsrfMiddleware
{
    public function handle(): void
    {
        if (!Session::verifyCsrfToken(Request::header('X-CSRF-Token'))) {
            throw new RuntimeException('Token keamanan tidak valid atau kedaluwarsa. Muat ulang halaman lalu coba lagi.', 419);
        }
    }
}
