<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * FILE: dashboard_mahasiswa.php (Dashboard Mahasiswa)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Validasi Keamanan Hak Akses: Hanya mahasiswa yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login_mahasiswa.php");
    exit;
}

// Jika pengguna yang login adalah admin, alihkan ke dashboard admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mahasiswa') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: dashboard_admin.php");
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'dosen') {
        header("Location: dashboard_dosen.php");
    } else {
        header("Location: login_mahasiswa.php");
    }
    exit;
}

$mahasiswa_id = $_SESSION['mahasiswa_id'];
$nim          = $_SESSION['nim'];
$nama         = $_SESSION['nama'];
$prodi        = $_SESSION['program_studi'] ?? 'Teknologi Rekayasa Perangkat Lunak';
$email        = $_SESSION['email'] ?? '-';

// Ambil total pengajuan judul mahasiswa ini
$total_pengajuan_mhs = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul WHERE mahasiswa_id = $mahasiswa_id"));

// Ambil data dosen aktif dari database MySQL untuk pilihan Dosen Pembimbing & Penguji
$dosen_query = "SELECT id, nidn, nama_dosen, gelar_akademik FROM dosen WHERE status = 'Aktif' ORDER BY nama_dosen ASC";
$result_dosen_aktif = mysqli_query($conn, $dosen_query);
$daftar_dosen = [];
if ($result_dosen_aktif) {
    while ($d = mysqli_fetch_assoc($result_dosen_aktif)) {
        $daftar_dosen[] = $d;
    }
}

