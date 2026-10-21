# TESTING.md — Strategi Pengujian E-Parking

## 1. Prinsip

Test harus memeriksa hasil dan keamanan backend, bukan hanya apakah halaman terlihat. Gunakan database test terpisah dan jangan menjalankan test destruktif terhadap database berisi data penting.

## 2. Backend PHP native

Backend menggunakan PHP tanpa framework. Pilih runner test PHP (misalnya PHPUnit) saat bootstrap dan dokumentasikan dependency-nya; jangan menganggap test sudah berjalan sebelum runner dan test benar-benar tersedia. Pisahkan test unit untuk aturan murni dari test integrasi API/database.

Database test harus berupa database sementara yang terisolasi dan disetujui. Database pengguna yang telah berisi data tidak boleh dikoneksi, diinspeksi, atau dipakai oleh test.

### Autentikasi
- Login dengan kredensial benar.
- Login dengan password salah.
- Login user nonaktif ditolak.
- Logout membatalkan sesi/token.
- Response tidak mengandung hash password.
- Request mutasi tanpa token CSRF atau dengan token salah ditolak; token benar diterima.

### Otorisasi
- Admin dapat mengakses modul admin.
- Admin tidak dapat membuat transaksi parkir.
- Petugas dapat mengakses transaksi.
- Petugas tidak dapat mengubah tarif/user atau melihat laporan owner.
- Owner dapat membaca rekap.
- Owner ditolak ketika memanggil endpoint admin, operator, atau transaksi mentah.

### Tarif dan area
- Tarif negatif ditolak.
- Jenis kendaraan tidak valid ditolak.
- Kapasitas nol/negatif ditolak.
- Kapasitas tidak dapat diturunkan di bawah jumlah terisi.
- Area nonaktif tidak menerima kendaraan.

### Kendaraan masuk
- Entry berhasil dan membuat transaksi status `masuk`.
- Area penuh menolak entry.
- Counter terisi bertambah tepat satu setelah berhasil.
- Kegagalan pada langkah tengah me-rollback seluruh perubahan.
- Plat dinormalisasi.
- Kendaraan dengan transaksi aktif tidak dapat masuk lagi jika aturan ini dipilih.
- Request bersamaan tidak dapat melampaui kapasitas.

### Kendaraan keluar
- Durasi minimum satu jam.
- Durasi 1 jam 10 menit menjadi 2 jam sesuai aturan pembulatan.
- Biaya menggunakan snapshot tarif yang tersimpan.
- Transaksi status `masuk` dapat ditutup.
- Transaksi yang sudah keluar tidak dapat ditutup lagi.
- Counter terisi berkurang tepat satu.
- Kegagalan prosedur/service me-rollback transaksi dan kapasitas.
- Request bersamaan untuk ID parkir yang sama hanya menghasilkan satu penutupan berhasil.
- Proses keluar membuat status pembayaran `belum_lunas`, bukan mengklaim telah dibayar.

### Pembayaran
- Nominal tunai kurang dari tagihan ditolak tanpa mengubah status pembayaran.
- Pembayaran cukup menyimpan metode, petugas, waktu, uang diterima, kembalian, dan status `lunas`.
- Pembayaran ganda ditolak.
- Transaksi legacy dengan status pembayaran tidak diketahui tidak dilaporkan sebagai lunas.

### Riwayat dan koreksi
- Pencarian tiket/plat, rentang tanggal, serta pagination menghasilkan hasil yang sesuai.
- Cetak ulang menggunakan data transaksi tersimpan tanpa menjalankan proses keluar lagi.
- Koreksi tanpa alasan atau dengan field di luar whitelist ditolak.
- Koreksi mencatat nilai lama/baru dan alasan di audit metadata.
- Perpindahan area aktif memvalidasi kapasitas, mengubah counter kedua area, dan rollback penuh bila gagal.
- Koreksi tidak mengubah snapshot kendaraan transaksi lain.

### Laporan
- Rentang tanggal valid.
- Tanggal mulai setelah tanggal selesai ditolak.
- Hanya transaksi selesai yang dihitung sebagai pendapatan.
- Filter menggunakan field tanggal yang telah disepakati.
- Owner tidak memperoleh kolom user atau data yang tidak disetujui.
- Total tagihan, total lunas, dan piutang dipisahkan dengan benar.
- CSV hanya berisi agregat tanggal/jenis kendaraan; tidak menyertakan plat, tiket, atau user ID dan tidak membiarkan formula CSV.

### Audit
- Aktivitas penting membuat log.
- Log tidak berisi password, token, atau secret.
- Role selain admin tidak dapat mengakses daftar log.

## 3. Frontend React

Pilih runner frontend (misalnya Vitest dan React Testing Library) saat bootstrap. Test komponen/flow yang disepakati harus mencakup:
- form menampilkan pesan validasi;
- loading, empty, success, dan error state;
- menu sesuai role;
- route guard memberi pengalaman yang benar;
- data nominal ditampilkan dari response backend;
- halaman struk bisa dicetak dan tidak menampilkan tombol navigasi pada hasil print;
- UI menangani `401`, `403`, `409`, dan `422`.
- UI dapat mencatat pembayaran tunai, mencari/cetak ulang riwayat, dan menjalankan koreksi beralasan.
- Refresh kapasitas manual meminta data terbaru dari server; area nonaktif tidak dapat dipilih untuk entry.

Route guard frontend bukan pengganti test otorisasi backend.

## 4. Manual acceptance test

1. Login sebagai admin, buat tarif dan area.
2. Buat user petugas dan owner.
3. Login sebagai petugas, catat kendaraan masuk.
4. Pastikan area penuh menolak kendaraan berikutnya.
5. Proses kendaraan keluar dan periksa durasi/biaya.
6. Coba proses transaksi yang sama lagi; harus ditolak.
7. Cetak struk.
8. Login sebagai owner dan lihat rekap periode.
9. Coba buka endpoint terlarang secara langsung; harus menerima `403`.
10. Periksa audit log sebagai admin.

## 5. Pelaporan hasil

Untuk setiap test run, catat:
- tanggal/waktu;
- perintah yang dijalankan;
- jumlah test lulus/gagal;
- kegagalan yang diketahui;
- environment yang digunakan.

Jangan menulis “semua test lulus” sebelum test benar-benar dijalankan.
