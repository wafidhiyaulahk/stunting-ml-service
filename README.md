# SiDeteksi Stunting

Implementasi Algoritma K-Nearest Neighbor (KNN) untuk Deteksi Risiko Stunting pada
Anak Balita Berbasis Website — studi kasus Puskesmas Pamulang.

Petunjuk pemasangan ada di `PANDUAN_INSTALASI.md`. Pembahasan rancangan sistem,
alasan setiap keputusan teknis, dan catatan yang perlu diverifikasi ada di
`DOKUMEN_RANCANGAN.md`.

## Susunan folder

```
stunting-app/
├── database/
│   └── skripsi_stunting.sql      Skema tabel, relasi, dan data awal
├── ml-service/                   Layanan Python pembungkus model KNN
│   ├── app.py                    REST API: /health, /config, /predict
│   ├── feature_config.json       Definisi ketujuh variabel dan teks pertanyaannya
│   ├── requirements.txt          Versi pustaka yang dikunci
│   └── model/model_KNN.pkl       Model yang sudah dilatih
└── web/                          Aplikasi web PHP
    ├── config/                   Konfigurasi dan koneksi PDO
    ├── helpers/                  Fungsi bersama, kerangka tampilan, penyusun saran
    ├── auth/                     Registrasi, masuk pengguna, masuk admin, keluar
    ├── user/                     Beranda, skrining, hasil, riwayat
    ├── admin/                    Dashboard, kelola pengguna, data skrining, posyandu
    ├── assets/                   Berkas CSS dan JavaScript
    └── api_wilayah.php           Endpoint dropdown alamat bertingkat
```

## Ringkasan model yang dibungkus

Hasil pembacaan langsung terhadap `model_KNN.pkl`:

```
imblearn.pipeline.Pipeline
├── prep  : ColumnTransformer
│           num = [P4072_anak, P1509, MPASI_DIVERSITY]  -> SimpleImputer(median) + StandardScaler
│           bin = [P916C, P404_anak]                    -> SimpleImputer(most_frequent)
│           nom = [P501, P810]                          -> SimpleImputer(most_frequent) + OneHotEncoder
├── smote : SMOTE(random_state=42)          aktif hanya saat pelatihan
└── clf   : KNeighborsClassifier(n_neighbors=41, metric='chebyshev', weights='uniform')
```

Urutan kolom masukan: `P4072_anak, P404_anak, P1509, P810, P916C, P501, MPASI_DIVERSITY`.
Keluaran: `0` tidak berisiko, `1` berisiko. Jumlah data latih setelah SMOTE 844 baris
dengan proporsi kelas seimbang 422 berbanding 422.

## Pemetaan variabel

| Kode di model | Variabel penelitian |
|---------------|---------------------|
| `P4072_anak` | Umur balita (0–59 bulan) |
| `P404_anak` | Jenis kelamin balita |
| `P1509` | Berat badan |
| `P810` | Tuberkulosis (TBC) |
| `P916C` | Anemia |
| `P501` | Kualitas air minum |
| `MPASI_DIVERSITY` | Pola pemberian makan (MP-ASI), dihitung dari 8 kolom `P1108*K2` |

Variabel terikat: status gizi balita dengan indikator tinggi badan menurut umur (TB/U),
stunting bila Z-score kurang dari -2 SD. **Tinggi badan bukan masukan model**, melainkan
dasar penentuan label saat pelatihan, sehingga sistem dapat menaksir risiko stunting
tanpa mengharuskan tinggi badan anak diukur lebih dahulu.

Seluruh teks pertanyaan dan pilihan jawaban diatur dari `ml-service/feature_config.json`.
