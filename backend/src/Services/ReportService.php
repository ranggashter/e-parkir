<?php

namespace App\Services;

use App\Support\Database;
use DateTime;
use InvalidArgumentException;
use PDO;

class ReportService
{
    public function getRevenueReport(string $startDate, string $endDate): array
    {
        $startDate = trim($startDate);
        $endDate = trim($endDate);

        if ($startDate === '' || $endDate === '') {
            throw new InvalidArgumentException("Tanggal mulai (start_date) dan tanggal selesai (end_date) wajib diisi.", 422);
        }

        $dStart = DateTime::createFromFormat('Y-m-d', $startDate);
        $dEnd = DateTime::createFromFormat('Y-m-d', $endDate);

        if (!$dStart || !$dEnd || $dStart->format('Y-m-d') !== $startDate || $dEnd->format('Y-m-d') !== $endDate) {
            throw new InvalidArgumentException("Format tanggal harus berupa YYYY-MM-DD yang valid.", 422);
        }

        if ($dStart > $dEnd) {
            throw new InvalidArgumentException("Tanggal mulai tidak boleh lebih besar dari tanggal selesai.", 422);
        }

        $pdo = Database::getConnection();

        // 1. Agregat total keseluruhan
        $totalStmt = $pdo->prepare("
            SELECT 
                COUNT(t.id_parkir) AS completed_transactions,
                SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN 1 ELSE 0 END) AS paid_transactions,
                SUM(CASE WHEN t.status_pembayaran = 'belum_lunas' THEN 1 ELSE 0 END) AS unpaid_transactions,
                SUM(CASE WHEN t.status_pembayaran = 'legacy_unknown' THEN 1 ELSE 0 END) AS legacy_unknown_transactions,
                COALESCE(SUM(t.biaya_total), 0) AS total_charged,
                COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN t.biaya_total ELSE 0 END), 0) AS total_revenue,
                COALESCE(SUM(CASE WHEN t.status_pembayaran = 'belum_lunas' THEN t.biaya_total ELSE 0 END), 0) AS outstanding_balance,
                COALESCE(SUM(CASE WHEN t.status_pembayaran = 'legacy_unknown' THEN t.biaya_total ELSE 0 END), 0) AS unknown_payment_amount,
                COALESCE(AVG(t.durasi_jam), 0) AS avg_duration_hours
            FROM tb_transaksi t
            WHERE t.status = 'keluar' 
              AND t.waktu_keluar IS NOT NULL
              AND DATE(t.waktu_keluar) >= :start 
              AND DATE(t.waktu_keluar) <= :end
        ");
        $totalStmt->execute(['start' => $startDate, 'end' => $endDate]);
        $totalRow = $totalStmt->fetch();

        // 2. Agregat per jenis kendaraan
        $typeStmt = $pdo->prepare("
            SELECT 
                COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan) AS jenis_kendaraan,
                COUNT(t.id_parkir) AS completed_transactions,
                COALESCE(SUM(t.biaya_total), 0) AS total_charged,
                COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN t.biaya_total ELSE 0 END), 0) AS total_revenue,
                COALESCE(SUM(CASE WHEN t.status_pembayaran = 'belum_lunas' THEN t.biaya_total ELSE 0 END), 0) AS outstanding_balance,
                COALESCE(SUM(CASE WHEN t.status_pembayaran = 'legacy_unknown' THEN t.biaya_total ELSE 0 END), 0) AS unknown_payment_amount
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
            WHERE t.status = 'keluar' 
              AND t.waktu_keluar IS NOT NULL
              AND DATE(t.waktu_keluar) >= :start 
              AND DATE(t.waktu_keluar) <= :end
            GROUP BY COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan)
            ORDER BY total_revenue DESC
        ");
        $typeStmt->execute(['start' => $startDate, 'end' => $endDate]);
        $byType = $typeStmt->fetchAll();

        // 3. Agregat per hari
        $dailyStmt = $pdo->prepare("
            SELECT 
                DATE(t.waktu_keluar) AS date,
                COUNT(t.id_parkir) AS completed_transactions,
                COALESCE(SUM(t.biaya_total), 0) AS total_charged,
                COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN t.biaya_total ELSE 0 END), 0) AS total_revenue,
                COALESCE(SUM(CASE WHEN t.status_pembayaran = 'belum_lunas' THEN t.biaya_total ELSE 0 END), 0) AS outstanding_balance,
                COALESCE(SUM(CASE WHEN t.status_pembayaran = 'legacy_unknown' THEN t.biaya_total ELSE 0 END), 0) AS unknown_payment_amount
            FROM tb_transaksi t
            WHERE t.status = 'keluar' 
              AND t.waktu_keluar IS NOT NULL
              AND DATE(t.waktu_keluar) >= :start 
              AND DATE(t.waktu_keluar) <= :end
            GROUP BY DATE(t.waktu_keluar)
            ORDER BY date ASC
        ");
        $dailyStmt->execute(['start' => $startDate, 'end' => $endDate]);
        $daily = $dailyStmt->fetchAll();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'completed_transactions' => (int) $totalRow['completed_transactions'],
            'paid_transactions' => (int) $totalRow['paid_transactions'],
            'unpaid_transactions' => (int) $totalRow['unpaid_transactions'],
            'legacy_unknown_transactions' => (int) $totalRow['legacy_unknown_transactions'],
            'total_charged' => self::moneyString($totalRow['total_charged']),
            'total_revenue' => self::moneyString($totalRow['total_revenue']),
            'outstanding_balance' => self::moneyString($totalRow['outstanding_balance']),
            'unknown_payment_amount' => self::moneyString($totalRow['unknown_payment_amount']),
            'avg_duration_hours' => round((float)$totalRow['avg_duration_hours'], 1),
            'by_vehicle_type' => array_map(function ($item) {
                return [
                    'jenis_kendaraan' => $item['jenis_kendaraan'],
                    'completed_transactions' => (int) $item['completed_transactions'],
                    'total_charged' => self::moneyString($item['total_charged']),
                    'total_revenue' => self::moneyString($item['total_revenue']),
                    'outstanding_balance' => self::moneyString($item['outstanding_balance']),
                    'unknown_payment_amount' => self::moneyString($item['unknown_payment_amount']),
                ];
            }, $byType),
            'daily_breakdown' => array_map(function ($item) {
                return [
                    'date' => $item['date'],
                    'completed_transactions' => (int) $item['completed_transactions'],
                    'total_charged' => self::moneyString($item['total_charged']),
                    'total_revenue' => self::moneyString($item['total_revenue']),
                    'outstanding_balance' => self::moneyString($item['outstanding_balance']),
                    'unknown_payment_amount' => self::moneyString($item['unknown_payment_amount']),
                ];
            }, $daily),
        ];
    }

    public function getRevenueExportRows(string $startDate, string $endDate): array
    {
        $this->validateDateRange($startDate, $endDate);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT DATE(t.waktu_keluar) AS tanggal,
                   COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan) AS jenis_kendaraan,
                   COUNT(t.id_parkir) AS jumlah_transaksi,
                   COALESCE(SUM(t.biaya_total), 0) AS total_tagihan,
                   COALESCE(SUM(CASE WHEN t.status_pembayaran = 'lunas' THEN t.biaya_total ELSE 0 END), 0) AS total_terbayar,
                   COALESCE(SUM(CASE WHEN t.status_pembayaran = 'belum_lunas' THEN t.biaya_total ELSE 0 END), 0) AS total_belum_lunas,
                   COALESCE(SUM(CASE WHEN t.status_pembayaran = 'legacy_unknown' THEN t.biaya_total ELSE 0 END), 0) AS total_status_tidak_diketahui
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
            WHERE t.status = 'keluar'
              AND t.waktu_keluar IS NOT NULL
              AND DATE(t.waktu_keluar) BETWEEN :start AND :end
            GROUP BY DATE(t.waktu_keluar), COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan)
            ORDER BY tanggal ASC, jenis_kendaraan ASC
        ");
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);
        return $stmt->fetchAll();
    }

    private function validateDateRange(string $startDate, string $endDate): void
    {
        $startDate = trim($startDate);
        $endDate = trim($endDate);
        $start = DateTime::createFromFormat('!Y-m-d', $startDate);
        $end = DateTime::createFromFormat('!Y-m-d', $endDate);
        if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
            throw new InvalidArgumentException("Format tanggal harus berupa YYYY-MM-DD yang valid.", 422);
        }

        if ($start > $end) {
            throw new InvalidArgumentException("Tanggal mulai tidak boleh lebih besar dari tanggal selesai.", 422);
        }
    }

    private static function moneyString(mixed $value): string
    {
        $amount = trim((string) $value);
        if (!preg_match('/^([0-9]+)(?:\.([0-9]+))?$/', $amount, $matches)) {
            throw new \UnexpectedValueException('Nilai nominal laporan tidak valid.');
        }
        $whole = ltrim($matches[1], '0');
        $fraction = substr(str_pad($matches[2] ?? '', 2, '0'), 0, 2);
        return ($whole === '' ? '0' : $whole) . '.' . $fraction;
    }

    public function getSummary(): array
    {
        $today = date('Y-m-d');
        $firstDayOfMonth = date('Y-m-01');

        $pdo = Database::getConnection();

        // Hari ini
        $stmtToday = $pdo->prepare("
            SELECT COUNT(id_parkir) AS count_today,
                   COALESCE(SUM(CASE WHEN status_pembayaran = 'lunas' THEN biaya_total ELSE 0 END), 0) AS rev_today,
                   COALESCE(SUM(CASE WHEN status_pembayaran = 'belum_lunas' THEN biaya_total ELSE 0 END), 0) AS outstanding_today
            FROM tb_transaksi
            WHERE status = 'keluar' AND DATE(waktu_keluar) = :today
        ");
        $stmtToday->execute(['today' => $today]);
        $rowToday = $stmtToday->fetch();

        // Bulan ini
        $stmtMonth = $pdo->prepare("
            SELECT COUNT(id_parkir) AS count_month,
                   COALESCE(SUM(CASE WHEN status_pembayaran = 'lunas' THEN biaya_total ELSE 0 END), 0) AS rev_month,
                   COALESCE(SUM(CASE WHEN status_pembayaran = 'belum_lunas' THEN biaya_total ELSE 0 END), 0) AS outstanding_month
            FROM tb_transaksi
            WHERE status = 'keluar' AND DATE(waktu_keluar) >= :start AND DATE(waktu_keluar) <= :end
        ");
        $stmtMonth->execute(['start' => $firstDayOfMonth, 'end' => $today]);
        $rowMonth = $stmtMonth->fetch();

        return [
            'today' => [
                'transactions' => (int)$rowToday['count_today'],
                'revenue' => self::moneyString($rowToday['rev_today']),
                'outstanding_balance' => self::moneyString($rowToday['outstanding_today']),
            ],
            'this_month' => [
                'transactions' => (int)$rowMonth['count_month'],
                'revenue' => self::moneyString($rowMonth['rev_month']),
                'outstanding_balance' => self::moneyString($rowMonth['outstanding_month']),
            ],
        ];
    }
}
