<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: jadwal_seminar.php (Jadwal Seminar Proposal Resmi / Disahkan - Publik)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

$is_logged_in = isset($_SESSION['login']) && $_SESSION['login'] === true;
$user_role    = $_SESSION['role'] ?? '';
$user_name    = $_SESSION['nama'] ?? '';

// Ambil data jadwal seminar proposal yang sudah disahkan (Terjadwal, Sedang Berlangsung, Selesai)
$query = "SELECT p.*, 
                 m.nim, m.nama AS nama_mhs, m.program_studi,
                 dp.nama_dosen AS nama_pembimbing, dp.gelar_akademik AS gelar_pembimbing,
                 du.nama_dosen AS nama_penguji, du.gelar_akademik AS gelar_penguji,
                 r.kode_ruangan, r.nama_ruangan, r.gedung
          FROM pendaftaran_seminar_sidang p
          JOIN mahasiswa m ON p.mahasiswa_id = m.id
          LEFT JOIN dosen dp ON p.dosen_pembimbing_id = dp.id
          LEFT JOIN dosen du ON p.dosen_penguji_id = du.id
          LEFT JOIN ruangan r ON p.ruangan_id = r.id
          WHERE (p.kategori = 'seminar' OR p.jenis = 'Seminar Proposal')
            AND p.status IN ('Terjadwal', 'Sedang Berlangsung', 'Selesai')
          ORDER BY p.tanggal ASC, p.jam_mulai ASC";

