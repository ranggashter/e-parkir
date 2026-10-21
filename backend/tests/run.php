<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Services\AreaService;
use App\Services\AuthService;
use App\Services\BackupService;
use App\Services\ParkingService;
use App\Services\ReportService;
use App\Services\TariffService;
use App\Services\UserService;
use App\Support\Database;
use App\Support\Session;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function run(): void
    {
        echo "=================================================" . PHP_EOL;
        echo "    E-Parking UKK RPL - Backend Automated Tests  " . PHP_EOL;
        echo "=================================================" . PHP_EOL;

        $this->setupTestDatabase();

        $this->testAuthValidLogin();
        $this->testAuthInvalidPassword();
        $this->testAuthDeactivatedUser();
        $this->testCsrfProtection();
        $this->testTariffValidation();
        $this->testAreaCapacityValidation();
        $this->testRoleMiddlewareGuards();
        $this->testParkingEntryAndCapacity();
        $this->testParkingEntryFullRejection();
        $this->testDuplicateActiveVehicleEntry();
        $this->testParkingExitAndCeilDurationBilling();
        $this->testCashPayment();
        $this->testTransactionCorrectionAndHistory();
        $this->testDoubleExitRejection();
        $this->testOwnerRevenueReport();
        $this->testDatabaseBackupRestore();

        echo PHP_EOL . "-------------------------------------------------" . PHP_EOL;
        echo "HASIL PENGUJIAN: {$this->passed} LULUS, {$this->failed} GAGAL" . PHP_EOL;
        echo "-------------------------------------------------" . PHP_EOL;

        if ($this->failed > 0) {
            echo "Detail kegagalan:" . PHP_EOL;
            foreach ($this->errors as $err) {
                echo " - " . $err . PHP_EOL;
            }
            exit(1);
        } else {
            echo "[VERIFIED] Semua pengujian logika bisnis dan keamanan berhasil 100%!" . PHP_EOL;
            exit(0);
        }
    }

    private function assert(bool $condition, string $testName): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] " . $testName . PHP_EOL;
        } else {
            $this->failed++;
            $this->errors[] = $testName;
            echo " [FAIL] " . $testName . PHP_EOL;
        }
    }

    private function setupTestDatabase(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        Database::setInstance($pdo);

        // Buat tabel untuk SQLite test environment
        $pdo->exec("
            CREATE TABLE tb_user (
                id_user INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_lengkap TEXT NOT NULL,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL,
                status_aktif INTEGER NOT NULL DEFAULT 1,
                created_at TEXT,
                updated_at TEXT
            );

            CREATE TABLE tb_tarif (
                id_tarif INTEGER PRIMARY KEY AUTOINCREMENT,
                jenis_kendaraan TEXT NOT NULL UNIQUE,
                tarif_per_jam NUMERIC NOT NULL,
                status_aktif INTEGER NOT NULL DEFAULT 1,
                created_at TEXT,
                updated_at TEXT
            );

            CREATE TABLE tb_area_parkir (
                id_area INTEGER PRIMARY KEY AUTOINCREMENT,
                nama_area TEXT NOT NULL UNIQUE,
                kapasitas INTEGER NOT NULL,
                terisi INTEGER NOT NULL DEFAULT 0,
                status_aktif INTEGER NOT NULL DEFAULT 1,
                created_at TEXT,
                updated_at TEXT
            );

            CREATE TABLE tb_kendaraan (
                id_kendaraan INTEGER PRIMARY KEY AUTOINCREMENT,
                plat_nomor TEXT NOT NULL,
                jenis_kendaraan TEXT NOT NULL,
                warna TEXT,
                pemilik TEXT,
                dibuat_oleh_user_id INTEGER,
                created_at TEXT,
                updated_at TEXT
            );

            CREATE TABLE tb_transaksi (
                id_parkir INTEGER PRIMARY KEY AUTOINCREMENT,
                id_kendaraan INTEGER NOT NULL,
                id_area INTEGER NOT NULL,
                id_tarif INTEGER NOT NULL,
                petugas_masuk_id INTEGER NOT NULL,
                petugas_keluar_id INTEGER,
                waktu_masuk TEXT NOT NULL,
                waktu_keluar TEXT,
                created_at TEXT,
                updated_at TEXT,
                plat_nomor_snapshot TEXT,
                jenis_kendaraan_snapshot TEXT,
                warna_snapshot TEXT,
                tarif_per_jam_snapshot NUMERIC NOT NULL,
                durasi_jam INTEGER,
                biaya_total NUMERIC,
                status TEXT NOT NULL DEFAULT 'masuk',
                status_pembayaran TEXT NOT NULL DEFAULT 'belum_lunas',
                metode_pembayaran TEXT,
                uang_diterima NUMERIC,
                uang_kembalian NUMERIC,
                dibayar_oleh_user_id INTEGER,
                dibayar_pada TEXT
            );

            CREATE TABLE tb_log_aktivitas (
                id_log INTEGER PRIMARY KEY AUTOINCREMENT,
                id_user INTEGER,
                aktivitas TEXT NOT NULL,
                metadata TEXT,
                ip_address TEXT,
                waktu_aktivitas TEXT,
                created_at TEXT,
                updated_at TEXT
            );
        ");

        // Seed default users
        $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
        $petugasHash = password_hash('petugas123', PASSWORD_BCRYPT);
        $ownerHash = password_hash('owner123', PASSWORD_BCRYPT);

        $pdo->exec("
            INSERT INTO tb_user (id_user, nama_lengkap, username, password, role, status_aktif) VALUES
            (1, 'Administrator', 'admin', '{$adminHash}', 'admin', 1),
            (2, 'Petugas Loket', 'petugas1', '{$petugasHash}', 'petugas', 1),
            (3, 'Owner Bisnis', 'owner1', '{$ownerHash}', 'owner', 1),
            (4, 'User Nonaktif', 'nonaktif', '{$petugasHash}', 'petugas', 0);

            INSERT INTO tb_tarif (id_tarif, jenis_kendaraan, tarif_per_jam) VALUES
            (1, 'motor', 2000.00),
            (2, 'mobil', 5000.00),
            (3, 'lainnya', 10000.00);

            INSERT INTO tb_area_parkir (id_area, nama_area, kapasitas, terisi) VALUES
            (1, 'Area Motor A', 2, 0),
            (2, 'Area Mobil B', 10, 0);
        ");
    }

    private function testAuthValidLogin(): void
    {
        $auth = new AuthService();
        $user = $auth->login('admin', 'admin123');
        $this->assert(
            $user['username'] === 'admin' && $user['role'] === 'admin' && !isset($user['password']),
            "Autentikasi: Login berhasil mengembalikan data user tanpa hash password"
        );
    }

    private function testAuthInvalidPassword(): void
    {
        $auth = new AuthService();
        $failed = false;
        try {
            $auth->login('admin', 'wrongpassword');
        } catch (\InvalidArgumentException $e) {
            $failed = true;
        }
        $this->assert($failed, "Keamanan: Login dengan password salah ditolak (401)");
    }

    private function testAuthDeactivatedUser(): void
    {
        $auth = new AuthService();
        $rejected = false;
        try {
            $auth->login('nonaktif', 'petugas123');
        } catch (\InvalidArgumentException $e) {
            $rejected = true;
        }
        $this->assert($rejected, "Keamanan: Login user nonaktif ditolak (403)");
    }

    private function testCsrfProtection(): void
    {
        $token = Session::csrfToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'invalid-token';
        $rejected = false;
        try {
            (new \App\Http\Middleware\CsrfMiddleware())->handle();
        } catch (\RuntimeException $e) {
            $rejected = true;
        }
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $accepted = true;
        try {
            (new \App\Http\Middleware\CsrfMiddleware())->handle();
        } catch (\Throwable $e) {
            $accepted = false;
        }
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $this->assert($rejected && $accepted, "Keamanan: Token CSRF valid diterima dan token palsu ditolak");
    }

    private function testTariffValidation(): void
    {
        $tariffService = new TariffService();
        $rejected = false;
        try {
            $tariffService->createTariff([
                'jenis_kendaraan' => 'truk',
                'tarif_per_jam' => -5000,
            ]);
        } catch (\InvalidArgumentException $e) {
            $rejected = true;
        }
        $this->assert($rejected, "Validasi Tarif: Tarif per jam negatif ditolak");
    }

    private function testAreaCapacityValidation(): void
    {
        $areaService = new AreaService();
        $rejected = false;
        try {
            $areaService->createArea([
                'nama_area' => 'Area Negatif',
                'kapasitas' => 0,
            ]);
        } catch (\InvalidArgumentException $e) {
            $rejected = true;
        }
        $this->assert($rejected, "Validasi Area: Kapasitas nol atau negatif ditolak");
    }

    private function testRoleMiddlewareGuards(): void
    {
        // 1. Role admin dilarang mengakses endpoint khusus petugas
        Session::setUser(['id_user' => 1, 'username' => 'admin', 'role' => 'admin']);
        $petugasGuard = \App\Http\Middleware\RoleMiddleware::allow('petugas');
        $adminBlocked = false;
        try {
            $petugasGuard();
        } catch (\Throwable $e) {
            $adminBlocked = true;
        }

        // 2. Role petugas dilarang mengakses endpoint khusus admin
        Session::setUser(['id_user' => 2, 'username' => 'petugas1', 'role' => 'petugas']);
        $adminGuard = \App\Http\Middleware\RoleMiddleware::allow('admin');
        $petugasBlocked = false;
        try {
            $adminGuard();
        } catch (\Throwable $e) {
            $petugasBlocked = true;
        }

        // 3. Role owner dilarang mengakses endpoint khusus admin dan operator
        Session::setUser(['id_user' => 3, 'username' => 'owner1', 'role' => 'owner']);
        $ownerBlockedFromAdmin = false;
        try {
            $adminGuard();
        } catch (\Throwable $e) {
            $ownerBlockedFromAdmin = true;
        }

        $this->assert(
            $adminBlocked && $petugasBlocked && $ownerBlockedFromAdmin,
            "Otorisasi Role: Batas akses ketat admin, petugas, dan owner terbukti menolak akses silang (403)"
        );
    }

    private function testParkingEntryAndCapacity(): void
    {
        $parking = new ParkingService();
        $petugas = ['id_user' => 2, 'username' => 'petugas1', 'role' => 'petugas'];

        // Masukkan kendaraan ke Area 1 (kapasitas 2)
        $entry1 = $parking->entry([
            'plat_nomor' => 'B 1234 ABC',
            'jenis_kendaraan' => 'motor',
            'id_area' => 1,
        ], $petugas);

        $pdo = Database::getConnection();
        $area = $pdo->query("SELECT terisi, kapasitas FROM tb_area_parkir WHERE id_area = 1")->fetch();

        $this->assert(
            $entry1['id_parkir'] > 0 &&
            $entry1['plat_nomor'] === 'B1234ABC' &&
            (int)$area['terisi'] === 1,
            "Transaksi Masuk: Kendaraan berhasil masuk, plat dinormalisasi, counter terisi bertambah 1"
        );
    }

    private function testParkingEntryFullRejection(): void
    {
        $parking = new ParkingService();
        $petugas = ['id_user' => 2, 'username' => 'petugas1', 'role' => 'petugas'];

        // Masukkan kendaraan ke-2 (area sekarang penuh: 2/2)
        $parking->entry([
            'plat_nomor' => 'B 5678 DEF',
            'jenis_kendaraan' => 'motor',
            'id_area' => 1,
        ], $petugas);

        // Masukkan kendaraan ke-3 (harus ditolak karena kapasitas penuh)
        $rejected = false;
        try {
            $parking->entry([
                'plat_nomor' => 'B 9999 XYZ',
                'jenis_kendaraan' => 'motor',
                'id_area' => 1,
            ], $petugas);
        } catch (\RuntimeException $e) {
            $rejected = true;
        }

        $this->assert($rejected, "Integritas Kapasitas: Kendaraan ditolak saat area parkir penuh (409 Conflict)");
    }

    private function testDuplicateActiveVehicleEntry(): void
    {
        $parking = new ParkingService();
        $petugas = ['id_user' => 2, 'username' => 'petugas1', 'role' => 'petugas'];

        // Coba masukkan lagi kendaraan dengan plat nomor yang sama saat masih status 'masuk'
        $rejected = false;
        try {
            $parking->entry([
                'plat_nomor' => 'B1234ABC',
                'jenis_kendaraan' => 'motor',
                'id_area' => 2, // walaupun coba di area lain
            ], $petugas);
        } catch (\RuntimeException $e) {
            $rejected = true;
        }

        $this->assert($rejected, "Integritas Transaksi: Kendaraan dengan transaksi aktif ditolak untuk masuk lagi");
    }

    private function testParkingExitAndCeilDurationBilling(): void
    {
        $pdo = Database::getConnection();
        $parking = new ParkingService();
        $petugas = ['id_user' => 2, 'username' => 'petugas1', 'role' => 'petugas'];

        // Ambil transaksi aktif
        $trx = $pdo->query("SELECT id_parkir, id_area FROM tb_transaksi WHERE status = 'masuk' LIMIT 1")->fetch();
        $idParkir = (int) $trx['id_parkir'];
        $idArea = (int) $trx['id_area'];

        // Simulasikan waktu masuk mundur 70 menit (1 jam 10 menit)
        // Menurut aturan UKK: 1 jam 10 menit dibulatkan ke atas menjadi 2 jam
        // Tarif motor = 2000/jam -> total = 4000
        $waktuMasukMundur = date('Y-m-d H:i:s', time() - (70 * 60));
        $pdo->exec("UPDATE tb_transaksi SET waktu_masuk = '{$waktuMasukMundur}' WHERE id_parkir = {$idParkir}");

        $exitResult = $parking->exit($idParkir, $petugas);

        $areaAfter = $pdo->query("SELECT terisi FROM tb_area_parkir WHERE id_area = {$idArea}")->fetch();

        $this->assert(
            $exitResult['durasi_jam'] === 2 &&
            (float)$exitResult['biaya_total'] === 4000.00 &&
            $exitResult['status'] === 'keluar',
            "Transaksi Keluar: Durasi 70 menit dibulatkan ke atas menjadi 2 jam (Biaya: 2 x Rp2.000 = Rp4.000)"
        );

        $this->assert(
            (int)$areaAfter['terisi'] === 1,
            "Integritas Kapasitas: Counter terisi berkurang tepat 1 setelah transaksi keluar berhasil"
        );
    }

    private function testCashPayment(): void
    {
        $pdo = Database::getConnection();
        $trx = $pdo->query("SELECT id_parkir FROM tb_transaksi WHERE status = 'keluar' LIMIT 1")->fetch();
        $service = new ParkingService();
        $petugas = ['id_user' => 2, 'username' => 'petugas1', 'role' => 'petugas'];

        $underpaidRejected = false;
        try {
            $service->recordCashPayment((int) $trx['id_parkir'], '3999.99', $petugas);
        } catch (\InvalidArgumentException $e) {
            $underpaidRejected = true;
        }
        $this->assert($underpaidRejected, "Pembayaran: Nominal tunai di bawah tagihan ditolak");

        $payment = $service->recordCashPayment((int) $trx['id_parkir'], '5000.00', $petugas);
        $saved = $pdo->query("SELECT status_pembayaran, uang_diterima, uang_kembalian, metode_pembayaran FROM tb_transaksi WHERE id_parkir = " . (int) $trx['id_parkir'])->fetch();
        $this->assert(
            $payment['status_pembayaran'] === 'lunas'
            && $payment['uang_kembalian'] === '1000.00'
            && $saved['status_pembayaran'] === 'lunas'
            && $saved['metode_pembayaran'] === 'tunai',
            "Pembayaran: Tunai, status lunas, dan kembalian tersimpan tepat satu kali"
        );

        $duplicateRejected = false;
        try {
            $service->recordCashPayment((int) $trx['id_parkir'], '5000.00', $petugas);
        } catch (\RuntimeException $e) {
            $duplicateRejected = true;
        }
        $this->assert($duplicateRejected, "Pembayaran: Pencatatan pembayaran kedua kali ditolak");
    }

    private function testTransactionCorrectionAndHistory(): void
    {
        $service = new ParkingService();
        $petugas = ['id_user' => 2, 'username' => 'petugas1', 'role' => 'petugas'];
        $entry = $service->entry([
            'plat_nomor' => 'B 7777 QWE',
            'jenis_kendaraan' => 'motor',
            'id_area' => 2,
        ], $petugas);

        $correction = $service->correctTransaction($entry['id_parkir'], [
            'plat_nomor' => 'B 8888 QWE',
            'warna' => 'Hitam',
            'id_area' => 1,
            'reason' => 'Kesalahan input loket',
        ], $petugas);

        $pdo = Database::getConnection();
        $area1 = $pdo->query("SELECT terisi FROM tb_area_parkir WHERE id_area = 1")->fetchColumn();
        $area2 = $pdo->query("SELECT terisi FROM tb_area_parkir WHERE id_area = 2")->fetchColumn();
        $history = $service->history([
            'q' => 'B8888QWE',
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d'),
        ]);
        $audit = $pdo->query("SELECT metadata FROM tb_log_aktivitas ORDER BY id_log DESC LIMIT 1")->fetchColumn();
        $metadata = json_decode((string) $audit, true);

        $this->assert(
            $correction['after']['plat_nomor'] === 'B8888QWE'
            && (int) $area1 === 2
            && (int) $area2 === 0,
            "Koreksi: Plat diperbarui dan counter area aktif berpindah secara atomik"
        );
        $this->assert(
            count($history['items']) === 1
            && $history['items'][0]['plat_nomor'] === 'B8888QWE'
            && ($metadata['reason'] ?? '') === 'Kesalahan input loket'
            && ($metadata['before']['plat_nomor'] ?? '') === 'B7777QWE',
            "Riwayat: Filter tiket/plat/tanggal dan audit nilai sebelum/sesudah tersedia"
        );

        $missingReasonRejected = false;
        try {
            $service->correctTransaction($entry['id_parkir'], ['warna' => 'Putih'], $petugas);
        } catch (\InvalidArgumentException $e) {
            $missingReasonRejected = true;
        }
        $this->assert($missingReasonRejected, "Koreksi: Alasan wajib diisi");
    }

    private function testDoubleExitRejection(): void
    {
        $pdo = Database::getConnection();
        $parking = new ParkingService();
        $petugas = ['id_user' => 2, 'username' => 'petugas1', 'role' => 'petugas'];

        // Ambil transaksi yang baru saja keluar
        $trx = $pdo->query("SELECT id_parkir FROM tb_transaksi WHERE status = 'keluar' LIMIT 1")->fetch();
        $idParkir = (int) $trx['id_parkir'];

        $rejected = false;
        try {
            $parking->exit($idParkir, $petugas);
        } catch (\RuntimeException $e) {
            $rejected = true;
        }

        $this->assert($rejected, "Integritas Transaksi: Transaksi yang sudah keluar ditolak diproses keluar lagi (409 Conflict)");
    }

    private function testOwnerRevenueReport(): void
    {
        $reportService = new ReportService();
        $today = date('Y-m-d');
        $report = $reportService->getRevenueReport($today, $today);

        $this->assert(
            $report['completed_transactions'] >= 1 &&
            (float)$report['total_revenue'] >= 4000.00 &&
            (float)$report['total_charged'] >= 4000.00 &&
            count($report['by_vehicle_type']) > 0,
            "Laporan Owner: Tagihan dan pembayaran lunas diagregasi terpisah"
        );

        $rows = $reportService->getRevenueExportRows($today, $today);
        $this->assert(
            count($rows) > 0
            && isset($rows[0]['tanggal'], $rows[0]['jenis_kendaraan'], $rows[0]['total_terbayar'])
            && !array_key_exists('plat_nomor', $rows[0])
            && !array_key_exists('id_user', $rows[0]),
            "Ekspor owner: CSV hanya menerima agregat harian/jenis kendaraan tanpa identitas"
        );
    }

    private function testDatabaseBackupRestore(): void
    {
        $service = new BackupService();
        $beforeTransactions = (int) Database::getConnection()->query('SELECT COUNT(*) FROM tb_transaksi')->fetchColumn();
        $backup = $service->createBackup();
        $decoded = json_decode($backup, true);
        $this->assert(
            ($decoded['format'] ?? null) === 'e-parking-backup'
            && ($decoded['database'] ?? null) === 'e_parking_ukk_app'
            && isset($decoded['tables']['tb_user'], $decoded['tables']['tb_transaksi']),
            'Backup: snapshot JSON memuat format, target DB, dan tabel aplikasi'
        );

        $rejected = false;
        try {
            $service->restoreBackup('{"format":"invalid"}', 1);
        } catch (\InvalidArgumentException) {
            $rejected = true;
        }
        $this->assert(
            $rejected && (int) Database::getConnection()->query('SELECT COUNT(*) FROM tb_transaksi')->fetchColumn() === $beforeTransactions,
            'Restore: file dengan format salah ditolak tanpa mengubah data'
        );

        $withoutCurrentAdmin = $decoded;
        $withoutCurrentAdmin['tables']['tb_user'][0]['status_aktif'] = 0;
        $rejected = false;
        try {
            $service->restoreBackup(json_encode($withoutCurrentAdmin, JSON_THROW_ON_ERROR), 1);
        } catch (\InvalidArgumentException) {
            $rejected = true;
        }
        $this->assert(
            $rejected && (int) Database::getConnection()->query('SELECT COUNT(*) FROM tb_transaksi')->fetchColumn() === $beforeTransactions,
            'Restore: backup tanpa admin aktif yang sedang login ditolak tanpa mengubah data'
        );

        $service->restoreBackup($backup, 1);
        $this->assert(
            (int) Database::getConnection()->query('SELECT COUNT(*) FROM tb_transaksi')->fetchColumn() === $beforeTransactions
            && (int) Database::getConnection()->query("SELECT COUNT(*) FROM tb_user WHERE id_user = 1 AND role = 'admin' AND status_aktif = 1")->fetchColumn() === 1,
            'Restore: backup valid memulihkan transaksi dan mempertahankan admin aktif'
        );
    }
}

$runner = new TestRunner();
$runner->run();
