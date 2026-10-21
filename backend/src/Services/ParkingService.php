<?php

namespace App\Services;

use App\Support\Database;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class ParkingService
{
    private TariffService $tariffService;

    public function __construct()
    {
        $this->tariffService = new TariffService();
    }

    public function searchActive(string $keyword = ''): array
    {
        $pdo = Database::getConnection();
        $keyword = trim($keyword);

        $sql = "
            SELECT t.id_parkir, t.waktu_masuk, t.tarif_per_jam_snapshot, t.status,
                   t.status_pembayaran,
                   COALESCE(t.plat_nomor_snapshot, k.plat_nomor) AS plat_nomor,
                   COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan) AS jenis_kendaraan,
                   COALESCE(t.warna_snapshot, k.warna) AS warna,
                   a.id_area, a.nama_area,
                   u.nama_lengkap AS nama_petugas_masuk,
                   t.status_pembayaran
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
            JOIN tb_area_parkir a ON t.id_area = a.id_area
            JOIN tb_user u ON t.petugas_masuk_id = u.id_user
            WHERE t.status = 'masuk'
        ";

        $params = [];
        if ($keyword !== '') {
            $sql .= " AND (k.plat_nomor LIKE :kw OR CAST(t.id_parkir AS CHAR) LIKE :kw2)";
            $params['kw'] = '%' . strtoupper(preg_replace('/\s+/', '', $keyword)) . '%';
            $params['kw2'] = '%' . $keyword . '%';
        }

        $sql .= " ORDER BY t.waktu_masuk DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function entry(array $data, array $user): array
    {
        $platNomor = strtoupper(trim(preg_replace('/\s+/', '', $data['plat_nomor'] ?? '')));
        $jenisKendaraan = strtolower(trim($data['jenis_kendaraan'] ?? ''));
        $warna = !empty($data['warna']) ? trim($data['warna']) : null;
        $idArea = (int) ($data['id_area'] ?? 0);
        $userId = (int) ($user['id_user'] ?? 0);

        if ($platNomor === '' || $jenisKendaraan === '' || $idArea <= 0) {
            throw new InvalidArgumentException("Plat nomor, jenis kendaraan, dan area parkir wajib diisi.", 422);
        }

        // Ambil tarif snapshot untuk jenis kendaraan
        $tarif = $this->tariffService->getActiveTariffByVehicle($jenisKendaraan);
        $snapshotTarif = $tarif['tarif_per_jam'];

        $pdo = Database::getConnection();
        Database::beginTransaction();

        $lock = Database::lockForUpdate();
        $now = date('Y-m-d H:i:s');

        try {
            // 1. Kunci baris area dan periksa kapasitas (Pencegahan Race Condition)
            $areaStmt = $pdo->prepare("
                SELECT id_area, nama_area, kapasitas, terisi, status_aktif 
                FROM tb_area_parkir 
                WHERE id_area = :id 
                {$lock}
            ");
            $areaStmt->execute(['id' => $idArea]);
            $area = $areaStmt->fetch();

            if (!$area) {
                throw new RuntimeException("Area parkir tidak ditemukan.", 404);
            }

            if ((int)$area['status_aktif'] !== 1) {
                throw new RuntimeException("Area parkir '{$area['nama_area']}' sedang nonaktif.", 409);
            }

            if ((int)$area['terisi'] >= (int)$area['kapasitas']) {
                throw new RuntimeException("Area parkir '{$area['nama_area']}' sudah penuh (Kapasitas: {$area['kapasitas']}).", 409);
            }

            // 2. Periksa apakah plat nomor sudah memiliki transaksi aktif (status = 'masuk')
            $checkActiveStmt = $pdo->prepare("
                SELECT t.id_parkir 
                FROM tb_transaksi t
                JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
                WHERE REPLACE(UPPER(k.plat_nomor), ' ', '') = :plat AND t.status = 'masuk'
                LIMIT 1
            ");
            $checkActiveStmt->execute(['plat' => $platNomor]);
            if ($checkActiveStmt->fetch()) {
                throw new RuntimeException("Kendaraan dengan plat nomor {$platNomor} masih memiliki transaksi parkir aktif.", 409);
            }

            // 3. Cari atau daftarkan kendaraan
            $kendaraanStmt = $pdo->prepare("
                SELECT id_kendaraan FROM tb_kendaraan 
                WHERE REPLACE(UPPER(plat_nomor), ' ', '') = :plat 
                LIMIT 1
            ");
            $kendaraanStmt->execute(['plat' => $platNomor]);
            $kendaraanRow = $kendaraanStmt->fetch();

            if ($kendaraanRow) {
                $idKendaraan = (int) $kendaraanRow['id_kendaraan'];
                $updateK = $pdo->prepare("
                    UPDATE tb_kendaraan 
                    SET jenis_kendaraan = :jenis, warna = COALESCE(:warna, warna) 
                    WHERE id_kendaraan = :id
                ");
                $updateK->execute(['jenis' => $jenisKendaraan, 'warna' => $warna, 'id' => $idKendaraan]);
            } else {
                $insertK = $pdo->prepare("
                    INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, dibuat_oleh_user_id)
                    VALUES (:plat, :jenis, :warna, :uid)
                ");
                $insertK->execute([
                    'plat' => $platNomor,
                    'jenis' => $jenisKendaraan,
                    'warna' => $warna,
                    'uid' => $userId,
                ]);
                $idKendaraan = (int) $pdo->lastInsertId();
            }

            // 4. Masukkan transaksi parkir baru
            $waktuMasuk = $now;
            $insertTrx = $pdo->prepare("
                INSERT INTO tb_transaksi (
                    id_kendaraan, id_area, id_tarif, petugas_masuk_id,
                    waktu_masuk, plat_nomor_snapshot, jenis_kendaraan_snapshot, warna_snapshot,
                    tarif_per_jam_snapshot, status, status_pembayaran
                ) VALUES (
                    :id_k, :id_a, :id_t, :uid,
                    :waktu_masuk, :plat, :jenis, :warna,
                    :tarif_snapshot, 'masuk', 'belum_lunas'
                )
            ");
            $insertTrx->execute([
                'id_k' => $idKendaraan,
                'id_a' => $idArea,
                'id_t' => $tarif['id_tarif'],
                'uid' => $userId,
                'waktu_masuk' => $waktuMasuk,
                'plat' => $platNomor,
                'jenis' => $jenisKendaraan,
                'warna' => $warna,
                'tarif_snapshot' => $snapshotTarif,
            ]);
            $idParkir = (int) $pdo->lastInsertId();

            // 5. Tambah counter terisi di area parkir
            $incStmt = $pdo->prepare("
                UPDATE tb_area_parkir 
                SET terisi = terisi + 1 
                WHERE id_area = :id
            ");
            $incStmt->execute(['id' => $idArea]);

            // 6. Audit log
            AuditService::log(
                $userId,
                "Kendaraan masuk: {$platNomor} ({$jenisKendaraan}) di {$area['nama_area']}"
            );

            Database::commit();

            return [
                'id_parkir' => $idParkir,
                'plat_nomor' => $platNomor,
                'jenis_kendaraan' => $jenisKendaraan,
                'warna' => $warna,
                'id_area' => $idArea,
                'nama_area' => $area['nama_area'],
                'waktu_masuk' => $waktuMasuk,
                'tarif_per_jam_snapshot' => $snapshotTarif,
                'status' => 'masuk',
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function exit(int $idParkir, array $user): array
    {
        $userId = (int) ($user['id_user'] ?? 0);
        $pdo = Database::getConnection();
        Database::beginTransaction();

        $lock = Database::lockForUpdate();
        $now = date('Y-m-d H:i:s');

        try {
            // 1. Kunci baris transaksi (SELECT ... FOR UPDATE pada MySQL)
            $trxStmt = $pdo->prepare("
                SELECT t.id_parkir, t.id_area, t.id_kendaraan, t.waktu_masuk, 
                           t.tarif_per_jam_snapshot, t.status,
                           COALESCE(t.plat_nomor_snapshot, k.plat_nomor) AS plat_nomor,
                           COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan) AS jenis_kendaraan,
                           COALESCE(t.warna_snapshot, k.warna) AS warna,
                           a.nama_area
                    FROM tb_transaksi t
                    JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
                    JOIN tb_area_parkir a ON t.id_area = a.id_area
                    WHERE t.id_parkir = :id
                {$lock}
            ");
            $trxStmt->execute(['id' => $idParkir]);
            $trx = $trxStmt->fetch();

            if (!$trx) {
                throw new RuntimeException("Transaksi parkir dengan ID {$idParkir} tidak ditemukan.", 404);
            }

            // Aturan F-06: Hanya menutup transaksi yang statusnya masih 'masuk'
            if ($trx['status'] !== 'masuk') {
                throw new RuntimeException("Transaksi parkir ID {$idParkir} sudah ditutup sebelumnya (status: {$trx['status']}).", 409);
            }

            // 2. Kunci baris area
            $areaStmt = $pdo->prepare("
                SELECT id_area, terisi 
                FROM tb_area_parkir 
                WHERE id_area = :id 
                {$lock}
            ");
            $areaStmt->execute(['id' => $trx['id_area']]);
            $area = $areaStmt->fetch();
            if (!$area || (int) $area['terisi'] < 1) {
                throw new RuntimeException("Counter kapasitas area tidak konsisten; transaksi keluar dibatalkan.", 409);
            }

            // 3. Perhitungan Durasi dan Biaya secara otoritatif di backend
            // Rumus UKK: durasi dihitung minimal 1 jam, pembulatan ke atas (1 jam 10 menit -> 2 jam)
            $tz = new DateTimeZone('Asia/Jakarta');
            $waktuMasuk = new DateTime($trx['waktu_masuk'], $tz);
            $waktuKeluar = new DateTime('now', $tz);

            $diffSeconds = $waktuKeluar->getTimestamp() - $waktuMasuk->getTimestamp();
            if ($diffSeconds < 0) {
                $diffSeconds = 0;
            }
            $diffMinutes = (int) ceil($diffSeconds / 60);
            if ($diffMinutes < 1) {
                $diffMinutes = 1;
            }

            $durasiJam = (int) ceil($diffMinutes / 60);
            if ($durasiJam < 1) {
                $durasiJam = 1;
            }

            $tarifCents = self::moneyToCents((string) $trx['tarif_per_jam_snapshot']);
            $biayaCents = $durasiJam * $tarifCents;

            $waktuKeluarStr = $waktuKeluar->format('Y-m-d H:i:s');
            $biayaTotalFormatted = self::centsToMoney($biayaCents);

            // 4. Update transaksi menjadi keluar
            $updateTrx = $pdo->prepare("
                UPDATE tb_transaksi 
                SET waktu_keluar = :waktu_keluar,
                    durasi_jam = :durasi,
                    biaya_total = :biaya,
                    petugas_keluar_id = :petugas_keluar_id,
                    status = 'keluar'
                WHERE id_parkir = :id
            ");
            $updateTrx->execute([
                'waktu_keluar' => $waktuKeluarStr,
                'durasi' => $durasiJam,
                'biaya' => $biayaTotalFormatted,
                'petugas_keluar_id' => $userId,
                'id' => $idParkir,
            ]);

            // 5. Kurangi counter terisi di area secara aman (tidak boleh negatif)
            $decStmt = $pdo->prepare("
                UPDATE tb_area_parkir 
                SET terisi = terisi - 1
                WHERE id_area = :id
            ");
            $decStmt->execute(['id' => $trx['id_area']]);

            // 6. Audit log
            AuditService::log(
                $userId,
                "Kendaraan keluar: {$trx['plat_nomor']} ({$trx['jenis_kendaraan']}), durasi {$durasiJam} jam, total Rp{$biayaTotalFormatted}"
            );

            Database::commit();

            return [
                'id_parkir' => (int) $idParkir,
                'plat_nomor' => $trx['plat_nomor'],
                'jenis_kendaraan' => $trx['jenis_kendaraan'],
                'warna' => $trx['warna'],
                'nama_area' => $trx['nama_area'],
                'waktu_masuk' => $trx['waktu_masuk'],
                'waktu_keluar' => $waktuKeluarStr,
                'durasi_jam' => $durasiJam,
                'durasi_menit' => $diffMinutes,
                    'tarif_per_jam' => $trx['tarif_per_jam_snapshot'],
                'biaya_total' => $biayaTotalFormatted,
                'status' => 'keluar',
                    'status_pembayaran' => 'belum_lunas',
                ];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function history(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $keyword = trim((string) ($filters['q'] ?? ''));
        $startDate = trim((string) ($filters['start_date'] ?? ''));
        $endDate = trim((string) ($filters['end_date'] ?? ''));

        if (($startDate !== '' || $endDate !== '') && ($startDate === '' || $endDate === '')) {
            throw new InvalidArgumentException("Tanggal mulai dan selesai harus diisi bersamaan.", 422);
        }
        if ($startDate !== '' && (!self::isValidDate($startDate) || !self::isValidDate($endDate))) {
            throw new InvalidArgumentException("Format tanggal harus berupa YYYY-MM-DD yang valid.", 422);
        }
        if ($startDate !== '' && $startDate > $endDate) {
            throw new InvalidArgumentException("Tanggal mulai tidak boleh setelah tanggal selesai.", 422);
        }

        $where = [];
        $params = [];
        if ($keyword !== '') {
            $where[] = "(COALESCE(t.plat_nomor_snapshot, k.plat_nomor) LIKE :plate OR CAST(t.id_parkir AS CHAR) LIKE :ticket)";
            $params['plate'] = '%' . strtoupper(preg_replace('/\s+/', '', $keyword)) . '%';
            $params['ticket'] = '%' . ltrim($keyword, "# \t") . '%';
        }
        if ($startDate !== '') {
            $where[] = "DATE(COALESCE(t.waktu_keluar, t.waktu_masuk)) BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $pdo = Database::getConnection();
        $count = $pdo->prepare("
            SELECT COUNT(*)
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
            {$whereSql}
        ");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT t.id_parkir, t.id_area, t.waktu_masuk, t.waktu_keluar,
                   t.tarif_per_jam_snapshot, t.durasi_jam, t.biaya_total, t.status,
                   t.status_pembayaran, t.metode_pembayaran, t.uang_diterima,
                   t.uang_kembalian, t.dibayar_pada, a.nama_area,
                   COALESCE(t.plat_nomor_snapshot, k.plat_nomor) AS plat_nomor,
                   COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan) AS jenis_kendaraan,
                   COALESCE(t.warna_snapshot, k.warna) AS warna
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
            JOIN tb_area_parkir a ON a.id_area = t.id_area
            {$whereSql}
            ORDER BY t.id_parkir DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function recordCashPayment(int $idParkir, string $amountReceived, array $user): array
    {
        $userId = (int) ($user['id_user'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException("Autentikasi petugas diperlukan.", 401);
        }

        $receivedCents = self::moneyToCents($amountReceived);
        $pdo = Database::getConnection();
        Database::beginTransaction();

        try {
            $lock = Database::lockForUpdate();
            $stmt = $pdo->prepare("
                SELECT id_parkir, status, status_pembayaran, biaya_total
                FROM tb_transaksi
                WHERE id_parkir = :id
                {$lock}
            ");
            $stmt->execute(['id' => $idParkir]);
            $transaction = $stmt->fetch();
            if (!$transaction) {
                throw new RuntimeException("Transaksi parkir tidak ditemukan.", 404);
            }
            if ($transaction['status'] !== 'keluar') {
                throw new RuntimeException("Pembayaran hanya dapat dicatat setelah kendaraan diproses keluar.", 409);
            }
            if ($transaction['status_pembayaran'] !== 'belum_lunas') {
                throw new RuntimeException("Transaksi tidak berstatus belum lunas atau perlu rekonsiliasi data lama.", 409);
            }

            $dueCents = self::moneyToCents((string) $transaction['biaya_total']);
            if ($receivedCents < $dueCents) {
                throw new InvalidArgumentException("Uang diterima kurang dari total tagihan.", 422);
            }
            $changeCents = $receivedCents - $dueCents;
            $paidAt = date('Y-m-d H:i:s');
            $update = $pdo->prepare("
                UPDATE tb_transaksi
                SET status_pembayaran = 'lunas',
                    metode_pembayaran = 'tunai',
                    uang_diterima = :received,
                    uang_kembalian = :change,
                    dibayar_oleh_user_id = :user_id,
                    dibayar_pada = :paid_at
                WHERE id_parkir = :id AND status_pembayaran = 'belum_lunas'
            ");
            $update->execute([
                'received' => self::centsToMoney($receivedCents),
                'change' => self::centsToMoney($changeCents),
                'user_id' => $userId,
                'paid_at' => $paidAt,
                'id' => $idParkir,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException("Pembayaran sudah dicatat oleh proses lain.", 409);
            }

            AuditService::log($userId, 'Pembayaran tunai dicatat', [
                'id_parkir' => $idParkir,
                'tagihan' => self::centsToMoney($dueCents),
                'uang_diterima' => self::centsToMoney($receivedCents),
                'uang_kembalian' => self::centsToMoney($changeCents),
            ]);
            Database::commit();

            return [
                'id_parkir' => $idParkir,
                'status_pembayaran' => 'lunas',
                'metode_pembayaran' => 'tunai',
                'biaya_total' => self::centsToMoney($dueCents),
                'uang_diterima' => self::centsToMoney($receivedCents),
                'uang_kembalian' => self::centsToMoney($changeCents),
                'dibayar_pada' => $paidAt,
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function correctTransaction(int $idParkir, array $data, array $user): array
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException("Alasan koreksi wajib diisi (5–500 karakter).", 422);
        }
        $allowedFields = ['plat_nomor', 'jenis_kendaraan', 'warna', 'id_area', 'reason'];
        if (array_diff(array_keys($data), $allowedFields)) {
            throw new InvalidArgumentException("Koreksi hanya dapat mengubah plat, jenis kendaraan, warna, dan area.", 422);
        }
        $userId = (int) ($user['id_user'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException("Autentikasi diperlukan.", 401);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $lock = Database::lockForUpdate();
            $stmt = $pdo->prepare("
                SELECT t.id_parkir, t.id_kendaraan, t.id_area, t.status,
                       COALESCE(t.plat_nomor_snapshot, k.plat_nomor) AS plat_nomor,
                       COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan) AS jenis_kendaraan,
                       COALESCE(t.warna_snapshot, k.warna) AS warna
                FROM tb_transaksi t
                JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
                WHERE t.id_parkir = :id
                {$lock}
            ");
            $stmt->execute(['id' => $idParkir]);
            $transaction = $stmt->fetch();
            if (!$transaction) {
                throw new RuntimeException("Transaksi parkir tidak ditemukan.", 404);
            }

            $newPlate = array_key_exists('plat_nomor', $data)
                ? strtoupper(preg_replace('/\s+/', '', trim((string) $data['plat_nomor'])))
                : $transaction['plat_nomor'];
            if ($newPlate === '' || !preg_match('/^[A-Z0-9-]{1,20}$/', $newPlate)) {
                throw new InvalidArgumentException("Plat nomor tidak valid.", 422);
            }
            $newType = strtolower(trim((string) ($data['jenis_kendaraan'] ?? $transaction['jenis_kendaraan'])));
            if (!in_array($newType, ['motor', 'mobil', 'lainnya'], true)) {
                throw new InvalidArgumentException("Jenis kendaraan tidak valid.", 422);
            }
            $newColor = array_key_exists('warna', $data) ? trim((string) $data['warna']) : $transaction['warna'];
            $newColor = $newColor === '' ? null : $newColor;
            if ($newColor !== null && mb_strlen($newColor) > 50) {
                throw new InvalidArgumentException("Warna maksimal 50 karakter.", 422);
            }
            $newAreaId = array_key_exists('id_area', $data) ? (int) $data['id_area'] : (int) $transaction['id_area'];
            if ($newAreaId <= 0) {
                throw new InvalidArgumentException("Area tujuan tidak valid.", 422);
            }

            if ($newPlate !== $transaction['plat_nomor'] && $transaction['status'] === 'masuk') {
                $duplicate = $pdo->prepare("
                    SELECT t.id_parkir
                    FROM tb_transaksi t
                    JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
                    WHERE t.status = 'masuk' AND t.id_parkir <> :id
                      AND COALESCE(t.plat_nomor_snapshot, k.plat_nomor) = :plate
                    LIMIT 1
                ");
                $duplicate->execute(['id' => $idParkir, 'plate' => $newPlate]);
                if ($duplicate->fetch()) {
                    throw new RuntimeException("Plat nomor tersebut sedang digunakan transaksi aktif lain.", 409);
                }
            }

            $oldAreaId = (int) $transaction['id_area'];
            if ($newAreaId !== $oldAreaId && $transaction['status'] === 'masuk') {
                $areaIds = [$oldAreaId, $newAreaId];
                sort($areaIds, SORT_NUMERIC);
                $areaStmt = $pdo->prepare("
                    SELECT id_area, nama_area, kapasitas, terisi, status_aktif
                    FROM tb_area_parkir
                    WHERE id_area IN (?, ?)
                    ORDER BY id_area
                    {$lock}
                ");
                $areaStmt->execute($areaIds);
                $areas = [];
                foreach ($areaStmt->fetchAll() as $area) {
                    $areas[(int) $area['id_area']] = $area;
                }
                if (!isset($areas[$oldAreaId], $areas[$newAreaId])) {
                    throw new RuntimeException("Area parkir tidak ditemukan.", 404);
                }
                $oldArea = $areas[$oldAreaId];
                $newArea = $areas[$newAreaId];
                if ((int) $oldArea['terisi'] < 1) {
                    throw new RuntimeException("Counter kapasitas area asal tidak konsisten.", 409);
                }
                if ((int) $newArea['status_aktif'] !== 1) {
                    throw new RuntimeException("Area tujuan sedang nonaktif.", 409);
                }
                if ((int) $newArea['terisi'] >= (int) $newArea['kapasitas']) {
                    throw new RuntimeException("Area tujuan sudah penuh.", 409);
                }
                $pdo->prepare("UPDATE tb_area_parkir SET terisi = terisi - 1 WHERE id_area = :id")
                    ->execute(['id' => $oldAreaId]);
                $pdo->prepare("UPDATE tb_area_parkir SET terisi = terisi + 1 WHERE id_area = :id")
                    ->execute(['id' => $newAreaId]);
            }

            $before = [
                'plat_nomor' => $transaction['plat_nomor'],
                'jenis_kendaraan' => $transaction['jenis_kendaraan'],
                'warna' => $transaction['warna'],
                'id_area' => $oldAreaId,
            ];
            $after = [
                'plat_nomor' => $newPlate,
                'jenis_kendaraan' => $newType,
                'warna' => $newColor,
                'id_area' => $newAreaId,
            ];
            $update = $pdo->prepare("
                UPDATE tb_transaksi
                SET plat_nomor_snapshot = :plate,
                    jenis_kendaraan_snapshot = :type,
                    warna_snapshot = :color,
                    id_area = :area
                WHERE id_parkir = :id
            ");
            $update->execute([
                'plate' => $newPlate,
                'type' => $newType,
                'color' => $newColor,
                'area' => $newAreaId,
                'id' => $idParkir,
            ]);

            AuditService::log($userId, 'Data transaksi parkir dikoreksi', [
                'id_parkir' => $idParkir,
                'reason' => $reason,
                'before' => $before,
                'after' => $after,
            ]);
            Database::commit();

            return ['id_parkir' => $idParkir, 'before' => $before, 'after' => $after, 'reason' => $reason];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function moneyToCents(string $amount): int
    {
        if (!preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/', trim($amount), $matches)) {
            throw new InvalidArgumentException("Nominal harus berupa angka positif dengan maksimal dua desimal.", 422);
        }
        $whole = (int) $matches[1];
        if ($whole > intdiv(PHP_INT_MAX, 100)) {
            throw new InvalidArgumentException("Nominal terlalu besar.", 422);
        }
        $fraction = str_pad($matches[2] ?? '', 2, '0');
        return ($whole * 100) + (int) $fraction;
    }

    private static function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function getReceipt(int $idParkir): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT t.id_parkir, t.waktu_masuk, t.waktu_keluar, t.tarif_per_jam_snapshot,
                   t.durasi_jam, t.biaya_total, t.status, t.status_pembayaran,
                   t.metode_pembayaran, t.uang_diterima, t.uang_kembalian, t.dibayar_pada,
                   COALESCE(t.plat_nomor_snapshot, k.plat_nomor) AS plat_nomor,
                   COALESCE(t.jenis_kendaraan_snapshot, k.jenis_kendaraan) AS jenis_kendaraan,
                   COALESCE(t.warna_snapshot, k.warna) AS warna,
                   a.nama_area,
                   ui.nama_lengkap AS nama_petugas_masuk,
                   uo.nama_lengkap AS nama_petugas_keluar,
                   up.nama_lengkap AS nama_penerima_pembayaran
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
            JOIN tb_area_parkir a ON t.id_area = a.id_area
            LEFT JOIN tb_user ui ON t.petugas_masuk_id = ui.id_user
            LEFT JOIN tb_user uo ON t.petugas_keluar_id = uo.id_user
            LEFT JOIN tb_user up ON t.dibayar_oleh_user_id = up.id_user
            WHERE t.id_parkir = :id
        ");
        $stmt->execute(['id' => $idParkir]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException("Data struk untuk ID parkir {$idParkir} tidak ditemukan.", 404);
        }

        return $row;
    }

    public function getDashboardStats(): array
    {
        $pdo = Database::getConnection();

        // Total terisi vs kapasitas
        $areaStats = $pdo->query("
            SELECT SUM(kapasitas) AS total_kapasitas, SUM(terisi) AS total_terisi
            FROM tb_area_parkir
        ")->fetch();

        // Total transaksi masuk hari ini
        $todayEntry = $pdo->query("
            SELECT COUNT(*) AS total_masuk_hari_ini
            FROM tb_transaksi
            WHERE DATE(waktu_masuk) = CURDATE()
        ")->fetch();

        // Total transaksi keluar hari ini
        $todayExit = $pdo->query("
            SELECT COUNT(*) AS total_keluar_hari_ini,
                   COALESCE(SUM(CASE WHEN status_pembayaran = 'lunas' THEN biaya_total ELSE 0 END), 0) AS pendapatan_hari_ini,
                   COALESCE(SUM(CASE WHEN status_pembayaran = 'belum_lunas' THEN biaya_total ELSE 0 END), 0) AS belum_lunas_hari_ini
            FROM tb_transaksi
            WHERE DATE(waktu_keluar) = CURDATE() AND status = 'keluar'
        ")->fetch();

        return [
            'total_kapasitas' => (int) ($areaStats['total_kapasitas'] ?? 0),
            'total_terisi' => (int) ($areaStats['total_terisi'] ?? 0),
            'total_masuk_hari_ini' => (int) ($todayEntry['total_masuk_hari_ini'] ?? 0),
            'total_keluar_hari_ini' => (int) ($todayExit['total_keluar_hari_ini'] ?? 0),
            'pendapatan_hari_ini' => number_format((float)($todayExit['pendapatan_hari_ini'] ?? 0), 2, '.', ''),
            'belum_lunas_hari_ini' => number_format((float)($todayExit['belum_lunas_hari_ini'] ?? 0), 2, '.', ''),
        ];
    }
}
