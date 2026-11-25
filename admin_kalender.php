<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: admin_kalender.php (Pengelolaan Kalender Akademik oleh Administrator)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';
require_once 'kalender_helper.php';

// Validasi Keamanan: Hanya admin yang berhak
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php?pesan=auth_required");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$nama     = $_SESSION['nama'];

$error_message = '';
$success_message = '';

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah_sukses') $success_message = "Kegiatan akademik baru berhasil ditambahkan dan disimpan!";
    elseif ($_GET['pesan'] === 'edit_sukses') $success_message = "Kegiatan akademik berhasil diperbarui!";
    elseif ($_GET['pesan'] === 'hapus_sukses') $success_message = "Kegiatan akademik berhasil dihapus dari kalender!";
    elseif ($_GET['pesan'] === 'status_sukses') $success_message = "Status publikasi kegiatan berhasil diperbarui!";
    elseif ($_GET['pesan'] === 'gagal') $error_message = "Terjadi kesalahan saat memproses data kegiatan.";
}

// ===================================================================
// 1. TAMBAH KEGIATAN AKADEMIK BARU
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $nama_kegiatan    = trim($_POST['nama_kegiatan'] ?? '');
    $kategori         = trim($_POST['kategori'] ?? '');
    $tanggal_mulai    = trim($_POST['tanggal_mulai'] ?? '');
    $tanggal_selesai  = trim($_POST['tanggal_selesai'] ?? '');
    $waktu_kegiatan   = trim($_POST['waktu_kegiatan'] ?? 'Sepanjang Hari');
    $deskripsi        = trim($_POST['deskripsi'] ?? '');
    $semester         = trim($_POST['semester'] ?? 'Ganjil');
    $tahun_akademik   = trim($_POST['tahun_akademik'] ?? '2026/2027');
    $status_publikasi = trim($_POST['status_publikasi'] ?? 'Dipublikasikan');

    // Jika tanggal selesai kosong, samakan dengan tanggal mulai
    if (empty($tanggal_selesai)) {
        $tanggal_selesai = $tanggal_mulai;
    }

    if (empty($nama_kegiatan) || empty($kategori) || empty($tanggal_mulai)) {
        $error_message = "Nama kegiatan, kategori, dan tanggal mulai wajib diisi!";
    } elseif ($tanggal_selesai < $tanggal_mulai) {
        $error_message = "Tanggal selesai tidak boleh lebih awal dari tanggal mulai!";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO kalender_akademik (nama_kegiatan, kategori, tanggal_mulai, tanggal_selesai, waktu_kegiatan, deskripsi, semester, tahun_akademik, status_publikasi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssssssss", $nama_kegiatan, $kategori, $tanggal_mulai, $tanggal_selesai, $waktu_kegiatan, $deskripsi, $semester, $tahun_akademik, $status_publikasi);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_kalender.php?pesan=tambah_sukses");
            exit;
        } else {
            $error_message = "Gagal menyimpan ke database: " . mysqli_error($conn);
        }
    }
}

// ===================================================================
// 2. EDIT KEGIATAN AKADEMIK
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id               = (int)($_POST['id'] ?? 0);
    $nama_kegiatan    = trim($_POST['nama_kegiatan'] ?? '');
    $kategori         = trim($_POST['kategori'] ?? '');
    $tanggal_mulai    = trim($_POST['tanggal_mulai'] ?? '');
    $tanggal_selesai  = trim($_POST['tanggal_selesai'] ?? '');
    $waktu_kegiatan   = trim($_POST['waktu_kegiatan'] ?? 'Sepanjang Hari');
    $deskripsi        = trim($_POST['deskripsi'] ?? '');
    $semester         = trim($_POST['semester'] ?? 'Ganjil');
    $tahun_akademik   = trim($_POST['tahun_akademik'] ?? '2026/2027');
    $status_publikasi = trim($_POST['status_publikasi'] ?? 'Dipublikasikan');

    if (empty($tanggal_selesai)) {
        $tanggal_selesai = $tanggal_mulai;
    }

    if ($id <= 0 || empty($nama_kegiatan) || empty($kategori) || empty($tanggal_mulai)) {
        $error_message = "Data tidak valid atau kolom wajib belum diisi!";
    } elseif ($tanggal_selesai < $tanggal_mulai) {
        $error_message = "Tanggal selesai tidak boleh lebih awal dari tanggal mulai!";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE kalender_akademik SET nama_kegiatan = ?, kategori = ?, tanggal_mulai = ?, tanggal_selesai = ?, waktu_kegiatan = ?, deskripsi = ?, semester = ?, tahun_akademik = ?, status_publikasi = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssssssssi", $nama_kegiatan, $kategori, $tanggal_mulai, $tanggal_selesai, $waktu_kegiatan, $deskripsi, $semester, $tahun_akademik, $status_publikasi, $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_kalender.php?pesan=edit_sukses");
            exit;
        } else {
            $error_message = "Gagal memperbarui data: " . mysqli_error($conn);
        }
    }
}

