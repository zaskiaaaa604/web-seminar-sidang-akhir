<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * PROGRAM STUDI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: dashboard_dosen.php (Dashboard & Review Pengajuan Judul Dosen)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Validasi Keamanan: Hanya dosen yang berhak mengakses dashboard dosen
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login_dosen.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dosen') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'mahasiswa') {
        header("Location: dashboard_mahasiswa.php");
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: dashboard_admin.php");
    } else {
        header("Location: login_dosen.php");
    }
    exit;
}

$dosen_id   = (int)$_SESSION['dosen_id'];
$nidn       = $_SESSION['nidn'];
$nama_dosen = $_SESSION['nama'];
$gelar      = $_SESSION['gelar'];
$email      = $_SESSION['email'] ?? '-';
$prodi      = $_SESSION['program_studi'] ?? 'Teknologi Rekayasa Perangkat Lunak';

$error_message = '';
$success_message = '';

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'sukses_review') {
        $success_message = "Status dan tanggapan telaah judul berhasil disimpan & dikirimkan kepada mahasiswa!";
    } elseif ($_GET['pesan'] === 'sukses_komentar') {
        $success_message = "Pesan/catatan diskusi berhasil dikirim kepada mahasiswa!";
    } elseif ($_GET['pesan'] === 'alasan_wajib') {
        $error_message = "Gagal memproses: Dosen wajib mengisi alasan penolakan jika menolak usulan judul mahasiswa!";
    } elseif ($_GET['pesan'] === 'revisi_wajib') {
        $error_message = "Gagal memproses: Dosen wajib menuliskan catatan revisi jika meminta mahasiswa memperbaiki judul!";
    } elseif ($_GET['pesan'] === 'kuota_penuh') {
        $error_message = "Gagal menyetujui usulan: Kuota maksimal mahasiswa bimbingan Anda sudah penuh (" . KUOTA_MAKSIMAL_DOSEN . "/" . KUOTA_MAKSIMAL_DOSEN . " mahasiswa)! Sesuai ketentuan, Anda tidak dapat menerima pengajuan baru sebelum ada mahasiswa bimbingan yang resmi dibatalkan atau dipindahkan.";
    }
}

