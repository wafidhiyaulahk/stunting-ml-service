<?php
/**
 * Kumpulan fungsi pembantu yang dipakai di seluruh aplikasi.
 */
require_once __DIR__ . '/../config/koneksi.php';

/* ------------------------------------------------------------------ */
/* Keamanan dan sesi                                                   */
/* ------------------------------------------------------------------ */

function e(?string $teks): string
{
    return htmlspecialchars($teks ?? '', ENT_QUOTES, 'UTF-8');
}

function wajibLoginPengguna(): void
{
    if (empty($_SESSION['pengguna_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function wajibLoginAdmin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/auth/login_admin.php');
        exit;
    }
}

function setPesan(string $tipe, string $teks): void
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'teks' => $teks];
}

function tampilkanPesan(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert alert-' . e($f['tipe']) . '">' . e($f['teks']) . '</div>';
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function periksaCsrf(): void
{
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '_')) {
        die('Permintaan tidak sah (CSRF).');
    }
}

/* ------------------------------------------------------------------ */
/* Utilitas umum                                                       */
/* ------------------------------------------------------------------ */

/** Normalisasi nomor HP: 0812..., +62812..., 62812... menjadi 0812... */
function normalisasiNoHp(string $noHp): string
{
    $digit = preg_replace('/\D/', '', $noHp);
    if (str_starts_with($digit, '62')) {
        $digit = '0' . substr($digit, 2);
    }
    return $digit;
}

/** Hitung usia balita dalam bulan penuh pada tanggal tertentu. */
function usiaDalamBulan(string $tanggalLahir, ?string $tanggalAcuan = null): int
{
    $lahir  = new DateTime($tanggalLahir);
    $acuan  = new DateTime($tanggalAcuan ?? 'today');
    $selisih = $lahir->diff($acuan);
    return ($selisih->y * 12) + $selisih->m;
}

function formatTanggal(string $tanggal): string
{
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli',
              'Agustus','September','Oktober','November','Desember'];
    $t = new DateTime($tanggal);
    return $t->format('j') . ' ' . $bulan[(int)$t->format('n')] . ' ' . $t->format('Y');
}

function ambilKelurahan(PDO $pdo): array
{
    return $pdo->query('SELECT DISTINCT kelurahan FROM wilayah ORDER BY kelurahan')
               ->fetchAll(PDO::FETCH_COLUMN);
}

/* ------------------------------------------------------------------ */
/* Pemanggilan model KNN melalui ML Service                            */
/* ------------------------------------------------------------------ */

/**
 * Kirim ketujuh variabel ke Flask dan kembalikan hasil prediksi.
 *
 * @param array $fitur ['P4072_anak'=>..,'P404_anak'=>..,'P1509'=>..,
 *                      'P810'=>..,'P916C'=>..,'P501'=>..,'MPASI_DIVERSITY'=>..]
 * @return array ['sukses'=>bool, ...]
 */
function prediksiStunting(array $fitur): array
{
    $ch = curl_init(ML_SERVICE_URL . '/predict');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Token: ' . ML_API_TOKEN,
        ],
        CURLOPT_POSTFIELDS     => json_encode($fitur),
    ]);

    $respons = curl_exec($ch);
    $kodeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($ch);
    curl_close($ch);

    if ($respons === false) {
        return ['sukses' => false, 'pesan' =>
            'Layanan model tidak dapat dihubungi. Pastikan ml-service sudah dijalankan '
            . '(python app.py). Detail: ' . $errorCurl];
    }

    $data = json_decode($respons, true);
    if (!is_array($data)) {
        return ['sukses' => false, 'pesan' => 'Balasan layanan model tidak dapat dibaca.'];
    }
    if ($kodeHttp !== 200) {
        return ['sukses' => false, 'pesan' => $data['pesan'] ?? 'Prediksi gagal.'];
    }
    return $data;
}

/** Cek apakah ML service hidup, dipakai untuk menampilkan indikator status. */
function statusMlService(): bool
{
    // Disimpan sekali per permintaan halaman agar tidak memanggil layanan berulang kali.
    static $status = null;
    if ($status !== null) {
        return $status;
    }
    $ch = curl_init(ML_SERVICE_URL . '/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
    ]);
    $hasil = curl_exec($ch);
    $kode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status = ($hasil !== false && $kode === 200);
}
