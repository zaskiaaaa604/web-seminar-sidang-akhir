<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: panduan.php (Panduan Penggunaan Sistem SIM-SEMSID - Publik)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

$is_logged_in = isset($_SESSION['login']) && $_SESSION['login'] === true;
$user_role    = $_SESSION['role'] ?? '';
$user_name    = $_SESSION['nama'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panduan Penggunaan Sistem - SIM-SEMSID PNL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .page-header-banner {
            background: linear-gradient(135deg, #0f172a, #1e3a8a);
            color: white;
            padding: 40px 0 35px;
            text-align: center;
        }
        .page-header-banner h1 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 10px;
        }
        .page-header-banner p {
            font-size: 1rem;
            color: #cbd5e1;
            max-width: 650px;
            margin: 0 auto;
        }

        .guide-nav-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin: 25px 0 35px;
        }
        .guide-pill-btn {
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 50px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #334155;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .guide-pill-btn:hover {
            border-color: #ea580c;
            color: #ea580c;
            transform: translateY(-2px);
        }

        .guide-card-box {
            background: white;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            scroll-margin-top: 100px;
        }
        .guide-card-title {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f1f5f9;
        }
        .guide-icon-badge {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(234, 88, 12, 0.12);
            color: #ea580c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .step-instruction-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 16px;
        }
        .step-instruction-item {
            display: flex;
            gap: 16px;
            background: #f8fafc;
            padding: 18px 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .step-instruction-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1e3a8a;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.95rem;
            flex-shrink: 0;
        }
        .step-instruction-item h5 {
            margin: 0 0 6px 0;
            font-size: 1.05rem;
            color: #0f172a;
        }
        .step-instruction-item p {
            margin: 0;
            font-size: 0.88rem;
            color: #475569;
            line-height: 1.45;
        }

        .tip-callout {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 12px 18px;
            border-radius: 0 8px 8px 0;
            margin-top: 14px;
            font-size: 0.86rem;
            color: #1e3a8a;
        }
    </style>