$pengajuan_sukses = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajukan_seminar_sidang') {
    $jenis_kegiatan    = htmlspecialchars($_POST['jenis_kegiatan'] ?? '');
    $judul_skripsi     = htmlspecialchars($_POST['judul'] ?? '');
    $id_pembimbing     = (int)($_POST['dosen_pembimbing'] ?? 0);
    $id_penguji        = (int)($_POST['dosen_penguji'] ?? 0);

    // Ambil nama dosen pembimbing & penguji yang dipilih
    $nama_pembimbing = 'Dosen Pembimbing Terpilih';
    $nama_penguji    = 'Dosen Penguji Terpilih';

    foreach ($daftar_dosen as $dsn) {
        if ((int)$dsn['id'] === $id_pembimbing) {
            $nama_pembimbing = $dsn['nama_dosen'] . ', ' . $dsn['gelar_akademik'];
        }
        if ((int)$dsn['id'] === $id_penguji) {
            $nama_penguji = $dsn['nama_dosen'] . ', ' . $dsn['gelar_akademik'];
        }
    }

    $kategori = ($jenis_kegiatan === 'Seminar Proposal') ? 'seminar' : 'sidang';
    if ($mahasiswa_id > 0 && !empty($judul_skripsi) && $id_pembimbing > 0 && $id_penguji > 0) {
        $stmt_reg = mysqli_prepare($conn, "INSERT INTO pendaftaran_seminar_sidang (mahasiswa_id, jenis, kategori, judul, dosen_pembimbing_id, dosen_penguji_id, status) VALUES (?, ?, ?, ?, ?, ?, 'Menunggu Verifikasi')");
        if ($stmt_reg) {
            mysqli_stmt_bind_param($stmt_reg, "isssii", $mahasiswa_id, $jenis_kegiatan, $kategori, $judul_skripsi, $id_pembimbing, $id_penguji);
            mysqli_stmt_execute($stmt_reg);
            $new_reg_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_reg);

            // NOTIFIKASI OTOMATIS:
            // 1. Notifikasi Mahasiswa (B.1)
            kirimNotifikasi($conn, 'mahasiswa', $mahasiswa_id, "Pendaftaran $jenis_kegiatan Berhasil Dikirim", "Pendaftaran $jenis_kegiatan untuk judul \"$judul_skripsi\" telah diajukan. Menunggu verifikasi berkas oleh Administrator Prodi.", 'dashboard_mahasiswa.php', 'pengajuan');

            // 2. Notifikasi Admin (D.1, D.2, D.3)
            $judul_notif_admin = ($kategori === 'seminar') ? 'Pendaftaran Seminar Baru' : 'Pendaftaran Sidang Baru';
            kirimNotifikasiAdmin($conn, $judul_notif_admin, "Mahasiswa $nama (NIM: $nim) telah mendaftarkan $jenis_kegiatan: \"$judul_skripsi\". Membutuhkan verifikasi berkas oleh admin.", 'admin_pendaftaran.php', 'verifikasi');
        }
    }

    $pengajuan_sukses = "Pendaftaran <strong>$jenis_kegiatan</strong> berhasil diajukan dengan <strong>Dosen Pembimbing: $nama_pembimbing</strong> dan <strong>Dosen Penguji: $nama_penguji</strong>. Data diteruskan ke Admin untuk verifikasi berkas dan penetapan jadwal!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa - <?= htmlspecialchars($nama); ?> | SIM-SEMSID</title>
    
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Utama -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-wrapper">

    <!-- Header / Navbar Dashboard Mahasiswa -->
    <header class="dashboard-nav">
        <div class="container dashboard-nav-container">
            <a href="dashboard_mahasiswa.php" class="logo-area">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text">
                    <h1>SIM-SEMSID</h1>
                    <span>POLITEKNIK NEGERI LHOKSEUMAWE</span>
                </div>
            </a>

            <div class="user-profile-menu">
                <div class="user-badge-info">
                    <div class="user-name"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($nama); ?></div>
                    <div class="user-role">Mahasiswa &bull; NIM: <?= htmlspecialchars($nim); ?></div>
                </div>
                <!-- Ikon Lonceng Notifikasi Real-time -->
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <a href="dokumen_mahasiswa.php" class="btn btn-outline-admin" style="padding: 7px 14px; font-size: 0.85rem;" title="Upload Dokumen Persyaratan">
                    <i class="fas fa-file-upload"></i> Upload Dokumen
                </a>
                <a href="logout.php" class="btn-logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari akun?');">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Konten Utama Dashboard -->
    <main class="dashboard-main">
        <div class="container">

            <!-- Navigasi Menu Mahasiswa -->
            <div style="display: flex; gap: 10px; margin-bottom: 22px; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
                <a href="dashboard_mahasiswa.php" class="btn btn-primary" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-tachometer-alt"></i> Dashboard Utama
                </a>
                <a href="pengajuan_judul_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px; border-color: var(--primary); color: var(--primary);">
                    <i class="fas fa-lightbulb"></i> Pengajuan Judul (<?= $total_pengajuan_mhs; ?>)
                </a>
                <a href="bimbingan_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-book-reader"></i> Riwayat Bimbingan
                </a>
                <a href="dokumen_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-file-arrow-up"></i> Upload Dokumen
                </a>
                <a href="index.php" class="btn btn-outline-admin" style="margin-left: auto; font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-external-link-alt"></i> Lihat Halaman Utama
                </a>
            </div>

            <!-- Pesan Notifikasi Sukses Pendaftaran Seminar / Sidang -->
            <?php if (!empty($pengajuan_sukses)): ?>
                <div class="alert alert-success" style="box-shadow: var(--shadow-sm); margin-bottom: 25px; border-left: 4px solid #16a34a;">
                    <i class="fas fa-check-circle" style="font-size: 1.3rem; margin-top: 2px;"></i>
                    <div>
                        <?= $pengajuan_sukses; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pesan Notifikasi Sukses Ganti Password -->
            <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'password_berhasil'): ?>
                <div class="alert alert-success" style="box-shadow: var(--shadow-sm); margin-bottom: 25px;">
                    <i class="fas fa-check-circle" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div>
                        <strong>Password Berhasil Diperbarui!</strong> Password akun Anda telah berhasil diubah secara aman. Silakan gunakan password baru ini pada login berikutnya.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Banner Sambutan Mahasiswa -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2>Selamat Datang, <?= htmlspecialchars($nama); ?>! 👋</h2>
                    <p>
                        Anda berhasil masuk ke Portal Mahasiswa Sistem Informasi Manajemen Seminar dan Sidang Akhir - Program Studi Teknologi Rekayasa Perangkat Lunak, Politeknik Negeri Lhokseumawe.
                    </p>
                </div>
                <div class="welcome-badge">
                    <i class="fas fa-id-card"></i> NIM: <?= htmlspecialchars($nim); ?>
                </div>
            </div>

            <!-- Kartu Status & Ringkasan -->
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
                <div class="dash-stat-card">
                    <div class="dash-stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4>Aktif</h4>
                        <p>Status Mahasiswa</p>
                    </div>
                </div>

                <a href="pengajuan_judul_mahasiswa.php" class="dash-stat-card" style="text-decoration: none; border-left: 4px solid var(--primary); transition: var(--transition);" title="Klik untuk mengelola Usulan Judul Penelitian">
                    <div class="dash-stat-icon" style="background-color: var(--primary-light); color: var(--primary);">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4><?= $total_pengajuan_mhs; ?> Usulan</h4>
                        <p style="color: var(--primary); font-weight: 700;">Pengajuan Judul (&rarr;)</p>
                    </div>
                </a>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon" style="background-color: #eff6ff; color: #2563eb;">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4>Siap Daftar</h4>
                        <p>Seminar Proposal</p>
                    </div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon" style="background-color: #fef3c7; color: #d97706;">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4>Tahap 1</h4>
                        <p>Sidang Skripsi</p>
                    </div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon" style="background-color: #f0fdf4; color: #16a34a;">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4>Online</h4>
                        <p>Layanan Berkas</p>
                    </div>
                </div>
            </div>

            <!-- Panel Profil & Informasi Akademik -->
            <div class="dashboard-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-id-badge"></i> Data Profil Mahasiswa</h3>
                    <span class="badge-status status-berlangsung"><i class="fas fa-check"></i> Terverifikasi</span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                    <div style="background: #f8fafc; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Nama Lengkap</span>
                        <p style="font-size: 1.1rem; font-weight: 700; color: var(--secondary); margin-top: 4px;"><?= htmlspecialchars($nama); ?></p>
                    </div>

                    <div style="background: #f8fafc; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Nomor Induk Mahasiswa (NIM)</span>
                        <p style="font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-top: 4px;"><?= htmlspecialchars($nim); ?></p>
                    </div>

                    <div style="background: #f8fafc; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Program Studi</span>
                        <p style="font-size: 1.1rem; font-weight: 700; color: var(--secondary); margin-top: 4px;"><?= htmlspecialchars($prodi); ?></p>
                    </div>

                    <div style="background: #f8fafc; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color);">
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Alamat Email Kampus</span>
                        <p style="font-size: 1.1rem; font-weight: 700; color: var(--secondary); margin-top: 4px;"><?= htmlspecialchars($email); ?></p>
                    </div>
                </div>
            </div>

            <!-- Panel Menu Layanan Akademik Mendatang -->
            <div class="dashboard-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-tasks"></i> Menu Layanan Pendaftaran</h3>
                    <span class="badge" style="background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 4px 10px; border-radius: 50px; font-size: 0.8rem; font-weight: 600;">
                        <i class="fas fa-clock"></i> Siap Dilanjutkan di Tahap Berikutnya
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div style="border: 2px solid var(--primary); border-radius: 12px; padding: 22px; background: var(--primary-light);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 44px; height: 44px; border-radius: 8px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                                <div>
                                    <h4 style="font-size: 1.05rem; color: var(--secondary); font-weight: 700;">Pengajuan Judul Penelitian</h4>
                                    <span style="font-size: 0.8rem; color: var(--primary); font-weight: 700;">Fitur Aktif & Terintegrasi</span>
                                </div>
                            </div>
                            <span class="badge" style="background: var(--primary); color: white; font-size: 0.75rem; padding: 3px 10px; border-radius: 50px;">Utama</span>
                        </div>
                        <p style="font-size: 0.88rem; color: #78350f; margin-bottom: 16px;">
                            Ajukan judul penelitian skripsi, pilih dosen pembimbing tujuan, unggah dokumen & gambar diagram, serta pantau catatan revisi.
                        </p>
                        <a href="pengajuan_judul_mahasiswa.php" class="btn btn-primary" style="width: 100%; text-decoration: none; text-align: center; display: block; padding: 11px;">
                            <i class="fas fa-paper-plane"></i> Kelola Pengajuan Judul (<?= $total_pengajuan_mhs; ?>)
                        </a>
                    </div>

                    <div style="border: 2px solid var(--primary); border-radius: 12px; padding: 22px; background: white;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 44px; height: 44px; border-radius: 8px; background: #fff7ed; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                    <i class="fas fa-book-reader"></i>
                                </div>
                                <div>
                                    <h4 style="font-size: 1.05rem; color: var(--secondary); font-weight: 700;">Riwayat Bimbingan Mahasiswa</h4>
                                    <span style="font-size: 0.8rem; color: var(--primary); font-weight: 700;">Logbook Bimbingan Aktif</span>
                                </div>
                            </div>
                            <span class="badge" style="background: var(--primary); color: white; font-size: 0.75rem; padding: 3px 10px; border-radius: 50px;">Bimbingan</span>
                        </div>
                        <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 16px;">
                            Catat sesi bimbingan bersama dosen pembimbing, laporkan progres penelitian, unggah dokumen revisi, dan lihat arahan dosen.
                        </p>
                        <a href="bimbingan_mahasiswa.php" class="btn btn-primary" style="width: 100%; text-decoration: none; text-align: center; display: block; padding: 11px;">
                            <i class="fas fa-book-bookmark"></i> Buka Logbook Riwayat Bimbingan
                        </a>
                    </div>
                    <div style="border: 1.5px solid var(--border-color); border-radius: 12px; padding: 22px; background: white;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <div style="width: 44px; height: 44px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                <i class="fas fa-file-pen"></i>
                            </div>
                            <div>
                                <h4 style="font-size: 1.05rem; color: var(--secondary); font-weight: 700;">Daftar Seminar Proposal</h4>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">Pendaftaran judul & berkas usulan</span>
                            </div>
                        </div>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
                            Isi judul usulan, pilih Dosen Pembimbing dan Dosen Penguji langsung dari database, serta unggah berkas proposal.
                        </p>
                        <button type="button" class="btn btn-primary" onclick="bukaModalPengajuan('Seminar Proposal');" style="width: 100%;">
                            <i class="fas fa-file-signature"></i> Buka Formulir Pendaftaran (Pilih Dosen)
                        </button>
                    </div>

                    <div style="border: 1.5px solid var(--border-color); border-radius: 12px; padding: 22px; background: white;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <div style="width: 44px; height: 44px; border-radius: 8px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                <i class="fas fa-award"></i>
                            </div>
                            <div>
                                <h4 style="font-size: 1.05rem; color: var(--secondary); font-weight: 700;">Daftar Sidang Akhir Skripsi</h4>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">Ujian komprehensif kelulusan</span>
                            </div>
                        </div>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
                            Pengajuan berkas laporan tugas akhir lengkap, lembar persetujuan pembimbing, dan syarat yudisium.
                        </p>
                        <button type="button" class="btn btn-outline-primary" onclick="bukaModalPengajuan('Sidang Akhir Skripsi');" style="width: 100%;">
                            <i class="fas fa-graduation-cap"></i> Buka Formulir Pendaftaran (Pilih Dosen)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tautan Cepat ke Beranda -->
            <div style="text-align: center; margin-top: 25px;">
                <a href="index.php" style="color: var(--primary); text-decoration: none; font-weight: 700; font-size: 0.95rem;">
                    <i class="fas fa-arrow-left"></i> Lihat Jadwal & Ketersediaan Ruangan di Halaman Utama
                </a>
            </div>

        </div>
    </main>

    <!-- ===================================================================
         MODAL PENDAFTARAN SEMINAR & SIDANG DENGAN PILIHAN DOSEN DATABASE
         =================================================================== -->
    <div class="custom-modal" id="modalPengajuan" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px; overflow-y: auto;">
        <div style="background: white; border-radius: var(--radius-md); max-width: 580px; width: 100%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); overflow: hidden; border: 1px solid var(--primary-border);">
            <div style="background: linear-gradient(135deg, var(--primary), #fb923c); color: white; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.15rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-file-signature"></i> <span id="judul_modal_pengajuan">Formulir Pendaftaran</span>
                </h3>
                <button type="button" onclick="tutupModalPengajuan();" style="background: transparent; border: none; color: white; font-size: 1.4rem; cursor: pointer;">&times;</button>
            </div>

            <form action="dashboard_mahasiswa.php" method="POST" style="padding: 24px;">
                <input type="hidden" name="action" value="ajukan_seminar_sidang">
                <input type="hidden" name="jenis_kegiatan" id="input_jenis_kegiatan" value="Seminar Proposal">

                <div style="background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 18px; font-size: 0.88rem;">
                    <div style="color: var(--secondary); font-weight: 700;"><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($nama); ?> (NIM: <?= htmlspecialchars($nim); ?>)</div>
                    <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 2px;">Prodi: <?= htmlspecialchars($prodi); ?></div>
                </div>

                <div class="form-field" style="margin-bottom: 16px;">
                    <label for="judul_penelitian" style="font-weight: 700; font-size: 0.88rem; color: var(--secondary); margin-bottom: 6px; display: block;">
                        <i class="fas fa-book"></i> Judul Penelitian / Skripsi:
                    </label>
                    <textarea id="judul_penelitian" name="judul" rows="3" placeholder="Masukkan judul penelitian skripsi Anda..." required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem; outline: none; font-family: inherit;"></textarea>
                </div>

                <!-- PILIHAN DOSEN PEMBIMBING BERDASARKAN DATABASE -->
                <div class="form-field" style="margin-bottom: 16px;">
                    <label for="pilih_dosen_pembimbing" style="font-weight: 700; font-size: 0.88rem; color: var(--secondary); margin-bottom: 6px; display: block;">
                        <i class="fas fa-user-tie" style="color: var(--primary);"></i> Dosen Pembimbing (Data Database):
                    </label>
                    <select id="pilih_dosen_pembimbing" name="dosen_pembimbing" required style="width: 100%; padding: 11px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem; background: white; outline: none; cursor: pointer;">
                        <option value="">-- Pilih Dosen Pembimbing dari Database --</option>
                        <?php foreach ($daftar_dosen as $d): ?>
                            <option value="<?= $d['id']; ?>">
                                <?= htmlspecialchars($d['nama_dosen']); ?>, <?= htmlspecialchars($d['gelar_akademik']); ?> (NIDN: <?= htmlspecialchars($d['nidn']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #64748b; font-size: 0.8rem;">*Nama dosen dipilih langsung dari database MySQL yang dikelola oleh Admin.</small>
                </div>

                <!-- PILIHAN DOSEN PENGUJI BERDASARKAN DATABASE -->
                <div class="form-field" style="margin-bottom: 20px;">
                    <label for="pilih_dosen_penguji" style="font-weight: 700; font-size: 0.88rem; color: var(--secondary); margin-bottom: 6px; display: block;">
                        <i class="fas fa-user-check" style="color: var(--primary);"></i> Dosen Penguji Utama (Data Database):
                    </label>
                    <select id="pilih_dosen_penguji" name="dosen_penguji" required style="width: 100%; padding: 11px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem; background: white; outline: none; cursor: pointer;">
                        <option value="">-- Pilih Dosen Penguji dari Database --</option>
                        <?php foreach ($daftar_dosen as $d): ?>
                            <option value="<?= $d['id']; ?>">
                                <?= htmlspecialchars($d['nama_dosen']); ?>, <?= htmlspecialchars($d['gelar_akademik']); ?> (NIDN: <?= htmlspecialchars($d['nidn']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #64748b; font-size: 0.8rem;">*Dosen penguji diambil secara otomatis dari daftar dosen aktif.</small>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <button type="button" class="btn btn-outline-admin" onclick="tutupModalPengajuan();">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Pengajuan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Interaktif -->
    <script>
        function bukaModalPengajuan(jenis) {
            document.getElementById('input_jenis_kegiatan').value = jenis;
            document.getElementById('judul_modal_pengajuan').innerText = 'Pendaftaran ' + jenis;
            document.getElementById('modalPengajuan').style.display = 'flex';
        }

        function tutupModalPengajuan() {
            document.getElementById('modalPengajuan').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalPengajuan');
            if (event.target === modal) {
                tutupModalPengajuan();
            }
        };
    </script>

    <!-- Footer -->
    <footer style="text-align: center; padding: 20px; font-size: 0.85rem; color: #64748b; background: white; border-top: 1px solid var(--border-color);">
        &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Jurusan Teknologi Informasi dan Komputer (TIK), Politeknik Negeri Lhokseumawe.
    </footer>

    <!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
    <script src="notifikasi.js"></script>
</body>
</html>
