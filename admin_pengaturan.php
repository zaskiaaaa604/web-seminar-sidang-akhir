<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: admin_pengaturan.php (Pengaturan Akun & Profil Administrator)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Validasi Keamanan Hak Akses: Hanya admin yang berhak mengakses halaman ini
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$success_msg = '';
$error_msg = '';

// Ambil data admin terbaru dari database
$stmt_admin = mysqli_prepare($conn, "SELECT id, username, nama_lengkap, password, created_at FROM admin WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_admin, "i", $admin_id);
mysqli_stmt_execute($stmt_admin);
$res_admin = mysqli_stmt_get_result($stmt_admin);
$admin_data = mysqli_fetch_assoc($res_admin);

if (!$admin_data) {
    header("Location: logout.php");
    exit;
}

// Proses Formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // UPDATE PROFIL
    if ($action === 'update_profile') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $username_baru = trim($_POST['username'] ?? '');

        if (empty($nama_lengkap) || empty($username_baru)) {
            $error_msg = "Nama lengkap dan username tidak boleh kosong.";
        } else {
            // Cek apakah username sudah digunakan admin lain
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM admin WHERE username = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_check, "si", $username_baru, $admin_id);
            mysqli_stmt_execute($stmt_check);
            $res_check = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($res_check) > 0) {
                $error_msg = "Username '$username_baru' sudah digunakan oleh akun lain. Silakan pilih username lain.";
            } else {
                $stmt_upd = mysqli_prepare($conn, "UPDATE admin SET nama_lengkap = ?, username = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_upd, "ssi", $nama_lengkap, $username_baru, $admin_id);

                if (mysqli_stmt_execute($stmt_upd)) {
                    $_SESSION['username'] = $username_baru;
                    $_SESSION['nama'] = $nama_lengkap;
                    $admin_data['username'] = $username_baru;
                    $admin_data['nama_lengkap'] = $nama_lengkap;
                    $success_msg = "Data profil administrator berhasil diperbarui.";
                } else {
                    $error_msg = "Gagal memperbarui profil: " . mysqli_error($conn);
                }
            }
        }
    }
}

