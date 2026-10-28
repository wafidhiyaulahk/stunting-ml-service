-- =====================================================================
-- Basis Data Sistem Deteksi Risiko Stunting Balita Berbasis Website
-- Studi kasus: Puskesmas Pamulang, Kota Tangerang Selatan
-- MySQL / MariaDB (XAMPP)
--
-- Cara pakai: buka phpMyAdmin -> Import -> pilih berkas ini -> Go
-- =====================================================================

DROP DATABASE IF EXISTS skripsi_stunting;
CREATE DATABASE skripsi_stunting DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE skripsi_stunting;

-- ---------------------------------------------------------------------
-- 1. WILAYAH  (sumber data dropdown alamat bertingkat: kelurahan -> RW -> RT)
-- ---------------------------------------------------------------------
CREATE TABLE wilayah (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    kelurahan  VARCHAR(60)  NOT NULL,
    rw         CHAR(3)      NOT NULL,
    rt         CHAR(3)      NOT NULL,
    UNIQUE KEY uq_wilayah (kelurahan, rw, rt),
    KEY idx_kelurahan (kelurahan)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. ADMIN
-- ---------------------------------------------------------------------
CREATE TABLE admin (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL,
    no_hp      VARCHAR(20)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,   -- disimpan sebagai hash password_hash()
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. PENGGUNA (orang tua / kader)
--    Satu akun boleh memiliki banyak balita -> relasi 1:N ke tabel balita
-- ---------------------------------------------------------------------
CREATE TABLE pengguna (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nama          VARCHAR(100) NOT NULL,
    no_hp         VARCHAR(20)  NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    kelurahan     VARCHAR(60)  NOT NULL,
    rw            CHAR(3)      NOT NULL,
    rt            CHAR(3)      NOT NULL,
    alamat_detail VARCHAR(255) DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wilayah_pengguna (kelurahan, rw, rt)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. BALITA
--    Data identitas dipisah dari data skrining supaya satu balita dapat
--    diskrining berulang kali tanpa menduplikasi identitasnya.
-- ---------------------------------------------------------------------
CREATE TABLE balita (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    pengguna_id   INT          NOT NULL,
    nama_balita   VARCHAR(100) NOT NULL,
    nama_ibu      VARCHAR(100) NOT NULL,
    nama_ayah     VARCHAR(100) NOT NULL,
    no_wa         VARCHAR(20)  NOT NULL,
    tanggal_lahir DATE         NOT NULL,
    jenis_kelamin TINYINT      NOT NULL COMMENT '0=laki-laki, 1=perempuan -> dipetakan ke P404_anak',
    kelurahan     VARCHAR(60)  NOT NULL,
    rw            CHAR(3)      NOT NULL,
    rt            CHAR(3)      NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_balita_pengguna FOREIGN KEY (pengguna_id)
        REFERENCES pengguna(id) ON DELETE CASCADE ON UPDATE CASCADE,
    KEY idx_balita_pengguna (pengguna_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. SKRINING
--    Menyimpan nilai mentah ketujuh variabel model agar hasil dapat
--    ditelusuri ulang (reproducible) dan agar model dapat dievaluasi
--    kembali bila suatu saat dilatih ulang.
-- ---------------------------------------------------------------------
CREATE TABLE skrining (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    balita_id         INT      NOT NULL,
    pengguna_id       INT      NOT NULL,

    -- Ketujuh variabel masukan model KNN (nama kolom mengikuti nama fitur model)
    p4072_anak        INT      NOT NULL COMMENT 'Umur balita dalam bulan (0-59)',
    p1509             DECIMAL(5,2) NOT NULL COMMENT 'Berat badan balita dalam kg',
    mpasi_diversity   TINYINT  NOT NULL COMMENT 'Pola pemberian makan MP-ASI: jumlah kelompok makanan 0-8',
    p916c             TINYINT  NOT NULL COMMENT 'Anemia: 0=tidak, 1=ya',
    p404_anak         TINYINT  NOT NULL COMMENT 'Jenis kelamin: 0=laki-laki, 1=perempuan',
    p501              TINYINT  NOT NULL COMMENT 'Kualitas air minum, kode nominal: 1,2,4,5',
    p810              TINYINT  NOT NULL COMMENT 'Tuberkulosis, kode nominal: 1,2,3 (3=tidak pernah)',
    detail_makanan    VARCHAR(255) DEFAULT NULL COMMENT 'JSON centang kelompok makanan',

    -- Keluaran model
    hasil             TINYINT  NOT NULL COMMENT '0=tidak berisiko, 1=berisiko',
    probabilitas      DECIMAL(5,4) NOT NULL COMMENT 'Peluang kelas berisiko',
    tetangga_berisiko TINYINT  DEFAULT NULL COMMENT 'Jumlah dari 41 tetangga terdekat',
    saran             TEXT     DEFAULT NULL,

    tanggal_skrining  DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_skrining_balita FOREIGN KEY (balita_id)
        REFERENCES balita(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_skrining_pengguna FOREIGN KEY (pengguna_id)
        REFERENCES pengguna(id) ON DELETE CASCADE ON UPDATE CASCADE,
    KEY idx_skrining_hasil (hasil),
    KEY idx_skrining_tanggal (tanggal_skrining)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. POSYANDU
-- ---------------------------------------------------------------------
CREATE TABLE posyandu (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nama_posyandu VARCHAR(100) NOT NULL,
    kelurahan    VARCHAR(60)  NOT NULL,
    rw           CHAR(3)      DEFAULT NULL,
    rt           CHAR(3)      DEFAULT NULL,
    alamat       VARCHAR(255) NOT NULL,
    nama_kepala  VARCHAR(100) NOT NULL,
    no_hp        VARCHAR(20)  NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_posyandu_kelurahan (kelurahan)
) ENGINE=InnoDB;

-- =====================================================================
-- DATA AWAL
-- =====================================================================

-- Akun admin bawaan -> No HP: 081200000001  Password: admin123
INSERT INTO admin (nama, no_hp, password) VALUES
('Administrator Puskesmas Pamulang', '081200000001',
 '$2y$10$z4UymEIoQDiuEJGA7G7Gj.WKWsFx1IT8.5w5wN6EuoD6F6qo1Qf1u');

-- Akun pengguna contoh -> No HP: 081300000001  Password: pengguna123
INSERT INTO pengguna (nama, no_hp, password, kelurahan, rw, rt, alamat_detail) VALUES
('Ibu Sari Wulandari', '081300000001',
 '$2y$10$LqgNaWNLRmdJLKC4Vn5LnOeU0.bhISQj2yuowhvnKtUZFzb/tbXDu',
 'Pamulang Barat', '001', '002', 'Jl. Siliwangi Gang Mawar No. 4');

-- Delapan kelurahan di Kecamatan Pamulang, masing-masing RW 001-005 dan RT 001-005
INSERT INTO wilayah (kelurahan, rw, rt)
SELECT k.nama, LPAD(w.n,3,'0'), LPAD(t.n,3,'0')
FROM (SELECT 'Pamulang Barat' AS nama UNION ALL SELECT 'Pamulang Timur'
      UNION ALL SELECT 'Pondok Benda'  UNION ALL SELECT 'Pondok Cabe Ilir'
      UNION ALL SELECT 'Pondok Cabe Udik' UNION ALL SELECT 'Benda Baru'
      UNION ALL SELECT 'Bambu Apus'    UNION ALL SELECT 'Kedaung') k
CROSS JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) w
CROSS JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) t;

-- Contoh data Posyandu wilayah kerja Puskesmas Pamulang
-- (silakan ganti dengan data resmi dari Puskesmas saat pengambilan data skripsi)
INSERT INTO posyandu (nama_posyandu, kelurahan, rw, rt, alamat, nama_kepala, no_hp) VALUES
('Posyandu Melati I',   'Pamulang Barat',   '001','002','Jl. Siliwangi No. 12, Pamulang Barat',        'Siti Aminah',      '081211110001'),
('Posyandu Mawar II',   'Pamulang Barat',   '003','001','Jl. Benda Raya No. 5, Pamulang Barat',        'Rohmah Wati',      '081211110002'),
('Posyandu Anggrek',    'Pamulang Timur',   '002','003','Jl. Pamulang Permai Blok C, Pamulang Timur',  'Dewi Lestari',     '081211110003'),
('Posyandu Dahlia',     'Pondok Benda',     '004','002','Jl. Pajajaran No. 21, Pondok Benda',          'Nurhayati',        '081211110004'),
('Posyandu Kenanga',    'Pondok Benda',     '001','004','Jl. Masjid Al-Ikhlas, Pondok Benda',          'Yuni Astuti',      '081211110005'),
('Posyandu Cempaka',    'Pondok Cabe Ilir', '002','001','Jl. Cabe Raya No. 8, Pondok Cabe Ilir',       'Endang Sulastri',  '081211110006'),
('Posyandu Teratai',    'Pondok Cabe Udik', '003','003','Jl. Kampung Sawah, Pondok Cabe Udik',         'Maryam Hasanah',   '081211110007'),
('Posyandu Flamboyan',  'Benda Baru',       '005','002','Jl. Benda Baru Raya No. 17, Benda Baru',      'Tri Wahyuni',      '081211110008'),
('Posyandu Seroja',     'Bambu Apus',       '001','001','Jl. Bambu Apus Raya No. 3, Bambu Apus',       'Lilis Suryani',    '081211110009'),
('Posyandu Sakura',     'Kedaung',          '002','005','Jl. Kedaung Raya No. 44, Kedaung',            'Ratna Komala',     '081211110010'),
('Posyandu Bougenville','Kedaung',          '004','001','Jl. Al-Hidayah, Kedaung',                     'Sri Mulyani',      '081211110011'),
('Posyandu Nusa Indah', 'Pamulang Timur',   '005','004','Jl. Surya Kencana No. 30, Pamulang Timur',    'Herlina Sari',     '081211110012');
