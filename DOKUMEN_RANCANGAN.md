# Dokumen Rancangan Sistem

Implementasi Algoritma K-Nearest Neighbor untuk Deteksi Risiko Stunting pada Anak
Balita Berbasis Website — Puskesmas Pamulang.

Dokumen ini menjelaskan rancangan sistem beserta alasan di balik setiap keputusan
teknis, sehingga dapat dipakai sebagai bahan penyusunan bab perancangan dan bab
implementasi pada laporan skripsi.

---

## 1. Temuan dari pembacaan model dan implikasinya

Sebelum merancang apa pun, berkas `model_KNN.pkl` dibaca terlebih dahulu untuk
mengetahui apa yang sebenarnya diharapkan model sebagai masukan. Hasilnya menentukan
seluruh rancangan berikutnya, mulai dari kolom basis data sampai susunan pertanyaan
pada formulir skrining.

Model yang tersimpan bukan sekadar objek `KNeighborsClassifier`, melainkan sebuah
`imblearn.pipeline.Pipeline` yang terdiri atas tiga tahap. Tahap pertama bernama
`prep`, berupa `ColumnTransformer` yang memperlakukan tiga kelompok variabel secara
berbeda: variabel numerik `P4072_anak`, `P1509`, dan `MPASI_DIVERSITY` diisi nilai
kosongnya dengan median lalu dibakukan menggunakan `StandardScaler`; variabel biner
`P916C` dan `P404_anak` hanya diisi nilai kosongnya dengan modus; sedangkan variabel
nominal `P501` dan `P810` diisi dengan modus lalu diubah menjadi kolom indikator
melalui `OneHotEncoder`. Tahap kedua adalah `SMOTE` yang menyeimbangkan kelas, dan
tahap ketiga adalah `KNeighborsClassifier` dengan `n_neighbors=41`, `metric='chebyshev'`,
serta `weights='uniform'`.

Tiga konsekuensi penting muncul dari struktur tersebut. Pertama, seluruh praproses
sudah melekat di dalam pipeline, sehingga aplikasi web **tidak boleh melakukan
normalisasi, encoding, maupun pengisian nilai kosong sendiri**; aplikasi cukup
mengirim nilai mentah apa adanya, dan pipeline yang akan mengurusnya. Kesalahan yang
paling sering terjadi pada implementasi sejenis adalah melakukan penskalaan ganda,
yang membuat hasil prediksi menyimpang tanpa memunculkan pesan error apa pun. Kedua,
karena `ColumnTransformer` dilatih memakai `DataFrame` bernama kolom, masukan saat
prediksi juga **wajib berupa `DataFrame` dengan nama kolom yang sama**; mengirim
senarai biasa berisiko tertukar urutannya. Ketiga, tahap `SMOTE` hanya bekerja pada
saat pelatihan dan otomatis dilewati saat prediksi, sehingga keberadaannya tidak
memperlambat aplikasi, tetapi pustaka `imbalanced-learn` tetap wajib terpasang agar
berkas `.pkl` dapat dibuka sama sekali.

Satu hal lagi yang bersifat teknis namun kritis: model ini dilatih dengan
scikit-learn versi 1.6.1. Percobaan memuatnya dengan scikit-learn 1.8.0 menghasilkan
kegagalan `Can't get attribute '_RemainderColsList'`. Karena itu versi pustaka pada
`requirements.txt` dikunci, dan hal ini sebaiknya disebutkan pada bagian spesifikasi
perangkat lunak di laporan skripsi.

### Ketujuh variabel masukan

Rentang nilai berikut direkonstruksi dari data pelatihan yang tersimpan di dalam
model, sehingga dapat dipakai sebagai acuan validasi formulir.

Pemetaan berikut disusun dari tabel variabel penelitian, dipadukan dengan rentang nilai
yang direkonstruksi langsung dari data pelatihan di dalam model.

