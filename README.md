# E-Parking — UKK RPL

Aplikasi manajemen parkir berbasis web. Backend menggunakan **PHP native tanpa framework**, antarmuka menggunakan **React**, dan rancangan basis data menggunakan **MySQL/MariaDB**.
## Teknologi dan batas sistem

- Backend: PHP native, REST API JSON, PDO untuk akses MySQL/MariaDB.
- Frontend: React dengan Vite pada folder `frontend/`.
- Database: MySQL/MariaDB; perubahan skema harus direncanakan dan disetujui sebelum diterapkan.
- Backend tidak memakai Laravel atau framework PHP lain.
- React hanya menampilkan data dan mengirim perintah; validasi, otorisasi, serta perhitungan biaya final berada di backend.

## Role

- **Admin:** mengelola user, tarif, area parkir, dan melihat log aktivitas; tidak membuat transaksi masuk/keluar.
- **Petugas:** mencatat kendaraan masuk/keluar dan mencetak struk; tidak mengubah user/tarif atau melihat rekap pendapatan.
- **Owner:** hanya melihat rekap pendapatan dan statistik yang disetujui; tidak mengakses manajemen maupun transaksi mentah.

Backend wajib mengotorisasi setiap endpoint. Menyembunyikan menu React bukan kontrol keamanan.

## Fitur tambahan

- Pembayaran tunai dicatat terpisah setelah transaksi keluar; sistem menyimpan uang diterima, kembalian, petugas, waktu, dan status lunas.
- Riwayat dapat dicari berdasarkan tiket/plat/tanggal, dengan opsi cetak ulang struk.
- Dashboard menyediakan refresh manual untuk angka kapasitas area.
- Admin dan petugas dapat melakukan koreksi terbatas pada plat/jenis/warna/area dengan alasan dan audit sebelum/sesudah.
- Owner dapat mengunduh CSV agregat per tanggal dan jenis kendaraan; ekspor tidak memuat nomor tiket, plat, atau data user.
- Admin memiliki halaman backup/pemulihan berbasis JSON. Restore mengganti data database aplikasi dan hanya menerima file backup resmi.

## Dokumen proyek

- `AGENTS.md` — aturan kontribusi dan implementasi.
- `PROJECT.md` — kebutuhan produk dan kriteria penerimaan.
- `ARCHITECTURE.md` — arsitektur PHP API dan React.
- `DATABASE.md` — skema yang direncanakan dan batasan perubahan database.
- `API.md` — kontrak endpoint.
- `SECURITY.md` — persyaratan keamanan.
- `TESTING.md` — strategi validasi.
- `TASKS.md` — progres dan backlog.
- `docs/DESIGN_DECISIONS.md` — keputusan dan pertanyaan yang masih terbuka.

## Environment yang diperiksa

Pada workstation saat dokumentasi ini diperbarui:

- PHP 8.2.12
- Composer 2.10.2 (tersedia, tidak wajib untuk runtime native PHP)
- Node.js 25.4.0 dan npm 11.7.0
- MariaDB 10.4.32
- Ekstensi PHP `PDO` dan `pdo_mysql` tersedia.

Versi pada komputer target UKK masih harus dikonfirmasi sebelum fitur bergantung padanya. Jangan menyimpulkan bahwa database lokal sudah cocok atau aman diubah.

## Struktur utama

```text
backend/
  public/          # entry point HTTP PHP
  src/             # routing, middleware, controller, layanan bisnis
  config/
  tests/
frontend/
  src/             # aplikasi React
  package.json
database/
  migrations/      # perubahan skema aditif, belum dijalankan
```

## Setup database baru dan menjalankan aplikasi

1. Pastikan database `e_parking_ukk_app` belum ada atau masih kosong. File [database/setup_e_parking_ukk_app.sql](database/setup_e_parking_ukk_app.sql) membuat database terpisah dan tidak menghapus database lama `parkir`.
2. Import file SQL tersebut melalui phpMyAdmin atau MariaDB client. Akun yang menjalankan import harus punya izin membuat database dan tabel.
3. Atur `DB_NAME=e_parking_ukk_app` serta host, user, dan password database yang benar di `.env` (root proyek atau `backend/.env`). Gunakan user database khusus aplikasi dengan hak akses ke database baru.
4. Ganti password akun awal sebelum aplikasi dipakai di luar development.
5. Setelah login sebagai admin, buka **Backup** untuk mengunduh salinan data. Sebelum restore, unduh backup kondisi terkini; simpan file backup secara aman karena berisi hash password dan data operasional.
6. Restore menerima berkas maksimal 50 MB. Jika konfigurasi PHP/XAMPP membatasi upload lebih kecil, sesuaikan `upload_max_filesize` dan `post_max_size` di `php.ini`, lalu restart Apache.

Jalankan API dan Vite pada dua terminal terpisah. Dependensi frontend harus sudah tersedia; gunakan `npm ci` bila perlu memasangnya dari lockfile.

```powershell
# Terminal pertama, dari folder proyek:
php -S 127.0.0.1:8000 -t backend\public backend\public\index.php

# Terminal kedua:
Set-Location frontend
npm run dev
```

Jangan import file setup ini ke database lama atau database berisi data. File ini hanya untuk database baru `e_parking_ukk_app`. Jangan menjalankan `backend\migrate.php`, `backend\seed.php`, migration, reset, drop, import, atau operasi tulis terhadap database `parkir` yang sudah berisi data.

## Aturan konfigurasi

- Jangan menyimpan credential atau data pribadi sungguhan di repository.
- Aplikasi tidak boleh terhubung memakai akun database `root`.
- Simpan credential lokal di environment yang tidak di-commit.
- Gunakan PDO prepared statements; jangan menggabungkan input ke SQL.
- Gunakan `password_hash()` dan `password_verify()` untuk kata sandi.

## Alur inti

1. Admin menyiapkan tarif dan area sesuai keputusan bisnis.
2. Petugas mencatat kendaraan masuk; backend memeriksa kapasitas dan menyimpan waktu.
3. Backend mencatat tarif snapshot hanya setelah keputusan skemanya disetujui.
4. Petugas memproses kendaraan keluar; backend menghitung biaya dan memperbarui status/kapasitas secara atomik.
5. Petugas mencetak struk dari hasil transaksi tersimpan.
6. Owner melihat rekap yang telah disetujui.

## Definition of Done

Perubahan dianggap selesai setelah validasi dan otorisasi backend, test relevan, dokumentasi, serta pemeriksaan keamanan dilakukan. Migration hanya boleh dijalankan pada database kosong/test yang terisolasi atau database yang secara eksplisit disetujui; database yang berisi data saat ini dikecualikan.
