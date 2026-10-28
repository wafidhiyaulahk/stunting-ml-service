<?php
/**
 * Beranda pengguna (orang tua / kader) setelah login.
 * Tampilannya disamakan dengan halaman utama publik agar terasa satu situs.
 */
require_once __DIR__ . '/../helpers/layout.php';
wajibLoginPengguna();

$idPengguna = $_SESSION['pengguna_id'];

// Setiap anak beserta hasil skrining terakhirnya
$stmt = $pdo->prepare(
    'SELECT b.id, b.nama_balita, b.tanggal_lahir, b.jenis_kelamin,
            s.id AS id_skrining, s.hasil, s.tanggal_skrining, s.p1509, s.tetangga_berisiko,
            (SELECT COUNT(*) FROM skrining x WHERE x.balita_id = b.id) AS jumlah_skrining
     FROM balita b
     LEFT JOIN skrining s ON s.id = (
         SELECT id FROM skrining WHERE balita_id = b.id ORDER BY tanggal_skrining DESC, id DESC LIMIT 1
     )
     WHERE b.pengguna_id = ?
     ORDER BY COALESCE(s.tanggal_skrining, b.created_at) DESC'
);
$stmt->execute([$idPengguna]);
$daftarAnak = $stmt->fetchAll();

$jumlahAnak    = count($daftarAnak);
$anakBerisiko  = count(array_filter($daftarAnak, fn($a) => $a['hasil'] !== null && (int)$a['hasil'] === 1));
$anakAman      = count(array_filter($daftarAnak, fn($a) => $a['hasil'] !== null && (int)$a['hasil'] === 0));
$totalSkrining = array_sum(array_map(fn($a) => (int)$a['jumlah_skrining'], $daftarAnak));

// Anak yang sudah lebih dari 30 hari tidak diskrining: diingatkan untuk skrining ulang
$perluUlang = array_filter($daftarAnak, fn($a) =>
    $a['tanggal_skrining'] !== null
    && strtotime($a['tanggal_skrining']) < strtotime('-30 days')
    && usiaDalamBulan($a['tanggal_lahir']) <= 59);

$jam = (int)date('G');
$salam = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));
$namaDepan = e($_SESSION['pengguna_nama']);

$layananHidup = statusMlService();

mulaiHalaman('Beranda', 'pengguna', 'beranda');
?>

<!-- ============================ SAMBUTAN ============================ -->
<section class="sambut">
    <div class="sambut-teks">
        <span class="pil-p"><span class="titik"></span> <?= $salam ?></span>
        <h1>Halo, <span class="sorot-p"><?= $namaDepan ?></span></h1>
        <p>Pantau risiko stunting anak Anda secara berkala. Siapkan berat badan dari penimbangan terakhir di
           Posyandu, lalu ikuti tiga langkah singkat untuk melihat hasil dan saran gizinya.</p>
        <div class="sambut-aksi">
            <a class="tombol tombol-besar" href="skrining.php"><?= ikon('tambah', 20) ?> Mulai Skrining</a>
            <a class="tombol tombol-besar tombol-putih" href="riwayat.php"><?= ikon('riwayat', 18) ?> Lihat Riwayat</a>
        </div>
    </div>

    <aside class="sambut-kartu" aria-label="Ringkasan akun">
        <p class="judul-kecil">Ringkasan anak Anda</p>
        <div class="ringkas-baris">
            <span class="ikon biru"><?= ikon('balita', 20) ?></span>
            <span class="teks">Anak terdaftar</span>
            <b><?= $jumlahAnak ?></b>
        </div>
        <div class="ringkas-baris">
            <span class="ikon"><?= ikon('skrining', 20) ?></span>
            <span class="teks">Skrining dilakukan</span>
            <b><?= $totalSkrining ?></b>
        </div>
        <div class="ringkas-baris">
            <span class="ikon hijau"><?= ikon('perisai', 20) ?></span>
            <span class="teks">Hasil terakhir tidak berisiko</span>
            <b><?= $anakAman ?></b>
        </div>
        <div class="ringkas-baris">
            <span class="ikon merah"><?= ikon('peringatan', 20) ?></span>
            <span class="teks">Perlu perhatian</span>
            <b class="<?= $anakBerisiko ? 'merah' : '' ?>"><?= $anakBerisiko ?></b>
        </div>
    </aside>