| Kode di model | Variabel penelitian | Sifat | Rentang / kategori |
|---------------|---------------------|-------|--------------------|
| `P4072_anak` | Umur balita (0–59 bulan) | numerik | 1 sampai 59, rata-rata 33,6 |
| `P1509` | Berat badan | numerik | 3,8 sampai 32,6 kg, rata-rata 12,0 |
| `MPASI_DIVERSITY` | Pola pemberian makan (MP-ASI) | numerik | 0 sampai 6, median 0 |
| `P404_anak` | Jenis kelamin balita | biner | 0 dan 1; sekitar 46 persen bernilai 1 |
| `P916C` | Anemia | biner | 0 dan 1; 22,2 persen bernilai 1 |
| `P810` | Tuberkulosis (TBC) | nominal | 1, 2, dan 3; 98,4 persen bernilai 3 |
| `P501` | Kualitas air minum | nominal | 1, 2, 4, dan 5; kode 3 tidak muncul |

Variabel terikatnya adalah status gizi balita dengan indikator tinggi badan menurut
umur (TB/U), yaitu stunting bila Z-score kurang dari -2 SD.

Satu hal yang perlu ditegaskan karena mudah disalahpahami: **tinggi badan bukan
variabel masukan model, melainkan dasar penentuan label saat pelatihan.** Justru di
situlah letak manfaat sistem ini. Mengukur panjang atau tinggi badan balita secara
benar memerlukan alat dan petugas terlatih, dan kesalahan pengukuran beberapa
sentimeter saja sudah dapat mengubah kesimpulan. Dengan memakai variabel yang lebih
mudah dikumpulkan orang tua di rumah, sistem menaksir status yang sebenarnya
ditentukan oleh tinggi badan tanpa mengharuskan tinggi badan itu diukur lebih dahulu.
Kalimat inilah yang sebaiknya menjadi argumen utama pada bab pendahuluan skripsi Anda.

Nama ketujuh variabel sudah pasti. Yang masih perlu Anda cocokkan dengan kamus data
adalah **arti tiap kode angka** pada dua variabel nominal, yaitu kode 1 dan 2 pada
`P810` serta kode 1, 2, 4, dan 5 pada `P501`. Agar pencocokan itu tidak menuntut
perubahan kode program, seluruh teks pertanyaan dan pilihan jawaban dipusatkan pada
satu berkas `ml-service/feature_config.json`; mengubah berkas tersebut otomatis
mengubah tampilan formulir skrining dan isi pop-up di halaman admin.

Dua catatan yang layak masuk ke bagian keterbatasan penelitian. Pertama, `P810` bernilai
3 pada 98,4 persen data pelatihan, sehingga variabel TBC nyaris konstan dan daya
pembedanya kecil. Kedua, `P916C` memiliki proporsi yang hampir sama pada kedua kelas,
yaitu 0,227 pada kelompok tidak berisiko dan 0,217 pada kelompok berisiko, sehingga
variabel anemia pada data ini juga tidak banyak membedakan. Keduanya merupakan temuan
yang jujur dan justru memperkuat pembahasan, bukan melemahkannya. Perlu dicatat pula
bahwa kategori 3 pada `P501` tidak pernah muncul saat pelatihan; `OneHotEncoder`
disetel `handle_unknown='ignore'` sehingga nilai tak dikenal tidak membuat sistem
gagal, melainkan diperlakukan sebagai seluruh indikator bernilai nol.

---

## 2. Struktur basis data

Rancangan basis data memakai enam tabel. Pemisahannya mengikuti satu prinsip: data
yang jarang berubah dipisahkan dari data yang tumbuh terus, supaya tidak terjadi
pengulangan informasi yang sama berkali-kali.

Tabel `pengguna` menyimpan akun orang tua atau kader, dengan `no_hp` sebagai kolom
unik karena nomor itulah yang dipakai untuk masuk. Tabel `balita` menyimpan identitas
anak dan terhubung ke `pengguna` melalui `pengguna_id`. Pemisahan inilah yang membuat
satu akun dapat memantau lebih dari satu anak sebagaimana diminta pada spesifikasi,
sekaligus membuat identitas anak tidak perlu diketik ulang setiap kali skrining
diulang. Tabel `skrining` menyimpan setiap peristiwa pemeriksaan dan terhubung ke
`balita`; relasinya satu ke banyak, sehingga riwayat pertumbuhan seorang anak dapat
ditelusuri dari waktu ke waktu.

