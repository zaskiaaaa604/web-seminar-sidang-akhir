<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: admin_kuota_dosen.php (Monitoring & Manajemen Kuota Bimbingan Dosen)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Validasi Keamanan: Hanya admin yang berhak
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$nama     = $_SESSION['nama'];

$error_message = '';
$success_message = '';

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'sukses_batal') {
        $success_message = "Mahasiswa bimbingan berhasil dibatalkan secara resmi. Kuota dosen pembimbing terkait otomatis bertambah 1 slot!";
    } elseif ($_GET['pesan'] === 'sukses_pindah') {
        $success_message = "Mahasiswa bimbingan berhasil dipindahkan ke dosen pembimbing baru. Kuota kedua dosen otomatis disesuaikan!";
    } elseif ($_GET['pesan'] === 'gagal_kuota_penuh') {
        $error_message = "Gagal memindahkan bimbingan: Dosen tujuan yang dipilih kuotanya sudah penuh (" . KUOTA_MAKSIMAL_DOSEN . "/" . KUOTA_MAKSIMAL_DOSEN . " mahasiswa)!";
    } elseif ($_GET['pesan'] === 'gagal') {
        $error_message = "Terjadi kesalahan sistem saat memproses bimbingan dosen.";
    }
}

// 1. PEMBATALAN BIMBINGAN OLEH ADMIN (UPDATE KUOTA DOSEN SECARA OTOMATIS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batalkan_bimbingan') {
    $pengajuan_id = (int)($_POST['pengajuan_id'] ?? 0);
    $alasan_batal = trim($_POST['alasan_batal'] ?? 'Bimbingan dibatalkan oleh Administrator Program Studi.');

    if ($pengajuan_id > 0) {
        $cek_p = mysqli_query($conn, "SELECT pj.*, d.nama_dosen, m.nama AS nama_mhs FROM pengajuan_judul pj JOIN dosen d ON pj.dosen_id = d.id JOIN mahasiswa m ON pj.mahasiswa_id = m.id WHERE pj.id = $pengajuan_id LIMIT 1");
        if ($p_data = mysqli_fetch_assoc($cek_p)) {
            $up_batal = mysqli_prepare($conn, "UPDATE pengajuan_judul SET status = 'Ditolak', alasan_penolakan = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_batal, "si", $alasan_batal, $pengajuan_id);
            if (mysqli_stmt_execute($up_batal)) {
                mysqli_stmt_close($up_batal);

                // Rekam log ke tanggapan_pengajuan
                $pesan_log = "Bimbingan resmi dibatalkan oleh Administrator. Kuota dosen dipulihkan. Alasan: " . $alasan_batal;
                $log_stmt = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'dosen', ?, ?, 'Ditolak')");
                mysqli_stmt_bind_param($log_stmt, "iiss", $pengajuan_id, $admin_id, $pesan_log, $status_perubahan);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);

                header("Location: admin_kuota_dosen.php?pesan=sukses_batal");
                exit;
            }
        }
    }
    header("Location: admin_kuota_dosen.php?pesan=gagal");
    exit;
}

// 2. PEMINDAHAN BIMBINGAN KE DOSEN LAIN (UPDATE KUOTA KEDUA DOSEN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pindahkan_bimbingan') {
    $pengajuan_id    = (int)($_POST['pengajuan_id'] ?? 0);
    $dosen_tujuan_id = (int)($_POST['dosen_tujuan_id'] ?? 0);
    $catatan_pindah  = trim($_POST['catatan_pindah'] ?? 'Bimbingan dipindahkan oleh Administrator Program Studi.');

    if ($pengajuan_id > 0 && $dosen_tujuan_id > 0) {
        $kuota_tujuan = hitungBimbinganDosen($conn, $dosen_tujuan_id);
        if ($kuota_tujuan >= KUOTA_MAKSIMAL_DOSEN) {
            header("Location: admin_kuota_dosen.php?pesan=gagal_kuota_penuh");
            exit;
        }

        $cek_p = mysqli_query($conn, "SELECT pj.dosen_id, d.nama_dosen AS dosen_lama FROM pengajuan_judul pj JOIN dosen d ON pj.dosen_id = d.id WHERE pj.id = $pengajuan_id LIMIT 1");
        $cek_d_baru = mysqli_query($conn, "SELECT nama_dosen, gelar_akademik FROM dosen WHERE id = $dosen_tujuan_id LIMIT 1");

        if (($row_p = mysqli_fetch_assoc($cek_p)) && ($row_baru = mysqli_fetch_assoc($cek_d_baru))) {
            $dosen_lama_nama = $row_p['dosen_lama'];
            $dosen_baru_nama = $row_baru['nama_dosen'] . ', ' . $row_baru['gelar_akademik'];

            $up_pindah = mysqli_prepare($conn, "UPDATE pengajuan_judul SET dosen_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_pindah, "ii", $dosen_tujuan_id, $pengajuan_id);
            if (mysqli_stmt_execute($up_pindah)) {
                mysqli_stmt_close($up_pindah);

                $pesan_log = "Administrator memindahkan bimbingan mahasiswa dari $dosen_lama_nama ke $dosen_baru_nama. Catatan: " . $catatan_pindah;
                $log_stmt = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'dosen', ?, ?, 'Diterima')");
                mysqli_stmt_bind_param($log_stmt, "iiss", $pengajuan_id, $admin_id, $pesan_log, $status_perubahan);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);

                header("Location: admin_kuota_dosen.php?pesan=sukses_pindah");
                exit;
            }
        }
    }
    header("Location: admin_kuota_dosen.php?pesan=gagal");
    exit;
}

