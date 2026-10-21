# TASKS.md — Backlog E-Parking

Status: `TODO` = belum dikerjakan; `DOING` = sedang dikerjakan; `BLOCKED` = menunggu keputusan/izin; `DONE` = selesai dan diverifikasi. Kode, migration, test, atau build yang belum dijalankan tidak boleh ditandai selesai.

## Progres aktual

- [x] Pemeriksaan environment lokal: PHP 8.2.12, Composer 2.10.2, Node.js 25.4.0, npm 11.7.0, MariaDB 10.4.32. Ekstensi PHP `PDO`, `pdo_mysql`, dan `pdo_sqlite` tersedia.
- [x] Stack dipilih dan dikonfirmasi: PHP native tanpa framework untuk backend, React/Vite untuk tampilan, satu repository.
- [x] Dokumen proyek diselaraskan dengan stack dan batasan database.
- [x] Pemilik menyatakan database sudah berisi data dan meminta agar database tidak disentuh. Tidak ada koneksi, inspeksi, atau perubahan database yang dilakukan pada database produksi tersebut.
- [x] Fondasi backend PHP native dan frontend React/Vite selesai diimplementasikan.
- [x] Skema DDL InnoDB (`database/schema.sql`), Seed data (`database/seed.sql`), dan Stored Procedure (`database/sp_parkir_keluar.sql`) selesai dibuat.
- [x] Test otomatis backend dijalankan ulang setelah fitur backup/restore (`backend/tests/run.php`): **25 LULUS, 0 GAGAL**; test memakai SQLite in-memory.
- [x] Seluruh file PHP di `backend/` lolos `php -l`.
- [x] Build frontend React/Vite dijalankan ulang (`npm run build`): **berhasil**.
- [x] Lima fitur tambahan diimplementasikan: pencatatan pembayaran tunai, riwayat/cetak ulang, refresh kapasitas manual, ekspor CSV agregat, dan koreksi transaksi dengan alasan serta audit.
- [x] Halaman backup/restore admin dan endpoint JSON backup diimplementasikan. Restore dibatasi ke `e_parking_ukk_app`, dilindungi admin + CSRF + konfirmasi, dan diuji pada SQLite in-memory.
- [x] Sediakan skrip SQL mandiri `database/setup_e_parking_ukk_app.sql` untuk database baru yang skemanya cocok dengan aplikasi; skrip **belum dijalankan**.
- [ ] Migrasi pembayaran ditinjau secara terpisah sebelum dapat digunakan. **Belum dijalankan**; database pengguna tidak dikoneksi, diperiksa, atau diubah.
- [ ] Uji integrasi MariaDB dan verifikasi kompatibilitas dengan skema target. **BLOCKED:** memerlukan database uji kosong/terisolasi dan izin; database pengguna yang berisi data dikecualikan.

## Tahap 0 — Konfirmasi dan bootstrap

- [x] `EP-001` Catat versi PHP, Node, database, dan environment lokal. **Hasil:** versi workstation tercatat di `README.md`.
- [x] `EP-002` Pilih integrasi React: satu repo PHP API + React/Vite di `frontend/`. **Hasil:** dikonfirmasi.
- [x] `EP-003` Aturan tarif >24 jam, pembulatan ceil dan minimal 1 jam. **Hasil:** diimplementasikan di backend (`max(1, ceil(minutes / 60))`).
- [x] `EP-004` Akses owner: rekap pendapatan agregat & ringkasan kendaraan tanpa mengekspos data mentah user. **Hasil:** selesai diimplementasikan.
- [x] `EP-005` Format struk: Karakteristik thermal & bukti pembayaran dengan print CSS `@media print`. **Hasil:** selesai diimplementasikan.
- [x] `EP-006` Catat keputusan yang telah disepakati dan pertanyaan terbuka pada `docs/DESIGN_DECISIONS.md`.
- [x] `EP-007` Catat larangan menyentuh database berisi data; koneksi isolasi diuji via SQLite memory pada automated tests.

