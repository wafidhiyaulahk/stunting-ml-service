<?php
require_once __DIR__ . '/../helpers/layout.php';
wajibLoginPengguna();

$stmt = $pdo->prepare(
    'SELECT s.id, s.hasil, s.tanggal_skrining, s.p4072_anak, s.p1509,
            b.nama_balita
     FROM skrining s JOIN balita b ON b.id = s.balita_id
     WHERE s.pengguna_id = ?
     ORDER BY s.tanggal_skrining DESC'
);
$stmt->execute([$_SESSION['pengguna_id']]);
$daftar = $stmt->fetchAll();

mulaiHalaman('Riwayat Skrining', 'pengguna', 'riwayat');
?>
<div class="wadah">
    <?= tampilkanPesan() ?>

    <div class="kartu">
        <h2>Riwayat Skrining</h2>
        <p class="bantuan">Seluruh skrining yang pernah dilakukan melalui akun ini,
           termasuk untuk anak yang berbeda.</p>

        <?php if (!$daftar): ?>
            <div class="alert alert-info">
                Belum ada riwayat skrining. <a href="skrining.php">Mulai skrining pertama Anda</a>.
            </div>
        <?php else: ?>
            <div class="tabel-bungkus">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Balita</th>
                            <th>Tanggal Skrining</th>
                            <th>Usia</th>
                            <th>Berat</th>
                            <th>Hasil Deteksi</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($daftar as $r): ?>
                        <tr>
                            <td><strong><?= e($r['nama_balita']) ?></strong></td>
                            <td><?= formatTanggal($r['tanggal_skrining']) ?></td>
                            <td><?= (int)$r['p4072_anak'] ?> bln</td>
                            <td><?= e($r['p1509']) ?> kg</td>
                            <td>
                                <span class="lencana <?= $r['hasil'] ? 'lencana-risiko' : 'lencana-aman' ?>">
                                    <?= $r['hasil'] ? 'Berisiko Stunting' : 'Tidak Berisiko' ?>
                                </span>
                            </td>
                            <td>
                                <a class="tombol tombol-kecil tombol-putih"
                                   href="hasil.php?id=<?= (int)$r['id'] ?>">Lihat Saran</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div style="text-align:center">
        <a href="skrining.php" class="tombol">Skrining Baru</a>
    </div>
</div>
<?php akhiriHalaman(); ?>
