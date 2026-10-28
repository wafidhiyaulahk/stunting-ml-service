<?php
/**
 * Kerangka tampilan untuk semua halaman.
 *
 * Halaman pengguna memakai bilah menu di atas, sedangkan halaman admin
 * memakai sidebar di kiri yang berubah menjadi laci (drawer) di layar kecil.
 */
require_once __DIR__ . '/fungsi.php';

/*
 * Jaring pengaman: fungsi aset() seharusnya berada di config/config.php.
 * Bila berkas itu belum diperbarui, definisi cadangan di bawah membuat
 * halaman tetap tampil (hanya tanpa penanda versi anti-cache), bukan
 * berhenti dengan layar putih karena fungsi tidak ditemukan.
 */
if (!function_exists('aset')) {
    function aset(string $relatif): string
    {
        $relatif = ltrim($relatif, '/');
        $berkas  = __DIR__ . '/../' . $relatif;
        $versi   = is_file($berkas) ? filemtime($berkas) : time();
        return BASE_URL . '/' . $relatif . '?v=' . $versi;
    }
}

/** Menandai apakah halaman yang sedang dibangun memakai kerangka admin. */
$GLOBALS['__kerangka_admin'] = false;

/**
 * Ikon garis sederhana (SVG sebaris), tanpa pustaka dari internet.
 */
function ikon(string $nama, int $ukuran = 20): string
{
    $jalur = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'pengguna'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'skrining'  => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
        'posyandu'  => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><path d="M12 7v6"/><path d="M9 10h6"/>',
        'keluar'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'menu'      => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'balita'    => '<circle cx="12" cy="6" r="3"/><path d="M8 21v-6l-2-3a6 6 0 0 1 12 0l-2 3v6"/>',
        'peringatan'=> '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'tambah'    => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'panah'     => '<path d="m9 18 6-6-6-6"/>',
        'cari'      => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'beranda'   => '<path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M9 22V12h6v10"/>',
        'kosong'    => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>',
        'unduh'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'riwayat'   => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'timbangan' => '<path d="M6 3h12l2 18H4Z"/><path d="M12 7v3"/><circle cx="12" cy="13" r="3"/>',
        'makan'     => '<path d="M3 11h18"/><path d="M4 11a8 8 0 0 0 16 0"/><path d="M12 3v4"/><path d="M8 5l1 3"/><path d="M16 5l-1 3"/>',
        'jantung'   => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 12 5a5.5 5.5 0 0 0-10 3.5c0 2.3 1.5 4 3 5.5l7 7Z"/><path d="M3.5 12h5l2-3 3 6 2-3h5"/>',
        'air'       => '<path d="M12 22a7 7 0 0 0 7-7c0-4-7-13-7-13S5 11 5 15a7 7 0 0 0 7 7Z"/>',
        'telepon'   => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/>',
        'cetak'     => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/>',
        'ulang'     => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
        'centang'   => '<path d="M20 6 9 17l-5-5"/>',
        'info'      => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'lampu'     => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2Z"/>',
        'kembali'   => '<path d="m15 18-6-6 6-6"/>',
        'pengguna1' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'tanggal'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'perisai'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    ];
    $isi = $jalur[$nama] ?? '';
    return '<svg width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $isi . '</svg>';
}

/**
 * Logo aplikasi.
 *
 * Untuk memakai logo sendiri, cukup taruh berkasnya di folder
 * web/assets/img dengan salah satu nama berikut: logo.png, logo.svg,
 * atau logo.jpg. Begitu berkas itu ada, seluruh halaman memakainya tanpa
 * perlu menyunting kode lagi. Bila berkas tidak ditemukan, tampilan
 * kembali memakai lambang tunas sehingga tidak pernah kosong.
 *
 * @param string $kelas Kelas CSS pembungkus, menyesuaikan tempat pemakaian.
 */
function berkasLogo(): ?string
{
    foreach (['logo.svg', 'logo.png', 'logo.jpg', 'logo.webp'] as $berkas) {
        if (is_file(__DIR__ . '/../assets/img/' . $berkas)) {
            return $berkas;
        }
    }
    return null;
}

function logoHtml(string $kelas = 'logo'): string
{
    $berkas = berkasLogo();
    if ($berkas !== null) {
        return '<span class="' . e($kelas) . ' ada-logo" aria-hidden="true">'
             . '<img src="' . aset('assets/img/' . $berkas) . '" alt=""></span>';
    }
    return '<span class="' . e($kelas) . '" aria-hidden="true">&#127793;</span>';
}

