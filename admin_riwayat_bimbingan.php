<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - JURUSAN TIK
 * FILE: admin_riwayat_bimbingan.php (Riwayat Bimbingan & Komunikasi Akademik)
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

// Filter Pencarian
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$sql = "
    SELECT rb.*, 
           m.nama AS nama_mahasiswa, m.nim, m.program_studi,
           d.nama_dosen, d.gelar_akademik, d.nidn
    FROM riwayat_bimbingan rb
    JOIN mahasiswa m ON rb.mahasiswa_id = m.id
    JOIN dosen d ON rb.dosen_id = d.id
    WHERE 1=1
";
$params = [];
$types  = "";

if (!empty($search)) {
    $sql .= " AND (m.nama LIKE ? OR m.nim LIKE ? OR d.nama_dosen LIKE ? OR rb.materi_bimbingan LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= "ssss";
}

if (!empty($status_filter)) {
    $sql .= " AND rb.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY rb.tanggal_bimbingan DESC, rb.waktu_pengiriman DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res_bimbingan = mysqli_stmt_get_result($stmt);

// STATISTIK RINGKASAN
$q_stat = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Disetujui' OR status = 'Selesai' THEN 1 ELSE 0 END) AS total_acc,
        SUM(CASE WHEN status = 'Perlu Revisi' THEN 1 ELSE 0 END) AS total_revisi,
        SUM(CASE WHEN status = 'Menunggu' OR status = 'Sedang Diperiksa' THEN 1 ELSE 0 END) AS total_proses
    FROM riwayat_bimbingan
");
$stat_bimb = mysqli_fetch_assoc($q_stat);
$total_all    = (int)($stat_bimb['total'] ?? 0);
$total_acc    = (int)($stat_bimb['total_acc'] ?? 0);
$total_revisi = (int)($stat_bimb['total_revisi'] ?? 0);
$total_proses = (int)($stat_bimb['total_proses'] ?? 0);

$active_menu = 'riwayat';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Bimbingan & Komunikasi - Admin SIM-SEMSID</title>

    <!-- FontAwesome & CSS Admin -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_style.css">
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
                    <h1>Riwayat Bimbingan & Komunikasi</h1>
                    <span>Pemantauan catatan konsultasi, bimbingan, dan perkembangan tugas akhir mahasiswa</span>
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

            <nav style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">
                <a href="dashboard_admin.php" style="color: var(--admin-blue-primary); text-decoration: none;"><i class="fas fa-home"></i> Dashboard</a>
                <span style="margin: 0 8px;">/</span>
                <span style="color: #1e293b; font-weight: 600;">Riwayat & Komunikasi</span>
            </nav>

            <!-- Kartu Statistik -->
            <div class="admin-stats-grid" style="margin-bottom: 25px;">
                <div class="admin-stat-card">
                    <div class="admin-stat-icon stat-icon-blue">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value"><?= $total_all; ?></div>
                        <div class="admin-stat-label">Total Sesi Bimbingan</div>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-icon stat-icon-green">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value" style="color: #16a34a;"><?= $total_acc; ?></div>
                        <div class="admin-stat-label">Disetujui / Selesai</div>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-icon stat-icon-amber">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value" style="color: #d97706;"><?= $total_proses; ?></div>
                        <div class="admin-stat-label">Sedang Diproses</div>
                    </div>
                </div>

                <div class="admin-stat-card">
                    <div class="admin-stat-icon stat-icon-red">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <div class="admin-stat-details">
                        <div class="admin-stat-value" style="color: #dc2626;"><?= $total_revisi; ?></div>
                        <div class="admin-stat-label">Perlu Revisi</div>
                    </div>
                </div>
            </div>

            <!-- Filter Pencarian -->
            <div class="admin-card-panel" style="margin-bottom: 20px;">
                <form method="GET" action="admin_riwayat_bimbingan.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                    <div style="flex: 1; min-width: 250px;">
                        <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari nama mahasiswa, NIM, dosen pembimbing, atau materi bimbingan..." style="width: 100%; padding: 9px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>

                    <div style="width: 200px;">
                        <select name="status" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="">-- Semua Status --</option>
                            <option value="Menunggu" <?= ($status_filter === 'Menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="Sedang Diperiksa" <?= ($status_filter === 'Sedang Diperiksa') ? 'selected' : ''; ?>>Sedang Diperiksa</option>
                            <option value="Perlu Revisi" <?= ($status_filter === 'Perlu Revisi') ? 'selected' : ''; ?>>Perlu Revisi</option>
                            <option value="Disetujui" <?= ($status_filter === 'Disetujui') ? 'selected' : ''; ?>>Disetujui</option>
                            <option value="Selesai" <?= ($status_filter === 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-admin-blue" style="padding: 9px 16px; font-size: 0.88rem;">
                        <i class="fas fa-magnifying-glass"></i> Cari
                    </button>

                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        <a href="admin_riwayat_bimbingan.php" class="btn-admin-outline" style="padding: 9px 14px; font-size: 0.88rem;">
                            <i class="fas fa-rotate-left"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabel Riwayat Bimbingan -->
            <div class="admin-card-panel">
                <div class="admin-panel-header">
                    <h3><i class="fas fa-book-reader" style="color: var(--admin-blue-primary);"></i> Catatan Riwayat Bimbingan & Arahan Dosen</h3>
                    <span style="font-size: 0.8rem; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 20px;">
                        Total: <?= mysqli_num_rows($res_bimbingan); ?> Catatan
                    </span>
                </div>

                <div class="admin-table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th>Mahasiswa (NIM)</th>
                                <th>Dosen Pembimbing</th>
                                <th>Tanggal</th>
                                <th>Materi & Progres</th>
                                <th>Arahan / Komentar Dosen</th>
                                <th>Status</th>
                                <th style="text-align: center;">Lampiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($res_bimbingan) === 0): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 35px; color: #64748b;">
                                        <i class="fas fa-comments" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px; display: block;"></i>
                                        Belum ada riwayat catatan bimbingan yang tercatat di sistem.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $no = 1;
                                while ($b = mysqli_fetch_assoc($res_bimbingan)): 
                                    $s_class = 'status-menunggu';
                                    if ($b['status'] === 'Disetujui' || $b['status'] === 'Selesai') $s_class = 'status-selesai';
                                    elseif ($b['status'] === 'Perlu Revisi') $s_class = 'status-batal';
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($b['nama_mahasiswa']); ?></div>
                                            <div style="font-size: 0.8rem; color: #64748b;">NIM: <?= htmlspecialchars($b['nim']); ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($b['nama_dosen'] . ($b['gelar_akademik'] ? ', ' . $b['gelar_akademik'] : '')); ?></div>
                                            <div style="font-size: 0.78rem; color: #64748b;">NIDN: <?= htmlspecialchars($b['nidn']); ?></div>
                                        </td>
                                        <td style="font-size: 0.82rem; color: #475569; white-space: nowrap;">
                                            <i class="fas fa-calendar-day" style="color: #ea580c;"></i> <?= date('d/m/Y', strtotime($b['tanggal_bimbingan'])); ?>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <div style="font-weight: 600; color: #0f172a; font-size: 0.88rem;"><?= htmlspecialchars($b['materi_bimbingan']); ?></div>
                                            <?php if (!empty($b['hasil_perkembangan'])): ?>
                                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 3px;">
                                                    <?= nl2br(htmlspecialchars(substr($b['hasil_perkembangan'], 0, 120))) . (strlen($b['hasil_perkembangan']) > 120 ? '...' : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 250px; font-size: 0.82rem;">
                                            <?php if (!empty($b['komentar_dosen'])): ?>
                                                <div style="background: #eff6ff; border-left: 3px solid #3b82f6; padding: 6px 10px; border-radius: 4px; color: #1e40af;">
                                                    <?= nl2br(htmlspecialchars($b['komentar_dosen'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-style: italic;">Belum ada tanggapan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="admin-badge <?= $s_class; ?>" style="font-size: 0.76rem; padding: 3px 8px;">
                                                <?= htmlspecialchars($b['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <?php if (!empty($b['file_lampiran'])): ?>
                                                <a href="uploads/revisi/<?= urlencode($b['file_lampiran']); ?>" target="_blank" class="btn-admin-outline" style="padding: 4px 8px; font-size: 0.78rem;" title="Lihat Lampiran">
                                                    <i class="fas fa-file-arrow-down"></i> Berkas
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.8rem;">-</span>
                                            <?php endif; ?>
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

    <script>
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('show');
        }
    </script>
    <script src="notifikasi.js"></script>
</body>
</html>
