# ARCHITECTURE.md — Arsitektur E-Parking

## 1. Stack dan pola aplikasi

- Backend: PHP native tanpa Laravel atau framework PHP lain.
- Frontend: React/Vite di folder `frontend/`.
- Database: MySQL/MariaDB melalui PDO.
- Komunikasi: REST API JSON dengan prefix rencana `/api/v1`.
- Deployment yang dituju: frontend dan API same-origin. Dalam development, Vite mem-proxy request API ke server PHP. Ini menghindari konfigurasi CORS terbuka.

Database yang sudah berisi data tidak boleh dikoneksi, diinspeksi, atau diubah. Skema aktual belum diverifikasi. Dokumen `DATABASE.md` mendeskripsikan kebutuhan, bukan bukti struktur database saat ini.

## 2. Alur request

1. React mengirim request ke API.
2. PHP memulai session bila diperlukan dan menjalankan perlindungan CSRF untuk request yang mengubah state.
3. Router mencocokkan method dan path; middleware autentikasi/otorisasi menolak akses yang tidak sah.
4. Validasi server memastikan bentuk dan rentang input.
5. Controller tipis memanggil service/action aturan bisnis.
6. Service menggunakan PDO prepared statements dan transaksi database untuk mutasi terkait.
7. Presenter/serializer mengembalikan hanya field yang diizinkan sebagai JSON.
8. React menangani loading, empty, success, dan error state.

Seluruh masukan tetap tidak tepercaya, termasuk ID yang berasal dari UI. User dan role didapat dari session server, bukan body atau local storage.

## 3. Struktur backend yang direncanakan

```text
backend/
├── public/
│   └── index.php             # satu entry point HTTP
├── src/
│   ├── Http/                 # router, controller, middleware, request validation
│   ├── Services/             # transaksi parkir, laporan, audit
│   ├── Repositories/         # query PDO terparameter
│   ├── Models/               # representasi data bila diperlukan
│   └── Support/               # response, konfigurasi, error
├── config/
├── tests/
└── bootstrap.php
```

Ini rancangan, bukan klaim bahwa file tersebut sudah dibuat. Hindari menambahkan framework/router buatan yang tidak perlu; sebelum memilih library eksternal, minta persetujuan dan evaluasi ketergantungannya.

## 4. Struktur frontend yang direncanakan

```text
frontend/
├── src/
│   ├── api/
│   ├── components/
│   ├── layouts/
│   ├── pages/
│   │   ├── auth/
│   │   ├── admin/
│   │   ├── operator/
│   │   └── owner/
│   ├── hooks/
│   └── types/
└── package.json
```

Route guard React hanya untuk pengalaman pengguna; API PHP tetap melakukan otorisasi setiap request.

## 5. Batas modul

- **Auth:** login, logout, identitas user saat ini, session dan proteksi CSRF.
- **Admin:** user, tarif, area, audit log.
- **Operator:** transaksi masuk/keluar, pencarian transaksi aktif, data struk.
- **Owner reporting:** agregat dan statistik yang disetujui, tanpa akses transaksi mentah.
- **Database:** integritas relasi, transaksi, locking, dan pelaporan.

## 6. Operasi transaksi dan locking

Transaksi masuk dan keluar harus atomik. Gunakan transaksi PDO (`beginTransaction`, `commit`, `rollBack`) dan row lock InnoDB pada record yang relevan. Tentukan urutan penguncian yang konsisten untuk mengurangi deadlock.

Masuk: kunci area, periksa status/kapasitas, cegah kendaraan aktif ganda sesuai keputusan bisnis, baca tarif yang berlaku, simpan transaksi dan audit, lalu tambah counter.

Keluar: kunci transaksi lalu area dengan urutan konsisten, pastikan status masih aktif, hitung biaya di backend, simpan waktu/status dan audit, lalu kurangi counter tepat satu kali.

Kesalahan harus menyebabkan rollback penuh dan respons error yang sesuai; jangan menelan exception atau mengirim data sukses palsu.

## 7. Pelaporan

Pendapatan dihitung hanya dari transaksi selesai. Field tanggal yang dipakai dan tingkat detail yang boleh dilihat owner masih menunggu keputusan di `docs/DESIGN_DECISIONS.md`. API owner hanya membaca query agregat yang diotorisasi.

## 8. Integrasi dan urutan implementasi

1. Bootstrap dan validasi PHP API serta React/Vite tanpa akses database berisi data.
2. Verifikasi skema aktual hanya setelah pemilik memberi izin; saat ini inspeksi database pun dilarang.
3. Sepakati skema dan mekanisme migration PHP sebelum membuat atau menjalankannya.
4. Tetapkan kontrak API dan test.
5. Implementasikan autentikasi/otorisasi sebelum fitur role.
6. Implementasikan aturan transaksi dan UI berdasarkan kontrak yang telah stabil.
