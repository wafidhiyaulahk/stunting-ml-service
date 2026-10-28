<?php
/**
 * Cetak laporan skrining untuk petugas Puskesmas.
 *
 * Halaman ini menyusun rekapitulasi hasil skrining pada rentang tanggal yang
 * dipilih, lalu menyiapkannya agar rapi ketika dicetak atau disimpan sebagai
 * PDF melalui jendela cetak peramban. Seluruh angka dihitung langsung dari
 * basis data, sehingga laporan selalu mengikuti data terbaru.
 *
 * Halaman bersifat baca saja, sama seperti menu Data Skrining: tidak ada
 * penambahan, perubahan, maupun penghapusan data dari sini.
 */
require_once __DIR__ . '/../helpers/layout.php';
require_once __DIR__ . '/../helpers/variabel.php';
wajibLoginAdmin();

/* ------------------------- Rentang tanggal ------------------------- */
// Bawaan: tanggal 1 bulan berjalan sampai hari ini.
$dariInput   = $_GET['dari']   ?? date('Y-m-01');
$sampaiInput = $_GET['sampai'] ?? date('Y-m-d');

/** Mengembalikan tanggal yang sah, atau tanggal cadangan bila isian tidak valid. */
function tanggalSah(string $nilai, string $cadangan): string
{
    $d = DateTime::createFromFormat('Y-m-d', $nilai);
    return ($d && $d->format('Y-m-d') === $nilai) ? $nilai : $cadangan;
}

$dari   = tanggalSah($dariInput, date('Y-m-01'));
$sampai = tanggalSah($sampaiInput, date('Y-m-d'));

// Bila pengguna membalik urutannya, tukar supaya kueri tetap masuk akal.
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

$kelurahanPilih = trim($_GET['kelurahan'] ?? '');

/* ------------------------- Pengambilan data ------------------------- */
// Batas atas memakai 23:59:59 agar skrining pada hari terakhir ikut terhitung.
$batasBawah = $dari . ' 00:00:00';
$batasAtas  = $sampai . ' 23:59:59';

$syarat = 's.tanggal_skrining BETWEEN ? AND ?';
$param  = [$batasBawah, $batasAtas];
if ($kelurahanPilih !== '') {
    $syarat .= ' AND b.kelurahan = ?';
    $param[] = $kelurahanPilih;
}

$stmt = $pdo->prepare(
    "SELECT s.id, s.tanggal_skrining, s.p4072_anak, s.p1509, s.hasil,
            s.probabilitas, s.tetangga_berisiko, s.mpasi_diversity,
            b.nama_balita, b.nama_ibu, b.jenis_kelamin, b.kelurahan, b.rw, b.rt
       FROM skrining s
       JOIN balita b ON b.id = s.balita_id
      WHERE $syarat
      ORDER BY s.tanggal_skrining ASC, b.nama_balita ASC"
);
$stmt->execute($param);
$baris = $stmt->fetchAll();

// Daftar kelurahan untuk penyaring; diambil dari data balita yang ada.
$daftarKelurahan = $pdo->query(
    'SELECT DISTINCT kelurahan FROM balita ORDER BY kelurahan'
)->fetchAll(PDO::FETCH_COLUMN);

/* ------------------------- Perhitungan rekap ------------------------- */
$total     = count($baris);
$berisiko  = 0;
$lakiLaki  = 0;
$perKel    = [];   // kelurahan => ['total' => n, 'berisiko' => n]
$perBulan  = [];   // 'YYYY-MM' => ['total' => n, 'berisiko' => n]
$balitaUnik = [];