</section>

<?php if (!$layananHidup): ?>
    <div class="alert alert-info">
        <?= ikon('info', 18) ?>
        <span>Layanan perhitungan sedang tidak aktif, jadi skrining baru belum bisa diproses. Riwayat skrining tetap bisa Anda lihat.</span>
    </div>
<?php endif; ?>

<?= tampilkanPesan() ?>

<?php if ($perluUlang): ?>
    <div class="pengingat">
        <span class="ikon"><?= ikon('tanggal', 20) ?></span>
        <div>
            <b>Waktunya skrining ulang</b>
            <span><?= e(implode(', ', array_map(fn($a) => $a['nama_balita'], $perluUlang))) ?>
                sudah lebih dari sebulan belum diperiksa. Lakukan skrining ulang setelah penimbangan di Posyandu.</span>
        </div>
    </div>
<?php endif; ?>

<!-- ============================ ANAK SAYA ============================ -->
<section class="bagian-p">
    <div class="kepala-bagian">
        <div>
            <span class="label-p">Anak saya</span>
            <h2>Hasil pemeriksaan terakhir</h2>
        </div>
        <?php if ($daftarAnak): ?>
            <a class="tombol tombol-kecil tombol-putih" href="skrining.php"><?= ikon('tambah', 16) ?> Tambah anak</a>
        <?php endif; ?>
    </div>

    <?php if (!$daftarAnak): ?>
        <div class="kartu-p kosong-p">
            <?= ikon('balita', 46) ?>
            <b>Belum ada anak yang terdaftar</b>
            Data anak akan tersimpan otomatis setelah skrining pertama.<br><br>
            <a class="tombol" href="skrining.php"><?= ikon('tambah', 18) ?> Skrining Anak Pertama</a>
        </div>
    <?php else: ?>
        <div class="kisi-anak">
            <?php foreach ($daftarAnak as $a):
                $usia     = usiaDalamBulan($a['tanggal_lahir']);
                $usiaTeks = $usia < 12 ? "$usia bulan" : intdiv($usia, 12) . ' tahun ' . ($usia % 12) . ' bulan';
                $melewati = $usia > 59;
                $status   = $a['hasil'] === null ? 'belum' : ((int)$a['hasil'] === 1 ? 'risiko' : 'aman');
            ?>
                <article class="kartu-anak <?= $status ?>">
                    <div class="atas">
                        <span class="foto <?= (int)$a['jenis_kelamin'] === 1 ? 'pr' : 'lk' ?>" aria-hidden="true">
                            <?= e(strtoupper(mb_substr($a['nama_balita'], 0, 1))) ?>
                        </span>
                        <div class="identitas">
                            <h3><?= e($a['nama_balita']) ?></h3>
                            <span><?= $usiaTeks ?> &middot; <?= (int)$a['jenis_kelamin'] === 1 ? 'Perempuan' : 'Laki-laki' ?></span>
                        </div>
                    </div>

                    <?php if ($status === 'belum'): ?>
                        <div class="status-anak belum">Belum pernah diskrining</div>
                    <?php else: ?>
                        <div class="status-anak <?= $status ?>">
                            <?= ikon($status === 'risiko' ? 'peringatan' : 'perisai', 18) ?>
                            <div>
                                <b><?= $status === 'risiko' ? 'Berisiko stunting' : 'Tidak berisiko' ?></b>
                                <span><?= (int)$a['tetangga_berisiko'] ?>/41 anak termirip berisiko</span>
                            </div>
                        </div>
                        <dl class="info-anak">
                            <?php $bln = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']; $t = strtotime($a['tanggal_skrining']); ?>
                            <div><dt>Diperiksa</dt><dd><?= date('j', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t) ?></dd></div>
                            <div><dt>Berat terakhir</dt><dd><?= e($a['p1509']) ?> kg</dd></div>
                            <div><dt>Skrining</dt><dd><?= (int)$a['jumlah_skrining'] ?> kali</dd></div>
                        </dl>
                    <?php endif; ?>

                    <div class="aksi-anak">
                        <?php if ($a['id_skrining']): ?>
                            <a class="tombol tombol-kecil tombol-putih" href="hasil.php?id=<?= (int)$a['id_skrining'] ?>">Lihat hasil</a>
                        <?php endif; ?>
                        <?php if ($melewati): ?>
                            <span class="catatan-usia">Sudah melewati usia balita</span>
                        <?php else: ?>
                            <a class="tombol tombol-kecil" href="skrining.php?balita=<?= (int)$a['id'] ?>"><?= ikon('ulang', 15) ?> Skrining ulang</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- ============================ CARA KERJA ============================ -->
