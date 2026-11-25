<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: dashboard_admin.php (Beranda Dashboard Administrator)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Validasi Keamanan Hak Akses: Hanya admin yang berhak mengakses halaman ini
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login_admin.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'mahasiswa') {
        header("Location: dashboard_mahasiswa.php");
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'dosen') {
        header("Location: dashboard_dosen.php");
    } else {
        header("Location: login_admin.php");
    }
    exit;
}

$admin_id = $_SESSION['admin_id'];
$username = $_SESSION['username'];
$nama     = $_SESSION['nama'];

// ===================================================================
// QUERY DATA STATISTIK RESMI DARI DATABASE MYSQL (BUKAN DATA CONTOH)
// ===================================================================

// 1. Total Pendaftar Seminar
$q_seminar = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pendaftaran_seminar_sidang WHERE jenis = 'Seminar Proposal' OR kategori = 'seminar'");
$row_seminar = mysqli_fetch_assoc($q_seminar);
$total_seminar = (int)($row_seminar['total'] ?? 0);

// 2. Total Pendaftar Sidang
$q_sidang = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pendaftaran_seminar_sidang WHERE jenis = 'Sidang Akhir Skripsi' OR kategori = 'sidang'");
$row_sidang = mysqli_fetch_assoc($q_sidang);
$total_sidang = (int)($row_sidang['total'] ?? 0);

// 3. Total Mahasiswa Terdaftar
$q_mhs = mysqli_query($conn, "SELECT COUNT(*) AS total FROM mahasiswa");
$row_mhs = mysqli_fetch_assoc($q_mhs);
$total_mahasiswa = (int)($row_mhs['total'] ?? 0);

// 4. Total Dosen Terdaftar
$q_dosen = mysqli_query($conn, "SELECT COUNT(*) AS total FROM dosen");
$row_dosen = mysqli_fetch_assoc($q_dosen);
$total_dosen = (int)($row_dosen['total'] ?? 0);

// 5. Total Pengajuan Judul
$q_judul = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pengajuan_judul");
$row_judul = mysqli_fetch_assoc($q_judul);
$total_pengajuan_judul = (int)($row_judul['total'] ?? 0);

// 6. Pengajuan Menunggu Pemeriksaan
$q_menunggu = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pengajuan_judul WHERE status = 'Menunggu' OR status = 'Sedang diperiksa'");
$row_menunggu = mysqli_fetch_assoc($q_menunggu);
$total_menunggu = (int)($row_menunggu['total'] ?? 0);