Hal yang membedakan rancangan ini dari rancangan yang hanya menyimpan hasil akhir
adalah bahwa tabel `skrining` turut menyimpan **ketujuh nilai variabel masukan** dalam
kolom tersendiri, bukan sekadar label berisiko atau tidak. Penyimpanan semacam ini
membuat setiap hasil dapat dihitung ulang dan diperiksa kembali, yang penting ketika
dosen penguji menanyakan bagaimana suatu hasil dapat muncul. Selain itu data yang
terkumpul nantinya dapat dipakai untuk melatih ulang model dengan data lokal Pamulang,
yang merupakan saran pengembangan yang wajar untuk ditulis pada bab penutup.

Tabel `posyandu` menyimpan daftar Posyandu beserta nama kader dan nomor kontaknya,
sedangkan tabel `wilayah` menyimpan kombinasi kelurahan, RW, dan RT se-Kecamatan
Pamulang sebagai sumber data dropdown alamat bertingkat. Tabel `admin` dipisahkan dari
`pengguna` karena kedua peran memiliki hak akses yang sama sekali berbeda; memisahkan
tabel membuat pengecekan hak akses menjadi tegas dan mengurangi risiko seorang
pengguna biasa tanpa sengaja memperoleh akses admin.

Seluruh relasi antartabel memakai `FOREIGN KEY` dengan `ON DELETE CASCADE`, sehingga
menghapus seorang pengguna otomatis menghapus data balita dan riwayat skrining
miliknya tanpa menyisakan baris yatim di dalam basis data.

Satu catatan mengenai spesifikasi awal: menu kelola pengguna diminta menampilkan kolom
kata sandi. Kata sandi pada sistem ini disimpan sebagai hasil `password_hash()` dan
secara teknis tidak dapat dikembalikan menjadi teks asli. Karena itu menu kelola
pengguna menampilkan nomor HP dan alamat, serta menyediakan kolom untuk **mengatur
ulang** kata sandi, bukan menampilkannya. Penyimpanan kata sandi dalam bentuk teks
biasa merupakan kelemahan keamanan yang biasanya justru menjadi bahan pertanyaan
penguji, sehingga pendekatan hash ini lebih aman untuk dipertahankan dan dijelaskan.

---

## 3. Arsitektur aplikasi

Persoalan utama arsitektur pada penelitian ini sederhana namun menentukan: model KNN
berupa berkas `.pkl` yang hanya dapat dibaca oleh Python, sedangkan aplikasi web
ditulis dengan PHP di atas XAMPP. Keduanya harus dipertemukan.

Terdapat tiga cara yang lazim ditempuh. Cara pertama adalah memanggil skrip Python
dari PHP melalui fungsi `shell_exec()`. Cara ini tampak paling singkat, tetapi model
harus dimuat ulang dari cakram pada setiap pemanggilan sehingga responsnya lambat,
penanganan error-nya sulit dibaca, dan pemanggilan perintah sistem dari PHP membuka
celah keamanan bila masukan tidak disaring dengan ketat. Cara kedua adalah menuliskan
ulang perhitungan KNN di dalam PHP. Cara ini menghindari Python sama sekali, tetapi
seluruh praproses — imputasi, penskalaan, dan one-hot encoding — harus ditiru dengan
persis, dan perbedaan sekecil apa pun akan membuat hasil di aplikasi berbeda dengan
hasil di notebook, sehingga justru melemahkan klaim penelitian.

Cara ketiga, yang dipakai pada rancangan ini, adalah menjalankan model sebagai
**layanan REST tersendiri**. Sebuah program Flask kecil memuat `model_KNN.pkl` satu
kali pada saat dinyalakan, lalu menunggu permintaan di `http://127.0.0.1:5000`.
Aplikasi PHP mengirim ketujuh variabel dalam bentuk JSON melalui cURL dan menerima
kembali hasil prediksi beserta probabilitasnya. Dengan cara ini model dijalankan oleh
pustaka yang sama persis dengan yang dipakai saat pelatihan sehingga hasilnya
dijamin identik dengan notebook, model tidak perlu dimuat berulang kali sehingga
responsnya cepat, dan kedua bagian sistem dapat diuji secara terpisah. Konsekuensinya,
ada dua proses yang harus dijalankan bersamaan, dan hal itu perlu disebutkan pada
panduan penggunaan sistem.

