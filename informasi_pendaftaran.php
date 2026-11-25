<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: informasi_pendaftaran.php (Informasi Persyaratan & Alur Pendaftaran - Publik)
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
    <title>Informasi Pendaftaran Seminar & Sidang - SIM-SEMSID PNL</title>
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

        .syarat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin: 30px 0 40px;
        }
        .syarat-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 26px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .syarat-card:hover {
            transform: translateY(-4px);
            border-color: #ea580c;
            box-shadow: 0 10px 25px rgba(234, 88, 12, 0.08);
        }
        .syarat-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .syarat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: rgba(234, 88, 12, 0.1);
            color: #ea580c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .syarat-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
        }
        .syarat-list li {
            padding: 8px 0;
            font-size: 0.9rem;
            color: #334155;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-bottom: 1px dashed #f1f5f9;
        }
        .syarat-list li i {
            color: #10b981;
            margin-top: 4px;
            font-size: 0.85rem;
        }

        .cta-box-pendaftaran {
            background: linear-gradient(135deg, #1e3a8a, #0f172a);
            color: white;
            padding: 35px 30px;
            border-radius: 14px;
            text-align: center;
            margin: 40px 0;
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.2);
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
                <li><a href="informasi_pendaftaran.php" class="nav-link active">Informasi Pendaftaran</a></li>
                <li><a href="panduan.php" class="nav-link">Panduan</a></li>
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
            <h1>Informasi & Persyaratan Pendaftaran</h1>
            <p>Ketentuan berkas, alur tahapan pendaftaran, dan persyaratan akademik pelaksanaan Seminar Proposal, Seminar Hasil, dan Sidang Akhir Skripsi TIK.</p>
        </div>
    </div>

    <main class="container" style="padding: 30px 0 60px;">

        <!-- 3 KARTU PERSYARATAN -->
        <div class="section-title" style="text-align: center; margin-bottom: 25px;">
            <span class="badge"><i class="fas fa-clipboard-check"></i> Ketentuan Akademik</span>
            <h2>Persyaratan Kegiatan Ujian</h2>
            <p>Pelajari seluruh persyaratan wajib sebelum mengajukan pendaftaran secara online pada portal mahasiswa.</p>
        </div>

        <div class="syarat-grid">
            <!-- 1. Seminar Proposal -->
            <div class="syarat-card">
                <div>
                    <div class="syarat-header">
                        <div class="syarat-icon"><i class="fas fa-chalkboard-user"></i></div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a;">Seminar Proposal</h3>
                            <span style="font-size: 0.8rem; color: #64748b;">Tahap Awal Penelitian Skripsi</span>
                        </div>
                    </div>
                    <ul class="syarat-list">
                        <li><i class="fas fa-check-circle"></i> <span>Telah menempuh minimal 90 SKS dengan IPK &ge; 2.75</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Telah lulus mata kuliah Metodologi Penelitian</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Judul skripsi telah disetujui resmi oleh calon Dosen Pembimbing</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Naskah Proposal Skripsi lengkap (Bab I, II, dan III) dalam format PDF</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Lembar Persetujuan Seminar Proposal bertandatangan Pembimbing</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Transkrip Nilai Sementara dan Kartu Rencana Studi (KRS) aktif</span></li>
                    </ul>
                </div>
                <div>
                    <a href="login_mahasiswa.php" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center;">
                        <i class="fas fa-paper-plane"></i> Daftar Seminar Proposal
                    </a>
                </div>
            </div>

            <!-- 2. Seminar Hasil -->
            <div class="syarat-card">
                <div>
                    <div class="syarat-header">
                        <div class="syarat-icon" style="background: rgba(13, 148, 136, 0.1); color: #0d9488;"><i class="fas fa-laptop-code"></i></div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a;">Seminar Hasil</h3>
                            <span style="font-size: 0.8rem; color: #64748b;">Tahap Presentasi Produk & Pengujian</span>
                        </div>
                    </div>
                    <ul class="syarat-list">
                        <li><i class="fas fa-check-circle"></i> <span>Telah menyelesaikan revisi perbaikan Seminar Proposal</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Telah melaksanakan minimal 8 kali catatan riwayat bimbingan resmi</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Produk/aplikasi perangkat lunak telah selesai dibangun dan siap didemokan</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Draft Naskah Skripsi lengkap (Bab I s/d Bab V) dalam format PDF</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Lembar Persetujuan Seminar Hasil bertandatangan Dosen Pembimbing</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Bukti uji coba sistem / hasil pengujian blackbox perangkat lunak</span></li>
                    </ul>
                </div>
                <div>
                    <a href="login_mahasiswa.php" class="btn btn-outline-admin" style="width: 100%; text-align: center; justify-content: center;">
                        <i class="fas fa-paper-plane"></i> Daftar Seminar Hasil
                    </a>
                </div>
            </div>

            <!-- 3. Sidang Akhir Skripsi -->
            <div class="syarat-card">
                <div>
                    <div class="syarat-header">
                        <div class="syarat-icon" style="background: rgba(30, 58, 138, 0.1); color: #1e3a8a;"><i class="fas fa-graduation-cap"></i></div>
                        <div>
                            <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a;">Sidang Akhir Skripsi</h3>
                            <span style="font-size: 0.8rem; color: #64748b;">Tahap Akhir Penentuan Kelulusan</span>
                        </div>
                    </div>
                    <ul class="syarat-list">
                        <li><i class="fas fa-check-circle"></i> <span>Telah lulus seluruh mata kuliah wajib (bebas tanggungan nilai D/E)</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Naskah Skripsi Final lengkap telah disetujui penuh oleh Dosen Pembimbing</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Surat Keterangan Bebas Pustaka & Laboratorium Jurusan TIK</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Sertifikat Kemampuan Bahasa Inggris (TOEFL/EPRT) minimal skor 400</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Hasil cek kemiripan (Turnitin / Plagiarisme) maksimal 25%</span></li>
                        <li><i class="fas fa-check-circle"></i> <span>Bukti submit artikel ilmiah / publikasi penelitian skripsi</span></li>
                    </ul>
                </div>
                <div>
                    <a href="login_mahasiswa.php" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center;">
                        <i class="fas fa-paper-plane"></i> Daftar Sidang Akhir
                    </a>
                </div>
            </div>
        </div>

        <!-- ALUR 4 TAHAPAN -->
        <div class="workflow-box" style="margin-top: 30px;">
            <div style="text-align: center; margin-bottom: 25px;">
                <span class="badge"><i class="fas fa-network-wired"></i> Prosedur Resmi</span>
                <h3 style="font-size: 1.5rem; color: #0f172a; margin: 8px 0;">4 Tahapan Alur Pelayanan Pendaftaran</h3>
                <p style="color: #64748b; font-size: 0.95rem; margin: 0;">Proses pendaftaran dirancang efisien dan terpadu mulai dari login hingga penerbitan jadwal resmi.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                <div class="step-item" style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div class="step-number">1</div>
                    <div class="step-info">
                        <h4 style="margin: 0 0 6px; color: #0f172a; font-size: 1.05rem;">Pendaftaran & Unggah Berkas</h4>
                        <p style="margin: 0; font-size: 0.88rem; color: #475569;">Mahasiswa masuk ke portal menggunakan NIM, mengisi form pendaftaran seminar/sidang, dan mengunggah dokumen persyaratan digital.</p>
                    </div>
                </div>

                <div class="step-item" style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div class="step-number">2</div>
                    <div class="step-info">
                        <h4 style="margin: 0 0 6px; color: #0f172a; font-size: 1.05rem;">Verifikasi oleh Admin Prodi</h4>
                        <p style="margin: 0; font-size: 0.88rem; color: #475569;">Administrator memeriksa kesesuaian berkas akademik. Status verifikasi dapat dipantau langsung oleh mahasiswa secara real-time.</p>
                    </div>
                </div>

                <div class="step-item" style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div class="step-number">3</div>
                    <div class="step-info">
                        <h4 style="margin: 0 0 6px; color: #0f172a; font-size: 1.05rem;">Penetapan Jadwal & Ruangan</h4>
                        <p style="margin: 0; font-size: 0.88rem; color: #475569;">Admin menetapkan tanggal, jam pelaksanaan, penugasan dosen penguji, dan alokasi ruangan dengan sistem pencegahan jadwal bentrok.</p>
                    </div>
                </div>

                <div class="step-item" style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div class="step-number">4</div>
                    <div class="step-info">
                        <h4 style="margin: 0 0 6px; color: #0f172a; font-size: 1.05rem;">Publikasi Jadwal Resmi</h4>
                        <p style="margin: 0; font-size: 0.88rem; color: #475569;">Jadwal otomatis disahkan dan tampil pada agenda publik website, serta mengirimkan notifikasi resmi ke mahasiswa dan dosen.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CALL TO ACTION PENDAFTARAN -->
        <div class="cta-box-pendaftaran">
            <h2 style="font-size: 1.8rem; margin: 0 0 10px;">Sudah Menyiapkan Berkas Persyaratan?</h2>
            <p style="font-size: 1rem; color: #e2e8f0; max-width: 650px; margin: 0 auto 20px;">
                Segera ajukan pendaftaran seminar atau sidang skripsi Anda secara online melalui portal mahasiswa SIM-SEMSID.
            </p>
            <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
                <a href="login_mahasiswa.php" class="btn btn-primary" style="padding: 12px 26px; font-size: 1rem;">
                    <i class="fas fa-sign-in-alt"></i> Login Mahasiswa untuk Mendaftar
                </a>
                <a href="panduan.php" class="btn btn-outline-admin" style="padding: 12px 20px; font-size: 1rem; color: white; border-color: white;">
                    <i class="fas fa-book-open"></i> Baca Panduan Lengkap
                </a>
            </div>
        </div>

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