$result = mysqli_query($conn, $query);
$jadwal_list = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $jadwal_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Seminar Proposal Resmi - SIM-SEMSID PNL</title>
    
    <!-- Link FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Link File CSS Utama -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php 
    $active_menu = 'seminar';
    include 'public_sidebar.php'; 
    ?>
    <!-- Backdrop Gelap untuk Layar Mobile -->
    <div class="site-sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Area Konten Utama Sebelah Kanan Sidebar -->
    <div class="site-content-wrapper">

        <!-- Topbar Informasi -->
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

        <!-- Navbar -->
        <header class="navbar">
            <div class="container nav-container">
                <a href="index.php" class="logo-area">
                    <div class="logo-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="logo-text">
                        <h1>SIM-SEMSID</h1>
                        <span>POLITEKNIK NEGERI LHOKSEUMAWE</span>
                    </div>
                </a>

                <ul class="nav-menu" id="navMenu">
                    <li><a href="index.php#beranda" class="nav-link">Beranda</a></li>
                    <li><a href="index.php#alur" class="nav-link">Alur Pelayanan</a></li>
                    <li><a href="index.php#ruangan" class="nav-link">Ketersediaan Ruangan</a></li>
                </ul>

                <div class="nav-buttons">
                    <?php if ($is_logged_in): ?>
                        <?php if ($user_role === 'mahasiswa'): ?>
                            <a href="dashboard_mahasiswa.php" class="btn btn-primary" title="Buka Dashboard Mahasiswa">
                                <i class="fas fa-user-circle"></i> Dashboard (<?= htmlspecialchars($user_name); ?>)
                            </a>
                        <?php elseif ($user_role === 'dosen'): ?>
                            <a href="dashboard_dosen.php" class="btn btn-primary" title="Buka Dashboard Dosen">
                                <i class="fas fa-chalkboard-teacher"></i> Dashboard Dosen
                            </a>
                        <?php else: ?>
                            <a href="dashboard_admin.php" class="btn btn-primary" title="Buka Dashboard Admin">
                                <i class="fas fa-shield-halved"></i> Dashboard Admin
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-outline-admin" title="Keluar Akun" style="color: #dc2626; border-color: #fca5a5;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="login_mahasiswa.php" class="btn btn-primary" title="Masuk sebagai Mahasiswa">
                            <i class="fas fa-user-graduate"></i> Login Mahasiswa
                        </a>
                        <a href="login_dosen.php" class="btn btn-outline-admin" title="Masuk sebagai Dosen">
                            <i class="fas fa-chalkboard-teacher"></i> Login Dosen
                        </a>
                        <a href="login_admin.php" class="btn btn-outline-admin" title="Masuk sebagai Admin Pengelola">
                            <i class="fas fa-user-shield"></i> Login Admin
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Tombol Toggle Sidebar Khusus Mobile / Tablet -->
                <button class="btn-sidebar-toggle" id="sidebarToggleBtn" aria-label="Buka Menu Sidebar">
                    <i class="fas fa-bars-staggered"></i> Menu
                </button>

                <button class="menu-toggle" id="menuToggle" aria-label="Buka Menu Navigasi">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </header>

        <!-- Main Content Halaman Jadwal Seminar -->
        <main style="padding: 40px 0 60px;">
            <div class="container">
                <!-- Breadcrumb -->
                <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">
                    <a href="index.php" style="color: var(--primary); text-decoration: none;"><i class="fas fa-house"></i> Beranda</a>
                    <span style="margin: 0 8px;">/</span>
                    <span style="color: #0f172a; font-weight: 600;">Jadwal Seminar Proposal Resmi</span>
                </div>

                <!-- Judul Halaman -->
                <div class="section-title" style="text-align: left; margin-bottom: 30px;">
                    <span class="badge" style="background-color: var(--primary-light); color: var(--primary); padding: 6px 14px; border-radius: 50px; font-weight: 700; border: 1px solid var(--primary-border);">
                        <i class="fas fa-chalkboard-user"></i> Jadwal Resmi Disahkan
                    </span>
                    <h2 style="margin-top: 10px;">Jadwal Seminar Proposal Mahasiswa</h2>
                    <p style="margin: 0; max-width: 800px;">
                        Berikut adalah daftar jadwal seminar proposal mahasiswa Prodi Teknologi Rekayasa Perangkat Lunak yang telah resmi diverifikasi dan disahkan oleh Program Studi.
                    </p>
                </div>

                <!-- Filter & Pencarian Cepat -->
                <div class="room-filter-card" style="margin-bottom: 25px;">
                    <div class="room-filter-header">
                        <h4><i class="fas fa-search"></i> Saring & Cari Jadwal Seminar</h4>
                        <span class="data-notice" style="background: #ecfdf5; color: #059669; border-color: #a7f3d0;">
                            <i class="fas fa-check-circle"></i> Data Terverifikasi & Resmi
                        </span>
                    </div>
                    <div class="filter-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                        <div class="filter-group">
                            <label for="searchKeyword"><i class="fas fa-magnifying-glass"></i> Cari Mahasiswa / Judul:</label>
                            <input type="text" id="searchKeyword" placeholder="Ketik nama, NIM, atau kata judul..." oninput="filterJadwal()" style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem;">
                        </div>
                        <div class="filter-group">
                            <label for="filterStatus"><i class="fas fa-filter"></i> Status Seminar:</label>
                            <select id="filterStatus" onchange="filterJadwal()" style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem;">
                                <option value="semua">-- Semua Status --</option>
                                <option value="Terjadwal">Terjadwal</option>
                                <option value="Sedang Berlangsung">Sedang Berlangsung</option>
                                <option value="Selesai">Selesai</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn-reset-filter" onclick="resetFilterJadwal()" style="height: 42px;">
                                <i class="fas fa-rotate-left"></i> Reset Pencarian
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabel Jadwal Seminar -->
                <div class="room-table-wrapper" style="box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; background: white;">
                    <table class="room-table" id="jadwalTable" style="margin: 0;">
                        <thead>
                            <tr>
                                <th style="width: 45px; text-align: center;">No</th>
                                <th>Mahasiswa (NIM & Nama)</th>
                                <th>Judul Seminar Proposal</th>
                                <th>Tanggal & Waktu</th>
                                <th>Ruangan & Gedung</th>
                                <th>Dosen Pembimbing & Penguji</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($jadwal_list)): ?>
                                <tr>
                                    <td colspan="7" class="room-table-empty">
                                        <i class="fas fa-calendar-xmark" style="font-size: 2.2rem; color: #cbd5e1; margin-bottom: 8px;"></i>
                                        <strong>Belum ada jadwal seminar proposal yang disahkan saat ini.</strong>
                                        <p style="font-size: 0.85rem; margin-top: 4px; color: #64748b;">Silakan periksa kembali secara berkala.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($jadwal_list as $j): ?>
                                    <?php
                                        $tgl_format = !empty($j['tanggal']) ? date('d-m-Y', strtotime($j['tanggal'])) : '-';
                                        $jam_format = (!empty($j['jam_mulai']) && !empty($j['jam_selesai'])) ? substr($j['jam_mulai'], 0, 5) . ' - ' . substr($j['jam_selesai'], 0, 5) . ' WIB' : '-';
                                        
                                        $badge_class = 'warning';
                                        $badge_icon = 'fa-clock';
                                        if ($j['status'] === 'Sedang Berlangsung') {
                                            $badge_class = 'digunakan';
                                            $badge_icon = 'fa-spinner fa-spin';
                                        } elseif ($j['status'] === 'Selesai') {
                                            $badge_class = 'tersedia';
                                            $badge_icon = 'fa-circle-check';
                                        }
                                    ?>
                                    <tr class="jadwal-row" 
                                        data-keyword="<?= strtolower(htmlspecialchars($j['nama_mhs'] . ' ' . $j['nim'] . ' ' . $j['judul'] . ' ' . ($j['nama_ruangan'] ?? ''))); ?>"
                                        data-status="<?= htmlspecialchars($j['status']); ?>">
                                        <td style="text-align: center; font-weight: 700; color: #64748b;"><?= $no++; ?></td>
                                        <td>
                                            <strong style="color: var(--secondary); font-size: 0.95rem;"><?= htmlspecialchars($j['nama_mhs']); ?></strong>
                                            <div style="font-size: 0.82rem; color: #64748b; font-family: monospace; margin-top: 2px;">
                                                NIM: <?= htmlspecialchars($j['nim']); ?>
                                            </div>
                                        </td>
                                        <td style="max-width: 320px;">
                                            <div style="font-weight: 600; color: #1e293b; font-size: 0.9rem; line-height: 1.4;">
                                                <?= htmlspecialchars($j['judul']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #0f172a; font-size: 0.88rem;">
                                                <i class="fas fa-calendar-day" style="color: var(--primary); margin-right: 4px;"></i>
                                                <?= $tgl_format; ?>
                                            </div>
                                            <div style="font-size: 0.82rem; color: #64748b; margin-top: 3px;">
                                                <i class="fas fa-clock" style="color: #f97316; margin-right: 4px;"></i>
                                                <?= $jam_format; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($j['nama_ruangan'])): ?>
                                                <span class="room-code-tag" style="margin-bottom: 4px;"><?= htmlspecialchars($j['kode_ruangan']); ?></span>
                                                <div style="font-weight: 600; font-size: 0.88rem; color: #0f172a;">
                                                    <?= htmlspecialchars($j['nama_ruangan']); ?>
                                                </div>
                                                <div style="font-size: 0.78rem; color: #64748b;">
                                                    <?= htmlspecialchars($j['gedung']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="empty-val">Belum Ditentukan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.84rem; margin-bottom: 4px;">
                                                <span style="color: #64748b; font-size: 0.76rem; text-transform: uppercase; font-weight: 700;">Pembimbing:</span><br>
                                                <strong><?= htmlspecialchars(($j['nama_pembimbing'] ?? '-') . (!empty($j['gelar_pembimbing']) ? ', ' . $j['gelar_pembimbing'] : '')); ?></strong>
                                            </div>
                                            <div style="font-size: 0.84rem;">
                                                <span style="color: #64748b; font-size: 0.76rem; text-transform: uppercase; font-weight: 700;">Penguji:</span><br>
                                                <strong><?= htmlspecialchars(($j['nama_penguji'] ?? '-') . (!empty($j['gelar_penguji']) ? ', ' . $j['gelar_penguji'] : '')); ?></strong>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge-status-room <?= $badge_class; ?>">
                                                <i class="fas <?= $badge_icon; ?>"></i>
                                                <?= htmlspecialchars($j['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr id="emptyFilterRow" style="display: none;">
                                <td colspan="7" class="room-table-empty">
                                    <i class="fas fa-magnifying-glass" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 8px;"></i>
                                    <strong>Tidak ada jadwal seminar yang cocok dengan pencarian Anda.</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <div class="container footer-bottom" style="border: none; padding: 25px 0;">
                <p>&copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Program Studi Teknologi Rekayasa Perangkat Lunak, Politeknik Negeri Lhokseumawe.</p>
            </div>
        </footer>

    </div> <!-- /.site-content-wrapper -->

    <script>
        // Toggle Navigasi Mobile
        const menuToggle = document.getElementById('menuToggle');
        const navMenu = document.getElementById('navMenu');
        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', function() {
                navMenu.classList.toggle('active');
            });
        }

        // Filter & Search Jadwal Seminar
        function filterJadwal() {
            const keyword = document.getElementById('searchKeyword').value.toLowerCase().trim();
            const status = document.getElementById('filterStatus').value;
            const rows = document.querySelectorAll('.jadwal-row');
            let visible = 0;

            rows.forEach(function(r) {
                const rowKey = r.getAttribute('data-keyword') || '';
                const rowStat = r.getAttribute('data-status') || '';

                const matchKey = !keyword || (rowKey.indexOf(keyword) !== -1);
                const matchStat = (status === 'semua') || (rowStat === status);

                if (matchKey && matchStat) {
                    r.style.display = '';
                    visible++;
                } else {
                    r.style.display = 'none';
                }
            });

            const emptyRow = document.getElementById('emptyFilterRow');
            if (emptyRow) {
                emptyRow.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';
            }
        }

        function resetFilterJadwal() {
            document.getElementById('searchKeyword').value = '';
            document.getElementById('filterStatus').value = 'semua';
            filterJadwal();
        }
    </script>
</body>
</html>