// Filter & Pencarian
$search_keyword = trim($_GET['search'] ?? '');
$filter_kuota   = trim($_GET['kuota'] ?? '');

$sql = "
    SELECT d.*, 
           COALESCE(b.total_bimbingan, 0) AS total_bimbingan,
           (" . KUOTA_MAKSIMAL_DOSEN . " - COALESCE(b.total_bimbingan, 0)) AS sisa_kuota
    FROM dosen d
    LEFT JOIN (
        SELECT dosen_id, COUNT(DISTINCT mahasiswa_id) AS total_bimbingan
        FROM pengajuan_judul
        WHERE status = 'Diterima'
        GROUP BY dosen_id
    ) b ON d.id = b.dosen_id
    WHERE 1=1
";
$params = [];
$types  = "";

if (!empty($search_keyword)) {
    $sql .= " AND (d.nidn LIKE ? OR d.nama_dosen LIKE ? OR d.gelar_akademik LIKE ?)";
    $like = "%$search_keyword%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

if ($filter_kuota === 'tersedia') {
    $sql .= " AND COALESCE(b.total_bimbingan, 0) < " . KUOTA_MAKSIMAL_DOSEN;
} elseif ($filter_kuota === 'penuh') {
    $sql .= " AND COALESCE(b.total_bimbingan, 0) >= " . KUOTA_MAKSIMAL_DOSEN;
}

$sql .= " ORDER BY d.nama_dosen ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result_kuota = mysqli_stmt_get_result($stmt);
} else {
    $result_kuota = mysqli_query($conn, $sql);
}

