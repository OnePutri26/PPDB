# Portal PPDB Sekolah

Aplikasi PPDB berbasis PHP native, PDO, MySQL, Bootstrap 5, dan JavaScript. Portal menyediakan registrasi/login calon siswa, formulir bertahap, unggah dokumen, pembayaran, dashboard siswa, verifikasi panitia, seleksi, daftar ulang, pengumuman, ekspor CSV, dan cetak bukti ke PDF melalui browser.

Antarmuka menggunakan identitas Neo Education: editorial typography, warm-cream canvas, electric purple, tangerine, lime, ilustrasi vektor orisinal, dan layout adaptif untuk landing page, autentikasi, formulir, serta portal siswa/panitia. Statistik publik tetap opsional dan sebaiknya hanya diisi dengan angka sekolah yang terverifikasi.

## Persyaratan

- PHP 8.1+ dengan `pdo_mysql`, `fileinfo`, `mbstring`, dan sesi aktif.
- MySQL 8+ atau MariaDB yang mendukung InnoDB, foreign key, dan `utf8mb4`.
- Apache (XAMPP) atau web server PHP lain yang diarahkan ke folder proyek.
- Koneksi internet untuk CDN Bootstrap, Font Awesome, dan Chart.js. File aplikasi tetap berjalan tanpa CDN, tetapi styling/ikon/grafik tidak dimuat.

## Instalasi XAMPP

1. Salin folder proyek ke `C:\xampp\htdocs\dashboard\ppdb`.
2. Pastikan Apache dan MySQL berjalan di XAMPP Control Panel. Konfigurasi Apache lokal mendengarkan pada port `80` dan `8080`, dengan document root `C:\xampp\htdocs`. Setelah perubahan konfigurasi Apache, restart Apache dari XAMPP Control Panel. Jika Apache belum berjalan, jalankan dari PowerShell:

   ```powershell
   & 'C:\xampp\apache\bin\httpd.exe' -d 'C:/xampp/apache'
   ```

   Jangan jalankan perintah tersebut jika Apache sudah aktif. Periksa konfigurasi sebelum restart dengan `& 'C:\xampp\apache\bin\httpd.exe' -t`.
3. Buka `http://127.0.0.1:8080/dashboard/ppdb/` (atau `http://localhost/dashboard/ppdb/` pada port default 80). `index.php` berada di `C:\xampp\htdocs\dashboard\ppdb`, sehingga tidak perlu virtual host maupun aturan rewrite tambahan.
4. Buka phpMyAdmin, lalu import `database/db_ppdb.sql`. Skrip membuat database `db_ppdb`, seluruh tabel, foreign key, index, role, serta konfigurasi awal jenjang SD/SMP/SMA/SMK. Pada proyek yang sudah memiliki database, jalankan `database/migrate_education_levels.sql` satu kali; migrasi tidak mengubah atau menghapus data pendaftaran lama.
5. Sesuaikan koneksi bila perlu. Nilai default lokal: host `127.0.0.1`, database `db_ppdb`, user `root`, tanpa kata sandi. Konfigurasi terpusat di `config/database.php`; untuk deployment gunakan environment variables:
   - `PPDB_DB_HOST`
   - `PPDB_DB_NAME`
   - `PPDB_DB_USER`
   - `PPDB_DB_PASSWORD`
   - `PPDB_BASE_URL` (default `/dashboard/ppdb`; ubah sesuai URL pemasangan)
6. Buka `http://localhost/dashboard/ppdb/setup-admin.php` dari komputer server untuk membuat admin pertama. Gunakan kata sandi minimal 12 karakter. Setelah admin berhasil dibuat, hapus atau ganti nama `setup-admin.php`.
7. Masuk sebagai admin, buka **Pengaturan & master data**, lalu aktifkan hanya jenjang yang tersedia dan isi sekolah asal, pilihan kelas, fasilitas, dan informasi biaya per jenjang. Tambahkan program/peminatan khusus SMA/SMK serta kompetensi keahlian yang sesuai, kemudian atur profil sekolah, tahun ajaran aktif, gelombang, jalur/biaya, petunjuk rekening resmi, dan pengumuman. Statistik siswa, pendidik, serta alumni untuk landing page bersifat opsional dan sebaiknya diisi hanya dengan angka aktual. Tanpa jenjang, tahun ajaran, gelombang, dan jalur aktif, pendaftaran tidak dapat dikirim.
8. Calon siswa mendaftar melalui halaman **Daftar akun**. Admin dapat membuat akun admin/panitia berikutnya dari halaman master data.

