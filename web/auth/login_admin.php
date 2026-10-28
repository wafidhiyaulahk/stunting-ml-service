<?php
require_once __DIR__ . '/../helpers/layout.php';

$galat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $noHp  = normalisasiNoHp($_POST['no_hp'] ?? '');
    $sandi = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM admin WHERE no_hp = ?');
    $stmt->execute([$noHp]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($sandi, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_nama'] = $admin['nama'];
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    }
    $galat = 'Nomor HP atau kata sandi salah.';
}

mulaiHalaman('Masuk Admin');
?>
<div class="wadah-sempit">
    <div class="kartu">
        <h2>Masuk Admin</h2>
        <p class="bantuan">Halaman khusus petugas <?= e(NAMA_INSTANSI) ?>.</p>

        <?php if ($galat): ?><div class="alert alert-gagal"><?= e($galat) ?></div><?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= tokenCsrf() ?>">
            <div class="bidang">
                <label for="no_hp">Nomor HP</label>
                <input type="tel" id="no_hp" name="no_hp" required autofocus>
            </div>
            <div class="bidang">
                <label for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="tombol tombol-blok">Masuk</button>
        </form>

        <p style="text-align:center;margin-bottom:0;font-size:.85rem">
            <a href="login.php">Kembali ke halaman pengguna</a>
        </p>
    </div>
</div>
<?php akhiriHalaman(); ?>
