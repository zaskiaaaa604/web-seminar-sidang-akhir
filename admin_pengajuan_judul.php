<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: admin_pengajuan_judul.php (Manajemen & Moderasi Pengajuan Judul)
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
    if ($_GET['pesan'] === 'status_sukses') $success_message = "Status pengajuan judul berhasil diperbarui!";
    elseif ($_GET['pesan'] === 'pesan_sukses') $success_message = "Tanggapan / catatan admin berhasil dikirim ke diskusi!";
    elseif ($_GET['pesan'] === 'hapus_sukses') $success_message = "Data pengajuan judul berhasil dihapus!";
    elseif ($_GET['pesan'] === 'kuota_penuh') $error_message = "Gagal menerima judul: Kuota mahasiswa bimbingan dosen tujuan sudah penuh (" . KUOTA_MAKSIMAL_DOSEN . "/" . KUOTA_MAKSIMAL_DOSEN . " mahasiswa)!";
    elseif ($_GET['pesan'] === 'gagal') $error_message = "Terjadi kesalahan sistem saat memproses pengajuan judul.";
}

// 1. UPDATE STATUS & MODERASI PENGAJUAN JUDUL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ubah_status') {
    $pengajuan_id     = (int)($_POST['pengajuan_id'] ?? 0);
    $status_baru      = trim($_POST['status'] ?? '');
    $catatan_revisi   = trim($_POST['catatan_revisi'] ?? '');
    $alasan_penolakan = trim($_POST['alasan_penolakan'] ?? '');

    $valid_statuses = ['Menunggu', 'Sedang diperiksa', 'Perlu revisi', 'Diterima', 'Ditolak'];
    if ($pengajuan_id > 0 && in_array($status_baru, $valid_statuses)) {
        // Ambil info dosen & status lama
        $q_cur = mysqli_query($conn, "SELECT dosen_id, status FROM pengajuan_judul WHERE id = $pengajuan_id LIMIT 1");
        if ($cur = mysqli_fetch_assoc($q_cur)) {
            $dosen_id = (int)$cur['dosen_id'];
            $status_lama = $cur['status'];

            // Jika status diubah menjadi 'Diterima', pastikan kuota dosen masih tersedia
            if ($status_baru === 'Diterima' && $status_lama !== 'Diterima') {
                $bimbingan_saat_ini = hitungBimbinganDosen($conn, $dosen_id);
                if ($bimbingan_saat_ini >= KUOTA_MAKSIMAL_DOSEN) {
                    header("Location: admin_pengajuan_judul.php?pesan=kuota_penuh");
                    exit;
                }
            }

            // Update status pengajuan di database
            $stmt = mysqli_prepare($conn, "UPDATE pengajuan_judul SET status = ?, catatan_revisi = ?, alasan_penolakan = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $status_baru, $catatan_revisi, $alasan_penolakan, $pengajuan_id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                // Rekam log di tanggapan_pengajuan
                $pesan_log = "Administrator memperbarui status pengajuan menjadi: [" . $status_baru . "].";
                if (!empty($catatan_revisi)) $pesan_log .= "\nCatatan Revisi: " . $catatan_revisi;
                if (!empty($alasan_penolakan)) $pesan_log .= "\nAlasan Penolakan: " . $alasan_penolakan;

                $log_stmt = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'dosen', ?, ?, ?)");
                // Role tanggapan: gunakan status_perubahan
                mysqli_stmt_bind_param($log_stmt, "iiss", $pengajuan_id, $admin_id, $pesan_log, $status_baru);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);

                // NOTIFIKASI OTOMATIS: Mahasiswa dan Dosen menerima kabar perubahan status oleh admin
                $q_inf = mysqli_query($conn, "SELECT pj.mahasiswa_id, pj.judul, pj.dosen_id, m.nama AS nama_mhs, m.nim, d.nama_dosen FROM pengajuan_judul pj JOIN mahasiswa m ON pj.mahasiswa_id = m.id JOIN dosen d ON pj.dosen_id = d.id WHERE pj.id = $pengajuan_id LIMIT 1");
                if ($r_inf = mysqli_fetch_assoc($q_inf)) {
                    $mhs_id = (int)$r_inf['mahasiswa_id'];
                    $dsn_id = (int)$r_inf['dosen_id'];
                    $judul_penelitian = $r_inf['judul'];
                    $nama_mhs = $r_inf['nama_mhs'];
                    $nama_dsn = $r_inf['nama_dosen'];

                    $tipe_notif = ($status_baru === 'Diterima') ? 'diterima' : (($status_baru === 'Ditolak') ? 'ditolak' : 'revisi');
                    kirimNotifikasi($conn, 'mahasiswa', $mhs_id, "Status Pengajuan Judul Diperbarui", "Administrator memperbarui status judul Anda: \"$judul_penelitian\" menjadi [$status_baru]." . (!empty($catatan_revisi) ? " Catatan: $catatan_revisi" : "") . (!empty($alasan_penolakan) ? " Alasan: $alasan_penolakan" : ""), 'pengajuan_judul_mahasiswa.php?buka_id=' . $pengajuan_id, $tipe_notif);

                    kirimNotifikasi($conn, 'dosen', $dsn_id, "Pembaruan Pengajuan Judul Mahasiswa", "Administrator telah memperbarui status usulan judul mahasiswa $nama_mhs: \"$judul_penelitian\" menjadi [$status_baru].", 'dashboard_dosen.php?buka_id=' . $pengajuan_id, 'info');
                }

                header("Location: admin_pengajuan_judul.php?pesan=status_sukses");
                exit;
            }
        }
    }
    header("Location: admin_pengajuan_judul.php?pesan=gagal");
    exit;
}

