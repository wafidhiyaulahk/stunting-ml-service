<?php
/**
 * Penyusun saran gizi.
 *
 * Saran disusun berlapis: kesimpulan, penjelasan kondisi berat badan menurut
 * umur, saran pemberian makan sesuai kelompok usia, saran keragaman makanan,
 * lalu tindak lanjut. Isinya menyesuaikan kondisi tiap balita, bukan teks
 * seragam untuk semua hasil.
 *
 * CATATAN ILMIAH:
 * Label yang dipelajari model berasal dari Z-score tinggi badan menurut umur
 * (TB/U), yaitu stunting bila kurang dari -2 SD. Tinggi badan dipakai untuk
 * menentukan label saat pelatihan, BUKAN sebagai variabel masukan. Justru di
 * situlah nilai sistem ini: risiko stunting ditaksir tanpa mengharuskan orang
 * tua mengukur sendiri tinggi badan anaknya.
 *
 * Ambang berat badan menurut umur (BB/U) di bawah ini hanyalah penanda
 * TAMBAHAN untuk memperkaya kalimat saran, bukan penentu hasil. Penentu hasil
 * tetap keluaran model KNN.
 */

/**
 * Ambang WHO berat badan menurut umur (kg) pada usia jangkar.
 *
 * $sd = 2 mengambil garis -2 SD, yaitu batas bawah; di bawah garis ini anak
 * digolongkan berat badan kurang. $sd = 1 mengambil garis -1 SD, dipakai untuk
 * mengenali anak yang angkanya memang belum kurang tetapi masih menempel di
 * pita terbawah, sehingga kalimat saran tidak terdengar terlalu melegakan.
 */
function ambangBeratWHO(int $usiaBulan, int $jenisKelamin, int $sd = 2): float
{
    // jenisKelamin: 0 = laki-laki, 1 = perempuan
    $lk2 = [0 => 2.5, 6 => 6.4, 12 => 7.7, 18 => 8.8, 24 => 9.7,
            36 => 11.3, 48 => 12.7, 60 => 14.1];
    $pr2 = [0 => 2.4, 6 => 5.7, 12 => 7.0, 18 => 8.1, 24 => 9.0,
            36 => 10.8, 48 => 12.3, 60 => 13.7];
    $lk1 = [0 => 2.9, 6 => 7.1, 12 => 8.6, 18 => 9.8, 24 => 10.8,
            36 => 12.7, 48 => 14.4, 60 => 16.0];
    $pr1 = [0 => 2.8, 6 => 6.5, 12 => 7.9, 18 => 9.1, 24 => 10.2,
            36 => 12.2, 48 => 14.0, 60 => 15.8];

    if ($sd === 1) {
        $tabel = $jenisKelamin === 1 ? $pr1 : $lk1;
    } else {
        $tabel = $jenisKelamin === 1 ? $pr2 : $lk2;
    }
    $usiaBulan = max(0, min(60, $usiaBulan));

    $jangkar = array_keys($tabel);
    $bawah = $jangkar[0];
    $atas  = end($jangkar);
    foreach ($jangkar as $i => $u) {
        if ($u <= $usiaBulan) {
            $bawah = $u;
            $atas  = $jangkar[min($i + 1, count($jangkar) - 1)];
        }
    }
    if ($atas === $bawah) {
        return $tabel[$bawah];
    }
    $rasio = ($usiaBulan - $bawah) / ($atas - $bawah);
    return round($tabel[$bawah] + $rasio * ($tabel[$atas] - $tabel[$bawah]), 1);
}

/** Dipertahankan agar pemanggilan lama tetap berjalan. */
function ambangBeratMinus2SD(int $usiaBulan, int $jenisKelamin): float
{
    return ambangBeratWHO($usiaBulan, $jenisKelamin, 2);
}

/**
 * @param array $data [usia_bulan, berat, jenis_kelamin, mpasi, hasil, nama_balita]
 * @return array daftar blok saran: ['judul' => ..., 'isi' => ...]
 */
