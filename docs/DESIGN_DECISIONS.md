# DESIGN_DECISIONS.md — Keputusan dan Asumsi E-Parking

Dokumen ini membedakan pilihan yang telah dikonfirmasi dari rekomendasi dan pertanyaan yang belum diputuskan. Placeholder bukan requirement final.

## Keputusan dikonfirmasi

### D-001 — Stack aplikasi
- Status: ACCEPTED
- Backend: PHP native tanpa Laravel atau framework PHP lain.
- Tampilan: React dengan Vite.
- Susunan repository: backend PHP dan frontend React/Vite pada satu repository.
- Database target aplikasi: MySQL/MariaDB.
- Dikonfirmasi pemilik pada 2026-10-21.

### D-002 — Database yang telah berisi data
- Status: ACCEPTED — batasan operasional
- Pemilik menyatakan database sudah berisi data dan menginstruksikan: jangan sentuh database.
- Tidak boleh membuat koneksi, inspeksi read-only, menjalankan query, migration, seeder, import, atau perubahan skema/data.
- Database baru hanya boleh digunakan setelah ada persetujuan eksplisit dan dipastikan terisolasi; larangan saat ini tetap berlaku untuk database yang sudah berisi data.

### D-003 — Kebutuhan produk dasar
- Status: ACCEPTED
- Aplikasi manajemen parkir web untuk UKK RPL.
- Role: `admin`, `petugas`, `owner`.
- Jenis kendaraan awal: `motor`, `mobil`, `lainnya`.
- Kendaraan ditolak saat area penuh; durasi dibulatkan ke atas dengan minimum satu jam.
- Struk dapat dicetak dan aktivitas penting dicatat.
- Owner mendapat laporan read-only, bukan manajemen user atau transaksi mentah.

## Lingkungan yang diperiksa

Workstation lokal saat pemeriksaan: PHP 8.2.12, Composer 2.10.2, Node.js 25.4.0, npm 11.7.0, MariaDB 10.4.32. Ini bukan konfirmasi bahwa mesin target UKK memakai versi yang sama. Database tidak diperiksa.

## Masih perlu keputusan/izin

### D-004 — Versi target UKK
- Status: BLOCKED
- Konfirmasi versi PHP, Node.js, dan MySQL/MariaDB pada mesin penilaian.
- Dampak: kompatibilitas implementasi dan cara menjalankan aplikasi.

### D-005 — Autentikasi browser
- Status: PROPOSED
- Rekomendasi: session PHP dengan cookie aman dan CSRF, menggunakan same-origin di deployment dan proxy Vite saat development.
- Belum dipilih: detail timeout session dan kebijakan logout.

### D-006 — Skema database aktual dan migration
- Status: BLOCKED
- Skema pada `DATABASE.md` adalah kebutuhan/rancangan yang belum diverifikasi terhadap database aktual.
- Dilarang menginspeksi database saat ini. Minta izin eksplisit atau database test kosong yang terisolasi sebelum meninjau skema maupun menguji migration.
- Pilih mekanisme migration terversi untuk PHP native sebelum membuat/menerapkan perubahan skema.

### D-007 — Periode pendapatan owner
- Status: BLOCKED
- Rekomendasi: gunakan `waktu_keluar`, karena pendapatan terealisasi saat transaksi selesai.
- Konfirmasi apakah laporan hanya agregat atau memerlukan baris transaksi terkurasi.

### D-008 — Snapshot tarif
- Status: PROPOSED
- Rekomendasi: simpan snapshot tarif pada transaksi masuk agar perubahan tarif tidak mengubah transaksi berjalan/historis.
- Perlu persetujuan skema sebelum implementasi; jangan menambah kolom ke database yang sudah berisi data tanpa izin.

### D-009 — Tarif lebih dari 24 jam
- Status: BLOCKED
- Tentukan tarif tetap per jam, tarif harian, maksimum harian, atau aturan lain. Jangan mengarang tarif harian/progresif.

### D-010 — Kendaraan aktif ganda
- Status: PROPOSED
- Rekomendasi: satu kendaraan tidak mempunyai lebih dari satu transaksi berstatus `masuk` pada waktu yang sama.
- Konfirmasi aturan duplikasi dan penanganan konkurensi.

### D-011 — Stored procedure
- Status: BLOCKED
- Dokumen awal menyebut `sp_parkir_keluar`. Tentukan apakah penilaian UKK memang mewajibkannya.
- Alternatif: satu service PHP dengan row locking. Jangan menduplikasi rumus biaya.

### D-012 — Format struk
- Status: BLOCKED
- Tentukan thermal (termasuk lebar kertas) atau A5 dan informasi tempat parkir yang dicetak.

### D-013 — Fitur tambahan
- Status: NOT IN SCOPE tanpa persetujuan
- QR/barcode, kamera, palang otomatis/IoT, payment gateway, notifikasi, aplikasi mobile, multi-cabang, denda tiket hilang, grace period, dan tarif progresif.

### D-014 — Pembayaran dipisah dari proses keluar
- Status: ACCEPTED
- Proses keluar menetapkan transaksi selesai tetapi belum lunas; petugas kemudian mencatat pembayaran tunai, uang diterima, dan kembalian.
- Pembayaran kurang dan pembayaran ganda ditolak.
- Ditentukan oleh pemilik pada 2026-10-21.

### D-015 — Riwayat dan cetak ulang
- Status: ACCEPTED
- Admin/petugas mencari dengan nomor tiket, plat, dan rentang tanggal; akses dibatasi backend.
- Ditentukan oleh pemilik pada 2026-10-21.

### D-016 — Pembaruan kapasitas
- Status: ACCEPTED
- Refresh manual, tanpa polling otomatis.
- Ditentukan oleh pemilik pada 2026-10-21.

### D-017 — Ekspor laporan
- Status: ACCEPTED
- CSV agregat berdasarkan tanggal/jenis kendaraan, tanpa plat, nomor tiket, atau identitas user.
- Tagihan, lunas, belum lunas, serta transaksi lama dengan status pembayaran tak diketahui dipisahkan.
- Ditentukan oleh pemilik pada 2026-10-21.

### D-018 — Hak dan cakupan koreksi
- Status: ACCEPTED
- Admin dan semua petugas boleh mengoreksi plat, jenis kendaraan, warna, dan area.
- Wajib ada alasan dan audit nilai sebelum/sesudah. Waktu, tarif, tagihan, status, serta pembayaran tidak dapat dikoreksi lewat fitur ini.
- Pemindahan transaksi aktif harus memperbarui counter area secara atomik.
- Ditentukan oleh pemilik pada 2026-10-21.

## Catatan perubahan keputusan

Tambahkan catatan dengan format:
- Tanggal:
- ID:
- Keputusan:
- Alasan:
- Dampak file/API/database:
- Disetujui oleh:
