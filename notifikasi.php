<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TIK
 * FILE: notifikasi.php (Halaman Lengkap Riwayat Notifikasi Pengguna)
 * ===================================================================
 */

session_start();
require_once __DIR__ . '/koneksi.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}

$user_role = $_SESSION['role'];
$user_id   = 0;
$user_name = $_SESSION['nama'] ?? 'Pengguna';
$back_url  = 'index.php';

if ($user_role === 'mahasiswa') {
    $user_id  = (int)($_SESSION['mahasiswa_id'] ?? 0);
    $back_url = 'dashboard_mahasiswa.php';
} elseif ($user_role === 'dosen') {
    $user_id  = (int)($_SESSION['dosen_id'] ?? 0);
    $back_url = 'dashboard_dosen.php';
} elseif ($user_role === 'admin') {
    $user_id  = (int)($_SESSION['admin_id'] ?? 0);
    $back_url = 'dashboard_admin.php';
}

if ($user_id <= 0) {
    header("Location: logout.php");
    exit;
}

// Proses aksi Tandai Semua Dibaca
if (isset($_POST['action']) && $_POST['action'] === 'tandai_semua') {
    tandaiSemuaNotifikasiDibaca($conn, $user_role, $user_id);
    header("Location: notifikasi.php?pesan=semua_dibaca");
    exit;
}

// Proses aksi Tandai Satu Dibaca
if (isset($_GET['action']) && $_GET['action'] === 'baca' && isset($_GET['id'])) {
    $nid = (int)$_GET['id'];
    tandaiNotifikasiDibaca($conn, $nid, $user_role, $user_id);
    header("Location: notifikasi.php");
    exit;
}

// Filter Notifikasi: 'semua', 'belum_dibaca', 'sudah_dibaca'
$filter = $_GET['filter'] ?? 'semua';
$where_filter = "";
if ($filter === 'belum_dibaca') {
    $where_filter = " AND is_read = 0";
} elseif ($filter === 'sudah_dibaca') {
    $where_filter = " AND is_read = 1";
}

// Ambil notifikasi
$sql = "SELECT id, judul, pesan, tautan, tipe, is_read, created_at 
        FROM notifikasi 
        WHERE penerima_role = ? AND penerima_id = ? $where_filter 
        ORDER BY created_at DESC, id DESC";
