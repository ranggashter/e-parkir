# SECURITY.md — Keamanan E-Parking

## 1. Password dan autentikasi

- Gunakan `password_hash()` dan `password_verify()` PHP; jangan gunakan SHA-256 biasa untuk password.
- Tolak login untuk user dengan `status_aktif = false`.
- Gunakan pesan login generik agar tidak membocorkan apakah username terdaftar.
- Terapkan rate limiting pada login.
- Jangan log password, hash password, token, cookie, atau header Authorization.
- Pilih satu strategi autentikasi yang sesuai deployment; jika memakai cookie/session, aktifkan perlindungan CSRF dan cookie secure pada HTTPS.

## 2. Otorisasi role

- `admin`: user, tarif, area, audit log; dilarang transaksi masuk/keluar.
- `petugas`: transaksi masuk/keluar dan struk; dilarang mengubah user/tarif serta mengakses rekap owner.
- `owner`: hanya endpoint laporan yang disetujui.
- Periksa role di backend melalui guard otorisasi terpusat.
- Jangan menerima role dari body, query, localStorage, atau state frontend sebagai bukti hak akses.
- Uji request langsung ke API untuk tiap role, bukan hanya tampilan menu.

## 3. SQL injection dan database

- Database yang telah berisi data tidak boleh dikoneksi, diinspeksi, atau diubah tanpa izin eksplisit pemilik.
- Gunakan PDO prepared statements dan parameter binding.
- Jangan menyambungkan input pengguna ke string SQL.
- Untuk query dinamis seperti sorting, gunakan allowlist nama kolom.
- Stored procedure tetap harus memvalidasi input dan memakai query yang aman.
- Gunakan akun database non-root.
- Pisahkan akun migration/DDL dari runtime jika memungkinkan.
- Batasi hak akses reporting jika memakai koneksi khusus.
- Jangan menganggap satu view cukup untuk keamanan jika role aplikasi masih dapat memanggil endpoint tabel mentah.
- Endpoint backup/restore hanya tersedia untuk admin dan dibatasi ke database `e_parking_ukk_app`; jangan izinkan target database ditentukan dari request.
- Backup mengandung hash password dan data operasional. Kirim sebagai unduhan `no-store`, jangan simpan salinan server tanpa perlindungan, dan simpan file pengguna di lokasi aman.
- Restore hanya menerima format JSON internal dengan tabel/kolom allowlist, bukan SQL bebas. Validasi format dan akun admin aktif dilakukan sebelum perubahan; penghapusan dan pengisian tabel berada dalam transaksi sehingga error memicu rollback.
- UI meminta unduhan backup terkini dan konfirmasi eksplisit sebelum restore; ini tidak menggantikan backup eksternal rutin dan prosedur pemulihan yang diuji.

## 4. XSS dan output

- React melakukan escaping default untuk text node; jangan memakai `dangerouslySetInnerHTML` untuk data pengguna tanpa sanitasi yang tepat.
- Untuk halaman print, jangan menyisipkan string ke HTML mentah.
- Validasi dan normalisasi plat nomor, nama, area, serta input teks.
- Hindari menampilkan stack trace atau query SQL kepada pengguna.

## 5. CSRF, CORS, dan session/token

- Untuk autentikasi cookie/session PHP, aktifkan session strict mode, perlindungan CSRF untuk request yang mengubah state, dan regenerasi session ID setelah login.
- Semua POST/PATCH/PUT/DELETE harus memverifikasi token CSRF session lewat header `X-CSRF-Token`; jangan menerima wildcard CORS dengan credential.
- Batasi CORS ke origin frontend yang benar-benar digunakan; jangan memakai wildcard dengan credential.
- Gunakan HTTPS pada deployment.
- Atur masa berlaku session/token, logout, dan pencabutan token.
- Jangan simpan token rahasia di source code atau `.env.example`.

## 6. Integritas transaksi

- Gunakan database transaction untuk perubahan transaksi, kapasitas, dan audit log.
- Lock baris yang relevan ketika memeriksa kapasitas atau menutup transaksi.
- Cegah transaksi aktif ganda untuk kendaraan yang sama.
- Cegah penutupan transaksi dua kali.
- Hitung biaya final di backend/database, bukan browser.
- Gunakan snapshot tarif agar perubahan tarif tidak mengubah transaksi historis secara diam-diam.
- Jika transaksi gagal, rollback seluruh perubahan terkait.
- Pembayaran tunai hanya boleh ditambahkan satu kali pada transaksi keluar yang belum lunas; hitung uang kembali di server dan audit petugas/waktu.
- Koreksi data transaksi hanya dapat mengubah plat/jenis/warna/area, memerlukan alasan, mencatat nilai sebelum/sesudah, dan mengunci counter area bila transaksi masih aktif.
- Ekspor CSV laporan hanya mengandung agregat; lindungi sel teks dari formula injection.

## 7. Audit trail

Catat aktor, aktivitas, waktu, dan metadata minimum yang diperlukan. Batasi akses log ke admin yang berwenang. Jangan menyimpan password, token, rahasia, atau payload request lengkap secara default.

## 8. Secrets dan konfigurasi

- `.env` harus masuk `.gitignore`.
- Commit hanya `.env.example` dengan placeholder.
- Rotasi credential jika pernah ter-commit.
- Matikan debug di production.
- Batasi izin filesystem dan backup.
- Jangan memakai data pribadi nyata pada fixture/test tanpa kebutuhan dan izin.

## 9. Pengujian keamanan minimum

- Login gagal dan user nonaktif.
- Petugas mencoba mengubah tarif/user.
- Admin mencoba membuat transaksi.
- Owner mencoba endpoint transaksi mentah.
- Input SQL injection/XSS pada field teks.
- CSRF/CORS sesuai mode autentikasi.
- Request transaksi ganda dan request keluar ganda.
- Error server tidak membocorkan stack trace.
