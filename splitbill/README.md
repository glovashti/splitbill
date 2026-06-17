## Langkah Instalasi & Menjalankan Aplikasi

Ikuti petunjuk di bawah ini untuk menjalankan aplikasi di komputer lokal (localhost):

1.  **Ekstrak Folder Proyek:**
    Pindahkan folder `splitbill` ke direktori web root server lokal Anda:
    *   XAMPP: `C:\xampp\htdocs\splitbill`
    *   Laragon: `C:\laragon\www\splitbill`

2.  **Impor Database ke phpMyAdmin:**
    *   Buka web browser dan akses `http://localhost/phpmyadmin/`.
    *   Buat database baru bernama `db_splitbill`.
    *   Pilih database `db_splitbill`, pergi ke tab **Import**, pilih file `database.sql` di root proyek ini, lalu klik **Go/Kirim**.
    *   *Catatan:* DDL database.sql akan otomatis membuat semua tabel, views, functions, dan triggers yang diperlukan.

3.  **Sesuaikan Konfigurasi Database:**
    *   Buka file `includes/config.php`.
    *   Pastikan parameter `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS` sesuai dengan server database XAMPP/Laragon Anda (Secara default: User = `root`, Pass = `""`).

4.  **Jalankan di Web Browser:**
    Akses URL berikut:
    ```text
    http://localhost/splitbill/
    ```

5.  **Gunakan Akun Demo / Daftar Baru:**
    *   Silakan klik "Daftar Gratis" di navbar untuk membuat akun pengguna pertama Anda.
    *   Setelah daftar, Anda akan diarahkan ke halaman login untuk masuk ke dashboard.
    *   username: admin
    *   email: admin@mail.com
    *   password: 12345678
