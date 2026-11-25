<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: jadwal_kalender.php (Jadwal & Kalender Akademik Terpadu - Publik)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

$is_logged_in = isset($_SESSION['login']) && $_SESSION['login'] === true;
$user_role    = $_SESSION['role'] ?? '';
$user_name    = $_SESSION['nama'] ?? '';

// Tab aktif: 'seminar', 'sidang', atau 'kalender'
$tab = trim($_GET['tab'] ?? 'seminar');
if (!in_array($tab, ['seminar', 'sidang', 'kalender'])) {
    $tab = 'seminar';
}

$search = trim($_GET['search'] ?? '');
$tanggal_filter = trim($_GET['tanggal'] ?? '');

// 1. DATA JADWAL SEMINAR PROPOSAL
$sql_seminar = "SELECT p.*, m.nim, m.nama AS nama_mhs, m.program_studi,
                       dp.nama_dosen AS nama_pembimbing, dp.gelar_akademik AS gelar_pembimbing,
                       du.nama_dosen AS nama_penguji, du.gelar_akademik AS gelar_penguji,
                       r.kode_ruangan, r.nama_ruangan, r.gedung
                FROM pendaftaran_seminar_sidang p
                JOIN mahasiswa m ON p.mahasiswa_id = m.id
                LEFT JOIN dosen dp ON p.dosen_pembimbing_id = dp.id
                LEFT JOIN dosen du ON p.dosen_penguji_id = du.id
                LEFT JOIN ruangan r ON p.ruangan_id = r.id
                WHERE (p.kategori = 'seminar' OR p.jenis = 'Seminar Proposal')
                  AND p.status IN ('Terjadwal', 'Sedang Berlangsung', 'Selesai')";
$params_sem = [];
$types_sem  = "";

if (!empty($search)) {
    $sql_seminar .= " AND (m.nama LIKE ? OR m.nim LIKE ? OR p.judul LIKE ? OR r.nama_ruangan LIKE ?)";
    $like = "%$search%";
    $params_sem = array_merge($params_sem, [$like, $like, $like, $like]);
    $types_sem .= "ssss";
}
if (!empty($tanggal_filter)) {
    $sql_seminar .= " AND p.tanggal = ?";
    $params_sem[] = $tanggal_filter;
    $types_sem .= "s";
}
$sql_seminar .= " ORDER BY p.tanggal ASC, p.jam_mulai ASC";

$stmt_sem = mysqli_prepare($conn, $sql_seminar);
if (!empty($params_sem)) mysqli_stmt_bind_param($stmt_sem, $types_sem, ...$params_sem);
mysqli_stmt_execute($stmt_sem);
$res_seminar = mysqli_stmt_get_result($stmt_sem);

// 2. DATA JADWAL SIDANG AKHIR
$sql_sidang = "SELECT p.*, m.nim, m.nama AS nama_mhs, m.program_studi,
                      dp.nama_dosen AS nama_pembimbing, dp.gelar_akademik AS gelar_pembimbing,
                      du.nama_dosen AS nama_penguji, du.gelar_akademik AS gelar_penguji,
                      r.kode_ruangan, r.nama_ruangan, r.gedung
               FROM pendaftaran_seminar_sidang p
               JOIN mahasiswa m ON p.mahasiswa_id = m.id
               LEFT JOIN dosen dp ON p.dosen_pembimbing_id = dp.id
               LEFT JOIN dosen du ON p.dosen_penguji_id = du.id
               LEFT JOIN ruangan r ON p.ruangan_id = r.id
               WHERE (p.kategori = 'sidang' OR p.jenis = 'Sidang Akhir Skripsi')
                 AND p.status IN ('Terjadwal', 'Sedang Berlangsung', 'Selesai')";
$params_sid = [];
$types_sid  = "";