Layanan tersebut hanya mendengarkan di `127.0.0.1`, artinya tidak dapat dihubungi dari
luar komputer, dan tetap dilindungi token sederhana pada header `X-API-Token` sebagai
lapisan pengaman tambahan.

Pada sisi PHP, aplikasi ditulis secara native tanpa framework. Pilihan ini diambil
karena sistem ini berukuran sedang, dan kode PHP native lebih mudah ditelusuri baris
per baris saat sidang dibanding kode yang tersembunyi di balik abstraksi framework.
Meski demikian, kode tetap ditata berlapis: berkas di `config/` mengurus konfigurasi
dan koneksi, berkas di `helpers/` memuat logika yang dipakai bersama, sedangkan
berkas di `user/` dan `admin/` hanya mengurus alur halaman. Koneksi basis data memakai
PDO dengan *prepared statement* pada seluruh query, sehingga sistem aman dari SQL
injection.

---

## 4. Alur integrasi model KNN

Alur satu kali skrining berlangsung sebagai berikut. Pengguna mengisi formulir pada
`user/skrining.php`, lalu data dikirim ke `user/proses_skrining.php`. Berkas itu
memvalidasi masukan, menyimpan atau mengambil data balita, kemudian menyusun ketujuh
variabel. Perlu diperhatikan bahwa dua di antaranya tidak diisi langsung oleh
pengguna: usia dalam bulan dihitung dari tanggal lahir memakai fungsi
`usiaDalamBulan()`, sedangkan skor keragaman makanan dihitung dari banyaknya kotak
centang kelompok makanan yang ditandai. Pendekatan ini dipilih karena menanyakan
"usia dalam bulan" atau "skor keragaman makanan" secara langsung kepada orang tua
berpotensi menghasilkan jawaban yang keliru.

Ketujuh nilai tersebut dikirim ke layanan model melalui fungsi `prediksiStunting()`.
Di sisi Python, fungsi `build_dataframe()` mengubah JSON menjadi `DataFrame` satu
baris dengan urutan kolom yang dibaca langsung dari `model.named_steps['prep'].feature_names_in_`,
bukan ditulis manual, sehingga urutan kolom tidak mungkin salah. Nilai kosong
dibiarkan sebagai `NaN` agar diisi oleh `SimpleImputer` di dalam pipeline dengan nilai
yang sama seperti saat pelatihan.

Balasan yang dikembalikan berbentuk seperti berikut:

```json
{
  "sukses": true,
  "hasil": 1,
  "label": "Berisiko Stunting",
  "probabilitas": { "tidak_berisiko": 0.1463, "berisiko": 0.8537 },
  "keyakinan_persen": 85.4,
  "tetangga_berisiko": 35,
  "total_tetangga": 41,
  "catatan": []
}
```

Nilai `tetangga_berisiko` sengaja disertakan karena inilah yang membuat hasil KNN
dapat dijelaskan kepada orang awam. Karena `weights='uniform'` dan `n_neighbors=41`,
probabilitas yang dihasilkan tidak lain adalah banyaknya tetangga suatu kelas dibagi
empat puluh satu. Dengan begitu aplikasi dapat menampilkan kalimat "35 dari 41 anak
dengan kondisi paling mirip termasuk kelompok berisiko stunting", yang jauh lebih
mudah dipahami orang tua dibanding angka probabilitas 0,8537, sekaligus memperlihatkan
cara kerja algoritma secara jujur.

Hasil beserta saran kemudian disimpan ke tabel `skrining`, dan pengguna diarahkan ke
`user/hasil.php`. Apabila layanan model sedang mati, fungsi `statusMlService()` yang
dipanggil di halaman skrining akan mendeteksinya lebih dahulu, tombol proses
dinonaktifkan, dan pengguna diberi keterangan yang jelas alih-alih dibiarkan menunggu
halaman yang gagal.

