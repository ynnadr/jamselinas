-- Jamselinas XV Bandung 2026 - Database Schema
-- MySQL

CREATE DATABASE IF NOT EXISTS jamselinas_2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jamselinas_2026;

-- Tabel Admin
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin (password: admin123)
INSERT INTO admins (username, password, nama) VALUES 
('admin', '$2y$10$jjU.2M8SRowf06SWW8xuUuM5ox87xry2jGLiafjDzLO6TBDscm7B6', 'Administrator');

-- Tabel Peserta
CREATE TABLE peserta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(32) NOT NULL UNIQUE,
    nomor_peserta VARCHAR(20) DEFAULT NULL UNIQUE,
    
    -- Data Pribadi
    nama_lengkap VARCHAR(100) NOT NULL,
    nama_panggilan VARCHAR(50) DEFAULT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    tanggal_lahir DATE DEFAULT NULL,
    jenis_kelamin ENUM('L', 'P') DEFAULT NULL,
    kota_asal VARCHAR(100) NOT NULL,
    alamat TEXT DEFAULT NULL,
    
    -- Komunitas
    nama_komunitas VARCHAR(100) DEFAULT 'Individu',
    kota_komunitas VARCHAR(100) DEFAULT NULL,
    
    -- Jersey
    ukuran_jersey1 VARCHAR(10) NOT NULL,
    ukuran_jersey2 VARCHAR(10) NOT NULL,
    
    -- Sepeda
    merk_sepeda VARCHAR(100) DEFAULT NULL,
    
    -- Kontak Darurat
    kontak_darurat_nama VARCHAR(100) NOT NULL,
    kontak_darurat_hubungan VARCHAR(50) DEFAULT NULL,
    kontak_darurat_wa VARCHAR(20) NOT NULL,
    
    -- Pembayaran
    paket ENUM('early_bird', 'regular', 'late') DEFAULT 'regular',
    harga INT NOT NULL DEFAULT 350000,
    bukti_pembayaran VARCHAR(255) DEFAULT NULL,
    status_pembayaran ENUM('menunggu', 'terverifikasi', 'ditolak') DEFAULT 'menunggu',
    verified_at DATETIME DEFAULT NULL,
    verified_by INT DEFAULT NULL,
    
    -- Status Acara
    status_ridepack ENUM('belum', 'siap', 'sudah_diambil') DEFAULT 'belum',
    status_absen ENUM('belum', 'hadir') DEFAULT 'belum',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (verified_by) REFERENCES admins(id)
);

-- Index untuk pencarian cepat
CREATE INDEX idx_token ON peserta(token);
CREATE INDEX idx_nomor ON peserta(nomor_peserta);
CREATE INDEX idx_status_bayar ON peserta(status_pembayaran);
CREATE UNIQUE INDEX idx_whatsapp ON peserta(whatsapp);