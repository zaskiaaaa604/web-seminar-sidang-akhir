<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TIK
 * FILE: bimbingan_mahasiswa.php (Riwayat Bimbingan Mahasiswa)
 * ===================================================================
 */

session_start();
require_once __DIR__ . '/koneksi.php';

// Validasi Hak Akses Khusus Mahasiswa
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'mahasiswa') {
    header("Location: login_mahasiswa.php");
    exit;
}

$mahasiswa_id = (int)($_SESSION['mahasiswa_id'] ?? 0);
$nama         = $_SESSION['nama'] ?? 'Mahasiswa';
$nim          = $_SESSION['nim'] ?? '';
$prodi        = $_SESSION['program_studi'] ?? 'Jurusan Teknologi Informasi dan Komputer (TIK)';

if ($mahasiswa_id <= 0) {
    header("Location: logout.php");
    exit;
}

$success_message = '';
$error_message   = '';

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah_sukses') {
        $success_message = "Catatan bimbingan baru berhasil disimpan dan diteruskan ke Dosen Pembimbing!";
    } elseif ($_GET['pesan'] === 'format_tidak_valid') {
        $error_message = "Format file tidak didukung! Format yang diperbolehkan: PDF, DOC, DOCX, ZIP, RAR, JPG, PNG.";
    } elseif ($_GET['pesan'] === 'ukuran_terlalu_besar') {
        $error_message = "Ukuran file lampiran terlalu besar! Maksimal ukuran file adalah 10 MB.";
    }
}

// ===================================================================
// 1. CARI DATA DOSEN PEMBIMBING YANG SUDAH DITETAPKAN
// ===================================================================
// Prioritas 1: Dari pengajuan_judul berstatus 'Diterima'
$dosen_pembimbing_list = [];

$q_judul_diterima = mysqli_query($conn, "
    SELECT d.id AS dosen_id, d.nidn, d.nama_dosen, d.gelar_akademik, d.email, d.no_hp, pj.judul, 'Pengajuan Judul Diterima' AS sumber
    FROM pengajuan_judul pj
    JOIN dosen d ON pj.dosen_id = d.id
    WHERE pj.mahasiswa_id = $mahasiswa_id AND pj.status = 'Diterima'
    ORDER BY pj.id DESC
");
while ($r = mysqli_fetch_assoc($q_judul_diterima)) {
    $dosen_pembimbing_list[$r['dosen_id']] = $r;
}

// Prioritas 2: Dari pendaftaran_seminar_sidang
$q_seminar = mysqli_query($conn, "
    SELECT d.id AS dosen_id, d.nidn, d.nama_dosen, d.gelar_akademik, d.email, d.no_hp, p.judul, p.jenis AS sumber
    FROM pendaftaran_seminar_sidang p
    JOIN dosen d ON p.dosen_pembimbing_id = d.id
    WHERE p.mahasiswa_id = $mahasiswa_id
    ORDER BY p.id DESC
");
while ($r = mysqli_fetch_assoc($q_seminar)) {
    if (!isset($dosen_pembimbing_list[$r['dosen_id']])) {
        $dosen_pembimbing_list[$r['dosen_id']] = $r;
    }
}

// Cadangan: jika belum ada judul disetujui, ambil dosen dari pengajuan judul yang pernah diajukan
if (empty($dosen_pembimbing_list)) {
    $q_judul_semua = mysqli_query($conn, "
        SELECT d.id AS dosen_id, d.nidn, d.nama_dosen, d.gelar_akademik, d.email, d.no_hp, pj.judul, 'Usulan Judul' AS sumber
        FROM pengajuan_judul pj
        JOIN dosen d ON pj.dosen_id = d.id
        WHERE pj.mahasiswa_id = $mahasiswa_id
        ORDER BY pj.id DESC LIMIT 1
    ");
    while ($r = mysqli_fetch_assoc($q_judul_semua)) {
        $dosen_pembimbing_list[$r['dosen_id']] = $r;
    }
}

// Tentukan dosen pembimbing utama
$pembimbing_utama = !empty($dosen_pembimbing_list) ? reset($dosen_pembimbing_list) : null;

// Direktori Unggahan Dokumen Bimbingan
$upload_dir = __DIR__ . '/uploads/bimbingan/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ===================================================================
// 2. PROSES TAMBAH CATATAN BIMBINGAN BARU
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_bimbingan') {
    $dosen_id           = (int)($_POST['dosen_id'] ?? 0);
    $tanggal_bimbingan  = trim($_POST['tanggal_bimbingan'] ?? date('Y-m-d'));
    $materi_bimbingan   = trim($_POST['materi_bimbingan'] ?? '');
    $hasil_perkembangan = trim($_POST['hasil_perkembangan'] ?? '');

    if ($dosen_id <= 0 || empty($materi_bimbingan) || empty($hasil_perkembangan) || empty($tanggal_bimbingan)) {
        $error_message = "Dosen pembimbing, tanggal bimbingan, materi bimbingan, dan perkembangan penelitian wajib diisi!";
    } else {
        $file_lampiran_name = null;
        $upload_ok = true;

        // Validasi dan Unggah File Lampiran (Opsional tapi didukung)
        if (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file_lampiran'];
            $allowed_exts = ['pdf', 'doc', 'docx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_exts)) {
                $upload_ok = false;
                $error_message = "Format file tidak didukung! Format yang diperbolehkan: PDF, DOC, DOCX, ZIP, RAR, JPG, PNG.";
            } elseif ($file['size'] > 10 * 1024 * 1024) { // Maksimal 10 MB
                $upload_ok = false;
                $error_message = "Ukuran dokumen maksimal 10 MB!";
            } else {
                $file_lampiran_name = 'bimbingan_' . $mahasiswa_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $upload_dir . $file_lampiran_name)) {
                    $upload_ok = false;
                    $error_message = "Gagal mengunggah file lampiran. Silakan coba lagi.";
                }
            }
        }

        if ($upload_ok) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO riwayat_bimbingan 
                (mahasiswa_id, dosen_id, tanggal_bimbingan, materi_bimbingan, hasil_perkembangan, file_lampiran, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Menunggu')
            ");

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iissss", $mahasiswa_id, $dosen_id, $tanggal_bimbingan, $materi_bimbingan, $hasil_perkembangan, $file_lampiran_name);
                if (mysqli_stmt_execute($stmt)) {
                    $new_bimbingan_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);

                    // NOTIFIKASI OTOMATIS: Dosen Pembimbing menerima notifikasi catatan bimbingan baru
                    kirimNotifikasi(
                        $conn,
                        'dosen',
                        $dosen_id,
                        'Catatan Bimbingan Baru dari Mahasiswa',
                        "Mahasiswa $nama (NIM: $nim) mengirimkan catatan bimbingan baru: \"$materi_bimbingan\".",
                        'bimbingan_dosen.php?mahasiswa_id=' . $mahasiswa_id,
                        'pengajuan'
                    );

                    header("Location: bimbingan_mahasiswa.php?pesan=tambah_sukses");
                    exit;
                } else {
                    $error_message = "Gagal menyimpan catatan bimbingan ke database: " . mysqli_error($conn);
                }
            } else {
                $error_message = "Terjadi gangguan pada query database.";
            }
        }
    }
}

