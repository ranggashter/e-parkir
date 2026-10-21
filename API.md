# API.md — Kontrak API E-Parking

## 1. Konvensi

Prefix yang direncanakan: `/api/v1`.

Base URL lokal placeholder: `http://localhost:8000`.

- Format request/response: JSON, kecuali endpoint cetak yang menampilkan halaman print.
- Waktu: ISO 8601 dengan timezone yang terdokumentasi.
- Uang: decimal yang konsisten, misalnya `"6000.00"`.
- Gunakan HTTP status yang sesuai: `200`, `201`, `204`, `401`, `403`, `404`, `409`, `422`, `500`.
- Semua endpoint selain login dan endpoint publik yang disepakati memerlukan autentikasi.
- Identitas pengguna dan role selalu berasal dari autentikasi server; jangan menerima `role` atau `user_id` sebagai otoritas dari body.
- Backend mengimplementasikan API dengan PHP native tanpa Laravel; nama route final mengikuti router PHP yang benar-benar dibuat.
- React/Vite berada di repository yang sama. Utamakan deployment same-origin; pada development, gunakan proxy Vite ke API PHP.

## 2. Autentikasi

- Strategi autentikasi final masih perlu dikonfirmasi sebelum implementasi. Rekomendasi awal untuk SPA same-origin adalah session cookie PHP dengan proteksi CSRF dan cookie `HttpOnly`, `SameSite`, serta `Secure` pada HTTPS. Jangan membuat skema token khusus tanpa kebutuhan.
- Jangan menyimpan secret backend di frontend.
- Jangan mengirim password atau token dalam log.
- `GET /api/v1/auth/csrf` menyediakan token session untuk header `X-CSRF-Token`. Semua request yang mengubah state harus menyertakan token tersebut.

### `POST /api/v1/auth/login`
Request:
```json
{
  "username": "petugas01",
  "password": "<password>"
}
```
Response sukses contoh:
```json
{
  "data": {
    "user": {
      "id_user": 12,
      "nama_lengkap": "Nama Petugas",
      "username": "petugas01",
      "role": "petugas"
    }
  },
  "message": "Login berhasil"
}
```
Password dan hash tidak boleh dikembalikan. Detail mekanisme cookie/token mengikuti strategi autentikasi terpilih.

### `POST /api/v1/auth/logout`
Mengakhiri sesi atau mencabut token yang sedang digunakan.

### `GET /api/v1/auth/me`
Mengembalikan identitas pengguna terautentikasi tanpa informasi rahasia.

## 3. Endpoint admin

Semua endpoint berikut memerlukan role `admin`.

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/v1/admin/dashboard` | Ringkasan operasional non-pendapatan yang disetujui |
| GET, POST | `/api/v1/admin/users` | Daftar/buat user |
| GET, PATCH | `/api/v1/admin/users/{id}` | Detail/update user |
| POST | `/api/v1/admin/users/{id}/deactivate` | Nonaktifkan user |
| GET, POST | `/api/v1/admin/tariffs` | Daftar/buat tarif |
| GET, PATCH | `/api/v1/admin/tariffs/{id}` | Detail/update tarif |
| GET, POST | `/api/v1/admin/areas` | Daftar/buat area |
| GET, PATCH | `/api/v1/admin/areas/{id}` | Detail/update area |
| GET | `/api/v1/admin/vehicles` | Cari/lihat master kendaraan bila fitur ini disetujui |
| GET | `/api/v1/admin/activity-logs` | Lihat log aktivitas dengan pagination |
| GET | `/api/v1/admin/database/backup` | Unduh snapshot data aplikasi dalam JSON |
| POST | `/api/v1/admin/database/restore` | Pulihkan snapshot JSON resmi (multipart field `backup_file`) |

Penghapusan permanen user, tarif, area, atau kendaraan yang direferensikan transaksi sebaiknya dilarang. Gunakan status nonaktif atau aturan arsip.

### Backup dan pemulihan

- Kedua endpoint hanya dapat diakses admin dan menggunakan cookie session; endpoint restore wajib menyertakan `X-CSRF-Token`.
- Backup berisi seluruh data tabel aplikasi, termasuk hash password dan log. File unduhan harus disimpan secara aman dan tidak dibagikan.
- Restore hanya menerima format JSON versioned yang dihasilkan aplikasi, maksimum 50 MB, dan mengganti isi tabel aplikasi dalam satu transaksi.
- Restore mensyaratkan backup memuat akun admin aktif yang sama dengan sesi saat ini. Validasi atau kegagalan insert membatalkan transaksi.
- Request restore multipart juga harus menyertakan field `confirmation=PULIHKAN e_parking_ukk_app`.
- Demi mencegah salah sasaran, fitur hanya berjalan jika `DB_NAME=e_parking_ukk_app`. UI meminta admin mengunduh backup kondisi terkini, memilih file, lalu mengetik konfirmasi sebelum restore.
- SQL bebas tidak pernah dieksekusi. Database `parkir` tidak menjadi target fitur ini.

## 4. Endpoint petugas

Semua endpoint berikut memerlukan role `petugas`.

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/v1/operator/areas` | Daftar area aktif dan kapasitas |
| GET | `/api/v1/operator/tariffs` | Daftar tarif aktif untuk pilihan |
| GET | `/api/v1/operator/transactions/active` | Cari transaksi aktif dengan filter plat/ID |
| GET | `/api/v1/operator/transactions/history` | Riwayat dengan pencarian tiket/plat, tanggal, dan pagination (admin/petugas) |
| POST | `/api/v1/operator/transactions/entry` | Catat kendaraan masuk |
| GET | `/api/v1/operator/transactions/{id}` | Detail transaksi yang dibutuhkan petugas |
| POST | `/api/v1/operator/transactions/{id}/exit` | Proses kendaraan keluar |
| GET | `/api/v1/operator/transactions/{id}/receipt` | Data/halaman struk untuk transaksi yang boleh diakses |
| POST | `/api/v1/operator/transactions/{id}/payment` | Catat pembayaran tunai setelah transaksi keluar |
| PATCH | `/api/v1/operator/transactions/{id}/correction` | Koreksi plat/jenis/warna/area dengan alasan (admin/petugas) |