---

## 5. Catatan penting mengenai saran gizi

Saran gizi disusun oleh `helpers/saran.php` dalam lima blok: kesimpulan, kondisi berat
badan menurut umur, saran pemberian makan menurut kelompok usia, ulasan keragaman
makanan, dan tindak lanjut. Isi tiap blok menyesuaikan kondisi masing-masing balita,
sehingga dua anak dengan hasil sama tetapi usia dan pola makan berbeda akan menerima
saran yang berbeda pula.

Blok kedua memakai ambang berat badan menurut umur pada minus dua simpang baku dari
standar WHO. Ambang ini berperan sebagai penanda tambahan untuk memperkaya kalimat
saran, bukan sebagai penentu hasil; penentu hasil tetap sepenuhnya keluaran model KNN,
yang labelnya sendiri berasal dari Z-score tinggi badan menurut umur. Berat badan
menurut umur dipilih sebagai pelengkap karena angkanya sudah dimiliki orang tua dari
penimbangan Posyandu, sehingga saran dapat menyebut selisih berat yang konkret dan
dapat ditindaklanjuti.

Dua blok berikutnya hanya muncul bila relevan. Blok anemia ditampilkan ketika `P916C`
bernilai 1, berisi saran sumber zat besi yang mudah diserap beserta peringatan agar teh
dan susu tidak diberikan bersamaan dengan waktu makan. Blok tuberkulosis ditampilkan
ketika `P810` bukan kode 3, menekankan pentingnya menuntaskan pengobatan dan
meningkatkan frekuensi makan selama masa pengobatan. Dengan begitu kedua variabel
klinis tersebut tidak berhenti sebagai angka masukan model, tetapi ikut membentuk
saran yang diterima keluarga.

Seluruh halaman hasil juga mencantumkan keterangan bahwa skrining ini merupakan
deteksi dini dan bukan pengganti pemeriksaan tenaga kesehatan, yang merupakan
keharusan etis bagi sistem yang menyentuh ranah kesehatan.

---

## 6. Panduan implementasi fitur utama

**Autentikasi.** Kata sandi disimpan memakai `password_hash()` dan diperiksa dengan
`password_verify()`. Nomor HP dinormalisasi lebih dahulu oleh `normalisasiNoHp()`,
sehingga `+6281...`, `6281...`, dan `0812...` dianggap sebagai nomor yang sama dan
pengguna tidak gagal masuk hanya karena perbedaan cara penulisan. Pesan kesalahan
sengaja dibuat seragam untuk nomor yang salah maupun kata sandi yang salah, agar
tidak membocorkan nomor mana yang terdaftar. Setelah berhasil masuk,
`session_regenerate_id(true)` dipanggil untuk mencegah pembajakan sesi, dan setiap
halaman terlindung memanggil `wajibLoginPengguna()` atau `wajibLoginAdmin()` pada
baris pertamanya. Seluruh formulir yang mengubah data dilindungi token CSRF.

**Skrining.** Formulir dibangun secara dinamis dari `feature_config.json`, sehingga
perubahan label variabel tidak menuntut perubahan kode. Ketika pengguna memilih anak
yang sudah pernah terdaftar, isian identitas terisi otomatis dan dikunci, sehingga
riwayat skrining tetap menempel pada satu data anak yang sama, bukan menciptakan data
anak baru yang serupa.

**Pop-up.** Pop-up dibuat dengan HTML dan CSS biasa tanpa pustaka tambahan. Isi
pop-up ditempelkan pada atribut `data-detail` masing-masing tombol dalam bentuk JSON
yang sudah di-*escape* di sisi PHP, lalu dibaca oleh fungsi `bukaDetail()`. Pendekatan
ini menghindari permintaan tambahan ke server saat pop-up dibuka, sehingga terasa
seketika. Pop-up dapat ditutup dengan tombol silang, dengan menekan tombol Escape,
atau dengan mengeklik area gelap di luar kotak.