// ===================================================================
// 1. PROSES KEPUTUSAN DOSEN (TERIMA, REVISI, TOLAK, ATAU SEDANG DIPERIKSA)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'proses_telaah') {
    $pengajuan_id     = (int)($_POST['pengajuan_id'] ?? 0);
    $status_baru      = trim($_POST['status'] ?? '');
    $catatan_revisi   = trim($_POST['catatan_revisi'] ?? '');
    $alasan_penolakan = trim($_POST['alasan_penolakan'] ?? '');

    // Validasi kepemilikan pengajuan (Dosen hanya boleh mengelola pengajuan yang ditujukan kepadanya)
    $cek_milik = mysqli_query($conn, "SELECT pj.id, pj.mahasiswa_id, pj.judul, pj.status, m.nama AS nama_mahasiswa, m.nim 
                                       FROM pengajuan_judul pj 
                                       JOIN mahasiswa m ON pj.mahasiswa_id = m.id 
                                       WHERE pj.id = $pengajuan_id AND pj.dosen_id = $dosen_id LIMIT 1");
    if ($row_p = mysqli_fetch_assoc($cek_milik)) {
        $allowed_status = ['Sedang diperiksa', 'Perlu revisi', 'Diterima', 'Ditolak'];
        if (!in_array($status_baru, $allowed_status)) {
            $error_message = "Pilihan status tidak valid.";
        } elseif ($status_baru === 'Ditolak' && empty($alasan_penolakan)) {
            header("Location: dashboard_dosen.php?pesan=alasan_wajib");
            exit;
        } elseif ($status_baru === 'Perlu revisi' && empty($catatan_revisi)) {
            header("Location: dashboard_dosen.php?pesan=revisi_wajib");
            exit;
        } else {
            // Jika dosen memutuskan status 'Diterima', jalankan transaksi database dengan row-locking (FOR UPDATE)
            // untuk mencegah kondisi race condition saat dua pengajuan diproses bersamaan
            if ($status_baru === 'Diterima') {
                mysqli_begin_transaction($conn);

                // 1. Kunci baris dosen pada transaksi ini
                $lock_stmt = mysqli_prepare($conn, "SELECT id FROM dosen WHERE id = ? FOR UPDATE");
                mysqli_stmt_bind_param($lock_stmt, "i", $dosen_id);
                mysqli_stmt_execute($lock_stmt);
                mysqli_stmt_close($lock_stmt);

                // 2. Hitung jumlah mahasiswa bimbingan aktif yang sudah diterima (kecuali jika pengajuan ini sudah berstatus Diterima sebelumnya)
                $cek_kuota_sql = "SELECT COUNT(DISTINCT mahasiswa_id) AS total_bimbingan 
                                  FROM pengajuan_judul 
                                  WHERE dosen_id = ? AND status = 'Diterima' AND id != ?";
                $stmt_cek = mysqli_prepare($conn, $cek_kuota_sql);
                mysqli_stmt_bind_param($stmt_cek, "ii", $dosen_id, $pengajuan_id);
                mysqli_stmt_execute($stmt_cek);
                $res_cek = mysqli_stmt_get_result($stmt_cek);
                $row_cek = mysqli_fetch_assoc($res_cek);
                $bimbingan_saat_ini = (int)($row_cek['total_bimbingan'] ?? 0);
                mysqli_stmt_close($stmt_cek);

                if ($bimbingan_saat_ini >= KUOTA_MAKSIMAL_DOSEN) {
                    mysqli_rollback($conn);
                    header("Location: dashboard_dosen.php?pesan=kuota_penuh");
                    exit;
                }
            }

            // Update status di MySQL
            $up_stmt = mysqli_prepare($conn, "UPDATE pengajuan_judul SET status = ?, catatan_revisi = ?, alasan_penolakan = ? WHERE id = ? AND dosen_id = ?");
            mysqli_stmt_bind_param($up_stmt, "sssii", $status_baru, $catatan_revisi, $alasan_penolakan, $pengajuan_id, $dosen_id);
            if (mysqli_stmt_execute($up_stmt)) {
                mysqli_stmt_close($up_stmt);

                // Rekam pesan ke tabel tanggapan riwayat komunikasi
                $pesan_log = "Status diubah menjadi: $status_baru.";
                if ($status_baru === 'Perlu revisi') {
                    $pesan_log = "Dosen meminta revisi: " . $catatan_revisi;
                } elseif ($status_baru === 'Ditolak') {
                    $pesan_log = "Usulan ditolak dengan alasan: " . $alasan_penolakan;
                } elseif ($status_baru === 'Diterima') {
                    $pesan_log = "Alhamdulillah, judul penelitian disetujui/diterima oleh dosen pembimbing.";
                    if (!empty($catatan_revisi)) {
                        $pesan_log .= " Catatan dosen: " . $catatan_revisi;
                    }
                }

                $log_stmt = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'dosen', ?, ?, ?)");
                mysqli_stmt_bind_param($log_stmt, "iiss", $pengajuan_id, $dosen_id, $pesan_log, $status_baru);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);

                // Commit transaksi jika status adalah Diterima
                if ($status_baru === 'Diterima') {
                    mysqli_commit($conn);
                }

                // NOTIFIKASI OTOMATIS: Kirim notifikasi ke Mahasiswa dan Admin
                $mhs_id_tujuan = (int)$row_p['mahasiswa_id'];
                $judul_usulan  = $row_p['judul'];
                $nama_mhs      = $row_p['nama_mahasiswa'];
                $nim_mhs       = $row_p['nim'];

                if ($status_baru === 'Diterima') {
                    kirimNotifikasi($conn, 'mahasiswa', $mhs_id_tujuan, 'Judul Penelitian Disetujui/Diterima! 🎉', "Selamat! Dosen pembimbing $nama_dosen, $gelar telah MENERIMA judul usulan Anda: \"$judul_usulan\".", 'pengajuan_judul_mahasiswa.php?buka_id=' . $pengajuan_id, 'diterima');
                    kirimNotifikasiAdmin($conn, 'Judul Mahasiswa Diterima Dosen', "Dosen $nama_dosen, $gelar telah MENERIMA judul mahasiswa $nama_mhs (NIM: $nim_mhs): \"$judul_usulan\".", 'admin_pengajuan_judul.php', 'diterima');
                } elseif ($status_baru === 'Perlu revisi') {
                    kirimNotifikasi($conn, 'mahasiswa', $mhs_id_tujuan, 'Perlu Revisi Judul Penelitian', "Dosen pembimbing $nama_dosen, $gelar meminta revisi untuk judul: \"$judul_usulan\". Catatan: $catatan_revisi", 'pengajuan_judul_mahasiswa.php?buka_id=' . $pengajuan_id, 'revisi');
                    kirimNotifikasiAdmin($conn, 'Dosen Meminta Revisi Judul', "Dosen $nama_dosen meminta revisi untuk judul mahasiswa $nama_mhs: \"$judul_usulan\".", 'admin_pengajuan_judul.php', 'revisi');
                } elseif ($status_baru === 'Ditolak') {
                    kirimNotifikasi($conn, 'mahasiswa', $mhs_id_tujuan, 'Pengajuan Judul Ditolak', "Dosen pembimbing $nama_dosen belum menyetujui judul Anda: \"$judul_usulan\". Alasan penolakan: $alasan_penolakan", 'pengajuan_judul_mahasiswa.php?buka_id=' . $pengajuan_id, 'ditolak');
                    kirimNotifikasiAdmin($conn, 'Pengajuan Judul Ditolak Dosen', "Dosen $nama_dosen menolak usulan judul mahasiswa $nama_mhs. Alasan: $alasan_penolakan", 'admin_pengajuan_judul.php', 'ditolak');
                } elseif ($status_baru === 'Sedang diperiksa') {
                    kirimNotifikasi($conn, 'mahasiswa', $mhs_id_tujuan, 'Pengajuan Judul Sedang Diperiksa', "Dosen pembimbing $nama_dosen sedang memeriksa judul penelitian Anda: \"$judul_usulan\".", 'pengajuan_judul_mahasiswa.php?buka_id=' . $pengajuan_id, 'info');
                }

                header("Location: dashboard_dosen.php?pesan=sukses_review");
                exit;
            } else {
                if ($status_baru === 'Diterima') {
                    mysqli_rollback($conn);
                }
                $error_message = "Terjadi kesalahan database: " . mysqli_error($conn);
            }
        }
    } else {
        $error_message = "Akses ditolak: Pengajuan tidak ditujukan kepada Anda!";
    }
}

