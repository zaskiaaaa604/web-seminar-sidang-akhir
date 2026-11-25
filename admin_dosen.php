<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * PROGRAM STUDI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: admin_dosen.php (Manajemen Data Dosen Pembimbing & Penguji)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Validasi Keamanan Hak Akses: Hanya admin yang berhak mengelola data dosen
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
$nama_admin = $_SESSION['nama'];

$error_message = '';
$success_message = '';

// Menangkap notifikasi feedback
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah_sukses') {
        $success_message = "Data dosen baru berhasil ditambahkan ke database!";
    } elseif ($_GET['pesan'] === 'edit_sukses') {
        $success_message = "Data dosen berhasil diperbarui!";
    } elseif ($_GET['pesan'] === 'hapus_sukses') {
        $success_message = "Data dosen berhasil dihapus dari sistem.";
    } elseif ($_GET['pesan'] === 'nidn_terdaftar') {
        $error_message = "Gagal menyimpan: NIDN tersebut sudah digunakan oleh dosen lain!";
    } elseif ($_GET['pesan'] === 'gagal') {
        $error_message = "Terjadi kesalahan sistem saat memproses data dosen.";
    } elseif ($_GET['pesan'] === 'sukses_batal_bimbingan') {
        $success_message = "Mahasiswa bimbingan berhasil dibatalkan dari dosen. Kuota dosen terkait otomatis diperbarui dan bertambah 1 slot!";
    } elseif ($_GET['pesan'] === 'sukses_pindah_bimbingan') {
        $success_message = "Mahasiswa bimbingan berhasil dipindahkan ke dosen pembimbing baru. Kuota kedua dosen otomatis diperbarui!";
    } elseif ($_GET['pesan'] === 'gagal_kuota_penuh') {
        $error_message = "Gagal memindahkan bimbingan: Dosen tujuan yang dipilih kuotanya sudah penuh (" . KUOTA_MAKSIMAL_DOSEN . "/" . KUOTA_MAKSIMAL_DOSEN . " mahasiswa)!";
    }
}

// ===================================================================
// 1. PROSES TAMBAH DOSEN BARU (CREATE)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $nidn           = trim($_POST['nidn'] ?? '');
    $nama_dosen     = trim($_POST['nama_dosen'] ?? '');
    $gelar_akademik = trim($_POST['gelar_akademik'] ?? '');
    $program_studi  = trim($_POST['program_studi'] ?? 'Teknologi Rekayasa Perangkat Lunak');
    $email          = trim($_POST['email'] ?? '');
    $no_hp          = trim($_POST['no_hp'] ?? '');
    $status         = trim($_POST['status'] ?? 'Aktif');

    if (empty($nidn) || empty($nama_dosen) || empty($gelar_akademik) || empty($email) || empty($no_hp)) {
        $error_message = "Seluruh kolom data dosen wajib diisi!";
    } else {
        // Cek keunikan NIDN
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM dosen WHERE nidn = ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, "s", $nidn);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            mysqli_stmt_close($check_stmt);
            header("Location: admin_dosen.php?pesan=nidn_terdaftar");
            exit;
        }
        mysqli_stmt_close($check_stmt);

        // Simpan data dosen baru ke MySQL (dengan default password hash dosen123)
        $default_dosen_pass = password_hash('dosen123', PASSWORD_DEFAULT);
        $insert_query = "INSERT INTO dosen (nidn, nama_dosen, gelar_akademik, password, program_studi, email, no_hp, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt  = mysqli_prepare($conn, $insert_query);

        if ($insert_stmt) {
            mysqli_stmt_bind_param($insert_stmt, "ssssssss", $nidn, $nama_dosen, $gelar_akademik, $default_dosen_pass, $program_studi, $email, $no_hp, $status);
            if (mysqli_stmt_execute($insert_stmt)) {
                mysqli_stmt_close($insert_stmt);
                header("Location: admin_dosen.php?pesan=tambah_sukses");
                exit;
            } else {
                $error_message = "Gagal menambahkan dosen ke database: " . mysqli_error($conn);
            }
            mysqli_stmt_close($insert_stmt);
        }
    }
}

