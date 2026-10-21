# DATABASE.md — Skema Database E-Parking

## 1. Prinsip

- Database: MySQL/MariaDB; database aplikasi baru default `e_parking_ukk_app`.
- Aplikasi backend menggunakan PHP native dan PDO, tanpa Laravel.
- Nama tabel `tb_*` dipertahankan agar dekat dengan dokumen UKK; nama ini adalah rancangan dari dokumen, bukan hasil inspeksi database aktual.
- Gunakan InnoDB agar foreign key, transaksi, dan row locking berfungsi.
- Uang menggunakan `DECIMAL(12,2)` atau presisi yang disetujui, bukan float.
- Semua perubahan skema harus terversi, dapat ditinjau, dan disetujui sebelum diterapkan. Mekanisme migration native PHP belum dipilih.
- Detail tipe/versi MySQL harus diverifikasi pada lingkungan UKK.

## 1.1 Batasan database saat ini

Database lama `parkir` berisi data dan tetap tidak boleh dikoneksi, diperiksa, atau diubah oleh aplikasi tanpa izin eksplisit. Pengguna meminta database baru terpisah; `database/setup_e_parking_ukk_app.sql` disediakan untuk membangunnya dengan skema aplikasi. File tersebut belum dijalankan. Jangan mengimpor file itu ke database `parkir` atau database lain yang sudah berisi data.

## 2. Tabel

### 2.1 `tb_user`
Kolom:
- `id_user`: BIGINT unsigned, primary key.
- `nama_lengkap`: VARCHAR(150), wajib.
- `username`: VARCHAR(80), unique, wajib.
- `password`: VARCHAR(255), wajib; berisi hasil `password_hash()` PHP, tidak pernah password teks biasa.
- `role`: VARCHAR/ENUM dengan nilai `admin`, `petugas`, `owner`.
- `status_aktif`: BOOLEAN, default true.
- `created_at`, `updated_at`: timestamps.

Jangan pernah mengirim kolom `password` dalam response API.

### 2.2 `tb_tarif`
- `id_tarif`: BIGINT unsigned, primary key.
- `jenis_kendaraan`: nilai `motor`, `mobil`, `lainnya`; unique jika hanya satu tarif aktif per jenis.
- `tarif_per_jam`: DECIMAL(12,2), tidak negatif.
- `status_aktif`: BOOLEAN.
- `created_at`, `updated_at`.

Jika kebutuhan tarif historis atau beberapa jenis tarif per periode muncul, gunakan tabel versi tarif; jangan mengubah skema diam-diam.

### 2.3 `tb_area_parkir`
- `id_area`: BIGINT unsigned, primary key.
- `nama_area`: VARCHAR(120), unique sesuai kebutuhan.
- `kapasitas`: INT unsigned, harus lebih besar dari nol.
- `terisi`: INT unsigned, default 0.
- `status_aktif`: BOOLEAN.
- `created_at`, `updated_at`.

Invariant: `terisi <= kapasitas`. Aplikasi wajib memvalidasi perubahan kapasitas. Jika DB tidak mendukung constraint yang dibutuhkan, enforce melalui service dan test.

### 2.4 `tb_kendaraan`
- `id_kendaraan`: BIGINT unsigned, primary key.
- `plat_nomor`: VARCHAR(20), dinormalisasi (trim dan uppercase), index; unique hanya jika aturan bisnis menyatakan satu record per plat.
- `jenis_kendaraan`: `motor`, `mobil`, `lainnya`.
- `warna`: VARCHAR(50), nullable.
- `pemilik`: VARCHAR(150), nullable; jangan meminta data pemilik jika tidak dibutuhkan.
- `dibuat_oleh_user_id`: FK ke `tb_user.id_user`, nullable sesuai aturan retensi.
- `created_at`, `updated_at`.