if (!empty($search)) {
    $sql_sidang .= " AND (m.nama LIKE ? OR m.nim LIKE ? OR p.judul LIKE ? OR r.nama_ruangan LIKE ?)";
    $like = "%$search%";
    $params_sid = array_merge($params_sid, [$like, $like, $like, $like]);
    $types_sid .= "ssss";
}
if (!empty($tanggal_filter)) {
    $sql_sidang .= " AND p.tanggal = ?";
    $params_sid[] = $tanggal_filter;
    $types_sid .= "s";
}
$sql_sidang .= " ORDER BY p.tanggal ASC, p.jam_mulai ASC";

$stmt_sid = mysqli_prepare($conn, $sql_sidang);
if (!empty($params_sid)) mysqli_stmt_bind_param($stmt_sid, $types_sid, ...$params_sid);
mysqli_stmt_execute($stmt_sid);
$res_sidang = mysqli_stmt_get_result($stmt_sid);

// 3. DATA KALENDER AKADEMIK
$sql_kalender = "SELECT * FROM kalender_akademik WHERE status_publikasi = 'Dipublikasikan'";
$params_kal = [];
$types_kal  = "";

if (!empty($search)) {
    $sql_kalender .= " AND (nama_kegiatan LIKE ? OR deskripsi LIKE ? OR kategori LIKE ?)";
    $like = "%$search%";
    $params_kal = array_merge($params_kal, [$like, $like, $like]);
    $types_kal .= "sss";
}
if (!empty($tanggal_filter)) {
    $sql_kalender .= " AND (tanggal_mulai <= ? AND tanggal_selesai >= ?)";
    $params_kal[] = $tanggal_filter;
    $params_kal[] = $tanggal_filter;
    $types_kal .= "ss";
}
$sql_kalender .= " ORDER BY tanggal_mulai ASC";

$stmt_kal = mysqli_prepare($conn, $sql_kalender);
if (!empty($params_kal)) mysqli_stmt_bind_param($stmt_kal, $types_kal, ...$params_kal);
mysqli_stmt_execute($stmt_kal);
$res_kalender = mysqli_stmt_get_result($stmt_kal);