foreach ($baris as $r) {
    $adaRisiko = (int)$r['hasil'] === 1;
    if ($adaRisiko)                      { $berisiko++; }
    if ((int)$r['jenis_kelamin'] === 0)  { $lakiLaki++; }

    $balitaUnik[$r['nama_balita'] . '|' . $r['kelurahan']] = true;

    $kel = $r['kelurahan'];
    if (!isset($perKel[$kel])) { $perKel[$kel] = ['total' => 0, 'berisiko' => 0]; }
    $perKel[$kel]['total']++;
    if ($adaRisiko) { $perKel[$kel]['berisiko']++; }

    $bln = substr($r['tanggal_skrining'], 0, 7);
    if (!isset($perBulan[$bln])) { $perBulan[$bln] = ['total' => 0, 'berisiko' => 0]; }
    $perBulan[$bln]['total']++;
    if ($adaRisiko) { $perBulan[$bln]['berisiko']++; }
}

$tidakBerisiko = $total - $berisiko;
$persenRisiko  = $total > 0 ? round($berisiko / $total * 100, 1) : 0;

// Kelurahan diurutkan dari jumlah berisiko terbanyak agar prioritas terbaca.
uasort($perKel, fn($a, $b) => [$b['berisiko'], $b['total']] <=> [$a['berisiko'], $a['total']]);
ksort($perBulan);

