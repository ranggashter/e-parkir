<?php

namespace App\Http\Controllers;

use App\Services\AreaService;
use App\Services\ParkingService;
use App\Services\TariffService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use Throwable;

class OperatorController
{
    private ParkingService $parkingService;
    private AreaService $areaService;
    private TariffService $tariffService;

    public function __construct()
    {
        $this->parkingService = new ParkingService();
        $this->areaService = new AreaService();
        $this->tariffService = new TariffService();
    }

    public function listActiveAreas(): void
    {
        try {
            $areas = $this->areaService->listAreas(true);
            Response::json(['areas' => $areas]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function listActiveTariffs(): void
    {
        try {
            $tariffs = $this->tariffService->listTariffs(true);
            Response::json(['tariffs' => $tariffs]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function searchActive(): void
    {
        $q = (string) Request::query('q', '');
        try {
            $activeList = $this->parkingService->searchActive($q);
            Response::json(['transactions' => $activeList]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    public function entry(): void
    {
        $user = Session::getUser();
        try {
            $result = $this->parkingService->entry(Request::body(), $user);
            Response::json($result, 'Kendaraan berhasil dicatat masuk.', 201);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function exit(array $params): void
    {
        $user = Session::getUser();
        $idParkir = (int) $params['id'];
        try {
            $result = $this->parkingService->exit($idParkir, $user);
            Response::json($result, 'Kendaraan berhasil diproses keluar.', 200);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function getReceipt(array $params): void
    {
        $idParkir = (int) $params['id'];
        try {
            $receipt = $this->parkingService->getReceipt($idParkir);
            Response::json(['receipt' => $receipt]);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 404;
            Response::error($e->getMessage(), $code);
        }
    }

    public function history(): void
    {
        try {
            $history = $this->parkingService->history([
                'q' => Request::query('q', ''),
                'start_date' => Request::query('start_date', ''),
                'end_date' => Request::query('end_date', ''),
                'page' => Request::query('page', 1),
                'per_page' => Request::query('per_page', 20),
            ]);
            Response::json($history);
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function recordPayment(array $params): void
    {
        try {
            $body = Request::body();
            $result = $this->parkingService->recordCashPayment(
                (int) $params['id'],
                (string) ($body['uang_diterima'] ?? ''),
                Session::getUser() ?? []
            );
            Response::json($result, 'Pembayaran tunai berhasil dicatat.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    public function correct(array $params): void
    {
        try {
            $result = $this->parkingService->correctTransaction(
                (int) $params['id'],
                Request::body(),
                Session::getUser() ?? []
            );
            Response::json($result, 'Koreksi transaksi berhasil dicatat.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }
}