### Kendaraan masuk

`POST /api/v1/operator/transactions/entry`

Request contoh:
```json
{
  "plat_nomor": "AE1234BC",
  "jenis_kendaraan": "motor",
  "warna": "Hitam",
  "id_area": 2
}
```

Validasi:
- `plat_nomor` dinormalisasi trim/uppercase.
- `jenis_kendaraan` salah satu nilai yang disepakati.
- area aktif dan memiliki kapasitas.
- tidak ada transaksi aktif duplikat untuk kendaraan yang sama, kecuali aturan bisnis mengizinkan.
- tarif aktif tersedia untuk jenis kendaraan.
- `id_user`/petugas berasal dari user terautentikasi.

Response `201` contoh:
```json
{
  "data": {
    "id_parkir": 1042,
    "plat_nomor": "AE1234BC",
    "jenis_kendaraan": "motor",
    "nama_area": "Area A",
    "waktu_masuk": "2026-10-21T08:30:00+07:00",
    "status": "masuk"
  },
  "message": "Kendaraan berhasil dicatat masuk"
}
```

Jika penuh, kembalikan `409 Conflict` atau `422 Unprocessable Entity` secara konsisten dan dokumentasikan kode error. Tidak boleh membuat transaksi parsial.

### Kendaraan keluar

`POST /api/v1/operator/transactions/{id}/exit`

Request tidak boleh menerima `biaya_total`, `durasi_jam`, `waktu_keluar`, atau status final dari frontend. Nilai tersebut dihitung dan ditetapkan server/database.

Response `200` contoh:
```json
{
  "data": {
    "id_parkir": 1042,
    "waktu_masuk": "2026-10-21T08:30:00+07:00",
    "waktu_keluar": "2026-10-21T10:05:00+07:00",
    "durasi_jam": 2,
    "tarif_per_jam": "3000.00",
    "biaya_total": "6000.00",
    "status": "keluar"
  },
  "message": "Transaksi parkir selesai"
}
```

Jika transaksi sudah keluar, jangan menutupnya kembali atau mengurangi kapasitas lagi. Kembalikan error konflik yang konsisten. Proses keluar membuat tagihan berstatus `belum_lunas`; proses tersebut tidak mencatat pembayaran.

### Pembayaran tunai

`POST /api/v1/operator/transactions/{id}/payment`

Request:
```json
{
  "uang_diterima": "10000.00"
}
```

- Hanya petugas yang boleh mencatat pembayaran.
- Hanya transaksi keluar berstatus `belum_lunas` yang dapat dibayar.
- Nominal harus mencukupi tagihan. Backend menghitung dan menyimpan uang diterima, kembalian, waktu, petugas, metode `tunai`, dan status `lunas` secara atomik.
- Pembayaran kedua kali ditolak. Integrasi pembayaran digital tidak termasuk.

