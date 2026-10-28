<?php
/**
 * Halaman utama publik (sebelum login).
 *
 * Berisi informasi umum seperti situs pada umumnya: pengenalan stunting,
 * cara kerja sistem, daftar Posyandu, dan tanya-jawab. Tombol "Masuk" di
 * header memberi pilihan masuk sebagai orang tua atau sebagai petugas.
 *
 * Pengguna atau admin yang masih login langsung diarahkan ke halamannya.
 */
require_once __DIR__ . '/helpers/layout.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}
if (!empty($_SESSION['pengguna_id'])) {
    header('Location: ' . BASE_URL . '/user/index.php');
    exit;
}

$baruKeluar = isset($_GET['keluar']);

try {
    $jumlahPosyandu = (int)$pdo->query('SELECT COUNT(*) FROM posyandu')->fetchColumn();
    $jumlahSkrining = (int)$pdo->query('SELECT COUNT(*) FROM skrining')->fetchColumn();
    $daftarPosyandu = $pdo->query(
        'SELECT nama_posyandu, kelurahan, rw, alamat, nama_kepala FROM posyandu ORDER BY kelurahan, nama_posyandu'
    )->fetchAll();
} catch (Throwable $e) {
    $jumlahPosyandu = 0;
    $jumlahSkrining = 0;
    $daftarPosyandu = [];
}

