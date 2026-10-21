# AGENTS.md — E-Parking UKK RPL

Dokumen ini adalah instruksi utama untuk semua AI coding agent yang bekerja pada proyek E-Parking. Instruksi ini berlaku untuk seluruh repository kecuali terdapat `AGENTS.md` yang lebih spesifik di subfolder.

## 1. Tujuan

Bangun aplikasi manajemen parkir berbasis web untuk UKK RPL dengan:
- Backend yang dipilih: PHP native tanpa framework; gunakan PHP sebagai API dan lapisan aturan bisnis.
- Frontend React sebagai antarmuka pengguna.
- MySQL/MariaDB sebagai basis data.
- Tiga role: `admin`, `petugas`, dan `owner`.
- Pencatatan kendaraan masuk/keluar, tarif, kapasitas area, struk, rekap pendapatan, dan audit log.

Baca `README.md`, `PROJECT.md`, `ARCHITECTURE.md`, `DATABASE.md`, `API.md`, `SECURITY.md`, dan `TASKS.md` sebelum memulai implementasi besar.

## 2. Aturan yang tidak boleh dilanggar

1. Jangan menghapus atau mengganti fitur yang sudah berjalan tanpa instruksi eksplisit.
2. Jangan mengubah file di luar ruang lingkup tugas kecuali memang diperlukan; jelaskan alasannya.
3. Jangan mengarang requirement bisnis. Jika keputusan belum ditentukan, catat sebagai pertanyaan/placeholder di `docs/DESIGN_DECISIONS.md`.
4. Jangan memasukkan password, token, secret, atau data pribadi sungguhan ke repository.
5. Jangan memakai akun database `root` untuk koneksi aplikasi.
6. Jangan menyimpan password dengan SHA-256 biasa. Gunakan `password_hash()` dan `password_verify()` PHP dengan algoritma password yang didukung.
7. Jangan mempercayai role atau `user_id` dari request frontend. Ambil identitas dari autentikasi server.
8. Jangan mengizinkan role `admin` melakukan transaksi masuk/keluar; role `petugas` mengelola transaksi; role `owner` hanya mengakses rekap yang disetujui.
9. Jangan menghitung total biaya parkir di komponen React.
10. Perhitungan biaya dan perubahan status transaksi harus dilakukan secara otoritatif di backend/database, dalam transaksi atomik.
11. Jangan mengurangi/menambah kapasitas area tanpa mengunci baris area dan menjaga konsistensi transaksi.
12. Jangan memproses kendaraan keluar dua kali atau mengurangi kapasitas dua kali.
13. Jangan mengekspos tabel user atau data mentah yang tidak dibutuhkan kepada owner.
14. Jangan mengklaim test, migrasi, build, atau fitur berhasil jika belum benar-benar dijalankan dan diperiksa.
15. Jangan menjalankan perintah Git destruktif (`reset --hard`, `clean -fd`, force-push) tanpa izin eksplisit.

## 3. Aturan stack

- Backend: PHP native tanpa Laravel atau framework PHP lain. Gunakan PHP 8.2 sebagai baseline lokal yang telah diperiksa; konfirmasi versi mesin UKK sebelum mengandalkan fitur khusus versi.
- Frontend: React dengan Vite, berada di folder `frontend/` dalam repository yang sama dan berkomunikasi dengan API JSON PHP.
- Database: MySQL/MariaDB. Database saat ini sudah berisi data dan tidak boleh dikoneksi, diinspeksi, diubah, atau dimigrasikan.
- Semua query dinamis harus menggunakan PDO prepared statements dan parameter binding; hindari SQL concatenation.
- Lakukan validasi server-side, otorisasi melalui guard/middleware terpusat, dan aturan bisnis melalui service/action PHP.
- Perubahan skema harus terversi dan dapat ditinjau; mekanisme migration native PHP belum diputuskan dan tidak boleh dijalankan pada database yang sudah berisi data.
- Simpan tanggal/waktu dengan konsisten; dokumentasikan timezone aplikasi dan database.
- Uang disimpan sebagai `DECIMAL`, bukan float. Representasi JSON nominal uang berupa angka atau string desimal yang konsisten.