### Riwayat dan koreksi

`GET /api/v1/operator/transactions/history?q=B1234ABC&start_date=2026-10-01&end_date=2026-10-21&page=1&per_page=20`

- Pencarian mencakup nomor tiket atau plat; filter tanggal memakai tanggal keluar, atau tanggal masuk untuk transaksi yang masih aktif.
- Hanya admin/petugas yang dapat membaca endpoint tersebut. Response tidak memuat data pemilik/user.
- Role admin dan petugas boleh melakukan koreksi melalui `PATCH /api/v1/operator/transactions/{id}/correction`.
- Field yang dapat dikoreksi hanya `plat_nomor`, `jenis_kendaraan`, `warna`, dan `id_area`; `reason` wajib (5–500 karakter).
- Waktu, tarif, biaya, status, dan pembayaran tidak dapat dikoreksi lewat endpoint ini.
- Koreksi menyimpan snapshot identitas di transaksi agar koreksi tidak mengubah kendaraan pada riwayat transaksi lain.
- Perpindahan area untuk transaksi aktif memeriksa kapasitas dan memperbarui kedua counter dalam satu transaksi database.
- Audit menyimpan alasan dan nilai sebelum/sesudah. Kegagalan audit membatalkan koreksi.

## 5. Endpoint owner

Semua endpoint berikut memerlukan role `owner`.

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/v1/owner/reports/revenue` | Rekap pendapatan pada rentang tanggal |
| GET | `/api/v1/owner/reports/revenue.csv` | Ekspor CSV agregat harian/jenis kendaraan tanpa plat atau identitas user |
| GET | `/api/v1/owner/reports/summary` | Ringkasan jumlah kendaraan dan pendapatan |

Contoh query:
`GET /api/v1/owner/reports/revenue?start_date=2026-10-01&end_date=2026-10-21`

Response agregat:
```json
{
  "data": {
    "start_date": "2026-10-01",
    "end_date": "2026-10-21",
    "completed_transactions": 120,
    "paid_transactions": 110,
    "unpaid_transactions": 10,
    "total_charged": "750000.00",
    "total_revenue": "690000.00",
    "outstanding_balance": "60000.00",
    "by_vehicle_type": [
      {
        "jenis_kendaraan": "motor",
        "completed_transactions": 80,
        "total_revenue": "320000.00"
      }
    ]
  }
}
```

Aturan:
- Validasi tanggal dan batas rentang bila diperlukan.
- Rekomendasi filter pendapatan memakai tanggal `waktu_keluar`, karena pendapatan direalisasikan saat transaksi selesai.
- `total_revenue` berarti nilai tagihan yang sudah lunas; `total_charged` mencakup semua transaksi keluar; `outstanding_balance` hanya tagihan yang diketahui belum lunas.
- Transaksi lama yang belum memiliki data pembayaran dilaporkan sebagai `legacy_unknown`, bukan diasumsikan lunas atau belum lunas.
- Jangan kembalikan data user, password, atau transaksi mentah melalui endpoint owner.
- Jika daftar transaksi tingkat baris diwajibkan untuk UKK, revisi `PROJECT.md` dan `DATABASE.md` terlebih dahulu.

## 6. Bentuk error

Gunakan bentuk konsisten, misalnya:
```json
{
  "message": "Validasi gagal",
  "errors": {
    "id_area": ["Area parkir sudah penuh."]
  }
}
```

- `401`: belum login atau kredensial tidak valid.
- `403`: sudah login tetapi role tidak berhak.
- `404`: resource tidak ditemukan atau sengaja disamarkan sesuai kebijakan.
- `409`: konflik state, misalnya transaksi sudah selesai atau area penuh.
- `419`: token CSRF hilang/tidak valid.
- `422`: input tidak valid.
- `500`: error server generik; jangan mengirim stack trace ke klien.

## 7. Pagination dan filter

Endpoint daftar harus memakai pagination server-side. Validasi dan batasi parameter `page`, `per_page`, pencarian, urutan, dan tanggal. Jangan menerima nama kolom `ORDER BY` bebas dari request.

## 8. Kontrak stabil

Perubahan nama field, format nominal, role, status, atau endpoint harus:
1. memperbarui `API.md`;
2. memperbarui test backend;
3. memperbarui pemanggil React;
4. memberi tahu coordinator/agent lain sebelum merge.
