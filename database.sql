-- ===================================================================
-- SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
-- POLITEKNIK NEGERI LHOKSEUMAWE
-- JURUSAN TEKNOLOGI INFORMASI DAN KOMPUTER
-- PROGRAM STUDI TEKNOLOGI REKAYASA PERANGKAT LUNAK
-- File: database.sql
-- ===================================================================

-- 1. Buat Database
CREATE DATABASE IF NOT EXISTS `db_seminar_sidang` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `db_seminar_sidang`;

-- 2. Tabel Admin
CREATE TABLE IF NOT EXISTS `admin` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Mahasiswa
CREATE TABLE IF NOT EXISTS `mahasiswa` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nim` VARCHAR(25) NOT NULL UNIQUE,
    `nama` VARCHAR(100) NOT NULL,
    `tanggal_lahir` DATE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `is_password_changed` TINYINT(1) DEFAULT 1,
    `program_studi` VARCHAR(100) DEFAULT 'Teknologi Rekayasa Perangkat Lunak',
    `email` VARCHAR(100) DEFAULT NULL,
    `no_hp` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Dosen
CREATE TABLE IF NOT EXISTS `dosen` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nidn` VARCHAR(30) NOT NULL UNIQUE,
    `nama_dosen` VARCHAR(100) NOT NULL,
    `gelar_akademik` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL DEFAULT '',
    `program_studi` VARCHAR(100) DEFAULT 'Teknologi Rekayasa Perangkat Lunak',
    `email` VARCHAR(100) NOT NULL,
    `no_hp` VARCHAR(25) NOT NULL,
    `status` ENUM('Aktif', 'Cuti', 'Tidak Aktif') DEFAULT 'Aktif',
    `kuota_maksimal` INT NOT NULL DEFAULT 8,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel Pengajuan Judul Penelitian & Skripsi
CREATE TABLE IF NOT EXISTS `pengajuan_judul` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mahasiswa_id` INT NOT NULL,
    `dosen_id` INT NOT NULL,
    `judul` VARCHAR(255) NOT NULL,
    `latar_belakang` TEXT NOT NULL,
    `file_dokumen` VARCHAR(255) DEFAULT NULL,
    `file_gambar` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('Menunggu', 'Sedang diperiksa', 'Perlu revisi', 'Diterima', 'Ditolak') DEFAULT 'Menunggu',
    `catatan_revisi` TEXT DEFAULT NULL,
    `alasan_penolakan` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pengajuan_mahasiswa` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pengajuan_dosen` FOREIGN KEY (`dosen_id`) REFERENCES `dosen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel Riwayat Komunikasi & Tanggapan Dosen-Mahasiswa
CREATE TABLE IF NOT EXISTS `tanggapan_pengajuan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pengajuan_id` INT NOT NULL,
    `pengirim_role` ENUM('mahasiswa', 'dosen') NOT NULL,
    `pengirim_id` INT NOT NULL,
    `pesan` TEXT NOT NULL,
    `file_lampiran` VARCHAR(255) DEFAULT NULL,
    `status_perubahan` ENUM('Menunggu', 'Sedang diperiksa', 'Perlu revisi', 'Diterima', 'Ditolak', 'Komentar') DEFAULT 'Komentar',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tanggapan_pengajuan` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan_judul` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- DATA AWAL (SEEDER)
-- ===================================================================

-- Akun Admin:
-- Username: admin | Password: admin123
INSERT INTO `admin` (`username`, `password`, `nama_lengkap`) 
VALUES 
('admin', '$2y$10$d5sJoi88QVCcdtSectPjXeoKvNp9py6cDuQ.0i.4LURDO0P2xPrnG', 'Administrator Prodi TRPL')
ON DUPLICATE KEY UPDATE `nama_lengkap` = VALUES(`nama_lengkap`);