// Format Tanggal Indo
function tglIndoShort($tgl) {
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
    <title>Jadwal & Kalender Akademik - SIM-SEMSID PNL</title>
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
        
        .tab-nav-wrapper {
            background: white;
            border-bottom: 2px solid #e2e8f0;
            position: sticky;
            top: 70px;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        .tab-nav-container {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 10px 0 0;
        }
        .tab-btn-item {
            padding: 14px 22px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .tab-btn-item:hover {
            color: #ea580c;
        }
        .tab-btn-item.active {
            color: #ea580c;
            border-bottom-color: #ea580c;
            background: rgba(234, 88, 12, 0.05);
            border-radius: 8px 8px 0 0;
        }

        .filter-search-box {
            background: white;
            padding: 18px 22px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin: 25px 0 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-search-box input, .filter-search-box select {
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        .jadwal-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .jadwal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            border-color: #fdba74;
        }

        .kalender-item-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            margin-bottom: 16px;
            border-left: 5px solid #ea580c;
            transition: transform 0.2s ease;
        }
        .kalender-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="container topbar-content">
            <div class="topbar-info">
                <span><i class="fas fa-university"></i> Politeknik Negeri Lhokseumawe</span>
                <span><i class="fas fa-code-branch"></i> Prodi TIK</span>
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
                <li><a href="jadwal_kalender.php" class="nav-link active">Jadwal & Kalender</a></li>
                <li><a href="informasi_pendaftaran.php" class="nav-link">Informasi Pendaftaran</a></li>
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

    <!-- HEADER BANNER -->
    <div class="page-header-banner">
        <div class="container">
            <h1>Jadwal & Kalender Akademik</h1>
            <p>Pusat informasi resmi pelaksanaan Seminar Proposal, Sidang Akhir Skripsi, serta agenda kalender akademik mahasiswa Program Studi Teknologi Rekayasa Perangkat Lunak.</p>
        </div>
    </div>

    <!-- TABS NAVIGASI 3 KATEGORI -->
    <div class="tab-nav-wrapper">
        <div class="container tab-nav-container">
            <a href="jadwal_kalender.php?tab=seminar" class="tab-btn-item <?= ($tab === 'seminar') ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-user"></i> Jadwal Seminar Proposal
            </a>
            <a href="jadwal_kalender.php?tab=sidang" class="tab-btn-item <?= ($tab === 'sidang') ? 'active' : ''; ?>">
                <i class="fas fa-award"></i> Jadwal Sidang Akhir
            </a>
            <a href="jadwal_kalender.php?tab=kalender" class="tab-btn-item <?= ($tab === 'kalender') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-days"></i> Kalender Akademik
            </a>
        </div>
    </div>

    <main class="container" style="padding: 20px 0 50px;">
        
        <!-- FORM PENCARIAN & FILTER -->
        <form method="GET" action="jadwal_kalender.php" class="filter-search-box">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab); ?>">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" name="search" placeholder="Cari nama mahasiswa, NIM, judul, atau ruangan..." value="<?= htmlspecialchars($search); ?>" style="width: 100%;">
            </div>
            <div>
                <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal_filter); ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="padding: 10px 18px;">
                    <i class="fas fa-search"></i> Cari Data
                </button>
                <?php if (!empty($search) || !empty($tanggal_filter)): ?>
                    <a href="jadwal_kalender.php?tab=<?= urlencode($tab); ?>" class="btn btn-outline-admin" style="padding: 10px 14px;">
                        <i class="fas fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- KONTEN TAB 1: JADWAL SEMINAR PROPOSAL -->
        <?php if ($tab === 'seminar'): ?>
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #0f172a;"><i class="fas fa-chalkboard-user" style="color: #ea580c;"></i> Daftar Jadwal Seminar Proposal Resmi</h3>
                <span style="font-size: 0.88rem; color: #64748b;">Ditemukan: <strong><?= mysqli_num_rows($res_seminar); ?></strong> Jadwal</span>
            </div>

            <?php if (mysqli_num_rows($res_seminar) === 0): ?>
                <div style="background: white; border-radius: 12px; padding: 50px 20px; text-align: center; border: 1px solid #e2e8f0;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                    <h4 style="color: #475569; margin: 0 0 6px;">Belum Ada Jadwal Seminar Proposal</h4>
                    <p style="color: #94a3b8; font-size: 0.9rem; margin: 0;">Tidak ditemukan jadwal seminar proposal yang sesuai dengan kata kunci pencarian Anda.</p>
                </div>
            <?php else: ?>
                <?php while ($r = mysqli_fetch_assoc($res_seminar)): ?>
                    <div class="jadwal-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 14px;">
                            <div>
                                <span style="background: #eff6ff; color: #1e3a8a; font-weight: 700; font-size: 0.75rem; padding: 4px 10px; border-radius: 50px; border: 1px solid #bfdbfe;">
                                    SEMINAR PROPOSAL
                                </span>
                                <h4 style="margin: 8px 0 4px; font-size: 1.15rem; color: #0f172a;"><?= htmlspecialchars($r['judul']); ?></h4>
                                <div style="font-size: 0.9rem; color: #475569; font-weight: 600;">
                                    <i class="fas fa-user-graduate" style="color: #ea580c; margin-right: 6px;"></i>
                                    <?= htmlspecialchars($r['nama_mhs']); ?> (NIM: <?= htmlspecialchars($r['nim']); ?>)
                                </div>
                            </div>
                            <div>
                                <span style="background: #dcfce7; color: #166534; font-size: 0.82rem; font-weight: 700; padding: 6px 14px; border-radius: 50px;">
                                    <i class="fas fa-circle-check"></i> <?= htmlspecialchars($r['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; padding-top: 14px; border-top: 1px solid #f1f5f9; font-size: 0.88rem;">
                            <div>
                                <span style="color: #64748b; font-size: 0.78rem; display: block; font-weight: 700;">WAKTU PELAKSANAAN:</span>
                                <strong><i class="fas fa-calendar-day" style="color: #ea580c;"></i> <?= tglIndoShort($r['tanggal']); ?></strong>
                                <div style="color: #334155;"><i class="fas fa-clock" style="color: #ea580c;"></i> <?= substr($r['jam_mulai'], 0, 5); ?> - <?= substr($r['jam_selesai'], 0, 5); ?> WIB</div>
                            </div>
                            <div>
                                <span style="color: #64748b; font-size: 0.78rem; display: block; font-weight: 700;">RUANGAN & GEDUNG:</span>
                                <strong><i class="fas fa-door-open" style="color: #1e3a8a;"></i> <?= htmlspecialchars($r['nama_ruangan'] ?? 'Ditentukan Kemudian'); ?></strong>
                                <div style="color: #64748b;"><?= htmlspecialchars($r['gedung'] ?? '-'); ?></div>
                            </div>
                            <div>
                                <span style="color: #64748b; font-size: 0.78rem; display: block; font-weight: 700;">PEMBIMBING & PENGUJI:</span>
                                <div><i class="fas fa-user-tie" style="color: #0284c7;"></i> <?= htmlspecialchars($r['nama_pembimbing'] ?? '-'); ?></div>
                                <div style="color: #64748b;"><i class="fas fa-user-check" style="color: #0284c7;"></i> Penguji: <?= htmlspecialchars($r['nama_penguji'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

        <!-- KONTEN TAB 2: JADWAL SIDANG AKHIR -->
        <?php elseif ($tab === 'sidang'): ?>
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #0f172a;"><i class="fas fa-award" style="color: #ea580c;"></i> Daftar Jadwal Sidang Akhir Skripsi Resmi</h3>
                <span style="font-size: 0.88rem; color: #64748b;">Ditemukan: <strong><?= mysqli_num_rows($res_sidang); ?></strong> Jadwal</span>
            </div>

            <?php if (mysqli_num_rows($res_sidang) === 0): ?>
                <div style="background: white; border-radius: 12px; padding: 50px 20px; text-align: center; border: 1px solid #e2e8f0;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                    <h4 style="color: #475569; margin: 0 0 6px;">Belum Ada Jadwal Sidang Akhir</h4>
                    <p style="color: #94a3b8; font-size: 0.9rem; margin: 0;">Tidak ditemukan jadwal sidang akhir yang sesuai dengan kriteria pencarian Anda.</p>
                </div>
            <?php else: ?>
                <?php while ($r = mysqli_fetch_assoc($res_sidang)): ?>
                    <div class="jadwal-card" style="border-left: 4px solid #1e3a8a;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 14px;">
                            <div>
                                <span style="background: #fdf2f8; color: #9d174d; font-weight: 700; font-size: 0.75rem; padding: 4px 10px; border-radius: 50px; border: 1px solid #fbcfe8;">
                                    SIDANG AKHIR SKRIPSI
                                </span>
                                <h4 style="margin: 8px 0 4px; font-size: 1.15rem; color: #0f172a;"><?= htmlspecialchars($r['judul']); ?></h4>
                                <div style="font-size: 0.9rem; color: #475569; font-weight: 600;">
                                    <i class="fas fa-user-graduate" style="color: #ea580c; margin-right: 6px;"></i>
                                    <?= htmlspecialchars($r['nama_mhs']); ?> (NIM: <?= htmlspecialchars($r['nim']); ?>)
                                </div>
                            </div>
                            <div>
                                <span style="background: #dcfce7; color: #166534; font-size: 0.82rem; font-weight: 700; padding: 6px 14px; border-radius: 50px;">
                                    <i class="fas fa-circle-check"></i> <?= htmlspecialchars($r['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; padding-top: 14px; border-top: 1px solid #f1f5f9; font-size: 0.88rem;">
                            <div>
                                <span style="color: #64748b; font-size: 0.78rem; display: block; font-weight: 700;">WAKTU PELAKSANAAN:</span>
                                <strong><i class="fas fa-calendar-day" style="color: #ea580c;"></i> <?= tglIndoShort($r['tanggal']); ?></strong>
                                <div style="color: #334155;"><i class="fas fa-clock" style="color: #ea580c;"></i> <?= substr($r['jam_mulai'], 0, 5); ?> - <?= substr($r['jam_selesai'], 0, 5); ?> WIB</div>
                            </div>
                            <div>
                                <span style="color: #64748b; font-size: 0.78rem; display: block; font-weight: 700;">RUANGAN & GEDUNG:</span>
                                <strong><i class="fas fa-door-open" style="color: #1e3a8a;"></i> <?= htmlspecialchars($r['nama_ruangan'] ?? 'Ditentukan Kemudian'); ?></strong>
                                <div style="color: #64748b;"><?= htmlspecialchars($r['gedung'] ?? '-'); ?></div>
                            </div>
                            <div>
                                <span style="color: #64748b; font-size: 0.78rem; display: block; font-weight: 700;">DEWAN PENGUJI:</span>
                                <div><i class="fas fa-user-tie" style="color: #0284c7;"></i> Pembimbing: <?= htmlspecialchars($r['nama_pembimbing'] ?? '-'); ?></div>
                                <div style="color: #64748b;"><i class="fas fa-user-check" style="color: #0284c7;"></i> Penguji Utama: <?= htmlspecialchars($r['nama_penguji'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

        <!-- KONTEN TAB 3: KALENDER AKADEMIK -->
        <?php elseif ($tab === 'kalender'): ?>
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #0f172a;"><i class="fas fa-calendar-days" style="color: #ea580c;"></i> Agenda Kalender Akademik Resmi PNL</h3>
                <span style="font-size: 0.88rem; color: #64748b;">Ditemukan: <strong><?= mysqli_num_rows($res_kalender); ?></strong> Agenda</span>
            </div>

            <?php if (mysqli_num_rows($res_kalender) === 0): ?>
                <div style="background: white; border-radius: 12px; padding: 50px 20px; text-align: center; border: 1px solid #e2e8f0;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                    <h4 style="color: #475569; margin: 0 0 6px;">Belum Ada Agenda Kalender Akademik</h4>
                    <p style="color: #94a3b8; font-size: 0.9rem; margin: 0;">Tidak ditemukan agenda kegiatan akademik yang sesuai.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; gap: 14px;">
                    <?php while ($k = mysqli_fetch_assoc($res_kalender)): ?>
                        <div class="kalender-item-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <span style="background: #f1f5f9; color: #334155; font-size: 0.75rem; font-weight: 700; padding: 3px 10px; border-radius: 4px; text-transform: uppercase;">
                                        <?= htmlspecialchars($k['kategori']); ?>
                                    </span>
                                    <h4 style="margin: 8px 0 4px; font-size: 1.1rem; color: #0f172a;"><?= htmlspecialchars($k['nama_kegiatan']); ?></h4>
                                    <?php if (!empty($k['deskripsi'])): ?>
                                        <p style="margin: 4px 0 0; font-size: 0.88rem; color: #475569;"><?= nl2br(htmlspecialchars($k['deskripsi'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 800; color: #ea580c; font-size: 0.95rem;">
                                        <i class="fas fa-calendar-day"></i> <?= tglIndoShort($k['tanggal_mulai']); ?>
                                        <?php if ($k['tanggal_mulai'] !== $k['tanggal_selesai']): ?>
                                            - <?= tglIndoShort($k['tanggal_selesai']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px;">
                                        Semester <?= htmlspecialchars($k['semester']); ?> &bull; <?= htmlspecialchars($k['tahun_akademik']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

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
