# Panduan Instalasi dan Menjalankan Aplikasi

Aplikasi ini terdiri atas dua bagian yang berjalan bersamaan: aplikasi web PHP di
dalam XAMPP, dan layanan model (`ml-service`) berbasis Python yang membungkus
`model_KNN.pkl`. Keduanya harus menyala agar skrining dapat diproses.

---

## 1. Menyiapkan basis data

Nyalakan **Apache** dan **MySQL** dari XAMPP Control Panel, lalu buka
`http://localhost/phpmyadmin`. Pilih menu **Import**, unggah berkas
`database/skripsi_stunting.sql`, kemudian tekan **Go**. Berkas tersebut sekaligus
membuat basis data `skripsi_stunting`, seluruh tabelnya, data wilayah Kecamatan
Pamulang, dua belas contoh Posyandu, satu akun admin, dan satu akun pengguna contoh.

Akun bawaan setelah impor:

| Peran    | Nomor HP       | Kata sandi     |
|----------|----------------|----------------|
| Admin    | `081200000001` | `admin123`     |
| Pengguna | `081300000001` | `pengguna123`  |

Gantilah kata sandi kedua akun tersebut sebelum aplikasi dipakai di lapangan.

### Data contoh (opsional)

Supaya dashboard admin langsung menampilkan grafik yang berisi saat didemonstrasikan,
impor juga `database/data_contoh.sql` **setelah** `skripsi_stunting.sql`. Berkas ini
menambahkan 3 akun pengguna dan 36 skrining yang tersebar selama 6 bulan terakhir
(April–September 2026). Hasil setiap skrining dihitung langsung oleh model KNN, bukan
diisi sembarangan. Semua akun contoh memakai kata sandi `pengguna123`.

Lewati langkah ini bila aplikasi akan dipakai dengan data sungguhan.

---

## 2. Menempatkan berkas aplikasi

Salin seluruh folder `stunting-app` ke dalam `C:\xampp\htdocs\`, sehingga
susunannya menjadi `C:\xampp\htdocs\stunting-app\web\...`.

Bila Anda memakai nama folder yang berbeda, sesuaikan nilai `BASE_URL` pada
`web/config/config.php`. Bila MySQL di XAMPP Anda memakai kata sandi root,
sesuaikan pula `DB_PASS` pada berkas yang sama.

---

## 3. Menjalankan layanan model KNN

### Cara termudah

Buka folder `C:\xampp\htdocs\stunting-app\ml-service`, lalu **klik dua kali berkas
`jalankan_service.bat`**. Pada saat pertama dijalankan, berkas tersebut membuat
lingkungan Python tersendiri di `ml-service\venv` dan memasang seluruh pustaka yang
dibutuhkan; pada saat berikutnya ia langsung menjalankan layanan.

Biarkan jendela hitam tersebut tetap terbuka selama aplikasi dipakai. Bila berhasil,
terminal menampilkan `Running on http://127.0.0.1:5000`. Anda dapat mengujinya dengan
membuka `http://127.0.0.1:5000/health` di peramban.

### Cara manual

Bila ingin menjalankannya sendiri lewat PowerShell:

```powershell
cd C:\xampp\htdocs\Deteksi\ml-service
python -m venv venv
venv\Scripts\python.exe -m pip install --upgrade pip
venv\Scripts\python.exe -m pip install -r requirements.txt
venv\Scripts\python.exe app.py


Perhatikan bahwa perintah di atas memanggil `venv\Scripts\python.exe` secara langsung
tanpa menjalankan `Activate.ps1`. Cara ini disengaja, karena PowerShell pada
pemasangan Windows standar menolak menjalankan skrip aktivasi dengan pesan
*"running scripts is disabled on this system"*. Dengan memanggil `python.exe` di
dalam venv secara langsung, hasilnya sama persis tanpa perlu mengubah
ExecutionPolicy sama sekali. Conda tidak diperlukan.

### Catatan mengenai versi pustaka

`model_KNN.pkl` dilatih memakai **scikit-learn 1.6.1**, sehingga versi itu dikunci di
`requirements.txt`. Memuatnya dengan scikit-learn yang lebih baru menimbulkan error
`Can't get attribute '_RemainderColsList'`. Model ini juga berupa
`imblearn.pipeline.Pipeline`, sehingga `imbalanced-learn` wajib terpasang meskipun
SMOTE hanya aktif saat pelatihan.

Sebaliknya, **numpy justru tidak boleh dikunci di versi 2.0.2**. Versi tersebut belum
menyediakan wheel untuk Python 3.13, sehingga pip berusaha mengompilasinya dari kode
sumber dan gagal dengan pesan `Unknown compiler(s)` pada komputer yang tidak memasang
Visual Studio Build Tools. Karena itu numpy dan pandas ditulis sebagai rentang versi
yang sudah tersedia dalam bentuk wheel siap pasang.

Kombinasi pada `requirements.txt` telah diuji pada Python 3.13 dan Python 3.11, dan
menghasilkan prediksi yang identik pada 576 kombinasi data uji.

### Bila venv sudah terlanjur bercampur versi

Apabila sebelumnya Anda sempat memasang scikit-learn atau numpy versi lain ke dalam
venv yang sama, cara paling aman adalah membuatnya ulang dari awal, bukan menimpanya:

```powershell
cd C:\xampp\htdocs\stunting-app\ml-service
Remove-Item -Recurse -Force venv
python -m venv venv
venv\Scripts\python.exe -m pip install -r requirements.txt
venv\Scripts\python.exe app.py
```

Hapus pula folder `venv` yang mungkin terbentuk di `C:\xampp\htdocs\stunting-app\`
(satu tingkat di atas `ml-service`), karena folder itu tidak dipakai oleh sistem ini.

---

## 4. Membuka aplikasi

Buka `http://localhost/stunting-app/web/` di peramban. Halaman masuk pengguna akan
muncul. Tautan menuju halaman masuk admin berada di bagian bawah halaman tersebut.

---

## 5. Bila terjadi masalah

Apabila muncul pesan *"Layanan penghitung KNN sedang tidak aktif"*, berarti
`python app.py` belum dijalankan atau jendelanya sudah tertutup. Apabila muncul
pesan *"Koneksi basis data gagal"*, periksa apakah MySQL sudah menyala di XAMPP dan
apakah basis data sudah diimpor. Apabila dropdown RW dan RT tidak terisi setelah
kelurahan dipilih, periksa nilai `BASE_URL` karena berkas `api_wilayah.php`
dipanggil melalui alamat tersebut.
