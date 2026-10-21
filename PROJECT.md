# PROJECT.md — Product Requirements E-Parking

## 1. Ringkasan

E-Parking adalah aplikasi manajemen parkir berbasis web untuk kebutuhan UKK Rekayasa Perangkat Lunak. Sistem mengelola kendaraan masuk/keluar, tarif, area dan kapasitas, bukti pembayaran/struk, laporan pendapatan, serta jejak aktivitas pengguna.

## 1.1 Stack yang dipilih

- Backend: PHP native tanpa Laravel/framework PHP lain, menyediakan REST API JSON.
- Tampilan: React dengan Vite pada folder `frontend/`.
- Database yang direncanakan: MySQL/MariaDB melalui PDO prepared statements.
- Frontend dan API ditargetkan same-origin saat deployment; Vite memakai proxy saat development.
- Database yang sudah berisi data tidak boleh disentuh, termasuk inspeksi read-only, sampai pemilik memberi izin.

## 2. Tujuan

- Mengganti pencatatan parkir manual dengan pencatatan digital.
- Mengurangi kesalahan perhitungan biaya.
- Mencegah kendaraan masuk ke area yang sudah penuh.
- Memastikan transaksi masuk/keluar konsisten walaupun ada permintaan bersamaan.
- Membatasi data berdasarkan role.
- Menyediakan rekap pendapatan berdasarkan rentang tanggal.
- Menyediakan audit trail untuk aktivitas penting.

## 3. Aktor dan hak akses

### Admin
Diizinkan:
- Mengelola user: membuat, melihat, memperbarui, menonaktifkan; penghapusan permanen sebaiknya dihindari jika user memiliki riwayat transaksi.
- Mengelola tarif parkir.
- Mengelola area parkir dan kapasitas.
- Mengelola data kendaraan/master jika memang dibutuhkan.
- Melihat log aktivitas.

Tidak diizinkan:
- Membuat transaksi masuk/keluar.
- Mengakses fungsi kasir transaksi.

### Petugas
Diizinkan:
- Mencatat kendaraan masuk.
- Mencari transaksi aktif untuk kendaraan.
- Memproses kendaraan keluar.
- Melihat detail transaksi yang diperlukan untuk operasional.
- Mencetak struk masuk/keluar.

Tidak diizinkan:
- Mengubah tarif.
- Mengelola user.
- Mengakses rekap pendapatan owner.
- Mengakses log aktivitas administratif kecuali kebutuhan operasional ditetapkan.

### Owner
Diizinkan:
- Memilih tanggal mulai dan tanggal selesai.
- Melihat rekap transaksi/pendapatan yang telah disaring.
- Melihat jumlah kendaraan dan total pendapatan agregat pada periode.

Tidak diizinkan:
- Melihat data user.
- Mengelola master data.
- Mengubah transaksi.
- Mengakses endpoint transaksi mentah.

**Catatan:** kebutuhan awal menyebut daftar transaksi melalui view rekap, tetapi juga melarang owner melihat data mentah transaksi. Implementasi awal sebaiknya menampilkan agregat pendapatan dan statistik ringkas. Jika daftar per transaksi memang wajib untuk UKK, buat view laporan terkurasi tanpa data user dan dokumentasikan persetujuannya.

## 4. Kebutuhan fungsional

### F-01 Autentikasi
- Login menggunakan username dan password.
- Hanya user aktif yang dapat login.
- Password disimpan menggunakan `password_hash()` dan diperiksa menggunakan `password_verify()`, bukan SHA-256 biasa.
- Logout membatalkan sesi/token yang relevan.
- Aktivitas login sukses dan gagal dapat dicatat tanpa menyimpan password/token.

### F-02 Manajemen user
- Admin membuat dan memperbarui user.
- Role hanya salah satu dari `admin`, `petugas`, `owner`.
- User yang sudah terkait transaksi dinonaktifkan, bukan dihapus permanen, kecuali kebijakan retensi menyatakan lain.
- Admin tidak dapat menghapus/menonaktifkan akun dirinya sendiri jika hal itu membuat sistem kehilangan admin terakhir; aturan ini perlu diuji.

