<?php
/**
 * Memproses formulir skrining:
 *   1. Validasi masukan
 *   2. Simpan / ambil data balita
 *   3. Susun ketujuh variabel dan kirim ke ML service (model KNN)
 *   4. Simpan hasil beserta saran, lalu arahkan ke halaman hasil
 */
require_once __DIR__ . '/../helpers/fungsi.php';
require_once __DIR__ . '/../helpers/variabel.php';
require_once __DIR__ . '/../helpers/saran.php';
wajibLoginPengguna();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: skrining.php');
    exit;
}
periksaCsrf();

$idPengguna = $_SESSION['pengguna_id'];

function kembaliDenganGalat(string $pesan): void
{
    setPesan('gagal', $pesan);
    header('Location: skrining.php');
    exit;
}

/* ---------- 1. Data balita ---------- */
$pilihanBalita = $_POST['balita_id'] ?? 'baru';

if ($pilihanBalita !== 'baru') {
    // Pastikan balita memang milik akun yang sedang masuk.
    $stmt = $pdo->prepare('SELECT * FROM balita WHERE id = ? AND pengguna_id = ?');
    $stmt->execute([(int)$pilihanBalita, $idPengguna]);
    $balita = $stmt->fetch();
    if (!$balita) {
        kembaliDenganGalat('Data anak tidak ditemukan pada akun Anda.');
    }
    $idBalita = (int)$balita['id'];
} else {
    $namaBalita = trim($_POST['nama_balita'] ?? '');
    $tglLahir   = $_POST['tanggal_lahir'] ?? '';
    $namaIbu    = trim($_POST['nama_ibu'] ?? '');
    $namaAyah   = trim($_POST['nama_ayah'] ?? '');
    $noWa       = normalisasiNoHp($_POST['no_wa'] ?? '');
    $kelurahan  = $_POST['alamat_kelurahan'] ?? '';
    $rw         = $_POST['alamat_rw'] ?? '';
    $rt         = $_POST['alamat_rt'] ?? '';

    if ($namaBalita === '' || $tglLahir === '' || $namaIbu === '' || $namaAyah === '') {
        kembaliDenganGalat('Lengkapi seluruh data anak terlebih dahulu.');
    }
    if ($kelurahan === '' || $rw === '' || $rt === '') {
        kembaliDenganGalat('Alamat anak (kelurahan, RW, RT) wajib dipilih.');
    }
    if (strtotime($tglLahir) > time()) {
        kembaliDenganGalat('Tanggal lahir tidak boleh melebihi hari ini.');
    }
}

$jenisKelamin = isset($_POST['p404_anak']) ? (int)$_POST['p404_anak'] : null;
if ($jenisKelamin === null) {
    kembaliDenganGalat('Jenis kelamin anak wajib dipilih.');
}

if ($pilihanBalita === 'baru') {
    $stmt = $pdo->prepare(
        'INSERT INTO balita (pengguna_id, nama_balita, nama_ibu, nama_ayah, no_wa,
                             tanggal_lahir, jenis_kelamin, kelurahan, rw, rt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$idPengguna, $namaBalita, $namaIbu, $namaAyah, $noWa,
                    $tglLahir, $jenisKelamin, $kelurahan, $rw, $rt]);
    $idBalita = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM balita WHERE id = ?');
    $stmt->execute([$idBalita]);
    $balita = $stmt->fetch();
}

/* ---------- 2. Susun ketujuh variabel model ---------- */
$usiaBulan = usiaDalamBulan($balita['tanggal_lahir']);
if ($usiaBulan > 59) {
    kembaliDenganGalat('Aplikasi ini hanya untuk balita usia 0 sampai 59 bulan. '
        . 'Usia anak saat ini ' . $usiaBulan . ' bulan.');
}

$berat = (float)($_POST['berat_badan'] ?? 0);
if ($berat <= 0) {
    kembaliDenganGalat('Berat badan anak wajib diisi.');
}

$tercentang = array_map('intval', $_POST['makanan'] ?? []);
$skorMpasi  = count(array_unique($tercentang));

$fitur = [
    'P4072_anak'      => $usiaBulan,                       // usia dalam bulan
    'P404_anak'       => $jenisKelamin,                    // variabel biner
    'P1509'           => $berat,                           // berat badan (kg)
    'P810'            => (int)($_POST['p810'] ?? 3),       // nominal 1/2/3
    'P916C'           => (int)($_POST['p916c'] ?? 0),      // variabel biner
    'P501'            => (int)($_POST['p501'] ?? 1),       // nominal 1/2/4/5
    'MPASI_DIVERSITY' => $skorMpasi,                       // 0 sampai 6
];

/* ---------- 3. Panggil model KNN ---------- */
$prediksi = prediksiStunting($fitur);
if (empty($prediksi['sukses'])) {
    kembaliDenganGalat($prediksi['pesan'] ?? 'Proses skrining gagal.');
}

/* ---------- 4. Susun saran dan simpan ---------- */
$blokSaran = susunSaran([
    'nama_balita'   => $balita['nama_balita'],
    'usia_bulan'    => $usiaBulan,
    'berat'         => $berat,
    'jenis_kelamin' => $jenisKelamin,
    'mpasi'         => $skorMpasi,
    'anemia'        => $fitur['P916C'],
    'tbc'           => $fitur['P810'],
    'hasil'         => (int)$prediksi['hasil'],
]);

$stmt = $pdo->prepare(
    'INSERT INTO skrining
        (balita_id, pengguna_id, p4072_anak, p1509, mpasi_diversity, p916c,
         p404_anak, p501, p810, detail_makanan, hasil, probabilitas,
         tetangga_berisiko, saran)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $idBalita, $idPengguna,
    $fitur['P4072_anak'], $fitur['P1509'], $fitur['MPASI_DIVERSITY'],
    $fitur['P916C'], $fitur['P404_anak'], $fitur['P501'], $fitur['P810'],
    json_encode($tercentang),
    (int)$prediksi['hasil'],
    $prediksi['probabilitas']['berisiko'],
    $prediksi['tetangga_berisiko'] ?? null,
    saranKeTeks($blokSaran),
]);

header('Location: hasil.php?id=' . (int)$pdo->lastInsertId());
exit;
