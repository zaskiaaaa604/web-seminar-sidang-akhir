<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - JURUSAN TIK
 * FILE: admin_dokumen.php (Manajemen & Pemeriksaan Dokumen Mahasiswa)
 * ===================================================================
 */

session_start();
require_once __DIR__ . '/koneksi.php';

// Validasi Keamanan: Hanya Administrator
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$admin_nama = $_SESSION['nama'] ?? 'Administrator';

$success_msg = '';
$error_msg   = '';

// ===================================================================
// 1. PROSES VERIFIKASI / PERUBAHAN STATUS DOKUMEN OLEH ADMIN
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $dok_id        = (int)($_POST['dokumen_id'] ?? 0);
    $status_baru   = trim($_POST['status'] ?? '');
    $catatan_admin = trim($_POST['catatan_admin'] ?? '');

    $allowed_status = ['Menunggu pemeriksaan', 'Diterima', 'Perlu perbaikan'];

    if (!in_array($status_baru, $allowed_status)) {
        $error_msg = "Status verifikasi yang dipilih tidak valid.";
    } elseif ($dok_id <= 0) {
        $error_msg = "Dokumen tidak ditemukan.";
    } else {
        $stmt_upd = mysqli_prepare($conn, "
            UPDATE dokumen_mahasiswa 
            SET status = ?, catatan_admin = ?, diperiksa_oleh = ?, waktu_diperiksa = NOW() 
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt_upd, "ssii", $status_baru, $catatan_admin, $admin_id, $dok_id);

        if (mysqli_stmt_execute($stmt_upd)) {
            // Ambil data mahasiswa pemilik dokumen untuk dikirimi notifikasi
            $stmt_mhs = mysqli_prepare($conn, "
                SELECT d.mahasiswa_id, d.jenis_dokumen, d.judul_dokumen, m.nama 
                FROM dokumen_mahasiswa d
                JOIN mahasiswa m ON d.mahasiswa_id = m.id
                WHERE d.id = ? LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt_mhs, "i", $dok_id);
            mysqli_stmt_execute($stmt_mhs);
            $res_mhs = mysqli_stmt_get_result($stmt_mhs);
            $doc_info = mysqli_fetch_assoc($res_mhs);

            if ($doc_info) {
                $target_mhs_id = $doc_info['mahasiswa_id'];
                $notif_judul   = "Status Berkas: " . $status_baru;
                $notif_pesan   = "Dokumen '{$doc_info['judul_dokumen']}' ({$doc_info['jenis_dokumen']}) telah diverifikasi oleh Administrator dengan status: '$status_baru'. Catatan: " . (!empty($catatan_admin) ? $catatan_admin : "Tidak ada catatan.");
                $notif_tautan  = "dokumen_mahasiswa.php";

                $stmt_notif = mysqli_prepare($conn, "
                    INSERT INTO notifikasi (penerima_role, penerima_id, judul, pesan, tautan, tipe, is_read) 
                    VALUES ('mahasiswa', ?, ?, ?, ?, 'dokumen', 0)
                ");
                mysqli_stmt_bind_param($stmt_notif, "isss", $target_mhs_id, $notif_judul, $notif_pesan, $notif_tautan);
                mysqli_stmt_execute($stmt_notif);
            }

            $success_msg = "Status berkas berhasil diperbarui menjadi <strong>'$status_baru'</strong> dan notifikasi telah dikirimkan ke mahasiswa bersangkutan.";
        } else {
            $error_msg = "Gagal memperbarui status: " . mysqli_error($conn);
        }
    }
}

// ===================================================================
// 2. FILTER & PENCARIAN
// ===================================================================
$tab_filter    = $_GET['tab'] ?? 'semua';
$search        = trim($_GET['search'] ?? '');
$jenis_filter  = trim($_GET['jenis'] ?? '');

