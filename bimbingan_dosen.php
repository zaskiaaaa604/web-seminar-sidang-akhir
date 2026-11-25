<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TIK
 * FILE: bimbingan_dosen.php (Riwayat Bimbingan Mahasiswa untuk Dosen)
 * ===================================================================
 */

session_start();
require_once __DIR__ . '/koneksi.php';

// Validasi Hak Akses Khusus Dosen
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dosen') {
    header("Location: login_dosen.php");
    exit;
}

$dosen_id   = (int)($_SESSION['dosen_id'] ?? 0);
$nama_dosen = $_SESSION['nama'] ?? 'Dosen';
$gelar      = $_SESSION['gelar'] ?? '';
$nidn       = $_SESSION['nidn'] ?? '';

if ($dosen_id <= 0) {
    header("Location: logout.php");
    exit;
}

$success_message = '';
$error_message   = '';

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tanggapan_sukses') {
        $success_message = "Komentar, arahan bimbingan, dan pembaruan status berhasil disimpan serta dikirimkan kepada mahasiswa!";
    }
}

// ===================================================================
// 1. PROSES TANGGAPAN / REVIEW CATATAN BIMBINGAN OLEH DOSEN
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tanggapi_bimbingan') {
    $bimbingan_id   = (int)($_POST['bimbingan_id'] ?? 0);
    $status_baru    = trim($_POST['status'] ?? 'Sedang Diperiksa');
    $komentar_dosen = trim($_POST['komentar_dosen'] ?? '');
    $selected_mhs_id = (int)($_POST['mahasiswa_id'] ?? 0);

    $allowed_status = ['Sedang Diperiksa', 'Perlu Revisi', 'Disetujui', 'Selesai'];
    if (!in_array($status_baru, $allowed_status)) {
        $error_message = "Pilihan status tidak valid.";
    } elseif ($bimbingan_id <= 0) {
        $error_message = "ID Catatan Bimbingan tidak valid.";
    } else {
        // Validasi Kepemilikan: Pastikan bimbingan ini ditujukan kepada dosen yang sedang login
        $cek_bimbingan = mysqli_prepare($conn, "
            SELECT rb.id, rb.mahasiswa_id, rb.materi_bimbingan, m.nama AS nama_mahasiswa, m.nim 
            FROM riwayat_bimbingan rb 
            JOIN mahasiswa m ON rb.mahasiswa_id = m.id 
            WHERE rb.id = ? AND rb.dosen_id = ? LIMIT 1
        ");

        if ($cek_bimbingan) {
            mysqli_stmt_bind_param($cek_bimbingan, "ii", $bimbingan_id, $dosen_id);
            mysqli_stmt_execute($cek_bimbingan);
            $res_cek = mysqli_stmt_get_result($cek_bimbingan);
            if ($row_b = mysqli_fetch_assoc($res_cek)) {
                mysqli_stmt_close($cek_bimbingan);

                // Update Riwayat Bimbingan di MySQL
                $stmt_up = mysqli_prepare($conn, "
                    UPDATE riwayat_bimbingan 
                    SET status = ?, komentar_dosen = ?, waktu_tanggapan = NOW() 
                    WHERE id = ? AND dosen_id = ?
                ");

                if ($stmt_up) {
                    mysqli_stmt_bind_param($stmt_up, "ssii", $status_baru, $komentar_dosen, $bimbingan_id, $dosen_id);
                    if (mysqli_stmt_execute($stmt_up)) {
                        mysqli_stmt_close($stmt_up);

                        $mhs_id_tujuan = (int)$row_b['mahasiswa_id'];
                        $materi        = $row_b['materi_bimbingan'];

                        // NOTIFIKASI OTOMATIS: Mahasiswa menerima notifikasi dari Dosen Pembimbing
                        if ($status_baru === 'Disetujui') {
                            kirimNotifikasi(
                                $conn,
                                'mahasiswa',
                                $mhs_id_tujuan,
                                'Hasil Bimbingan Disetujui! 🎉',
                                "Catatan bimbingan materi \"$materi\" telah DISETUJUI oleh dosen pembimbing $nama_dosen, $gelar." . (!empty($komentar_dosen) ? " Catatan: $komentar_dosen" : ""),
                                'bimbingan_mahasiswa.php',
                                'diterima'
                            );
                        } elseif ($status_baru === 'Selesai') {
                            kirimNotifikasi(
                                $conn,
                                'mahasiswa',
                                $mhs_id_tujuan,
                                'Sesi Bimbingan Selesai! ✅',
                                "Catatan bimbingan \"$materi\" telah ditandai SELESAI oleh dosen pembimbing $nama_dosen, $gelar." . (!empty($komentar_dosen) ? " Catatan: $komentar_dosen" : ""),
                                'bimbingan_mahasiswa.php',
                                'diterima'
                            );
                        } elseif ($status_baru === 'Perlu Revisi') {
                            kirimNotifikasi(
                                $conn,
                                'mahasiswa',
                                $mhs_id_tujuan,
                                'Bimbingan Perlu Revisi',
                                "Dosen pembimbing $nama_dosen, $gelar meminta revisi untuk materi: \"$materi\". Arahan revisi: $komentar_dosen",
                                'bimbingan_mahasiswa.php',
                                'revisi'
                            );
                        } else {
                            kirimNotifikasi(
                                $conn,
                                'mahasiswa',
                                $mhs_id_tujuan,
                                'Arahan / Komentar Bimbingan Baru',
                                "Dosen pembimbing $nama_dosen, $gelar memberikan tanggapan pada bimbingan \"$materi\": \"$komentar_dosen\". Status: [$status_baru].",
                                'bimbingan_mahasiswa.php',
                                'komentar'
                            );
                        }

                        header("Location: bimbingan_dosen.php?mahasiswa_id=" . $mhs_id_tujuan . "&pesan=tanggapan_sukses#bimbingan_" . $bimbingan_id);
                        exit;
                    } else {
                        $error_message = "Gagal memperbarui tanggapan bimbingan: " . mysqli_error($conn);
                    }
                }
            } else {
                mysqli_stmt_close($cek_bimbingan);
                $error_message = "Akses ditolak: Catatan bimbingan ini bukan bimbingan yang ditujukan kepada Anda!";
            }
        }
    }
}

// ===================================================================
// 2. MENGAMBIL DAFTAR MAHASISWA YANG MENJADI TANGGUNG JAWAB DOSEN INI
// ===================================================================
// Sumber Mahasiswa Bimbingan:
// 1) Pengajuan Judul yang sudah berstatus 'Diterima' untuk dosen ini
// 2) Pendaftaran Seminar / Sidang di mana dosen_pembimbing_id = dosen ini
$mahasiswa_bimbingan_list = [];

// A. Mahasiswa dari Pengajuan Judul Diterima
$q_mhs_judul = mysqli_query($conn, "
    SELECT pj.mahasiswa_id, pj.judul, m.nama, m.nim, m.program_studi, m.email, m.no_hp
    FROM pengajuan_judul pj
    JOIN mahasiswa m ON pj.mahasiswa_id = m.id
    WHERE pj.dosen_id = $dosen_id AND pj.status = 'Diterima'
");
while ($r = mysqli_fetch_assoc($q_mhs_judul)) {
    $mid = (int)$r['mahasiswa_id'];
    $mahasiswa_bimbingan_list[$mid] = [
        'id'            => $mid,
        'nama'          => $r['nama'],
        'nim'           => $r['nim'],
        'program_studi' => $r['program_studi'],
        'email'         => $r['email'],
        'no_hp'         => $r['no_hp'],
        'judul'         => $r['judul'],
        'sumber'        => 'Pengajuan Judul Disetujui'
    ];
}

// B. Mahasiswa dari Pendaftaran Seminar / Sidang (Pembimbing)
$q_mhs_seminar = mysqli_query($conn, "
    SELECT p.mahasiswa_id, p.judul, m.nama, m.nim, m.program_studi, m.email, m.no_hp
    FROM pendaftaran_seminar_sidang p
    JOIN mahasiswa m ON p.mahasiswa_id = m.id
    WHERE p.dosen_pembimbing_id = $dosen_id
");
while ($r = mysqli_fetch_assoc($q_mhs_seminar)) {
    $mid = (int)$r['mahasiswa_id'];
    if (!isset($mahasiswa_bimbingan_list[$mid])) {
        $mahasiswa_bimbingan_list[$mid] = [
            'id'            => $mid,
            'nama'          => $r['nama'],
            'nim'           => $r['nim'],
            'program_studi' => $r['program_studi'],
            'email'         => $r['email'],
            'no_hp'         => $r['no_hp'],
            'judul'         => $r['judul'],
            'sumber'        => 'Pendaftaran Seminar/Sidang'
        ];
    }
}

// C. Mahasiswa yang pernah mengirimkan catatan bimbingan ke dosen ini
$q_mhs_catatan = mysqli_query($conn, "
    SELECT rb.mahasiswa_id, m.nama, m.nim, m.program_studi, m.email, m.no_hp
    FROM riwayat_bimbingan rb
    JOIN mahasiswa m ON rb.mahasiswa_id = m.id
    WHERE rb.dosen_id = $dosen_id
    GROUP BY rb.mahasiswa_id
");
while ($r = mysqli_fetch_assoc($q_mhs_catatan)) {
    $mid = (int)$r['mahasiswa_id'];
    if (!isset($mahasiswa_bimbingan_list[$mid])) {
        $mahasiswa_bimbingan_list[$mid] = [
            'id'            => $mid,
            'nama'          => $r['nama'],
            'nim'           => $r['nim'],
            'program_studi' => $r['program_studi'],
            'email'         => $r['email'],
            'no_hp'         => $r['no_hp'],
            'judul'         => 'Penelitian Skripsi Mahasiswa',
            'sumber'        => 'Logbook Bimbingan'
        ];
    }
}

// Tambahkan data agregasi statistik bimbingan untuk masing-masing mahasiswa
foreach ($mahasiswa_bimbingan_list as $mid => &$m_data) {
    $q_stat = mysqli_query($conn, "
        SELECT 
            COUNT(*) AS total_sesi,
            SUM(CASE WHEN status = 'Menunggu' THEN 1 ELSE 0 END) AS jml_menunggu,
            SUM(CASE WHEN status = 'Sedang Diperiksa' THEN 1 ELSE 0 END) AS jml_diperiksa,
            SUM(CASE WHEN status = 'Perlu Revisi' THEN 1 ELSE 0 END) AS jml_revisi,
            SUM(CASE WHEN status = 'Disetujui' OR status = 'Selesai' THEN 1 ELSE 0 END) AS jml_selesai,
            MAX(tanggal_bimbingan) AS tanggal_terakhir
        FROM riwayat_bimbingan
        WHERE mahasiswa_id = $mid AND dosen_id = $dosen_id
    ");
    $stat = mysqli_fetch_assoc($q_stat);
    $m_data['total_sesi']       = (int)($stat['total_sesi'] ?? 0);
    $m_data['jml_menunggu']     = (int)($stat['jml_menunggu'] ?? 0);
    $m_data['jml_diperiksa']    = (int)($stat['jml_diperiksa'] ?? 0);
    $m_data['jml_revisi']       = (int)($stat['jml_revisi'] ?? 0);
    $m_data['jml_selesai']      = (int)($stat['jml_selesai'] ?? 0);
    $m_data['tanggal_terakhir'] = $stat['tanggal_terakhir'] ?? null;
}
unset($m_data);

// ===================================================================
// 3. CEK APAKAH ADA MAHASISWA TERTENTU YANG DIPILIH
// ===================================================================
$selected_mahasiswa_id = isset($_GET['mahasiswa_id']) ? (int)$_GET['mahasiswa_id'] : 0;
$selected_mahasiswa    = null;
$riwayat_mahasiswa     = [];

if ($selected_mahasiswa_id > 0) {
    // Validasi Keamanan: Dosen HANYA boleh mengakses mahasiswa bimbingannya sendiri
    if (isset($mahasiswa_bimbingan_list[$selected_mahasiswa_id])) {
        $selected_mahasiswa = $mahasiswa_bimbingan_list[$selected_mahasiswa_id];

        // Ambil riwayat bimbingan mahasiswa ini
        $stmt_detail = mysqli_prepare($conn, "
            SELECT * FROM riwayat_bimbingan 
            WHERE mahasiswa_id = ? AND dosen_id = ? 
            ORDER BY tanggal_bimbingan DESC, id DESC
        ");
        if ($stmt_detail) {
            mysqli_stmt_bind_param($stmt_detail, "ii", $selected_mahasiswa_id, $dosen_id);
            mysqli_stmt_execute($stmt_detail);
            $res_d = mysqli_stmt_get_result($stmt_detail);
            while ($r = mysqli_fetch_assoc($res_d)) {
                $riwayat_mahasiswa[] = $r;
            }
            mysqli_stmt_close($stmt_detail);
        }
    } else {
        $error_message = "Akses ditolak: Anda tidak memiliki wewenang untuk melihat riwayat bimbingan mahasiswa tersebut.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Bimbingan Mahasiswa - <?= htmlspecialchars($nama_dosen); ?> | SIM-SEMSID</title>
    
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Utama SIM-SEMSID -->
    <link rel="stylesheet" href="style.css">

    <style>
        .badge-status-menunggu { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .badge-status-sedang-diperiksa { background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-status-perlu-revisi { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-status-disetujui { background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-status-selesai { background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

        .mhs-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .mhs-card:hover {
            border-color: var(--primary-border);
            box-shadow: 0 8px 24px rgba(234, 88, 12, 0.08);
            transform: translateY(-2px);
        }

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

        .action-review-box {
            background: #fffaf5;
            border: 1.5px dashed var(--primary-border);
            border-radius: 8px;
            padding: 18px;
            margin-top: 16px;
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
    </style>
</head>
<body class="dashboard-wrapper">

    <!-- Header / Navbar Dashboard Dosen -->
    <header class="dashboard-nav">
        <div class="container dashboard-nav-container">
            <a href="dashboard_dosen.php" class="logo-area">
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
                    <div class="user-name"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($nama_dosen); ?>, <?= htmlspecialchars($gelar); ?></div>
                    <div class="user-role">Dosen &bull; NIDN: <?= htmlspecialchars($nidn); ?></div>
                </div>
                <!-- Ikon Lonceng Notifikasi Real-time -->
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <a href="logout.php" class="btn-logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari akun dosen?');">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Konten Utama Bimbingan Dosen -->
    <main class="dashboard-main">
        <div class="container">

            <!-- Navigasi Menu Dosen -->
            <div style="display: flex; gap: 10px; margin-bottom: 22px; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
                <a href="dashboard_dosen.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-tachometer-alt"></i> Dashboard Bimbingan & Usulan Judul
                </a>
                <a href="bimbingan_dosen.php" class="btn btn-primary" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-book-reader"></i> Riwayat Bimbingan Mahasiswa (<?= count($mahasiswa_bimbingan_list); ?>)
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

            <?php if ($selected_mahasiswa): ?>
                <!-- ===================================================================
                     TAMPILAN RIWAYAT BIMBINGAN KHUSUS MAHASISWA YANG DIPILIH
                     =================================================================== -->
                <div class="dashboard-panel" style="margin-bottom: 24px;">
                    <div class="panel-header" style="flex-wrap: wrap; gap: 10px;">
                        <div>
                            <a href="bimbingan_dosen.php" class="btn btn-outline-admin" style="font-size: 0.82rem; padding: 6px 12px; margin-bottom: 6px; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Mahasiswa
                            </a>
                            <h3 style="margin-top: 4px;">
                                <i class="fas fa-user-graduate"></i> Riwayat Bimbingan: <?= htmlspecialchars($selected_mahasiswa['nama']); ?>
                            </h3>
                            <span style="font-size: 0.82rem; color: var(--text-muted);">
                                NIM: <strong><?= htmlspecialchars($selected_mahasiswa['nim']); ?></strong> &bull; Prodi: <?= htmlspecialchars($selected_mahasiswa['program_studi']); ?>
                            </span>
                        </div>
                        <span class="badge" style="background-color: var(--primary-light); color: var(--primary); border: 1px solid var(--primary-border); padding: 6px 14px; border-radius: 50px; font-weight: 700;">
                            Total <?= count($riwayat_mahasiswa); ?> Sesi Bimbingan
                        </span>
                    </div>

                    <!-- Judul Penelitian Mahasiswa -->
                    <div style="background: #fffaf5; border: 1px solid var(--primary-border); border-radius: 8px; padding: 16px; margin-top: 10px;">
                        <span style="font-size: 0.78rem; color: #9a3412; font-weight: 700; text-transform: uppercase;">Judul Penelitian Mahasiswa:</span>
                        <p style="margin: 4px 0 0 0; font-size: 0.95rem; font-weight: 700; color: var(--secondary); line-height: 1.45;">
                            "<?= htmlspecialchars($selected_mahasiswa['judul']); ?>"
                        </p>
                    </div>
                </div>

                <!-- Timeline Riwayat Bimbingan Mahasiswa Ini -->
                <div class="dashboard-panel">
                    <div class="panel-header">
                        <h3><i class="fas fa-list-ol"></i> Logbook Catatan Bimbingan</h3>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">Diurutkan dari catatan bimbingan terbaru</span>
                    </div>

                    <?php if (empty($riwayat_mahasiswa)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                            <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 12px; color: #cbd5e1;"></i>
                            <h4 style="font-size: 1.1rem; color: #475569; margin-bottom: 4px;">Belum Ada Catatan Bimbingan</h4>
                            <p style="font-size: 0.88rem; margin: 0;">Mahasiswa ini belum mengirimkan catatan bimbingan.</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline-container">
                            <?php $nomor = count($riwayat_mahasiswa); ?>
                            <?php foreach ($riwayat_mahasiswa as $row): ?>
                                <?php
                                    $status_badge_class = 'badge-status-menunggu';
                                    if ($row['status'] === 'Sedang Diperiksa') $status_badge_class = 'badge-status-sedang-diperiksa';
                                    elseif ($row['status'] === 'Perlu Revisi') $status_badge_class = 'badge-status-perlu-revisi';
                                    elseif ($row['status'] === 'Disetujui') $status_badge_class = 'badge-status-disetujui';
                                    elseif ($row['status'] === 'Selesai') $status_badge_class = 'badge-status-selesai';
                                ?>
                                <div class="timeline-card" id="bimbingan_<?= $row['id']; ?>">
                                    <div class="timeline-node" title="Bimbingan Ke-<?= $nomor; ?>">
                                        <?= $nomor; ?>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
                                        <div>
                                            <span style="font-size: 0.78rem; font-weight: 700; color: var(--primary); text-transform: uppercase;">
                                                <i class="fas fa-calendar-day"></i> Sesi Bimbingan Ke-<?= $nomor--; ?> &bull; Tanggal: <?= date('d F Y', strtotime($row['tanggal_bimbingan'])); ?>
                                            </span>
                                            <h4 style="margin: 4px 0 0 0; font-size: 1.15rem; color: var(--secondary); font-weight: 700;">
                                                <?= htmlspecialchars($row['materi_bimbingan']); ?>
                                            </h4>
                                        </div>

                                        <span class="badge <?= $status_badge_class; ?>" style="padding: 5px 14px; border-radius: 50px; font-size: 0.82rem; font-weight: 700;">
                                            <?= htmlspecialchars($row['status']); ?>
                                        </span>
                                    </div>

                                    <!-- Deskripsi Hasil & Perkembangan -->
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 14px;">
                                        <div style="font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase;">
                                            <i class="fas fa-file-pen"></i> Hasil / Perkembangan Penelitian dari Mahasiswa:
                                        </div>
                                        <p style="margin: 0; font-size: 0.88rem; color: #1e293b; line-height: 1.55; white-space: pre-line;">
                                            <?= htmlspecialchars($row['hasil_perkembangan']); ?>
                                        </p>
                                    </div>

                                    <!-- File Lampiran Mahasiswa (Jika Ada) -->
                                    <?php if (!empty($row['file_lampiran'])): ?>
                                        <div style="margin-bottom: 14px;">
                                            <span style="font-size: 0.8rem; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                                <i class="fas fa-paperclip"></i> Dokumen Lampiran Mahasiswa:
                                            </span>
                                            <a href="uploads/bimbingan/<?= htmlspecialchars($row['file_lampiran']); ?>" target="_blank" class="file-pill" title="Unduh Dokumen Mahasiswa">
                                                <i class="fas fa-file-arrow-down" style="color: var(--primary);"></i>
                                                <span><?= htmlspecialchars($row['file_lampiran']); ?></span>
                                                <span style="font-size: 0.72rem; color: var(--primary); font-weight: 700;">(Periksa / Unduh)</span>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Tanggapan & Arahan Dosen Yang Tersimpan Sebelumnya -->
                                    <?php if (!empty($row['komentar_dosen'])): ?>
                                        <div style="background: #fff8f5; border-left: 4px solid var(--primary); border-radius: 8px; padding: 14px; margin-bottom: 14px; border-top: 1px solid #fed7aa; border-right: 1px solid #fed7aa; border-bottom: 1px solid #fed7aa;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap;">
                                                <span style="font-size: 0.82rem; font-weight: 700; color: #9a3412;">
                                                    <i class="fas fa-comment-check"></i> Catatan & Arahan Anda Sebelumnya:
                                                </span>
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
                                    <?php endif; ?>

                                    <!-- Panel Form Input Tanggapan Dosen -->
                                    <div class="action-review-box">
                                        <h5 style="margin: 0 0 12px 0; font-size: 0.92rem; color: var(--secondary); font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-pen-to-square" style="color: var(--primary);"></i> Berikan Komentar, Arahan & Perbarui Status
                                        </h5>

                                        <form action="bimbingan_dosen.php?mahasiswa_id=<?= $selected_mahasiswa_id; ?>" method="POST">
                                            <input type="hidden" name="action" value="tanggapi_bimbingan">
                                            <input type="hidden" name="bimbingan_id" value="<?= $row['id']; ?>">
                                            <input type="hidden" name="mahasiswa_id" value="<?= $selected_mahasiswa_id; ?>">

                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 12px;">
                                                <div>
                                                    <label style="font-size: 0.82rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">
                                                        Perbarui Status Bimbingan:
                                                    </label>
                                                    <select name="status" class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--border-color); border-radius: 6px; font-size: 0.88rem;" required>
                                                        <option value="Sedang Diperiksa" <?= ($row['status'] === 'Sedang Diperiksa' || $row['status'] === 'Menunggu') ? 'selected' : ''; ?>>
                                                            🔵 Sedang Diperiksa
                                                        </option>
                                                        <option value="Perlu Revisi" <?= ($row['status'] === 'Perlu Revisi') ? 'selected' : ''; ?>>
                                                            🟠 Perlu Revisi
                                                        </option>
                                                        <option value="Disetujui" <?= ($row['status'] === 'Disetujui') ? 'selected' : ''; ?>>
                                                            🟢 Disetujui
                                                        </option>
                                                        <option value="Selesai" <?= ($row['status'] === 'Selesai') ? 'selected' : ''; ?>>
                                                            ✅ Selesai (Memenuhi Syarat)
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div style="margin-bottom: 12px;">
                                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">
                                                    Catatan, Arahan, atau Catatan Revisi untuk Mahasiswa:
                                                </label>
                                                <textarea name="komentar_dosen" rows="3" class="form-control" placeholder="Tuliskan arahan perbaikan, komentar pembimbing, atau persetujuan untuk mahasiswa..." style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--border-color); border-radius: 6px; font-size: 0.88rem; line-height: 1.5;" required><?= htmlspecialchars($row['komentar_dosen'] ?? ''); ?></textarea>
                                            </div>

                                            <div style="display: flex; justify-content: flex-end;">
                                                <button type="submit" class="btn btn-primary" style="font-size: 0.85rem; padding: 8px 18px;">
                                                    <i class="fas fa-paper-plane"></i> Simpan Catatan & Kirim ke Mahasiswa
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- ===================================================================
                     TAMPILAN DAFTAR SELURUH MAHASISWA BIMBINGAN DOSEN
                     =================================================================== -->
                <div class="dashboard-panel">
                    <div class="panel-header" style="flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h3><i class="fas fa-users"></i> Daftar Mahasiswa Bimbingan Anda</h3>
                            <span style="font-size: 0.82rem; color: var(--text-muted);">
                                Mahasiswa bimbingan tugas akhir skripsi yang menjadi tanggung jawab Anda
                            </span>
                        </div>
                        <span class="badge" style="background-color: var(--primary-light); color: var(--primary); border: 1px solid var(--primary-border); padding: 6px 14px; border-radius: 50px; font-weight: 700;">
                            Total: <?= count($mahasiswa_bimbingan_list); ?> Mahasiswa Bimbingan
                        </span>
                    </div>

                    <?php if (empty($mahasiswa_bimbingan_list)): ?>
                        <div style="text-align: center; padding: 50px 20px; color: #94a3b8;">
                            <i class="fas fa-user-slash" style="font-size: 3.5rem; margin-bottom: 12px; color: #cbd5e1;"></i>
                            <h4 style="font-size: 1.15rem; color: #475569; margin-bottom: 4px;">Belum Ada Mahasiswa Bimbingan</h4>
                            <p style="font-size: 0.88rem; margin: 0;">
                                Saat ini belum ada usulan judul penelitian mahasiswa yang Anda setujui atau ditugaskan sebagai pembimbing.
                            </p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
                            <?php foreach ($mahasiswa_bimbingan_list as $mhs): ?>
                                <div class="mhs-card">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
                                            <div>
                                                <h4 style="margin: 0; font-size: 1.1rem; color: var(--secondary); font-weight: 700;">
                                                    <?= htmlspecialchars($mhs['nama']); ?>
                                                </h4>
                                                <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600;">
                                                    NIM: <?= htmlspecialchars($mhs['nim']); ?> &bull; <?= htmlspecialchars($mhs['program_studi']); ?>
                                                </span>
                                            </div>
                                            <span class="badge" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.75rem; padding: 4px 10px; border-radius: 50px;">
                                                <?= $mhs['total_sesi']; ?> Bimbingan
                                            </span>
                                        </div>

                                        <!-- Judul Penelitian -->
                                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 14px;">
                                            <span style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Judul Skripsi:</span>
                                            <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: #1e293b; font-weight: 600; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                "<?= htmlspecialchars($mhs['judul']); ?>"
                                            </p>
                                        </div>

                                        <!-- Ringkasan Status Sesi -->
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; font-size: 0.78rem;">
                                            <?php if ($mhs['jml_menunggu'] > 0): ?>
                                                <span class="badge badge-status-menunggu" style="padding: 3px 8px; border-radius: 4px;">
                                                    ⏳ <?= $mhs['jml_menunggu']; ?> Menunggu
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($mhs['jml_diperiksa'] > 0): ?>
                                                <span class="badge badge-status-sedang-diperiksa" style="padding: 3px 8px; border-radius: 4px;">
                                                    🔵 <?= $mhs['jml_diperiksa']; ?> Diperiksa
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($mhs['jml_revisi'] > 0): ?>
                                                <span class="badge badge-status-perlu-revisi" style="padding: 3px 8px; border-radius: 4px;">
                                                    🟠 <?= $mhs['jml_revisi']; ?> Revisi
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($mhs['jml_selesai'] > 0): ?>
                                                <span class="badge badge-status-disetujui" style="padding: 3px 8px; border-radius: 4px;">
                                                    🟢 <?= $mhs['jml_selesai']; ?> Disetujui
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($mhs['total_sesi'] === 0): ?>
                                                <span style="color: #94a3b8; font-style: italic;">Belum ada sesi bimbingan</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div>
                                        <a href="bimbingan_dosen.php?mahasiswa_id=<?= $mhs['id']; ?>" class="btn btn-primary" style="width: 100%; text-decoration: none; text-align: center; display: block; padding: 10px; font-size: 0.88rem;">
                                            <i class="fas fa-book-reader"></i> Buka Logbook & Riwayat Bimbingan
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Footer -->
    <footer style="text-align: center; padding: 20px; font-size: 0.85rem; color: #64748b; background: white; border-top: 1px solid var(--border-color); margin-top: 40px;">
        &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Jurusan Teknologi Informasi dan Komputer (TIK), Politeknik Negeri Lhokseumawe.
    </footer>

    <!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
    <script src="notifikasi.js"></script>
</body>
</html>
