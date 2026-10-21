<?php

use App\Support\Env;

return [
    'name' => Env::get('APP_NAME', 'E-Parking UKK RPL'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => filter_var(Env::get('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Jakarta'),
    'session_lifetime' => (int) Env::get('SESSION_LIFETIME', 7200),
];