### 2.5 `tb_transaksi`
- `id_parkir`: BIGINT unsigned, primary key.
- `id_kendaraan`: FK ke `tb_kendaraan.id_kendaraan`.
- `id_area`: FK ke `tb_area_parkir.id_area`.
- `id_tarif`: FK ke `tb_tarif.id_tarif`.
- `petugas_masuk_id`: FK ke `tb_user.id_user`.
- `petugas_keluar_id`: FK ke `tb_user.id_user`, nullable sampai transaksi selesai.
- `waktu_masuk`: DATETIME, wajib.
- `waktu_keluar`: DATETIME, nullable.
- `plat_nomor_snapshot`, `jenis_kendaraan_snapshot`, `warna_snapshot`: snapshot nullable untuk transaksi lama; transaksi baru menyimpan nilai pada saat masuk/koreksi agar riwayat transaksi lain tidak berubah.
- `tarif_per_jam_snapshot`: DECIMAL(12,2), wajib; nilai tarif yang berlaku saat kendaraan masuk.
- `durasi_jam`: INT unsigned, nullable sampai selesai.
- `biaya_total`: DECIMAL(12,2), nullable sampai selesai.
- `status`: `masuk` atau `keluar`.
- `status_pembayaran`: `belum_lunas`, `lunas`, atau `legacy_unknown`.
- `metode_pembayaran`: `tunai`, nullable sebelum pembayaran.
- `uang_diterima`, `uang_kembalian`: DECIMAL(12,2), nullable sebelum pembayaran.
- `dibayar_oleh_user_id`: FK ke user petugas, nullable.
- `dibayar_pada`: DATETIME, nullable.
- `created_at`, `updated_at`.

Aturan:
- Status `masuk` berarti kendaraan masih berada di area.
- Status `keluar` berarti transaksi sudah ditutup.
- Proses keluar tidak menyatakan pembayaran lunas. Pembayaran tunai dicatat terpisah; `biaya_total` adalah tagihan, sedangkan `uang_diterima` dan `uang_kembalian` mencatat kasir.
- Migrasi atas transaksi historis memakai `legacy_unknown` karena status pembayaran lama tidak dapat disimpulkan. Jangan mengubah nilai lama menjadi lunas atau belum lunas tanpa rekonsiliasi yang disetujui.
- Koreksi kendaraan/area dibatasi pada snapshot transaksi; koreksi area untuk transaksi aktif juga memperbarui counter area secara atomik.
- Transaksi aktif harus memiliki `waktu_keluar IS NULL`; transaksi selesai memiliki `waktu_keluar`, `durasi_jam`, dan `biaya_total`.
- Perlu strategi yang sesuai database untuk mencegah dua transaksi aktif pada kendaraan yang sama. Jika partial unique index tidak tersedia/kompatibel, lakukan pengecekan dan penguncian di service serta uji konkurensi.
- Index yang disarankan: `(status, waktu_masuk)`, `(status, id_area)`, `waktu_keluar`, `(status_pembayaran, dibayar_pada)`, dan pencarian plat di tabel kendaraan.
- Jangan menghapus transaksi historis secara permanen.

### 2.6 `tb_log_aktivitas`
- `id_log`: BIGINT unsigned, primary key.
- `id_user`: FK ke `tb_user.id_user`, nullable untuk kejadian sistem atau user yang sudah dihapus sesuai kebijakan.
- `aktivitas`: VARCHAR(255), ringkasan yang aman.
- `metadata`: JSON nullable, hanya metadata nonrahasia.
- `ip_address`: VARCHAR(45), nullable; simpan hanya jika diperlukan dan sesuai kebijakan privasi.
- `waktu_aktivitas`: DATETIME.
- `metadata` untuk koreksi menyimpan alasan dan nilai sebelum/sesudah; jangan memasukkan password/token.
- `created_at`, `updated_at` jika model memakai timestamps.

Audit log tidak boleh memuat password, token, secret, atau data sensitif yang tidak diperlukan.

## 3. Relasi

- `tb_user` 1:N `tb_transaksi` sebagai petugas masuk.
- `tb_user` 1:N `tb_transaksi` sebagai petugas keluar.
- `tb_user` 1:N `tb_log_aktivitas`.
- `tb_kendaraan` 1:N `tb_transaksi` secara historis.
- `tb_area_parkir` 1:N `tb_transaksi`.
- `tb_tarif` 1:N `tb_transaksi`.

## 4. Integritas kapasitas