function susunSaran(array $data): array
{
    $nama   = $data['nama_balita'];
    $usia   = (int)$data['usia_bulan'];
    $berat  = (float)$data['berat'];
    $jk     = (int)$data['jenis_kelamin'];
    $mpasi  = (int)$data['mpasi'];
    $hasil  = (int)$data['hasil'];

    $ambang   = ambangBeratWHO($usia, $jk, 2);   // batas bawah (-2 SD)
    $ambang1  = ambangBeratWHO($usia, $jk, 1);   // pita terbawah kondisi normal (-1 SD)
    $kurang   = $berat < $ambang;
    $mepet    = !$kurang && $berat < $ambang1;   // normal, tetapi masih menempel batas
    $selisih  = round($ambang - $berat, 1);
    $jarak    = round($berat - $ambang, 1);      // jarak aman dari batas bawah
    $blok     = [];

    /* ---------------- 1. Kesimpulan ---------------- */
    if ($hasil === 1) {
        $isi = "Berdasarkan perhitungan algoritma K-Nearest Neighbor, kondisi {$nama} "
             . "memiliki kemiripan dengan kelompok balita yang berisiko mengalami stunting. "
             . "Hasil ini bukan diagnosis medis, melainkan peringatan dini agar keluarga "
             . "segera melakukan pemeriksaan lanjutan. Stunting yang ditangani sebelum anak "
             . "berusia dua tahun masih sangat mungkin diperbaiki, karena pada masa itu "
             . "pertumbuhan anak berlangsung paling cepat.";
    } else {
        $isi = "Berdasarkan perhitungan algoritma K-Nearest Neighbor, kondisi {$nama} "
             . "lebih menyerupai kelompok balita yang tidak berisiko stunting. Hasil ini "
             . "patut disyukuri, namun pemantauan tetap perlu diteruskan karena status gizi "
             . "anak dapat berubah seiring pertumbuhannya. Penimbangan rutin setiap bulan "
             . "di Posyandu adalah cara paling sederhana untuk menjaga kondisi ini.";
    }
    $blok[] = ['judul' => 'Kesimpulan Skrining', 'isi' => $isi];

    /* ---------------- 2. Berat badan menurut umur ---------------- */
    $usiaTeks = $usia < 12 ? "{$usia} bulan" : intdiv($usia, 12) . ' tahun ' . ($usia % 12) . ' bulan';
    if ($kurang) {
        $isi = "Pada usia {$usiaTeks}, berat badan {$nama} tercatat {$berat} kg. "
             . "Sebagai pembanding, batas bawah berat badan normal menurut umur untuk anak "
             . "seusianya adalah sekitar {$ambang} kg, sehingga masih terdapat selisih "
             . "sekitar {$selisih} kg yang perlu dikejar. Kekurangan berat badan sering "
             . "menjadi tanda paling awal sebelum pertumbuhan tinggi badan ikut terhambat, "
             . "sehingga selisih ini sebaiknya tidak dibiarkan berlarut. Sasaran yang wajar "
             . "adalah kenaikan berat badan bertahap setiap bulan, bukan kenaikan drastis "
             . "dalam waktu singkat.";
    } elseif ($mepet) {
        $isi = "Pada usia {$usiaTeks}, berat badan {$nama} tercatat {$berat} kg. Angka ini "
             . "masih tergolong normal karena berada di atas batas bawah berat badan menurut "
             . "umur, yaitu sekitar {$ambang} kg, namun jaraknya tipis, hanya sekitar "
             . "{$jarak} kg. Perlu diketahui bahwa {$ambang} kg bukan berat badan yang ideal, "
             . "melainkan garis terbawah sebelum seorang anak digolongkan berat badan kurang; "
             . "berat badan tengah untuk anak seusianya berada di sekitar {$ambang1} kg ke "
             . "atas. Karena itu kondisi ini sebaiknya diperlakukan sebagai tanda waspada: "
             . "pastikan penimbangan di Posyandu tidak terlewat setiap bulan, dan pastikan "
             . "grafik di buku KIA terus naik, bukan sekadar bertahan.";
    } else {
        $isi = "Pada usia {$usiaTeks}, berat badan {$nama} tercatat {$berat} kg dan angka ini "
             . "berada dalam kisaran berat badan normal menurut umur untuk anak seusianya, "
             . "yaitu di atas batas bawah sekitar {$ambang} kg dengan jarak yang cukup aman. "
             . "Yang perlu dijaga berikutnya bukan sekadar angkanya, melainkan kenaikannya: "
             . "grafik berat badan di buku KIA sebaiknya terus naik mengikuti garis "
             . "pertumbuhan, karena berat badan yang mendatar selama dua bulan berturut-turut "
             . "sudah termasuk tanda peringatan.";
    }
    $blok[] = ['judul' => 'Kondisi Berat Badan Menurut Umur', 'isi' => $isi];

    /* ---------------- 3. Saran pemberian makan per kelompok usia ---------------- */
    if ($usia < 6) {
        $isi = "Anak berusia di bawah enam bulan cukup mendapat ASI saja tanpa tambahan "
             . "air putih, madu, pisang, maupun bubur. Berikan ASI sesering anak mau, "
             . "paling tidak delapan sampai dua belas kali dalam sehari termasuk malam hari. "
             . "Bila ibu merasa ASI kurang, perbanyak frekuensi menyusui karena isapan bayi "
             . "justru merangsang produksi ASI, dan konsultasikan ke bidan atau konselor "
             . "menyusui sebelum memutuskan memberi susu formula.";
    } elseif ($usia < 12) {
        $isi = "Pada usia enam sampai dua belas bulan, ASI tetap diteruskan sambil "
             . "diperkenalkan makanan pendamping. Tekstur dimulai dari bubur kental yang "
             . "tidak tumpah saat sendok dimiringkan, lalu naik menjadi makanan cincang "
             . "halus menjelang usia sembilan bulan. Berikan dua sampai tiga kali makan "
             . "utama ditambah satu kali selingan, dengan porsi sekitar tiga sampai setengah "
             . "mangkuk kecil. Pastikan setiap porsi mengandung sumber protein hewani "
             . "seperti telur, ikan, hati ayam, atau daging, karena protein hewani adalah "
             . "kunci pertumbuhan yang paling sering terlewat pada anak Indonesia.";
    } elseif ($usia < 24) {
        $isi = "Pada usia satu sampai dua tahun, anak sudah dapat makan makanan keluarga "
             . "yang dicincang atau dilembutkan. Berikan tiga kali makan utama ditambah dua "
             . "kali selingan bergizi setiap hari, dan teruskan ASI hingga anak berusia dua "
             . "tahun. Masa ini adalah bagian akhir dari seribu hari pertama kehidupan, "
             . "sehingga perbaikan gizi yang dilakukan sekarang masih memberi dampak besar "
             . "pada tinggi badan anak kelak. Biasakan anak makan bersama keluarga pada jam "
             . "yang tetap dan batasi jajanan manis yang membuat anak kenyang palsu.";
    } else {
        $isi = "Pada usia di atas dua tahun, anak mengikuti pola makan keluarga dengan tiga "
             . "kali makan utama dan dua kali selingan. Susun piring anak dengan perbandingan "
             . "sepertiga makanan pokok, sepertiga lauk hewani dan nabati, serta sepertiga "
             . "sayur dan buah. Pada usia ini anak sering mulai memilih-milih makanan, dan "
             . "hal itu wajar; tawarkan makanan yang sama berulang kali dengan cara "
             . "penyajian berbeda tanpa memaksa, serta hindari memberi susu atau jajanan "
             . "terlalu dekat dengan jam makan.";
    }
    $blok[] = ['judul' => 'Saran Pemberian Makan Sesuai Usia', 'isi' => $isi];

    /* ---------------- 4. Keragaman makanan ---------------- */
    $total = count(kelompokMakanan());
    if ($usia < 6) {
        $isi = "Skor keragaman makanan belum relevan untuk anak di bawah enam bulan karena "
             . "kebutuhan gizinya sepenuhnya dipenuhi oleh ASI.";
    } elseif ($mpasi <= 1) {
        $isi = "Dalam sehari terakhir anak tercatat hanya menerima {$mpasi} dari {$total} "
             . "kelompok makanan. Pola makan yang terlalu sempit, misalnya hanya bubur atau "
             . "nasi dengan kuah sayur, membuat anak kenyang tetapi kekurangan zat gizi "
             . "pembangun. Langkah paling cepat memperbaikinya adalah menambahkan satu butir "
             . "telur setiap hari, karena telur murah, mudah didapat, dan terbukti membantu "
             . "pertumbuhan anak. Setelah itu tambahkan satu kelompok makanan baru setiap "
             . "beberapa hari hingga anak terbiasa.";
    } elseif ($mpasi <= 3) {
        $isi = "Dalam sehari terakhir anak menerima {$mpasi} dari {$total} kelompok makanan. "
             . "Keragaman ini sudah cukup baik tetapi belum ideal. Perhatikan kelompok yang "
             . "belum tercentang dan usahakan melengkapinya, khususnya lauk hewani serta "
             . "sayur dan buah berwarna, karena keduanya menyumbang zat besi, seng, dan "
             . "vitamin A yang paling dibutuhkan anak dalam masa pertumbuhan.";
    } else {
        $isi = "Dalam sehari terakhir anak menerima {$mpasi} dari {$total} kelompok makanan, "
             . "yang menunjukkan pola makan sudah beragam. Pertahankan kebiasaan ini dan "
             . "jaga agar keragaman tersebut berlangsung setiap hari, bukan hanya pada hari "
             . "tertentu saja.";
    }
    $blok[] = ['judul' => 'Pola Pemberian Makan (MP-ASI)', 'isi' => $isi];

    /* ---------------- 5. Anemia, bila ada ---------------- */
    if ((int)($data['anemia'] ?? 0) === 1) {
        $blok[] = ['judul' => 'Catatan Khusus: Anemia', 'isi' =>
            "{$nama} tercatat pernah dinyatakan mengalami anemia. Kekurangan zat besi membuat "
            . "tubuh anak sulit mengangkut oksigen, sehingga nafsu makan menurun, anak lebih "
            . "mudah sakit, dan pertumbuhannya melambat. Anemia dan hambatan pertumbuhan sering "
            . "berjalan beriringan karena keduanya berakar pada asupan gizi yang sama. "
            . "Utamakan sumber zat besi yang mudah diserap tubuh, yaitu hati ayam, daging merah, "
            . "ikan, dan telur, serta sajikan bersama buah kaya vitamin C seperti jeruk, jambu, "
            . "atau tomat yang membantu penyerapan zat besi. Sebaliknya, hindari memberi teh "
            . "atau susu bersamaan dengan makan karena keduanya justru menghambat penyerapan "
            . "zat besi. Tanyakan pula kepada petugas kesehatan mengenai pemberian suplemen zat "
            . "besi dan obat cacing sesuai anjuran, karena cacingan merupakan penyebab anemia "
            . "yang sering terlewat pada anak."];
    }

    /* ---------------- 6. Tuberkulosis, bila ada ---------------- */
    $kodeTbc = (int)($data['tbc'] ?? 3);
    if ($kodeTbc !== 3) {
        $blok[] = ['judul' => 'Catatan Khusus: Tuberkulosis (TBC)', 'isi' =>
            "{$nama} memiliki catatan tuberkulosis. TBC menguras energi dan zat gizi anak dalam "
            . "waktu lama, sehingga berat badan sulit naik meskipun anak tampak makan seperti "
            . "biasa; inilah sebabnya TBC menjadi salah satu penyebab hambatan pertumbuhan yang "
            . "paling sering tidak disadari. Hal terpenting adalah memastikan pengobatan TBC "
            . "dijalani sampai tuntas sesuai jadwal dari Puskesmas dan tidak dihentikan hanya "
            . "karena anak sudah terlihat membaik. Selama masa pengobatan, kebutuhan gizi anak "
            . "meningkat, sehingga berikan porsi makan lebih sering dalam ukuran kecil, "
            . "perbanyak sumber protein hewani, dan pantau berat badannya setiap bulan. Anggota "
            . "keluarga serumah sebaiknya ikut diperiksa, dan pastikan rumah mendapat cukup "
            . "cahaya matahari serta udara segar."];
    }

    /* ---------------- 7. Tindak lanjut ---------------- */
    if ($hasil === 1) {
        $isi = "Bawalah {$nama} ke Posyandu terdekat atau ke Puskesmas Pamulang dalam waktu "
             . "dekat untuk pengukuran tinggi badan dan penimbangan ulang oleh petugas, "
             . "karena penentuan stunting yang sebenarnya memerlukan pengukuran tinggi badan "
             . "menurut umur. Bawa buku KIA agar riwayat pertumbuhan anak dapat dibaca "
             . "petugas. Lakukan penimbangan setiap bulan tanpa terlewat, pastikan imunisasi "
             . "lengkap sesuai jadwal, berikan vitamin A pada bulan Februari dan Agustus, "
             . "serta biasakan cuci tangan dengan sabun sebelum menyiapkan makanan karena "
             . "diare berulang termasuk penyebab tersembunyi terhambatnya pertumbuhan.";
    } else {
        $isi = "Lanjutkan penimbangan rutin setiap bulan di Posyandu terdekat dan pastikan "
             . "hasilnya selalu dicatat di buku KIA agar arah pertumbuhan anak terlihat. "
             . "Lengkapi imunisasi sesuai jadwal, berikan vitamin A pada bulan Februari dan "
             . "Agustus, serta jaga kebersihan air dan makanan. Ulangi skrining melalui "
             . "aplikasi ini setelah penimbangan berikutnya agar perubahan kondisi anak "
             . "dapat terpantau dari waktu ke waktu.";
    }
    $blok[] = ['judul' => 'Tindak Lanjut yang Disarankan', 'isi' => $isi];

    return $blok;
}

/** Ubah blok saran menjadi teks polos untuk disimpan ke kolom TEXT di basis data. */
function saranKeTeks(array $blok): string
{
    $baris = [];
    foreach ($blok as $b) {
        $baris[] = $b['judul'] . "\n" . $b['isi'];
    }
    return implode("\n\n", $baris);
}