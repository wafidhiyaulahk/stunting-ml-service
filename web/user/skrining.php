<?php
require_once __DIR__ . '/../helpers/layout.php';
require_once __DIR__ . '/../helpers/variabel.php';
wajibLoginPengguna();

$idPengguna = $_SESSION['pengguna_id'];

// Balita yang sudah pernah didaftarkan, agar pengguna tidak perlu mengetik ulang.
$stmt = $pdo->prepare('SELECT * FROM balita WHERE pengguna_id = ? ORDER BY nama_balita');
$stmt->execute([$idPengguna]);
$daftarBalita = $stmt->fetchAll();

$daftarKelurahan = ambilKelurahan($pdo);
$layananHidup    = statusMlService();

$fJk     = fiturVariabel('P404_anak');
$fP916c  = fiturVariabel('P916C');
$fP501   = fiturVariabel('P501');
$fP810   = fiturVariabel('P810');
$fBerat  = fiturVariabel('P1509');
$makanan = kelompokMakanan();

mulaiHalaman('Skrining', 'pengguna', 'skrining');
?>
<div class="wadah">

    <div class="langkah">
        <div class="aktif">1. Data anak</div>
        <div class="aktif">2. Kondisi anak dan keluarga</div>
        <div>3. Hasil dan saran</div>
    </div>

    <?= tampilkanPesan() ?>

    <?php if (!$layananHidup): ?>
        <div class="alert alert-gagal">
            Layanan penghitung KNN sedang tidak aktif, sehingga skrining belum dapat diproses.
            Jalankan lebih dahulu <code>python app.py</code> pada folder <code>ml-service</code>.
        </div>
    <?php endif; ?>

    <form method="post" action="proses_skrining.php" id="form-skrining">
        <input type="hidden" name="csrf" value="<?= tokenCsrf() ?>">

        <!-- ============ Bagian 1: identitas balita ============ -->
        <div class="kartu">
            <h2>1. Data Anak</h2>

            <?php if ($daftarBalita): ?>
            <div class="bidang">
                <label for="balita_id">Pilih anak yang sudah terdaftar</label>
                <span class="bantuan">Pilih "Tambah anak baru" bila anak belum pernah diskrining.</span>
                <select id="balita_id" name="balita_id">
                    <option value="baru">+ Tambah anak baru</option>
                    <?php foreach ($daftarBalita as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"
                                data-balita='<?= e(json_encode($b)) ?>'>
                            <?= e($b['nama_balita']) ?> (lahir <?= formatTanggal($b['tanggal_lahir']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div id="kotak-data-balita">
                <div class="grid-2">
                    <div class="bidang">
                        <label for="nama_balita">Nama Anak</label>
                        <input type="text" id="nama_balita" name="nama_balita" required>
                    </div>
                    <div class="bidang">
                        <label for="tanggal_lahir">Tanggal Lahir Anak</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" required
                               max="<?= date('Y-m-d') ?>">
                        <span class="bantuan" id="info_usia"></span>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="bidang">
                        <label for="nama_ibu">Nama Ibu</label>
                        <input type="text" id="nama_ibu" name="nama_ibu" required>
                    </div>
                    <div class="bidang">
                        <label for="nama_ayah">Nama Ayah</label>
                        <input type="text" id="nama_ayah" name="nama_ayah" required>
                    </div>
                </div>

                <div class="bidang">
                    <label for="no_wa">Nomor WhatsApp yang Dapat Dihubungi</label>
                    <span class="bantuan">Dipakai petugas Posyandu bila diperlukan pendampingan.</span>
                    <input type="tel" id="no_wa" name="no_wa" required placeholder="08xxxxxxxxxx">
                </div>

                <div class="bidang">
                    <label for="alamat_kelurahan">Kelurahan</label>
                    <select id="alamat_kelurahan" name="alamat_kelurahan" required>
                        <option value="">Pilih kelurahan</option>
                        <?php foreach ($daftarKelurahan as $k): ?>
                            <option value="<?= e($k) ?>"><?= e($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="bidang">
                        <label for="alamat_rt">RT</label>
                        <input type="text" id="alamat_rt" name="alamat_rt" required inputmode="numeric" maxlength="3" placeholder="contoh: 03">
                    </div>
                    <div class="bidang">
                        <label for="alamat_rw">RW</label>
                        <input type="text" id="alamat_rw" name="alamat_rw" required inputmode="numeric" maxlength="3" placeholder="contoh: 05">
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ Bagian 2: variabel model ============ -->
        <div class="kartu">
            <h2>2. Kondisi Anak dan Keluarga</h2>
            <p class="bantuan">Isilah sesuai keadaan sebenarnya. Tujuh keterangan di bawah ini
               adalah data yang dipakai algoritma KNN untuk menghitung risiko.</p>

            <!-- P1509 : berat badan -->
            <div class="bidang">
                <label for="berat_badan"><?= e($fBerat['label']) ?> (kg)</label>
                <span class="bantuan"><?= e($fBerat['pertanyaan_awam']) ?></span>
                <input type="number" id="berat_badan" name="berat_badan" step="0.1"
                       min="<?= e($fBerat['min']) ?>" max="<?= e($fBerat['max']) ?>" required>
            </div>

            <!-- P404_anak : biner -->
            <div class="bidang">
                <label><?= e($fJk['label']) ?></label>
                <span class="bantuan"><?= e($fJk['pertanyaan_awam']) ?></span>
                <div class="pilihan-kotak">
                    <?php foreach ($fJk['opsi'] as $o): ?>
                        <label><input type="radio" name="p404_anak" value="<?= (int)$o['nilai'] ?>" required>
                               <span><?= e($o['teks']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- MPASI_DIVERSITY : dihitung dari centang -->
            <div class="bidang">
                <label><?= e(fiturVariabel('MPASI_DIVERSITY')['label']) ?></label>
                <span class="bantuan"><?= e(fiturVariabel('MPASI_DIVERSITY')['pertanyaan_awam']) ?>
                    Bila anak masih menyusu ASI saja, cukup centang pilihan ASI.</span>
                <div class="pilihan-kotak">
                    <?php foreach ($makanan as $i => $m): ?>
                        <label><input type="checkbox" name="makanan[]" value="<?= $i ?>">
                               <span><?= e($m) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <p class="bantuan" id="skor_makanan" style="margin-top:.5rem"></p>
            </div>

            <!-- P916C : biner -->
            <div class="bidang">
                <label><?= e($fP916c['label']) ?></label>
                <span class="bantuan"><?= e($fP916c['pertanyaan_awam']) ?></span>
                <div class="pilihan-kotak">
                    <?php foreach ($fP916c['opsi'] as $o): ?>
                        <label><input type="radio" name="p916c" value="<?= (int)$o['nilai'] ?>" required>
                               <span><?= e($o['teks']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- P810 : riwayat TBC. Ditampilkan sebagai pilihan kotak, bukan
                 dropdown, karena pertanyaan riwayat penyakit lebih mudah dijawab
                 bila seluruh pilihannya langsung terlihat. -->
            <div class="bidang">
                <label><?= e($fP810['label']) ?></label>
                <span class="bantuan"><?= e($fP810['pertanyaan_awam']) ?></span>
                <div class="pilihan-kotak">
                    <?php foreach ($fP810['opsi'] as $o): ?>
                        <label><input type="radio" name="p810" value="<?= (int)$o['nilai'] ?>" required>
                               <span><?= e($o['teks']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- P501 : kualitas air minum -->
            <div class="bidang">
                <label for="p501"><?= e($fP501['label']) ?></label>
                <span class="bantuan"><?= e($fP501['pertanyaan_awam']) ?></span>
                <select id="p501" name="p501" required>
                    <option value="">Pilih salah satu</option>
                    <?php foreach ($fP501['opsi'] as $o): ?>
                        <option value="<?= (int)$o['nilai'] ?>"><?= e($o['teks']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="text-align:center">
            <button type="submit" class="tombol" <?= $layananHidup ? '' : 'disabled' ?>>
                Proses Skrining
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Bila pengguna memilih anak yang sudah terdaftar, isian identitas
    // diisi otomatis lalu dikunci agar data balita tetap konsisten.
    const pilih = document.getElementById('balita_id');
    if (!pilih) return;
    const kotak = document.getElementById('kotak-data-balita');

    pilih.addEventListener('change', () => {
        const opsi = pilih.selectedOptions[0];
        const isian = kotak.querySelectorAll('input, select');

        if (pilih.value === 'baru') {
            isian.forEach(el => { el.readOnly = false; el.disabled = false; el.value = ''; });
            document.getElementById('info_usia').textContent = '';
            return;
        }

        const b = JSON.parse(opsi.getAttribute('data-balita'));
        document.getElementById('nama_balita').value      = b.nama_balita;
        document.getElementById('tanggal_lahir').value    = b.tanggal_lahir;
        document.getElementById('nama_ibu').value         = b.nama_ibu;
        document.getElementById('nama_ayah').value        = b.nama_ayah;
        document.getElementById('no_wa').value            = b.no_wa;
        document.getElementById('alamat_kelurahan').value = b.kelurahan || '';
        document.getElementById('alamat_rt').value        = b.rt || '';
        document.getElementById('alamat_rw').value        = b.rw || '';
        isian.forEach(el => { if (el.tagName === 'INPUT') el.readOnly = true; });
        document.getElementById('alamat_kelurahan').disabled = true;
        document.getElementById('tanggal_lahir').dispatchEvent(new Event('change'));

        // Jenis kelamin ikut terisi karena tersimpan bersama data balita.
        const jk = document.querySelector('input[name="p404_anak"][value="' + b.jenis_kelamin + '"]');
        if (jk) jk.checked = true;
    });
});
</script>
<?php akhiriHalaman(); ?>