**Dashboard admin.** Grafik perbandingan digambar memakai div dan CSS, bukan pustaka
grafik dari internet. Pilihan ini disengaja agar aplikasi tetap tampil utuh saat
didemonstrasikan di ruang sidang yang koneksi internetnya tidak dapat diandalkan.
Selain grafik perbandingan, dashboard menampilkan sebaran hasil per kelurahan yang
berguna bagi petugas Puskesmas untuk menentukan wilayah prioritas.

---

## 7. Saran penerapan antarmuka bagi pengguna awam

Pengguna sistem ini adalah orang tua balita, yang sebagian besar bukan pengguna
komputer yang terbiasa. Beberapa penerapan berikut sudah tertanam pada kode dan
sebaiknya dipertahankan saat Anda mengembangkannya lebih lanjut.

Istilah teknis diterjemahkan menjadi pertanyaan sehari-hari. Pengguna tidak pernah
ditanya berapa skor keragaman makanan anaknya, melainkan diminta mencentang jenis
makanan yang dimakan anak dalam sehari terakhir, dan sistem yang menghitung skornya.
Demikian pula usia dalam bulan tidak ditanyakan, melainkan dihitung dari tanggal lahir
dan langsung ditampilkan kembali sebagai umpan balik sehingga pengguna dapat memeriksa
sendiri kebenarannya.

Alamat diisi melalui dropdown bertingkat, bukan kolom teks bebas. Selain memudahkan
pengguna, cara ini menjaga keseragaman data sehingga pencarian Posyandu terdekat dan
rekapitulasi per kelurahan dapat berjalan dengan benar. Pilihan jawaban ditampilkan
sebagai kotak besar yang dapat diketuk, bukan radio button kecil, karena sebagian
besar pengguna akan mengaksesnya dari telepon genggam.

Proses skrining dibagi menjadi tiga langkah yang ditampilkan di bagian atas halaman,
supaya pengguna mengetahui posisinya dan tidak merasa formulirnya tak berujung. Pada
halaman hasil, kesimpulan ditempatkan paling atas dengan warna yang tegas, disusul
penjelasan cara sistem sampai pada kesimpulan itu, baru kemudian saran terperinci.
Urutan ini mengikuti kebiasaan membaca orang awam yang ingin mengetahui hasilnya lebih
dahulu sebelum membaca penjelasannya.

Halaman depan aplikasi dibuat sebagai halaman perkenalan, bukan langsung melompat ke
formulir masuk. Pengunjung yang baru pertama kali membuka aplikasi perlu lebih dahulu
memahami apa yang dikerjakan sistem sebelum diminta menyerahkan data anaknya. Pada
halaman itu disertakan ilustrasi cara kerja algoritma dalam bentuk sebaran titik:
titik di tengah mewakili anak yang diperiksa, lingkaran putus-putus mewakili
ketetanggaan, dan warna titik di dalamnya memperlihatkan mengapa suatu kesimpulan
diambil. Ilustrasi semacam ini menjelaskan mekanisme yang benar-benar dipakai model,
berbeda dengan gambar hiasan yang tidak menambah pemahaman apa pun.

Seluruh halaman diakhiri dengan blok disklaimer penelitian yang menyatakan bahwa
aplikasi ini merupakan produk penelitian skripsi berbasis algoritma K-Nearest Neighbor,
bahwa keluarannya merupakan deteksi dini dan bukan diagnosis medis, serta bahwa
penentuan stunting yang sebenarnya memerlukan pengukuran tinggi badan oleh tenaga
kesehatan. Menempatkannya di setiap halaman, bukan hanya di halaman hasil, merupakan
keharusan etis bagi sistem yang menyentuh ranah kesehatan sekaligus menegaskan posisi
aplikasi sebagai sarana penelitian.

Hasil berisiko ditampilkan dengan nada yang mengajak bertindak, bukan menakut-nakuti,
dan selalu disertai jalan keluar berupa daftar Posyandu terdekat lengkap dengan nomor
kader yang dapat langsung dihubungi melalui WhatsApp. Sistem deteksi yang hanya
menyampaikan kabar buruk tanpa memberi arahan justru berisiko membuat orang tua panik
dan tidak berbuat apa-apa.