Jumlah `terisi` harus sama dengan jumlah transaksi berstatus `masuk` untuk area tersebut. Karena angka terisi adalah counter denormalisasi:
- semua mutasi harus melalui satu service/action;
- kunci baris area menggunakan mekanisme row lock di dalam transaction;
- tambah `terisi` hanya setelah kapasitas diverifikasi;
- kurangi `terisi` hanya ketika transaksi aktif berhasil berubah menjadi selesai atau dipindahkan ke area lain;
- sediakan pemeriksaan rekonsiliasi untuk membandingkan counter dengan transaksi aktif.

Jangan mengubah `terisi` dari controller atau job lain secara langsung.

## 5. View rekap

Nama yang direncanakan: `v_rekap_transaksi`.

View laporan hanya menyertakan kolom yang memang disetujui untuk pelaporan. Hindari `SELECT *`, password, username, ID user, dan metadata internal.

Ada dua opsi:
1. **Direkomendasikan untuk kebutuhan privasi:** view agregat berdasarkan tanggal/jenis kendaraan, dengan total pendapatan dan jumlah transaksi; owner tidak melihat transaksi per kendaraan.
2. **Jika penilaian UKK mewajibkan daftar transaksi:** view terkurasi berisi nomor parkir, plat, jenis kendaraan, area, waktu masuk/keluar, durasi, biaya, dan status. Ini tetap data transaksi tingkat baris, jadi harus disetujui karena bertentangan dengan larangan melihat data mentah transaksi.

Backend PHP dapat membaca view melalui PDO dengan query read-only. Jangan menganggap view otomatis aman bila endpoint tetap bisa membaca tabel transaksi secara langsung.

## 6. Stored procedure untuk transaksi keluar

Spesifikasi awal meminta `sp_parkir_keluar`. Implementasi dapat mempertahankan prosedur ini jika versi MySQL/MariaDB dan lingkungan penilaian mendukungnya.

Kontrak yang disarankan:
- Input: `p_id_parkir`, `p_id_user`.
- Dipanggil dari service PHP.
- PHP membuka transaction PDO; prosedur (bila akhirnya dipilih) melakukan `SELECT ... FOR UPDATE` pada transaksi dan area terkait.
- Prosedur memeriksa status masih `masuk`, mengambil snapshot tarif, menghitung durasi dan biaya, mengubah status, mengurangi kapasitas tepat sekali, dan menulis audit log.
- Prosedur mengembalikan data hasil untuk struk melalui result set atau query setelah prosedur selesai.
- **Jangan melakukan `START TRANSACTION`, `COMMIT`, atau `ROLLBACK` sendiri di prosedur jika PHP mengelola transaksi di luar**, karena pengelolaan transaksi bersarang tidak berjalan seperti nested transaction umum. Error harus dipropagasikan agar PHP melakukan rollback.
- Tangani kasus transaksi tidak ditemukan, sudah keluar, tarif tidak valid, dan counter area tidak konsisten.

Rumus awal:
- `durasi_menit = selisih waktu_keluar dan waktu_masuk dalam menit`.
- `durasi_jam = max(1, ceil(durasi_menit / 60))`.
- `biaya_total = durasi_jam * tarif_per_jam_snapshot`.

Aturan pembulatan waktu yang sangat dekat batas jam, presisi timestamp, dan tarif menginap perlu dites. Penggunaan stored procedure masih belum diputuskan. Alternatifnya adalah service PHP dengan row locking dan test yang setara. Jangan menjalankan dua sumber perhitungan yang berbeda.

## 7. Database privileges

- Jangan gunakan user `root` untuk aplikasi.
- User runtime PHP hanya diberi hak yang dibutuhkan oleh aplikasi. Jika migration membutuhkan DDL, gunakan akun migrasi terpisah dari akun runtime bila memungkinkan.
- Jika menerapkan user DB berbeda untuk admin/petugas/owner, pahami bahwa aplikasi PHP biasanya menggunakan satu koneksi DB; pembatasan role tetap harus diterapkan di middleware/guard aplikasi. Akun DB terpisah bukan pengganti otorisasi aplikasi.
- Untuk kebutuhan view-only owner, akses hanya melalui endpoint laporan yang terotorisasi; jika perlu, gunakan koneksi pelaporan terpisah dengan hak `SELECT` pada view saja.