// ===================================================================
// 3. HAPUS KEGIATAN AKADEMIK
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM kalender_akademik WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_kalender.php?pesan=hapus_sukses");
            exit;
        }
    }
    header("Location: admin_kalender.php?pesan=gagal");
    exit;
}

// ===================================================================
// 4. TOGGLE STATUS PUBLIKASI (DRAFT / DIPUBLIKASIKAN)
// ===================================================================
if (isset($_GET['toggle_status']) && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $q_cur = mysqli_query($conn, "SELECT status_publikasi FROM kalender_akademik WHERE id = $id LIMIT 1");
    if ($row_cur = mysqli_fetch_assoc($q_cur)) {
        $new_stat = ($row_cur['status_publikasi'] === 'Dipublikasikan') ? 'Draft' : 'Dipublikasikan';
        mysqli_query($conn, "UPDATE kalender_akademik SET status_publikasi = '$new_stat' WHERE id = $id");
        header("Location: admin_kalender.php?pesan=status_sukses");
        exit;
    }
}

// ===================================================================
// FILTER DAN PENCARIAN DATA
// ===================================================================
$search          = trim($_GET['search'] ?? '');
$filter_kategori = trim($_GET['kategori'] ?? '');
$filter_status   = trim($_GET['status'] ?? '');
$filter_semester = trim($_GET['semester'] ?? '');

$sql = "SELECT * FROM kalender_akademik WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (nama_kegiatan LIKE ? OR deskripsi LIKE ? OR tahun_akademik LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

if (!empty($filter_kategori) && $filter_kategori !== 'semua') {
    $sql .= " AND kategori = ?";
    $params[] = $filter_kategori;
    $types .= "s";
}

if (!empty($filter_status) && in_array($filter_status, ['Dipublikasikan', 'Draft'])) {
    $sql .= " AND status_publikasi = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_semester) && in_array($filter_semester, ['Ganjil', 'Genap'])) {
    $sql .= " AND semester = ?";
    $params[] = $filter_semester;
    $types .= "s";
}

$sql .= " ORDER BY tanggal_mulai DESC, id DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result_kegiatan = mysqli_stmt_get_result($stmt);
} else {
    $result_kegiatan = mysqli_query($conn, $sql);
}

// Statistik Kalender
$total_kegiatan    = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kalender_akademik"));
$total_publikasi   = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kalender_akademik WHERE status_publikasi = 'Dipublikasikan'"));
$total_draft       = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM kalender_akademik WHERE status_publikasi = 'Draft'"));
$total_ujian_resmi = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pendaftaran_seminar_sidang WHERE status IN ('Terjadwal', 'Sedang Berlangsung', 'Selesai')"));

$kategori_list = getKategoriKalenderList();
$active_menu = 'kalender';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengelolaan Kalender Akademik - Panel Admin SIM-SEMSID</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>

