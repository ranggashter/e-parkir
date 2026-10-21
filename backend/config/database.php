<?php

use App\Support\Env;

return [
    'driver' => Env::get('DB_DRIVER', 'mysql'),
    'mysql' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_NAME', 'e_parking_ukk_app'),
        'username' => Env::get('DB_USER', 'parkir_app'),
        'password' => Env::get('DB_PASS', ''),
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    'sqlite' => [
        'database' => Env::get('DB_SQLITE_PATH', ':memory:'),
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
];