<section class="bagian-p">
    <div class="kepala-bagian">
        <div>
            <span class="label-p">Cara kerja</span>
            <h2>Bagaimana sistem menilai risiko?</h2>
        </div>
    </div>
    <div class="kisi-langkah-p">
        <div class="langkah-p">
            <span class="no">1</span>
            <h3>Anda mengisi kondisi anak</h3>
            <p>Usia, berat badan, pola makan, riwayat anemia dan TBC, serta sumber air minum keluarga.</p>
        </div>
        <div class="langkah-p">
            <span class="no">2</span>
            <h3>Sistem mencari 41 anak termirip</h3>
            <p>Kondisi anak dibandingkan dengan data ratusan balita yang status gizinya sudah diketahui.</p>
        </div>
        <div class="langkah-p">
            <span class="no">3</span>
            <h3>Mengikuti kelompok terbanyak</h3>
            <p>Bila sebagian besar anak yang mirip berisiko stunting, anak Anda ditandai berisiko, dan sebaliknya.</p>
        </div>
    </div>
</section>

<!-- ============================ TIPS & INFO ============================ -->
<section class="bagian-p">
    <div class="kepala-bagian">
        <div>
            <span class="label-p">Tips gizi</span>
            <h2>Langkah sederhana mencegah stunting</h2>
        </div>
    </div>
    <div class="kisi-tips">
        <div class="tips">
            <span class="ikon"><?= ikon('makan', 22) ?></span>
            <h3>Protein hewani setiap hari</h3>
            <p>Satu butir telur, sepotong ikan, atau hati ayam setiap hari sangat membantu pertumbuhan anak.</p>
        </div>
        <div class="tips">
            <span class="ikon kuning"><?= ikon('timbangan', 22) ?></span>
            <h3>Timbang setiap bulan</h3>
            <p>Datang ke Posyandu tiap bulan dan pastikan grafik berat badan di buku KIA terus naik.</p>
        </div>
        <div class="tips">
            <span class="ikon biru"><?= ikon('air', 22) ?></span>
            <h3>Air minum yang bersih</h3>
            <p>Gunakan air yang dimasak atau air kemasan, dan cuci tangan dengan sabun sebelum menyiapkan makanan.</p>
        </div>
    </div>

    <div class="kartu-p akordeon" style="margin-top:1.1rem">
        <h2><?= ikon('info', 20) ?> Pertanyaan yang sering diajukan</h2>
        <details>
            <summary>Apakah hasil skrining ini diagnosis?</summary>
            <p>Bukan. Hasil skrining adalah peringatan dini. Status stunting yang sebenarnya ditentukan dari
               pengukuran tinggi badan oleh petugas kesehatan di Posyandu atau Puskesmas.</p>
        </details>
        <details>
            <summary>Apa yang perlu disiapkan sebelum skrining?</summary>
            <p>Tanggal lahir anak, berat badan dari penimbangan terakhir (lihat buku KIA), serta ingatan tentang
               makanan yang diterima anak dalam 24 jam terakhir.</p>
        </details>
        <details>
            <summary>Seberapa sering sebaiknya diperiksa?</summary>
            <p>Setiap bulan, bersamaan dengan jadwal penimbangan di Posyandu, agar perubahan kondisi anak cepat terlihat.</p>
        </details>
    </div>
</section>

<?php akhiriHalaman(); ?>