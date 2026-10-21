<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\OwnerController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Router;
use App\Support\Response;

// Frontend menggunakan Vite proxy di development dan same-origin saat deployment.
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $router = new Router();

    // ==========================================
    // 1. Autentikasi
    // ==========================================
    $router->get('/api/v1/auth/csrf', [AuthController::class, 'csrfToken']);
    $router->post('/api/v1/auth/login', [AuthController::class, 'login']);
    $router->post('/api/v1/auth/logout', [AuthController::class, 'logout']);
    $router->get('/api/v1/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

    // ==========================================
    // 2. Modul Admin (Role: admin)
    // ==========================================
    $adminGuard = [AuthMiddleware::class, RoleMiddleware::allow('admin')];
    $router->get('/api/v1/admin/dashboard', [AdminController::class, 'dashboard'], $adminGuard);
    
    // User Management
    $router->get('/api/v1/admin/users', [AdminController::class, 'listUsers'], $adminGuard);
    $router->post('/api/v1/admin/users', [AdminController::class, 'createUser'], $adminGuard);
    $router->get('/api/v1/admin/users/{id}', [AdminController::class, 'getUser'], $adminGuard);
    $router->patch('/api/v1/admin/users/{id}', [AdminController::class, 'updateUser'], $adminGuard);
    $router->post('/api/v1/admin/users/{id}/deactivate', [AdminController::class, 'toggleUserStatus'], $adminGuard);

    // Tariff Management
    $router->get('/api/v1/admin/tariffs', [AdminController::class, 'listTariffs'], $adminGuard);
    $router->post('/api/v1/admin/tariffs', [AdminController::class, 'createTariff'], $adminGuard);
    $router->patch('/api/v1/admin/tariffs/{id}', [AdminController::class, 'updateTariff'], $adminGuard);

    // Area Management
    $router->get('/api/v1/admin/areas', [AdminController::class, 'listAreas'], $adminGuard);
    $router->post('/api/v1/admin/areas', [AdminController::class, 'createArea'], $adminGuard);
    $router->patch('/api/v1/admin/areas/{id}', [AdminController::class, 'updateArea'], $adminGuard);

    // Activity Logs
    $router->get('/api/v1/admin/activity-logs', [AdminController::class, 'activityLogs'], $adminGuard);
    $router->get('/api/v1/admin/database/backup', [AdminController::class, 'downloadBackup'], $adminGuard);
    $router->post('/api/v1/admin/database/restore', [AdminController::class, 'restoreBackup'], $adminGuard);

    // ==========================================
    // 3. Modul Petugas / Operator (Role: petugas)
    // ==========================================
    $operatorGuard = [AuthMiddleware::class, RoleMiddleware::allow('petugas')];
    $operatorAndAdminGuard = [AuthMiddleware::class, RoleMiddleware::allow('admin', 'petugas')];
    $router->get('/api/v1/operator/areas', [OperatorController::class, 'listActiveAreas'], $operatorGuard);
    $router->get('/api/v1/operator/tariffs', [OperatorController::class, 'listActiveTariffs'], $operatorGuard);
    $router->get('/api/v1/operator/transactions/active', [OperatorController::class, 'searchActive'], $operatorGuard);
    $router->post('/api/v1/operator/transactions/entry', [OperatorController::class, 'entry'], $operatorGuard);
    $router->post('/api/v1/operator/transactions/{id}/exit', [OperatorController::class, 'exit'], $operatorGuard);
    $router->get('/api/v1/operator/transactions/{id}/receipt', [OperatorController::class, 'getReceipt'], $operatorAndAdminGuard);
    $router->post('/api/v1/operator/transactions/{id}/payment', [OperatorController::class, 'recordPayment'], $operatorGuard);
    $router->get('/api/v1/operator/transactions/history', [OperatorController::class, 'history'], $operatorAndAdminGuard);
    $router->patch('/api/v1/operator/transactions/{id}/correction', [OperatorController::class, 'correct'], $operatorAndAdminGuard);

    // ==========================================
    // 4. Modul Owner (Role: owner)
    // ==========================================
    $ownerGuard = [AuthMiddleware::class, RoleMiddleware::allow('owner')];
    $router->get('/api/v1/owner/reports/revenue.csv', [OwnerController::class, 'revenueCsv'], $ownerGuard);
    $router->get('/api/v1/owner/reports/revenue', [OwnerController::class, 'revenueReport'], $ownerGuard);
    $router->get('/api/v1/owner/reports/summary', [OwnerController::class, 'summary'], $ownerGuard);

    // Jalankan Router
    $router->dispatch();

} catch (\Throwable $e) {
    $code = $e->getCode();
    if (!is_int($code) || $code < 400 || $code >= 600) {
        $code = 500;
    }
    Response::error($e->getMessage() ?: 'Terjadi kesalahan pada server.', $code);
}
