<?php
require_once __DIR__ . '/../helpers/layout.php';

$galat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();

    $nama      = trim($_POST['nama'] ?? '');
    $noHp      = normalisasiNoHp($_POST['no_hp'] ?? '');
    $sandi     = $_POST['password'] ?? '';
    $ulangi    = $_POST['ulangi'] ?? '';
    $kelurahan = $_POST['alamat_kelurahan'] ?? '';
    $rw        = trim($_POST['alamat_rw'] ?? '');
    $rt        = trim($_POST['alamat_rt'] ?? '');
    $detail    = trim($_POST['alamat_detail'] ?? '');

    if ($nama === '')                              $galat[] = 'Nama lengkap wajib diisi.';
    if (!preg_match('/^08[0-9]{8,12}$/', $noHp))   $galat[] = 'Nomor HP tidak valid. Gunakan format 08xxxxxxxxxx.';
    if (strlen($sandi) < 6)                        $galat[] = 'Kata sandi minimal 6 karakter.';
    if ($sandi !== $ulangi)                        $galat[] = 'Ulangi kata sandi tidak sama.';
    if ($kelurahan === '' || $rt === '' || $rw === '') $galat[] = 'Alamat (kelurahan, RT, RW) wajib diisi.';

    if (!$galat) {
        $cek = $pdo->prepare('SELECT id FROM pengguna WHERE no_hp = ?');
        $cek->execute([$noHp]);
        if ($cek->fetch()) {
            $galat[] = 'Nomor HP tersebut sudah terdaftar. Silakan masuk.';
        }
    }

    if (!$galat) {
        $stmt = $pdo->prepare(
            'INSERT INTO pengguna (nama, no_hp, password, kelurahan, rw, rt, alamat_detail)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $nama, $noHp, password_hash($sandi, PASSWORD_DEFAULT),
            $kelurahan, $rw, $rt, $detail ?: null,
        ]);
        setPesan('sukses', 'Registrasi berhasil. Silakan masuk dengan nomor HP dan kata sandi Anda.');
        header('Location: login.php');
        exit;
    }
}

mulaiHalaman('Registrasi');
$daftarKelurahan = ambilKelurahan($pdo);
?>
<div class="wadah-sempit">
    <div class="kartu">
        <h2>Buat Akun Baru</h2>
        <p class="bantuan">Satu akun dapat digunakan untuk memantau lebih dari satu anak.</p>

        <?php foreach ($galat as $g): ?>
            <div class="alert alert-gagal"><?= e($g) ?></div>
        <?php endforeach; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= tokenCsrf() ?>">

            <div class="bidang">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" required value="<?= e($_POST['nama'] ?? '') ?>">
            </div>

            <div class="bidang">
                <label for="no_hp">Nomor HP</label>
                <span class="bantuan">Nomor ini juga dipakai untuk masuk ke aplikasi.</span>
                <input type="tel" id="no_hp" name="no_hp" required placeholder="08xxxxxxxxxx"
                       value="<?= e($_POST['no_hp'] ?? '') ?>">
            </div>

            <div class="bidang">
                <label for="alamat_kelurahan">Kelurahan</label>
                <select id="alamat_kelurahan" name="alamat_kelurahan" required>
                    <option value="">Pilih kelurahan</option>
                    <?php foreach ($daftarKelurahan as $k): ?>
                        <option value="<?= e($k) ?>" <?= ($_POST['alamat_kelurahan'] ?? '') === $k ? 'selected' : '' ?>><?= e($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid-2">
                <div class="bidang">
                    <label for="alamat_rt">RT</label>
                    <input type="text" id="alamat_rt" name="alamat_rt" required inputmode="numeric" maxlength="3"
                           placeholder="contoh: 03" value="<?= e($_POST['alamat_rt'] ?? '') ?>">
                </div>
                <div class="bidang">
                    <label for="alamat_rw">RW</label>
                    <input type="text" id="alamat_rw" name="alamat_rw" required inputmode="numeric" maxlength="3"
                           placeholder="contoh: 05" value="<?= e($_POST['alamat_rw'] ?? '') ?>">
                </div>
            </div>

            <div class="bidang">
                <label for="alamat_detail">Nama Jalan / Patokan <span class="bantuan" style="display:inline">(boleh dikosongkan)</span></label>
                <input type="text" id="alamat_detail" name="alamat_detail" value="<?= e($_POST['alamat_detail'] ?? '') ?>">
            </div>

            <div class="grid-2">
                <div class="bidang">
                    <label for="password">Kata Sandi</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
                <div class="bidang">
                    <label for="ulangi">Ulangi Kata Sandi</label>
                    <input type="password" id="ulangi" name="ulangi" required minlength="6">
                </div>
            </div>

            <button type="submit" class="tombol tombol-blok">Daftar Sekarang</button>
        </form>

        <p style="text-align:center;margin-bottom:0">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </div>
</div>
<?php akhiriHalaman(); ?>