## Jalankan dengan PHP built-in server

Dari folder proyek:

```powershell
php -S 127.0.0.1:8080
```

Set `PPDB_BASE_URL` ke `/` untuk server yang menyajikan folder proyek langsung sebagai document root, lalu buka `http://127.0.0.1:8080`. Untuk URL proyek XAMPP `/dashboard/ppdb`, jangan ubah nilai `PPDB_BASE_URL` default.

## Alur aplikasi

1. Siswa membuat akun dan masuk.
2. Siswa mengisi biodata, orang tua, sekolah sebelumnya, pilihan tahun ajaran/gelombang/jalur/program, serta dokumen wajib.
3. Siswa memilih jenjang SD/SMP/SMA/SMK yang diaktifkan sekolah. Persyaratan sekolah asal dan dokumen menyesuaikan jenjang; pilihan program hanya muncul untuk SMA/SMK dan pilihan kompetensi kedua hanya tersedia untuk SMK. Pendaftaran mendapatkan nomor unik. Jika jalur berbiaya, siswa mengirim bukti pembayaran; jalur tanpa biaya menandai pembayaran sebagai tidak diperlukan.
4. Panitia memverifikasi biodata, setiap dokumen, dan bukti pembayaran.
5. Setelah biodata, seluruh dokumen wajib sesuai jenjang (empat untuk SD; enam untuk SMP/SMA/SMK), dan pembayaran (jika berlaku) terverifikasi, admin/panitia dapat menerbitkan hasil seleksi.
6. Siswa melihat status seleksi serta daftar ulang di dashboard. Admin mengelola proses daftar ulang melalui dashboard.

Unggahan dibatasi ke PDF/JPEG/PNG, maksimal 5 MB per file, disimpan dengan nama acak di `uploads/private`, dan tidak disajikan sebagai URL publik. Endpoint unduh memeriksa kepemilikan atau role admin/panitia. File `.htaccess` juga menolak akses langsung pada Apache.

## Catatan operasional & keamanan

- Akun dibuat dengan `password_hash()` dan diverifikasi dengan `password_verify()`. Query memakai PDO prepared statements; form POST dilindungi CSRF; output HTML di-escape.
- Gunakan HTTPS, kredensial database khusus dengan hak minimum, backup database/unggahan, dan ganti kata sandi awal. Atur `upload_max_filesize` serta `post_max_size` PHP minimal sesuai batas file, misalnya `8M`.
- Untuk Nginx atau web server tanpa `.htaccess`, konfigurasi web server agar menolak seluruh akses langsung ke `uploads/`. Jangan mengekspos folder tersebut melalui alias/static server.
- Bukti pendaftaran menyediakan tombol **Cetak / Simpan PDF** yang membuka dialog cetak browser. Dari dialog tersebut pilih **Save as PDF**. Tidak ada library PDF pihak ketiga yang dibundel.
- Ekspor berupa CSV berformat UTF-8 dan kompatibel dengan Excel; kolom yang berpotensi menjadi formula spreadsheet dinetralkan.
- Sistem tidak memproses uang secara otomatis. Panitia mengatur instruksi pembayaran resmi, mencocokkan pembayaran, lalu memverifikasi bukti.
- `activity_logs` mencatat kejadian penting, bukan semua perubahan database. Batasi akses server dan retensi data sesuai kebijakan sekolah/peraturan perlindungan data.

## Struktur utama

```text
admin/       Dashboard, verifikasi, master data, ekspor, daftar ulang
assets/      CSS dan JavaScript
auth/        Registrasi, login, logout
config/      Koneksi PDO
database/    Skema siap import
includes/    Bootstrap aplikasi, helper, layout bersama
student/     Formulir, dashboard, pembayaran, bukti, unduh berkas
uploads/     Penyimpanan privat untuk dokumen (dibuat saat upload)
index.php    Landing page dan informasi PPDB
setup-admin.php  Setup admin lokal satu kali; hapus setelah digunakan
```