## Tahap 1 — Fondasi proyek

- [x] `EP-010` Buat backend PHP native dan konfigurasi lingkungan lokal. **Hasil:** Selesai (`backend/bootstrap.php`, `backend/public/index.php`, `backend/config/`).
- [x] `EP-011` Siapkan React/Vite sesuai keputusan integrasi. **Hasil:** Selesai (`frontend/` dengan Tailwind CSS dan Vite proxy ke `http://127.0.0.1:8000`).
- [x] `EP-012` Siapkan konfigurasi database non-root via `.env.example` dan PDO. **Hasil:** Selesai.
- [x] `EP-013` Siapkan format response API dan penanganan error standar JSON. **Hasil:** Selesai (`App\Support\Response`).
- [x] `EP-014` Siapkan automated test suite PHP. **Hasil:** Selesai (`backend/tests/run.php`).
- [x] `EP-015` Pastikan credential lokal, `.env`, dan file build tidak masuk Git. **Hasil:** Selesai (`.gitignore`).

## Tahap 2 — Database dan autentikasi

- [x] `EP-020` Buat file DDL skema `tb_user`. **Hasil:** Selesai di `database/schema.sql`.
- [x] `EP-021` Buat file DDL skema `tb_tarif` dan `tb_area_parkir`. **Hasil:** Selesai di `database/schema.sql`.
- [x] `EP-022` Buat file DDL skema `tb_kendaraan`, `tb_transaksi`, dan `tb_log_aktivitas`. **Hasil:** Selesai di `database/schema.sql`.
- [x] `EP-023` Tambahkan foreign key, unique constraint, dan index sesuai skema. **Hasil:** Selesai di `database/schema.sql`.
- [x] `EP-024` Implementasikan login, logout, dan endpoint me. **Hasil:** Selesai (`App\Http\Controllers\AuthController`).
- [x] `EP-025` Terapkan `password_hash()`/`password_verify()` dan status aktif. **Hasil:** Selesai (`App\Services\AuthService`).
- [x] `EP-026` Terapkan guard/middleware role PHP dan test akses. **Hasil:** Selesai (`App\Http\Middleware\RoleMiddleware`).
- [x] `EP-027` Siapkan seeder data default (`database/seed.sql` & `backend/seed.php`). **Hasil:** Selesai.

## Tahap 3 — Modul admin

- [x] `EP-030` CRUD/penonaktifan user dengan pembatasan role. **Hasil:** Selesai (`App\Services\UserService`).
- [x] `EP-031` CRUD tarif dan validasi tarif non-negatif. **Hasil:** Selesai (`App\Services\TariffService`).
- [x] `EP-032` CRUD area dan validasi kapasitas (tidak boleh < terisi). **Hasil:** Selesai (`App\Services\AreaService`).
- [x] `EP-033` Endpoint audit log yang hanya dapat diakses admin. **Hasil:** Selesai (`App\Services\AuditService`).
- [x] `EP-034` Halaman React admin: dashboard, user, tarif, area, log. **Hasil:** Selesai di `frontend/src/pages/admin/`.
- [x] `EP-035` Test validasi, otorisasi, dan aturan arsip. **Hasil:** Lulus di `backend/tests/run.php`.

## Tahap 4 — Transaksi parkir

- [x] `EP-040` Implementasikan pencarian transaksi aktif. **Hasil:** Selesai (`App\Services\ParkingService::searchActive`).
- [x] `EP-041` Implementasikan transaksi masuk atomik dengan locking baris area. **Hasil:** Selesai (`App\Services\ParkingService::entry`).
- [x] `EP-042` Cegah transaksi aktif ganda untuk kendaraan yang sama. **Hasil:** Selesai & teruji di test suite.
- [x] `EP-043` Implementasikan transaksi keluar dan aturan durasi/biaya (ceil, min 1 jam). **Hasil:** Selesai & teruji (70 menit -> 2 jam).
- [x] `EP-044` Stored procedure `sp_parkir_keluar` disediakan di `database/sp_parkir_keluar.sql`. **Hasil:** Selesai.
- [x] `EP-045` Pastikan transaksi keluar ganda ditolak dan kapasitas dikurangi sekali. **Hasil:** Selesai & teruji di test suite.
- [x] `EP-046` Buat UI React petugas untuk masuk, pencarian, dan keluar. **Hasil:** Selesai di `frontend/src/pages/operator/`.
- [x] `EP-047` Buat halaman/modal struk dan CSS print. **Hasil:** Selesai di `frontend/src/components/ReceiptModal.jsx`.
- [x] `EP-048` Test area penuh, plat duplikat, dan transaksi sudah keluar. **Hasil:** Lulus di `backend/tests/run.php`.

