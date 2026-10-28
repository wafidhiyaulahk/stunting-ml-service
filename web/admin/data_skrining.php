<?php
require_once __DIR__ . '/../helpers/layout.php';
require_once __DIR__ . '/../helpers/variabel.php';
wajibLoginAdmin();

/*
 * Halaman ini sengaja hanya membaca data (tanpa tambah, ubah, maupun hapus).
 * Setiap baris skrining adalah keluaran model KNN atas data yang diisi
 * orang tua, sehingga isinya tidak boleh disunting petugas. Menjaga catatan
 * tetap apa adanya membuat rekapitulasi pada dashboard dapat
 * dipertanggungjawabkan sebagai hasil perhitungan algoritma, bukan hasil
 * penyuntingan manual.
 */

/* ----------------------------- Penyaring ----------------------------- */
$cari   = trim($_GET['cari'] ?? '');
$filter = $_GET['hasil'] ?? '';

$sql = 'SELECT s.*, b.nama_balita, b.nama_ibu, b.nama_ayah, b.no_wa, b.tanggal_lahir,
               b.jenis_kelamin, b.kelurahan, b.rw, b.rt, p.nama AS nama_pengguna, p.no_hp
        FROM skrining s
        JOIN balita b   ON b.id = s.balita_id
        JOIN pengguna p ON p.id = s.pengguna_id
        WHERE 1 = 1';
$param = [];
if ($cari !== '') {
    $sql .= ' AND b.nama_balita LIKE ?';
    $param[] = '%' . $cari . '%';
}
if ($filter !== '') {
    $sql .= ' AND s.hasil = ?';
    $param[] = (int)$filter;
}
$sql .= ' ORDER BY s.tanggal_skrining DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$daftar = $stmt->fetchAll();

/** Siapkan isi pop-up data balita. */
function detailBalita(array $r): array
{
    return [
        'Nama Balita'   => e($r['nama_balita']),
        'Tanggal Lahir' => formatTanggal($r['tanggal_lahir']),
        'Usia Saat Skrining' => (int)$r['p4072_anak'] . ' bulan',
        'Jenis Kelamin' => e(teksOpsi('P404_anak', $r['p404_anak'])),
        'Nama Ibu'      => e($r['nama_ibu']),
        'Nama Ayah'     => e($r['nama_ayah']),
        'Nomor WhatsApp'=> e($r['no_wa']),
        'Alamat'        => e($r['kelurahan']) . ' RW ' . e($r['rw']) . ' / RT ' . e($r['rt']),
        'Akun Pendaftar'=> e($r['nama_pengguna']) . ' (' . e($r['no_hp']) . ')',
    ];
}

/** Siapkan isi pop-up hasil skrining. */
function detailHasil(array $r): array
{
    $berisiko = (int)$r['hasil'] === 1;
    $lencana  = '<span class="lencana ' . ($berisiko ? 'lencana-risiko' : 'lencana-aman') . '">'
              . ($berisiko ? 'Berisiko Stunting' : 'Tidak Berisiko Stunting') . '</span>';

    return [
        'Hasil Deteksi'   => $lencana,
        'Peluang Berisiko'=> round($r['probabilitas'] * 100, 1) . '%',
        'Tetangga Terdekat' => (int)$r['tetangga_berisiko'] . ' dari 41 tetangga tergolong berisiko',
        'Tanggal Skrining'=> formatTanggal($r['tanggal_skrining']),
        'Usia Anak'       => (int)$r['p4072_anak'] . ' bulan',
        'Berat Badan'     => e($r['p1509']) . ' kg',
        'Pola Makan (MP-ASI)' => (int)$r['mpasi_diversity'] . ' dari ' . count(kelompokMakanan()) . ' kelompok makanan',
        e(fiturVariabel('P501')['label'])  => e(teksOpsi('P501', $r['p501'])),
        e(fiturVariabel('P810')['label'])  => e(teksOpsi('P810', $r['p810'])),
        e(fiturVariabel('P916C')['label']) => e(teksOpsi('P916C', $r['p916c'])),
        'Saran yang Diberikan' => nl2br(e($r['saran'] ?? '-')),
    ];
}