// 2. KIRIM PESAN DISKUSI ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kirim_diskusi') {
    $pengajuan_id = (int)($_POST['pengajuan_id'] ?? 0);
    $pesan        = trim($_POST['pesan'] ?? '');

    if ($pengajuan_id > 0 && !empty($pesan)) {
        $pesan_admin = "[Pesan Administrator]: " . $pesan;
        $status_perubahan = 'Komentar';
        $log_stmt = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'dosen', ?, ?, ?)");
        mysqli_stmt_bind_param($log_stmt, "iiss", $pengajuan_id, $admin_id, $pesan_admin, $status_perubahan);
        if (mysqli_stmt_execute($log_stmt)) {
            mysqli_stmt_close($log_stmt);
            header("Location: admin_pengajuan_judul.php?pesan=pesan_sukses");
            exit;
        }
    }
    header("Location: admin_pengajuan_judul.php?pesan=gagal");
    exit;
}

// 3. HAPUS PENGAJUAN JUDUL
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM pengajuan_judul WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_pengajuan_judul.php?pesan=hapus_sukses");
            exit;
        }
    }
    header("Location: admin_pengajuan_judul.php?pesan=gagal");
    exit;
}

// Filter & Pencarian
$search        = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_dosen  = (int)($_GET['dosen_id'] ?? 0);

$sql = "
    SELECT pj.*, 
           m.nim, m.nama AS nama_mahasiswa, m.email AS email_mahasiswa, m.no_hp AS hp_mahasiswa,
           d.nidn, d.nama_dosen, d.gelar_akademik, d.email AS email_dosen
    FROM pengajuan_judul pj
    JOIN mahasiswa m ON pj.mahasiswa_id = m.id
    JOIN dosen d ON pj.dosen_id = d.id
    WHERE 1=1
";
$params = [];
$types  = "";

if (!empty($search)) {
    $sql .= " AND (pj.judul LIKE ? OR m.nama LIKE ? OR m.nim LIKE ? OR d.nama_dosen LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "ssss";
}

if (!empty($filter_status) && in_array($filter_status, ['Menunggu', 'Sedang diperiksa', 'Perlu revisi', 'Diterima', 'Ditolak'])) {
    $sql .= " AND pj.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_dosen > 0) {
    $sql .= " AND pj.dosen_id = ?";
    $params[] = $filter_dosen;
    $types .= "i";
}

$sql .= " ORDER BY pj.created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result_judul = mysqli_stmt_get_result($stmt);
} else {
    $result_judul = mysqli_query($conn, $sql);
}

// Statistik Pengajuan
$stat_total    = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul"));
$stat_menunggu = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul WHERE status IN ('Menunggu', 'Sedang diperiksa')"));
$stat_diterima = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul WHERE status = 'Diterima'"));
$stat_revisi   = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul WHERE status IN ('Perlu revisi', 'Ditolak')"));

// Ambil list dosen untuk filter
$list_dosen = mysqli_query($conn, "SELECT id, nama_dosen, gelar_akademik FROM dosen ORDER BY nama_dosen ASC");

