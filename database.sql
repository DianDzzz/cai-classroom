CREATE DATABASE cai_db;
USE cai_db;

-- Tabel User (Multi-user: Dosen & Mahasiswa)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('mahasiswa', 'dosen') NOT NULL,
    nrp_nip VARCHAR(20), -- NRP untuk mhs, NIP untuk dosen
    tahun_masuk INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Mata Kuliah
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_mk VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    dosen_id INT,
    FOREIGN KEY (dosen_id) REFERENCES users(id)
);

-- Tabel Minggu (Customizable oleh Dosen)
CREATE TABLE course_weeks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    minggu_ke INT,
    deskripsi_minggu TEXT,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Tabel Materi (File/Link)
CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    week_id INT,
    judul_materi VARCHAR(255),
    file_path TEXT, -- Menyimpan nama file di folder uploads/
    FOREIGN KEY (week_id) REFERENCES course_weeks(id) ON DELETE CASCADE
);

-- Tabel Nilai (Input Dosen: A-F)
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    course_id INT,
    nilai CHAR(1), -- A, B, C, D, E, F
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
);