// Data Pendukung Tambahan untuk Dashboard:
// Pendaftaran Terkini (Seminar & Sidang)
$q_pendaftaran_terkini = mysqli_query($conn, "
    SELECT p.*, m.nama AS nama_mahasiswa, m.nim, d1.nama_dosen AS nama_pembimbing, d2.nama_dosen AS nama_penguji, r.nama_ruangan
    FROM pendaftaran_seminar_sidang p
    JOIN mahasiswa m ON p.mahasiswa_id = m.id
    JOIN dosen d1 ON p.dosen_pembimbing_id = d1.id
    JOIN dosen d2 ON p.dosen_penguji_id = d2.id
    LEFT JOIN ruangan r ON p.ruangan_id = r.id
    ORDER BY p.id DESC LIMIT 5
");

// Pengajuan Judul Terbaru
$q_judul_terkini = mysqli_query($conn, "
    SELECT pj.*, m.nama AS nama_mahasiswa, m.nim, d.nama_dosen, d.gelar_akademik
    FROM pengajuan_judul pj
    JOIN mahasiswa m ON pj.mahasiswa_id = m.id
    JOIN dosen d ON pj.dosen_id = d.id
    ORDER BY pj.updated_at DESC LIMIT 5
");

// Dokumen Mahasiswa Terbaru
$q_dokumen_terkini = mysqli_query($conn, "
    SELECT d.*, m.nama AS nama_mahasiswa, m.nim 
    FROM dokumen_mahasiswa d
    JOIN mahasiswa m ON d.mahasiswa_id = m.id
    ORDER BY d.created_at DESC LIMIT 5
");

$active_menu = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Dashboard Admin - SIM-SEMSID Politeknik Negeri Lhokseumawe</title>

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS Utama & CSS Khusus Admin Navy -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">

    <!-- Komponen Sidebar Navy Gelap Kiri -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Area Konten Utama -->
    <div class="admin-main-wrapper">

        <!-- Topbar Admin -->
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button type="button" class="admin-mobile-toggle" onclick="toggleSidebar();" title="Buka Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="admin-topbar-title">
                    <h1>Dashboard Utama</h1>
                    <span>Sistem Informasi Manajemen Seminar & Sidang Akhir Mahasiswa</span>
                </div>
            </div>

            <div class="admin-topbar-right">
                <!-- Komponen Lonceng Notifikasi Admin -->
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <div class="admin-badge-date">
                    <i class="fas fa-calendar-day"></i>
                    <span><?= date('l, d F Y'); ?></span>
                </div>
                <a href="index.php" target="_blank" class="btn-admin-outline" style="font-size: 0.8rem; padding: 6px 12px;" title="Lihat Website Publik">
                    <i class="fas fa-globe"></i> Website Publik
                </a>
            </div>
        </header>

        <!-- Konten Halaman Beranda Dashboard -->
        <main class="admin-content-body">

            <!-- Banner Sambutan: Selamat Datang, Admin! -->
            <div class="admin-welcome-hero">
                <h2>Selamat Datang, Admin! 👋</h2>
                <p>
                    Anda masuk sebagai <strong><?= htmlspecialchars($nama); ?></strong> di Panel Administrator 
                    <strong>Jurusan Teknologi Informasi dan Komputer (TIK) - Politeknik Negeri Lhokseumawe</strong>. 
                    Kelola data pendaftaran seminar, sidang akhir skripsi, data dosen, mahasiswa, kuota bimbingan, dan verifikasi dokumen persyaratan secara terpadu.
                </p>
                <div style="margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="admin_pendaftaran.php" class="btn-admin-orange" style="font-size: 0.84rem; padding: 7px 14px;">
                        <i class="fas fa-plus-circle"></i> Kelola Pendaftaran
                    </a>
                    <a href="admin_dokumen.php" class="btn-admin-primary" style="font-size: 0.84rem; padding: 7px 14px;">
                        <i class="fas fa-folder-open"></i> Periksa Dokumen
                    </a>
                    <a href="admin_dosen.php" class="btn-admin-outline" style="background: rgba(255,255,255,0.15); color: white; border-color: rgba(255,255,255,0.3); font-size: 0.84rem; padding: 7px 14px;">
                        <i class="fas fa-user-tie"></i> Data Dosen & Kuota
                    </a>
                </div>
            </div>

            <!-- Kartu Statistik 6 Item Sesuai Ketentuan -->
            <div class="admin-stats-grid">
                <!-- 1. Total Pendaftar Seminar -->
                <a href="admin_jadwal_seminar.php" class="admin-stat-card" title="Klik untuk membuka Jadwal & Data Seminar">
                    <div class="admin-stat-icon stat-icon-blue">
                        <i class="fas fa-chalkboard-user"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value"><?= $total_seminar; ?></div>
                        <div class="admin-stat-label">Total Pendaftar Seminar</div>
                    </div>
                </a>

                <!-- 2. Total Pendaftar Sidang -->
                <a href="admin_jadwal_sidang.php" class="admin-stat-card" title="Klik untuk membuka Jadwal & Data Sidang">
                    <div class="admin-stat-icon stat-icon-orange">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value"><?= $total_sidang; ?></div>
                        <div class="admin-stat-label">Total Pendaftar Sidang</div>
                    </div>
                </a>

                <!-- 3. Total Mahasiswa -->
                <a href="admin_mahasiswa.php" class="admin-stat-card" title="Klik untuk membuka Manajemen Data Mahasiswa">
                    <div class="admin-stat-icon stat-icon-green">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value"><?= $total_mahasiswa; ?></div>
                        <div class="admin-stat-label">Total Mahasiswa</div>
                    </div>
                </a>

                <!-- 4. Total Dosen -->
                <a href="admin_dosen.php" class="admin-stat-card" title="Klik untuk membuka Manajemen Data Dosen">
                    <div class="admin-stat-icon stat-icon-purple">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value"><?= $total_dosen; ?></div>
                        <div class="admin-stat-label">Total Dosen</div>
                    </div>
                </a>

                <!-- 5. Total Pengajuan Judul -->
                <a href="admin_pengajuan_judul.php" class="admin-stat-card" title="Klik untuk membuka Semua Pengajuan Judul">
                    <div class="admin-stat-icon stat-icon-amber">
                        <i class="fas fa-book-bookmark"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value"><?= $total_pengajuan_judul; ?></div>
                        <div class="admin-stat-label">Total Pengajuan Judul</div>
                    </div>
                </a>

                <!-- 6. Pengajuan Menunggu Pemeriksaan -->
                <a href="admin_pengajuan_judul.php?status_filter=Menunggu" class="admin-stat-card" title="Klik untuk memfilter pengajuan yang belum diperiksa">
                    <div class="admin-stat-icon stat-icon-red">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value" style="color: #dc2626;"><?= $total_menunggu; ?></div>
                        <div class="admin-stat-label">Menunggu Pemeriksaan</div>
                    </div>
                </a>
            </div>

            <!-- Bagian Dua Kolom Widget: Pendaftaran Terkini & Pengajuan Judul Terbaru -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 24px; margin-bottom: 25px;">

                <!-- Panel 1: Pendaftaran Seminar & Sidang Terkini -->
                <div class="admin-card-panel" style="margin-bottom: 0;">
                    <div class="admin-panel-header">
                        <h3><i class="fas fa-calendar-check" style="color: var(--admin-blue-primary);"></i> Pendaftaran Seminar & Sidang Terkini</h3>
                        <a href="admin_pendaftaran.php" class="btn-admin-outline" style="font-size: 0.78rem; padding: 5px 10px;">
                            Lihat Semua &rarr;
                        </a>
                    </div>

                    <div class="admin-table-responsive">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>Mahasiswa</th>
                                    <th>Kategori</th>
                                    <th>Jadwal / Waktu</th>
                                    <th>Ruangan</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($q_pendaftaran_terkini && mysqli_num_rows($q_pendaftaran_terkini) > 0): ?>
                                    <?php while ($p = mysqli_fetch_assoc($q_pendaftaran_terkini)): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 700; color: var(--admin-navy);"><?= htmlspecialchars($p['nama_mahasiswa']); ?></div>
                                                <small style="color: var(--admin-text-muted); font-family: monospace;"><?= htmlspecialchars($p['nim']); ?></small>
                                            </td>
                                            <td>
                                                <span class="<?= ($p['kategori'] === 'seminar') ? 'badge-admin-blue' : 'badge-admin-orange'; ?>">
                                                    <?= htmlspecialchars($p['jenis']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.82rem; font-weight: 600; color: #334155;">
                                                    <?= $p['tanggal'] ? date('d M Y', strtotime($p['tanggal'])) : '-'; ?>
                                                </div>
                                                <small style="color: #64748b;"><?= $p['jam_mulai'] ? substr($p['jam_mulai'], 0, 5) . ' - ' . substr($p['jam_selesai'], 0, 5) . ' WIB' : 'Waktu menyusul'; ?></small>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.82rem; color: #475569;">
                                                    <i class="fas fa-door-open" style="color: var(--admin-accent-orange);"></i> <?= htmlspecialchars($p['nama_ruangan'] ?? 'Belum ditentukan'); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php
                                                $st = $p['status'];
                                                $b_class = 'badge-admin-amber';
                                                if ($st === 'Terjadwal') $b_class = 'badge-admin-blue';
                                                elseif ($st === 'Sedang Berlangsung') $b_class = 'badge-admin-orange';
                                                elseif ($st === 'Selesai') $b_class = 'badge-admin-green';
                                                elseif ($st === 'Ditolak') $b_class = 'badge-admin-red';
                                                ?>
                                                <span class="<?= $b_class; ?>"><?= htmlspecialchars($st); ?></span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: #94a3b8; padding: 25px;">
                                            Belum ada data pendaftaran seminar atau sidang.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Panel 2: Usulan Pengajuan Judul Terkini -->
                <div class="admin-card-panel" style="margin-bottom: 0;">
                    <div class="admin-panel-header">
                        <h3><i class="fas fa-book-bookmark" style="color: var(--admin-accent-orange);"></i> Usulan Pengajuan Judul Terkini</h3>
                        <a href="admin_pengajuan_judul.php" class="btn-admin-outline" style="font-size: 0.78rem; padding: 5px 10px;">
                            Lihat Semua &rarr;
                        </a>
                    </div>

                    <div class="admin-table-responsive">
                        <table class="admin-data-table">
                            <thead>
                                <tr>
                                    <th>Mahasiswa</th>
                                    <th>Judul Usulan</th>
                                    <th>Dosen Tujuan</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($q_judul_terkini && mysqli_num_rows($q_judul_terkini) > 0): ?>
                                    <?php while ($j = mysqli_fetch_assoc($q_judul_terkini)): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 700; color: var(--admin-navy);"><?= htmlspecialchars($j['nama_mahasiswa']); ?></div>
                                                <small style="color: var(--admin-text-muted); font-family: monospace;"><?= htmlspecialchars($j['nim']); ?></small>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.85rem; font-weight: 600; color: #1e293b; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($j['judul']); ?>">
                                                    <?= htmlspecialchars($j['judul']); ?>
                                                </div>
                                                <small style="color: #64748b;"><?= date('d/m/Y H:i', strtotime($j['created_at'])); ?> WIB</small>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.82rem; color: #334155;">
                                                    <i class="fas fa-user-tie" style="color: var(--admin-blue-primary);"></i> <?= htmlspecialchars($j['nama_dosen']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php
                                                $jst = $j['status'];
                                                $jb_class = 'badge-admin-amber';
                                                if ($jst === 'Sedang diperiksa') $jb_class = 'badge-admin-blue';
                                                elseif ($jst === 'Perlu revisi') $jb_class = 'badge-admin-orange';
                                                elseif ($jst === 'Diterima') $jb_class = 'badge-admin-green';
                                                elseif ($jst === 'Ditolak') $jb_class = 'badge-admin-red';
                                                ?>
                                                <span class="<?= $jb_class; ?>"><?= htmlspecialchars($jst); ?></span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #94a3b8; padding: 25px;">
                                            Belum ada pengajuan judul dari mahasiswa.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Panel 3: Berkas Dokumen Mahasiswa Terbaru -->
            <div class="admin-card-panel">
                <div class="admin-panel-header">
                    <h3><i class="fas fa-folder-open" style="color: var(--admin-blue-primary);"></i> Dokumen Persyaratan Mahasiswa Terbaru</h3>
                    <a href="admin_dokumen.php" class="btn-admin-primary" style="font-size: 0.8rem; padding: 6px 14px;">
                        <i class="fas fa-clipboard-check"></i> Periksa Semua Dokumen
                    </a>
                </div>

                <div class="admin-table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th>Mahasiswa (NIM)</th>
                                <th>Jenis & Judul Dokumen</th>
                                <th>Nama Berkas</th>
                                <th>Waktu Upload</th>
                                <th>Status</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($q_dokumen_terkini && mysqli_num_rows($q_dokumen_terkini) > 0): ?>
                                <?php while ($dok = mysqli_fetch_assoc($q_dokumen_terkini)): 
                                    $st_class = 'status-menunggu';
                                    if ($dok['status'] === 'Diterima') $st_class = 'status-selesai';
                                    elseif ($dok['status'] === 'Perlu perbaikan') $st_class = 'status-batal';
                                ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($dok['nama_mahasiswa']); ?></div>
                                            <div style="font-size: 0.78rem; color: #64748b;">NIM: <?= htmlspecialchars($dok['nim']); ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($dok['judul_dokumen']); ?></div>
                                            <div style="font-size: 0.75rem; color: #ea580c; font-weight: 600;"><?= htmlspecialchars($dok['jenis_dokumen']); ?></div>
                                        </td>
                                        <td>
                                            <span style="font-family: monospace; font-size: 0.78rem; background: #f8fafc; padding: 2px 6px; border-radius: 4px;">
                                                <?= htmlspecialchars($dok['nama_file_asli']); ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.8rem; color: #64748b;">
                                            <?= date('d/m/Y H:i', strtotime($dok['created_at'])); ?> WIB
                                        </td>
                                        <td>
                                            <span class="admin-badge <?= $st_class; ?>" style="font-size: 0.72rem; padding: 3px 8px;">
                                                <?= htmlspecialchars($dok['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <a href="admin_dokumen.php" class="btn-admin-outline" style="font-size: 0.78rem; padding: 4px 10px;">
                                                Periksa &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 25px; color: #64748b;">
                                        Belum ada dokumen persyaratan yang diunggah oleh mahasiswa.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>

        <!-- Footer Admin -->
        <footer class="admin-footer">
            &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Jurusan Teknologi Informasi dan Komputer (TIK), Politeknik Negeri Lhokseumawe.
        </footer>

    </div>

    <!-- JavaScript Responsif Sidebar Toggle -->
    <script>
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('show');
        }

        // Tutup sidebar jika layar diklik di luar area sidebar pada tampilan mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('adminSidebar');
            const toggle  = document.querySelector('.admin-mobile-toggle');
            if (window.innerWidth <= 992 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>
    <!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
    <script src="notifikasi.js"></script>
</body>
</html>
