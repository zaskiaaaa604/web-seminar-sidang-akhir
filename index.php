<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: index.php (Halaman Beranda Utama SIM-SEM SID - Publik)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

$is_logged_in = isset($_SESSION['login']) && $_SESSION['login'] === true;
$user_role    = $_SESSION['role'] ?? '';
$user_name    = $_SESSION['nama'] ?? '';

// 1. DATA INFORMASI LAYANAN (STATISTIK TERJADWAL)
$q_count_sem = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pendaftaran_seminar_sidang WHERE (kategori='seminar' OR jenis='Seminar Proposal') AND status IN ('Terjadwal', 'Sedang Berlangsung')");
$row_sem = mysqli_fetch_assoc($q_count_sem);
$total_sem_aktif = (int)($row_sem['total'] ?? 0);

$q_count_sid = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pendaftaran_seminar_sidang WHERE (kategori='sidang' OR jenis='Sidang Akhir Skripsi') AND status IN ('Terjadwal', 'Sedang Berlangsung')");
$row_sid = mysqli_fetch_assoc($q_count_sid);
$total_sid_aktif = (int)($row_sid['total'] ?? 0);

// 2. DATA PENGUMUMAN TERBARU (DARI KALENDER AKADEMIK & INFORMASI SISTEM)
$q_pengumuman = mysqli_query($conn, "
    SELECT * FROM kalender_akademik 
    WHERE status_publikasi = 'Dipublikasikan' 
    ORDER BY tanggal_mulai ASC LIMIT 4
");

// Format tanggal pendek
function tglIndoShortIndex($tgl) {
    if (empty($tgl)) return '-';
    $bln = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $ts = strtotime($tgl);
    return date('j', $ts) . ' ' . $bln[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Manajemen Seminar dan Sidang Akhir - SIM-SEM SID PNL</title>
    
    <!-- Link FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Link CSS Utama -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* Card Informasi Layanan */
        .layanan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin: 30px 0 50px;
        }
        .layanan-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 30px 26px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }
        .layanan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #ea580c;
        }
        .layanan-card:hover {
            transform: translateY(-5px);
            border-color: #ea580c;
            box-shadow: 0 12px 28px rgba(234, 88, 12, 0.1);
        }
        .layanan-icon-box {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: rgba(234, 88, 12, 0.12);
            color: #ea580c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        .layanan-card h3 {
            font-size: 1.3rem;
            color: #0f172a;
            margin: 0 0 10px;
            font-weight: 800;
        }
        .layanan-card p {
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.5;
            margin: 0 0 20px;
        }

        /* Pengumuman Terbaru */
        .pengumuman-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 25px 0 40px;
        }
        .pengumuman-item {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 22px;
            border-left: 4px solid #1e3a8a;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }
        .pengumuman-item:hover {
            transform: translateY(-3px);
            border-left-color: #ea580c;
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }
        .pengumuman-kategori {
            display: inline-block;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 4px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .pengumuman-date {
            font-size: 0.8rem;
            color: #ea580c;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .pengumuman-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 8px;
        }
        .pengumuman-desc {
            font-size: 0.88rem;
            color: #475569;
            margin: 0;
            line-height: 1.45;
        }
    </style>
</head>
<body>

    <?php 
    $active_menu = 'beranda';
    include 'public_sidebar.php'; 
    ?>
    <!-- Backdrop Layar Mobile -->
    <div class="site-sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="site-content-wrapper">

    <!-- ==================== 1. TOPBAR INFORMASI ==================== -->
    <div class="topbar">
        <div class="container topbar-content">
            <div class="topbar-info">
                <span><i class="fas fa-university"></i> Politeknik Negeri Lhokseumawe</span>
                <span><i class="fas fa-laptop-code"></i> Jurusan TIK</span>
                <span><i class="fas fa-code-branch"></i> Program Studi Teknologi Rekayasa Perangkat Lunak</span>
            </div>
            <div class="topbar-info">
                <span><i class="fas fa-clock"></i> Pelayanan: Senin - Jumat (08:00 - 16:00 WIB)</span>
            </div>
        </div>
    </div>

    <!-- ==================== 2. HEADER & 5 MENU UTAMA ==================== -->
    <header class="navbar">
        <div class="container nav-container">
            <!-- Logo & Nama SIM-SEM SID -->
            <a href="index.php" class="logo-area">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text">
                    <h1>SIM-SEM SID</h1>
                    <span>POLITEKNIK NEGERI LHOKSEUMAWE</span>
                </div>
            </a>

            <!-- 4 Menu Utama Pengunjung -->
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php" class="nav-link active">Beranda</a></li>
                <li><a href="jadwal_kalender.php" class="nav-link">Jadwal & Kalender</a></li>
                <li><a href="informasi_pendaftaran.php" class="nav-link">Informasi Pendaftaran</a></li>
                <li><a href="panduan.php" class="nav-link">Panduan</a></li>
            </ul>

            <!-- Tombol Login Pengguna -->
            <div class="nav-buttons">
                <?php if ($is_logged_in): ?>
                    <a href="<?= ($user_role === 'admin') ? 'dashboard_admin.php' : (($user_role === 'dosen') ? 'dashboard_dosen.php' : 'dashboard_mahasiswa.php'); ?>" class="btn btn-primary" title="Buka Dashboard Akun">
                        <i class="fas fa-user-circle"></i> Dashboard (<?= htmlspecialchars($user_name); ?>)
                    </a>
                    <a href="logout.php" class="btn btn-outline-admin" title="Keluar Akun" style="color: #dc2626; border-color: #fca5a5;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login_mahasiswa.php" class="btn btn-primary" title="Login Mahasiswa">
                        <i class="fas fa-user-graduate"></i> Login Mahasiswa
                    </a>
                    <a href="login_dosen.php" class="btn btn-outline-admin" title="Login Dosen">
                        <i class="fas fa-chalkboard-teacher"></i> Login Dosen
                    </a>
                    <a href="login_admin.php" class="btn btn-outline-admin" title="Login Admin">
                        <i class="fas fa-user-shield"></i> Login Admin
                    </a>
                <?php endif; ?>
            </div>

            <!-- Tombol Toggle Sidebar Mobile -->
            <button class="btn-sidebar-toggle" id="sidebarToggleBtn" aria-label="Buka Menu Sidebar">
                <i class="fas fa-bars-staggered"></i> Menu
            </button>

            <!-- Hamburger Button Mobile -->
            <button class="menu-toggle" id="menuToggle" aria-label="Buka Menu Navigasi">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Notifikasi Pesan Sistem -->
    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'logout_berhasil'): ?>
        <div class="container" style="margin-top: 20px;">
            <div class="alert alert-success">
                <i class="fas fa-check-circle" style="font-size: 1.2rem; margin-top: 2px;"></i>
                <div>
                    <strong>Berhasil Logout!</strong> Sesi login Anda telah dihapus dengan aman. Terima kasih telah menggunakan layanan SIM-SEM SID.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ==================== 3. HERO SECTION ==================== -->
    <section class="hero" id="beranda">
        <div class="container">
            <div class="hero-content">
                <span class="hero-tag">
                    <i class="fas fa-shield-halved"></i> Portal Resmi Akademik Jurusan TIK
                </span>
                <h1 class="hero-title">
                    Sistem Informasi Manajemen <span style="color: #ea580c;">Seminar dan Sidang Akhir</span>
                </h1>
                <p class="hero-desc">
                    Layanan terpadu untuk mempermudah proses pengajuan judul, pendaftaran seminar, pelaksanaan sidang akhir, serta pemantauan jadwal akademik mahasiswa.
                </p>
                <div class="hero-cta">
                    <a href="login_mahasiswa.php" class="btn btn-primary">
                        <i class="fas fa-user-graduate"></i> Login Mahasiswa
                    </a>
                    <a href="login_dosen.php" class="btn btn-outline-admin">
                        <i class="fas fa-chalkboard-teacher"></i> Login Dosen
                    </a>
                    <a href="login_admin.php" class="btn btn-outline-admin">
                        <i class="fas fa-user-shield"></i> Login Admin
                    </a>
                </div>

                <!-- Indikator Keunggulan -->
                <div class="hero-highlights">
                    <div class="highlight-item">
                        <div class="highlight-icon"><i class="fas fa-laptop-file"></i></div>
                        <div class="highlight-text">
                            <h4>Pendaftaran Daring</h4>
                            <p>Unggah berkas terpadu</p>
                        </div>
                    </div>
                    <div class="highlight-item">
                        <div class="highlight-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="highlight-text">
                            <h4>Jadwal Terbuka</h4>
                            <p>Transparan & real-time</p>
                        </div>
                    </div>
                    <div class="highlight-item">
                        <div class="highlight-icon"><i class="fas fa-shield-halved"></i></div>
                        <div class="highlight-text">
                            <h4>Bebas Konflik</h4>
                            <p>Pencegahan bentrok otomatis</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 4. TIGA KARTU INFORMASI LAYANAN ==================== -->
    <section class="container" style="padding: 20px 0 30px;">
        <div class="section-title" style="text-align: center; margin-bottom: 10px;">
            <span class="badge"><i class="fas fa-layer-group"></i> Layanan Utama</span>
            <h2>Informasi Layanan Akademik</h2>
            <p>Pilih layanan berikut untuk melihat rincian jadwal seminar, jadwal sidang, atau panduan pendaftaran.</p>
        </div>

        <div class="layanan-grid">
            <!-- 1. Kartu Jadwal Seminar -->
            <div class="layanan-card">
                <div>
                    <div class="layanan-icon-box">
                        <i class="fas fa-chalkboard-user"></i>
                    </div>
                    <h3>Jadwal Seminar Proposal</h3>
                    <p>
                        Lihat agenda pelaksanaan seminar proposal mahasiswa yang telah disahkan, lengkap dengan nama peserta, nomor ruangan, dan dosen penelaah.
                    </p>
                    <div style="font-size: 0.85rem; color: #1e3a8a; font-weight: 700; margin-bottom: 16px;">
                        <i class="fas fa-circle-check" style="color: #10b981;"></i> Status Jadwal Terbuka untuk Publik
                    </div>
                </div>
                <div>
                    <a href="jadwal_kalender.php?tab=seminar" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center;">
                        <i class="fas fa-calendar-alt"></i> Lihat Jadwal Seminar
                    </a>
                </div>
            </div>

            <!-- 2. Kartu Jadwal Sidang -->
            <div class="layanan-card">
                <div>
                    <div class="layanan-icon-box" style="background: rgba(30, 58, 138, 0.12); color: #1e3a8a;">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3>Jadwal Sidang Akhir</h3>
                    <p>
                        Pantau jadwal resmi sidang tugas akhir/skripsi sarjana terapan, susunan dewan penguji, serta informasi waktu dan lokasi ruangan sidang.
                    </p>
                    <div style="font-size: 0.85rem; color: #1e3a8a; font-weight: 700; margin-bottom: 16px;">
                        <i class="fas fa-circle-check" style="color: #10b981;"></i> Status Jadwal Terbuka untuk Publik
                    </div>
                </div>
                <div>
                    <a href="jadwal_kalender.php?tab=sidang" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center; background: #1e3a8a; border-color: #1e3a8a;">
                        <i class="fas fa-graduation-cap"></i> Lihat Jadwal Sidang
                    </a>
                </div>
            </div>

            <!-- 3. Kartu Informasi Pendaftaran -->
            <div class="layanan-card">
                <div>
                    <div class="layanan-icon-box" style="background: rgba(13, 148, 136, 0.12); color: #0d9488;">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3>Informasi Pendaftaran</h3>
                    <p>
                        Ketahui seluruh persyaratan berkas seminar proposal, seminar hasil, sidang akhir, dokumen kelengkapan, dan alur pendaftaran terstruktur.
                    </p>
                    <div style="font-size: 0.85rem; color: #0d9488; font-weight: 700; margin-bottom: 16px;">
                        <i class="fas fa-file-pdf"></i> Format Digital Tanpa Cetak Berkas Fisik
                    </div>
                </div>
                <div>
                    <a href="informasi_pendaftaran.php" class="btn btn-outline-admin" style="width: 100%; text-align: center; justify-content: center; border-color: #0d9488; color: #0d9488;">
                        <i class="fas fa-arrow-right"></i> Pelajari Persyaratan
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 5. PENGUMUMAN TERBARU ==================== -->
    <section class="container" style="padding: 10px 0 50px;">
        <div class="section-title" style="text-align: center; margin-bottom: 15px;">
            <span class="badge" style="background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe;"><i class="fas fa-bullhorn"></i> Informasi Akademik</span>
            <h2>Pengumuman Terbaru</h2>
            <p>Pengumuman dan agenda penting terkait pelaksanaan seminar proposal, sidang akhir, dan kalender akademik TIK.</p>
        </div>

        <div class="pengumuman-grid">
            <?php if (mysqli_num_rows($q_pengumuman) === 0): ?>
                <div style="grid-column: 1 / -1; background: white; padding: 30px; text-align: center; border-radius: 12px; border: 1px solid #e2e8f0; color: #64748b;">
                    <i class="fas fa-info-circle" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 8px; display: block;"></i>
                    Belum ada pengumuman kegiatan akademik baru yang dipublikasikan saat ini.
                </div>
            <?php else: ?>
                <?php while ($p = mysqli_fetch_assoc($q_pengumuman)): ?>
                    <div class="pengumuman-item">
                        <span class="pengumuman-kategori"><?= htmlspecialchars($p['kategori']); ?></span>
                        <div class="pengumuman-date">
                            <i class="fas fa-calendar-day"></i> <?= tglIndoShortIndex($p['tanggal_mulai']); ?>
                            <?php if ($p['tanggal_mulai'] !== $p['tanggal_selesai']): ?>
                                - <?= tglIndoShortIndex($p['tanggal_selesai']); ?>
                            <?php endif; ?>
                        </div>
                        <h4 class="pengumuman-title"><?= htmlspecialchars($p['nama_kegiatan']); ?></h4>
                        <?php if (!empty($p['deskripsi'])): ?>
                            <p class="pengumuman-desc"><?= htmlspecialchars(mb_strimwidth($p['deskripsi'], 0, 110, '...')); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <div style="text-align: center;">
            <a href="jadwal_kalender.php?tab=kalender" class="btn btn-outline-admin" style="font-size: 0.9rem; padding: 10px 22px;">
                <i class="fas fa-calendar-days"></i> Lihat Kalender Akademik Lengkap
            </a>
        </div>
    </section>

    <!-- ==================== 6. ALUR PELAYANAN ==================== -->
    <section class="about-section" id="alur" style="background: #ffffff; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 60px 0;">
        <div class="container about-grid">
            <div class="about-text">
                <span class="badge" style="background-color: var(--primary-light); color: var(--primary); padding: 5px 12px; border-radius: 50px; font-weight: bold; font-size: 0.85rem; border: 1px solid var(--primary-border);">
                    <i class="fas fa-info-circle"></i> Alur Pelayanan
                </span>
                <h3 style="margin-top: 15px;">Pelayanan Seminar & Sidang yang Terstruktur</h3>
                <p>
                    Website <strong>SIM-SEM SID</strong> mengintegrasikan tahapan akademik tingkat akhir mulai dari pendaftaran, verifikasi berkas oleh admin, penetapan dewan penguji dan ruangan bebas bentrok, hingga penerbitan jadwal resmi.
                </p>
                <div style="margin-top: 20px;">
                    <a href="panduan.php" class="btn btn-primary" style="padding: 10px 20px;">
                        <i class="fas fa-book-open"></i> Pelajari Panduan Lengkap
                    </a>
                </div>
            </div>

            <div class="workflow-box">
                <h4><i class="fas fa-network-wired" style="color: var(--primary);"></i> 4 Tahapan Pelayanan</h4>
                
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-info">
                        <h5>Pendaftaran & Unggah Berkas</h5>
                        <p>Mahasiswa login ke portal akun lalu mengisi form pendaftaran dan mengunggah dokumen persyaratan digital.</p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-info">
                        <h5>Verifikasi oleh Admin Prodi</h5>
                        <p>Petugas mengecek kelengkapan berkas akademik. Status pendaftaran dapat dipantau langsung oleh mahasiswa.</p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-info">
                        <h5>Penetapan Jadwal & Ruangan</h5>
                        <p>Admin menetapkan tanggal, jam, dosen penguji, serta ruangan ujian yang terlindung dari jadwal bertabrakan.</p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-number">4</div>
                    <div class="step-info">
                        <h5>Publikasi Jadwal Resmi</h5>
                        <p>Jadwal disahkan dan otomatis tampil pada agenda website sehingga dapat dilihat oleh civitas akademika.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== 7. FOOTER ==================== -->
    <footer class="footer">
        <div class="container footer-grid">
            <div class="footer-col">
                <h4>SIM-SEM SID</h4>
                <p>
                    Sistem Informasi Manajemen Seminar dan Sidang Akhir Mahasiswa Politeknik Negeri Lhokseumawe. Layanan terpadu pengajuan judul, pendaftaran seminar, pelaksanaan sidang akhir, dan transparansi jadwal akademik.
                </p>
                <p style="font-size: 0.85rem; color: #64748b; margin-top: 12px; line-height: 1.6;">
                    <i class="fas fa-university" style="color: #ea580c;"></i> Politeknik Negeri Lhokseumawe<br>
                    <i class="fas fa-laptop-code" style="color: #ea580c;"></i> Jurusan Teknologi Informasi dan Komputer (TIK)<br>
                    <i class="fas fa-graduation-cap" style="color: #ea580c;"></i> Layanan Seminar & Sidang Terpadu
                </p>
            </div>

            <div class="footer-col">
                <h4>Menu Navigasi</h4>
                <ul class="footer-links">
                    <li><a href="index.php"><i class="fas fa-angle-right"></i> Beranda</a></li>
                    <li><a href="jadwal_kalender.php"><i class="fas fa-angle-right"></i> Jadwal & Kalender</a></li>
                    <li><a href="informasi_pendaftaran.php"><i class="fas fa-angle-right"></i> Informasi Pendaftaran</a></li>
                    <li><a href="panduan.php"><i class="fas fa-angle-right"></i> Panduan Pengguna</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Kontak & Bantuan</h4>
                <ul class="footer-links footer-contact">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Jl. Banda Aceh - Medan Km. 280.3, Buketrata, Lhokseumawe, Aceh 24301</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>admin.tik@pnl.ac.id</span>
                    </li>
                    <li>
                        <i class="fas fa-phone-alt"></i>
                        <span>(0645) 42615 / Ext. Prodi TIK</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="container footer-bottom">
            <p>
                &copy; <?= date('Y'); ?> <strong>SIM-SEM SID</strong> - Politeknik Negeri Lhokseumawe. Hak Cipta Dilindungi Undang-Undang.
            </p>
        </div>
    </footer>

    </div> <!-- /.site-content-wrapper -->

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