/**
 * Alamat ikon tab peramban (favicon).
 *
 * Memakai berkas logo yang sama bila tersedia, sehingga mengganti logo
 * sekaligus mengganti ikon pada tab. Bila belum ada, dipakai gambar tunas
 * bawaan yang ditulis langsung sebagai SVG agar tidak perlu berkas apa pun.
 */
function ikonSitusHtml(): string
{
    $berkas = berkasLogo();
    if ($berkas !== null) {
        return aset('assets/img/' . $berkas);
    }
    return 'data:image/svg+xml,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
        . '<rect width="32" height="32" rx="8" fill="#238552"/>'
        . '<path d="M16 24c0-5 3-8 7-9-1 5-3 8-7 9zm0 0c0-5-3-8-7-9 1 5 3 8 7 9z" fill="#fff"/></svg>'
    );
}

function kepalaHtml(string $judul, array $cssTambahan = []): void
{
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#238552">
<meta name="description" content="Aplikasi penelitian skrining risiko stunting balita dengan algoritma K-Nearest Neighbor di wilayah kerja <?= e(NAMA_INSTANSI) ?>.">
<title><?= e($judul) ?> &middot; <?= e(NAMA_APP) ?></title>
<link rel="icon" href="<?= ikonSitusHtml() ?>">
<link rel="stylesheet" href="<?= aset('assets/css/style.css') ?>">
<?php foreach ($cssTambahan as $css): ?>
<link rel="stylesheet" href="<?= aset('assets/css/' . $css) ?>">
<?php endforeach; ?>
<!-- Lapis tampilan modern dimuat paling akhir agar dapat memperbarui rupa
     halaman tanpa mengubah berkas gaya yang sudah ada. -->
<link rel="stylesheet" href="<?= aset('assets/css/modern.css') ?>">
<!-- defer: skrip dijalankan setelah HTML selesai dibaca, tetapi tetap sebelum
     event DOMContentLoaded, sehingga fungsi di app.js sudah tersedia bagi
     skrip kecil di masing-masing halaman. -->
<script src="<?= aset('assets/js/app.js') ?>" defer></script>
<script src="<?= aset('assets/js/modern.js') ?>" defer></script>
</head>
<?php
}

function mulaiHalaman(string $judul, string $peran = 'tamu', string $menuAktif = ''): void
{
    if ($peran === 'admin') {
        mulaiHalamanAdmin($judul, $menuAktif);
        return;
    }

    if ($peran === 'pengguna') {
        mulaiHalamanPengguna($judul, $menuAktif);
        return;
    }
    $menu = [];

    kepalaHtml($judul);
    ?>
<body>
<header class="topbar">
    <a class="merek" href="<?= BASE_URL ?>/index.php" style="color:inherit;text-decoration:none">
        <?= logoHtml() ?>
        <div><?= e(NAMA_APP) ?><span class="instansi"><?= e(NAMA_INSTANSI) ?></span></div>
    </a>
    <nav>
        <?php foreach ($menu as $kunci => [$teks, $tautan]): ?>
            <a href="<?= $tautan ?>" class="<?= $menuAktif === $kunci ? 'aktif' : '' ?>"><?= e($teks) ?></a>
        <?php endforeach; ?>
        <?php if ($peran === 'tamu'): ?>
            <a href="<?= BASE_URL ?>/index.php"><?= ikon('kembali', 16) ?> Halaman utama</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/auth/logout.php">Keluar</a>
        <?php endif; ?>
    </nav>
</header>
<?php
}

/**
 * Kerangka pengguna: bilah atas putih di laptop, dan menu bawah seperti
 * aplikasi di HP supaya tombol mudah dijangkau jempol.
 */