<div class="admin-app">
    <!-- Sidebar Navy Admin -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Topbar Admin -->
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button class="admin-sidebar-toggle" id="sidebarToggle" title="Buka/Tutup Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="admin-breadcrumb">
                    <a href="dashboard_admin.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
                    <span>/</span>
                    <span class="current">Kalender Akademik</span>
                </div>
            </div>
            <div class="admin-topbar-right">
                <!-- Komponen Notifikasi Lonceng -->
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <div class="topbar-date-pill">
                    <i class="fas fa-calendar-day"></i> <?= date('d M Y'); ?>
                </div>
                <div class="admin-badge-role">
                    <i class="fas fa-shield-halved"></i> Panel Administrator
                </div>
            </div>
        </header>

        <!-- Container Konten Admin -->
        <main class="admin-content">
            <!-- Header Halaman & Tombol Aksi Tambah -->
            <div class="admin-page-header">
                <div>
                    <h2><i class="fas fa-calendar-days" style="color: var(--admin-accent-orange);"></i> Pengelolaan Kalender Akademik</h2>
                    <p>Atur timeline kegiatan akademik, tanggal penting, pendaftaran seminar & sidang, semester, dan status publikasi.</p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="kalender_akademik.php" target="_blank" class="admin-btn admin-btn-outline" title="Lihat Kalender Publik">
                        <i class="fas fa-arrow-up-right-from-square"></i> Lihat Kalender Publik
                    </a>
                    <button type="button" class="admin-btn admin-btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Tambah Kegiatan Akademik
                    </button>
                </div>
            </div>

            <!-- Pesan Notifikasi Berhasil / Gagal -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <i class="fas fa-circle-check" style="font-size: 1.2rem; color: #16a34a;"></i>
                    <div><?= htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <i class="fas fa-triangle-exclamation" style="font-size: 1.2rem; color: #dc2626;"></i>
                    <div><?= htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Kartu Statistik Kalender Akademik -->
            <div class="admin-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 25px;">
                <div class="admin-stat-card">
                    <div class="admin-stat-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="fas fa-calendar-days"></i>
                    </div>
                    <div class="admin-stat-info">
                        <h3><?= $total_kegiatan; ?></h3>
                        <p>Total Kegiatan Terdaftar</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-icon" style="background: #ecfdf5; color: #059669;">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="admin-stat-info">
                        <h3><?= $total_publikasi; ?></h3>
                        <p>Dipublikasikan (Publik)</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-icon" style="background: #fffbeb; color: #d97706;">
                        <i class="fas fa-file-pen"></i>
                    </div>
                    <div class="admin-stat-info">
                        <h3><?= $total_draft; ?></h3>
                        <p>Status Draft (Tersembunyi)</p>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-icon" style="background: #fff7ed; color: #ea580c;">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="admin-stat-info">
                        <h3><?= $total_ujian_resmi; ?></h3>
                        <p>Jadwal Ujian Terpadu (Live)</p>
                    </div>
                </div>
            </div>

            <!-- Form Saring & Cari Kegiatan -->
            <div class="admin-card" style="margin-bottom: 25px;">
                <div class="admin-card-header" style="padding: 14px 20px;">
                    <h3 style="font-size: 0.95rem; margin: 0; color: #0f172a;">
                        <i class="fas fa-filter"></i> Saring & Cari Kegiatan Akademik
                    </h3>
                </div>
                <div class="admin-card-body" style="padding: 16px 20px;">
                    <form method="GET" action="admin_kalender.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; align-items: flex-end;">
                        <div>
                            <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 5px;">Pencarian Kata Kunci:</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Nama kegiatan atau deskripsi..." style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem;">
                        </div>

                        <div>
                            <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 5px;">Kategori Kegiatan:</label>
                            <select name="kategori" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: white;">
                                <option value="semua">-- Semua Kategori --</option>
                                <?php foreach ($kategori_list as $k_nama => $k_val): ?>
                                    <option value="<?= htmlspecialchars($k_nama); ?>" <?= ($filter_kategori === $k_nama) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($k_nama); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 5px;">Status Publikasi:</label>
                            <select name="status" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: white;">
                                <option value="">-- Semua Status --</option>
                                <option value="Dipublikasikan" <?= ($filter_status === 'Dipublikasikan') ? 'selected' : ''; ?>>Dipublikasikan</option>
                                <option value="Draft" <?= ($filter_status === 'Draft') ? 'selected' : ''; ?>>Draft</option>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 0.78rem; font-weight: 700; color: #475569; display: block; margin-bottom: 5px;">Semester:</label>
                            <select name="semester" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: white;">
                                <option value="">-- Semua Semester --</option>
                                <option value="Ganjil" <?= ($filter_semester === 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                                <option value="Genap" <?= ($filter_semester === 'Genap') ? 'selected' : ''; ?>>Genap</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="admin-btn admin-btn-primary" style="flex: 1; padding: 10px;">
                                <i class="fas fa-magnifying-glass"></i> Filter
                            </button>
                            <a href="admin_kalender.php" class="admin-btn admin-btn-outline" style="padding: 10px;" title="Reset Filter">
                                <i class="fas fa-rotate-left"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel Daftar Kegiatan Kalender Akademik -->
            <div class="admin-card">
                <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.05rem; margin: 0; color: #0f172a;">
                        <i class="fas fa-list-check"></i> Daftar Master Kegiatan Kalender Akademik
                    </h3>
                    <span style="font-size: 0.82rem; color: #64748b;">
                        Menampilkan <strong><?= mysqli_num_rows($result_kegiatan); ?></strong> data kegiatan
                    </span>
                </div>
                <div class="admin-card-body" style="padding: 0;">
                    <div style="overflow-x: auto;">
                        <table class="admin-table" style="width: 100%; margin: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 45px; text-align: center;">No</th>
                                    <th>Nama Kegiatan & Deskripsi</th>
                                    <th>Kategori</th>
                                    <th>Tanggal & Waktu</th>
                                    <th>Semester & TA</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="width: 130px; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result_kegiatan) === 0): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px 20px; color: #64748b;">
                                            <i class="fas fa-calendar-xmark" style="font-size: 2.2rem; color: #cbd5e1; margin-bottom: 8px; display: block;"></i>
                                            <strong>Tidak ada kegiatan akademik yang sesuai dengan filter.</strong>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; while ($row = mysqli_fetch_assoc($result_kegiatan)): ?>
                                        <?php 
                                            $k_info = getKategoriInfo($row['kategori']);
                                            $is_draft = ($row['status_publikasi'] === 'Draft');
                                            $row_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: 700; color: #64748b;"><?= $no++; ?></td>
                                            <td style="max-width: 300px;">
                                                <strong style="color: #0f172a; font-size: 0.95rem;"><?= htmlspecialchars($row['nama_kegiatan']); ?></strong>
                                                <?php if (!empty($row['deskripsi'])): ?>
                                                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px; line-height: 1.4;">
                                                        <?= htmlspecialchars(substr($row['deskripsi'], 0, 90)) . (strlen($row['deskripsi']) > 90 ? '...' : ''); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge" style="background: <?= $k_info['warna_bg']; ?>; color: <?= $k_info['warna_teks']; ?>; border: 1px solid <?= $k_info['warna_border']; ?>; font-size: 0.75rem; padding: 3px 9px; border-radius: 20px;">
                                                    <i class="fas <?= $k_info['ikon']; ?>"></i> <?= htmlspecialchars($row['kategori']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem;">
                                                    <i class="fas fa-calendar-day" style="color: var(--admin-accent-orange); margin-right: 4px;"></i>
                                                    <?= date('d M Y', strtotime($row['tanggal_mulai'])); ?>
                                                    <?php if ($row['tanggal_selesai'] !== $row['tanggal_mulai']): ?>
                                                        <span style="color: #64748b; font-weight: normal;">s.d</span> <?= date('d M Y', strtotime($row['tanggal_selesai'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
                                                    <i class="fas fa-clock" style="color: #f97316;"></i> <?= htmlspecialchars($row['waktu_kegiatan']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; font-size: 0.85rem; color: #334155;">
                                                    Semester <?= htmlspecialchars($row['semester']); ?>
                                                </div>
                                                <div style="font-size: 0.78rem; color: #64748b;">
                                                    TA <?= htmlspecialchars($row['tahun_akademik']); ?>
                                                </div>
                                            </td>
                                            <td style="text-align: center;">
                                                <a href="admin_kalender.php?toggle_status=1&id=<?= $row['id']; ?>" 
                                                   title="Klik untuk ubah status (Draft / Dipublikasikan)"
                                                   class="badge" 
                                                   style="text-decoration: none; cursor: pointer; padding: 4px 10px; border-radius: 20px; font-size: 0.76rem; <?= $is_draft ? 'background: #fffbeb; color: #d97706; border: 1px solid #fde68a;' : 'background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;'; ?>">
                                                    <i class="fas <?= $is_draft ? 'fa-file-pen' : 'fa-circle-check'; ?>"></i>
                                                    <?= htmlspecialchars($row['status_publikasi']); ?>
                                                </a>
                                            </td>
                                            <td style="text-align: center;">
                                                <div style="display: inline-flex; gap: 6px;">
                                                    <button type="button" class="admin-btn admin-btn-outline" 
                                                            style="padding: 5px 9px; font-size: 0.78rem;" 
                                                            title="Edit Kegiatan"
                                                            onclick="openEditModal(<?= $row_json; ?>)">
                                                        <i class="fas fa-pen-to-square"></i>
                                                    </button>
                                                    <form method="POST" action="admin_kalender.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kegiatan \'<?= htmlspecialchars(addslashes($row['nama_kegiatan'])); ?>\'?');" style="display: inline;">
                                                        <input type="hidden" name="action" value="hapus">
                                                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                                        <button type="submit" class="admin-btn admin-btn-danger" style="padding: 5px 9px; font-size: 0.78rem;" title="Hapus Kegiatan">
                                                            <i class="fas fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Petunjuk Sinkronisasi Jadwal Mahasiswa -->
            <div style="margin-top: 25px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px 20px; display: flex; align-items: flex-start; gap: 14px;">
                <div style="font-size: 1.5rem; color: #2563eb; margin-top: 2px;">
                    <i class="fas fa-circle-info"></i>
                </div>
                <div style="font-size: 0.88rem; color: #1e40af; line-height: 1.5;">
                    <strong>Informasi Integrasi Kalender & Jadwal Ujian:</strong><br>
                    Jadwal Seminar Proposal dan Sidang Akhir Skripsi mahasiswa yang berstatus <strong>Terjadwal</strong>, <strong>Sedang Berlangsung</strong>, atau <strong>Selesai</strong> otomatis ditampilkan di Kalender Akademik publik secara real-time. Anda tidak perlu memasukkan nama mahasiswa secara manual di tabel ini untuk mencegah data ganda.
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ==================== MODAL TAMBAH KEGIATAN ==================== -->
<div class="calendar-modal-backdrop" id="modalTambahKegiatan" onclick="closeModalOnBackdrop(event, 'modalTambahKegiatan')">
    <div class="calendar-modal-content" style="max-width: 620px;">
        <div class="calendar-modal-header">
            <h3><i class="fas fa-plus-circle" style="color: var(--admin-accent-orange);"></i> Tambah Kegiatan Akademik Baru</h3>
            <button type="button" class="calendar-modal-close" onclick="closeAddModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST" action="admin_kalender.php">
            <input type="hidden" name="action" value="tambah">
            <div class="calendar-modal-body">
                <div style="margin-bottom: 14px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Nama Kegiatan Akademik <span style="color: red;">*</span>:</label>
                    <input type="text" name="nama_kegiatan" required placeholder="Contoh: Periode Pendaftaran Seminar Proposal Gelombang II" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Kategori Kegiatan <span style="color: red;">*</span>:</label>
                    <select name="kategori" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; background: white;">
                        <option value="">-- Pilih Kategori Kegiatan --</option>
                        <?php foreach ($kategori_list as $kat_key => $k_val): ?>
                            <option value="<?= htmlspecialchars($kat_key); ?>"><?= htmlspecialchars($kat_key); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Tanggal Mulai <span style="color: red;">*</span>:</label>
                        <input type="date" name="tanggal_mulai" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Tanggal Selesai:</label>
                        <input type="date" name="tanggal_selesai" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                        <small style="color: #64748b; font-size: 0.72rem;">Kosongkan jika kegiatan hanya 1 hari</small>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Waktu Kegiatan:</label>
                        <input type="text" name="waktu_kegiatan" value="Sepanjang Hari" placeholder="Contoh: 08:00 - 16:00 WIB" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Status Publikasi:</label>
                        <select name="status_publikasi" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; background: white;">
                            <option value="Dipublikasikan">Dipublikasikan (Tampil di Kalender)</option>
                            <option value="Draft">Draft (Disimpan Sementara)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Semester:</label>
                        <select name="semester" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; background: white;">
                            <option value="Ganjil">Semester Ganjil</option>
                            <option value="Genap">Semester Genap</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Tahun Akademik:</label>
                        <input type="text" name="tahun_akademik" value="2026/2027" placeholder="Contoh: 2026/2027" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Deskripsi / Keterangan Tambahan:</label>
                    <textarea name="deskripsi" rows="3" placeholder="Informasi tata cara, berkas, atau ruang lingkup kegiatan..." style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;"></textarea>
                </div>
            </div>
            <div class="calendar-modal-footer">
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeAddModal()" style="margin-right: 8px;">Batal</button>
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> Simpan Kegiatan</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL EDIT KEGIATAN ==================== -->
<div class="calendar-modal-backdrop" id="modalEditKegiatan" onclick="closeModalOnBackdrop(event, 'modalEditKegiatan')">
    <div class="calendar-modal-content" style="max-width: 620px;">
        <div class="calendar-modal-header">
            <h3><i class="fas fa-pen-to-square" style="color: var(--admin-accent-orange);"></i> Edit Kegiatan Akademik</h3>
            <button type="button" class="calendar-modal-close" onclick="closeEditModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST" action="admin_kalender.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="calendar-modal-body">
                <div style="margin-bottom: 14px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Nama Kegiatan Akademik <span style="color: red;">*</span>:</label>
                    <input type="text" name="nama_kegiatan" id="edit_nama_kegiatan" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Kategori Kegiatan <span style="color: red;">*</span>:</label>
                    <select name="kategori" id="edit_kategori" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; background: white;">
                        <?php foreach ($kategori_list as $kat_key => $k_val): ?>
                            <option value="<?= htmlspecialchars($kat_key); ?>"><?= htmlspecialchars($kat_key); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Tanggal Mulai <span style="color: red;">*</span>:</label>
                        <input type="date" name="tanggal_mulai" id="edit_tanggal_mulai" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Tanggal Selesai:</label>
                        <input type="date" name="tanggal_selesai" id="edit_tanggal_selesai" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Waktu Kegiatan:</label>
                        <input type="text" name="waktu_kegiatan" id="edit_waktu_kegiatan" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Status Publikasi:</label>
                        <select name="status_publikasi" id="edit_status_publikasi" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; background: white;">
                            <option value="Dipublikasikan">Dipublikasikan (Tampil di Kalender)</option>
                            <option value="Draft">Draft (Disimpan Sementara)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Semester:</label>
                        <select name="semester" id="edit_semester" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; background: white;">
                            <option value="Ganjil">Semester Ganjil</option>
                            <option value="Genap">Semester Genap</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Tahun Akademik:</label>
                        <input type="text" name="tahun_akademik" id="edit_tahun_akademik" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                    </div>
                </div>

                <div>
                    <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Deskripsi / Keterangan:</label>
                    <textarea name="deskripsi" id="edit_deskripsi" rows="3" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;"></textarea>
                </div>
            </div>
            <div class="calendar-modal-footer">
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeEditModal()" style="margin-right: 8px;">Batal</button>
                <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-check"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Buka & Tutup Modal Tambah
    function openAddModal() {
        document.getElementById('modalTambahKegiatan').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeAddModal() {
        document.getElementById('modalTambahKegiatan').classList.remove('show');
        document.body.style.overflow = '';
    }

    // Buka & Tutup Modal Edit
    function openEditModal(data) {
        if (!data) return;
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nama_kegiatan').value = data.nama_kegiatan || '';
        document.getElementById('edit_kategori').value = data.kategori || '';
        document.getElementById('edit_tanggal_mulai').value = data.tanggal_mulai || '';
        document.getElementById('edit_tanggal_selesai').value = data.tanggal_selesai || '';
        document.getElementById('edit_waktu_kegiatan').value = data.waktu_kegiatan || '';
        document.getElementById('edit_status_publikasi').value = data.status_publikasi || 'Dipublikasikan';
        document.getElementById('edit_semester').value = data.semester || 'Ganjil';
        document.getElementById('edit_tahun_akademik').value = data.tahun_akademik || '2026/2027';
        document.getElementById('edit_deskripsi').value = data.deskripsi || '';

        document.getElementById('modalEditKegiatan').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        document.getElementById('modalEditKegiatan').classList.remove('show');
        document.body.style.overflow = '';
    }

    function closeModalOnBackdrop(e, modalId) {
        if (e.target && e.target.id === modalId) {
            if (modalId === 'modalTambahKegiatan') closeAddModal();
            else if (modalId === 'modalEditKegiatan') closeEditModal();
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
        }
    });
</script>
</body>
</html>