$sql_docs = "
    SELECT d.*, m.nama AS nama_mahasiswa, m.nim, m.program_studi, m.email 
    FROM dokumen_mahasiswa d
    JOIN mahasiswa m ON d.mahasiswa_id = m.id
    WHERE 1=1
";
$params = [];
$types  = "";

if ($tab_filter === 'pemeriksaan' || $tab_filter === 'menunggu') {
    $sql_docs .= " AND d.status = 'Menunggu pemeriksaan'";
} elseif ($tab_filter === 'diterima') {
    $sql_docs .= " AND d.status = 'Diterima'";
} elseif ($tab_filter === 'revisi') {
    $sql_docs .= " AND d.status = 'Perlu perbaikan'";
}

if (!empty($search)) {
    $sql_docs .= " AND (m.nama LIKE ? OR m.nim LIKE ? OR d.judul_dokumen LIKE ? OR d.nama_file_asli LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= "ssss";
}

if (!empty($jenis_filter)) {
    $sql_docs .= " AND d.jenis_dokumen = ?";
    $params[] = $jenis_filter;
    $types .= "s";
}

$sql_docs .= " ORDER BY d.created_at DESC";

$stmt = mysqli_prepare($conn, $sql_docs);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res_data = mysqli_stmt_get_result($stmt);

// STATISTIK RINGKASAN DOKUMEN
$q_stat = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Menunggu pemeriksaan' THEN 1 ELSE 0 END) AS total_menunggu,
        SUM(CASE WHEN status = 'Diterima' THEN 1 ELSE 0 END) AS total_diterima,
        SUM(CASE WHEN status = 'Perlu perbaikan' THEN 1 ELSE 0 END) AS total_revisi
    FROM dokumen_mahasiswa
");
$stats = mysqli_fetch_assoc($q_stat);
$total_all      = (int)($stats['total'] ?? 0);
$total_menunggu = (int)($stats['total_menunggu'] ?? 0);
$total_diterima = (int)($stats['total_diterima'] ?? 0);
$total_revisi   = (int)($stats['total_revisi'] ?? 0);