function mulaiHalamanPengguna(string $judul, string $menuAktif): void
{
    $GLOBALS['__kerangka_pengguna'] = true;
    $GLOBALS['__menu_aktif'] = $menuAktif;

    $nama    = $_SESSION['pengguna_nama'] ?? 'Pengguna';
    $inisial = strtoupper(mb_substr(trim($nama), 0, 1));
    $menu = [
        'beranda'  => ['Beranda',  BASE_URL . '/user/index.php',    'beranda'],
        'skrining' => ['Skrining', BASE_URL . '/user/skrining.php', 'skrining'],
        'riwayat'  => ['Riwayat',  BASE_URL . '/user/riwayat.php',  'riwayat'],
    ];

    kepalaHtml($judul, ['pengguna.css']);
    ?>
<body class="pengguna">
<header class="kepala-pengguna">
    <div class="kepala-isi">
        <a class="merek-pengguna" href="<?= BASE_URL ?>/user/index.php">
            <?= logoHtml() ?>
            <span><b><?= e(NAMA_APP) ?></b><small><?= e(NAMA_INSTANSI) ?></small></span>
        </a>
        <nav class="nav-pengguna" aria-label="Menu utama">
            <?php foreach ($menu as $k => [$teks, $tautan, $ik]): ?>
                <a href="<?= $tautan ?>" class="<?= $menuAktif === $k ? 'aktif' : '' ?>"
                   <?= $menuAktif === $k ? 'aria-current="page"' : '' ?>><?= ikon($ik, 18) ?> <?= e($teks) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="akun-pengguna">
            <span class="avatar" aria-hidden="true"><?= e($inisial) ?></span>
            <span class="nama"><?= e($nama) ?></span>
            <a class="tombol-keluar-p" href="<?= BASE_URL ?>/auth/logout.php" title="Keluar" aria-label="Keluar"><?= ikon('keluar', 18) ?></a>
        </div>
    </div>
</header>
<main class="isi-pengguna">
<?php
}

/**
 * Kerangka admin: sidebar kiri + bilah atas + area konten.
 */
function mulaiHalamanAdmin(string $judul, string $menuAktif): void
{
    $GLOBALS['__kerangka_admin'] = true;

    $menu = [
        'dashboard' => ['Dashboard',       BASE_URL . '/admin/index.php',         'dashboard'],
        'skrining'  => ['Data Skrining',   BASE_URL . '/admin/data_skrining.php', 'skrining'],
        'laporan'   => ['Cetak Laporan',   BASE_URL . '/admin/laporan.php',       'cetak'],
        'pengguna'  => ['Kelola Pengguna', BASE_URL . '/admin/pengguna.php',      'pengguna'],
        'posyandu'  => ['Kelola Posyandu', BASE_URL . '/admin/posyandu.php',      'posyandu'],
    ];

    $namaAdmin = $_SESSION['admin_nama'] ?? 'Admin';
    $inisial   = strtoupper(mb_substr(trim($namaAdmin), 0, 1));
    $hari      = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][(int)date('w')];

    kepalaHtml($judul, ['admin.css']);
    ?>
<body class="admin">
<div class="admin-kerangka">

    <aside class="sidebar" id="sidebar" aria-label="Menu admin">
        <a class="sidebar-merek" href="<?= BASE_URL ?>/admin/index.php">
            <?= logoHtml() ?>
            <span><b><?= e(NAMA_APP) ?></b><small>Panel Admin</small></span>
        </a>

        <div class="sidebar-isi">
            <div class="sidebar-label">Utama</div>
            <ul class="sidebar-menu">
                <?php foreach (['dashboard', 'skrining', 'laporan'] as $k): [$teks, $tautan, $ik] = $menu[$k]; ?>
                    <li><a href="<?= $tautan ?>" class="<?= $menuAktif === $k ? 'aktif' : '' ?>"
                           <?= $menuAktif === $k ? 'aria-current="page"' : '' ?>><?= ikon($ik) ?> <?= e($teks) ?></a></li>
                <?php endforeach; ?>
            </ul>

            <div class="sidebar-label">Data Master</div>
            <ul class="sidebar-menu">
                <?php foreach (['pengguna', 'posyandu'] as $k): [$teks, $tautan, $ik] = $menu[$k]; ?>
                    <li><a href="<?= $tautan ?>" class="<?= $menuAktif === $k ? 'aktif' : '' ?>"
                           <?= $menuAktif === $k ? 'aria-current="page"' : '' ?>><?= ikon($ik) ?> <?= e($teks) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="sidebar-kaki">
            <div class="profil-admin">
                <span class="avatar" aria-hidden="true"><?= e($inisial) ?></span>
                <div class="teks">
                    <div class="nama"><?= e($namaAdmin) ?></div>
                    <div class="peran">Administrator</div>
                </div>
                <a class="tombol-keluar" href="<?= BASE_URL ?>/auth/logout.php" title="Keluar" aria-label="Keluar">
                    <?= ikon('keluar', 18) ?>
                </a>
            </div>
        </div>
    </aside>

    <div class="latar-sidebar" onclick="tutupSidebar()" aria-hidden="true"></div>

    <div class="admin-utama">
        <header class="bilah-atas">
            <button class="tombol-menu" type="button" onclick="bukaSidebar()" aria-label="Buka menu" aria-controls="sidebar">
                <?= ikon('menu', 20) ?>
            </button>
            <div class="jejak">Admin &nbsp;/&nbsp; <b><?= e($judul) ?></b></div>
            <div class="bilah-kanan">
                <span class="tanggal-hari"><?= $hari ?>, <?= formatTanggal(date('Y-m-d')) ?></span>
                <?php $hidup = statusMlService(); ?>
                <span class="status-layanan <?= $hidup ? 'hidup' : 'mati' ?>"
                      title="<?= $hidup ? 'Layanan model KNN berjalan normal' : 'Jalankan python app.py di folder ml-service' ?>">
                    <span class="titik"></span> <span>KNN</span><span class="teks-status"><?= $hidup ? 'aktif' : 'mati' ?></span>
                </span>
            </div>
        </header>

        <main class="admin-konten">