// ===================================================================
// 2. PROSES EDIT DATA DOSEN (UPDATE)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id             = (int)($_POST['id'] ?? 0);
    $nidn           = trim($_POST['nidn'] ?? '');
    $nama_dosen     = trim($_POST['nama_dosen'] ?? '');
    $gelar_akademik = trim($_POST['gelar_akademik'] ?? '');
    $program_studi  = trim($_POST['program_studi'] ?? 'Teknologi Rekayasa Perangkat Lunak');
    $email          = trim($_POST['email'] ?? '');
    $no_hp          = trim($_POST['no_hp'] ?? '');
    $status         = trim($_POST['status'] ?? 'Aktif');

    if ($id <= 0 || empty($nidn) || empty($nama_dosen) || empty($gelar_akademik) || empty($email) || empty($no_hp)) {
        $error_message = "Seluruh data dosen wajib diisi dengan benar!";
    } else {
        // Cek apakah NIDN dipakai oleh dosen lain
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM dosen WHERE nidn = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, "si", $nidn, $id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            mysqli_stmt_close($check_stmt);
            header("Location: admin_dosen.php?pesan=nidn_terdaftar");
            exit;
        }
        mysqli_stmt_close($check_stmt);

        // Update data dosen di MySQL
        $update_query = "UPDATE dosen SET nidn = ?, nama_dosen = ?, gelar_akademik = ?, program_studi = ?, email = ?, no_hp = ?, status = ? WHERE id = ?";
        $update_stmt  = mysqli_prepare($conn, $update_query);

        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "sssssssi", $nidn, $nama_dosen, $gelar_akademik, $program_studi, $email, $no_hp, $status, $id);
            if (mysqli_stmt_execute($update_stmt)) {
                mysqli_stmt_close($update_stmt);
                header("Location: admin_dosen.php?pesan=edit_sukses");
                exit;
            } else {
                $error_message = "Gagal memperbarui data dosen: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}

// ===================================================================
// 3. PROSES HAPUS DATA DOSEN (DELETE)
// ===================================================================
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id > 0) {
        $delete_stmt = mysqli_prepare($conn, "DELETE FROM dosen WHERE id = ?");
        if ($delete_stmt) {
            mysqli_stmt_bind_param($delete_stmt, "i", $id);
            if (mysqli_stmt_execute($delete_stmt)) {
                mysqli_stmt_close($delete_stmt);
                header("Location: admin_dosen.php?pesan=hapus_sukses");
                exit;
            } else {
                header("Location: admin_dosen.php?pesan=gagal");
                exit;
            }
        }
    }
}

// ===================================================================
// 4. PROSES PEMBATALAN & PEMINDAHAN MAHASISWA BIMBINGAN
// ===================================================================
// Pembatalan Bimbingan Mahasiswa secara Resmi oleh Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batalkan_bimbingan') {
    $pengajuan_id = (int)($_POST['pengajuan_id'] ?? 0);
    $alasan_batal = trim($_POST['alasan_batal'] ?? 'Bimbingan dibatalkan resmi oleh Administrator Program Studi.');

    if ($pengajuan_id > 0) {
        $cek_p = mysqli_query($conn, "SELECT pj.*, d.nama_dosen, m.nama AS nama_mhs FROM pengajuan_judul pj JOIN dosen d ON pj.dosen_id = d.id JOIN mahasiswa m ON pj.mahasiswa_id = m.id WHERE pj.id = $pengajuan_id LIMIT 1");
        if ($p_data = mysqli_fetch_assoc($cek_p)) {
            $up_batal = mysqli_prepare($conn, "UPDATE pengajuan_judul SET status = 'Ditolak', alasan_penolakan = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_batal, "si", $alasan_batal, $pengajuan_id);
            if (mysqli_stmt_execute($up_batal)) {
                mysqli_stmt_close($up_batal);

                $pesan_log = "Mahasiswa bimbingan resmi dibatalkan oleh Administrator. Alasan: " . $alasan_batal;
                $log_stmt = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'dosen', ?, ?, 'Ditolak')");
                mysqli_stmt_bind_param($log_stmt, "iiss", $pengajuan_id, $admin_id, $pesan_log);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);

                header("Location: admin_dosen.php?pesan=sukses_batal_bimbingan");
                exit;
            }
        }
    }
}

