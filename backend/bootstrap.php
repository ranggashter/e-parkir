<?php

// Autoloader PSR-4 sederhana untuk App\
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Support\Env;
use App\Support\Session;

// Muat .env jika ada
if (file_exists(__DIR__ . '/.env')) {
    Env::load(__DIR__ . '/.env');
} elseif (file_exists(dirname(__DIR__) . '/.env')) {
    Env::load(dirname(__DIR__) . '/.env');
}

// Konfigurasi Timezone
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Jakarta'));

// Error handling terpusat
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Inisialisasi session aman
Session::start();

