-- ============================================================
-- DATABASE: Classroom Artificial Intelligence (CAI)
-- Driver  : MySQLi (PHP 8.x)
-- Charset : utf8mb4 (mendukung emoji & karakter internasional)
-- ============================================================

CREATE DATABASE IF NOT EXISTS cai_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cai_db;

-- ------------------------------------------------------------
-- TABEL 1: users
-- Menyimpan akun dosen dan mahasiswa.
-- password disimpan sebagai bcrypt hash (password_hash()).
-- ------------------------------------------------------------
CREATE TABLE users (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    full_name   VARCHAR(100) DEFAULT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('mahasiswa','dosen') NOT NULL,
    nrp_nip     VARCHAR(20)  DEFAULT NULL,
    tahun_masuk YEAR         DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL 2: courses (Mata Kuliah)
-- Setiap mata kuliah dimiliki oleh satu dosen.
-- ------------------------------------------------------------
CREATE TABLE courses (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    kode_mk    VARCHAR(20)  NOT NULL UNIQUE,
    nama_mk    VARCHAR(100) NOT NULL,
    sks        INT          NOT NULL DEFAULT 3,
    deskripsi  TEXT         DEFAULT NULL,
    dosen_id   INT          NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL 3: enrollments
-- Relasi Many-to-Many antara mahasiswa dan mata kuliah.
-- ------------------------------------------------------------
CREATE TABLE enrollments (
    id          INT       AUTO_INCREMENT PRIMARY KEY,
    user_id     INT       NOT NULL,
    course_id   INT       NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment (user_id, course_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL 4: course_weeks (Minggu Perkuliahan)
-- Setiap mata kuliah terdiri dari 1-16 minggu.
-- ------------------------------------------------------------
CREATE TABLE course_weeks (
    id               INT          AUTO_INCREMENT PRIMARY KEY,
    course_id        INT          NOT NULL,
    minggu_ke        INT          NOT NULL,
    judul_minggu     VARCHAR(255) DEFAULT NULL,
    deskripsi_minggu TEXT         DEFAULT NULL,
    UNIQUE KEY uq_week (course_id, minggu_ke),
    CONSTRAINT chk_minggu CHECK (minggu_ke BETWEEN 1 AND 16),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL 5: materials (Materi Perkuliahan)
-- Setiap minggu dapat memiliki lebih dari satu materi.
-- jenis_file menentukan apakah berkas PDF, PPT, atau tautan URL.
-- ------------------------------------------------------------
CREATE TABLE materials (
    id             INT          AUTO_INCREMENT PRIMARY KEY,
    week_id        INT          NOT NULL,
    judul          VARCHAR(255) NOT NULL,
    jenis_file     ENUM('pdf','ppt','link') NOT NULL,
    link_atau_file TEXT         NOT NULL,   -- path file di /uploads/ atau URL
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (week_id) REFERENCES course_weeks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL 6: progress (Progres Belajar Mahasiswa)
-- is_completed diperbarui via asynchronous fetch dari checkbox.
-- UNIQUE KEY memastikan satu baris per mahasiswa per minggu per MK.
-- ------------------------------------------------------------
CREATE TABLE progress (
    id           INT       AUTO_INCREMENT PRIMARY KEY,
    user_id      INT       NOT NULL,
    course_id    INT       NOT NULL,
    minggu_ke    INT       NOT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_progress (user_id, course_id, minggu_ke),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL 7: chat_history (Riwayat Chat AI / CAI)
-- Menyimpan tanya-jawab mahasiswa dengan Gemini API.
-- feedback: thumbs up/down dari mahasiswa.
-- is_verified & feedback_dosen: hasil validasi dosen.
-- ------------------------------------------------------------
CREATE TABLE chat_history (
    id             INT       AUTO_INCREMENT PRIMARY KEY,
    user_id        INT       NOT NULL,
    course_id      INT       DEFAULT NULL,  -- konteks mata kuliah saat chat
    minggu_ke      INT       DEFAULT NULL,  -- konteks minggu saat chat
    pertanyaan     TEXT      NOT NULL,
    jawaban        TEXT      NOT NULL,
    feedback       ENUM('up','down','none') NOT NULL DEFAULT 'none',
    is_verified    TINYINT(1) NOT NULL DEFAULT 0,
    feedback_dosen TEXT      DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL 8: grades (Nilai Akhir)
-- Input manual oleh dosen: A, B, C, D, E, atau F.
-- UNIQUE KEY mencegah duplikasi nilai per mahasiswa per MK.
-- ------------------------------------------------------------
CREATE TABLE grades (
    id         INT       AUTO_INCREMENT PRIMARY KEY,
    user_id    INT       NOT NULL,
    course_id  INT       NOT NULL,
    nilai      CHAR(1)   DEFAULT NULL,
    keterangan TEXT      DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_grade (user_id, course_id),
    CONSTRAINT chk_nilai CHECK (nilai IN ('A','B','C','D','E','F') OR nilai IS NULL),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DATA SAMPEL (untuk keperluan pengujian / demo)
-- Password semua akun demo: admin123
-- Hash bcrypt dihasilkan dengan: password_hash('admin123', PASSWORD_DEFAULT)
-- GANTI password sebelum deploy ke production!
-- ============================================================

-- Akun Dosen
INSERT INTO users (full_name, username, email, password, role, nrp_nip, tahun_masuk, is_active) VALUES
('Dr. Budi Santoso, M.Kom', 'dosen_budi', 'budi@kampus.ac.id',  '$2y$10$pqE8Nj24hTci6gnic2lrLevGP6fESF1.AXeqN4nxT0w4i2uJpl68e', 'dosen',     '198501012010011001', NULL, 1),
('Dr. Sari Utami, M.T',     'dosen_sari', 'sari@kampus.ac.id',  '$2y$10$pqE8Nj24hTci6gnic2lrLevGP6fESF1.AXeqN4nxT0w4i2uJpl68e', 'dosen',     '197803152005012002', NULL, 1);

-- Akun Mahasiswa
INSERT INTO users (full_name, username, email, password, role, nrp_nip, tahun_masuk, is_active) VALUES
('Andi Pratama',    'mhs_andi', 'andi@student.ac.id', '$2y$10$pqE8Nj24hTci6gnic2lrLevGP6fESF1.AXeqN4nxT0w4i2uJpl68e', 'mahasiswa', '225150200111001', 2022, 1),
('Rina Kusumawati', 'mhs_rina', 'rina@student.ac.id', '$2y$10$pqE8Nj24hTci6gnic2lrLevGP6fESF1.AXeqN4nxT0w4i2uJpl68e', 'mahasiswa', '225150200111002', 2022, 1),
('Doni Firmansyah', 'mhs_doni', 'doni@student.ac.id', '$2y$10$pqE8Nj24hTci6gnic2lrLevGP6fESF1.AXeqN4nxT0w4i2uJpl68e', 'mahasiswa', '235150200111001', 2023, 1);

-- Mata Kuliah
INSERT INTO courses (kode_mk, nama_mk, sks, deskripsi, dosen_id) VALUES
('PWEB01', 'Pemrograman Web',              3, 'Mata kuliah yang membahas pengembangan aplikasi berbasis web menggunakan HTML, CSS, JavaScript, dan PHP.', 1),
('BASDAT', 'Basis Data',                   3, 'Mata kuliah yang membahas perancangan dan implementasi basis data relasional menggunakan MySQL.',            1),
('KECBUT', 'Kecerdasan Buatan',            3, 'Pengantar AI: machine learning, neural network, dan penerapan AI dalam berbagai bidang.',                   2);

-- Enrollments (mahasiswa mendaftar ke mata kuliah)
INSERT INTO enrollments (user_id, course_id) VALUES
(3, 1), (3, 2), (3, 3),   -- andi di semua MK
(4, 1), (4, 2),            -- rina di PWEB & BASDAT
(5, 1), (5, 3);            -- doni di PWEB & KECBUT

-- Minggu Perkuliahan (PWEB01 - 4 minggu pertama sebagai sampel)
INSERT INTO course_weeks (course_id, minggu_ke, judul_minggu, deskripsi_minggu) VALUES
(1,  1, 'Pengantar Web & HTML5',         'Sejarah web, cara kerja HTTP, struktur dokumen HTML5, tag semantik.'),
(1,  2, 'CSS3 & Styling Modern',         'Selector CSS, box model, flexbox, grid, CSS variables, dark mode.'),
(1,  3, 'JavaScript Dasar',              'Variabel, tipe data, fungsi, DOM manipulation, event listener.'),
(1,  4, 'PHP Native & Form Handling',    'Sintaks PHP, superglobal $_POST/$_GET, validasi server-side.'),
(1,  5, 'MySQLi & Prepared Statements',  'Koneksi MySQLi OOP, parameterized query, CRUD operasi dasar.'),
(2,  1, 'Pengantar Basis Data',          'Konsep DBMS, model relasional, ERD, kunci primer dan asing.'),
(2,  2, 'SQL DDL & DML',                 'CREATE, ALTER, DROP, INSERT, UPDATE, DELETE, SELECT.'),
(3,  1, 'Pengantar Kecerdasan Buatan',   'Sejarah AI, cabang-cabang AI, aplikasi AI di dunia nyata.'),
(3,  2, 'Machine Learning Dasar',        'Supervised vs Unsupervised learning, algoritma dasar.');

-- Materi (per minggu)
INSERT INTO materials (week_id, judul, jenis_file, link_atau_file) VALUES
(1, 'Slide Pengantar Web',         'ppt',  'uploads/pweb_w1_slide.pptx'),
(1, 'Modul HTML5 Lengkap',         'pdf',  'uploads/pweb_w1_modul.pdf'),
(1, 'Referensi MDN Web Docs',      'link', 'https://developer.mozilla.org/id/docs/Web/HTML'),
(2, 'Slide CSS3 & Flexbox',        'ppt',  'uploads/pweb_w2_slide.pptx'),
(2, 'Cheat Sheet CSS Variables',   'pdf',  'uploads/pweb_w2_cheatsheet.pdf'),
(3, 'Slide JavaScript ES6+',       'ppt',  'uploads/pweb_w3_slide.pptx'),
(3, 'Latihan DOM Manipulation',    'pdf',  'uploads/pweb_w3_latihan.pdf'),
(4, 'Slide PHP Native',            'ppt',  'uploads/pweb_w4_slide.pptx'),
(5, 'Slide MySQLi OOP',            'ppt',  'uploads/pweb_w5_slide.pptx'),
(5, 'Contoh Prepared Statements',  'pdf',  'uploads/pweb_w5_contoh.pdf'),
(6, 'Slide Pengantar Basis Data',  'ppt',  'uploads/bd_w1_slide.pptx'),
(7, 'Modul SQL DDL & DML',         'pdf',  'uploads/bd_w2_modul.pdf'),
(8, 'Slide Pengantar AI',          'ppt',  'uploads/ai_w1_slide.pptx'),
(9, 'Modul Machine Learning',      'pdf',  'uploads/ai_w2_modul.pdf');

-- Progress Belajar (andi sudah selesai minggu 1-2 PWEB, minggu 1 BASDAT)
INSERT INTO progress (user_id, course_id, minggu_ke, is_completed) VALUES
(3, 1, 1, 1),
(3, 1, 2, 1),
(3, 1, 3, 0),
(3, 2, 1, 1),
(4, 1, 1, 1);

-- Riwayat Chat AI (contoh interaksi mahasiswa dengan CAI)
INSERT INTO chat_history (user_id, course_id, minggu_ke, pertanyaan, jawaban, feedback, is_verified, feedback_dosen) VALUES
(3, 1, 1,
 'Apa perbedaan antara tag <div> dan <section> di HTML5?',
 'Tag <div> adalah elemen blok generik tanpa makna semantik, sedangkan <section> adalah elemen semantik yang mengelompokkan konten bertema yang memiliki judul. Gunakan <section> saat konten tersebut bisa berdiri sendiri dalam outline dokumen.',
 'up', 1, NULL),
(3, 1, 2,
 'Bagaimana cara membuat layout responsif dengan Flexbox?',
 'Untuk layout responsif dengan Flexbox, set container dengan display:flex lalu gunakan flex-wrap:wrap. Tambahkan media query untuk mengubah flex-direction dari row ke column pada layar kecil. Properti flex-grow dan flex-basis membantu distribusi ruang otomatis.',
 'up', 0, NULL),
(4, 1, 1,
 'Apa itu semantic HTML dan mengapa penting?',
 'Semantic HTML menggunakan tag yang mendeskripsikan makna konten, bukan hanya tampilannya. Contohnya: <header>, <nav>, <main>, <article>, <footer>. Penting untuk aksesibilitas (screen reader), SEO, dan keterbacaan kode.',
 'down', 1, 'Jawaban sudah benar secara umum. Tambahkan contoh konkret keuntungan SEO: mesin pencari lebih mudah mengindeks konten yang tersusun semantik.');

-- Nilai Akhir (sampel)
INSERT INTO grades (user_id, course_id, nilai, keterangan) VALUES
(3, 2, 'A', 'Aktif dan nilai UAS sangat baik.'),
(4, 2, 'B', 'Pengerjaan tugas konsisten, perlu peningkatan pada UAS.');
