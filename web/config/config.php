<?php
/**
 * Konfigurasi global aplikasi.
 */

// ---- Basis data (sesuaikan bila XAMPP Anda memakai password root) ----
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'skripsi_stunting');

// ---- ML Service (Flask) ----
define('ML_SERVICE_URL', 'http://127.0.0.1:5000');
define('ML_API_TOKEN', 'skripsi-stunting-2026'); // harus sama dengan di app.py

// ---- Identitas aplikasi ----
define('NAMA_APP', 'Skrining Deteksi Stunting');
define('NAMA_INSTANSI', 'Wilayah Pamulang');

// ---- Alamat dasar aplikasi ----
// Dideteksi otomatis dari letak folder "web" di dalam htdocs, sehingga aplikasi
// tetap berjalan meskipun foldernya diberi nama lain (misalnya /Deteksi/web).
// Bila deteksi gagal di komputer tertentu, isi manual di sini, contoh:
//     define('BASE_URL_MANUAL', '/Deteksi/web');
define('BASE_URL_MANUAL', '');

function deteksiBaseUrl(): string
{
    if (BASE_URL_MANUAL !== '') {
        return rtrim(BASE_URL_MANUAL, '/');
    }
    $akar = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $web  = realpath(__DIR__ . '/..');
    if ($akar === '' || $web === false || realpath($akar) === false) {
        return '/stunting-app/web';
    }
    // Samakan garis miring dan abaikan huruf besar-kecil (Windows: C:\ vs c:/)
    $akar = rtrim(str_replace('\\', '/', realpath($akar)), '/');
    $web  = str_replace('\\', '/', $web);
    if (stripos($web, $akar) !== 0) {
        return '/stunting-app/web';
    }
    return rtrim(substr($web, strlen($akar)), '/');
}
define('BASE_URL', deteksiBaseUrl());

/**
 * Alamat berkas aset (CSS/JS) lengkap dengan penanda versi.
 *
 * Browser menyimpan CSS di cache, sehingga perubahan pada style.css sering
 * tidak terlihat walaupun filenya sudah diubah. Menempelkan waktu ubah file
 * (filemtime) sebagai parameter ?v= membuat alamatnya berubah setiap kali
 * file disunting, jadi browser terpaksa mengunduh versi terbaru.
 */
function aset(string $relatif): string
{
    $relatif = ltrim($relatif, '/');
    $berkas  = __DIR__ . '/../' . $relatif;
    $versi   = is_file($berkas) ? filemtime($berkas) : time();
    return BASE_URL . '/' . $relatif . '?v=' . $versi;
}

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}