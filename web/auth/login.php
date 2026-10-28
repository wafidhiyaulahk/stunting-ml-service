<?php
require_once __DIR__ . '/../helpers/layout.php';

$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $noHp  = normalisasiNoHp($_POST['no_hp'] ?? '');
    $sandi = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM pengguna WHERE no_hp = ?');
    $stmt->execute([$noHp]);
    $pengguna = $stmt->fetch();

    // Pesan galat sengaja dibuat sama untuk nomor salah maupun sandi salah,
    // agar tidak membocorkan nomor mana yang terdaftar.
    if ($pengguna && password_verify($sandi, $pengguna['password'])) {
        session_regenerate_id(true);
        $_SESSION['pengguna_id']   = $pengguna['id'];
        $_SESSION['pengguna_nama'] = $pengguna['nama'];
        header('Location: ' . BASE_URL . '/user/index.php');
        exit;
    }
    $galat = 'Nomor HP atau kata sandi salah.';
}

mulaiHalaman('Masuk');
?>
<div class="wadah-sempit">
    <div class="kartu">
        <h2>Masuk ke Akun Anda</h2>
        <p class="bantuan">Gunakan nomor HP yang Anda daftarkan.</p>

        <?= tampilkanPesan() ?>
        <?php if ($galat): ?><div class="alert alert-gagal"><?= e($galat) ?></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= tokenCsrf() ?>">
            <div class="bidang">
                <label for="no_hp">Nomor HP</label>
                <input type="tel" id="no_hp" name="no_hp" required placeholder="08xxxxxxxxxx" autofocus>
            </div>
            <div class="bidang">
                <label for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="tombol tombol-blok">Masuk</button>
        </form>

        <p style="text-align:center">Belum punya akun? <a href="registrasi.php">Daftar di sini</a></p>
        <p style="text-align:center;margin-bottom:0;font-size:.85rem">
            <a href="login_admin.php">Masuk sebagai admin</a>
        </p>
    </div>
</div>
<?php akhiriHalaman(); ?>
