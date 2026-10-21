<?php

namespace App\Http\Controllers;

use App\Services\AreaService;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\ParkingService;
use App\Services\TariffService;
use App\Services\UserService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use Throwable;

class AdminController
{
    private UserService $userService;
    private TariffService $tariffService;
    private AreaService $areaService;
    private ParkingService $parkingService;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->tariffService = new TariffService();
        $this->areaService = new AreaService();
        $this->parkingService = new ParkingService();
    }

    public function dashboard(): void
    {
        try {
            $stats = $this->parkingService->getDashboardStats();
            $areas = $this->areaService->listAreas();
            $recentLogs = AuditService::getLogs(1, 10);

            Response::json([
                'stats' => $stats,
                'areas' => $areas,
                'recent_logs' => $recentLogs['items'],
            ], 'Data dashboard admin berhasil diambil.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    // --- Users ---
    public function listUsers(): void
    {
        $page = (int) Request::query('page', 1);
        $perPage = (int) Request::query('per_page', 10);
        $search = Request::query('search');

        try {
            $data = $this->userService->listUsers($page, $perPage, $search);
            Response::json($data);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function getUser(array $params): void
    {
        try {
            $user = $this->userService->getUser((int)$params['id']);
            Response::json(['user' => $user]);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function createUser(): void
    {
        try {
            $user = $this->userService->createUser(Request::body());
            Response::json(['user' => $user], 'User berhasil dibuat.', 201);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function updateUser(array $params): void
    {
        try {
            $user = $this->userService->updateUser((int)$params['id'], Request::body());
            Response::json(['user' => $user], 'User berhasil diperbarui.', 200);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function toggleUserStatus(array $params): void
    {
        try {
            $user = $this->userService->toggleStatus((int)$params['id']);
            Response::json(['user' => $user], 'Status user berhasil diperbarui.', 200);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    // --- Tariffs ---
    public function listTariffs(): void
    {
        try {
            $tariffs = $this->tariffService->listTariffs();
            Response::json(['tariffs' => $tariffs]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function createTariff(): void
    {
        try {
            $tariff = $this->tariffService->createTariff(Request::body());
            Response::json(['tariff' => $tariff], 'Tarif berhasil ditambahkan.', 201);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function updateTariff(array $params): void
    {
        try {
            $tariff = $this->tariffService->updateTariff((int)$params['id'], Request::body());
            Response::json(['tariff' => $tariff], 'Tarif berhasil diperbarui.', 200);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    // --- Areas ---
    public function listAreas(): void
    {
        try {
            $areas = $this->areaService->listAreas();
            Response::json(['areas' => $areas]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function createArea(): void
    {
        try {
            $area = $this->areaService->createArea(Request::body());
            Response::json(['area' => $area], 'Area parkir berhasil ditambahkan.', 201);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function updateArea(array $params): void
    {
        try {
            $area = $this->areaService->updateArea((int)$params['id'], Request::body());
            Response::json(['area' => $area], 'Area parkir berhasil diperbarui.', 200);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    // --- Activity Logs ---
    public function activityLogs(): void
    {
        $page = (int) Request::query('page', 1);
        $perPage = (int) Request::query('per_page', 20);

        try {
            $logs = AuditService::getLogs($page, $perPage);
            Response::json($logs);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function downloadBackup(): void
    {
        try {
            $json = (new BackupService())->createBackup();
            AuditService::log(
                (int) (Session::getUser()['id_user'] ?? 0),
                'Admin mengunduh backup database',
                ['format' => 'e-parking-backup', 'version' => 1]
            );
            Response::download($json, 'e-parking-backup-' . date('Ymd-His') . '.json', 'application/json; charset=UTF-8');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function restoreBackup(): void
    {
        try {
            if (($_POST['confirmation'] ?? '') !== 'PULIHKAN e_parking_ukk_app') {
                throw new \InvalidArgumentException('Konfirmasi pemulihan database tidak sesuai.', 422);
            }
            $file = $_FILES['backup_file'] ?? null;
            if (!is_array($file)) {
                throw new \InvalidArgumentException('Pilih file backup JSON yang valid untuk dipulihkan.', 422);
            }
            $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                throw new \InvalidArgumentException('Ukuran file melebihi batas upload PHP. Sesuaikan upload_max_filesize dan post_max_size di php.ini.', 413);
            }
            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Unggahan file backup gagal (kode ' . $uploadError . '). Pilih file kembali dan coba lagi.', 422);
            }
            if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
                throw new \InvalidArgumentException('File backup yang diunggah tidak valid.', 422);
            }
            $size = (int) ($file['size'] ?? 0);
            if ($size <= 0 || $size > 50 * 1024 * 1024) {
                throw new \InvalidArgumentException('Ukuran file backup harus antara 1 byte dan 50 MB.', 413);
            }
            $json = file_get_contents($file['tmp_name']);
            if ($json === false) {
                throw new \RuntimeException('Gagal membaca file backup yang diunggah.');
            }

            $user = Session::getUser();
            (new BackupService())->restoreBackup($json, (int) ($user['id_user'] ?? 0));
            Response::json(null, 'Database berhasil dipulihkan. Data sebelum pemulihan harus sudah dicadangkan melalui tombol Unduh Backup.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
