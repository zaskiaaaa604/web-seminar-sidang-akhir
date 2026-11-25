<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: public_sidebar.php (Sidebar Publik Ringkas - 5 Menu Utama)
 * ===================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['login']) && $_SESSION['login'] === true;
$user_role    = $_SESSION['role'] ?? '';
$user_name    = $_SESSION['nama'] ?? '';

if (!isset($active_menu)) {
    $active_menu = 'beranda';
}
?>
<!-- Sidebar Publik (5 Menu Utama Pengunjung) -->
<aside class="site-sidebar" id="siteSidebar">
    <!-- Header Sidebar -->
    <div class="site-sidebar-header">
        <div class="site-sidebar-logo-icon">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="site-sidebar-brand">
            <h2>SIM-SEMSID</h2>
            <span>POLITEKNIK NEGERI LHOKSEUMAWE</span>
        </div>
    </div>

    <!-- Status Pengguna / Pengunjung -->
    <div class="site-sidebar-user">
        <div class="site-user-avatar <?= $is_logged_in ? 'avatar-user' : 'avatar-guest'; ?>">
            <i class="fas <?= ($user_role === 'admin') ? 'fa-user-shield' : (($user_role === 'dosen') ? 'fa-chalkboard-teacher' : (($user_role === 'mahasiswa') ? 'fa-user-graduate' : 'fa-user')); ?>"></i>
        </div>
        <div class="site-user-info">
            <div class="site-user-name" title="<?= $is_logged_in ? htmlspecialchars($user_name) : 'Pengunjung Publik'; ?>">
                <?= $is_logged_in ? htmlspecialchars($user_name) : 'Pengunjung Publik'; ?>
            </div>
            <div class="site-user-role">
                <?php if ($is_logged_in): ?>
                    <i class="fas fa-circle-check" style="color: #22c55e;"></i> <?= ucfirst(htmlspecialchars($user_role)); ?>
                <?php else: ?>
                    <i class="fas fa-globe" style="color: #94a3b8;"></i> Akses Terbuka
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 5 MENU UTAMA PUBLIK -->
    <nav class="site-sidebar-nav">
        <ul class="site-sidebar-menu">
            <!-- 1. Beranda -->
            <li>
                <a href="index.php" class="site-sidebar-link <?= ($active_menu === 'beranda') ? 'active' : ''; ?>" title="Beranda Utama SIM-SEMSID">
                    <i class="fas fa-house"></i>
                    <span>Beranda</span>
                </a>
            </li>

            <!-- 2. Jadwal & Kalender -->
            <li>
                <a href="jadwal_kalender.php" class="site-sidebar-link <?= ($active_menu === 'jadwal_kalender') ? 'active' : ''; ?>" title="Jadwal Seminar, Sidang & Kalender Akademik">
                    <i class="fas fa-calendar-days"></i>
                    <span>Jadwal & Kalender</span>
                </a>
            </li>

            <!-- 3. Informasi Pendaftaran -->
            <li>
                <a href="informasi_pendaftaran.php" class="site-sidebar-link <?= ($active_menu === 'informasi_pendaftaran') ? 'active' : ''; ?>" title="Persyaratan & Alur Pendaftaran Seminar / Sidang">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Informasi Pendaftaran</span>
                </a>
            </li>

            <!-- 4. Panduan -->
            <li>
                <a href="panduan.php" class="site-sidebar-link <?= ($active_menu === 'panduan') ? 'active' : ''; ?>" title="Panduan Lengkap Penggunaan Portal">
                    <i class="fas fa-book-open"></i>
                    <span>Panduan</span>
                </a>
            </li>
        </ul>

        <!-- Pemisah Portal Login Pengguna -->
        <div class="site-sidebar-divider">
            <span class="site-sidebar-divider-label">Akses Masuk Akun</span>
        </div>

        <ul class="site-sidebar-menu">
            <?php if ($is_logged_in): ?>
                <li>
                    <a href="<?= ($user_role === 'admin') ? 'dashboard_admin.php' : (($user_role === 'dosen') ? 'dashboard_dosen.php' : 'dashboard_mahasiswa.php'); ?>" 
                       class="site-sidebar-link site-link-admin" title="Kembali ke Dashboard Anda">
                        <i class="fas fa-gauge-high"></i>
                        <span>Buka Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="site-sidebar-link link-logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari akun?');" style="color: #fca5a5;">
                        <i class="fas fa-right-from-bracket"></i>
                        <span>Keluar Akun</span>
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <a href="login_mahasiswa.php" class="site-sidebar-link" title="Masuk Portal Mahasiswa">
                        <i class="fas fa-user-graduate" style="color: #60a5fa;"></i>
                        <span>Login Mahasiswa</span>
                    </a>
                </li>
                <li>
                    <a href="login_dosen.php" class="site-sidebar-link" title="Masuk Portal Dosen">
                        <i class="fas fa-chalkboard-teacher" style="color: #34d399;"></i>
                        <span>Login Dosen</span>
                    </a>
                </li>
                <li>
                    <a href="login_admin.php" class="site-sidebar-link site-link-admin" title="Masuk Panel Administrator">
                        <i class="fas fa-user-shield" style="color: #fb923c;"></i>
                        <span>Login Admin</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</aside>

<!-- Skrip Pengendali Responsif Sidebar Mobile / Tablet -->
<script>
(function() {
    function initSiteSidebarMobile() {
        const sb = document.getElementById('siteSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const toggleBtns = document.querySelectorAll('.btn-sidebar-toggle, #sidebarToggleBtn');
        if (!sb) return;

        function openSidebar() {
            sb.classList.add('show');
            if (backdrop) backdrop.classList.add('show');
            document.body.style.overflow = window.innerWidth <= 992 ? 'hidden' : '';
        }

        function closeSidebar() {
            sb.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }

        toggleBtns.forEach(function(btn) {
            btn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (sb.classList.contains('show')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            };
        });

        if (backdrop) {
            backdrop.onclick = function() {
                closeSidebar();
            };
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sb.classList.contains('show')) {
                closeSidebar();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSiteSidebarMobile);
    } else {
        initSiteSidebarMobile();
    }
})();
</script>