-- Data Akun Mahasiswa:
-- Password awal menggunakan tanggal lahir masing-masing mahasiswa (Format DD-MM-YYYY)
-- 1. Zaskia                : 28-08-2008 (Date: 2008-08-28)
-- 2. Cut Inas              : 11-08-2008 (Date: 2008-08-11)
-- 3. Syakira Putri         : 25-03-2008 / 25-3-2008 (Date: 2008-03-25)
-- 4. Rajwa Qamira Balqis   : 01-08-2008 / 1-08-2008 (Date: 2008-08-01)
INSERT INTO `mahasiswa` (`nim`, `nama`, `tanggal_lahir`, `password`, `is_password_changed`, `program_studi`, `email`, `no_hp`)
VALUES 
('2026583050016', 'Zaskia', '2008-08-28', '$2y$10$gf8zAXTM.aRYRxJ4AAKM1ejvcKbmZI/CTtS.Nb2dNDYuNvUE50Ah2', 1, 'Teknologi Rekayasa Perangkat Lunak', 'zaskia@pnl.ac.id', '081234567891'),
('2026583050003', 'Cut Inas', '2008-08-11', '$2y$10$aVONSznUYAgSadxiITZ4hObHomaaocwqHwby6zPJnSbkmKDwESJsS', 1, 'Teknologi Rekayasa Perangkat Lunak', 'cutinas@pnl.ac.id', '081234567892'),
('2026583050014', 'Syakira Putri', '2008-03-25', '$2y$10$eCGXp8mHnXvqvuQidCZPPOw2VHTRcV/Wdl3Jl2PfgseToUZxUC9QC', 1, 'Teknologi Rekayasa Perangkat Lunak', 'syakira@pnl.ac.id', '081234567893'),
('2026583050012', 'Rajwa Qamira Balqis', '2008-08-01', '$2y$10$WW7E4Sam1LTnsrlBh5.hlOBdpZPU94bhoy5xxga6T.USk7WUmn7NC', 1, 'Teknologi Rekayasa Perangkat Lunak', 'rajwa@pnl.ac.id', '081234567894')
ON DUPLICATE KEY UPDATE 
    `nama` = VALUES(`nama`),
    `tanggal_lahir` = VALUES(`tanggal_lahir`),
    `password` = VALUES(`password`);

-- Data Dosen Jurusan TIK / Prodi Teknologi Rekayasa Perangkat Lunak:
-- Digunakan untuk pemilihan Dosen Pembimbing dan Dosen Penguji seminar/sidang
INSERT INTO `dosen` (`nidn`, `nama_dosen`, `gelar_akademik`, `program_studi`, `email`, `no_hp`, `status`)
VALUES
('0012057501', 'Dr. Ir. Hendra Setiawan', 'M.T.', 'Teknologi Rekayasa Perangkat Lunak', 'hendra.setiawan@pnl.ac.id', '08116701234', 'Aktif'),
('0023087802', 'Prof. Dr. Sri Wahyuni', 'M.Kom.', 'Teknologi Rekayasa Perangkat Lunak', 'sri.wahyuni@pnl.ac.id', '08116701235', 'Aktif'),
('0015048203', 'Bambang Sudarsono', 'M.Cs.', 'Teknologi Rekayasa Perangkat Lunak', 'bambang.sudarsono@pnl.ac.id', '08116701236', 'Aktif'),
('0019097404', 'Drs. Agus Wijaya', 'M.Sc., Ph.D.', 'Teknologi Rekayasa Perangkat Lunak', 'agus.wijaya@pnl.ac.id', '08116701237', 'Aktif'),
('0008027905', 'Ir. Muhammad Arifin', 'M.Kom.', 'Teknologi Rekayasa Perangkat Lunak', 'm.arifin@pnl.ac.id', '08116701238', 'Aktif'),
('0014068306', 'Dr. Ratna Dewi', 'S.T., M.Eng.', 'Teknologi Rekayasa Perangkat Lunak', 'ratna.dewi@pnl.ac.id', '08116701239', 'Aktif'),
('0027118507', 'Eko Prasetyo', 'S.Kom., M.T.', 'Teknologi Rekayasa Perangkat Lunak', 'eko.prasetyo@pnl.ac.id', '08116701240', 'Aktif'),
('0003018608', 'Nurul Hidayati', 'M.Kom.', 'Teknologi Rekayasa Perangkat Lunak', 'nurul.hidayati@pnl.ac.id', '08116701241', 'Aktif')
ON DUPLICATE KEY UPDATE 
    `nama_dosen` = VALUES(`nama_dosen`),
    `gelar_akademik` = VALUES(`gelar_akademik`),
    `email` = VALUES(`email`),
    `no_hp` = VALUES(`no_hp`),
    `status` = VALUES(`status`);

-- 7. Tabel Ruangan Seminar & Sidang
CREATE TABLE IF NOT EXISTS `ruangan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kode_ruangan` VARCHAR(30) NOT NULL UNIQUE,
    `nama_ruangan` VARCHAR(100) NOT NULL,
    `gedung` VARCHAR(100) NOT NULL,
    `kapasitas` INT NOT NULL DEFAULT 30,
    `fasilitas` TEXT NULL,
    `status` ENUM('Tersedia', 'Sedang Digunakan', 'Sudah Dipesan') NOT NULL DEFAULT 'Tersedia',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data Awal Ruangan:
INSERT INTO `ruangan` (`kode_ruangan`, `nama_ruangan`, `gedung`, `kapasitas`, `fasilitas`, `status`)
VALUES
('TIK-201', 'Ruang Sidang Utama TIK.201', 'Gedung Jurusan TIK Lt. 2', 40, 'Proyektor, AC, Sound System, Smart Board', 'Sedang Digunakan'),
('DIR-302', 'Ruang Seminar A.302', 'Gedung Direktorat PNL Lt. 3', 50, 'Proyektor, AC, Mic Wireless, Podium', 'Sudah Dipesan'),
('DIR-304', 'Ruang Seminar B.304', 'Gedung Direktorat PNL Lt. 3', 50, 'Proyektor, AC, Sound System', 'Tersedia'),
('TER-405', 'Lab Komputer & Sidang 405', 'Gedung Terpadu PNL Lt. 4', 35, '30 PC Core i7, AC, Proyektor, Smart TV', 'Sudah Dipesan'),
('TIK-101', 'Ruang Rapat & Seminar TIK.101', 'Gedung Jurusan TIK Lt. 1', 30, 'Proyektor, AC, Whiteboard', 'Tersedia'),
('TIK-203', 'Lab Rekayasa Perangkat Lunak', 'Gedung Jurusan TIK Lt. 2', 40, 'High-end Workstations, AC, Proyektor', 'Tersedia'),
('DIR-301', 'Aula Mini Seminar Direktorat', 'Gedung Direktorat PNL Lt. 3', 70, 'Audio System, 2 Proyektor, AC', 'Tersedia'),
('TER-402', 'Ruang Ujian Komprehensif', 'Gedung Terpadu PNL Lt. 4', 25, 'AC, LCD Projector, Whiteboard', 'Tersedia')
ON DUPLICATE KEY UPDATE 
    `nama_ruangan` = VALUES(`nama_ruangan`),
    `gedung` = VALUES(`gedung`),
    `kapasitas` = VALUES(`kapasitas`),
    `fasilitas` = VALUES(`fasilitas`),
    `status` = VALUES(`status`);

-- 8. Tabel Pendaftaran Seminar & Sidang
CREATE TABLE IF NOT EXISTS `pendaftaran_seminar_sidang` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mahasiswa_id` INT NOT NULL,
    `jenis` ENUM('Seminar Proposal', 'Sidang Akhir Skripsi') NOT NULL,
    `kategori` ENUM('seminar', 'sidang') NOT NULL,
    `judul` VARCHAR(255) NOT NULL,
    `dosen_pembimbing_id` INT NOT NULL,
    `dosen_penguji_id` INT NOT NULL,
    `ruangan_id` INT NULL,
    `tanggal` DATE NULL,
    `jam_mulai` TIME NULL,
    `jam_selesai` TIME NULL,
    `status` ENUM('Menunggu Verifikasi', 'Terjadwal', 'Sedang Berlangsung', 'Selesai', 'Ditolak') NOT NULL DEFAULT 'Menunggu Verifikasi',
    `berkas_dokumen` VARCHAR(255) NULL,
    `catatan` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pendaftaran_mhs` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pendaftaran_pembimbing` FOREIGN KEY (`dosen_pembimbing_id`) REFERENCES `dosen`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pendaftaran_penguji` FOREIGN KEY (`dosen_penguji_id`) REFERENCES `dosen`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pendaftaran_ruangan` FOREIGN KEY (`ruangan_id`) REFERENCES `ruangan`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 9. Tabel Kalender Akademik
CREATE TABLE IF NOT EXISTS `kalender_akademik` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_kegiatan` VARCHAR(255) NOT NULL,
    `kategori` VARCHAR(100) NOT NULL,
    `tanggal_mulai` DATE NOT NULL,
    `tanggal_selesai` DATE NOT NULL,
    `waktu_kegiatan` VARCHAR(100) DEFAULT 'Sepanjang Hari',
    `deskripsi` TEXT DEFAULT NULL,
    `semester` ENUM('Ganjil', 'Genap') NOT NULL DEFAULT 'Ganjil',
    `tahun_akademik` VARCHAR(20) NOT NULL DEFAULT '2026/2027',
    `status_publikasi` ENUM('Draft', 'Dipublikasikan') NOT NULL DEFAULT 'Dipublikasikan',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tgl` (`tanggal_mulai`, `tanggal_selesai`),
    INDEX `idx_kategori` (`kategori`),
    INDEX `idx_status` (`status_publikasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