// Pemindahan Mahasiswa Bimbingan ke Dosen Lain oleh Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pindahkan_bimbingan') {
    $pengajuan_id    = (int)($_POST['pengajuan_id'] ?? 0);
    $dosen_tujuan_id = (int)($_POST['dosen_tujuan_id'] ?? 0);
    $catatan_pindah  = trim($_POST['catatan_pindah'] ?? 'Bimbingan dipindahkan oleh Administrator Program Studi.');

    if ($pengajuan_id > 0 && $dosen_tujuan_id > 0) {
        // Cek kuota dosen tujuan
        $kuota_tujuan = hitungBimbinganDosen($conn, $dosen_tujuan_id);
        if ($kuota_tujuan >= KUOTA_MAKSIMAL_DOSEN) {
            header("Location: admin_dosen.php?pesan=gagal_kuota_penuh");
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
                mysqli_stmt_bind_param($log_stmt, "iiss", $pengajuan_id, $admin_id, $pesan_log);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);

                header("Location: admin_dosen.php?pesan=sukses_pindah_bimbingan");
                exit;
            }
        }
    }
}

// ===================================================================
// 5. MENGAMBIL DATA DOSEN & REKAP KUOTA DARI DATABASE MYSQL (READ)
// ===================================================================
$search_keyword = trim($_GET['search'] ?? '');
$filter_status  = trim($_GET['status_filter'] ?? '');
$filter_kuota   = trim($_GET['kuota_filter'] ?? '');

$sql_query = "
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
    $sql_query .= " AND (d.nidn LIKE ? OR d.nama_dosen LIKE ? OR d.gelar_akademik LIKE ? OR d.email LIKE ?)";
    $like = "%" . $search_keyword . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if (!empty($filter_status) && in_array($filter_status, ['Aktif', 'Cuti', 'Tidak Aktif'])) {
    $sql_query .= " AND d.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_kuota === 'tersedia') {
    $sql_query .= " AND COALESCE(b.total_bimbingan, 0) < " . KUOTA_MAKSIMAL_DOSEN;
} elseif ($filter_kuota === 'penuh') {
    $sql_query .= " AND COALESCE(b.total_bimbingan, 0) >= " . KUOTA_MAKSIMAL_DOSEN;
}

$sql_query .= " ORDER BY d.nama_dosen ASC";

if (!empty($params)) {
    $stmt_list = mysqli_prepare($conn, $sql_query);
    mysqli_stmt_bind_param($stmt_list, $types, ...$params);
    mysqli_stmt_execute($stmt_list);
    $result_dosen = mysqli_stmt_get_result($stmt_list);
} else {
    $result_dosen = mysqli_query($conn, $sql_query);
}

// Seluruh data mahasiswa bimbingan yang diterima
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

// Daftar dosen aktif dengan sisa kuota (opsi pindah)
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