function formatBytesAdmin($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

$active_menu = 'dokumen';
$active_submenu = ($tab_filter === 'pemeriksaan') ? 'pemeriksaan' : (($tab_filter === 'status') ? 'status' : 'dokumen_mhs');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemeriksaan Dokumen Mahasiswa - Admin SIM-SEMSID</title>

    <!-- FontAwesome & CSS Admin -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .filter-tab-bar {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 22px;
            padding-bottom: 2px;
        }
        .filter-tab-link {
            padding: 10px 18px;
            font-size: 0.88rem;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            border-radius: 6px 6px 0 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .filter-tab-link:hover {
            color: #ea580c;
            background: #fff7ed;
        }
        .filter-tab-link.active {
            color: #ea580c;
            border-bottom: 3px solid #ea580c;
            background: #fff7ed;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-box {
            background: white;
            border-radius: 12px;
            max-width: 550px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: modalFadeIn 0.2s ease;
        }
        @keyframes modalFadeIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="admin-body">

    <?php include 'admin_sidebar.php'; ?>

    <div class="admin-main-wrapper">

        <!-- Topbar Admin -->
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button type="button" class="admin-mobile-toggle" onclick="toggleSidebar();" title="Buka Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="admin-topbar-title">
                    <h1>Pemeriksaan Dokumen Persyaratan</h1>
                    <span>Verifikasi berkas proposal, laporan, dan dokumen kelayakan mahasiswa</span>
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

        <main class="admin-content-body">

            <!-- Breadcrumb Navigation -->
            <nav style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">
                <a href="dashboard_admin.php" style="color: var(--admin-blue-primary); text-decoration: none;"><i class="fas fa-home"></i> Dashboard</a>
                <span style="margin: 0 8px;">/</span>
                <span style="color: #1e293b; font-weight: 600;">Dokumen Mahasiswa</span>
            </nav>

            <?php if (!empty($success_msg)): ?>
                <div style="background: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
                    <div><?= $success_msg; ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div style="background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
                    <div><?= htmlspecialchars($error_msg); ?></div>
                </div>
            <?php endif; ?>

            <!-- Kartu Statistik Dokumen Mahasiswa -->
            <div class="admin-stats-grid" style="margin-bottom: 25px;">
                <a href="admin_dokumen.php?tab=semua" class="admin-stat-card">
                    <div class="admin-stat-icon stat-icon-blue">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value"><?= $total_all; ?></div>
                        <div class="admin-stat-label">Total Dokumen Diunggah</div>
                    </div>
                </a>

                <a href="admin_dokumen.php?tab=pemeriksaan" class="admin-stat-card">
                    <div class="admin-stat-icon stat-icon-amber">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value" style="color: #d97706;"><?= $total_menunggu; ?></div>
                        <div class="admin-stat-label">Menunggu Pemeriksaan</div>
                    </div>
                </a>

                <a href="admin_dokumen.php?tab=diterima" class="admin-stat-card">
                    <div class="admin-stat-icon stat-icon-green">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value" style="color: #16a34a;"><?= $total_diterima; ?></div>
                        <div class="admin-stat-label">Dokumen Diterima (ACC)</div>
                    </div>
                </a>

                <a href="admin_dokumen.php?tab=revisi" class="admin-stat-card">
                    <div class="admin-stat-icon stat-icon-red">
                        <i class="fas fa-circle-exclamation"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value" style="color: #dc2626;"><?= $total_revisi; ?></div>
                        <div class="admin-stat-label">Perlu Perbaikan (Revisi)</div>
                    </div>
                </a>
            </div>

            <!-- Tab Filter Status Pemeriksaan -->
            <div class="filter-tab-bar">
                <a href="admin_dokumen.php?tab=semua<?= !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="filter-tab-link <?= ($tab_filter === 'semua' || empty($tab_filter)) ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Semua Dokumen (<?= $total_all; ?>)
                </a>
                <a href="admin_dokumen.php?tab=pemeriksaan<?= !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="filter-tab-link <?= ($tab_filter === 'pemeriksaan' || $tab_filter === 'menunggu') ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i> Pemeriksaan Berkas (<?= $total_menunggu; ?>)
                </a>
                <a href="admin_dokumen.php?tab=diterima<?= !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="filter-tab-link <?= ($tab_filter === 'diterima') ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Berkas Diterima (<?= $total_diterima; ?>)
                </a>
                <a href="admin_dokumen.php?tab=revisi<?= !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="filter-tab-link <?= ($tab_filter === 'revisi') ? 'active' : ''; ?>">
                    <i class="fas fa-triangle-exclamation"></i> Perlu Perbaikan (<?= $total_revisi; ?>)
                </a>
            </div>

            <!-- Panel Filter Pencarian & Kategori -->
            <div class="admin-card-panel" style="margin-bottom: 20px;">
                <form method="GET" action="admin_dokumen.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab_filter); ?>">

                    <div style="flex: 1; min-width: 250px;">
                        <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari nama mahasiswa, NIM, judul naskah, atau nama file..." style="width: 100%; padding: 9px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>

                    <div style="width: 240px;">
                        <select name="jenis" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="">-- Semua Jenis Dokumen --</option>
                            <option value="File proposal tugas akhir" <?= ($jenis_filter === 'File proposal tugas akhir') ? 'selected' : ''; ?>>File proposal tugas akhir</option>
                            <option value="File laporan tugas akhir" <?= ($jenis_filter === 'File laporan tugas akhir') ? 'selected' : ''; ?>>File laporan tugas akhir</option>
                            <option value="Bukti persyaratan seminar" <?= ($jenis_filter === 'Bukti persyaratan seminar') ? 'selected' : ''; ?>>Bukti persyaratan seminar</option>
                            <option value="Dokumen persyaratan sidang" <?= ($jenis_filter === 'Dokumen persyaratan sidang') ? 'selected' : ''; ?>>Dokumen persyaratan sidang</option>
                            <option value="Dokumen pendukung lainnya" <?= ($jenis_filter === 'Dokumen pendukung lainnya') ? 'selected' : ''; ?>>Dokumen pendukung lainnya</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-admin-blue" style="padding: 9px 16px; font-size: 0.88rem;">
                        <i class="fas fa-magnifying-glass"></i> Filter
                    </button>

                    <?php if (!empty($search) || !empty($jenis_filter)): ?>
                        <a href="admin_dokumen.php?tab=<?= htmlspecialchars($tab_filter); ?>" class="btn-admin-outline" style="padding: 9px 14px; font-size: 0.88rem;">
                            <i class="fas fa-rotate-left"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabel Data Dokumen Mahasiswa -->
            <div class="admin-card-panel">
                <div class="admin-panel-header">
                    <h3><i class="fas fa-file-contract" style="color: var(--admin-blue-primary);"></i> Data Berkas Persyaratan Mahasiswa</h3>
                    <span style="font-size: 0.8rem; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 20px;">
                        Ditemukan: <?= mysqli_num_rows($res_data); ?> Berkas
                    </span>
                </div>

                <div class="admin-table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th>Mahasiswa (NIM)</th>
                                <th>Jenis & Judul Dokumen</th>
                                <th>Nama Berkas & Ukuran</th>
                                <th>Waktu Upload</th>
                                <th>Status</th>
                                <th>Catatan Admin</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($res_data) === 0): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 35px; color: #64748b;">
                                        <i class="fas fa-folder-open" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px; display: block;"></i>
                                        Tidak ada berkas dokumen mahasiswa yang sesuai dengan kriteria filter saat ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $no = 1;
                                while ($row = mysqli_fetch_assoc($res_data)): 
                                    $b_class = 'status-menunggu';
                                    $b_icon  = 'fa-clock';
                                    if ($row['status'] === 'Diterima') {
                                        $b_class = 'status-selesai';
                                        $b_icon  = 'fa-circle-check';
                                    } elseif ($row['status'] === 'Perlu perbaikan') {
                                        $b_class = 'status-batal';
                                        $b_icon  = 'fa-circle-exclamation';
                                    }
                                    $download_url = 'uploads/dokumen/' . urlencode($row['nama_file_simpan']);
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['nama_mahasiswa']); ?></div>
                                            <div style="font-size: 0.8rem; color: #64748b;">NIM: <?= htmlspecialchars($row['nim']); ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['judul_dokumen']); ?></div>
                                            <div style="font-size: 0.76rem; color: #ea580c; font-weight: 600; margin-top: 2px;">
                                                <i class="fas fa-tag"></i> <?= htmlspecialchars($row['jenis_dokumen']); ?>
                                            </div>
                                            <?php if (!empty($row['keterangan'])): ?>
                                                <div style="font-size: 0.76rem; color: #64748b; font-style: italic; margin-top: 2px;">
                                                    <?= htmlspecialchars($row['keterangan']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-family: monospace; font-size: 0.8rem; background: #f8fafc; padding: 2px 6px; border-radius: 4px; display: inline-block; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($row['nama_file_asli']); ?>">
                                                <?= htmlspecialchars($row['nama_file_asli']); ?>
                                            </div>
                                            <div style="font-size: 0.76rem; color: #64748b; margin-top: 2px;">
                                                <?= formatBytesAdmin($row['ukuran_file']); ?>
                                            </div>
                                        </td>
                                        <td style="font-size: 0.8rem; color: #475569;">
                                            <?= date('d/m/Y H:i', strtotime($row['created_at'])); ?> WIB
                                        </td>
                                        <td>
                                            <span class="admin-badge <?= $b_class; ?>" style="font-size: 0.76rem; padding: 3px 8px;">
                                                <i class="fas <?= $b_icon; ?>"></i> <?= htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.82rem; max-width: 200px;">
                                            <?php if (!empty($row['catatan_admin'])): ?>
                                                <div style="background: #fff7ed; border-left: 3px solid #ea580c; padding: 5px 8px; border-radius: 4px; color: #9a3412;">
                                                    <?= htmlspecialchars($row['catatan_admin']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <a href="<?= $download_url; ?>" target="_blank" download="<?= htmlspecialchars($row['nama_file_asli']); ?>" class="btn-admin-outline" style="padding: 5px 9px; font-size: 0.78rem;" title="Unduh Berkas">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button type="button" class="btn-admin-orange" style="padding: 5px 9px; font-size: 0.78rem;" onclick='openVerifikasiModal(<?= json_encode($row); ?>)' title="Periksa & Ubah Status">
                                                <i class="fas fa-clipboard-check"></i> Periksa
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>

        <footer class="admin-footer">
            &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Program Studi Teknologi Informasi dan Komputer (TIK), Politeknik Negeri Lhokseumawe.
        </footer>

    </div>

    <!-- Modal Dialog Verifikasi & Catatan Admin -->
    <div class="modal-overlay" id="modalVerifikasi">
        <div class="modal-box">
            <div style="background: var(--admin-navy); color: white; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.05rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-clipboard-check" style="color: #ea580c;"></i> Pemeriksaan & Verifikasi Dokumen
                </h3>
                <button type="button" onclick="closeVerifikasiModal()" style="background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" action="admin_dokumen.php" style="padding: 20px;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="dokumen_id" id="modalDokId" value="">

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                    <div style="font-size: 0.8rem; color: #64748b;">Mahasiswa:</div>
                    <div id="modalMhsNama" style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">-</div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">Judul Dokumen:</div>
                    <div id="modalDokJudul" style="font-weight: 600; color: #1e3a8a; font-size: 0.9rem;">-</div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 0.88rem;">
                        Status Pemeriksaan <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="status" id="modalStatusSelect" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
                        <option value="Menunggu pemeriksaan">Menunggu pemeriksaan</option>
                        <option value="Diterima">Diterima (Berkas Sah & Lengkap)</option>
                        <option value="Perlu perbaikan">Perlu perbaikan (Ada Revisi/Kekurangan)</option>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 0.88rem;">
                        Catatan & Arahan untuk Mahasiswa
                    </label>
                    <textarea name="catatan_admin" id="modalCatatanInput" rows="3" placeholder="Masukkan arahan atau catatan perbaikan jika ada..." style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;"></textarea>
                    <small style="color: #64748b; font-size: 0.78rem; display: block; margin-top: 4px;">Catatan ini akan dikirimkan langsung ke notifikasi mahasiswa.</small>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeVerifikasiModal()" class="btn-admin-outline" style="padding: 9px 16px;">Batal</button>
                    <button type="submit" class="btn-admin-orange" style="padding: 9px 18px;">
                        <i class="fas fa-floppy-disk"></i> Simpan Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('show');
        }

        function openVerifikasiModal(data) {
            document.getElementById('modalDokId').value = data.id;
            document.getElementById('modalMhsNama').textContent = data.nama_mahasiswa + ' (NIM: ' + data.nim + ')';
            document.getElementById('modalDokJudul').textContent = data.judul_dokumen + ' [' + data.jenis_dokumen + ']';
            document.getElementById('modalStatusSelect').value = data.status;
            document.getElementById('modalCatatanInput').value = data.catatan_admin || '';
            document.getElementById('modalVerifikasi').style.display = 'flex';
        }

        function closeVerifikasiModal() {
            document.getElementById('modalVerifikasi').style.display = 'none';
        }

        window.onclick = function(e) {
            const modal = document.getElementById('modalVerifikasi');
            if (e.target === modal) {
                closeVerifikasiModal();
            }
        };
    </script>
    <script src="notifikasi.js"></script>
</body>
</html>
