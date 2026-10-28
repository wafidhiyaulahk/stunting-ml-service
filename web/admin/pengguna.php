<?php
require_once __DIR__ . '/../helpers/layout.php';
wajibLoginAdmin();

/* ------------------------- Aksi tambah/edit/hapus ------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        // ON DELETE CASCADE ikut menghapus data balita dan skrining miliknya.
        $stmt = $pdo->prepare('DELETE FROM pengguna WHERE id = ?');
        $stmt->execute([(int)$_POST['id']]);
        setPesan('sukses', 'Data pengguna berhasil dihapus.');

    } elseif ($aksi === 'simpan') {
        $id        = (int)($_POST['id'] ?? 0);
        $nama      = trim($_POST['nama'] ?? '');
        $noHp      = normalisasiNoHp($_POST['no_hp'] ?? '');
        $kelurahan = $_POST['kelurahan'] ?? '';
        $rw        = $_POST['rw'] ?? '';
        $rt        = $_POST['rt'] ?? '';
        $detail    = trim($_POST['alamat_detail'] ?? '');
        $sandi     = $_POST['password'] ?? '';

        if ($nama === '' || !preg_match('/^08[0-9]{8,12}$/', $noHp) || $kelurahan === '') {
            setPesan('gagal', 'Nama, nomor HP, dan alamat wajib diisi dengan benar.');
        } else {
            $cek = $pdo->prepare('SELECT id FROM pengguna WHERE no_hp = ? AND id <> ?');
            $cek->execute([$noHp, $id]);

            if ($cek->fetch()) {
                setPesan('gagal', 'Nomor HP sudah dipakai pengguna lain.');
            } elseif ($id > 0) {
                if ($sandi !== '') {
                    $stmt = $pdo->prepare(
                        'UPDATE pengguna SET nama=?, no_hp=?, kelurahan=?, rw=?, rt=?,
                                             alamat_detail=?, password=? WHERE id=?'
                    );
                    $stmt->execute([$nama, $noHp, $kelurahan, $rw, $rt, $detail ?: null,
                                    password_hash($sandi, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE pengguna SET nama=?, no_hp=?, kelurahan=?, rw=?, rt=?,
                                             alamat_detail=? WHERE id=?'
                    );
                    $stmt->execute([$nama, $noHp, $kelurahan, $rw, $rt, $detail ?: null, $id]);
                }
                setPesan('sukses', 'Data pengguna berhasil diperbarui.');
            } else {
                if (strlen($sandi) < 6) {
                    setPesan('gagal', 'Kata sandi minimal 6 karakter untuk pengguna baru.');
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO pengguna (nama, no_hp, password, kelurahan, rw, rt, alamat_detail)
                         VALUES (?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([$nama, $noHp, password_hash($sandi, PASSWORD_DEFAULT),
                                    $kelurahan, $rw, $rt, $detail ?: null]);
                    setPesan('sukses', 'Pengguna baru berhasil ditambahkan.');
                }
            }
        }
    }
    header('Location: pengguna.php');
    exit;
}

$daftar = $pdo->query(
    'SELECT p.*, (SELECT COUNT(*) FROM balita b WHERE b.pengguna_id = p.id) AS jumlah_balita
     FROM pengguna p ORDER BY p.created_at DESC'
)->fetchAll();
$daftarKelurahan = ambilKelurahan($pdo);

mulaiHalaman('Kelola Pengguna', 'admin', 'pengguna');
?>
<div class="kepala-halaman">
    <div>
        <h1>Kelola Pengguna</h1>
        <p>Akun orang tua atau kader yang terdaftar. Kata sandi disimpan terenkripsi dan hanya bisa diatur ulang.</p>
    </div>
    <button class="tombol" onclick="formPengguna()"><?= ikon('tambah', 18) ?> Tambah Pengguna</button>
</div>

<?= tampilkanPesan() ?>

<section class="panel">
    <div class="alat-tabel">
        <div class="cari">
            <?= ikon('cari', 18) ?>
            <input type="text" id="saring-tabel" placeholder="Cari nama, nomor HP, atau kelurahan…" aria-label="Cari pengguna">
        </div>
        <span style="font-size:.82rem;color:var(--abu-500)"><b style="color:var(--abu-700)"><?= count($daftar) ?></b> pengguna</span>
    </div>

    <table class="tabel-admin" id="tabel-data">
        <thead>
            <tr><th>Pengguna</th><th>Nomor HP</th><th>Alamat</th><th>Balita</th><th>Terdaftar</th><th style="text-align:right">Aksi</th></tr>
        </thead>
        <tbody>
        <?php foreach ($daftar as $p): $aman = array_diff_key($p, ['password' => 1]); ?>
            <tr>
                <td class="sel-kepala">
                    <span class="sel-utama"><?= e($p['nama']) ?></span>
                </td>
                <td data-label="Nomor HP"><?= e($p['no_hp']) ?></td>
                <td data-label="Alamat">
                    Kel. <?= e($p['kelurahan']) ?>, RW <?= e($p['rw']) ?>/RT <?= e($p['rt']) ?>
                    <?= $p['alamat_detail'] ? '<span class="sel-sub">' . e($p['alamat_detail']) . '</span>' : '' ?>
                </td>
                <td data-label="Balita"><?= (int)$p['jumlah_balita'] ?> anak</td>
                <td data-label="Terdaftar"><?= formatTanggal($p['created_at']) ?></td>
                <td class="sel-aksi">
                    <div class="aksi-sel" style="justify-content:flex-end">
                        <button class="tombol tombol-kecil tombol-putih"
                                onclick='formPengguna(<?= json_encode($aman, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                        <form method="post" onsubmit="return confirm('Hapus pengguna ini? Seluruh data balita dan riwayat skriningnya ikut terhapus.')">
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
            <tr><td colspan="6" class="kosong"><?= ikon('kosong', 36) ?><br>Belum ada pengguna terdaftar.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<!-- Pop-up formulir tambah/edit -->
<div class="modal-latar" id="modal-pengguna">
    <div class="modal">
        <div class="modal-kepala">
            <h3 id="judul-modal">Tambah Pengguna</h3>
            <button class="modal-tutup" onclick="tutupModal('modal-pengguna')">&times;</button>
        </div>
        <div class="modal-isi">
            <form method="post" id="form-pengguna">
                <input type="hidden" name="csrf" value="<?= tokenCsrf() ?>">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id" id="f_id" value="0">

                <div class="bidang">
                    <label for="f_nama">Nama Lengkap</label>
                    <input type="text" id="f_nama" name="nama" required>
                </div>
                <div class="bidang">
                    <label for="f_no_hp">Nomor HP</label>
                    <input type="tel" id="f_no_hp" name="no_hp" required placeholder="08xxxxxxxxxx">
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
                        <label for="alamat_rw">RW</label>
                        <select id="alamat_rw" name="rw" required disabled><option value="">Pilih kelurahan dahulu</option></select>
                    </div>
                    <div class="bidang">
                        <label for="alamat_rt">RT</label>
                        <select id="alamat_rt" name="rt" required disabled><option value="">Pilih RW dahulu</option></select>
                    </div>
                </div>
                <div class="bidang">
                    <label for="f_detail">Nama Jalan / Patokan</label>
                    <input type="text" id="f_detail" name="alamat_detail">
                </div>
                <div class="bidang">
                    <label for="f_sandi">Kata Sandi</label>
                    <span class="bantuan" id="ket-sandi">Minimal 6 karakter.</span>
                    <input type="password" id="f_sandi" name="password">
                </div>

                <button type="submit" class="tombol tombol-blok">Simpan</button>
            </form>
        </div>
    </div>
</div>

<script>
function formPengguna(data) {
    const baru = !data;
    document.getElementById('judul-modal').textContent = baru ? 'Tambah Pengguna' : 'Edit Pengguna';
    document.getElementById('f_id').value      = baru ? 0 : data.id;
    document.getElementById('f_nama').value    = baru ? '' : data.nama;
    document.getElementById('f_no_hp').value   = baru ? '' : data.no_hp;
    document.getElementById('f_detail').value  = baru ? '' : (data.alamat_detail || '');
    document.getElementById('f_sandi').value   = '';
    document.getElementById('f_sandi').required = baru;
    document.getElementById('ket-sandi').textContent = baru
        ? 'Minimal 6 karakter.'
        : 'Kosongkan bila kata sandi tidak diubah.';

    // siapkanWilayah sudah mengisi kelurahan, RW, dan RT sekaligus bila
    // nilai terpilih dikirim, sehingga tidak perlu memicu event change manual.
    if (baru) document.getElementById('alamat_kelurahan').value = '';
    siapkanWilayah('alamat', '<?= BASE_URL ?>', baru ? {} :
        { kelurahan: data.kelurahan, rw: data.rw, rt: data.rt });

    bukaModal('modal-pengguna');
}
</script>
<?php akhiriHalaman(); ?>