// ===================================================================
// 2. PROSES BALAS KOMENTAR DISKUSI OLEH DOSEN
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'balas_komentar') {
    $pengajuan_id = (int)($_POST['pengajuan_id'] ?? 0);
    $pesan        = trim($_POST['pesan'] ?? '');

    $cek = mysqli_query($conn, "SELECT id, mahasiswa_id, judul FROM pengajuan_judul WHERE id = $pengajuan_id AND dosen_id = $dosen_id LIMIT 1");
    if ($row_c = mysqli_fetch_assoc($cek)) {
        if (!empty($pesan)) {
            $chat_stmt = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'dosen', ?, ?, 'Komentar')");
            mysqli_stmt_bind_param($chat_stmt, "iis", $pengajuan_id, $dosen_id, $pesan);
            mysqli_stmt_execute($chat_stmt);
            mysqli_stmt_close($chat_stmt);

            // NOTIFIKASI OTOMATIS: Mahasiswa menerima notifikasi komentar dari Dosen
            $mhs_id_tujuan = (int)$row_c['mahasiswa_id'];
            $judul_usulan  = $row_c['judul'];
            if ($mhs_id_tujuan > 0) {
                kirimNotifikasi($conn, 'mahasiswa', $mhs_id_tujuan, 'Komentar Baru dari Dosen Pembimbing', "$nama_dosen, $gelar mengirim tanggapan pada usulan judul \"$judul_usulan\": \"$pesan\"", 'pengajuan_judul_mahasiswa.php?buka_id=' . $pengajuan_id, 'komentar');
            }

            header("Location: dashboard_dosen.php?pesan=sukses_komentar");
            exit;
        }
    }
}

// ===================================================================
// 3. MENGAMBIL DATA PENGAJUAN KHUSUS UNTUK DOSEN INI
// ===================================================================
$filter_status = trim($_GET['status_filter'] ?? '');
$search_query  = trim($_GET['search'] ?? '');

$sql = "SELECT pj.*, m.nama AS nama_mahasiswa, m.nim, m.email AS email_mahasiswa, m.no_hp AS no_hp_mahasiswa, m.program_studi 
        FROM pengajuan_judul pj 
        JOIN mahasiswa m ON pj.mahasiswa_id = m.id 
        WHERE pj.dosen_id = ?";
$types = "i";
$params = [$dosen_id];

if (!empty($filter_status) && in_array($filter_status, ['Menunggu', 'Sedang diperiksa', 'Perlu revisi', 'Diterima', 'Ditolak'])) {
    $sql .= " AND pj.status = ?";
    $types .= "s";
    $params[] = $filter_status;
}