### F-03 Tarif
- Tarif dikelola admin.
- Jenis kendaraan awal: `motor`, `mobil`, `lainnya`.
- Tarif harus bernilai non-negatif dan menggunakan tipe decimal.
- Tentukan apakah perubahan tarif hanya berlaku untuk transaksi baru atau juga transaksi yang sedang berjalan. Rekomendasi: simpan snapshot tarif saat masuk agar tarif historis konsisten.

### F-04 Area dan kapasitas
- Area memiliki kapasitas dan jumlah terisi.
- Kapasitas harus bilangan bulat positif.
- `0 <= terisi <= kapasitas`.
- Kendaraan tidak boleh masuk jika `terisi >= kapasitas`.
- Perubahan kapasitas tidak boleh membuat kapasitas lebih kecil daripada jumlah terisi.
- Update jumlah terisi harus atomik dan konsisten dengan transaksi aktif.

### F-05 Kendaraan masuk
- Petugas memasukkan plat nomor, jenis kendaraan, dan area parkir.
- Sistem memastikan tidak ada transaksi aktif untuk kendaraan yang sama, kecuali bisnis secara eksplisit mengizinkan.
- Sistem mengecek area aktif dan kapasitas dalam transaksi database.
- Sistem menyimpan waktu masuk, petugas, area, dan snapshot tarif yang digunakan.
- Sistem mengembalikan nomor/ID parkir untuk bukti masuk.

### F-06 Kendaraan keluar
- Petugas mencari kendaraan/transaksi aktif melalui ID parkir atau plat nomor.
- Sistem hanya menutup transaksi yang statusnya masih `masuk`.
- Durasi dihitung berdasarkan aturan pembulatan yang ditetapkan: pembulatan ke atas dan minimal satu jam.
- Contoh: 1 jam 10 menit ditagih 2 jam.
- Backend/database menghitung biaya; frontend tidak menghitung biaya final.
- Penutupan transaksi, perhitungan biaya, log, dan pengurangan kapasitas harus atomik.
- Permintaan ganda tidak boleh menghasilkan penagihan/pengurangan kapasitas ganda.

### F-07 Struk
- Tampilkan nomor parkir, plat nomor, jenis kendaraan, area, waktu masuk/keluar, durasi, tarif yang dipakai, total biaya, dan petugas sesuai kebijakan.
- Sediakan layout cetak menggunakan CSS print.
- Format final thermal atau A5 masih perlu dikonfirmasi.
- Data struk berasal dari hasil transaksi tersimpan, bukan perhitungan ulang di browser.

### F-08 Rekap owner
- Filter berdasarkan tanggal mulai dan tanggal selesai.
- Tanggal valid; tanggal mulai tidak boleh setelah tanggal selesai.
- Menampilkan total pendapatan transaksi yang selesai pada periode yang dipilih, jumlah kendaraan selesai, dan ringkasan per jenis kendaraan bila diperlukan.
- Tetapkan secara eksplisit apakah filter periode memakai `waktu_keluar` (direkomendasikan untuk pendapatan) atau `waktu_masuk`.
- Owner tidak dapat mengubah data.

### F-09 Audit trail
Catat aktivitas penting:
- login berhasil/gagal;
- pembuatan/perubahan/nonaktif user;
- perubahan tarif dan area;
- kendaraan masuk/keluar;
- akses atau kegagalan akses penting jika dibutuhkan.

Log tidak boleh menyimpan password, token, atau rahasia. Perubahan log tidak boleh tersedia sebagai CRUD umum untuk role biasa.

### F-10 Pembayaran tunai terpisah
- Proses keluar menyelesaikan parkir dan menetapkan tagihan `belum_lunas`; tidak otomatis menyatakan lunas.
- Petugas dapat mencatat pembayaran tunai setelah kendaraan keluar.
- Backend menolak pembayaran kurang, menghitung kembalian, dan menyimpan petugas/waktu/metode/status secara atomik.
- Pembayaran kedua kali ditolak. Transaksi lama tanpa bukti pembayaran diberi status `legacy_unknown` saat migrasi, bukan diasumsikan lunas atau belum lunas.