// Kelompokkan Posyandu per kelurahan
$perKelurahan = [];
foreach ($daftarPosyandu as $p) {
    $perKelurahan[$p['kelurahan']][] = $p;
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#238552">
<meta name="description" content="Aplikasi penelitian skrining risiko stunting balita dengan algoritma K-Nearest Neighbor di wilayah kerja <?= e(NAMA_INSTANSI) ?>.">
<title><?= e(NAMA_APP) ?> &middot; Deteksi Dini Risiko Stunting Balita</title>
<link rel="icon" href="<?= ikonSitusHtml() ?>">
<link rel="stylesheet" href="<?= aset('assets/css/style.css') ?>">
<link rel="stylesheet" href="<?= aset('assets/css/landing.css') ?>">
<link rel="stylesheet" href="<?= aset('assets/css/modern.css') ?>">
<script src="<?= aset('assets/js/modern.js') ?>" defer></script>
</head>
<body class="beranda-publik">

<!-- ============================ HEADER ============================ -->
<header class="nav-depan" id="atas">
    <div class="nav-isi">
        <a class="nav-merek" href="<?= BASE_URL ?>/index.php">
            <?= logoHtml() ?>
            <span><?= e(NAMA_APP) ?><small><?= e(NAMA_INSTANSI) ?></small></span>
        </a>

        <nav class="nav-tautan-grup" id="menu-publik" aria-label="Menu utama">
            <a class="nav-tautan" href="#atas">Beranda</a>
            <a class="nav-tautan" href="#tentang">Tentang Stunting</a>
            <a class="nav-tautan" href="#cara-kerja">Cara Kerja</a>
            <a class="nav-tautan" href="#posyandu">Posyandu</a>
            <a class="nav-tautan" href="#tanya-jawab">Tanya Jawab</a>
        </nav>

        <div class="nav-aksi">
            <details class="menu-masuk">
                <summary class="tombol tombol-kecil tombol-putih">
                    Masuk <?= ikon('panah', 14) ?>
                </summary>
                <div class="menu-masuk-isi">
                    <a href="<?= BASE_URL ?>/auth/login.php">
                        <span class="ikon"><?= ikon('pengguna1', 18) ?></span>
                        <span><b>Orang tua / kader</b><small>Skrining dan riwayat anak</small></span>
                    </a>
                    <a href="<?= BASE_URL ?>/auth/login_admin.php">
                        <span class="ikon admin"><?= ikon('perisai', 18) ?></span>
                        <span><b>Petugas Puskesmas</b><small>Panel admin</small></span>
                    </a>
                </div>
            </details>
            <a class="tombol tombol-kecil tombol-daftar" href="<?= BASE_URL ?>/auth/registrasi.php">Daftar</a>
            <button class="tombol-hamburger" type="button" aria-label="Buka menu" aria-controls="menu-publik"
                    onclick="document.body.classList.toggle('menu-publik-terbuka')"><?= ikon('menu', 22) ?></button>
        </div>
    </div>
</header>

<?php if ($baruKeluar): ?>
    <div class="toast-keluar" role="status" id="toast-keluar">
        <?= ikon('centang', 18) ?> Anda sudah keluar dengan aman.
    </div>
<?php endif; ?>

<!-- ============================ SAMPUL ============================ -->
<section class="sampul">
    <div class="sampul-isi">
        <div>
            <span class="pil"><span class="titik"></span> Aplikasi Penelitian &middot; Algoritma K-Nearest Neighbor</span>

            <h1>Kenali <span class="sorot">risiko stunting</span> pada anak sejak dini, dari rumah.</h1>

            <p class="intro">
                Cukup isi berat badan, usia, dan kebiasaan makan anak. Sistem membandingkan kondisi
                anak Anda dengan data ratusan balita yang sudah diketahui statusnya, lalu memberi
                saran gizi serta daftar Posyandu terdekat di wilayah kerja <?= e(NAMA_INSTANSI) ?>.
            </p>

            <div class="sampul-aksi">
                <a class="tombol tombol-besar" href="<?= BASE_URL ?>/auth/registrasi.php">
                    Mulai Skrining Gratis <?= ikon('panah', 18) ?>
                </a>
                <a class="tombol tombol-besar tombol-putih" href="<?= BASE_URL ?>/auth/login.php">Sudah punya akun</a>
            </div>

            <div class="jaminan">
                <div><?= ikon('centang', 17) ?> Tanpa biaya</div>
                <div><?= ikon('riwayat', 17) ?> Hasil keluar seketika</div>
                <div><?= ikon('perisai', 17) ?> Data anak tersimpan aman</div>
            </div>
        </div>

        <!-- Ilustrasi cara kerja KNN -->
        <aside class="kartu-ilustrasi">
            <p class="judul-kecil">Cara sistem menilai</p>
            <h3>Mencari 41 anak dengan kondisi paling mirip</h3>
            <svg class="peta" viewBox="0 0 320 260" role="img"
                 aria-label="Ilustrasi algoritma K-Nearest Neighbor: data anak dibandingkan dengan balita lain yang kondisinya paling mirip.">
                <defs>
                    <pattern id="kisi" width="32" height="32" patternUnits="userSpaceOnUse">
                        <path d="M32 0H0v32" fill="none" stroke="#edf2ef" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="320" height="260" fill="#fbfdfc"/>
                <rect width="320" height="260" fill="url(#kisi)"/>
                <circle cx="160" cy="130" r="85" fill="rgba(51,162,102,.06)" stroke="#5fc084" stroke-width="1.6" stroke-dasharray="5 5"/>
                <g fill="#b9e3c8">
                    <circle cx="40" cy="50" r="5"/><circle cx="70" cy="30" r="5"/><circle cx="260" cy="45" r="5"/>
                    <circle cx="290" cy="90" r="5"/><circle cx="35" cy="200" r="5"/><circle cx="270" cy="210" r="5"/>
                    <circle cx="295" cy="165" r="5"/><circle cx="250" cy="240" r="5"/><circle cx="30" cy="110" r="5"/>
                    <circle cx="150" cy="25" r="5"/><circle cx="90" cy="228" r="5"/><circle cx="230" cy="30" r="5"/>
                </g>
                <g fill="#e6b3ad"><circle cx="60" cy="238" r="5"/><circle cx="288" cy="130" r="5"/></g>
                <g fill="#b32218">
                    <circle cx="120" cy="95" r="6"/><circle cx="185" cy="80" r="6"/><circle cx="210" cy="110" r="6"/>
                    <circle cx="100" cy="140" r="6"/><circle cx="160" cy="190" r="6"/><circle cx="195" cy="170" r="6"/>
                    <circle cx="140" cy="120" r="6"/><circle cx="190" cy="125" r="6"/><circle cx="170" cy="158" r="6"/>
                </g>
                <g fill="#5fc084">
                    <circle cx="145" cy="70" r="6"/><circle cx="125" cy="165" r="6"/>
                    <circle cx="215" cy="145" r="6"/><circle cx="110" cy="115" r="6"/>
                </g>
                <circle cx="160" cy="130" r="15" fill="#fff" stroke="#1a6842" stroke-width="2.5"/>
                <circle cx="160" cy="130" r="6" fill="#1a6842"/>
                <rect x="104" y="215" width="112" height="26" rx="13" fill="#1a6842"/>
                <text x="160" y="232" text-anchor="middle" fill="#fff" font-family="Segoe UI, system-ui, sans-serif" font-size="12.5" font-weight="600">Anak Anda</text>
            </svg>
            <div class="keterangan">
                <span><i style="background:#b32218"></i> Berisiko stunting</span>
                <span><i style="background:#5fc084"></i> Tidak berisiko</span>
                <span><i style="background:#b9e3c8"></i> Data lain</span>
            </div>
        </aside>
    </div>
</section>

<!-- ============================ TENTANG STUNTING ============================ -->
<section class="bagian" id="tentang">
    <div class="bagian-kepala">
        <span class="label">Tentang stunting</span>
        <h2>Stunting tidak terlihat dalam semalam</h2>
        <p>Stunting adalah gagal tumbuh akibat kekurangan gizi yang berlangsung lama. Tandanya sering baru
           disadari ketika anak sudah jauh tertinggal dari teman seusianya, padahal masa paling menentukan
           justru terjadi jauh sebelum itu.</p>
    </div>

    <div class="kisi-fakta">
        <div class="fakta">
            <span class="angka">1.000</span>
            <p>Hari pertama kehidupan, sejak dalam kandungan hingga usia dua tahun, adalah masa emas pertumbuhan anak</p>
        </div>
        <div class="fakta">
            <span class="angka">0&ndash;59</span>
            <p>Rentang usia balita dalam bulan yang dapat diperiksa melalui aplikasi ini</p>
        </div>
        <div class="fakta">
            <span class="angka">41</span>
            <p>Jumlah anak dengan kondisi paling mirip yang ditimbang sistem untuk setiap satu hasil skrining</p>
        </div>
        <div class="fakta">
            <span class="angka"><?= $jumlahPosyandu ?></span>
            <p>Posyandu di wilayah kerja <?= e(NAMA_INSTANSI) ?> yang terdaftar dan siap dihubungi</p>
        </div>
    </div>

    <div class="kisi-penyebab">
        <div class="penyebab">
            <span class="ikon"><?= ikon('makan', 22) ?></span>
            <h3>Asupan gizi kurang beragam</h3>
            <p>Makanan yang hanya mengenyangkan tanpa lauk hewani, sayur, dan buah membuat anak kekurangan zat pembangun tubuh.</p>
        </div>
        <div class="penyebab">
            <span class="ikon merah"><?= ikon('jantung', 22) ?></span>
            <h3>Penyakit yang berulang</h3>
            <p>Anemia, TBC, dan diare berulang menguras energi anak sehingga berat dan tinggi badan sulit bertambah.</p>
        </div>
        <div class="penyebab">
            <span class="ikon biru"><?= ikon('air', 22) ?></span>
            <h3>Air dan sanitasi kurang layak</h3>
            <p>Air minum yang tidak terlindungi meningkatkan risiko infeksi yang menghambat pertumbuhan anak.</p>
        </div>
    </div>
</section>

<!-- ============================ CARA KERJA ============================ -->
<section class="bagian-terang" id="cara-kerja">
    <div class="bagian">
        <div class="bagian-kepala">
            <span class="label">Cara kerja</span>
            <h2>Tiga langkah, selesai dalam beberapa menit</h2>
            <p>Anda tidak perlu memahami istilah teknis apa pun. Cukup jawab pertanyaan sederhana tentang kondisi anak sehari-hari.</p>
        </div>
        <div class="kisi-langkah">
            <div class="langkah-kartu">
                <div class="nomor" aria-hidden="true"></div>
                <h3>Daftar dan isi data anak</h3>
                <p>Buat akun dengan nomor HP, lalu masukkan nama dan tanggal lahir anak. Usia dalam bulan dihitung otomatis.</p>
            </div>
            <div class="langkah-kartu">
                <div class="nomor" aria-hidden="true"></div>
                <h3>Jawab kondisi sehari-hari</h3>
                <p>Isi berat badan dari penimbangan terakhir, centang makanan yang dimakan anak, serta riwayat anemia dan TBC.</p>
            </div>
            <div class="langkah-kartu">
                <div class="nomor" aria-hidden="true"></div>
                <h3> hasil dan saran</h3>
                <p>Sistem menampilkan hasil skrining, saran gizi sesuai usia anak, dan Posyandu terdekat. 
                    catatan : hasil yang diberikan bukan diagnosis, sistem ini hanya skrining awal perlu dikonfirmasi oleh tenaga kesehatan </p>
            </div>
        </div>
    </div>
</section>

<!-- ============================ POSYANDU ============================ -->
<section class="bagian" id="posyandu">
    <div class="bagian-kepala">
        <span class="label">Posyandu</span>
        <h2>Posyandu di wilayah kerja <?= e(NAMA_INSTANSI) ?></h2>
        <p>Bawa anak ke Posyandu terdekat setiap bulan untuk penimbangan dan pengukuran tinggi badan.
           Nomor kader dapat dilihat setelah Anda masuk.</p>
    </div>

    <?php if (!$perKelurahan): ?>
        <p style="text-align:center;color:var(--abu-500)">Data Posyandu belum tersedia.</p>
    <?php else: ?>
        <div class="kisi-posyandu">
            <?php foreach ($perKelurahan as $kel => $daftar): ?>
                <div class="kel-posyandu">
                    <h3><?= ikon('posyandu', 18) ?> Kel. <?= e($kel) ?></h3>
                    <ul>
                        <?php foreach ($daftar as $p): ?>
                            <li><b><?= e($p['nama_posyandu']) ?></b><span><?= e($p['alamat']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- ============================ TANYA JAWAB ============================ -->
<section class="bagian-terang" id="tanya-jawab">
    <div class="bagian" style="max-width:820px">
        <div class="bagian-kepala">
            <span class="label">Tanya jawab</span>
            <h2>Pertanyaan yang sering diajukan</h2>
        </div>
        <div class="faq">
            <details>
                <summary>Apakah hasil skrining ini sama dengan diagnosis dokter?</summary>
                <p>Tidak. Hasil skrining adalah peringatan dini berdasarkan kemiripan data. Status stunting yang sebenarnya
                   ditentukan dari pengukuran tinggi badan oleh petugas kesehatan di Posyandu atau Puskesmas.</p>
            </details>
            <details>
                <summary>Siapa saja yang bisa memakai aplikasi ini?</summary>
                <p>Orang tua, wali, maupun kader Posyandu yang memiliki balita usia 0 sampai 59 bulan di wilayah kerja
                   <?= e(NAMA_INSTANSI) ?>. Satu akun dapat dipakai untuk memantau beberapa anak.</p>
            </details>
            <details>
                <summary>Apa yang perlu disiapkan sebelum skrining?</summary>
                <p>Tanggal lahir anak, berat badan dari penimbangan terakhir (lihat buku KIA), serta ingatan tentang
                   makanan yang diterima anak dalam 24 jam terakhir.</p>
            </details>
            <details>
                <summary>Apa itu algoritma K-Nearest Neighbor (KNN)?</summary>
                <p>KNN adalah metode yang menilai sesuatu berdasarkan data lain yang paling mirip. Aplikasi ini mencari
                   41 balita dengan kondisi paling mirip dengan anak Anda, lalu mengikuti kelompok terbanyak di antara
                   mereka: berisiko atau tidak berisiko stunting.</p>
            </details>
            <details>
                <summary>Apakah data anak saya aman?</summary>
                <p>Data hanya dapat dilihat oleh pemilik akun dan petugas Puskesmas. Kata sandi disimpan dalam bentuk
                   terenkripsi sehingga tidak dapat dibaca siapa pun, termasuk petugas.</p>
            </details>
            <details>
                <summary>Seberapa sering sebaiknya anak diskrining?</summary>
                <p>Setiap bulan, bersamaan dengan jadwal penimbangan di Posyandu, agar perubahan kondisi anak cepat terlihat.</p>
            </details>
        </div>
    </div>
</section>

<!-- ============================ AJAKAN ============================ -->
<section class="ajakan">
    <h2>Mulai periksa kondisi anak Anda sekarang</h2>
    <p>Pendaftaran hanya memerlukan nomor HP dan alamat. Prosesnya gratis, dan hasilnya dapat langsung Anda bawa
       ke Posyandu terdekat sebagai bahan pemeriksaan lanjutan.</p>
    <div style="display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap">
        <a class="tombol tombol-besar tombol-putih" href="<?= BASE_URL ?>/auth/registrasi.php">Daftar Sekarang</a>
        <a class="tombol tombol-besar tombol-transparan" href="<?= BASE_URL ?>/auth/login.php">Masuk</a>
    </div>
</section>

<!-- ============================ FOOTER ============================ -->
<footer class="kaki-publik">
    <div class="kaki-publik-isi">
        <div>
            <a class="nav-merek" href="<?= BASE_URL ?>/index.php" style="color:#fff">
                <?= logoHtml() ?>
                <span><?= e(NAMA_APP) ?><small style="color:#9cc3ab"><?= e(NAMA_INSTANSI) ?></small></span>
            </a>
            <p>Sistem deteksi dini risiko stunting pada anak balita berbasis website, menggunakan algoritma
               K-Nearest Neighbor.</p>
        </div>
        <div>
            <h4>Menu</h4>
            <a href="#tentang">Tentang Stunting</a>
            <a href="#cara-kerja">Cara Kerja</a>
            <a href="#posyandu">Posyandu</a>
            <a href="#tanya-jawab">Tanya Jawab</a>
        </div>
        <div>
            <h4>Akun</h4>
            <a href="<?= BASE_URL ?>/auth/registrasi.php">Daftar</a>
            <a href="<?= BASE_URL ?>/auth/login.php">Masuk orang tua / kader</a>
            <a href="<?= BASE_URL ?>/auth/login_admin.php">Masuk petugas</a>
        </div>
        <div>
            <h4>Wilayah layanan</h4>
            <p style="margin:0"><?= e(NAMA_INSTANSI) ?><br>Kecamatan Pamulang<br>Kota Tangerang Selatan</p>
        </div>
    </div>
</footer>

<?php kakiDisklaimer(); ?>

<script>
// Tutup menu Masuk dan menu HP saat mengklik di luar atau memilih tautan
document.addEventListener('click', (e) => {
    const menu = document.querySelector('.menu-masuk');
    if (menu && menu.open && !menu.contains(e.target)) menu.open = false;
    if (e.target.closest('.nav-tautan-grup a')) document.body.classList.remove('menu-publik-terbuka');
});
// Sembunyikan pemberitahuan "sudah keluar" setelah beberapa detik dan bersihkan alamat
const toast = document.getElementById('toast-keluar');
if (toast) {
    setTimeout(() => toast.classList.add('hilang'), 3500);
    history.replaceState(null, '', location.pathname);
}
</script>
</body>
</html>