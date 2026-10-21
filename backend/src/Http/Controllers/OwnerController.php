<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\Request;
use App\Support\Response;
use Throwable;

class OwnerController
{
    private ReportService $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
    }

    public function revenueReport(): void
    {
        $startDate = (string) Request::query('start_date', date('Y-m-01'));
        $endDate = (string) Request::query('end_date', date('Y-m-d'));

        try {
            $report = $this->reportService->getRevenueReport($startDate, $endDate);
            Response::json($report, 'Rekap laporan pendapatan berhasil diambil.');
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
            Response::error($e->getMessage(), $code);
        }
    }

    public function revenueCsv(): void
    {
        $startDate = (string) Request::query('start_date', '');
        $endDate = (string) Request::query('end_date', '');

        try {
            $rows = $this->reportService->getRevenueExportRows($startDate, $endDate);
            $stream = fopen('php://temp', 'r+');
            if ($stream === false) {
                throw new \RuntimeException('Gagal menyiapkan file laporan.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Tanggal keluar',
                'Jenis kendaraan',
                'Jumlah transaksi',
                'Total tagihan',
                'Total terbayar',
                'Total belum lunas',
                'Total status pembayaran tidak diketahui',
            ]);
            foreach ($rows as $row) {
                fputcsv($stream, [
                    $row['tanggal'],
                    $this->safeCsvText((string) $row['jenis_kendaraan']),
                    (int) $row['jumlah_transaksi'],
                    $this->safeCsvAmount($row['total_tagihan']),
                    $this->safeCsvAmount($row['total_terbayar']),
                    $this->safeCsvAmount($row['total_belum_lunas']),
                    $this->safeCsvAmount($row['total_status_tidak_diketahui']),
                ]);
            }

            rewind($stream);
            $csv = stream_get_contents($stream);
            fclose($stream);
            if ($csv === false) {
                throw new \RuntimeException('Gagal membaca hasil laporan CSV.');
            }
            Response::csv($csv, "rekap-parkir-{$startDate}-{$endDate}.csv");
        } catch (Throwable $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            Response::error($e->getMessage(), $code);
        }
    }

    private function safeCsvText(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/', $value) ? "'" . $value : $value;
    }

    private function safeCsvAmount(mixed $value): string
    {
        $amount = trim((string) $value);
        if (!preg_match('/^([0-9]+)(?:\.([0-9]+))?$/', $amount, $matches)) {
            throw new \UnexpectedValueException('Nominal ekspor tidak valid.');
        }
        $whole = ltrim($matches[1], '0');
        $fraction = substr(str_pad($matches[2] ?? '', 2, '0'), 0, 2);
        return ($whole === '' ? '0' : $whole) . '.' . $fraction;
    }

    public function summary(): void
    {
        try {
            $summary = $this->reportService->getSummary();
            Response::json($summary, 'Ringkasan data berhasil diambil.');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
