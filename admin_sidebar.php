<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - JURUSAN TIK
 * FILE: admin_sidebar.php (Sidebar Dashboard Admin Terstruktur)
 * ===================================================================
 */

// Validasi Keamanan: Hanya admin yang berhak
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    return;
}

if (!isset($active_menu)) {
    $active_menu = 'dashboard';
}
if (!isset($active_submenu)) {
    $active_submenu = '';
}

$admin_nama_tampil = $_SESSION['nama'] ?? 'Administrator';
$admin_user_tampil = $_SESSION['username'] ?? 'admin';

// Logika Pengelompokan Submenu Aktif (Auto-expand Accordion)
$is_pendaftaran_group = in_array($active_menu, ['pendaftaran', 'judul']);
$is_jadwal_group      = in_array($active_menu, ['seminar', 'sidang', 'kalender']);
$is_akademik_group    = ($active_menu === 'mahasiswa');
$is_dosen_group       = in_array($active_menu, ['dosen', 'kuota']);
$is_dokumen_group     = ($active_menu === 'dokumen');
$is_laporan_group     = ($active_menu === 'laporan');
?>
<!-- Sidebar Navy Gelap Admin (Struktur Menu Elegan & Terpadu) -->
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Header Sidebar -->
    <div class="admin-sidebar-header">
        <div class="admin-sidebar-logo-icon">
            <i class="fas fa-shield-halved"></i>
        </div>
        <div class="admin-sidebar-brand">
            <h2>Panel Admin</h2>
            <span>SIM-SEM SID &bull; PNL TIK</span>
        </div>
    </div>

    <!-- Info Akun Admin Aktif -->
    <div class="admin-sidebar-user">
        <div class="admin-user-avatar">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="admin-user-info">
            <div class="admin-user-name" title="<?= htmlspecialchars($admin_nama_tampil); ?>">
                <?= htmlspecialchars($admin_nama_tampil); ?>
            </div>
            <div class="admin-user-role">
                <i class="fas fa-circle-check" style="font-size: 0.65rem; color: #22c55e;"></i> Administrator TIK
            </div>
        </div>
    </div>

    <!-- Navigasi Menu Admin -->
    <nav class="admin-sidebar-nav">
        <ul class="admin-sidebar-menu">

            <!-- 1. DASHBOARD -->
            <li>
                <a href="dashboard_admin.php" class="admin-sidebar-link <?= ($active_menu === 'dashboard') ? 'active' : ''; ?>" title="Dashboard Utama Admin">
                    <i class="fas fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- 2. PENDAFTARAN & PENGAJUAN (SUBMENU) -->
            <li class="admin-sidebar-dropdown <?= $is_pendaftaran_group ? 'active open' : ''; ?>">
                <button type="button" class="admin-sidebar-dropdown-toggle" title="Kelola pendaftaran seminar, sidang & pengajuan judul">
                    <i class="fas fa-clipboard-check menu-icon"></i>
                    <span>Pendaftaran & Pengajuan</span>
                    <i class="fas fa-chevron-down chevron-icon"></i>
                </button>
                <ul class="admin-sidebar-submenu">
                    <li>
                        <a href="admin_pendaftaran.php" class="admin-submenu-link <?= ($active_menu === 'pendaftaran') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Data Pendaftaran</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_pengajuan_judul.php" class="admin-submenu-link <?= ($active_menu === 'judul') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-book-bookmark"></i>
                            <span>Pengajuan Judul</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- 3. JADWAL & KALENDER (SUBMENU) -->
            <li class="admin-sidebar-dropdown <?= $is_jadwal_group ? 'active open' : ''; ?>">
                <button type="button" class="admin-sidebar-dropdown-toggle" title="Kelola jadwal seminar proposal, sidang akhir & kalender akademik">
                    <i class="fas fa-calendar-days menu-icon"></i>
                    <span>Jadwal & Kalender</span>
                    <i class="fas fa-chevron-down chevron-icon"></i>
                </button>
                <ul class="admin-sidebar-submenu">
                    <li>
                        <a href="admin_jadwal_seminar.php" class="admin-submenu-link <?= ($active_menu === 'seminar') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-chalkboard-user"></i>
                            <span>Jadwal Seminar</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_jadwal_sidang.php" class="admin-submenu-link <?= ($active_menu === 'sidang') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-award"></i>
                            <span>Jadwal Sidang</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_kalender.php" class="admin-submenu-link <?= ($active_menu === 'kalender') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-calendar-check"></i>
                            <span>Kalender Akademik</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- 4. DATA AKADEMIK (SUBMENU: DATA MAHASISWA) -->
            <li class="admin-sidebar-dropdown <?= $is_akademik_group ? 'active open' : ''; ?>">
                <button type="button" class="admin-sidebar-dropdown-toggle" title="Kelola data mahasiswa">
                    <i class="fas fa-graduation-cap menu-icon"></i>
                    <span>Data Akademik</span>
                    <i class="fas fa-chevron-down chevron-icon"></i>
                </button>
                <ul class="admin-sidebar-submenu">
                    <li>
                        <a href="admin_mahasiswa.php" class="admin-submenu-link <?= ($active_menu === 'mahasiswa') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Data Mahasiswa</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- 5. DOSEN & BIMBINGAN (SUBMENU) -->
            <li class="admin-sidebar-dropdown <?= $is_dosen_group ? 'active open' : ''; ?>">
                <button type="button" class="admin-sidebar-dropdown-toggle" title="Kelola data dosen pengajar & kuota bimbingan">
                    <i class="fas fa-user-tie menu-icon"></i>
                    <span>Dosen & Bimbingan</span>
                    <i class="fas fa-chevron-down chevron-icon"></i>
                </button>
                <ul class="admin-sidebar-submenu">
                    <li>
                        <a href="admin_dosen.php" class="admin-submenu-link <?= ($active_menu === 'dosen') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Data Dosen</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_kuota_dosen.php" class="admin-submenu-link <?= ($active_menu === 'kuota') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-chart-pie"></i>
                            <span>Kuota Bimbingan Dosen</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- 6. DOKUMEN (SUBMENU) -->
            <li class="admin-sidebar-dropdown <?= $is_dokumen_group ? 'active open' : ''; ?>">
                <button type="button" class="admin-sidebar-dropdown-toggle" title="Kelola berkas proposal, laporan, dan dokumen kelayakan">
                    <i class="fas fa-folder-open menu-icon"></i>
                    <span>Dokumen</span>
                    <i class="fas fa-chevron-down chevron-icon"></i>
                </button>
                <ul class="admin-sidebar-submenu">
                    <li>
                        <a href="admin_dokumen.php" class="admin-submenu-link <?= ($active_menu === 'dokumen' && ($active_submenu === 'dokumen_mhs' || empty($active_submenu))) ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-file-contract"></i>
                            <span>Dokumen Mahasiswa</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_dokumen.php?tab=pemeriksaan" class="admin-submenu-link <?= ($active_menu === 'dokumen' && $active_submenu === 'pemeriksaan') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Pemeriksaan Berkas</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_dokumen.php?tab=status" class="admin-submenu-link <?= ($active_menu === 'dokumen' && $active_submenu === 'status') ? 'active-submenu' : ''; ?>">
                            <i class="fas fa-chart-simple"></i>
                            <span>Status Upload</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- 7. RIWAYAT & KOMUNIKASI -->
            <li>
                <a href="admin_riwayat_bimbingan.php" class="admin-sidebar-link <?= ($active_menu === 'riwayat') ? 'active' : ''; ?>" title="Riwayat konsultasi bimbingan & catatan komunikasi">
                    <i class="fas fa-comments"></i>
                    <span>Riwayat & Komunikasi</span>
                </a>
            </li>

            <!-- 8. LAPORAN (SUBMENU) -->
            <li class="admin-sidebar-dropdown <?= $is_laporan_group ? 'active open' : ''; ?>">
                <button type="button" class="admin-sidebar-dropdown-toggle" title="Unduh dan cetak laporan PDF & Excel">
                    <i class="fas fa-file-invoice menu-icon"></i>
                    <span>Laporan</span>
                    <i class="fas fa-chevron-down chevron-icon"></i>
                </button>
                <ul class="admin-sidebar-submenu">
                    <li>
                        <a href="admin_laporan.php?jenis=pendaftaran_seminar" class="admin-submenu-link">
                            <i class="fas fa-file-lines"></i>
                            <span>Laporan Pendaftaran</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_laporan.php?jenis=jadwal_seminar" class="admin-submenu-link">
                            <i class="fas fa-calendar-days"></i>
                            <span>Laporan Seminar</span>
                        </a>
                    </li>
                    <li>
                        <a href="admin_laporan.php?jenis=jadwal_sidang" class="admin-submenu-link">
                            <i class="fas fa-award"></i>
                            <span>Laporan Sidang</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- 9. PENGATURAN -->
            <li>
                <a href="admin_pengaturan.php" class="admin-sidebar-link <?= ($active_menu === 'pengaturan') ? 'active' : ''; ?>" title="Pengaturan Akun & Sistem">
                    <i class="fas fa-gear"></i>
                    <span>Pengaturan</span>
                </a>
            </li>
        </ul>

        <!-- Pemisah Visual Navigasi Keluar -->
        <div class="admin-sidebar-divider">
            <span class="admin-sidebar-divider-label">Navigasi Keluar</span>
        </div>

        <ul class="admin-sidebar-menu">
            <li>
                <a href="index.php" class="admin-sidebar-link" title="Lihat Website Publik">
                    <i class="fas fa-globe"></i>
                    <span>Kembali ke Website</span>
                </a>
            </li>
            <!-- 10. LOGOUT -->
            <li>
                <a href="logout.php" class="admin-sidebar-link link-logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari Panel Administrator?');" title="Keluar dari Panel Admin">
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- Skrip Interaktif Accordion Submenu & Responsif Mobile -->
<script>
(function() {
    function initAdminSidebar() {
        const sb = document.getElementById('adminSidebar');
        if (!sb) return;

        // Toggle Accordion Submenu
        const dropdownToggles = sb.querySelectorAll('.admin-sidebar-dropdown-toggle');
        dropdownToggles.forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const parent = this.closest('.admin-sidebar-dropdown');
                if (parent) {
                    parent.classList.toggle('open');
                }
            });
        });

        // Toggle Sidebar Mobile
        const toggleButtons = document.querySelectorAll('.admin-mobile-toggle, .admin-sidebar-toggle, #sidebarToggle');
        toggleButtons.forEach(function(btn) {
            btn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                sb.classList.toggle('show');
            };
        });

        // Tutup otomatis saat klik luar di layar mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 992 && sb.classList.contains('show')) {
                let insideToggle = false;
                toggleButtons.forEach(function(btn) {
                    if (btn.contains(e.target)) insideToggle = true;
                });
                if (!sb.contains(e.target) && !insideToggle) {
                    sb.classList.remove('show');
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminSidebar);
    } else {
        initAdminSidebar();
    }
})();
</script>