// Ambil semua tanggapan untuk modal detail
$q_tanggapan = mysqli_query($conn, "SELECT * FROM tanggapan_pengajuan ORDER BY created_at ASC");
$tanggapan_map = [];
if ($q_tanggapan) {
    while ($tg = mysqli_fetch_assoc($q_tanggapan)) {
        $tanggapan_map[$tg['pengajuan_id']][] = $tg;
    }
}

$active_menu = 'judul';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Judul Penelitian - Panel Admin SIM-SEMSID</title>
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
                    <span class="current">Pengajuan Judul</span>
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
                    <h1><i class="fas fa-book-bookmark" style="color: #60a5fa; margin-right: 8px;"></i> Pengajuan Judul Penelitian & Skripsi</h1>
                    <p>Pantau seluruh alur pengajuan judul mahasiswa kepada dosen calon pembimbing, periksa dokumen pendukung, dan moderasi status persetujuan kuota.</p>
                </div>
                <div>
                    <a href="admin_kuota_dosen.php" class="btn-admin btn-admin-primary">
                        <i class="fas fa-chart-pie"></i> Cek Kuota Dosen (<?= KUOTA_MAKSIMAL_DOSEN; ?>/dosen)
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
                        <div class="stat-card-title">Total Pengajuan Judul</div>
                        <div class="stat-card-icon icon-blue"><i class="fas fa-folder-open"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $stat_total; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-list"></i> Seluruh usulan penelitian</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Menunggu / Diperiksa</div>
                        <div class="stat-card-icon icon-orange"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $stat_menunggu; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-hourglass-half"></i> Perlu respon dosen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Judul Diterima (Kuota)</div>
                        <div class="stat-card-icon icon-green"><i class="fas fa-check-double"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $stat_diterima; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-user-check"></i> Resmi menjadi bimbingan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Revisi / Ditolak</div>
                        <div class="stat-card-icon icon-purple"><i class="fas fa-rotate"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $stat_revisi; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-pen-nib"></i> Memerlukan perbaikan</div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="admin-table-card">
                <div class="admin-table-header">
                    <div>
                        <h2 class="admin-table-title"><i class="fas fa-table-list" style="color: var(--primary);"></i> Data Pengajuan Judul Mahasiswa</h2>
                        <p style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">Daftar pengajuan judul, dosen pembimbing yang dituju, dan riwayat komunikasi</p>
                    </div>

                    <!-- Toolbar Filter -->
                    <form method="GET" action="admin_pengajuan_judul.php" class="admin-toolbar-filter">
                        <div class="admin-search-input">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" name="search" placeholder="Cari judul, NIM, mahasiswa..." value="<?= htmlspecialchars($search); ?>">
                        </div>
                        <select name="status" class="admin-filter-select" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="Menunggu" <?= ($filter_status === 'Menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="Sedang diperiksa" <?= ($filter_status === 'Sedang diperiksa') ? 'selected' : ''; ?>>Sedang diperiksa</option>
                            <option value="Perlu revisi" <?= ($filter_status === 'Perlu revisi') ? 'selected' : ''; ?>>Perlu revisi</option>
                            <option value="Diterima" <?= ($filter_status === 'Diterima') ? 'selected' : ''; ?>>Diterima</option>
                            <option value="Ditolak" <?= ($filter_status === 'Ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                        </select>
                        <select name="dosen_id" class="admin-filter-select" onchange="this.form.submit()" style="max-width: 220px;">
                            <option value="0">Semua Dosen</option>
                            <?php if ($list_dosen): while ($ds = mysqli_fetch_assoc($list_dosen)): ?>
                                <option value="<?= $ds['id']; ?>" <?= ($filter_dosen == $ds['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($ds['nama_dosen'] . ', ' . $ds['gelar_akademik']); ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                        <button type="submit" class="btn-admin btn-admin-secondary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($search) || !empty($filter_status) || $filter_dosen > 0): ?>
                            <a href="admin_pengajuan_judul.php" class="btn-admin btn-admin-secondary" title="Reset Filter"><i class="fas fa-rotate-left"></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Mahasiswa Pengusul</th>
                                <th>Judul Penelitian & Skripsi</th>
                                <th>Dosen Pembimbing</th>
                                <th>Tanggal Pengajuan</th>
                                <th>Status</th>
                                <th>Berkas</th>
                                <th style="text-align: center; width: 140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result_judul) > 0): ?>
                                <?php $no = 1; while ($row = mysqli_fetch_assoc($result_judul)): 
                                    $p_id = $row['id'];
                                    $diskusi = $tanggapan_map[$p_id] ?? [];
                                ?>
                                    <tr>
                                        <td><strong><?= $no++; ?></strong></td>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;"><?= htmlspecialchars($row['nama_mahasiswa']); ?></div>
                                            <span class="badge-role badge-role-admin" style="font-family: monospace; font-size: 0.8rem;"><?= htmlspecialchars($row['nim']); ?></span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #1e293b; max-width: 320px; line-height: 1.4;">
                                                <?= htmlspecialchars($row['judul']); ?>
                                            </div>
                                            <div style="font-size: 0.78rem; color: #64748b; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                <?= htmlspecialchars($row['latar_belakang']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #0f172a; font-size: 0.88rem;">
                                                <?= htmlspecialchars($row['nama_dosen'] . ', ' . $row['gelar_akademik']); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b; font-family: monospace;">NIDN: <?= htmlspecialchars($row['nidn']); ?></div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem; color: #334155; font-weight: 600;">
                                                <?= date('d M Y', strtotime($row['created_at'])); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #94a3b8;">
                                                <?= date('H:i', strtotime($row['created_at'])); ?> WIB
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $st = $row['status'];
                                            if ($st === 'Diterima') {
                                                echo '<span class="badge-status badge-selesai"><i class="fas fa-check-circle"></i> Diterima</span>';
                                            } elseif ($st === 'Menunggu') {
                                                echo '<span class="badge-status badge-menunggu"><i class="fas fa-clock"></i> Menunggu</span>';
                                            } elseif ($st === 'Sedang diperiksa') {
                                                echo '<span class="badge-status badge-terjadwal"><i class="fas fa-magnifying-glass"></i> Diperiksa</span>';
                                            } elseif ($st === 'Perlu revisi') {
                                                echo '<span class="badge-status" style="background:#fff7ed; color:#c2410c; border:1px solid #ffedd5;"><i class="fas fa-pen"></i> Perlu Revisi</span>';
                                            } elseif ($st === 'Ditolak') {
                                                echo '<span class="badge-status badge-ditolak"><i class="fas fa-ban"></i> Ditolak</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px;">
                                                <?php if (!empty($row['file_dokumen'])): ?>
                                                    <a href="uploads/judul/<?= htmlspecialchars($row['file_dokumen']); ?>" target="_blank" class="btn-action btn-action-detail" title="Lihat Dokumen Pendukung (PDF/DOC)">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($row['file_gambar'])): ?>
                                                    <a href="uploads/judul/<?= htmlspecialchars($row['file_gambar']); ?>" target="_blank" class="btn-action btn-action-edit" title="Lihat Gambar Arsitektur/Diagram">
                                                        <i class="fas fa-image"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (empty($row['file_dokumen']) && empty($row['file_gambar'])): ?>
                                                    <span style="color: #94a3b8; font-size: 0.8rem;">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center;">
                                                <button class="btn-action btn-action-detail" title="Detail & Diskusi Pengajuan" onclick='bukaModalDetail(<?= json_encode($row); ?>, <?= json_encode($diskusi); ?>)'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-action btn-action-edit" title="Ubah Status & Moderasi" onclick='bukaModalStatus(<?= json_encode($row); ?>)'>
                                                    <i class="fas fa-sliders"></i>
                                                </button>
                                                <a href="admin_pengajuan_judul.php?action=hapus&id=<?= $row['id']; ?>" class="btn-action btn-action-delete" title="Hapus Pengajuan" onclick="return confirm('Apakah Anda yakin ingin menghapus data pengajuan judul ini?');">
                                                    <i class="fas fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                        Belum ada data pengajuan judul yang sesuai dengan pencarian.
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

<!-- MODAL DETAIL PENGAJUAN -->
<div class="admin-modal" id="modalDetail">
    <div class="admin-modal-card" style="max-width: 650px;">
        <div class="admin-modal-header">
            <h3><i class="fas fa-circle-info" style="color: var(--primary);"></i> Detail Pengajuan Judul & Diskusi</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalDetail')">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">Informasi Mahasiswa & Dosen</div>
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <div style="font-weight: 700; color: #0f172a;" id="dt_mahasiswa">-</div>
                        <div style="font-size: 0.8rem; color: #64748b;" id="dt_nim">-</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 700; color: #0f172a;" id="dt_dosen">-</div>
                        <div style="font-size: 0.8rem; color: #64748b;" id="dt_nidn">-</div>
                    </div>
                </div>
            </div>

            <div class="form-admin-group">
                <label class="form-admin-label">Judul Penelitian</label>
                <div id="dt_judul" style="font-weight: 600; color: #1e293b; background: white; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px;">-</div>
            </div>

            <div class="form-admin-group">
                <label class="form-admin-label">Latar Belakang / Rumusan Masalah</label>
                <div id="dt_latar_belakang" style="font-size: 0.88rem; color: #334155; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px; white-space: pre-wrap; max-height: 160px; overflow-y: auto;">-</div>
            </div>

            <div class="form-admin-group" id="dt_berkas_wrap" style="display: none;">
                <label class="form-admin-label">Dokumen & Gambar Terlampir</label>
                <div id="dt_berkas_links" style="display: flex; gap: 10px; flex-wrap: wrap;"></div>
            </div>

            <hr style="margin: 18px 0; border: none; border-top: 1px solid #e2e8f0;">

            <!-- Riwayat Diskusi / Timeline -->
            <div style="font-weight: 700; color: #0f172a; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-comments" style="color: #3b82f6;"></i> Riwayat Catatan & Komunikasi
            </div>
            <div id="dt_diskusi_box" style="max-height: 200px; overflow-y: auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 14px;">
                <p style="color: #94a3b8; font-size: 0.85rem; text-align: center;">Belum ada riwayat percakapan.</p>
            </div>

            <!-- Kirim Pesan Admin Tambahan -->
            <form method="POST" action="admin_pengajuan_judul.php">
                <input type="hidden" name="action" value="kirim_diskusi">
                <input type="hidden" name="pengajuan_id" id="diskusi_pengajuan_id">
                <div style="display: flex; gap: 8px;">
                    <input type="text" name="pesan" class="form-admin-control" placeholder="Tuliskan catatan/tanggapan administrator..." required>
                    <button type="submit" class="btn-admin btn-admin-primary" style="white-space: nowrap;">
                        <i class="fas fa-paper-plane"></i> Kirim
                    </button>
                </div>
            </form>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalDetail')">Tutup</button>
        </div>
    </div>
</div>

<!-- MODAL UBAH STATUS PENGAJUAN -->
<div class="admin-modal" id="modalStatus">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><i class="fas fa-sliders" style="color: #2563eb;"></i> Moderasi Status Pengajuan Judul</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalStatus')">&times;</button>
        </div>
        <form method="POST" action="admin_pengajuan_judul.php">
            <input type="hidden" name="action" value="ubah_status">
            <input type="hidden" name="pengajuan_id" id="status_pengajuan_id">
            <div class="admin-modal-body">
                <div class="form-admin-group">
                    <label class="form-admin-label">Judul Penelitian</label>
                    <div id="status_judul_preview" style="font-weight: 600; color: #1e293b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; font-size: 0.9rem;">-</div>
                </div>

                <div class="form-admin-group">
                    <label class="form-admin-label">Pilih Status Baru *</label>
                    <select name="status" id="select_status" class="form-admin-control" required onchange="toggleFeedbackFields()">
                        <option value="Menunggu">Menunggu</option>
                        <option value="Sedang diperiksa">Sedang diperiksa</option>
                        <option value="Perlu revisi">Perlu revisi</option>
                        <option value="Diterima">Diterima (Mengikat Kuota Bimbingan)</option>
                        <option value="Ditolak">Ditolak</option>
                    </select>
                    <small style="color: #64748b; font-size: 0.75rem;">Status 'Diterima' akan otomatis memotong 1 kuota dosen pembimbing terkait (maksimal <?= KUOTA_MAKSIMAL_DOSEN; ?>).</small>
                </div>

                <div class="form-admin-group" id="group_catatan_revisi" style="display: none;">
                    <label class="form-admin-label">Catatan Revisi untuk Mahasiswa</label>
                    <textarea name="catatan_revisi" id="txt_catatan_revisi" class="form-admin-control" rows="3" placeholder="Jelaskan poin-poin yang perlu diperbaiki oleh mahasiswa..."></textarea>
                </div>

                <div class="form-admin-group" id="group_alasan_penolakan" style="display: none;">
                    <label class="form-admin-label">Alasan Penolakan Judul *</label>
                    <textarea name="alasan_penolakan" id="txt_alasan_penolakan" class="form-admin-control" rows="3" placeholder="Sebutkan alasan penolakan judul secara jelas..."></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalStatus')">Batal</button>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="fas fa-save"></i> Simpan Status</button>
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

    function bukaModalDetail(row, diskusiList) {
        document.getElementById('dt_mahasiswa').innerText = row.nama_mahasiswa;
        document.getElementById('dt_nim').innerText = 'NIM: ' + row.nim;
        document.getElementById('dt_dosen').innerText = row.nama_dosen + ', ' + row.gelar_akademik;
        document.getElementById('dt_nidn').innerText = 'NIDN: ' + row.nidn;
        document.getElementById('dt_judul').innerText = row.judul;
        document.getElementById('dt_latar_belakang').innerText = row.latar_belakang;
        document.getElementById('diskusi_pengajuan_id').value = row.id;

        // Lampiran berkas
        const berkasWrap = document.getElementById('dt_berkas_wrap');
        const berkasLinks = document.getElementById('dt_berkas_links');
        berkasLinks.innerHTML = '';
        let hasFiles = false;

        if (row.file_dokumen) {
            hasFiles = true;
            berkasLinks.innerHTML += `
                <a href="uploads/judul/${row.file_dokumen}" target="_blank" class="btn-admin btn-admin-secondary" style="font-size: 0.8rem;">
                    <i class="fas fa-file-pdf" style="color: #ef4444;"></i> Buka Dokumen Pendukung
                </a>
            `;
        }
        if (row.file_gambar) {
            hasFiles = true;
            berkasLinks.innerHTML += `
                <a href="uploads/judul/${row.file_gambar}" target="_blank" class="btn-admin btn-admin-secondary" style="font-size: 0.8rem;">
                    <i class="fas fa-image" style="color: #3b82f6;"></i> Buka Gambar Diagram
                </a>
            `;
        }
        berkasWrap.style.display = hasFiles ? 'block' : 'none';

        // Render Riwayat Diskusi
        const diskusiBox = document.getElementById('dt_diskusi_box');
        if (diskusiList && diskusiList.length > 0) {
            let html = '';
            diskusiList.forEach(item => {
                const isMhs = item.pengirim_role === 'mahasiswa';
                const sender = isMhs ? 'Mahasiswa' : 'Dosen / Admin';
                const bg = isMhs ? '#eff6ff' : '#f0fdf4';
                const border = isMhs ? '#bfdbfe' : '#bbf7d0';
                html += `
                    <div style="background: ${bg}; border: 1px solid ${border}; border-radius: 6px; padding: 8px 12px; margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; font-weight: 600;">
                            <span>${sender}</span>
                            <span>${item.created_at}</span>
                        </div>
                        <div style="font-size: 0.85rem; color: #1e293b; margin-top: 4px; white-space: pre-wrap;">${item.pesan}</div>
                    </div>
                `;
            });
            diskusiBox.innerHTML = html;
        } else {
            diskusiBox.innerHTML = '<p style="color: #94a3b8; font-size: 0.85rem; text-align: center; margin: 10px 0;">Belum ada riwayat percakapan.</p>';
        }

        document.getElementById('modalDetail').classList.add('active');
    }

    function bukaModalStatus(row) {
        document.getElementById('status_pengajuan_id').value = row.id;
        document.getElementById('status_judul_preview').innerText = row.judul;
        document.getElementById('select_status').value = row.status;
        document.getElementById('txt_catatan_revisi').value = row.catatan_revisi || '';
        document.getElementById('txt_alasan_penolakan').value = row.alasan_penolakan || '';
        toggleFeedbackFields();
        document.getElementById('modalStatus').classList.add('active');
    }

    function toggleFeedbackFields() {
        const val = document.getElementById('select_status').value;
        const grpRevisi = document.getElementById('group_catatan_revisi');
        const grpTolak = document.getElementById('group_alasan_penolakan');

        grpRevisi.style.display = (val === 'Perlu revisi') ? 'block' : 'none';
        grpTolak.style.display = (val === 'Ditolak') ? 'block' : 'none';
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