### F-11 Riwayat dan cetak ulang
- Admin dan petugas dapat mencari riwayat menggunakan nomor tiket/plat serta rentang tanggal dengan pagination.
- Petugas dapat mencatat pembayaran dan mencetak ulang struk; admin dapat melihat/cetak ulang dan mengoreksi.
- Filter tanggal memakai waktu keluar, atau waktu masuk bila transaksi masih aktif.
- Hasil tidak menyertakan data pemilik atau rahasia user.

### F-12 Ringkasan kapasitas dengan refresh manual
- Halaman petugas/admin menampilkan kapasitas, terisi, dan slot tersisa per area.
- Pengguna dapat memperbarui angka melalui tombol refresh manual.
- Area nonaktif tidak ditawarkan untuk transaksi masuk; angka kapasitas tetap berasal dari server.

### F-13 Koreksi transaksi
- Admin dan semua petugas boleh mengoreksi plat, jenis kendaraan, warna, dan area.
- Alasan wajib; audit menyimpan nilai sebelum/sesudah dan aktor.
- Waktu, tarif, tagihan, dan pembayaran tidak dapat dikoreksi melalui alur ini.
- Pemindahan transaksi aktif mengunci area, memvalidasi kapasitas, dan memperbarui counter secara atomik.
- Koreksi menyimpan snapshot per transaksi agar riwayat kendaraan lain tidak berubah.

### F-14 Ekspor laporan owner
- Owner dapat mengunduh CSV agregat berdasarkan rentang tanggal terpilih.
- CSV dikelompokkan menurut tanggal keluar dan jenis kendaraan; memuat jumlah transaksi, total tagihan, total terbayar, dan total belum lunas.
- CSV tidak berisi plat, nomor tiket, maupun identitas user.
- `total_revenue` pada laporan berarti tagihan yang telah lunas; nilai tagihan dan piutang dilaporkan terpisah.

## 5. Kebutuhan nonfungsional

- Validasi server untuk seluruh input.
- Otorisasi server pada semua route sensitif.
- Request yang mengubah state dilindungi token CSRF dan session cookie aman.
- Query aman menggunakan PDO prepared statements dan parameter binding.
- Transaksi database untuk operasi multi-tabel.
- Respons API konsisten.
- UI responsif dan dapat digunakan pada desktop/tablet.
- Waktu dan timezone konsisten.
- Error teknis tidak mengekspos stack trace di production.
- Test PHP dan React untuk role, kapasitas, biaya, dan transaksi bersamaan.
- Tidak memakai database root dari aplikasi.

## 6. Di luar ruang lingkup sampai dikonfirmasi

Jangan mengimplementasikan tanpa persetujuan:
- pembayaran digital/payment gateway;
- QR code/barcode;
- pengenalan plat nomor melalui kamera;
- palang otomatis/IoT;
- aplikasi mobile;
- notifikasi SMS/WhatsApp;
- multi-lokasi/cabang;
- tarif progresif, tarif menginap, denda tiket hilang, atau grace period.

## 7. Kriteria penerimaan utama

1. User nonaktif tidak dapat login.
2. Role tidak berhak menerima `403` ketika memanggil endpoint terlarang langsung.
3. Area penuh menolak kendaraan masuk.
4. Dua permintaan masuk bersamaan tidak melebihi kapasitas.
5. Durasi dibulatkan sesuai aturan yang disetujui.
6. Transaksi yang sudah keluar tidak dapat diproses keluar lagi.
7. Kapasitas berkurang tepat sekali saat transaksi keluar berhasil.
8. Owner hanya dapat melihat data rekap yang disetujui.
9. Struk sesuai data transaksi tersimpan.
10. Audit log dibuat untuk aktivitas penting.
11. Proses keluar tidak otomatis menandai pembayaran lunas; petugas dapat mencatat tunai tepat satu kali dan melihat kembalian.
12. Riwayat mendukung pencarian tiket/plat/tanggal, cetak ulang, dan koreksi terbatas yang diaudit.
13. CSV owner hanya berisi agregat serta memisahkan tagihan, terbayar, dan belum lunas.