// Rekap Seluruh Mahasiswa yang Bimbingannya Diterima (untuk modal per dosen)
$q_all_bimbingan = mysqli_query($conn, "
    SELECT pj.id AS pengajuan_id, pj.dosen_id, pj.mahasiswa_id, pj.judul, pj.updated_at,
           m.nama AS nama_mahasiswa, m.nim, m.email, m.no_hp
    FROM pengajuan_judul pj
    JOIN mahasiswa m ON pj.mahasiswa_id = m.id
    WHERE pj.status = 'Diterima'
    ORDER BY pj.updated_at DESC
");
$bimbingan_by_dosen = [];
if ($q_all_bimbingan) {
    while ($bm = mysqli_fetch_assoc($q_all_bimbingan)) {
        $bimbingan_by_dosen[$bm['dosen_id']][] = $bm;
    }
}

// Ambil dosen yang masih memiliki kuota untuk target pemindahan
$q_dosen_tersedia = mysqli_query($conn, "
    SELECT d.id, d.nidn, d.nama_dosen, d.gelar_akademik,
           (" . KUOTA_MAKSIMAL_DOSEN . " - COALESCE(b.total_bimbingan, 0)) AS sisa_kuota
    FROM dosen d
    LEFT JOIN (
        SELECT dosen_id, COUNT(DISTINCT mahasiswa_id) AS total_bimbingan
        FROM pengajuan_judul
        WHERE status = 'Diterima'
        GROUP BY dosen_id
    ) b ON d.id = b.dosen_id
    WHERE d.status = 'Aktif' AND (" . KUOTA_MAKSIMAL_DOSEN . " - COALESCE(b.total_bimbingan, 0)) > 0
    ORDER BY d.nama_dosen ASC
");
$list_dosen_tersedia = [];
if ($q_dosen_tersedia) {
    while ($dt = mysqli_fetch_assoc($q_dosen_tersedia)) {
        $list_dosen_tersedia[] = $dt;
    }
}

// Statistik Global Kuota
$stat_total_dosen = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM dosen"));
$stat_kuota_penuh = mysqli_num_rows(mysqli_query($conn, "
    SELECT d.id FROM dosen d 
    JOIN (
        SELECT dosen_id, COUNT(DISTINCT mahasiswa_id) AS total 
        FROM pengajuan_judul 
        WHERE status = 'Diterima' 
        GROUP BY dosen_id 
        HAVING total >= " . KUOTA_MAKSIMAL_DOSEN . "
    ) b ON d.id = b.dosen_id
"));
$stat_kuota_tersedia = $stat_total_dosen - $stat_kuota_penuh;

$q_total_bimbingan_mhs = mysqli_query($conn, "SELECT COUNT(DISTINCT mahasiswa_id) AS total FROM pengajuan_judul WHERE status = 'Diterima'");
$stat_total_mhs_bimbingan = ($q_total_bimbingan_mhs) ? mysqli_fetch_assoc($q_total_bimbingan_mhs)['total'] : 0;

$active_menu = 'kuota';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuota Bimbingan Dosen - Panel Admin SIM-SEMSID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>

<div class="admin-app">
    <!-- Sidebar Navy -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Topbar -->
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button class="admin-sidebar-toggle" id="sidebarToggle" title="Buka/Tutup Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="admin-breadcrumb">
                    <a href="dashboard_admin.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
                    <span>/</span>
                    <span class="current">Kuota Bimbingan Dosen</span>
                </div>
            </div>
            <div class="admin-topbar-right">
                <!-- Komponen Lonceng Notifikasi Admin -->
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <div class="topbar-date-pill">
                    <i class="fas fa-calendar-day"></i> <?= date('d M Y'); ?>
                </div>
                <div class="admin-badge-role">
                    <i class="fas fa-shield-halved"></i> Panel Administrator
                </div>
            </div>
        </header>

        <!-- Content Body -->
        <main class="admin-content-body">
            <!-- Welcome Header Banner -->
            <div class="welcome-admin-banner">
                <div class="welcome-admin-info">
                    <h1><i class="fas fa-chart-pie" style="color: #60a5fa; margin-right: 8px;"></i> Kuota Bimbingan Dosen (Maksimal <?= KUOTA_MAKSIMAL_DOSEN; ?> Mahasiswa)</h1>
                    <p>
                        Pantau kapasitas bimbingan setiap dosen Program Studi Teknologi Rekayasa Perangkat Lunak. 
                        Dosen yang telah mencapai <strong>8/8 mahasiswa</strong> otomatis disembunyikan dari pilihan mahasiswa baru. 
                        Admin dapat membatalkan atau memindahkan bimbingan mahasiswa untuk memperbarui kuota secara otomatis.
                    </p>
                </div>
                <div>
                    <a href="admin_dosen.php" class="btn-admin btn-admin-primary">
                        <i class="fas fa-user-tie"></i> Kelola Data Dosen
                    </a>
                </div>
            </div>

            <!-- Feedback Alerts -->
            <?php if (!empty($success_message)): ?>
                <div class="admin-alert admin-alert-success">
                    <i class="fas fa-circle-check"></i>
                    <div><?= htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="admin-alert admin-alert-danger">
                    <i class="fas fa-triangle-exclamation"></i>
                    <div><?= htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Metric Cards -->
            <div class="admin-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Total Dosen Pengajar</div>
                        <div class="stat-card-icon icon-blue"><i class="fas fa-chalkboard-user"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $stat_total_dosen; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-university"></i> Dosen Prodi TIK</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Kuota Masih Tersedia</div>
                        <div class="stat-card-icon icon-green"><i class="fas fa-user-plus"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $stat_kuota_tersedia; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-check"></i> &lt; <?= KUOTA_MAKSIMAL_DOSEN; ?> bimbingan aktif</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Kuota Penuh (8/8)</div>
                        <div class="stat-card-icon icon-orange"><i class="fas fa-lock"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $stat_kuota_penuh; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-shield"></i> Tidak tampil di form mhs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Total Mahasiswa Dibimbing</div>
                        <div class="stat-card-icon icon-purple"><i class="fas fa-users-viewfinder"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $stat_total_mhs_bimbingan; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-graduation-cap"></i> Judul resmi diterima</div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="admin-table-card">
                <div class="admin-table-header">
                    <div>
                        <h2 class="admin-table-title"><i class="fas fa-chart-column" style="color: var(--primary);"></i> Daftar Kuota Bimbingan Seluruh Dosen</h2>
                        <p style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">Data kuota dihitung realtime dari judul berstatus 'Diterima' di database</p>
                    </div>

                    <!-- Toolbar Filter -->
                    <form method="GET" action="admin_kuota_dosen.php" class="admin-toolbar-filter">
                        <div class="admin-search-input">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" name="search" placeholder="Cari NIDN atau nama dosen..." value="<?= htmlspecialchars($search_keyword); ?>">
                        </div>
                        <select name="kuota" class="admin-filter-select" onchange="this.form.submit()">
                            <option value="">Semua Kuota</option>
                            <option value="tersedia" <?= ($filter_kuota === 'tersedia') ? 'selected' : ''; ?>>Kuota Tersedia (&lt; 8)</option>
                            <option value="penuh" <?= ($filter_kuota === 'penuh') ? 'selected' : ''; ?>>Kuota Penuh (8/8)</option>
                        </select>
                        <button type="submit" class="btn-admin btn-admin-secondary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($search_keyword) || !empty($filter_kuota)): ?>
                            <a href="admin_kuota_dosen.php" class="btn-admin btn-admin-secondary" title="Reset Filter"><i class="fas fa-rotate-left"></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Dosen Pembimbing</th>
                                <th>NIDN</th>
                                <th>Status Dosen</th>
                                <th style="width: 140px;">Kuota Bimbingan</th>
                                <th style="width: 180px;">Visualisasi Kapasitas</th>
                                <th>Sisa Slot</th>
                                <th style="text-align: center; width: 150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result_kuota) > 0): ?>
                                <?php $no = 1; while ($d = mysqli_fetch_assoc($result_kuota)): 
                                    $d_id = $d['id'];
                                    $terpakai = (int)$d['total_bimbingan'];
                                    $maks = KUOTA_MAKSIMAL_DOSEN;
                                    $persen = round(($terpakai / $maks) * 100);
                                    $is_penuh = ($terpakai >= $maks);
                                    $sisa = max(0, $maks - $terpakai);
                                    $mhs_list = $bimbingan_by_dosen[$d_id] ?? [];
                                ?>
                                    <tr>
                                        <td><strong><?= $no++; ?></strong></td>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">
                                                <?= htmlspecialchars($d['nama_dosen'] . ', ' . $d['gelar_akademik']); ?>
                                            </div>
                                            <div style="font-size: 0.78rem; color: #64748b;">
                                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($d['email']); ?>
                                            </div>
                                        </td>
                                        <td><span class="badge-role badge-role-admin" style="font-family: monospace; font-size: 0.82rem;"><?= htmlspecialchars($d['nidn']); ?></span></td>
                                        <td>
                                            <?php if ($d['status'] === 'Aktif'): ?>
                                                <span class="badge-status badge-selesai"><i class="fas fa-circle-check"></i> Aktif</span>
                                            <?php else: ?>
                                                <span class="badge-status badge-menunggu"><i class="fas fa-circle-pause"></i> <?= htmlspecialchars($d['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-weight: 700; font-size: 1.05rem; color: <?= $is_penuh ? '#dc2626' : '#1e293b'; ?>;">
                                                <?= $terpakai; ?> / <?= $maks; ?>
                                            </span>
                                            <span style="font-size: 0.75rem; color: #64748b; display: block;">Mahasiswa</span>
                                        </td>
                                        <td>
                                            <div style="background: #e2e8f0; border-radius: 999px; height: 10px; width: 100%; overflow: hidden; margin-bottom: 4px;">
                                                <div style="height: 100%; width: <?= min(100, $persen); ?>%; background: <?= $is_penuh ? '#ef4444' : ($persen >= 75 ? '#f97316' : '#10b981'); ?>; transition: width 0.3s ease;"></div>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-align: right;"><?= $persen; ?>% Terisi</div>
                                        </td>
                                        <td>
                                            <?php if ($is_penuh): ?>
                                                <span class="badge-status badge-ditolak"><i class="fas fa-lock"></i> KUOTA PENUH</span>
                                            <?php else: ?>
                                                <span class="badge-status badge-terjadwal" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;">
                                                    <i class="fas fa-circle-check"></i> Tersedia (<?= $sisa; ?> slot)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <button class="btn-admin btn-admin-secondary" style="font-size: 0.8rem; padding: 6px 10px; white-space: nowrap;" onclick='bukaModalBimbingan(<?= json_encode($d); ?>, <?= json_encode($mhs_list); ?>)'>
                                                <i class="fas fa-users-viewfinder"></i> Detail (<?= count($mhs_list); ?>)
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-chart-pie" style="font-size: 3rem; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                        Tidak ada data dosen yang sesuai dengan kriteria filter kuota.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- MODAL DETAIL MAHASISWA BIMBINGAN DOSEN -->
<div class="admin-modal" id="modalBimbingan">
    <div class="admin-modal-card" style="max-width: 750px;">
        <div class="admin-modal-header">
            <h3><i class="fas fa-users" style="color: var(--primary);"></i> Daftar Mahasiswa Bimbingan Aktif</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalBimbingan')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 1.05rem;" id="md_nama_dosen">-</div>
                    <div style="font-size: 0.8rem; color: #64748b;" id="md_nidn_dosen">-</div>
                </div>
                <div style="text-align: right;">
                    <span id="md_badge_kuota" class="badge-status badge-selesai" style="font-size: 0.85rem;">0 / <?= KUOTA_MAKSIMAL_DOSEN; ?> Mahasiswa</span>
                </div>
            </div>

            <div id="md_list_mahasiswa_box">
                <!-- Diisi via JavaScript -->
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalBimbingan')">Tutup</button>
        </div>
    </div>
</div>

<!-- MODAL PINDAHKAN BIMBINGAN KE DOSEN LAIN -->
<div class="admin-modal" id="modalPindah">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><i class="fas fa-shuffle" style="color: #2563eb;"></i> Pindahkan Mahasiswa Bimbingan</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalPindah')">&times;</button>
        </div>
        <form method="POST" action="admin_kuota_dosen.php">
            <input type="hidden" name="action" value="pindahkan_bimbingan">
            <input type="hidden" name="pengajuan_id" id="pindah_pengajuan_id">
            <div class="admin-modal-body">
                <div class="form-admin-group">
                    <label class="form-admin-label">Mahasiswa & Judul Penelitian</label>
                    <div id="pindah_info_mhs" style="font-size: 0.9rem; font-weight: 600; color: #1e293b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px;">-</div>
                </div>

                <div class="form-admin-group">
                    <label class="form-admin-label">Pilih Dosen Pembimbing Baru (Kuota Tersedia) *</label>
                    <select name="dosen_tujuan_id" id="pindah_dosen_tujuan" class="form-admin-control" required>
                        <option value="">-- Pilih Dosen Pengganti --</option>
                        <?php foreach ($list_dosen_tersedia as $dt): ?>
                            <option value="<?= $dt['id']; ?>">
                                <?= htmlspecialchars($dt['nama_dosen'] . ', ' . $dt['gelar_akademik']); ?> (Sisa Kuota: <?= $dt['sisa_kuota']; ?>/<?= KUOTA_MAKSIMAL_DOSEN; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #64748b; font-size: 0.75rem;">Hanya dosen aktif dengan sisa kuota yang ditampilkan.</small>
                </div>

                <div class="form-admin-group">
                    <label class="form-admin-label">Catatan Pemindahan Bimbingan</label>
                    <textarea name="catatan_pindah" class="form-admin-control" rows="2" placeholder="Alasan pemindahan bimbingan oleh administrator..."></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalPindah')">Batal</button>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="fas fa-paper-plane"></i> Proses Pemindahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL BATALKAN BIMBINGAN MAHASISWA -->
<div class="admin-modal" id="modalBatal">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><i class="fas fa-user-xmark" style="color: #dc2626;"></i> Batalkan Bimbingan Resmi</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalBatal')">&times;</button>
        </div>
        <form method="POST" action="admin_kuota_dosen.php">
            <input type="hidden" name="action" value="batalkan_bimbingan">
            <input type="hidden" name="pengajuan_id" id="batal_pengajuan_id">
            <div class="admin-modal-body">
                <div class="admin-alert admin-alert-danger" style="margin-bottom: 16px;">
                    <i class="fas fa-triangle-exclamation"></i>
                    <div>
                        Tindakan ini akan membatalkan status 'Diterima' pada pengajuan judul mahasiswa. Kuota dosen pembimbing terkait otomatis dipulihkan (+1 slot).
                    </div>
                </div>

                <div class="form-admin-group">
                    <label class="form-admin-label">Mahasiswa</label>
                    <div id="batal_info_mhs" style="font-size: 0.9rem; font-weight: 600; color: #1e293b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px;">-</div>
                </div>

                <div class="form-admin-group">
                    <label class="form-admin-label">Alasan Pembatalan Resmi *</label>
                    <textarea name="alasan_batal" class="form-admin-control" rows="3" placeholder="Sebutkan alasan resmi pembatalan bimbingan..." required></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalBatal')">Tutup</button>
                <button type="submit" class="btn-admin btn-admin-primary" style="background: #dc2626; border-color: #dc2626;"><i class="fas fa-ban"></i> Konfirmasi Pembatalan</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Responsive Sidebar Toggle
    const sidebar = document.getElementById('adminSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }

    function bukaModalBimbingan(dosen, mhsList) {
        document.getElementById('md_nama_dosen').innerText = dosen.nama_dosen + ', ' + dosen.gelar_akademik;
        document.getElementById('md_nidn_dosen').innerText = 'NIDN: ' + dosen.nidn + ' • ' + dosen.program_studi;
        
        const count = mhsList ? mhsList.length : 0;
        const badge = document.getElementById('md_badge_kuota');
        badge.innerText = count + ' / <?= KUOTA_MAKSIMAL_DOSEN; ?> Mahasiswa';
        badge.className = (count >= <?= KUOTA_MAKSIMAL_DOSEN; ?>) ? 'badge-status badge-ditolak' : 'badge-status badge-selesai';

        const box = document.getElementById('md_list_mahasiswa_box');
        if (mhsList && mhsList.length > 0) {
            let html = '<div style="display: flex; flex-direction: column; gap: 10px;">';
            mhsList.forEach((item, idx) => {
                html += `
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div style="max-width: 480px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">${idx + 1}. ${item.nama_mahasiswa}</span>
                                <span class="badge-role badge-role-admin" style="font-family: monospace; font-size: 0.78rem;">${item.nim}</span>
                            </div>
                            <div style="font-size: 0.85rem; color: #334155; margin-top: 4px; font-weight: 600;">
                                <i class="fas fa-book-bookmark" style="color: #ea580c; font-size: 0.8rem; margin-right: 4px;"></i> ${item.judul}
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                Diterima: ${item.updated_at} • Email: ${item.email || '-'}
                            </div>
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" class="btn-admin btn-admin-secondary" style="font-size: 0.75rem; padding: 5px 9px;" onclick='bukaModalPindah(${JSON.stringify(item)})' title="Pindahkan Mahasiswa ke Dosen Lain">
                                <i class="fas fa-shuffle"></i> Pindahkan
                            </button>
                            <button type="button" class="btn-admin" style="font-size: 0.75rem; padding: 5px 9px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;" onclick='bukaModalBatal(${JSON.stringify(item)})' title="Batalkan Bimbingan Resmi">
                                <i class="fas fa-user-xmark"></i> Batalkan
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            box.innerHTML = html;
        } else {
            box.innerHTML = `
                <div style="text-align: center; padding: 30px; color: #94a3b8;">
                    <i class="fas fa-user-slash" style="font-size: 2.5rem; margin-bottom: 8px; opacity: 0.4; display: block;"></i>
                    Dosen ini belum memiliki mahasiswa bimbingan yang disetujui (0/<?= KUOTA_MAKSIMAL_DOSEN; ?>).
                </div>
            `;
        }

        document.getElementById('modalBimbingan').classList.add('active');
    }

    function bukaModalPindah(item) {
        document.getElementById('pindah_pengajuan_id').value = item.pengajuan_id;
        document.getElementById('pindah_info_mhs').innerText = item.nama_mahasiswa + ' (' + item.nim + ') - ' + item.judul;
        document.getElementById('modalPindah').classList.add('active');
    }

    function bukaModalBatal(item) {
        document.getElementById('batal_pengajuan_id').value = item.pengajuan_id;
        document.getElementById('batal_info_mhs').innerText = item.nama_mahasiswa + ' (' + item.nim + ') - ' + item.judul;
        document.getElementById('modalBatal').classList.add('active');
    }

    function tutupModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('admin-modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

<!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
<script src="notifikasi.js"></script>
</body>
</html>