<?php
}

/**
 * Kaki halaman berisi disklaimer penelitian.
 *
 * Disklaimer ini muncul di seluruh halaman karena sistem menyentuh ranah
 * kesehatan: pengguna harus selalu mengetahui bahwa keluaran sistem merupakan
 * hasil perhitungan algoritma pada data penelitian, bukan diagnosis medis.
 */
function kakiDisklaimer(): void
{
    ?>
<footer class="kaki-halaman">
    <div class="kaki-isi">
        <div class="disklaimer">
            <span class="ikon" aria-hidden="true">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                </svg>
            </span>
            <div>
                <h4>Disklaimer Penelitian</h4>
                <p>
                    Aplikasi ini merupakan produk penelitian skripsi yang menerapkan algoritma
                    <strong>K-Nearest Neighbor (KNN)</strong> untuk mendeteksi risiko stunting pada anak balita.
                    Hasil yang ditampilkan diperoleh dari perhitungan kemiripan data anak terhadap data
                    balita yang dipakai saat pelatihan model, sehingga bersifat <strong>deteksi dini</strong>
                    dan <strong>bukan diagnosis medis</strong>.
                </p>
                <p>
                    Penentuan status stunting yang sebenarnya memerlukan pengukuran tinggi badan menurut umur
                    serta pemeriksaan langsung oleh tenaga kesehatan. Silakan tetap membawa anak ke Posyandu
                    atau <?= e(NAMA_INSTANSI) ?> untuk pemeriksaan lanjutan, apa pun hasil skrining yang muncul
                    pada aplikasi ini.
                </p>
            </div>
        </div>
        <div class="kaki-bawah">
            <span><strong><?= e(NAMA_APP) ?></strong> &mdash; Implementasi Algoritma K-Nearest Neighbor untuk Deteksi Risiko Stunting pada Anak Balita Berbasis Website</span>
            <span><?= e(NAMA_INSTANSI) ?> &middot; <?= date('Y') ?></span>
        </div>
    </div>
</footer>
<?php
}

function akhiriHalaman(): void
{
    if (!empty($GLOBALS['__kerangka_admin'])) {
        ?>
        </main>
        <?php kakiDisklaimer(); ?>
    </div>
</div>
</body>
</html>
<?php
        return;
    }

    if (!empty($GLOBALS['__kerangka_pengguna'])) {
        $aktif = $GLOBALS['__menu_aktif'] ?? '';
        ?>
</main>
<?php kakiDisklaimer(); ?>
<nav class="menu-bawah" aria-label="Menu utama">
    <a href="<?= BASE_URL ?>/user/index.php" class="<?= $aktif === 'beranda' ? 'aktif' : '' ?>"><?= ikon('beranda', 22) ?><span>Beranda</span></a>
    <a href="<?= BASE_URL ?>/user/skrining.php" class="utama <?= $aktif === 'skrining' ? 'aktif' : '' ?>"><span class="bulat"><?= ikon('tambah', 26) ?></span><span>Skrining</span></a>
    <a href="<?= BASE_URL ?>/user/riwayat.php" class="<?= $aktif === 'riwayat' ? 'aktif' : '' ?>"><?= ikon('riwayat', 22) ?><span>Riwayat</span></a>
</nav>
</body>
</html>
<?php
        return;
    }

    kakiDisklaimer();
    ?>
</body>
</html>
<?php
}