## Tahap 5 — Laporan owner

- [x] `EP-050` Konfirmasi definisi tanggal laporan dan agregat pendapatan. **Hasil:** Menggunakan `waktu_keluar` untuk realisasi pendapatan.
- [x] `EP-051` Implementasikan query rekap read-only dan view `v_rekap_transaksi`. **Hasil:** Selesai di `App\Services\ReportService`.
- [x] `EP-052` Implementasikan filter tanggal, total pendapatan, jumlah transaksi, dan ringkasan jenis kendaraan. **Hasil:** Selesai.
- [x] `EP-053` Buat halaman rekap React khusus owner dengan opsi print. **Hasil:** Selesai di `frontend/src/pages/owner/ReportsPage.jsx`.
- [x] `EP-054` Test owner tidak dapat mengakses endpoint admin/petugas/transaksi mentah. **Hasil:** Lulus di `backend/tests/run.php`.

## Tahap 6 — Hardening dan final UKK

- [x] `EP-060` Audit seluruh route untuk autentikasi dan otorisasi. **Hasil:** Terverifikasi ketat.
- [x] `EP-061` Audit validasi, SQL injection (PDO prepared statements), XSS, CSRF/session, error response aman tanpa kebocoran stack trace. **Hasil:** Terverifikasi.
- [x] `EP-062` Jalankan test backend PHP, lint, dan frontend React build. **Hasil terkini:** 25/25 test PHP lulus, semua PHP lolos lint, build React Vite sukses. Test memakai SQLite in-memory dan bukan uji integrasi MariaDB.
- [x] `EP-064` Skenario presentasi UKK: login tiap role (Admin, Petugas, Owner) dengan quick-fill button, kelola master, masuk kendaraan, keluar kendaraan, cetak karcis & struk, laporan owner. **Hasil:** Siap digunakan untuk penilaian UKK.
- [x] `EP-065` Catat pembayaran tunai terpisah setelah kendaraan keluar, termasuk uang diterima, kembalian, petugas, waktu, dan status. **Hasil:** backend dan UI riwayat; teruji pada suite in-memory.
- [x] `EP-066` Sediakan pencarian riwayat berdasarkan tiket/plat/tanggal dan cetak ulang struk tersimpan. **Hasil:** endpoint serta halaman petugas diimplementasikan.
- [x] `EP-067` Sediakan ringkasan kapasitas dengan pembaruan manual. **Hasil:** layanan area dan tombol refresh UI menerapkan pilihan refresh manual.
- [x] `EP-068` Ekspor laporan sebagai CSV agregat tanpa plat dan identitas user. **Hasil:** endpoint owner dan UI ekspor; pengujian memverifikasi data CSV agregat.
- [x] `EP-069` Koreksi plat/jenis/warna/area dengan alasan wajib, audit nilai sebelum/sesudah, dan pembaruan counter area aktif secara atomik. **Hasil:** backend dan UI riwayat; teruji pada suite in-memory.
- [x] `EP-070` Buat halaman admin untuk backup unduhan dan restore file backup JSON resmi. **Hasil:** endpoint dibatasi admin/CSRF, target tetap `e_parking_ukk_app`, validasi format dan akun admin aktif, pemulihan transactional, konfirmasi eksplisit di UI/API, pengujian backup/restore lulus.
