<?php
require_once __DIR__ . '/../helpers/layout.php';
wajibLoginAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $stmt = $pdo->prepare('DELETE FROM posyandu WHERE id = ?');
        $stmt->execute([(int)$_POST['id']]);
        setPesan('sukses', 'Data Posyandu berhasil dihapus.');

    } elseif ($aksi === 'simpan') {
        $id        = (int)($_POST['id'] ?? 0);
        $nama      = trim($_POST['nama_posyandu'] ?? '');
        $kelurahan = $_POST['kelurahan'] ?? '';
        $rw        = trim($_POST['rw'] ?? '');
        $rt        = trim($_POST['rt'] ?? '');
        $alamat    = trim($_POST['alamat'] ?? '');

        // Posyandu cukup berisi nama dan alamat; nama kader dan nomor HP
        // tidak lagi diminta karena data tersebut sulit diperoleh untuk
        // setiap Posyandu. RT dan RW diisi bebas oleh petugas.
        if ($nama === '' || $kelurahan === '' || $alamat === '') {
            setPesan('gagal', 'Nama Posyandu, kelurahan, dan alamat wajib dilengkapi.');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE posyandu SET nama_posyandu=?, kelurahan=?, rw=?, rt=?, alamat=? WHERE id=?'
            );
            $stmt->execute([$nama, $kelurahan, $rw ?: null, $rt ?: null, $alamat, $id]);
            setPesan('sukses', 'Data Posyandu berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO posyandu (nama_posyandu, kelurahan, rw, rt, alamat)
                 VALUES (?,?,?,?,?)'
            );
            $stmt->execute([$nama, $kelurahan, $rw ?: null, $rt ?: null, $alamat]);
            setPesan('sukses', 'Posyandu baru berhasil ditambahkan.');
        }
    }
    header('Location: posyandu.php');
    exit;
}

$daftar = $pdo->query('SELECT * FROM posyandu ORDER BY kelurahan, nama_posyandu')->fetchAll();
$daftarKelurahan = ambilKelurahan($pdo);

mulaiHalaman('Kelola Posyandu', 'admin', 'posyandu');
?>
<div class="kepala-halaman">
    <div>
        <h1>Kelola Posyandu</h1>
        <p>Daftar Posyandu di wilayah kerja <?= e(NAMA_INSTANSI) ?> yang ditampilkan kepada pengguna sebagai rujukan.</p>
    </div>
    <button class="tombol" onclick="formPosyandu()"><?= ikon('tambah', 18) ?> Tambah Posyandu</button>
</div>

<?= tampilkanPesan() ?>

<section class="panel">
    <div class="alat-tabel">
        <div class="cari">
            <?= ikon('cari', 18) ?>
            <input type="text" id="saring-tabel" placeholder="Cari nama Posyandu, kelurahan, atau alamat…" aria-label="Cari Posyandu">
        </div>
        <span style="font-size:.82rem;color:var(--abu-500)"><b style="color:var(--abu-700)"><?= count($daftar) ?></b> Posyandu</span>
    </div>

    <table class="tabel-admin" id="tabel-data">
        <thead>
            <tr><th>Posyandu</th><th>Kelurahan</th><th>Alamat</th><th style="text-align:right">Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($daftar as $p): ?>
            <tr>
                <td class="sel-kepala">
                    <span class="sel-utama"><?= e($p['nama_posyandu']) ?></span>
                </td>
                <td data-label="Kelurahan">
                    <?= e($p['kelurahan']) ?>
                    <?= $p['rw'] || $p['rt'] ? '<span class="sel-sub">RT ' . e($p['rt']) . ' / RW ' . e($p['rw']) . '</span>' : '' ?>
                </td>
                <td data-label="Alamat"><?= e($p['alamat']) ?></td>
                <td class="sel-aksi">
                    <div class="aksi-sel" style="justify-content:flex-end">
                        <button class="tombol tombol-kecil tombol-putih"
                                onclick='formPosyandu(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                        <form method="post" onsubmit="return confirm('Hapus Posyandu ini?')">
                            <input type="hidden" name="csrf" value="<?= tokenCsrf() ?>">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="tombol tombol-kecil tombol-merah">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$daftar): ?>
            <tr><td colspan="4" class="kosong"><?= ikon('kosong', 36) ?><br>Belum ada data Posyandu.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<div class="modal-latar" id="modal-posyandu">
    <div class="modal">
        <div class="modal-kepala">
            <h3 id="judul-modal">Tambah Posyandu</h3>
            <button class="modal-tutup" onclick="tutupModal('modal-posyandu')">&times;</button>
        </div>
        <div class="modal-isi">
            <form method="post">
                <input type="hidden" name="csrf" value="<?= tokenCsrf() ?>">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id" id="f_id" value="0">

                <div class="bidang">
                    <label for="f_nama">Nama Posyandu</label>
                    <input type="text" id="f_nama" name="nama_posyandu" required>
                </div>
                <div class="bidang">
                    <label for="alamat_kelurahan">Kelurahan</label>
                    <select id="alamat_kelurahan" name="kelurahan" required>
                        <option value="">Pilih kelurahan</option>
                        <?php foreach ($daftarKelurahan as $k): ?>
                            <option value="<?= e($k) ?>"><?= e($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="bidang">
                        <label for="f_rt">RT</label>
                        <input type="text" id="f_rt" name="rt" inputmode="numeric" maxlength="4" placeholder="contoh: 03">
                    </div>
                    <div class="bidang">
                        <label for="f_rw">RW</label>
                        <input type="text" id="f_rw" name="rw" inputmode="numeric" maxlength="4" placeholder="contoh: 05">
                    </div>
                </div>
                <div class="bidang">
                    <label for="f_alamat">Alamat Lengkap</label>
                    <input type="text" id="f_alamat" name="alamat" required>
                </div>

                <button type="submit" class="tombol tombol-blok">Simpan</button>
            </form>
        </div>
    </div>
</div>

<script>
function formPosyandu(data) {
    const baru = !data;
    document.getElementById('judul-modal').textContent = baru ? 'Tambah Posyandu' : 'Edit Posyandu';
    document.getElementById('f_id').value     = baru ? 0 : data.id;
    document.getElementById('f_nama').value   = baru ? '' : data.nama_posyandu;
    document.getElementById('f_alamat').value = baru ? '' : data.alamat;
    document.getElementById('alamat_kelurahan').value = baru ? '' : data.kelurahan;
    document.getElementById('f_rt').value     = baru ? '' : (data.rt || '');
    document.getElementById('f_rw').value     = baru ? '' : (data.rw || '');

    bukaModal('modal-posyandu');
}
</script>
<?php akhiriHalaman(); ?>