$jumlahBerisiko = count(array_filter($daftar, fn($r) => (int)$r['hasil'] === 1));

mulaiHalaman('Data Skrining', 'admin', 'skrining');
?>
<div class="kepala-halaman">
    <div>
        <h1>Data Skrining</h1>
        <p>Halaman ini bersifat baca saja. Klik <b>nama balita</b> untuk melihat data lengkap anak, atau klik <b>hasil</b> untuk melihat rincian skrining dan saran.</p>
    </div>
</div>

<?= tampilkanPesan() ?>

<section class="panel">
    <form method="get" class="alat-tabel">
        <div class="cari">
            <?= ikon('cari', 18) ?>
            <input type="text" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama balita…" aria-label="Cari nama balita">
        </div>
        <select name="hasil" aria-label="Saring hasil">
            <option value="">Semua hasil</option>
            <option value="1" <?= $filter === '1' ? 'selected' : '' ?>>Berisiko stunting</option>
            <option value="0" <?= $filter === '0' ? 'selected' : '' ?>>Tidak berisiko</option>
        </select>
        <button class="tombol">Terapkan</button>
        <?php if ($cari !== '' || $filter !== ''): ?>
            <a class="tautan-kecil" href="data_skrining.php">Hapus penyaring</a>
        <?php endif; ?>
    </form>

    <div style="padding:.7rem 1.25rem;font-size:.82rem;color:var(--abu-500);border-bottom:1px solid var(--abu-100)">
        Menampilkan <b style="color:var(--abu-700)"><?= count($daftar) ?></b> data
        &middot; <?= $jumlahBerisiko ?> berisiko
    </div>

    <table class="tabel-admin">
        <thead>
            <tr><th>Nama Balita</th><th>Wilayah</th><th>Tanggal</th><th>Hasil Deteksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($daftar as $r): ?>
            <tr>
                <td class="sel-kepala">
                    <button class="tautan-popup"
                            data-detail='<?= e(json_encode(detailBalita($r))) ?>'
                            onclick="bukaDetail('modal-detail', this, 'Data Lengkap Balita')">
                        <?= e($r['nama_balita']) ?>
                    </button>
                    <span class="sel-sub"><?= (int)$r['p4072_anak'] ?> bulan &middot; Ibu <?= e($r['nama_ibu']) ?></span>
                </td>
                <td data-label="Wilayah">Kel. <?= e($r['kelurahan']) ?></td>
                <td data-label="Tanggal"><?= formatTanggal($r['tanggal_skrining']) ?></td>
                <td data-label="Hasil">
                    <button class="tautan-popup" style="text-decoration:none"
                            data-detail='<?= e(json_encode(detailHasil($r))) ?>'
                            onclick="bukaDetail('modal-detail', this, 'Rincian Hasil Skrining')"
                            title="Lihat rincian hasil">
                        <span class="lencana <?= $r['hasil'] ? 'lencana-risiko' : 'lencana-aman' ?>">
                            <?= $r['hasil'] ? 'Berisiko' : 'Tidak berisiko' ?>
                        </span>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$daftar): ?>
            <tr><td colspan="4" class="kosong"><?= ikon('kosong', 36) ?><br>Tidak ada data skrining yang cocok.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<div class="alert alert-info" style="margin-top:1.25rem">
    <div>
        <b>Data hanya dapat dilihat.</b>
        Seluruh baris pada halaman ini adalah keluaran perhitungan model K-Nearest Neighbor
        atas data yang diisi orang tua melalui menu skrining, sehingga tidak disediakan
        fungsi tambah, ubah, maupun hapus. Catatan yang dibiarkan apa adanya menjaga
        rekapitulasi pada dashboard tetap mencerminkan hasil algoritma, bukan hasil
        penyuntingan petugas.
    </div>
</div>

<!-- Pop-up serbaguna: isinya diisi oleh fungsi bukaDetail() di app.js -->
<div class="modal-latar" id="modal-detail">
    <div class="modal">
        <div class="modal-kepala">
            <h3>Detail</h3>
            <button class="modal-tutup" onclick="tutupModal('modal-detail')" aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-isi"><dl></dl></div>
    </div>
</div>
<?php akhiriHalaman(); ?>