if (!empty($search_query)) {
    $sql .= " AND (m.nama LIKE ? OR m.nim LIKE ? OR pj.judul LIKE ?)";
    $types .= "sss";
    $like = "%" . $search_query . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY pj.updated_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result_pengajuan = mysqli_stmt_get_result($stmt);

// Menghitung Statistik Dosen & Kuota Bimbingan Mahasiswa
$stat_total    = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul WHERE dosen_id = $dosen_id"));
$stat_menunggu = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul WHERE dosen_id = $dosen_id AND (status = 'Menunggu' OR status = 'Sedang diperiksa')"));
$stat_revisi   = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul WHERE dosen_id = $dosen_id AND status = 'Perlu revisi'"));
$stat_diterima = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul WHERE dosen_id = $dosen_id AND status = 'Diterima'"));
$stat_ditolak  = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM pengajuan_judul WHERE dosen_id = $dosen_id AND status = 'Ditolak'"));

// Kuota Bimbingan Aktif (hanya dihitung berdasarkan mahasiswa yang status judulnya 'Diterima')
$kuota_terpakai = hitungBimbinganDosen($conn, $dosen_id);
$sisa_kuota     = max(0, KUOTA_MAKSIMAL_DOSEN - $kuota_terpakai);
$persen_kuota   = min(100, round(($kuota_terpakai / KUOTA_MAKSIMAL_DOSEN) * 100));
$is_kuota_penuh = ($kuota_terpakai >= KUOTA_MAKSIMAL_DOSEN);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - SIM-SEMSID Politeknik Negeri Lhokseumawe</title>
    
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Utama -->
    <link rel="stylesheet" href="style.css">

    <style>
        .badge-status-menunggu { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .badge-status-sedang-diperiksa { background-color: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .badge-status-perlu-revisi { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-status-diterima { background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-status-ditolak { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .dosen-submission-item {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 24px;
            margin-bottom: 22px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            transition: var(--transition);
        }

        .dosen-submission-item:hover {
            border-color: var(--primary-border);
            box-shadow: 0 8px 24px rgba(234, 88, 12, 0.08);
        }

        .mhs-identity-box {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .review-action-panel {
            background: #fffaf5;
            border: 1.5px dashed var(--primary-border);
            border-radius: 10px;
            padding: 18px 20px;
            margin-top: 18px;
        }

        .file-link-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.85rem;
            color: #334155;
            text-decoration: none;
            transition: var(--transition);
        }

        .file-link-pill:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Modal Dialog */
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

        .modal-custom.show { display: flex; }

        .modal-box {
            background: white;
            border-radius: var(--radius-md);
            max-width: 620px;
            width: 100%;
            border: 1px solid var(--primary-border);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
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

    <!-- Konten Utama Dashboard Dosen -->
    <main class="dashboard-main">
        <div class="container">

            <!-- Navigasi Menu Dosen -->
            <div style="display: flex; gap: 10px; margin-bottom: 22px; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
                <a href="dashboard_dosen.php" class="btn btn-primary" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-tachometer-alt"></i> Dashboard Bimbingan & Usulan Judul
                </a>
                <a href="bimbingan_dosen.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-book-reader"></i> Riwayat Bimbingan Mahasiswa
                </a>
            </div>

            <!-- Banner Sambutan Dosen -->
            <div class="welcome-banner" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
                <div class="welcome-text">
                    <h2>Portal Telaah Judul & Bimbingan Mahasiswa 👨‍🏫👩‍🏫</h2>
                    <p>
                        Selamat datang, <strong><?= htmlspecialchars($nama_dosen); ?>, <?= htmlspecialchars($gelar); ?></strong>. Di portal ini Anda dapat meninjau usulan judul penelitian skripsi mahasiswa bimbingan, memeriksa dokumen & gambar arsitektur, memberikan catatan revisi, serta memutuskan penerimaan judul.
                    </p>
                    <div style="margin-top: 12px;">
                        <a href="bimbingan_dosen.php" class="btn btn-primary" style="font-size: 0.82rem; padding: 7px 14px; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-book-reader"></i> Buka Riwayat Bimbingan Mahasiswa
                        </a>
                    </div>
                </div>
                <div class="welcome-badge" style="background-color: rgba(234, 88, 12, 0.25); border: 1px solid #ea580c; color: #ffedd5;">
                    <i class="fas fa-chalkboard-teacher"></i> Dosen Pembimbing TIK
                </div>
            </div>

            <!-- Pesan Notifikasi Feedback -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" style="box-shadow: var(--shadow-sm); margin-bottom: 20px;">
                    <i class="fas fa-circle-check" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div><?= $success_message; ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" style="box-shadow: var(--shadow-sm); margin-bottom: 20px;">
                    <i class="fas fa-triangle-exclamation" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div><?= $error_message; ?></div>
                </div>
            <?php endif; ?>

            <!-- Kartu Metrik Ringkasan Usulan Masuk & Kuota Dosen -->
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 25px;">
                <div class="dash-stat-card" style="border-left: 4px solid <?= $is_kuota_penuh ? '#dc2626' : 'var(--primary)'; ?>; background: <?= $is_kuota_penuh ? '#fff5f5' : 'white'; ?>;">
                    <div class="dash-stat-icon" style="background-color: <?= $is_kuota_penuh ? '#fee2e2' : 'var(--primary-light)'; ?>; color: <?= $is_kuota_penuh ? '#dc2626' : 'var(--primary)'; ?>;">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4><?= $kuota_terpakai; ?> / <?= KUOTA_MAKSIMAL_DOSEN; ?></h4>
                        <p style="font-weight: 700; color: <?= $is_kuota_penuh ? '#b91c1c' : 'var(--secondary)'; ?>;">
                            Kuota Bimbingan <?= $is_kuota_penuh ? '(Penuh)' : '(Sisa ' . $sisa_kuota . ')'; ?>
                        </p>
                    </div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4><?= $stat_total; ?></h4>
                        <p>Total Usulan Masuk</p>
                    </div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon" style="background-color: #fff7ed; color: #c2410c;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4><?= $stat_menunggu; ?></h4>
                        <p>Menunggu / Diperiksa</p>
                    </div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon" style="background-color: #fffbeb; color: #d97706;">
                        <i class="fas fa-pen-to-square"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4><?= $stat_revisi; ?></h4>
                        <p>Perlu Revisi</p>
                    </div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon" style="background-color: #f0fdf4; color: #16a34a;">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4><?= $stat_diterima; ?></h4>
                        <p>Judul Diterima</p>
                    </div>
                </div>

                <div class="dash-stat-card">
                    <div class="dash-stat-icon" style="background-color: #fef2f2; color: #dc2626;">
                        <i class="fas fa-circle-xmark"></i>
                    </div>
                    <div class="dash-stat-info">
                        <h4><?= $stat_ditolak; ?></h4>
                        <p>Judul Ditolak</p>
                    </div>
                </div>
            </div>

            <!-- Toolbar Pencarian & Filter Status -->
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 22px;">
                <h3 style="margin: 0; font-size: 1.2rem; color: var(--secondary);">
                    <i class="fas fa-list-check" style="color: var(--primary);"></i> Daftar Usulan Judul Mahasiswa Ditujukan Kepada Anda
                </h3>

                <form method="GET" action="dashboard_dosen.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Cari Nama Mahasiswa / NIM / Judul..." value="<?= htmlspecialchars($search_query); ?>" style="padding: 9px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.88rem; outline: none; min-width: 250px;">
                    
                    <select name="status_filter" onchange="this.form.submit();" style="padding: 9px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.88rem; background: white; outline: none;">
                        <option value="">Semua Status</option>
                        <option value="Menunggu" <?= ($filter_status === 'Menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="Sedang diperiksa" <?= ($filter_status === 'Sedang diperiksa') ? 'selected' : ''; ?>>Sedang diperiksa</option>
                        <option value="Perlu revisi" <?= ($filter_status === 'Perlu revisi') ? 'selected' : ''; ?>>Perlu revisi</option>
                        <option value="Diterima" <?= ($filter_status === 'Diterima') ? 'selected' : ''; ?>>Diterima</option>
                        <option value="Ditolak" <?= ($filter_status === 'Ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                    </select>

                    <?php if (!empty($search_query) || !empty($filter_status)): ?>
                        <a href="dashboard_dosen.php" class="btn btn-outline-admin" style="padding: 9px 14px; font-size: 0.85rem;"><i class="fas fa-rotate-left"></i> Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Daftar Pengajuan Judul Mahasiswa -->
            <?php if ($result_pengajuan && mysqli_num_rows($result_pengajuan) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result_pengajuan)): ?>
                    <div class="dosen-submission-item" id="item_<?= $row['id']; ?>">
                        
                        <!-- Header Judul & Status -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; margin-bottom: 12px; flex-wrap: wrap;">
                            <h4 style="margin: 0; font-size: 1.15rem; color: var(--secondary); line-height: 1.4; flex: 1;">
                                <?= htmlspecialchars($row['judul']); ?>
                            </h4>

                            <?php
                            $st = $row['status'];
                            $badge_class = 'badge-status-menunggu';
                            $icon_class  = 'fa-clock';
                            if ($st === 'Sedang diperiksa') {
                                $badge_class = 'badge-status-sedang-diperiksa';
                                $icon_class  = 'fa-magnifying-glass';
                            } elseif ($st === 'Perlu revisi') {
                                $badge_class = 'badge-status-perlu-revisi';
                                $icon_class  = 'fa-pen-to-square';
                            } elseif ($st === 'Diterima') {
                                $badge_class = 'badge-status-diterima';
                                $icon_class  = 'fa-circle-check';
                            } elseif ($st === 'Ditolak') {
                                $badge_class = 'badge-status-ditolak';
                                $icon_class  = 'fa-circle-xmark';
                            }
                            ?>
                            <span class="badge <?= $badge_class; ?>" style="padding: 6px 14px; border-radius: 50px; font-weight: 700; font-size: 0.85rem;">
                                <i class="fas <?= $icon_class; ?>"></i> <?= htmlspecialchars($st); ?>
                            </span>
                        </div>

                        <!-- Identitas Mahasiswa Pengaju -->
                        <div class="mhs-identity-box">
                            <span><i class="fas fa-user-graduate" style="color: var(--primary);"></i> <strong><?= htmlspecialchars($row['nama_mahasiswa']); ?></strong></span>
                            <span>&bull; NIM: <strong><?= htmlspecialchars($row['nim']); ?></strong></span>
                            <span>&bull; Prodi: <?= htmlspecialchars($row['program_studi']); ?></span>
                            <span>&bull; <a href="mailto:<?= htmlspecialchars($row['email_mahasiswa']); ?>" style="color: #2563eb; text-decoration: none;"><i class="fas fa-envelope"></i> <?= htmlspecialchars($row['email_mahasiswa']); ?></a></span>
                            <span>&bull; <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['no_hp_mahasiswa']); ?>" target="_blank" style="color: #16a34a; text-decoration: none;"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($row['no_hp_mahasiswa']); ?></a></span>
                        </div>

                        <!-- Deskripsi Latar Belakang -->
                        <div style="background: #f8fafc; padding: 14px 18px; border-radius: 8px; font-size: 0.9rem; line-height: 1.6; color: #334155; margin-bottom: 12px; border: 1px solid #e2e8f0;">
                            <strong><i class="fas fa-align-left" style="color: var(--primary);"></i> Latar Belakang & Deskripsi Masalah:</strong>
                            <p style="margin: 6px 0 0 0; white-space: pre-line;"><?= htmlspecialchars($row['latar_belakang']); ?></p>
                        </div>

                        <!-- Berkas Lampiran -->
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
                            <?php if (!empty($row['file_dokumen'])): ?>
                                <a href="uploads/judul/<?= urlencode($row['file_dokumen']); ?>" target="_blank" class="file-link-pill">
                                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i> Unduh Dokumen Usulan Mahasiswa (<?= htmlspecialchars(pathinfo($row['file_dokumen'], PATHINFO_EXTENSION)); ?>)
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($row['file_gambar'])): ?>
                                <a href="uploads/judul/<?= urlencode($row['file_gambar']); ?>" target="_blank" class="file-link-pill">
                                    <i class="fas fa-image" style="color: #2563eb;"></i> Lihat Gambar / Diagram Arsitektur
                                </a>
                            <?php endif; ?>

                            <?php if (empty($row['file_dokumen']) && empty($row['file_gambar'])): ?>
                                <span style="font-size: 0.8rem; color: #94a3b8;"><i class="fas fa-paperclip"></i> Mahasiswa tidak melampirkan berkas tambahan.</span>
                            <?php endif; ?>
                        </div>

                        <!-- Panel Form Keputusan Telaah Dosen -->
                        <div class="review-action-panel">
                            <h5 style="margin: 0 0 10px 0; font-size: 0.95rem; color: var(--secondary); display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-gavel" style="color: var(--primary);"></i> Keputusan & Tindakan Dosen Pembimbing:
                            </h5>

                            <form action="dashboard_dosen.php" method="POST" id="form_telaah_<?= $row['id']; ?>">
                                <input type="hidden" name="action" value="proses_telaah">
                                <input type="hidden" name="pengajuan_id" value="<?= $row['id']; ?>">

                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 12px;">
                                    <div>
                                        <label style="font-size: 0.85rem; font-weight: 700; color: var(--secondary); margin-bottom: 4px; display: block;">
                                            Tentukan Status Keputusan:
                                        </label>
                                        <select name="status" id="select_status_<?= $row['id']; ?>" onchange="toggleStatusInput(<?= $row['id']; ?>);" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem; background: white; outline: none; cursor: pointer;">
                                            <option value="Sedang diperiksa" <?= ($row['status'] === 'Sedang diperiksa' || $row['status'] === 'Menunggu') ? 'selected' : ''; ?>>🔍 Sedang diperiksa</option>
                                            <option value="Perlu revisi" <?= ($row['status'] === 'Perlu revisi') ? 'selected' : ''; ?>>📝 Perlu revisi (Wajib Isi Catatan)</option>
                                            <?php if ($is_kuota_penuh && $row['status'] !== 'Diterima'): ?>
                                                <option value="Diterima" disabled style="color: #94a3b8; background: #f8fafc;">⛔ Diterima (Kuota Bimbingan Penuh 8/8)</option>
                                            <?php else: ?>
                                                <option value="Diterima" <?= ($row['status'] === 'Diterima') ? 'selected' : ''; ?>>✅ Diterima (Judul Disetujui)</option>
                                            <?php endif; ?>
                                            <option value="Ditolak" <?= ($row['status'] === 'Ditolak') ? 'selected' : ''; ?>>❌ Ditolak (Wajib Isi Alasan)</option>
                                        </select>
                                        <?php if ($is_kuota_penuh && $row['status'] !== 'Diterima'): ?>
                                            <small style="color: #b91c1c; font-size: 0.78rem; display: block; margin-top: 5px;">
                                                <i class="fas fa-lock"></i> Kuota bimbingan Anda saat ini penuh (<?= KUOTA_MAKSIMAL_DOSEN; ?>/<?= KUOTA_MAKSIMAL_DOSEN; ?>). Pengajuan tidak dapat disetujui kecuali ada mahasiswa yang dibatalkan/dipindahkan.
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Box Input Catatan Revisi / Arahan -->
                                <div id="box_revisi_<?= $row['id']; ?>" style="display: <?= ($row['status'] === 'Perlu revisi' || $row['status'] === 'Diterima') ? 'block' : 'none'; ?>; margin-bottom: 12px;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: #b45309; margin-bottom: 4px; display: block;">
                                        <i class="fas fa-pen"></i> Tuliskan Poin Catatan / Arahan untuk Mahasiswa:
                                    </label>
                                    <textarea name="catatan_revisi" rows="3" placeholder="Contoh: Perjelas batasan masalah, tambahkan metode penelitian..." style="width: 100%; padding: 10px; border: 1px solid #fde68a; border-radius: 6px; font-size: 0.88rem; outline: none; font-family: inherit;"><?= htmlspecialchars($row['catatan_revisi'] ?? ''); ?></textarea>
                                </div>

                                <!-- Box Input Alasan Penolakan -->
                                <div id="box_tolak_<?= $row['id']; ?>" style="display: <?= ($row['status'] === 'Ditolak') ? 'block' : 'none'; ?>; margin-bottom: 12px;">
                                    <label style="font-size: 0.85rem; font-weight: 700; color: #b91c1c; margin-bottom: 4px; display: block;">
                                        <i class="fas fa-circle-exclamation"></i> Alasan Penolakan Judul (Wajib Diisi):
                                    </label>
                                    <textarea name="alasan_penolakan" rows="3" placeholder="Contoh: Judul ini sudah pernah diteliti pada tahun sebelumnya / ruang lingkup kurang memadai..." style="width: 100%; padding: 10px; border: 1px solid #fecaca; border-radius: 6px; font-size: 0.88rem; outline: none; font-family: inherit;"><?= htmlspecialchars($row['alasan_penolakan'] ?? ''); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary" style="padding: 9px 20px; font-size: 0.88rem;">
                                    <i class="fas fa-floppy-disk"></i> Simpan & Kirim Keputusan ke Mahasiswa
                                </button>
                            </form>
                        </div>

                        <!-- Riwayat Komunikasi & Diskusi Bimbingan -->
                        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                            <h5 style="font-size: 0.92rem; color: var(--secondary); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-comments" style="color: var(--primary);"></i> Riwayat Komunikasi & Tanggapan:
                            </h5>

                            <?php
                            $row_id = (int)$row['id'];
                            $q_chat = mysqli_query($conn, "SELECT * FROM tanggapan_pengajuan WHERE pengajuan_id = $row_id ORDER BY created_at ASC");
                            ?>

                            <?php if ($q_chat && mysqli_num_rows($q_chat) > 0): ?>
                                <div style="max-height: 250px; overflow-y: auto; padding: 12px; background: #fdfdfd; border-radius: 8px; border: 1px solid #f1f5f9; margin-bottom: 12px;">
                                    <?php while ($c = mysqli_fetch_assoc($q_chat)): ?>
                                        <div style="padding: 10px 14px; border-radius: 8px; margin-bottom: 10px; font-size: 0.88rem; max-width: 85%; <?= ($c['pengirim_role'] === 'dosen') ? 'margin-left: auto; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a;' : 'margin-right: auto; background: var(--primary-light); border: 1px solid var(--primary-border); color: #9a3412;'; ?>">
                                            <div>
                                                <strong>
                                                    <?php if ($c['pengirim_role'] === 'dosen'): ?>
                                                        <i class="fas fa-user-tie"></i> Anda (Dosen Pembimbing):
                                                    <?php else: ?>
                                                        <i class="fas fa-user-graduate"></i> <?= htmlspecialchars($row['nama_mahasiswa']); ?>:
                                                    <?php endif; ?>
                                                </strong>
                                            </div>
                                            <div style="margin-top: 4px;"><?= nl2br(htmlspecialchars($c['pesan'])); ?></div>
                                            <div style="font-size: 0.72rem; color: #64748b; margin-top: 4px; display: flex; justify-content: space-between;">
                                                <span><i class="fas fa-tag"></i> <?= htmlspecialchars($c['status_perubahan']); ?></span>
                                                <span><?= date('d/m/Y H:i', strtotime($c['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p style="font-size: 0.82rem; color: #94a3b8; font-style: italic; margin-bottom: 10px;">
                                    Belum ada riwayat percakapan untuk usulan ini.
                                </p>
                            <?php endif; ?>

                            <!-- Form Kirim Pesan Diskusi Cepat -->
                            <form action="dashboard_dosen.php" method="POST" style="display: flex; gap: 8px;">
                                <input type="hidden" name="action" value="balas_komentar">
                                <input type="hidden" name="pengajuan_id" value="<?= $row['id']; ?>">
                                <input type="text" name="pesan" placeholder="Ketik catatan bimbingan atau pertanyaan untuk mahasiswa..." required style="flex: 1; padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; outline: none;">
                                <button type="submit" class="btn btn-outline-primary" style="padding: 9px 16px; font-size: 0.85rem;">
                                    <i class="fas fa-paper-plane"></i> Kirim Catatan
                                </button>
                            </form>
                        </div>

                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="dashboard-panel" style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                    <h4 style="color: var(--secondary); margin-bottom: 6px;">Tidak Ada Pengajuan Judul</h4>
                    <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 500px; margin: 0 auto;">
                        Saat ini belum ada mahasiswa yang mengajukan usulan judul penelitian kepada Anda, atau tidak ada data yang cocok dengan filter pencarian.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Tautan Cepat ke Beranda -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="index.php" style="color: var(--primary); text-decoration: none; font-weight: 700; font-size: 0.95rem;">
                    <i class="fas fa-arrow-left"></i> Lihat Halaman Utama Website
                </a>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer style="text-align: center; padding: 20px; font-size: 0.85rem; color: #64748b; background: white; border-top: 1px solid var(--border-color); margin-top: 40px;">
        &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Jurusan Teknologi Informasi dan Komputer (TIK), Politeknik Negeri Lhokseumawe. Panel Dosen Pembimbing.
    </footer>

    <!-- JavaScript Interaktif -->
    <script>
        function toggleStatusInput(id) {
            const selectVal = document.getElementById('select_status_' + id).value;
            const boxRevisi = document.getElementById('box_revisi_' + id);
            const boxTolak  = document.getElementById('box_tolak_' + id);

            if (selectVal === 'Perlu revisi' || selectVal === 'Diterima') {
                boxRevisi.style.display = 'block';
                boxTolak.style.display  = 'none';
            } else if (selectVal === 'Ditolak') {
                boxRevisi.style.display = 'none';
                boxTolak.style.display  = 'block';
            } else {
                boxRevisi.style.display = 'none';
                boxTolak.style.display  = 'none';
            }
        }
    </script>
    <!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
    <script src="notifikasi.js"></script>
</body>
</html>