$active_menu = 'pengaturan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun & Sistem - SIM-SEMSID PNL</title>

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS Utama & Admin -->
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
                    <h1>Pengaturan Akun & Sistem</h1>
                    <span>Kelola profil admin, keamanan password, dan informasi server</span>
                </div>
            </div>

            <div class="admin-topbar-right">
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <div class="admin-badge-date">
                    <i class="fas fa-calendar-day"></i>
                    <span><?= date('l, d F Y'); ?></span>
                </div>
                <a href="index.php" target="_blank" class="btn-admin-outline" style="font-size: 0.8rem; padding: 6px 12px;">
                    <i class="fas fa-globe"></i> Website Publik
                </a>
            </div>
        </header>

        <!-- Konten Halaman Pengaturan -->
        <main class="admin-content-body">

            <!-- Breadcrumb Navigation -->
            <nav style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">
                <a href="dashboard_admin.php" style="color: var(--admin-blue-primary); text-decoration: none;"><i class="fas fa-home"></i> Dashboard</a>
                <span style="margin: 0 8px;">/</span>
                <span style="color: #1e293b; font-weight: 600;">Pengaturan</span>
            </nav>

            <?php if (!empty($success_msg)): ?>
                <div style="background: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
                    <div><?= htmlspecialchars($success_msg); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div style="background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
                    <div><?= htmlspecialchars($error_msg); ?></div>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 25px;">

                <!-- Form Profil Admin -->
                <div class="admin-card-panel" style="margin-bottom: 0;">
                    <div class="admin-panel-header">
                        <h3><i class="fas fa-user-gear" style="color: var(--admin-blue-primary);"></i> Profil Administrator</h3>
                    </div>
                    <form method="POST" action="admin_pengaturan.php" style="padding-top: 10px;">
                        <input type="hidden" name="action" value="update_profile">

                        <div style="margin-bottom: 18px;">
                            <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 0.88rem;">
                                Nama Lengkap
                            </label>
                            <input type="text" name="nama_lengkap" class="admin-input-control" value="<?= htmlspecialchars($admin_data['nama_lengkap']); ?>" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                        </div>

                        <div style="margin-bottom: 18px;">
                            <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 0.88rem;">
                                Username Login
                            </label>
                            <input type="text" name="username" class="admin-input-control" value="<?= htmlspecialchars($admin_data['username']); ?>" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                            <small style="color: #64748b; font-size: 0.78rem; display: block; margin-top: 4px;">Username digunakan saat login ke panel admin.</small>
                        </div>

                        <div style="margin-bottom: 18px;">
                            <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 0.88rem;">
                                Peran / Hak Akses
                            </label>
                            <input type="text" disabled value="Administrator Utama (Full Access)" style="width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; border-radius: 6px; font-size: 0.9rem;">
                        </div>

                        <button type="submit" class="btn-admin-orange" style="width: 100%; justify-content: center; padding: 11px;">
                            <i class="fas fa-floppy-disk"></i> Simpan Perubahan Profil
                        </button>
                    </form>
                </div>

                <!-- Panel Keamanan & Hak Akses Administrator -->
                <div class="admin-card-panel" style="margin-bottom: 0;">
                    <div class="admin-panel-header">
                        <h3><i class="fas fa-shield-halved" style="color: var(--admin-orange);"></i> Keamanan & Otoritas Akun</h3>
                    </div>
                    <div style="padding-top: 10px;">
                        <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 14px; border-radius: 6px; margin-bottom: 16px;">
                            <div style="font-weight: 700; color: #1e3a8a; font-size: 0.9rem; margin-bottom: 4px;">
                                <i class="fas fa-lock"></i> Kredensial Terproteksi Enkripsi
                            </div>
                            <p style="font-size: 0.84rem; color: #334155; margin: 0; line-height: 1.5;">
                                Seluruh akun sistem SIM-SEM SID dilindungi algoritma kriptografi <strong>Bcrypt (PHP password_hash)</strong> untuk menjamin kerahasiaan dan keamanan data otentikasi.
                            </p>
                        </div>

                        <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 14px;">
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Status Autentikasi</div>
                            <div style="font-weight: 700; color: #16a34a; font-size: 0.92rem; margin-top: 4px;">
                                <i class="fas fa-circle-check"></i> Sesi Aktif & Terverifikasi
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                                Login via: Panel Administrator Resmi PNL
                            </div>
                        </div>

                        <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px;">
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Otoritas Sistem</div>
                            <div style="font-weight: 700; color: #1e3a8a; font-size: 0.92rem; margin-top: 4px;">
                                <i class="fas fa-user-check"></i> Super Administrator (TIK)
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 2px;">
                                Akses penuh verifikasi dokumen, pendaftaran, dan penetapan jadwal.
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Panel Informasi Sistem & Lingkungan Server -->
            <div class="admin-card-panel">
                <div class="admin-panel-header">
                    <h3><i class="fas fa-circle-info" style="color: #0284c7;"></i> Informasi Sistem & Lingkungan Server</h3>
                    <span style="font-size: 0.8rem; background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 20px; font-weight: 600;">SIM-SEM SID v2.0</span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-top: 15px;">
                    <div style="background: #f8fafc; padding: 14px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 0.78rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Institusi / Prodi</div>
                        <div style="font-weight: 700; color: var(--admin-navy); margin-top: 4px; font-size: 0.92rem;">Politeknik Negeri Lhokseumawe</div>
                        <div style="font-size: 0.82rem; color: #475569;">D4 Teknologi Rekayasa Perangkat Lunak</div>
                    </div>

                    <div style="background: #f8fafc; padding: 14px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 0.78rem; color: #64748b; font-weight: 600; text-transform: uppercase;">PHP Version</div>
                        <div style="font-weight: 700; color: #16a34a; margin-top: 4px; font-size: 0.92rem;">
                            <i class="fab fa-php"></i> PHP <?= phpversion(); ?>
                        </div>
                        <div style="font-size: 0.82rem; color: #475569;">Environment: XAMPP Windows</div>
                    </div>

                    <div style="background: #f8fafc; padding: 14px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 0.78rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Database Server</div>
                        <div style="font-weight: 700; color: var(--admin-blue-primary); margin-top: 4px; font-size: 0.92rem;">
                            <i class="fas fa-database"></i> MySQL / MariaDB
                        </div>
                        <div style="font-size: 0.82rem; color: #475569;">Host: localhost (3306) | DB: db_seminar_sidang</div>
                    </div>

                    <div style="background: #f8fafc; padding: 14px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 0.78rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Status Direktori Uploads</div>
                        <?php 
                        $upload_dir = __DIR__ . '/uploads';
                        $is_writable = is_dir($upload_dir) && is_writable($upload_dir);
                        ?>
                        <div style="font-weight: 700; color: <?= $is_writable ? '#16a34a' : '#ea580c'; ?>; margin-top: 4px; font-size: 0.92rem;">
                            <i class="fas <?= $is_writable ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <?= $is_writable ? 'Tersedia & Siap Tulis (Writable)' : 'Perlu Izin Akses'; ?>
                        </div>
                        <div style="font-size: 0.82rem; color: #475569;">Folder: /uploads (Berkas Proposal & Skripsi)</div>
                    </div>
                </div>
            </div>

        </main>

        <!-- Footer Admin -->
        <footer class="admin-footer">
            &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Program Studi Teknologi Rekayasa Perangkat Lunak, Politeknik Negeri Lhokseumawe.
        </footer>

    </div>

    <!-- JavaScript Responsif Sidebar Toggle -->
    <script>
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('show');
        }

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
    <script src="notifikasi.js"></script>
</body>
</html>
