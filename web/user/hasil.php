<?php
require_once __DIR__ . '/../helpers/layout.php';
require_once __DIR__ . '/../helpers/variabel.php';
require_once __DIR__ . '/../helpers/saran.php';
wajibLoginPengguna();

$idSkrining = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, b.nama_balita, b.nama_ibu, b.nama_ayah, b.no_wa, b.tanggal_lahir,
            b.jenis_kelamin, b.kelurahan, b.rw, b.rt
     FROM skrining s JOIN balita b ON b.id = s.balita_id
     WHERE s.id = ? AND s.pengguna_id = ?'
);
$stmt->execute([$idSkrining, $_SESSION['pengguna_id']]);
$data = $stmt->fetch();

if (!$data) {
    setPesan('gagal', 'Hasil skrining tidak ditemukan.');
    header('Location: riwayat.php');
    exit;
}

$berisiko = (int)$data['hasil'] === 1;
$persen   = round($data['probabilitas'] * 100);

$blokSaran = susunSaran([
    'nama_balita'   => $data['nama_balita'],
    'usia_bulan'    => (int)$data['p4072_anak'],
    'berat'         => (float)$data['p1509'],
    'jenis_kelamin' => (int)$data['jenis_kelamin'],
    'mpasi'         => (int)$data['mpasi_diversity'],
    'anemia'        => (int)$data['p916c'],
    'tbc'           => (int)$data['p810'],
    'hasil'         => (int)$data['hasil'],
]);

// Posyandu terdekat: prioritas pada kelurahan yang sama, lalu RW yang sama.
$stmt = $pdo->prepare(
    'SELECT * FROM posyandu
     ORDER BY (kelurahan = ?) DESC, (rw = ?) DESC, nama_posyandu
     LIMIT 4'
);
$stmt->execute([$data['kelurahan'], $data['rw']]);
$daftarPosyandu = $stmt->fetchAll();

mulaiHalaman('Hasil Skrining', 'pengguna', 'skrining');
?>
<div class="wadah">

    <div class="langkah">
        <div>1. Data anak</div>
        <div>2. Kondisi anak dan keluarga</div>
        <div class="aktif">3. Hasil dan saran</div>
    </div>

    <div class="panel-hasil <?= $berisiko ? 'risiko' : 'aman' ?>">
        <p>Hasil skrining untuk</p>
        <h1><?= e($data['nama_balita']) ?></h1>
        <p style="font-size:1.25rem;font-weight:700;margin-top:.6rem">
            <?= $berisiko ? 'Berisiko Stunting' : 'Tidak Berisiko Stunting' ?>
        </p>
        <div class="meter"><span style="width:<?= $berisiko ? $persen : 100 - $persen ?>%"></span></div>
        <?php $totalTetangga = 41; $jmlBerisiko = (int)$data['tetangga_berisiko']; $jmlAman = $totalTetangga - $jmlBerisiko; ?>
        <p style="font-size:.9rem">
            Sistem membandingkan <?= e($data['nama_balita']) ?> dengan <?= $totalTetangga ?> anak lain
            yang kondisinya paling mirip. Dari <?= $totalTetangga ?> anak pembanding itu,
            <strong><?= $jmlBerisiko ?> anak tergolong berisiko stunting</strong>
            dan <?= $jmlAman ?> anak tidak berisiko —
            sehingga <?= e($data['nama_balita']) ?> dinilai
            <strong><?= $berisiko ? 'berisiko' : 'tidak berisiko' ?></strong>
            karena mengikuti kelompok yang lebih banyak.
        </p>
    </div>

    <div class="kartu">
        <h2>Ringkasan Data yang Diperiksa</h2>
        <div class="grid-2">
            <div>
                <p style="margin:.2rem 0"><strong>Usia anak:</strong> <?= (int)$data['p4072_anak'] ?> bulan</p>
                <p style="margin:.2rem 0"><strong>Berat badan:</strong> <?= e($data['p1509']) ?> kg</p>
                <p style="margin:.2rem 0"><strong>Jenis kelamin:</strong> <?= teksOpsi('P404_anak', $data['p404_anak']) ?></p>
                <p style="margin:.2rem 0"><strong>Pola makan (MP-ASI):</strong> <?= (int)$data['mpasi_diversity'] ?> dari <?= count(kelompokMakanan()) ?> kelompok</p>
            </div>
            <div>
                <p style="margin:.2rem 0"><strong><?= e(fiturVariabel('P501')['label']) ?>:</strong> <?= teksOpsi('P501', $data['p501']) ?></p>
                <p style="margin:.2rem 0"><strong><?= e(fiturVariabel('P810')['label']) ?>:</strong> <?= teksOpsi('P810', $data['p810']) ?></p>
                <p style="margin:.2rem 0"><strong><?= e(fiturVariabel('P916C')['label']) ?>:</strong> <?= teksOpsi('P916C', $data['p916c']) ?></p>
                <p style="margin:.2rem 0"><strong>Tanggal skrining:</strong> <?= formatTanggal($data['tanggal_skrining']) ?></p>
            </div>
        </div>
    </div>

    <div class="kartu">
        <h2>Saran dari Sisi Gizi</h2>
        <?php foreach ($blokSaran as $b): ?>
            <div class="saran-blok">
                <h3><?= e($b['judul']) ?></h3>
                <p><?= e($b['isi']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="kartu">
        <h2>Posyandu Terdekat di Wilayah Anda</h2>
        <p class="bantuan">Silakan datang ke Posyandu terdekat berikut untuk pemeriksaan lanjutan
           dan pengukuran tinggi badan anak.</p>
        <?php foreach ($daftarPosyandu as $p): ?>
            <div class="posyandu-item">
                <strong><?= e($p['nama_posyandu']) ?></strong>
                <small><?= e($p['alamat']) ?> &mdash; Kel. <?= e($p['kelurahan']) ?>
                       <?= $p['rw'] ? 'RW ' . e($p['rw']) : '' ?></small>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="text-align:center;display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap">
        <a href="skrining.php" class="tombol tombol-putih">Skrining Anak Lain</a>
        <a href="riwayat.php" class="tombol">Lihat Riwayat Skrining</a>
    </div>

</div>
<?php akhiriHalaman(); ?>