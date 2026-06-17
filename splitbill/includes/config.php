<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Konfigurasi Database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'db_splitbill');
define('DB_USER', 'root');
define('DB_PASS', '');

// Membangun koneksi database menggunakan PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

/**
 * Mengamankan output untuk mencegah XSS (Cross-Site Scripting)
 * Sesuai dengan spesifikasi teknis poin 2 (Keamanan Input).
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka menjadi tampilan mata uang Rupiah
 */
function format_rupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

/**
 * Fungsi pembantu untuk memvalidasi input form (helpers)
 */
function validate_input($data) {
    return trim(stripslashes($data));
}
