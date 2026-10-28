<?php
/**
 * Koneksi basis data memakai PDO.
 *
 * PDO dipilih dibanding mysqli karena prepared statement-nya lebih ringkas
 * sehingga seluruh query di aplikasi ini aman dari SQL injection.
 */
require_once __DIR__ . '/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#b91c1c">'
        . '<h2>Koneksi basis data gagal</h2>'
        . '<p>' . htmlspecialchars($e->getMessage()) . '</p>'
        . '<p>Pastikan MySQL di XAMPP sudah dinyalakan dan basis data '
        . '<b>' . DB_NAME . '</b> sudah diimpor.</p></div>');
}