## 4. Aturan multi-agent

Tetapkan satu agent sebagai **Coordinator/Integrator**. Agent lain tidak boleh membuat keputusan arsitektur yang bertentangan tanpa menyepakatinya dengan coordinator.

### Peran yang disarankan
- **Coordinator:** menjaga scope, membaca dokumen, membagi tugas, menyelesaikan konflik, memeriksa integrasi, memperbarui `TASKS.md`.
- **Backend/API:** router/front controller PHP, validasi, service, guard otorisasi, repository PDO, dan test backend.
- **Frontend/UI:** halaman React, komponen, form, validasi tampilan, state loading/error, print receipt.
- **Database/Reporting:** desain migration, index, view/procedure bila dipilih, integritas data, query laporan.
- **QA/Security:** test role, concurrency, validasi, keamanan, regression test, review perubahan.

Satu file atau modul sebaiknya hanya memiliki satu agent penanggung jawab pada satu waktu. Jika dua agent perlu menyentuh file yang sama, coordinator menetapkan urutan kerja atau meminta patch terpisah.

### Kontrak sebelum bekerja
Setiap agent harus menyampaikan:
1. Tujuan dan ruang lingkup.
2. File yang akan dibuat/diubah.
3. Kontrak API/data yang dibutuhkan.
4. Risiko atau keputusan yang belum jelas.
5. Test yang akan dijalankan.

### Kontrak setelah bekerja
Agent harus menyampaikan:
1. File yang berubah.
2. Ringkasan implementasi.
3. Perintah test yang benar-benar dijalankan dan hasilnya.
4. Hal yang belum selesai.
5. Perubahan dokumentasi yang dibutuhkan.

## 5. Workflow wajib

1. Periksa struktur repository, versi PHP/Node, dependency, status repository jika tersedia, dan dokumentasi.
2. Stack telah dipilih: PHP native API + React/Vite dalam satu repository. Jangan menggantinya tanpa persetujuan.
3. Pecah pekerjaan menjadi tugas kecil di `TASKS.md`.
4. Konfirmasi akses database dan skema terlebih dahulu; buat mekanisme migration serta kontrak API sebelum frontend bergantung pada struktur data yang belum stabil.
5. Implementasikan otorisasi backend sebelum menganggap UI role sudah aman.
6. Terapkan aturan transaksi masuk/keluar dalam service dan database transaction.
7. Tulis test untuk happy path, validasi, akses terlarang, dan kasus konkurensi.
8. Jalankan migration, test, lint/build yang tersedia.
9. Perbarui dokumen jika kontrak atau skema berubah.
10. Laporkan hasil tanpa klaim yang belum diverifikasi.

## 6. Kriteria kualitas

- Tidak ada route sensitif tanpa autentikasi dan otorisasi.
- Tidak ada password/token di log atau response.
- Validasi dilakukan di server PHP.
- Semua perubahan multi-tabel bersifat atomik.
- Kapasitas tidak dapat menjadi negatif atau melebihi batas.
- Satu transaksi parkir tidak dapat ditutup dua kali.
- Owner hanya mendapat data rekap yang telah disetujui.
- UI menyediakan loading, empty, success, dan error state.
- Test otomatis mencakup alur kritis.
- Dokumentasi sesuai implementasi aktual.

## 7. Keputusan yang harus dikonfirmasi

Catat keputusan belum ditentukan di `docs/DESIGN_DECISIONS.md`, terutama:
- Versi PHP/Node/MySQL yang tersedia pada komputer UKK.
- Mekanisme migration dan runner test untuk PHP native.
- Apakah QR/barcode, kamera, palang otomatis, atau pembayaran digital diperlukan.
- Aturan tarif untuk durasi lebih dari 24 jam, pergantian hari, dan kendaraan menginap.
- Apakah tarif disimpan sebagai snapshot saat kendaraan masuk.
- Format struk final: thermal atau A5, lebar kertas, identitas tempat parkir.
- Zona waktu operasional.
