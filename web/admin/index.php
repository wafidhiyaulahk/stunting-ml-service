<?php
require_once __DIR__ . '/../helpers/layout.php';
wajibLoginAdmin();

/* ============================ Pengambilan data ============================ */

$total    = (int)$pdo->query('SELECT COUNT(*) FROM skrining')->fetchColumn();
$berisiko = (int)$pdo->query('SELECT COUNT(*) FROM skrining WHERE hasil = 1')->fetchColumn();
$aman     = $total - $berisiko;
$pengguna = (int)$pdo->query('SELECT COUNT(*) FROM pengguna')->fetchColumn();
$balita   = (int)$pdo->query('SELECT COUNT(*) FROM balita')->fetchColumn();
$posyandu = (int)$pdo->query('SELECT COUNT(*) FROM posyandu')->fetchColumn();

$persenRisiko = $total > 0 ? round($berisiko / $total * 100, 1) : 0;
$persenAman   = $total > 0 ? round(100 - $persenRisiko, 1) : 0;
// Tampilan memakai koma desimal sesuai kaidah bahasa Indonesia (28,2%)
$tRisiko = number_format($persenRisiko, 1, ',', '.');
$tAman   = number_format($persenAman, 1, ',', '.');

// Skrining bulan ini dibanding bulan lalu
$bulanIni  = (int)$pdo->query("SELECT COUNT(*) FROM skrining
    WHERE DATE_FORMAT(tanggal_skrining,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();
$bulanLalu = (int)$pdo->query("SELECT COUNT(*) FROM skrining
    WHERE DATE_FORMAT(tanggal_skrining,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m')")->fetchColumn();

// Balita baru dalam 30 hari terakhir
$balitaBaru = (int)$pdo->query('SELECT COUNT(*) FROM balita WHERE created_at >= CURDATE() - INTERVAL 30 DAY')->fetchColumn();

// Tren enam bulan terakhir, bulan tanpa data tetap ditampilkan bernilai nol
$namaBulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$tren = [];
for ($i = 5; $i >= 0; $i--) {
    $t = new DateTime('first day of this month');
    $t->modify("-{$i} month");
    $tren[$t->format('Y-m')] = [
        'label'    => $namaBulan[(int)$t->format('n')],
        'labelPjg' => $namaBulan[(int)$t->format('n')] . ' ' . $t->format('Y'),
        'aman'     => 0,
        'risiko'   => 0,
    ];
}
$stmt = $pdo->query("SELECT DATE_FORMAT(tanggal_skrining,'%Y-%m') AS bln,
                            SUM(hasil = 1) AS risiko, SUM(hasil = 0) AS aman
                     FROM skrining
                     WHERE tanggal_skrining >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
                     GROUP BY bln");
foreach ($stmt as $r) {
    if (isset($tren[$r['bln']])) {
        $tren[$r['bln']]['risiko'] = (int)$r['risiko'];
        $tren[$r['bln']]['aman']   = (int)$r['aman'];
    }
}

// Sebaran per kelurahan
$perKelurahan = $pdo->query(
    'SELECT b.kelurahan, SUM(s.hasil = 1) AS berisiko, COUNT(*) AS jumlah
     FROM skrining s JOIN balita b ON b.id = s.balita_id
     GROUP BY b.kelurahan
     ORDER BY berisiko DESC, jumlah DESC, b.kelurahan'
)->fetchAll();
$maksWilayah = max(1, ...array_map(fn($r) => (int)$r['jumlah'], $perKelurahan ?: [['jumlah' => 1]]));

// Lima skrining terbaru
$terbaru = $pdo->query(
    'SELECT s.id, s.hasil, s.probabilitas, s.tanggal_skrining, s.p4072_anak,
            b.nama_balita, b.kelurahan
     FROM skrining s JOIN balita b ON b.id = s.balita_id
     ORDER BY s.tanggal_skrining DESC LIMIT 5'
)->fetchAll();

/* ============================ Grafik tren (SVG) ============================ */

$maksTren = max(1, ...array_map(fn($b) => $b['aman'] + $b['risiko'], array_values($tren)));

// Skala sumbu dibulatkan ke angka yang bersih
$langkah = max(1, (int)ceil($maksTren / 4));
foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000] as $l) {
    if ($l >= $langkah) { $langkah = $l; break; }
}
$puncak = $langkah * (int)ceil($maksTren / $langkah);

$delta = $bulanIni - $bulanLalu;

mulaiHalaman('Dashboard', 'admin', 'dashboard');
?>

<div class="kepala-halaman">
    <div>
        <h1>Dashboard</h1>
        <p>Ringkasan hasil skrining risiko stunting di wilayah kerja <?= e(NAMA_INSTANSI) ?>.</p>
    </div>
    <a class="tombol" href="data_skrining.php"><?= ikon('skrining', 18) ?> Lihat Data Skrining</a>
</div>

<?php if (!statusMlService()): ?>
    <div class="alert alert-gagal">
        <?= ikon('peringatan', 18) ?>
        <span>Layanan model KNN sedang tidak aktif, sehingga pengguna belum dapat melakukan skrining.
        Jalankan <code>jalankan_service.bat</code> atau <code>python app.py</code> di folder <code>ml-service</code>.</span>
    </div>
<?php endif; ?>

<!-- ===================== Kartu statistik ===================== -->
<section class="kisi-stat" aria-label="Ringkasan angka">
    <div class="stat">
        <span class="ikon"><?= ikon('skrining', 22) ?></span>
        <div>
            <div class="label">Total skrining</div>
            <div class="nilai"><?= number_format($total, 0, ',', '.') ?></div>
            <div class="sub">
                <b><?= $bulanIni ?></b> bulan ini
                <?php if ($bulanLalu > 0 || $bulanIni > 0): ?>
                    &middot; <?= $delta >= 0 ? '+' : '&minus;' ?><?= abs($delta) ?> dari bulan lalu
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="stat">
        <span class="ikon merah"><?= ikon('peringatan', 22) ?></span>
        <div>
            <div class="label">Berisiko stunting</div>
            <div class="nilai"><?= number_format($berisiko, 0, ',', '.') ?></div>
            <div class="sub"><b><?= $tRisiko ?>%</b> dari seluruh skrining</div>
        </div>
    </div>
    <div class="stat">
        <span class="ikon biru"><?= ikon('balita', 22) ?></span>
        <div>
            <div class="label">Balita terdata</div>
            <div class="nilai"><?= number_format($balita, 0, ',', '.') ?></div>
            <div class="sub"><b><?= $balitaBaru ?></b> baru dalam 30 hari &middot; <?= $pengguna ?> akun</div>
        </div>
    </div>
    <div class="stat">
        <span class="ikon kuning"><?= ikon('posyandu', 22) ?></span>
        <div>
            <div class="label">Posyandu terdaftar</div>
            <div class="nilai"><?= number_format($posyandu, 0, ',', '.') ?></div>
            <div class="sub">Rujukan tindak lanjut pengguna</div>
        </div>
    </div>
</section>

<!-- ===================== Proporsi + tren ===================== -->
<section class="kisi-panel">
    <article class="panel lebar-4">
        <div class="panel-kepala">
            <div>
                <h2>Proporsi hasil deteksi</h2>
                <p>Seluruh skrining yang pernah dilakukan</p>
            </div>
        </div>
        <div class="panel-isi">
            <?php if ($total === 0): ?>
                <div class="kosong"><?= ikon('kosong', 36) ?><br>Belum ada data skrining.</div>
            <?php else: ?>
                <div class="angka-utama">
                    <b><?= $tRisiko ?>%</b>
                    <span>balita berisiko stunting</span>
                </div>

                <div class="batang-proporsi" role="img"
                     aria-label="<?= $berisiko ?> berisiko dan <?= $aman ?> tidak berisiko dari <?= $total ?> skrining">
                    <?php if ($berisiko > 0): ?>
                        <div style="width:<?= $persenRisiko ?>%;background:var(--grafik-risiko)"
                             data-tip='<?= e(json_encode(['judul' => 'Berisiko stunting', 'baris' => [['warna' => '#b32218', 'teks' => "$berisiko skrining ($tRisiko%)"]]])) ?>'></div>
                    <?php endif; ?>
                    <?php if ($aman > 0): ?>
                        <div style="width:<?= $persenAman ?>%;background:var(--grafik-aman)"
                             data-tip='<?= e(json_encode(['judul' => 'Tidak berisiko', 'baris' => [['warna' => '#5fc084', 'teks' => "$aman skrining ($tAman%)"]]])) ?>'></div>
                    <?php endif; ?>
                </div>

                <div class="rincian-proporsi">
                    <div class="baris">
                        <span><i style="background:var(--grafik-risiko)"></i> Berisiko stunting</span>
                        <b><?= $berisiko ?> &middot; <?= $tRisiko ?>%</b>
                    </div>
                    <div class="baris">
                        <span><i style="background:var(--grafik-aman)"></i> Tidak berisiko</span>
                        <b><?= $aman ?> &middot; <?= $tAman ?>%</b>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <article class="panel lebar-8">
        <div class="panel-kepala">
            <div>
                <h2>Tren skrining 6 bulan terakhir</h2>
                <p>Jumlah skrining per bulan menurut hasil deteksi</p>
            </div>
            <div class="legenda">
                <span><i style="background:var(--grafik-risiko)"></i> Berisiko</span>
                <span><i style="background:var(--grafik-aman)"></i> Tidak berisiko</span>
            </div>
        </div>
        <div class="panel-isi">
            <!-- Grafik dibangun dengan HTML/CSS, bukan SVG, agar ukuran huruf
                 tetap sama di layar mana pun; hanya lebar kolomnya yang menyesuaikan. -->
            <div class="tren" role="img" aria-label="Grafik kolom bertumpuk jumlah skrining per bulan selama enam bulan terakhir">
                <div class="tren-plot">
                    <?php for ($v = 0; $v <= $puncak; $v += $langkah): ?>
                        <div class="tren-garis" style="bottom:<?= $v / $puncak * 100 ?>%"><span><?= $v ?></span></div>
                    <?php endfor; ?>
                    <div class="tren-kolom">
                        <?php foreach ($tren as $b):
                            $jml = $b['aman'] + $b['risiko'];
                            $tip = ['judul' => $b['labelPjg'], 'baris' => [
                                ['warna' => '#b32218', 'teks' => 'Berisiko: ' . $b['risiko']],
                                ['warna' => '#5fc084', 'teks' => 'Tidak berisiko: ' . $b['aman']],
                            ]];
                        ?>
                            <div class="tren-slot" data-tip='<?= e(json_encode($tip)) ?>'>
                                <div class="tren-tumpuk" style="height:<?= $jml / $puncak * 100 ?>%">
                                    <?php if ($jml > 0): ?><span class="tren-nilai"><?= $jml ?></span><?php endif; ?>
                                    <?php if ($b['risiko'] > 0): ?><div class="seg risiko" style="flex-grow:<?= $b['risiko'] ?>"></div><?php endif; ?>
                                    <?php if ($b['aman'] > 0): ?><div class="seg aman" style="flex-grow:<?= $b['aman'] ?>"></div><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="tren-bulan">
                    <?php foreach ($tren as $b): ?><span><?= e($b['label']) ?></span><?php endforeach; ?>
                </div>
            </div>
        </div>
    </article>
</section>

<!-- ===================== Kelurahan + skrining terbaru ===================== -->
<section class="kisi-panel">
    <article class="panel lebar-5">
        <div class="panel-kepala">
            <div>
                <h2>Sebaran per kelurahan</h2>
                <p>Diurutkan dari jumlah balita berisiko terbanyak</p>
            </div>
        </div>
        <div class="panel-isi">
            <?php if (!$perKelurahan): ?>
                <div class="kosong"><?= ikon('kosong', 36) ?><br>Belum ada data skrining.</div>
            <?php else: ?>
                <div class="legenda" style="margin-bottom:1rem">
                    <span><i style="background:var(--grafik-risiko)"></i> Berisiko</span>
                    <span><i style="background:var(--grafik-aman)"></i> Tidak berisiko</span>
                </div>
                <div class="daftar-wilayah">
                    <?php foreach ($perKelurahan as $k):
                        $ri = (int)$k['berisiko']; $jm = (int)$k['jumlah']; $am = $jm - $ri;
                        $tip = ['judul' => 'Kel. ' . $k['kelurahan'], 'baris' => [
                            ['warna' => '#b32218', 'teks' => "Berisiko: $ri"],
                            ['warna' => '#5fc084', 'teks' => "Tidak berisiko: $am"],
                        ]];
                    ?>
                        <div class="wilayah" data-tip='<?= e(json_encode($tip)) ?>'>
                            <div class="atas">
                                <span><?= e($k['kelurahan']) ?></span>
                                <b><strong><?= $ri ?></strong> berisiko dari <?= $jm ?></b>
                            </div>
                            <div class="jalur">
                                <?php if ($ri > 0): ?><div style="width:<?= $ri / $maksWilayah * 100 ?>%;background:var(--grafik-risiko)"></div><?php endif; ?>
                                <?php if ($am > 0): ?><div style="width:<?= $am / $maksWilayah * 100 ?>%;background:var(--grafik-aman)"></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <article class="panel lebar-7">
        <div class="panel-kepala">
            <div>
                <h2>Skrining terbaru</h2>
                <p>Lima pemeriksaan terakhir yang masuk</p>
            </div>
            <a class="tautan-kecil" href="data_skrining.php">Lihat semua &rarr;</a>
        </div>
        <div class="panel-isi rapat">
            <table class="tabel-admin">
                <thead>
                    <tr><th>Balita</th><th>Usia</th><th>Tanggal</th><th>Hasil</th></tr>
                </thead>
                <tbody>
                <?php foreach ($terbaru as $r): ?>
                    <tr>
                        <td class="sel-kepala">
                            <span class="sel-utama"><?= e($r['nama_balita']) ?></span>
                            <span class="sel-sub">Kel. <?= e($r['kelurahan']) ?></span>
                        </td>
                        <td data-label="Usia"><?= (int)$r['p4072_anak'] ?> bln</td>
                        <td data-label="Tanggal"><?= formatTanggal($r['tanggal_skrining']) ?></td>
                        <td data-label="Hasil">
                            <span class="lencana <?= $r['hasil'] ? 'lencana-risiko' : 'lencana-aman' ?>">
                                <?= $r['hasil'] ? 'Berisiko' : 'Tidak berisiko' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$terbaru): ?>
                    <tr><td colspan="4" class="kosong">Belum ada skrining yang masuk.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<!-- ===================== Aksi cepat ===================== -->
<section class="kisi-panel">
    <article class="panel lebar-12">
        <div class="panel-kepala"><div><h2>Aksi cepat</h2></div></div>
        <div class="panel-isi">
            <div class="aksi-cepat" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr))">
                <a href="data_skrining.php?hasil=1">
                    <span class="ikon" style="background:var(--merah-bg);color:var(--merah-tx)"><?= ikon('peringatan', 18) ?></span>
                    Tinjau balita berisiko
                    <span class="panah"><?= ikon('panah', 18) ?></span>
                </a>
                <a href="pengguna.php">
                    <span class="ikon"><?= ikon('pengguna', 18) ?></span>
                    Kelola akun pengguna
                    <span class="panah"><?= ikon('panah', 18) ?></span>
                </a>
                <a href="posyandu.php">
                    <span class="ikon"><?= ikon('posyandu', 18) ?></span>
                    Perbarui data Posyandu
                    <span class="panah"><?= ikon('panah', 18) ?></span>
                </a>
            </div>
        </div>
    </article>
</section>

<?php akhiriHalaman(); ?>