// ===================================================================
// 3. MENGAMBIL SELURUH RIWAYAT BIMBINGAN KHUSUS MAHASISWA INI
// ===================================================================
$riwayat_list = [];
$stmt_riwayat = mysqli_prepare($conn, "
    SELECT rb.*, d.nama_dosen, d.gelar_akademik, d.nidn
    FROM riwayat_bimbingan rb
    JOIN dosen d ON rb.dosen_id = d.id
    WHERE rb.mahasiswa_id = ?
    ORDER BY rb.tanggal_bimbingan DESC, rb.id DESC
");

if ($stmt_riwayat) {
    mysqli_stmt_bind_param($stmt_riwayat, "i", $mahasiswa_id);
    mysqli_stmt_execute($stmt_riwayat);
    $res = mysqli_stmt_get_result($stmt_riwayat);
    while ($row = mysqli_fetch_assoc($res)) {
        $riwayat_list[] = $row;
    }
    mysqli_stmt_close($stmt_riwayat);
}

// Statistik Bimbingan Mahasiswa
$total_bimbingan = count($riwayat_list);
$bimbingan_menunggu   = 0;
$bimbingan_diperiksa  = 0;
$bimbingan_revisi     = 0;
$bimbingan_disetujui  = 0;
$bimbingan_selesai    = 0;

foreach ($riwayat_list as $b) {
    if ($b['status'] === 'Menunggu') $bimbingan_menunggu++;
    elseif ($b['status'] === 'Sedang Diperiksa') $bimbingan_diperiksa++;
    elseif ($b['status'] === 'Perlu Revisi') $bimbingan_revisi++;
    elseif ($b['status'] === 'Disetujui') $bimbingan_disetujui++;
    elseif ($b['status'] === 'Selesai') $bimbingan_selesai++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Bimbingan Mahasiswa - <?= htmlspecialchars($nama); ?> | SIM-SEMSID</title>
    
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Utama Website SIM-SEMSID -->
    <link rel="stylesheet" href="style.css">

    <style>
        .badge-status-menunggu { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .badge-status-sedang-diperiksa { background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-status-perlu-revisi { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-status-disetujui { background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-status-selesai { background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

        .timeline-container {
            position: relative;
            padding-left: 28px;
            border-left: 2px dashed #cbd5e1;
            margin-left: 10px;
            margin-top: 20px;
        }

        .timeline-card {
            position: relative;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            transition: var(--transition);
        }

        .timeline-card:hover {
            border-color: var(--primary-border);
            box-shadow: 0 8px 24px rgba(234, 88, 12, 0.08);
        }

        .timeline-node {
            position: absolute;
            left: -41px;
            top: 22px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .comment-dosen-box {
            background: #fff8f5;
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            border-top: 1px solid #fed7aa;
            border-right: 1px solid #fed7aa;
            border-bottom: 1px solid #fed7aa;
        }

        .file-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #334155;
            text-decoration: none;
            transition: var(--transition);
        }

        .file-pill:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Modal Tambah Bimbingan */
        .modal-custom {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-custom.show {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: var(--radius-md);
            max-width: 620px;
            width: 100%;
            border: 1px solid var(--primary-border);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: fadeInModal 0.2s ease-out;
        }

        @keyframes fadeInModal {
            from { opacity: 0; transform: translateY(-15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header-orange {
            background: linear-gradient(135deg, var(--primary), #fb923c);
            color: white;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
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

    <!-- Konten Utama Riwayat Bimbingan -->
    <main class="dashboard-main">
        <div class="container">

            <!-- Navigasi Menu Mahasiswa -->
            <div style="display: flex; gap: 10px; margin-bottom: 22px; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
                <a href="dashboard_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-tachometer-alt"></i> Dashboard Utama
                </a>
                <a href="pengajuan_judul_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-lightbulb"></i> Pengajuan Judul
                </a>
                <a href="bimbingan_mahasiswa.php" class="btn btn-primary" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-book-reader"></i> Riwayat Bimbingan (<?= $total_bimbingan; ?>)
                </a>
                <a href="dokumen_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-file-arrow-up"></i> Upload Dokumen
                </a>
            </div>

            <!-- Pesan Notifikasi Sukses / Gagal -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <i class="fas fa-circle-check"></i> <div><?= $success_message; ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <i class="fas fa-circle-exclamation"></i> <div><?= $error_message; ?></div>
                </div>
            <?php endif; ?>

            <!-- Panel Dosen Pembimbing yang Ditetapkan -->
            <div class="dashboard-panel" style="margin-bottom: 24px;">
                <div class="panel-header">
                    <h3><i class="fas fa-user-tie"></i> Dosen Pembimbing Ditetapkan</h3>
                    <?php if ($pembimbing_utama): ?>
                        <span class="badge" style="background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700;">
                            <i class="fas fa-check-circle"></i> Pembimbing Aktif
                        </span>
                    <?php else: ?>
                        <span class="badge" style="background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700;">
                            <i class="fas fa-clock"></i> Belum Ditetapkan
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($pembimbing_utama): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; background: #fffaf5; border: 1px solid var(--primary-border); border-radius: 10px; padding: 20px;">
                        <div>
                            <span style="font-size: 0.78rem; color: #9a3412; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Dosen Pembimbing Tugas Akhir</span>
                            <h4 style="margin: 4px 0 6px 0; font-size: 1.15rem; color: var(--secondary);">
                                <i class="fas fa-chalkboard-teacher" style="color: var(--primary);"></i> <?= htmlspecialchars($pembimbing_utama['nama_dosen']); ?>, <?= htmlspecialchars($pembimbing_utama['gelar_akademik']); ?>
                            </h4>
                            <div style="font-size: 0.85rem; color: #64748b; display: flex; gap: 15px; flex-wrap: wrap;">
                                <span><i class="fas fa-id-card"></i> NIDN: <?= htmlspecialchars($pembimbing_utama['nidn']); ?></span>
                                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($pembimbing_utama['email']); ?></span>
                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($pembimbing_utama['no_hp']); ?></span>
                            </div>
                        </div>
                        <div>
                            <span style="font-size: 0.78rem; color: #9a3412; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Judul Penelitian Yang Disetujui</span>
                            <p style="margin: 4px 0 0 0; font-size: 0.92rem; color: #1e293b; font-weight: 600; line-height: 1.45;">
                                "<?= htmlspecialchars($pembimbing_utama['judul'] ?? 'Belum ada judul'); ?>"
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 10px; padding: 20px; text-align: center; color: #64748b;">
                        <i class="fas fa-info-circle" style="font-size: 2rem; color: #94a3b8; margin-bottom: 8px;"></i>
                        <p style="margin: 0; font-size: 0.9rem;">
                            Anda belum memiliki Dosen Pembimbing yang resmi disetujui. Pastikan pengajuan judul Anda telah diterima atau Anda telah terdaftar dalam seminar/sidang.
                        </p>
                        <div style="margin-top: 12px;">
                            <a href="pengajuan_judul_mahasiswa.php" class="btn btn-primary" style="font-size: 0.82rem; padding: 6px 14px;">
                                <i class="fas fa-lightbulb"></i> Buka Menu Pengajuan Judul
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kartu Ringkasan Statistik Bimbingan -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 10px; padding: 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: #fff7ed; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-book-bookmark"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">Total Bimbingan</span>
                        <h4 style="margin: 0; font-size: 1.4rem; color: var(--secondary); font-weight: 800;"><?= $total_bimbingan; ?></h4>
                    </div>
                </div>

                <div style="background: white; border: 1px solid var(--border-color); border-radius: 10px; padding: 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">Menunggu / Diperiksa</span>
                        <h4 style="margin: 0; font-size: 1.4rem; color: #2563eb; font-weight: 800;"><?= ($bimbingan_menunggu + $bimbingan_diperiksa); ?></h4>
                    </div>
                </div>

                <div style="background: white; border: 1px solid var(--border-color); border-radius: 10px; padding: 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: #fffbeb; color: #b45309; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-pen-ruler"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">Perlu Revisi</span>
                        <h4 style="margin: 0; font-size: 1.4rem; color: #b45309; font-weight: 800;"><?= $bimbingan_revisi; ?></h4>
                    </div>
                </div>

                <div style="background: white; border: 1px solid var(--border-color); border-radius: 10px; padding: 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">Disetujui / Selesai</span>
                        <h4 style="margin: 0; font-size: 1.4rem; color: #16a34a; font-weight: 800;"><?= ($bimbingan_disetujui + $bimbingan_selesai); ?></h4>
                    </div>
                </div>
            </div>

            <!-- Panel Riwayat Bimbingan Utama -->
            <div class="dashboard-panel">
                <div class="panel-header" style="flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3><i class="fas fa-list-check"></i> Daftar Logbook & Riwayat Bimbingan</h3>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">Urutan berdasarkan tanggal bimbingan terbaru</span>
                    </div>

                    <?php if ($pembimbing_utama): ?>
                        <button type="button" class="btn btn-primary" onclick="bukaModalBimbingan();" style="font-size: 0.88rem; padding: 8px 16px;">
                            <i class="fas fa-plus-circle"></i> Tambah Catatan Bimbingan
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" onclick="alert('Anda harus memiliki Dosen Pembimbing yang telah disetujui terlebih dahulu untuk membuat catatan bimbingan.');" style="font-size: 0.88rem; padding: 8px 16px; opacity: 0.7;">
                            <i class="fas fa-plus-circle"></i> Tambah Catatan Bimbingan
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($riwayat_list)): ?>
                    <div style="text-align: center; padding: 50px 20px; color: #94a3b8;">
                        <i class="fas fa-book-open" style="font-size: 3.5rem; margin-bottom: 15px; color: #cbd5e1;"></i>
                        <h4 style="font-size: 1.15rem; color: #475569; margin-bottom: 6px;">Belum Ada Riwayat Bimbingan</h4>
                        <p style="font-size: 0.88rem; margin: 0 0 15px 0;">
                            Klik tombol <strong>Tambah Catatan Bimbingan</strong> di atas untuk mencatat materi dan perkembangan skripsi Anda.
                        </p>
                        <?php if ($pembimbing_utama): ?>
                            <button type="button" class="btn btn-primary" onclick="bukaModalBimbingan();" style="font-size: 0.85rem; padding: 8px 16px;">
                                <i class="fas fa-plus"></i> Buat Catatan Bimbingan Pertama
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="timeline-container">
                        <?php $nomor_bimbingan = $total_bimbingan; ?>
                        <?php foreach ($riwayat_list as $row): ?>
                            <?php
                                $status_badge_class = 'badge-status-menunggu';
                                if ($row['status'] === 'Sedang Diperiksa') $status_badge_class = 'badge-status-sedang-diperiksa';
                                elseif ($row['status'] === 'Perlu Revisi') $status_badge_class = 'badge-status-perlu-revisi';
                                elseif ($row['status'] === 'Disetujui') $status_badge_class = 'badge-status-disetujui';
                                elseif ($row['status'] === 'Selesai') $status_badge_class = 'badge-status-selesai';
                            ?>
                            <div class="timeline-card" id="bimbingan_<?= $row['id']; ?>">
                                <div class="timeline-node" title="Sesi Bimbingan Ke-<?= $nomor_bimbingan; ?>">
                                    <?= $nomor_bimbingan; ?>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                            <span style="font-size: 0.78rem; font-weight: 700; color: var(--primary); text-transform: uppercase;">
                                                <i class="fas fa-calendar-day"></i> Bimbingan Ke-<?= $nomor_bimbingan--; ?> &bull; <?= date('d F Y', strtotime($row['tanggal_bimbingan'])); ?>
                                            </span>
                                        </div>
                                        <h4 style="margin: 0; font-size: 1.1rem; color: var(--secondary); font-weight: 700;">
                                            <?= htmlspecialchars($row['materi_bimbingan']); ?>
                                        </h4>
                                        <span style="font-size: 0.82rem; color: #64748b;">
                                            Dosen Pembimbing: <strong><?= htmlspecialchars($row['nama_dosen']); ?>, <?= htmlspecialchars($row['gelar_akademik']); ?></strong>
                                        </span>
                                    </div>

                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="badge <?= $status_badge_class; ?>" style="padding: 5px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700;">
                                            <?= htmlspecialchars($row['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Hasil & Perkembangan yang Dikerjakan Mahasiswa -->
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 14px;">
                                    <div style="font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase;">
                                        <i class="fas fa-tasks"></i> Hasil & Perkembangan Penelitian:
                                    </div>
                                    <p style="margin: 0; font-size: 0.88rem; color: #1e293b; line-height: 1.55; white-space: pre-line;">
                                        <?= htmlspecialchars($row['hasil_perkembangan']); ?>
                                    </p>
                                </div>

                                <!-- Dokumen Lampiran Hasil Bimbingan (Jika Ada) -->
                                <?php if (!empty($row['file_lampiran'])): ?>
                                    <div style="margin-bottom: 14px;">
                                        <span style="font-size: 0.8rem; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                            <i class="fas fa-paperclip"></i> Dokumen Lampiran / Revisi:
                                        </span>
                                        <a href="uploads/bimbingan/<?= htmlspecialchars($row['file_lampiran']); ?>" target="_blank" class="file-pill" title="Unduh Dokumen Hasil Bimbingan">
                                            <i class="fas fa-file-arrow-down" style="color: var(--primary);"></i>
                                            <span><?= htmlspecialchars($row['file_lampiran']); ?></span>
                                            <span style="font-size: 0.72rem; color: var(--primary); font-weight: 700;">(Lihat / Unduh)</span>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Tanggapan & Arahan dari Dosen Pembimbing -->
                                <?php if (!empty($row['komentar_dosen'])): ?>
                                    <div class="comment-dosen-box">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                                            <div style="font-size: 0.82rem; font-weight: 700; color: #9a3412;">
                                                <i class="fas fa-comment-dots"></i> Komentar & Arahan Dosen (<?= htmlspecialchars($row['nama_dosen']); ?>):
                                            </div>
                                            <?php if (!empty($row['waktu_tanggapan'])): ?>
                                                <span style="font-size: 0.75rem; color: #9a3412;">
                                                    <i class="far fa-clock"></i> <?= date('d M Y, H:i', strtotime($row['waktu_tanggapan'])); ?> WIB
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="margin: 0; font-size: 0.9rem; color: #7c2d12; line-height: 1.5; white-space: pre-line;">
                                            <?= htmlspecialchars($row['komentar_dosen']); ?>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div style="background: #fffbeb; border: 1px dashed #fde68a; border-radius: 8px; padding: 10px 14px; font-size: 0.82rem; color: #92400e; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-hourglass-half"></i>
                                        <span>Menunggu catatan dan arahan bimbingan dari Dosen Pembimbing.</span>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; pt: 10px; border-top: 1px solid #f1f5f9;">
                                    <span style="font-size: 0.75rem; color: #94a3b8;">
                                        <i class="far fa-clock"></i> Dikirim: <?= date('d M Y, H:i', strtotime($row['waktu_pengiriman'])); ?> WIB
                                    </span>
                                    <button type="button" class="btn btn-outline-admin" onclick="lihatDetailBimbingan(<?= htmlspecialchars(json_encode($row)); ?>);" style="font-size: 0.8rem; padding: 5px 12px;">
                                        <i class="fas fa-eye"></i> Lihat Detail
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </main>

    <!-- ===================================================================
         MODAL TAMBAH CATATAN BIMBINGAN BARU
         =================================================================== -->
    <div class="modal-custom" id="modalBimbingan">
        <div class="modal-box">
            <div class="modal-header-orange">
                <h3 style="margin: 0; font-size: 1.15rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-book-reader"></i> Formulir Catatan Bimbingan Baru
                </h3>
                <button type="button" onclick="tutupModalBimbingan();" style="background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>

            <form action="bimbingan_mahasiswa.php" method="POST" enctype="multipart/form-data" style="padding: 24px;">
                <input type="hidden" name="action" value="tambah_bimbingan">

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 0.88rem; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">
                        Dosen Pembimbing Tujuan <span style="color: #ef4444;">*</span>
                    </label>
                    <select name="dosen_id" class="form-control" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.9rem;" required>
                        <?php foreach ($dosen_pembimbing_list as $dsn): ?>
                            <option value="<?= $dsn['dosen_id']; ?>">
                                <?= htmlspecialchars($dsn['nama_dosen']); ?>, <?= htmlspecialchars($dsn['gelar_akademik']); ?> (NIDN: <?= htmlspecialchars($dsn['nidn']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 0.88rem; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">
                        Tanggal Sesi Bimbingan <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="date" name="tanggal_bimbingan" class="form-control" value="<?= date('Y-m-d'); ?>" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.9rem;" required>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 0.88rem; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">
                        Materi / Pokok Bahasan Bimbingan <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="materi_bimbingan" class="form-control" placeholder="Contoh: Konsultasi BAB 1, Perumusan Masalah, dan Tinjauan Pustaka" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.9rem;" required>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 0.88rem; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">
                        Hasil / Perkembangan yang Dikerjakan <span style="color: #ef4444;">*</span>
                    </label>
                    <textarea name="hasil_perkembangan" rows="5" class="form-control" placeholder="Jelaskan secara rinci progres yang telah Anda selesaikan, perbaikan yang telah dilakukan, atau kendala teknis yang ingin dikonsultasikan..." style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; line-height: 1.5;" required></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label style="font-size: 0.88rem; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">
                        Unggah Dokumen Hasil / Revisi Skripsi <span style="font-size: 0.75rem; color: #64748b; font-weight: normal;">(Opsional, format PDF/DOC/DOCX/ZIP, Maks. 10 MB)</span>
                    </label>
                    <input type="file" name="file_lampiran" class="form-control" accept=".pdf,.doc,.docx,.zip,.rar,.jpg,.jpeg,.png" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.85rem;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn btn-outline-admin" onclick="tutupModalBimbingan();" style="padding: 10px 18px;">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">
                        <i class="fas fa-paper-plane"></i> Kirim Catatan Bimbingan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================
         MODAL LIHAT DETAIL BIMBINGAN
         =================================================================== -->
    <div class="modal-custom" id="modalDetailBimbingan">
        <div class="modal-box">
            <div class="modal-header-orange">
                <h3 style="margin: 0; font-size: 1.15rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-file-lines"></i> Rincian Sesi Bimbingan
                </h3>
                <button type="button" onclick="tutupModalDetail();" style="background: transparent; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>

            <div style="padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
                    <div>
                        <span style="font-size: 0.78rem; color: var(--text-muted); font-weight: 600;">Tanggal Bimbingan</span>
                        <div id="detail_tanggal" style="font-size: 1rem; font-weight: 700; color: var(--secondary);">-</div>
                    </div>
                    <div>
                        <span id="detail_status" class="badge" style="padding: 6px 14px; border-radius: 50px; font-weight: 700;">-</span>
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <span style="font-size: 0.78rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Dosen Pembimbing</span>
                    <div id="detail_dosen" style="font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-top: 2px;">-</div>
                </div>

                <div style="margin-bottom: 14px;">
                    <span style="font-size: 0.78rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Materi / Pokok Bahasan</span>
                    <div id="detail_materi" style="font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-top: 2px;">-</div>
                </div>

                <div style="margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px;">
                    <span style="font-size: 0.78rem; color: #475569; font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 6px;">Hasil Perkembangan yang Dikerjakan</span>
                    <div id="detail_hasil" style="font-size: 0.88rem; color: #1e293b; line-height: 1.5; white-space: pre-line;">-</div>
                </div>

                <div id="detail_lampiran_wrapper" style="margin-bottom: 16px; display: none;">
                    <span style="font-size: 0.78rem; color: #475569; font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 6px;">File Lampiran Dokumen</span>
                    <a id="detail_lampiran_link" href="#" target="_blank" class="file-pill">
                        <i class="fas fa-file-arrow-down" style="color: var(--primary);"></i>
                        <span id="detail_lampiran_nama">-</span>
                    </a>
                </div>

                <div id="detail_komentar_wrapper" style="margin-bottom: 16px; display: none;">
                    <div class="comment-dosen-box">
                        <div style="font-size: 0.82rem; font-weight: 700; color: #9a3412; margin-bottom: 4px;">
                            <i class="fas fa-comment-dots"></i> Komentar & Arahan Dosen:
                        </div>
                        <div id="detail_komentar" style="font-size: 0.88rem; color: #7c2d12; line-height: 1.5; white-space: pre-line;">-</div>
                        <div id="detail_waktu_tanggapan" style="font-size: 0.75rem; color: #9a3412; margin-top: 6px;">-</div>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-outline-admin" onclick="tutupModalDetail();" style="padding: 8px 18px;">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer style="text-align: center; padding: 20px; font-size: 0.85rem; color: #64748b; background: white; border-top: 1px solid var(--border-color); margin-top: 40px;">
        &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Jurusan Teknologi Informasi dan Komputer (TIK), Politeknik Negeri Lhokseumawe.
    </footer>

    <!-- Skrip Interaktif Modal & Detail -->
    <script>
        function bukaModalBimbingan() {
            document.getElementById('modalBimbingan').classList.add('show');
        }

        function tutupModalBimbingan() {
            document.getElementById('modalBimbingan').classList.remove('show');
        }

        function lihatDetailBimbingan(data) {
            document.getElementById('detail_tanggal').innerText = data.tanggal_bimbingan;
            document.getElementById('detail_dosen').innerText = data.nama_dosen + ', ' + data.gelar_akademik;
            document.getElementById('detail_materi').innerText = data.materi_bimbingan;
            document.getElementById('detail_hasil').innerText = data.hasil_perkembangan;
            
            const badge = document.getElementById('detail_status');
            badge.innerText = data.status;
            badge.className = 'badge';
            if (data.status === 'Sedang Diperiksa') badge.classList.add('badge-status-sedang-diperiksa');
            else if (data.status === 'Perlu Revisi') badge.classList.add('badge-status-perlu-revisi');
            else if (data.status === 'Disetujui') badge.classList.add('badge-status-disetujui');
            else if (data.status === 'Selesai') badge.classList.add('badge-status-selesai');
            else badge.classList.add('badge-status-menunggu');

            // Lampiran
            const lampiranWrapper = document.getElementById('detail_lampiran_wrapper');
            if (data.file_lampiran) {
                document.getElementById('detail_lampiran_nama').innerText = data.file_lampiran;
                document.getElementById('detail_lampiran_link').href = 'uploads/bimbingan/' + data.file_lampiran;
                lampiranWrapper.style.display = 'block';
            } else {
                lampiranWrapper.style.display = 'none';
            }

            // Komentar Dosen
            const komentarWrapper = document.getElementById('detail_komentar_wrapper');
            if (data.komentar_dosen) {
                document.getElementById('detail_komentar').innerText = data.komentar_dosen;
                if (data.waktu_tanggapan) {
                    document.getElementById('detail_waktu_tanggapan').innerText = 'Diberikan pada: ' + data.waktu_tanggapan;
                } else {
                    document.getElementById('detail_waktu_tanggapan').innerText = '';
                }
                komentarWrapper.style.display = 'block';
            } else {
                komentarWrapper.style.display = 'none';
            }

            document.getElementById('modalDetailBimbingan').classList.add('show');
        }

        function tutupModalDetail() {
            document.getElementById('modalDetailBimbingan').classList.remove('show');
        }

        window.onclick = function(e) {
            const mB = document.getElementById('modalBimbingan');
            const mD = document.getElementById('modalDetailBimbingan');
            if (e.target === mB) tutupModalBimbingan();
            if (e.target === mD) tutupModalDetail();
        };
    </script>

    <!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
    <script src="notifikasi.js"></script>
</body>
</html>