</head>
<body>

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="container topbar-content">
            <div class="topbar-info">
                <span><i class="fas fa-university"></i> Politeknik Negeri Lhokseumawe</span>
                <span><i class="fas fa-code-branch"></i> Program Studi Teknologi Rekayasa Perangkat Lunak</span>
            </div>
            <div class="topbar-info">
                <span><i class="fas fa-clock"></i> Pelayanan: Senin - Jumat (08:00 - 16:00 WIB)</span>
            </div>
        </div>
    </div>

    <!-- NAVBAR 5 MENU UTAMA -->
    <header class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="logo-area">
                <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="logo-text">
                    <h1>SIM-SEMSID</h1>
                    <span>POLITEKNIK NEGERI LHOKSEUMAWE</span>
                </div>
            </a>

            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php" class="nav-link">Beranda</a></li>
                <li><a href="jadwal_kalender.php" class="nav-link">Jadwal & Kalender</a></li>
                <li><a href="informasi_pendaftaran.php" class="nav-link">Informasi Pendaftaran</a></li>
                <li><a href="panduan.php" class="nav-link active">Panduan</a></li>
            </ul>

            <div class="nav-buttons">
                <?php if ($is_logged_in): ?>
                    <a href="<?= ($user_role === 'admin') ? 'dashboard_admin.php' : (($user_role === 'dosen') ? 'dashboard_dosen.php' : 'dashboard_mahasiswa.php'); ?>" class="btn btn-primary">
                        <i class="fas fa-user-circle"></i> Dashboard (<?= htmlspecialchars($user_name); ?>)
                    </a>
                <?php else: ?>
                    <a href="login_mahasiswa.php" class="btn btn-primary"><i class="fas fa-user-graduate"></i> Login Mahasiswa</a>
                    <a href="login_dosen.php" class="btn btn-outline-admin"><i class="fas fa-chalkboard-teacher"></i> Login Dosen</a>
                    <a href="login_admin.php" class="btn btn-outline-admin"><i class="fas fa-user-shield"></i> Login Admin</a>
                <?php endif; ?>
            </div>

            <button class="menu-toggle" id="menuToggle" aria-label="Buka Menu Navigasi"><i class="fas fa-bars"></i></button>
        </div>
    </header>

    <!-- BANNER -->
    <div class="page-header-banner">
        <div class="container">
            <h1>Panduan Penggunaan Sistem</h1>
            <p>Petunjuk praktis langkah demi langkah untuk mahasiswa dan civitas akademika dalam memanfaatkan seluruh layanan portal SIM-SEMSID.</p>
        </div>
    </div>

    <main class="container" style="padding: 20px 0 60px;">

        <!-- PILL SHORTCUTS -->
        <div class="guide-nav-pills">
            <a href="#panduan-login" class="guide-pill-btn"><i class="fas fa-sign-in-alt"></i> 1. Cara Login</a>
            <a href="#panduan-judul" class="guide-pill-btn"><i class="fas fa-book-bookmark"></i> 2. Pengajuan Judul</a>
            <a href="#panduan-seminar" class="guide-pill-btn"><i class="fas fa-chalkboard-user"></i> 3. Daftar Seminar</a>
            <a href="#panduan-sidang" class="guide-pill-btn"><i class="fas fa-graduation-cap"></i> 4. Daftar Sidang Akhir</a>
            <a href="#panduan-jadwal" class="guide-pill-btn"><i class="fas fa-calendar-check"></i> 5. Lihat Jadwal & Ruangan</a>
        </div>

        <!-- 1. PANDUAN CARA LOGIN MAHASISWA -->
        <section class="guide-card-box" id="panduan-login">
            <div class="guide-card-title">
                <div class="guide-icon-badge"><i class="fas fa-user-lock"></i></div>
                <div>
                    <h3 style="margin: 0; font-size: 1.3rem; color: #0f172a;">1. Cara Login Mahasiswa</h3>
                    <span style="font-size: 0.85rem; color: #64748b;">Akses masuk ke dashboard pribadi mahasiswa SIM-SEMSID</span>
                </div>
            </div>

            <ol class="step-instruction-list">
                <li class="step-instruction-item">
                    <div class="step-instruction-num">1</div>
                    <div>
                        <h5>Kunjungi Halaman Login Mahasiswa</h5>
                        <p>Klik tombol <strong>Login Mahasiswa</strong> pada bagian kanan atas navbar atau pada kartu portal beranda utama.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">2</div>
                    <div>
                        <h5>Masukkan Nomor Induk Mahasiswa (NIM)</h5>
                        <p>Ketik NIM resmi Anda yang telah terdaftar di pangkalan data Politeknik Negeri Lhokseumawe (contoh: 2026583050014).</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">3</div>
                    <div>
                        <h5>Masukkan Password Akun Anda</h5>
                        <p>Untuk login pertama kali, gunakan password default atau tanggal lahir Anda. Jika sudah pernah mengubah password, gunakan password terbaru.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">4</div>
                    <div>
                        <h5>Masuk ke Dashboard</h5>
                        <p>Klik tombol <strong>Masuk ke Portal</strong>. Setelah berhasil, Anda akan langsung diarahkan ke Dashboard Mahasiswa.</p>
                    </div>
                </li>
            </ol>
            <div class="tip-callout">
                <i class="fas fa-lightbulb"></i> <strong>Tips Keamanan:</strong> Jangan membagikan password Anda kepada siapa pun. Anda dapat mengubah password sewaktu-waktu melalui menu Profil Akun.
            </div>
        </section>

        <!-- 2. PANDUAN CARA MENGAJUKAN JUDUL -->
        <section class="guide-card-box" id="panduan-judul">
            <div class="guide-card-title">
                <div class="guide-icon-badge"><i class="fas fa-book-bookmark"></i></div>
                <div>
                    <h3 style="margin: 0; font-size: 1.3rem; color: #0f172a;">2. Cara Mengajukan Judul Skripsi</h3>
                    <span style="font-size: 0.85rem; color: #64748b;">Pengajuan usulan topik penelitian skripsi kepada Dosen Pembimbing</span>
                </div>
            </div>

            <ol class="step-instruction-list">
                <li class="step-instruction-item">
                    <div class="step-instruction-num">1</div>
                    <div>
                        <h5>Buka Menu Pengajuan Judul</h5>
                        <p>Pada sidebar Dashboard Mahasiswa, pilih menu <strong>Pengajuan Judul</strong> lalu klik tombol <strong>+ Ajukan Judul Baru</strong>.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">2</div>
                    <div>
                        <h5>Isi Formulir Usulan Judul</h5>
                        <p>Ketik judul skripsi yang diusulkan secara jelas dan spesifik. Tuliskan ringkasan latar belakang masalah dan rumusan tujuan penelitian.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">3</div>
                    <div>
                        <h5>Pilih Calon Dosen Pembimbing</h5>
                        <p>Pilih dosen pembimbing yang bidang keahliannya sesuai dengan topik penelitian Anda (sistem otomatis membatasi kuota maksimal 8 bimbingan per dosen).</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">4</div>
                    <div>
                        <h5>Unggah Dokumen Draft / Proposal Awal</h5>
                        <p>Lampirkan draft outline naskah dalam format PDF (maksimal 5 MB) lalu klik <strong>Kirim Pengajuan</strong>.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">5</div>
                    <div>
                        <h5>Pantau Status Verifikasi Dosen</h5>
                        <p>Dosen pembimbing akan meninjau pengajuan Anda. Notifikasi otomatis akan dikirimkan saat status berubah menjadi <em>Diterima</em>, <em>Perlu Revisi</em>, atau <em>Ditolak</em>.</p>
                    </div>
                </li>
            </ol>
        </section>

        <!-- 3. PANDUAN CARA MENDAFTAR SEMINAR PROPOSAL & HASIL -->
        <section class="guide-card-box" id="panduan-seminar">
            <div class="guide-card-title">
                <div class="guide-icon-badge"><i class="fas fa-chalkboard-user"></i></div>
                <div>
                    <h3 style="margin: 0; font-size: 1.3rem; color: #0f172a;">3. Cara Mendaftar Seminar Proposal & Hasil</h3>
                    <span style="font-size: 0.85rem; color: #64748b;">Langkah pendaftaran ujian seminar secara online</span>
                </div>
            </div>

            <ol class="step-instruction-list">
                <li class="step-instruction-item">
                    <div class="step-instruction-num">1</div>
                    <div>
                        <h5>Pastikan Judul Telah Disetujui</h5>
                        <p>Syarat utama pendaftaran seminar adalah usulan judul Anda telah berstatus <strong>Diterima</strong> oleh Dosen Pembimbing.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">2</div>
                    <div>
                        <h5>Buka Menu Pendaftaran Seminar</h5>
                        <p>Klik menu <strong>Pendaftaran Ujian</strong> &gt; <strong>Seminar Proposal</strong> pada dashboard akun mahasiswa Anda.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">3</div>
                    <div>
                        <h5>Unggah Berkas Persyaratan Lengkap</h5>
                        <p>Unggah file naskah proposal skripsi (Bab 1-3), lembar persetujuan pembimbing, dan transkrip nilai sementara.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">4</div>
                    <div>
                        <h5>Verifikasi Admin & Penerbitan Jadwal</h5>
                        <p>Admin program studi akan memverifikasi berkas Anda, menentukan ruangan, dan menetapkan dosen penguji. Jadwal resmi akan diumumkan secara otomatis.</p>
                    </div>
                </li>
            </ol>
        </section>

        <!-- 4. PANDUAN CARA MENDAFTAR SIDANG AKHIR -->
        <section class="guide-card-box" id="panduan-sidang">
            <div class="guide-card-title">
                <div class="guide-icon-badge"><i class="fas fa-graduation-cap"></i></div>
                <div>
                    <h3 style="margin: 0; font-size: 1.3rem; color: #0f172a;">4. Cara Mendaftar Sidang Akhir Skripsi</h3>
                    <span style="font-size: 0.85rem; color: #64748b;">Pendaftaran tahap akhir kelulusan sarjana terapan</span>
                </div>
            </div>

            <ol class="step-instruction-list">
                <li class="step-instruction-item">
                    <div class="step-instruction-num">1</div>
                    <div>
                        <h5>Selesaikan Bimbingan & Naskah Final</h5>
                        <p>Pastikan riwayat bimbingan Anda telah disetujui penuh oleh pembimbing dan draft naskah Bab 1 s/d Bab 5 telah siap diujikan.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">2</div>
                    <div>
                        <h5>Lengkapi Dokumen Bebas Pustaka & TOEFL</h5>
                        <p>Siapkan bukti bebas tanggungan laboratorium dan sertifikat kemampuan bahasa Inggris sesuai standar nilai yang berlaku di PNL.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">3</div>
                    <div>
                        <h5>Kirim Formulir Pendaftaran Sidang</h5>
                        <p>Buka menu <strong>Pendaftaran Sidang Akhir</strong>, isi form, unggah dokumen kelayakan, lalu klik tombol <strong>Kirim Pendaftaran Sidang</strong>.</p>
                    </div>
                </li>
            </ol>
        </section>

        <!-- 5. PANDUAN CARA MELIHAT JADWAL SEMINAR & SIDANG -->
        <section class="guide-card-box" id="panduan-jadwal">
            <div class="guide-card-title">
                <div class="guide-icon-badge"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <h3 style="margin: 0; font-size: 1.3rem; color: #0f172a;">5. Cara Melihat Jadwal Seminar & Sidang</h3>
                    <span style="font-size: 0.85rem; color: #64748b;">Mengecek waktu ujian, nama dewan penguji, dan agenda akademik</span>
                </div>
            </div>

            <ol class="step-instruction-list">
                <li class="step-instruction-item">
                    <div class="step-instruction-num">1</div>
                    <div>
                        <h5>Tanpa Perlu Login (Akses Terbuka)</h5>
                        <p>Pengunjung, mahasiswa, maupun dosen dapat langsung membuka menu <strong>Jadwal & Kalender</strong> pada navbar bagian atas website.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">2</div>
                    <div>
                        <h5>Gunakan Fitur Pencarian & Filter Tanggal</h5>
                        <p>Ketik nama Anda, NIM, atau judul skripsi pada kotak pencarian untuk melihat waktu pelaksanaan dan susunan dewan penelaah/penguji secara akurat.</p>
                    </div>
                </li>
                <li class="step-instruction-item">
                    <div class="step-instruction-num">3</div>
                    <div>
                        <h5>Pantau Kalender Akademik</h5>
                        <p>Pilih tab <strong>Kalender Akademik</strong> untuk melihat rentang tanggal penting seperti batas pengumpulan naskah, pembagian jadwal, dan yudisium.</p>
                    </div>
                </li>
            </ol>
        </section>

    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container footer-grid">
            <div class="footer-col">
                <h4>SIM-SEMSID PNL</h4>
                <p>Sistem Informasi Manajemen Seminar dan Sidang Akhir Mahasiswa Politeknik Negeri Lhokseumawe. Jurusan Teknologi Informasi dan Komputer (TIK).</p>
            </div>
            <div class="footer-col">
                <h4>Navigasi Utama</h4>
                <ul class="footer-links">
                    <li><a href="index.php"><i class="fas fa-angle-right"></i> Beranda</a></li>
                    <li><a href="jadwal_kalender.php"><i class="fas fa-angle-right"></i> Jadwal & Kalender</a></li>
                    <li><a href="informasi_pendaftaran.php"><i class="fas fa-angle-right"></i> Informasi Pendaftaran</a></li>
                    <li><a href="panduan.php"><i class="fas fa-angle-right"></i> Panduan Pengguna</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Akses Masuk</h4>
                <ul class="footer-links">
                    <li><a href="login_mahasiswa.php"><i class="fas fa-angle-right"></i> Portal Mahasiswa</a></li>
                    <li><a href="login_dosen.php"><i class="fas fa-angle-right"></i> Portal Dosen</a></li>
                    <li><a href="login_admin.php"><i class="fas fa-angle-right"></i> Portal Administrator</a></li>
                </ul>
            </div>
        </div>
        <div class="container footer-bottom">
            <p>&copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Politeknik Negeri Lhokseumawe. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const navMenu = document.getElementById('navMenu');
        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', function() {
                navMenu.classList.toggle('active');
            });
        }
    </script>
</body>
</html>