$stmt = mysqli_prepare($conn, $sql);
$notifikasi_list = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "si", $user_role, $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $row['waktu_lalu'] = formatWaktuLalu($row['created_at']);
        $notifikasi_list[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Hitung total notifikasi belum dibaca
$total_unread = hitungNotifikasiBelumDibaca($conn, $user_role, $user_id);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Notifikasi - SIM-SEMSID PNL</title>
    
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Utama & Admin -->
    <link rel="stylesheet" href="style.css">
    <?php if ($user_role === 'admin'): ?>
        <link rel="stylesheet" href="admin_style.css">
    <?php endif; ?>
    
    <style>
        .notif-page-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid var(--border-color);
            padding: 24px;
            margin-bottom: 25px;
        }
        .notif-item-card {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-bottom: 12px;
            background: white;
            transition: all 0.2s ease;
        }
        .notif-item-card.unread {
            background: #fff8f5;
            border-left: 4px solid var(--primary);
        }
        .notif-item-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        .notif-icon-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }
        .icon-tipe-diterima { background: #dcfce7; color: #166534; }
        .icon-tipe-ditolak { background: #fee2e2; color: #991b1b; }
        .icon-tipe-revisi { background: #fef3c7; color: #92400e; }
        .icon-tipe-pengajuan { background: #e0f2fe; color: #075985; }
        .icon-tipe-jadwal { background: #f3e8ff; color: #6b21a8; }
        .icon-tipe-komentar { background: #fef9c3; color: #854d0e; }
        .icon-tipe-verifikasi { background: #ffedd5; color: #c2410c; }
        .icon-tipe-info { background: #e2e8f0; color: #475569; }

        .notif-filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .notif-filter-tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            color: #64748b;
            background: #f1f5f9;
            transition: all 0.2s ease;
        }
        .notif-filter-tab.active {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body class="<?= ($user_role === 'admin') ? 'admin-body' : 'dashboard-wrapper'; ?>">

    <?php if ($user_role === 'admin'): ?>
        <?php include 'admin_sidebar.php'; ?>
        <div class="admin-main-wrapper">
            <header class="admin-topbar">
                <div class="admin-topbar-left">
                    <a href="<?= $back_url; ?>" class="btn-admin-outline" style="padding: 6px 12px; font-size: 0.85rem;">
                        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                    <div class="admin-topbar-title">
                        <h1>Pusat Notifikasi Admin</h1>
                        <span>Semua notifikasi aktivitas pendaftaran, jadwal, dan bimbingan</span>
                    </div>
                </div>
                <div class="admin-topbar-right">
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="tandai_semua">
                        <button type="submit" class="btn-admin-orange" style="font-size: 0.82rem; padding: 7px 14px;" <?= ($total_unread === 0) ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : ''; ?>>
                            <i class="fas fa-check-double"></i> Tandai Semua Sudah Dibaca
                        </button>
                    </form>
                </div>
            </header>
            <main class="admin-content-body">
    <?php else: ?>
        <header class="dashboard-nav">
            <div class="container dashboard-nav-container">
                <a href="<?= $back_url; ?>" class="logo-area">
                    <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="logo-text">
                        <h1>SIM-SEMSID</h1>
                        <span>POLITEKNIK NEGERI LHOKSEUMAWE</span>
                    </div>
                </a>
                <div class="user-profile-menu">
                    <div class="user-badge-info">
                        <div class="user-name"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($user_name); ?></div>
                        <div class="user-role"><?= ucfirst($user_role); ?></div>
                    </div>
                    <a href="<?= $back_url; ?>" class="btn btn-outline-admin" style="font-size: 0.85rem; padding: 7px 14px;">
                        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </header>
        <main class="dashboard-main">
            <div class="container">
    <?php endif; ?>

        <!-- Konten Halaman Notifikasi -->
        <div class="notif-page-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h2 style="font-size: 1.35rem; color: var(--secondary); margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-bell" style="color: var(--primary);"></i> Notifikasi Saya
                        <?php if ($total_unread > 0): ?>
                            <span class="badge" style="background: #ef4444; color: white; border-radius: 50px; font-size: 0.8rem; padding: 3px 9px;">
                                <?= $total_unread; ?> Belum Dibaca
                            </span>
                        <?php endif; ?>
                    </h2>
                    <p style="color: #64748b; font-size: 0.88rem; margin: 5px 0 0 0;">
                        Pemberitahuan otomatis terkait status pengajuan judul, bimbingan, jadwal ujian, dan verifikasi berkas.
                    </p>
                </div>

                <?php if ($user_role !== 'admin'): ?>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="tandai_semua">
                        <button type="submit" class="btn btn-primary" style="font-size: 0.85rem; padding: 8px 16px;" <?= ($total_unread === 0) ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : ''; ?>>
                            <i class="fas fa-check-double"></i> Tandai Semua Sudah Dibaca
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Filter Tabs -->
            <div class="notif-filter-tabs">
                <a href="notifikasi.php?filter=semua" class="notif-filter-tab <?= ($filter === 'semua') ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Semua
                </a>
                <a href="notifikasi.php?filter=belum_dibaca" class="notif-filter-tab <?= ($filter === 'belum_dibaca') ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i> Belum Dibaca (<?= $total_unread; ?>)
                </a>
                <a href="notifikasi.php?filter=sudah_dibaca" class="notif-filter-tab <?= ($filter === 'sudah_dibaca') ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open"></i> Sudah Dibaca
                </a>
            </div>

            <!-- Daftar Notifikasi -->
            <?php if (empty($notifikasi_list)): ?>
                <div style="text-align: center; padding: 50px 20px; color: #94a3b8;">
                    <i class="fas fa-bell-slash" style="font-size: 3.5rem; margin-bottom: 15px; color: #cbd5e1;"></i>
                    <h3 style="font-size: 1.15rem; color: #475569; margin-bottom: 6px;">Belum Ada Notifikasi</h3>
                    <p style="font-size: 0.88rem; margin: 0;">Saat ini tidak ada notifikasi yang sesuai dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifikasi_list as $n): ?>
                    <?php
                        $icon_class = 'fas fa-info-circle';
                        $icon_bg = 'icon-tipe-info';
                        switch ($n['tipe']) {
                            case 'diterima':
                                $icon_class = 'fas fa-circle-check';
                                $icon_bg = 'icon-tipe-diterima';
                                break;
                            case 'ditolak':
                                $icon_class = 'fas fa-circle-xmark';
                                $icon_bg = 'icon-tipe-ditolak';
                                break;
                            case 'revisi':
                                $icon_class = 'fas fa-pen-to-square';
                                $icon_bg = 'icon-tipe-revisi';
                                break;
                            case 'pengajuan':
                                $icon_class = 'fas fa-lightbulb';
                                $icon_bg = 'icon-tipe-pengajuan';
                                break;
                            case 'jadwal':
                                $icon_class = 'fas fa-calendar-check';
                                $icon_bg = 'icon-tipe-jadwal';
                                break;
                            case 'verifikasi':
                                $icon_class = 'fas fa-shield-check';
                                $icon_bg = 'icon-tipe-verifikasi';
                                break;
                            case 'komentar':
                                $icon_class = 'fas fa-comments';
                                $icon_bg = 'icon-tipe-komentar';
                                break;
                        }
                    ?>
                    <div class="notif-item-card <?= ((int)$n['is_read'] === 0) ? 'unread' : ''; ?>">
                        <div class="notif-icon-circle <?= $icon_bg; ?>">
                            <i class="<?= $icon_class; ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 6px;">
                                <h4 style="margin: 0; font-size: 1rem; color: var(--secondary); font-weight: 700;">
                                    <?= htmlspecialchars($n['judul']); ?>
                                </h4>
                                <span style="font-size: 0.78rem; color: #94a3b8; white-space: nowrap;">
                                    <i class="far fa-clock"></i> <?= htmlspecialchars($n['waktu_lalu']); ?>
                                </span>
                            </div>
                            <p style="margin: 0 0 10px 0; font-size: 0.88rem; color: #475569; line-height: 1.5;">
                                <?= nl2br(htmlspecialchars($n['pesan'])); ?>
                            </p>
                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <?php if (!empty($n['tautan'])): ?>
                                    <a href="api_notifikasi.php?action=redirect&id=<?= $n['id']; ?>" class="btn btn-primary" style="font-size: 0.8rem; padding: 5px 12px; text-decoration: none;">
                                        <i class="fas fa-arrow-up-right-from-square"></i> Buka Halaman Terkait
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ((int)$n['is_read'] === 0): ?>
                                    <a href="notifikasi.php?action=baca&id=<?= $n['id']; ?>" style="font-size: 0.8rem; color: #64748b; text-decoration: none; padding: 4px 8px; border-radius: 4px; background: #f1f5f9;">
                                        <i class="fas fa-check"></i> Tandai Sudah Dibaca
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: #10b981; font-weight: 600;">
                                        <i class="fas fa-check-circle"></i> Sudah dibaca
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>

    <?php if ($user_role === 'admin'): ?>
            </main>
        </div>
    <?php else: ?>
            </div>
        </main>
    <?php endif; ?>

</body>
</html>
