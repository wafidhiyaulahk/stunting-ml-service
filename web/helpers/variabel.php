<?php
/**
 * Membaca feature_config.json milik ML service.
 *
 * Berkas JSON itu sengaja dijadikan satu-satunya sumber kebenaran: label
 * pertanyaan, pilihan jawaban, dan rentang nilai hanya ditulis di sana.
 * Bila suatu saat Anda mengetahui arti sebenarnya dari kode P501/P810/P916C
 * dari kamus data penelitian, cukup ubah berkas JSON tersebut dan form
 * skrining di aplikasi web ikut berubah tanpa mengedit kode PHP.
 */

function konfigurasiVariabel(): array
{
    static $konfig = null;
    if ($konfig !== null) {
        return $konfig;
    }

    $path = __DIR__ . '/../../ml-service/feature_config.json';
    if (!is_readable($path)) {
        die('Berkas feature_config.json tidak ditemukan di folder ml-service.');
    }
    $konfig = json_decode(file_get_contents($path), true);
    if (!is_array($konfig)) {
        die('Isi feature_config.json tidak valid.');
    }
    return $konfig;
}

function fiturVariabel(string $kode): array
{
    return konfigurasiVariabel()['fitur'][$kode] ?? [];
}

function kelompokMakanan(): array
{
    return fiturVariabel('MPASI_DIVERSITY')['kelompok_makanan'] ?? [];
}

/** Ubah nilai kode menjadi teks yang terbaca manusia, untuk ditampilkan di pop-up. */
function teksOpsi(string $kode, $nilai): string
{
    $f = fiturVariabel($kode);
    foreach ($f['opsi'] ?? [] as $opsi) {
        if ((string)$opsi['nilai'] === (string)$nilai) {
            return $opsi['teks'];
        }
    }
    return (string)$nilai;
}