/** Menulis tanggal dalam bentuk yang lazim dipakai pada dokumen resmi. */
function tanggalPanjang(string $tgl): string
{
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = strtotime($tgl);
    return (int)date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

/** Menulis bulan dalam bentuk singkat, misalnya "Sep 2026". */
function bulanSingkat(string $ym): string
{
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
              'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    [$th, $bl] = explode('-', $ym);
    return $bulan[(int)$bl] . ' ' . $th;
}

/** Menulis usia balita dalam bentuk tahun dan bulan. */
function usiaTeks(int $bulan): string
{
    return $bulan < 12 ? $bulan . ' bln' : intdiv($bulan, 12) . ' th ' . ($bulan % 12) . ' bln';
}

mulaiHalaman('Cetak Laporan', 'admin', 'laporan');
?>
<div class="kepala-halaman tanpa-cetak">
    <div>
        <h1>Cetak Laporan</h1>
        <p>Pilih rentang tanggal, lalu tekan <b>Cetak laporan</b>. Pada jendela cetak, pilih <b>Save as PDF</b> bila ingin menyimpannya sebagai berkas.</p>
    </div>
</div>

<!-- ===================== Penyaring (tidak ikut tercetak) ===================== -->
<section class="panel tanpa-cetak">
    <form method="get" class="alat-tabel">
        <label class="bidang-cetak">
            <span>Dari tanggal</span>
            <input type="date" name="dari" value="<?= e($dari) ?>">
        </label>
        <label class="bidang-cetak">
            <span>Sampai tanggal</span>
            <input type="date" name="sampai" value="<?= e($sampai) ?>">
        </label>
        <label class="bidang-cetak">
            <span>Kelurahan</span>
            <select name="kelurahan">
                <option value="">Semua kelurahan</option>
                <?php foreach ($daftarKelurahan as $kel): ?>
                    <option value="<?= e($kel) ?>" <?= $kelurahanPilih === $kel ? 'selected' : '' ?>><?= e($kel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="tombol">Terapkan</button>
        <button type="button" class="tombol tombol-putih" onclick="window.print()">
            <?= ikon('cetak', 18) ?> Cetak laporan
        </button>
    </form>
</section>

<!-- ===================== Isi laporan ===================== -->
<section class="lembar-laporan" id="lembar">

    <!-- Kop laporan -->
    <header class="kop">
        <div class="kop-teks">
            <h2>Laporan Skrining Resiko Stunting Balita</h2>
            <p><?= e(NAMA_INSTANSI) ?></p>
            <p class="kop-kecil">Dihasilkan oleh <?= e(NAMA_APP) ?>, aplikasi penelitian berbasis algoritma K-Nearest Neighbor</p>
        </div>
    </header>

    <table class="meta-laporan">
        <tr>
            <th>Periode laporan</th>
            <td><?= tanggalPanjang($dari) ?> sampai <?= tanggalPanjang($sampai) ?></td>
            <th>Wilayah</th>
            <td><?= $kelurahanPilih !== '' ? 'Kelurahan ' . e($kelurahanPilih) : 'Seluruh wilayah kerja' ?></td>
        </tr>
        <tr>
            <th>Tanggal cetak</th>
            <td><?= tanggalPanjang(date('Y-m-d')) ?></td>
            <th>Dicetak oleh</th>
            <td><?= e($_SESSION['admin_nama'] ?? 'Administrator') ?></td>
        </tr>
    </table>

    <?php if ($total === 0): ?>
        <p class="kosong-laporan">Tidak ada data skrining pada rentang tanggal yang dipilih.</p>
    <?php else: ?>

    <!-- A. Ringkasan -->
    <h3 class="judul-bagian">A. Ringkasan</h3>
    <table class="tabel-cetak ringkas">
        <tr>
            <th>Jumlah skrining</th>
            <th>Berisiko stunting</th>
            <th>Tidak berisiko</th>
            <th>Proporsi berisiko</th>
            <th>Balita terdata</th>
        </tr>
        <tr class="angka-besar">
            <td><?= $total ?></td>
            <td><?= $berisiko ?></td>
            <td><?= $tidakBerisiko ?></td>
            <td><?= number_format($persenRisiko, 1, ',', '.') ?>%</td>
            <td><?= count($balitaUnik) ?></td>
        </tr>
    </table>
    <p class="catatan-bagian">
        Dari <?= $total ?> skrining pada periode ini, <?= $berisiko ?> di antaranya menunjukkan indikasi risiko stunting
        (<?= number_format($persenRisiko, 1, ',', '.') ?> persen). Jumlah balita laki-laki <?= $lakiLaki ?> anak
        dan perempuan <?= $total - $lakiLaki ?> anak.
    </p>

    <!-- B. Sebaran per kelurahan -->
    <h3 class="judul-bagian">B. Sebaran per Kelurahan</h3>
    <table class="tabel-cetak">
        <thead>
            <tr>
                <th style="width:34px">No</th>
                <th>Kelurahan</th>
                <th style="width:92px">Skrining</th>
                <th style="width:92px">Berisiko</th>
                <th style="width:110px">Proporsi</th>
            </tr>
        </thead>
        <tbody>
        <?php $n = 1; foreach ($perKel as $kel => $d): ?>
            <tr>
                <td class="tengah"><?= $n++ ?></td>
                <td><?= e($kel) ?></td>
                <td class="tengah"><?= $d['total'] ?></td>
                <td class="tengah"><?= $d['berisiko'] ?></td>
                <td class="tengah"><?= number_format($d['total'] ? $d['berisiko'] / $d['total'] * 100 : 0, 1, ',', '.') ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2"><b>Jumlah</b></td>
                <td class="tengah"><b><?= $total ?></b></td>
                <td class="tengah"><b><?= $berisiko ?></b></td>
                <td class="tengah"><b><?= number_format($persenRisiko, 1, ',', '.') ?>%</b></td>
            </tr>
        </tfoot>
    </table>

    <!-- C. Rekap per bulan -->
    <?php if (count($perBulan) > 1): ?>
    <h3 class="judul-bagian">C. Rekapitulasi per Bulan</h3>
    <table class="tabel-cetak">
        <thead>
            <tr>
                <th style="width:34px">No</th>
                <th>Bulan</th>
                <th style="width:92px">Skrining</th>
                <th style="width:92px">Berisiko</th>
                <th style="width:110px">Proporsi</th>
            </tr>
        </thead>
        <tbody>
        <?php $n = 1; foreach ($perBulan as $ym => $d): ?>
            <tr>
                <td class="tengah"><?= $n++ ?></td>
                <td><?= bulanSingkat($ym) ?></td>
                <td class="tengah"><?= $d['total'] ?></td>
                <td class="tengah"><?= $d['berisiko'] ?></td>
                <td class="tengah"><?= number_format($d['total'] ? $d['berisiko'] / $d['total'] * 100 : 0, 1, ',', '.') ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- D. Rincian -->
    <h3 class="judul-bagian"><?= count($perBulan) > 1 ? 'D' : 'C' ?>. Rincian Hasil Skrining</h3>
    <table class="tabel-cetak rincian">
        <thead>
            <tr>
                <th style="width:30px">No</th>
                <th style="width:84px">Tanggal</th>
                <th>Nama Balita</th>
                <th style="width:82px">Usia</th>
                <th style="width:34px">JK</th>
                <th style="width:60px">Berat</th>
                <th>Kelurahan</th>
                <th style="width:66px">Tetangga</th>
                <th style="width:100px">Hasil</th>
            </tr>
        </thead>
        <tbody>
        <?php $n = 1; foreach ($baris as $r): $risiko = (int)$r['hasil'] === 1; ?>
            <tr>
                <td class="tengah"><?= $n++ ?></td>
                <td><?= date('d/m/Y', strtotime($r['tanggal_skrining'])) ?></td>
                <td><?= e($r['nama_balita']) ?></td>
                <td><?= usiaTeks((int)$r['p4072_anak']) ?></td>
                <td class="tengah"><?= (int)$r['jenis_kelamin'] === 0 ? 'L' : 'P' ?></td>
                <td class="tengah"><?= number_format((float)$r['p1509'], 1, ',', '.') ?></td>
                <td><?= e($r['kelurahan']) ?></td>
                <td class="tengah"><?= (int)$r['tetangga_berisiko'] ?>/41</td>
                <td class="<?= $risiko ? 'sel-risiko' : 'sel-aman' ?>"><?= $risiko ? 'Berisiko' : 'Tidak berisiko' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="catatan-bagian">
        Kolom <b>Tetangga</b> menunjukkan berapa di antara 41 balita pembanding terdekat yang tergolong berisiko
        stunting. Nilai inilah yang menentukan keputusan algoritma melalui suara terbanyak.
    </p>

    <?php endif; ?>

    <!-- Catatan metode -->
    <div class="catatan-metode">
        <b>Catatan metode.</b> Hasil pada laporan ini merupakan keluaran algoritma K-Nearest Neighbor dengan
        k = 41, jarak Chebyshev, dan bobot seragam, yang dilatih memakai tujuh variabel: usia, berat badan,
        jenis kelamin, keragaman makanan MP-ASI, riwayat anemia, riwayat tuberkulosis, dan sumber air minum.
        Status gizi pada data pelatihan ditentukan dari indeks tinggi badan menurut umur dengan ambang
        Z-score kurang dari &minus;2 SD. Keluaran sistem bersifat deteksi dini, <b>bukan diagnosis medis</b>,
        sehingga penetapan status stunting tetap memerlukan pengukuran antropometri dan pemeriksaan oleh
        tenaga kesehatan.
    </div>

    <!-- Tanda tangan -->
    <div class="ttd">
        <div class="ttd-kolom">
            <p>Mengetahui,</p>
            <p>Kepala <?= e(NAMA_INSTANSI) ?></p>
            <div class="ttd-ruang"></div>
            <p class="ttd-nama">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</p>
            <p class="ttd-jab">NIP.</p>
        </div>
        <div class="ttd-kolom">
            <p>Pamulang, <?= tanggalPanjang(date('Y-m-d')) ?></p>
            <p>Petugas yang melaporkan</p>
            <div class="ttd-ruang"></div>
            <p class="ttd-nama"><?= e($_SESSION['admin_nama'] ?? 'Administrator') ?></p>
            <p class="ttd-jab">NIP.</p>
        </div>
    </div>
</section>
<?php akhiriHalaman(); ?>