// Statistik Data Dosen
$count_total = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM dosen"));
$count_aktif = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM dosen WHERE status = 'Aktif'"));
$count_cuti  = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM dosen WHERE status != 'Aktif'"));
$count_kuota_penuh = mysqli_num_rows(mysqli_query($conn, "
    SELECT d.id 
    FROM dosen d 
    JOIN (
        SELECT dosen_id, COUNT(DISTINCT mahasiswa_id) AS total 
        FROM pengajuan_judul 
        WHERE status = 'Diterima' 
        GROUP BY dosen_id 
        HAVING total >= " . KUOTA_MAKSIMAL_DOSEN . "
    ) b ON d.id = b.dosen_id
"));

$active_menu = 'dosen';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Data Dosen - Panel Admin SIM-SEMSID</title>
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
                    <span class="current">Data Dosen</span>
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
            <!-- Page Header Banner -->
            <div class="welcome-admin-banner">
                <div class="welcome-admin-info">
                    <h1><i class="fas fa-user-tie" style="color: #60a5fa; margin-right: 8px;"></i> Manajemen Data Dosen Pengajar TIK</h1>
                    <p>Kelola data NIDN, gelar, kontak, dan status dosen aktif. Dosen otomatis menjadi pilihan pembimbing & penguji seminar/sidang dengan kuota maksimal <?= KUOTA_MAKSIMAL_DOSEN; ?> mahasiswa.</p>
                </div>
                <div>
                    <button class="btn-admin btn-admin-primary" onclick="bukaModalTambah()">
                        <i class="fas fa-user-plus"></i> Tambah Dosen Baru
                    </button>
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
                        <div class="stat-card-title">Total Dosen Terdaftar</div>
                        <div class="stat-card-icon icon-blue"><i class="fas fa-chalkboard-teacher"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $count_total; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-id-badge"></i> Dosen Prodi TIK</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Dosen Aktif (Siap Uji)</div>
                        <div class="stat-card-icon icon-green"><i class="fas fa-user-check"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $count_aktif; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-check"></i> Siap membimbing & menguji</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Dosen Cuti / Non-Aktif</div>
                        <div class="stat-card-icon icon-orange"><i class="fas fa-user-clock"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $count_cuti; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-pause"></i> Tidak tampil pada opsi form</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Kuota Bimbingan Penuh</div>
                        <div class="stat-card-icon icon-purple"><i class="fas fa-user-lock"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $count_kuota_penuh; ?> Dosen</div>
                    <div class="stat-card-desc"><i class="fas fa-shield"></i> Kuota (8/8) penuh</div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="admin-table-card">
                <div class="admin-table-header">
                    <div>
                        <h2 class="admin-table-title"><i class="fas fa-table-list" style="color: var(--primary);"></i> Daftar Dosen Program Studi TIK</h2>
                        <p style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">Kelola identitas akademik, kontak, status keaktifan, dan rekap mahasiswa bimbingan</p>
                    </div>

                    <!-- Toolbar Filter -->
                    <form method="GET" action="admin_dosen.php" class="admin-toolbar-filter">
                        <div class="admin-search-input">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" name="search" placeholder="Cari NIDN, nama, email..." value="<?= htmlspecialchars($search_keyword); ?>">
                        </div>
                        <select name="status_filter" class="admin-filter-select" onchange="this.form.submit()">
                            <option value="">Semua Status Dosen</option>
                            <option value="Aktif" <?= ($filter_status === 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="Cuti" <?= ($filter_status === 'Cuti') ? 'selected' : ''; ?>>Cuti</option>
                            <option value="Tidak Aktif" <?= ($filter_status === 'Tidak Aktif') ? 'selected' : ''; ?>>Tidak Aktif</option>
                        </select>
                        <select name="kuota_filter" class="admin-filter-select" onchange="this.form.submit()">
                            <option value="">Semua Kuota</option>
                            <option value="tersedia" <?= ($filter_kuota === 'tersedia') ? 'selected' : ''; ?>>Kuota Tersedia (&lt; 8)</option>
                            <option value="penuh" <?= ($filter_kuota === 'penuh') ? 'selected' : ''; ?>>Kuota Penuh (8/8)</option>
                        </select>
                        <button type="submit" class="btn-admin btn-admin-secondary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($search_keyword) || !empty($filter_status) || !empty($filter_kuota)): ?>
                            <a href="admin_dosen.php" class="btn-admin btn-admin-secondary" title="Reset Filter"><i class="fas fa-rotate-left"></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>NIDN</th>
                                <th>Nama Dosen & Gelar</th>
                                <th>Program Studi</th>
                                <th>Kontak Dosen</th>
                                <th>Status</th>
                                <th>Bimbingan (Kuota)</th>
                                <th style="text-align: center; width: 150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result_dosen) > 0): ?>
                                <?php $no = 1; while ($row = mysqli_fetch_assoc($result_dosen)): 
                                    $dosen_id = $row['id'];
                                    $terpakai = (int)$row['total_bimbingan'];
                                    $maks = KUOTA_MAKSIMAL_DOSEN;
                                    $is_penuh = ($terpakai >= $maks);
                                    $mhs_bimbingan = $bimbingan_by_dosen[$dosen_id] ?? [];
                                ?>
                                    <tr>
                                        <td><strong><?= $no++; ?></strong></td>
                                        <td><span class="badge-role badge-role-admin" style="font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars($row['nidn']); ?></span></td>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">
                                                <?= htmlspecialchars($row['nama_dosen'] . ', ' . $row['gelar_akademik']); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b;">
                                                Login Password: <code>dosen123</code>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['program_studi']); ?></td>
                                        <td>
                                            <div style="font-size: 0.8rem; color: #334155;"><i class="fas fa-envelope" style="color: #3b82f6; width: 14px;"></i> <?= htmlspecialchars($row['email']); ?></div>
                                            <div style="font-size: 0.8rem; color: #334155;"><i class="fas fa-phone" style="color: #10b981; width: 14px;"></i> <?= htmlspecialchars($row['no_hp']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] === 'Aktif'): ?>
                                                <span class="badge-status badge-selesai"><i class="fas fa-circle-check"></i> Aktif</span>
                                            <?php elseif ($row['status'] === 'Cuti'): ?>
                                                <span class="badge-status badge-menunggu"><i class="fas fa-circle-pause"></i> Cuti</span>
                                            <?php else: ?>
                                                <span class="badge-status badge-ditolak"><i class="fas fa-circle-xmark"></i> Tidak Aktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span style="font-weight: 700; color: <?= $is_penuh ? '#dc2626' : '#1e293b'; ?>;">
                                                    <?= $terpakai; ?> / <?= $maks; ?>
                                                </span>
                                                <?php if ($is_penuh): ?>
                                                    <span class="badge-status badge-ditolak" style="font-size: 0.7rem; padding: 2px 6px;">Penuh</span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn-action btn-action-detail" style="margin-top: 4px; font-size: 0.72rem; padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;" onclick='bukaModalBimbingan(<?= json_encode($row); ?>, <?= json_encode($mhs_bimbingan); ?>)'>
                                                <i class="fas fa-users"></i> Lihat (<?= count($mhs_bimbingan); ?>)
                                            </button>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center;">
                                                <button class="btn-action btn-action-edit" title="Edit Data Dosen" onclick='bukaModalEdit(<?= json_encode($row); ?>)'>
                                                    <i class="fas fa-pen-to-square"></i>
                                                </button>
                                                <a href="admin_dosen.php?action=hapus&id=<?= $row['id']; ?>" class="btn-action btn-action-delete" title="Hapus Data Dosen" onclick="return confirm('Apakah Anda yakin ingin menghapus data dosen <?= htmlspecialchars(addslashes($row['nama_dosen'])); ?>?');">
                                                    <i class="fas fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-chalkboard-user" style="font-size: 3rem; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                        Belum ada data dosen yang sesuai dengan pencarian Anda.
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

<!-- MODAL TAMBAH DOSEN -->
<div class="admin-modal" id="modalTambah">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><i class="fas fa-user-plus" style="color: var(--primary);"></i> Tambah Dosen Baru</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalTambah')">&times;</button>
        </div>
        <form method="POST" action="admin_dosen.php">
            <input type="hidden" name="action" value="tambah">
            <div class="admin-modal-body">
                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">NIDN (Nomor Induk Dosen Nasional) *</label>
                        <input type="text" name="nidn" class="form-admin-control" placeholder="Contoh: 0012057501" required>
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">Gelar Akademik *</label>
                        <input type="text" name="gelar_akademik" class="form-admin-control" placeholder="Contoh: M.Kom. / Ph.D." required>
                    </div>
                </div>

                <div class="form-admin-group">
                    <label class="form-admin-label">Nama Dosen Lengkap *</label>
                    <input type="text" name="nama_dosen" class="form-admin-control" placeholder="Contoh: Dr. Ir. Hendra Setiawan" required>
                </div>

                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">Email Dosen *</label>
                        <input type="email" name="email" class="form-admin-control" placeholder="hendra@pnl.ac.id" required>
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">Nomor WhatsApp / HP *</label>
                        <input type="text" name="no_hp" class="form-admin-control" placeholder="0811xxxxxxx" required>
                    </div>
                </div>

                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">Program Studi</label>
                        <input type="text" name="program_studi" class="form-admin-control" value="Teknologi Rekayasa Perangkat Lunak" readonly style="background: #f1f5f9;">
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">Status Keaktifan *</label>
                        <select name="status" class="form-admin-control" required>
                            <option value="Aktif">Aktif</option>
                            <option value="Cuti">Cuti</option>
                            <option value="Tidak Aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalTambah')">Batal</button>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="fas fa-save"></i> Simpan Dosen</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT DOSEN -->
<div class="admin-modal" id="modalEdit">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><i class="fas fa-pen-to-square" style="color: #2563eb;"></i> Edit Data Dosen</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalEdit')">&times;</button>
        </div>
        <form method="POST" action="admin_dosen.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="admin-modal-body">
                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">NIDN *</label>
                        <input type="text" name="nidn" id="edit_nidn" class="form-admin-control" required>
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">Gelar Akademik *</label>
                        <input type="text" name="gelar_akademik" id="edit_gelar_akademik" class="form-admin-control" required>
                    </div>
                </div>

                <div class="form-admin-group">
                    <label class="form-admin-label">Nama Dosen Lengkap *</label>
                    <input type="text" name="nama_dosen" id="edit_nama_dosen" class="form-admin-control" required>
                </div>

                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">Email Dosen *</label>
                        <input type="email" name="email" id="edit_email" class="form-admin-control" required>
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">Nomor WhatsApp / HP *</label>
                        <input type="text" name="no_hp" id="edit_no_hp" class="form-admin-control" required>
                    </div>
                </div>

                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">Program Studi</label>
                        <input type="text" name="program_studi" id="edit_program_studi" class="form-admin-control" readonly style="background: #f1f5f9;">
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">Status Keaktifan *</label>
                        <select name="status" id="edit_status" class="form-admin-control" required>
                            <option value="Aktif">Aktif</option>
                            <option value="Cuti">Cuti</option>
                            <option value="Tidak Aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalEdit')">Batal</button>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="fas fa-save"></i> Perbarui Dosen</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL LIHAT MAHASISWA BIMBINGAN DOSEN -->
<div class="admin-modal" id="modalBimbingan">
    <div class="admin-modal-card" style="max-width: 720px;">
        <div class="admin-modal-header">
            <h3><i class="fas fa-users-viewfinder" style="color: var(--primary);"></i> Rekap Mahasiswa Bimbingan Dosen</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalBimbingan')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <div style="font-weight: 700; color: #0f172a; font-size: 1.05rem;" id="bm_dosen_nama">-</div>
                    <div style="font-size: 0.8rem; color: #64748b;" id="bm_dosen_nidn">-</div>
                </div>
                <div>
                    <a href="admin_kuota_dosen.php" class="btn-admin btn-admin-secondary" style="font-size: 0.8rem;">
                        <i class="fas fa-chart-pie"></i> Buka Manajemen Kuota Penuh
                    </a>
                </div>
            </div>

            <div id="bm_container">
                <!-- Diisi via JS -->
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalBimbingan')">Tutup</button>
        </div>
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

    function bukaModalTambah() {
        document.getElementById('modalTambah').classList.add('active');
    }

    function bukaModalEdit(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nidn').value = data.nidn;
        document.getElementById('edit_nama_dosen').value = data.nama_dosen;
        document.getElementById('edit_gelar_akademik').value = data.gelar_akademik;
        document.getElementById('edit_program_studi').value = data.program_studi || 'Teknologi Rekayasa Perangkat Lunak';
        document.getElementById('edit_email').value = data.email;
        document.getElementById('edit_no_hp').value = data.no_hp;
        document.getElementById('edit_status').value = data.status;
        document.getElementById('modalEdit').classList.add('active');
    }

    function bukaModalBimbingan(dosen, mhsList) {
        document.getElementById('bm_dosen_nama').innerText = dosen.nama_dosen + ', ' + dosen.gelar_akademik;
        document.getElementById('bm_dosen_nidn').innerText = 'NIDN: ' + dosen.nidn + ' • Kuota: ' + (mhsList ? mhsList.length : 0) + '/<?= KUOTA_MAKSIMAL_DOSEN; ?> Mahasiswa';

        const container = document.getElementById('bm_container');
        if (mhsList && mhsList.length > 0) {
            let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
            mhsList.forEach((m, idx) => {
                html += `
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <div style="font-weight: 700; color: #0f172a;">${idx + 1}. ${m.nama_mahasiswa} <span class="badge-role badge-role-admin" style="font-size: 0.75rem;">${m.nim}</span></div>
                            <div style="font-size: 0.85rem; color: #334155; margin-top: 2px;"><i class="fas fa-book" style="color: #ea580c;"></i> ${m.judul}</div>
                        </div>
                        <div>
                            <span class="badge-status badge-selesai" style="font-size: 0.75rem;">Diterima</span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div style="text-align: center; padding: 25px; color: #94a3b8;">
                    <i class="fas fa-user-slash" style="font-size: 2.5rem; opacity: 0.4; display: block; margin-bottom: 8px;"></i>
                    Belum ada mahasiswa bimbingan yang diterima oleh dosen ini.
                </div>
            `;
        }

        document.getElementById('modalBimbingan').classList.add('active');
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
