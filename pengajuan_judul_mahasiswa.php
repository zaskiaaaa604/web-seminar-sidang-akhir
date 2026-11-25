<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * PROGRAM STUDI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: pengajuan_judul_mahasiswa.php (Pengajuan Judul & Komunikasi)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Validasi Keamanan: Hanya mahasiswa yang berhak mengakses halaman ini
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login_mahasiswa.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mahasiswa') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'dosen') {
        header("Location: dashboard_dosen.php");
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: dashboard_admin.php");
    } else {
        header("Location: login_mahasiswa.php");
    }
    exit;
}

$mahasiswa_id = (int)$_SESSION['mahasiswa_id'];
$nim          = $_SESSION['nim'];
$nama         = $_SESSION['nama'];
$prodi        = $_SESSION['program_studi'] ?? 'Jurusan Teknologi Informasi dan Komputer (TIK)';

$error_message = '';
$success_message = '';

// Menangkap parameter pesan feedback
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'sukses_tambah') {
        $success_message = "Judul penelitian berhasil diajukan kepada dosen! Silakan pantau tanggapan dan status secara berkala.";
    } elseif ($_GET['pesan'] === 'sukses_revisi') {
        $success_message = "Perbaikan judul & dokumen revisi berhasil dikirim ulang kepada dosen pembimbing!";
    } elseif ($_GET['pesan'] === 'sukses_komentar') {
        $success_message = "Pesan/komentar Anda berhasil dikirim ke dosen!";
    } elseif ($_GET['pesan'] === 'file_terlalu_besar') {
        $error_message = "Ukuran file yang diunggah melebihi batas maksimal (Dokumen maks 5MB, Gambar maks 3MB)!";
    } elseif ($_GET['pesan'] === 'ekstensi_tidak_valid') {
        $error_message = "Format file tidak didukung! Format yang diperbolehkan: PDF, DOC, DOCX untuk dokumen, dan JPG, PNG, WEBP untuk gambar.";
    }
}

// Direktori unggahan berkas
$upload_dir = __DIR__ . '/uploads/judul/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ===================================================================
// 1. PROSES PENGAJUAN JUDUL BARU
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_pengajuan') {
    $judul          = trim($_POST['judul'] ?? '');
    $latar_belakang = trim($_POST['latar_belakang'] ?? '');
    $dosen_id       = (int)($_POST['dosen_id'] ?? 0);

    if (empty($judul) || empty($latar_belakang) || $dosen_id <= 0) {
        $error_message = "Judul penelitian, latar belakang, dan dosen tujuan wajib diisi!";
    } else {
        // Validasi Dosen Aktif
        $cek_dosen = mysqli_query($conn, "SELECT id, nama_dosen, gelar_akademik FROM dosen WHERE id = $dosen_id AND status = 'Aktif' LIMIT 1");
        if (mysqli_num_rows($cek_dosen) === 0) {
            $error_message = "Dosen yang dipilih tidak valid atau sedang tidak aktif.";
        } else {
            $row_dsn_info = mysqli_fetch_assoc($cek_dosen);
            $nama_dosen_tujuan = $row_dsn_info['nama_dosen'] . ', ' . $row_dsn_info['gelar_akademik'];

            // Validasi Kuota Maksimal Mahasiswa Bimbingan Dosen (Maks 8 mahasiswa diterima)
            $bimbingan_terpakai = hitungBimbinganDosen($conn, $dosen_id);
            if ($bimbingan_terpakai >= KUOTA_MAKSIMAL_DOSEN) {
                $error_message = "Mohon maaf, kuota mahasiswa bimbingan untuk dosen tersebut telah penuh (" . KUOTA_MAKSIMAL_DOSEN . "/" . KUOTA_MAKSIMAL_DOSEN . " mahasiswa). Silakan pilih dosen pembimbing lainnya yang masih memiliki sisa kuota.";
            } else {
                $file_dokumen_name = null;
                $file_gambar_name  = null;
                $upload_ok = true;

            // Proses Upload Dokumen Pendukung (PDF, DOC, DOCX)
            if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
                $doc_file = $_FILES['file_dokumen'];
                $allowed_doc_exts = ['pdf', 'doc', 'docx'];
                $doc_ext = strtolower(pathinfo($doc_file['name'], PATHINFO_EXTENSION));

                if (!in_array($doc_ext, $allowed_doc_exts)) {
                    $upload_ok = false;
                    $error_message = "Format dokumen tidak valid! Hanya mendukung PDF, DOC, dan DOCX.";
                } elseif ($doc_file['size'] > 5 * 1024 * 1024) {
                    $upload_ok = false;
                    $error_message = "Ukuran dokumen maksimal 5 MB!";
                } else {
                    $file_dokumen_name = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $doc_ext;
                    move_uploaded_file($doc_file['tmp_name'], $upload_dir . $file_dokumen_name);
                }
            }

            // Proses Upload Gambar Pendukung (JPG, PNG, WEBP)
            if ($upload_ok && isset($_FILES['file_gambar']) && $_FILES['file_gambar']['error'] === UPLOAD_ERR_OK) {
                $img_file = $_FILES['file_gambar'];
                $allowed_img_exts = ['jpg', 'jpeg', 'png', 'webp'];
                $img_ext = strtolower(pathinfo($img_file['name'], PATHINFO_EXTENSION));

                if (!in_array($img_ext, $allowed_img_exts)) {
                    $upload_ok = false;
                    $error_message = "Format gambar tidak valid! Hanya mendukung JPG, JPEG, PNG, dan WEBP.";
                } elseif ($img_file['size'] > 3 * 1024 * 1024) {
                    $upload_ok = false;
                    $error_message = "Ukuran gambar maksimal 3 MB!";
                } else {
                    $file_gambar_name = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $img_ext;
                    move_uploaded_file($img_file['tmp_name'], $upload_dir . $file_gambar_name);
                }
            }

            if ($upload_ok) {
                $insert_query = "INSERT INTO pengajuan_judul (mahasiswa_id, dosen_id, judul, latar_belakang, file_dokumen, file_gambar, status) VALUES (?, ?, ?, ?, ?, ?, 'Menunggu')";
                $stmt = mysqli_prepare($conn, $insert_query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "iissss", $mahasiswa_id, $dosen_id, $judul, $latar_belakang, $file_dokumen_name, $file_gambar_name);
                    if (mysqli_stmt_execute($stmt)) {
                        $new_pengajuan_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($stmt);

                        // Rekam ke tabel tanggapan riwayat
                        $catat_awal = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'mahasiswa', ?, 'Mengajukan judul penelitian baru.', 'Menunggu')");
                        mysqli_stmt_bind_param($catat_awal, "ii", $new_pengajuan_id, $mahasiswa_id);
                        mysqli_stmt_execute($catat_awal);
                        mysqli_stmt_close($catat_awal);

                        // NOTIFIKASI OTOMATIS:
                        // 1. Notifikasi untuk Mahasiswa Pengusul
                        kirimNotifikasi($conn, 'mahasiswa', $mahasiswa_id, 'Pengajuan Judul Berhasil Dikirim', "Judul penelitian Anda: \"$judul\" berhasil diajukan kepada $nama_dosen_tujuan. Menunggu tanggapan dosen pembimbing.", 'pengajuan_judul_mahasiswa.php?buka_id=' . $new_pengajuan_id, 'pengajuan');

                        // 2. Notifikasi untuk Dosen Pembimbing Tujuan
                        kirimNotifikasi($conn, 'dosen', $dosen_id, 'Pengajuan Judul Baru dari Mahasiswa', "Mahasiswa $nama (NIM: $nim) mengajukan judul penelitian baru: \"$judul\".", 'dashboard_dosen.php?buka_id=' . $new_pengajuan_id, 'pengajuan');

                        // 3. Notifikasi untuk Administrator Prodi (Membutuhkan Pemantauan)
                        kirimNotifikasiAdmin($conn, 'Pengajuan Judul Baru', "Mahasiswa $nama (NIM: $nim) mengajukan judul baru kepada $nama_dosen_tujuan: \"$judul\".", 'admin_pengajuan_judul.php', 'pengajuan');

                        header("Location: pengajuan_judul_mahasiswa.php?pesan=sukses_tambah");
                        exit;
                    } else {
                        $error_message = "Gagal menyimpan pengajuan ke database: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}
}

// ===================================================================
// 2. PROSES PERBAIKAN & AJUKAN ULANG JUDUL (REVISI)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajukan_revisi') {
    $pengajuan_id   = (int)($_POST['pengajuan_id'] ?? 0);
    $judul          = trim($_POST['judul'] ?? '');
    $latar_belakang = trim($_POST['latar_belakang'] ?? '');
    $catatan_mhs    = trim($_POST['catatan_mahasiswa'] ?? 'Mengirimkan perbaikan judul sesuai arahan revisi.');

    // Validasi kepemilikan pengajuan (Mahasiswa hanya boleh merevisi miliknya sendiri)
    $cek_milik = mysqli_query($conn, "SELECT id, status, file_dokumen, file_gambar FROM pengajuan_judul WHERE id = $pengajuan_id AND mahasiswa_id = $mahasiswa_id LIMIT 1");
    if ($row_p = mysqli_fetch_assoc($cek_milik)) {
        if ($row_p['status'] !== 'Perlu revisi' && $row_p['status'] !== 'Sedang diperiksa' && $row_p['status'] !== 'Menunggu') {
            $error_message = "Pengajuan dengan status <strong>{$row_p['status']}</strong> tidak dapat diajukan ulang.";
        } else {
            $file_dokumen_name = $row_p['file_dokumen'];
            $file_gambar_name  = $row_p['file_gambar'];
            $upload_ok = true;

            // Jika ada upload dokumen baru pengganti
            if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
                $doc_file = $_FILES['file_dokumen'];
                $doc_ext = strtolower(pathinfo($doc_file['name'], PATHINFO_EXTENSION));
                if (in_array($doc_ext, ['pdf', 'doc', 'docx']) && $doc_file['size'] <= 5 * 1024 * 1024) {
                    $file_dokumen_name = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $doc_ext;
                    move_uploaded_file($doc_file['tmp_name'], $upload_dir . $file_dokumen_name);
                }
            }

            // Jika ada upload gambar baru pengganti
            if (isset($_FILES['file_gambar']) && $_FILES['file_gambar']['error'] === UPLOAD_ERR_OK) {
                $img_file = $_FILES['file_gambar'];
                $img_ext = strtolower(pathinfo($img_file['name'], PATHINFO_EXTENSION));
                if (in_array($img_ext, ['jpg', 'jpeg', 'png', 'webp']) && $img_file['size'] <= 3 * 1024 * 1024) {
                    $file_gambar_name = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $img_ext;
                    move_uploaded_file($img_file['tmp_name'], $upload_dir . $file_gambar_name);
                }
            }

            // Update status menjadi 'Sedang diperiksa'
            $update_stmt = mysqli_prepare($conn, "UPDATE pengajuan_judul SET judul = ?, latar_belakang = ?, file_dokumen = ?, file_gambar = ?, status = 'Sedang diperiksa' WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "ssssi", $judul, $latar_belakang, $file_dokumen_name, $file_gambar_name, $pengajuan_id);
            if (mysqli_stmt_execute($update_stmt)) {
                mysqli_stmt_close($update_stmt);

                // Tambahkan riwayat tanggapan mahasiswa
                $pesan_revisi = "Mahasiswa mengirim revisi: " . $catatan_mhs;
                $tanggapan_stmt = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'mahasiswa', ?, ?, 'Sedang diperiksa')");
                mysqli_stmt_bind_param($tanggapan_stmt, "iis", $pengajuan_id, $mahasiswa_id, $pesan_revisi);
                mysqli_stmt_execute($tanggapan_stmt);
                mysqli_stmt_close($tanggapan_stmt);

                // NOTIFIKASI OTOMATIS REVISI:
                $q_pinfo = mysqli_query($conn, "SELECT dosen_id FROM pengajuan_judul WHERE id = $pengajuan_id LIMIT 1");
                if ($row_pinfo = mysqli_fetch_assoc($q_pinfo)) {
                    $target_dosen_id = (int)$row_pinfo['dosen_id'];
                    if ($target_dosen_id > 0) {
                        kirimNotifikasi($conn, 'dosen', $target_dosen_id, 'Revisi Judul Diajukan Mahasiswa', "Mahasiswa $nama (NIM: $nim) telah mengirimkan perbaikan/revisi untuk judul: \"$judul\".", 'dashboard_dosen.php?buka_id=' . $pengajuan_id, 'revisi');
                    }
                }
                kirimNotifikasiAdmin($conn, 'Pembaruan Revisi Pengajuan Judul', "Mahasiswa $nama (NIM: $nim) mengirimkan revisi judul: \"$judul\".", 'admin_pengajuan_judul.php', 'revisi');

                header("Location: pengajuan_judul_mahasiswa.php?pesan=sukses_revisi");
                exit;
            }
        }
    } else {
        $error_message = "Akses ditolak: Data pengajuan tidak ditemukan atau bukan milik Anda!";
    }
}

// ===================================================================
// 3. PROSES KIRIM KOMENTAR / CHAT KEPADA DOSEN
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kirim_komentar') {
    $pengajuan_id = (int)($_POST['pengajuan_id'] ?? 0);
    $pesan        = trim($_POST['pesan'] ?? '');

    $cek_milik = mysqli_query($conn, "SELECT id, dosen_id, judul FROM pengajuan_judul WHERE id = $pengajuan_id AND mahasiswa_id = $mahasiswa_id LIMIT 1");
    if ($row_komentar = mysqli_fetch_assoc($cek_milik)) {
        if (!empty($pesan)) {
            $chat_stmt = mysqli_prepare($conn, "INSERT INTO tanggapan_pengajuan (pengajuan_id, pengirim_role, pengirim_id, pesan, status_perubahan) VALUES (?, 'mahasiswa', ?, ?, 'Komentar')");
            mysqli_stmt_bind_param($chat_stmt, "iis", $pengajuan_id, $mahasiswa_id, $pesan);
            mysqli_stmt_execute($chat_stmt);
            mysqli_stmt_close($chat_stmt);

            // NOTIFIKASI OTOMATIS: Dosen menerima notifikasi komentar baru
            $target_dosen_id = (int)$row_komentar['dosen_id'];
            $judul_komentar  = $row_komentar['judul'];
            if ($target_dosen_id > 0) {
                kirimNotifikasi($conn, 'dosen', $target_dosen_id, 'Pesan / Dokumen Baru dari Mahasiswa', "Mahasiswa $nama mengirim tanggapan pada judul \"$judul_komentar\": \"$pesan\"", 'dashboard_dosen.php?buka_id=' . $pengajuan_id, 'komentar');
            }

            header("Location: pengajuan_judul_mahasiswa.php?pesan=sukses_komentar&buka_id=" . $pengajuan_id);
            exit;
        }
    }
}

// ===================================================================
// 4. MENGAMBIL DATA PENGAJUAN KHUSUS MAHASISWA INI (RESIKO PRIVASI TERJAGA)
// ===================================================================
$query_pengajuan = "SELECT pj.*, d.nama_dosen, d.gelar_akademik, d.email AS email_dosen, d.nidn AS nidn_dosen 
                    FROM pengajuan_judul pj 
                    JOIN dosen d ON pj.dosen_id = d.id 
                    WHERE pj.mahasiswa_id = ? 
                    ORDER BY pj.updated_at DESC";
$stmt_list = mysqli_prepare($conn, $query_pengajuan);
mysqli_stmt_bind_param($stmt_list, "i", $mahasiswa_id);
mysqli_stmt_execute($stmt_list);
$result_pengajuan = mysqli_stmt_get_result($stmt_list);

// Mengambil daftar dosen aktif yang kuota bimbingannya BELUM PENUH (sisa kuota > 0)
// Ketentuan: Dosen yang sudah menerima 8 mahasiswa bimbingan otomatis TIDAK ditampilkan lagi di formulir pengajuan
$sql_dosen_tersedia = "
    SELECT d.id, d.nidn, d.nama_dosen, d.gelar_akademik,
           COALESCE(b.total_bimbingan, 0) AS total_bimbingan,
           (" . KUOTA_MAKSIMAL_DOSEN . " - COALESCE(b.total_bimbingan, 0)) AS sisa_kuota
    FROM dosen d
    LEFT JOIN (
        SELECT dosen_id, COUNT(DISTINCT mahasiswa_id) AS total_bimbingan
        FROM pengajuan_judul
        WHERE status = 'Diterima'
        GROUP BY dosen_id
    ) b ON d.id = b.dosen_id
    WHERE d.status = 'Aktif'
      AND (" . KUOTA_MAKSIMAL_DOSEN . " - COALESCE(b.total_bimbingan, 0)) > 0
    ORDER BY d.nama_dosen ASC
";
$dosen_pilihan = mysqli_query($conn, $sql_dosen_tersedia);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Judul Penelitian - SIM-SEMSID Politeknik Negeri Lhokseumawe</title>
    
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Utama -->
    <link rel="stylesheet" href="style.css">

    <style>
        .badge-status-menunggu {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .badge-status-sedang-diperiksa {
            background-color: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }
        .badge-status-perlu-revisi {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .badge-status-diterima {
            background-color: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .badge-status-ditolak {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .submission-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            transition: var(--transition);
        }

        .submission-card:hover {
            border-color: var(--primary-border);
            box-shadow: 0 8px 24px rgba(234, 88, 12, 0.08);
        }

        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .submission-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 0;
            line-height: 1.4;
        }

        .meta-tags {
            display: flex;
            gap: 14px;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .file-attachment-box {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 14px 0;
        }

        .file-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.84rem;
            color: #334155;
            text-decoration: none;
            transition: var(--transition);
        }

        .file-pill:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .revision-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            margin: 14px 0;
            color: #92400e;
            font-size: 0.9rem;
        }

        .rejected-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            margin: 14px 0;
            color: #991b1b;
            font-size: 0.9rem;
        }

        /* Timeline Komunikasi Mahasiswa - Dosen */
        .chat-thread-container {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .chat-bubble {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 12px;
            max-width: 85%;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .chat-bubble.dosen {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e3a8a;
            margin-right: auto;
        }

        .chat-bubble.mahasiswa {
            background: var(--primary-light);
            border: 1px solid var(--primary-border);
            color: #9a3412;
            margin-left: auto;
        }

        .chat-meta {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

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
            max-width: 650px;
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
                <a href="logout.php" class="btn-logout" onclick="return confirm('Apakah Anda yakin ingin logout?');">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Konten Utama Pengajuan Judul -->
    <main class="dashboard-main">
        <div class="container">

            <!-- Navigasi Menu Mahasiswa -->
            <div style="display: flex; gap: 10px; margin-bottom: 22px; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
                <a href="dashboard_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-tachometer-alt"></i> Dashboard Utama
                </a>
                <a href="pengajuan_judul_mahasiswa.php" class="btn btn-primary" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-lightbulb"></i> Pengajuan Judul
                </a>
                <a href="bimbingan_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-book-reader"></i> Riwayat Bimbingan
                </a>
                <a href="dokumen_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-file-arrow-up"></i> Upload Dokumen
                </a>
                <a href="index.php" class="btn btn-outline-admin" style="margin-left: auto; font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-external-link-alt"></i> Lihat Halaman Utama
                </a>
            </div>

            <!-- Banner Sambutan -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2>Sistem Pengajuan Judul Penelitian & Komunikasi Bimbingan 📝</h2>
                    <p>
                        Ajukan usulan judul penelitian atau skripsi kepada dosen pembimbing Jurusan Teknologi Informasi dan Komputer (TIK). Pantau status telaah, catatan revisi, dan diskusikan ide penelitian Anda secara langsung.
                    </p>
                </div>
                <div class="welcome-badge">
                    <i class="fas fa-user-graduate"></i> <?= htmlspecialchars($nama); ?>
                </div>
            </div>

            <!-- Notifikasi Pesan Feedback -->
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

            <!-- Bar Aksi Tombol Pengajuan Baru -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3 style="margin: 0; font-size: 1.2rem; color: var(--secondary);">
                        <i class="fas fa-folder-open" style="color: var(--primary);"></i> Riwayat Pengajuan Judul Anda
                    </h3>
                    <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
                        Hanya Anda yang dapat melihat daftar usulan judul dan tanggapan dari dosen ini.
                    </p>
                </div>
                <button type="button" class="btn btn-primary" onclick="bukaModalBaru();" style="padding: 10px 22px;">
                    <i class="fas fa-plus-circle"></i> Ajukan Judul Penelitian Baru
                </button>
            </div>

            <!-- Daftar Pengajuan Judul Mahasiswa -->
            <?php if ($result_pengajuan && mysqli_num_rows($result_pengajuan) > 0): ?>
                <?php while ($p = mysqli_fetch_assoc($result_pengajuan)): ?>
                    <div class="submission-card" id="card_<?= $p['id']; ?>">
                        <div class="submission-header">
                            <div>
                                <h4 class="submission-title"><?= htmlspecialchars($p['judul']); ?></h4>
                            </div>
                            <div>
                                <?php
                                $st = $p['status'];
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
                                    <i class="fas <?= $icon_class; ?>"></i> Status: <?= htmlspecialchars($st); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Meta Info Dosen & Waktu -->
                        <div class="meta-tags">
                            <span><i class="fas fa-user-tie" style="color: var(--primary);"></i> Dosen Tujuan: <strong><?= htmlspecialchars($p['nama_dosen']); ?>, <?= htmlspecialchars($p['gelar_akademik']); ?></strong> (NIDN: <?= htmlspecialchars($p['nidn_dosen']); ?>)</span>
                            <span><i class="fas fa-calendar-day"></i> Diajukan: <?= date('d M Y, H:i', strtotime($p['created_at'])); ?> WIB</span>
                            <span><i class="fas fa-rotate"></i> Terakhir Diperbarui: <?= date('d M Y, H:i', strtotime($p['updated_at'])); ?> WIB</span>
                        </div>

                        <!-- Latar Belakang / Deskripsi -->
                        <div style="background: #f8fafc; padding: 14px 18px; border-radius: 8px; font-size: 0.9rem; line-height: 1.6; color: #334155; margin-bottom: 12px; border: 1px solid #e2e8f0;">
                            <strong><i class="fas fa-align-left" style="color: var(--primary);"></i> Deskripsi / Latar Belakang Masalah:</strong>
                            <p style="margin: 6px 0 0 0; white-space: pre-line;"><?= htmlspecialchars($p['latar_belakang']); ?></p>
                        </div>

                        <!-- Berkas Lampiran Gambar & Dokumen -->
                        <div class="file-attachment-box">
                            <?php if (!empty($p['file_dokumen'])): ?>
                                <a href="uploads/judul/<?= urlencode($p['file_dokumen']); ?>" target="_blank" class="file-pill">
                                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i> Unduh Dokumen Usulan (<?= htmlspecialchars(pathinfo($p['file_dokumen'], PATHINFO_EXTENSION)); ?>)
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($p['file_gambar'])): ?>
                                <a href="uploads/judul/<?= urlencode($p['file_gambar']); ?>" target="_blank" class="file-pill">
                                    <i class="fas fa-image" style="color: #2563eb;"></i> Lihat Gambar / Diagram Arsitektur
                                </a>
                            <?php endif; ?>

                            <?php if (empty($p['file_dokumen']) && empty($p['file_gambar'])): ?>
                                <span style="font-size: 0.8rem; color: #94a3b8;"><i class="fas fa-paperclip"></i> Tidak ada lampiran berkas pendukung.</span>
                            <?php endif; ?>
                        </div>

                        <!-- Catatan Revisi dari Dosen jika status Perlu revisi -->
                        <?php if ($p['status'] === 'Perlu revisi' && !empty($p['catatan_revisi'])): ?>
                            <div class="revision-box">
                                <div style="font-weight: 700; margin-bottom: 4px;">
                                    <i class="fas fa-triangle-exclamation"></i> Catatan Revisi dari Dosen:
                                </div>
                                <div style="white-space: pre-line;"><?= htmlspecialchars($p['catatan_revisi']); ?></div>
                                <div style="margin-top: 10px;">
                                    <button type="button" class="btn btn-primary" onclick='bukaModalRevisi(<?= json_encode($p); ?>);' style="padding: 8px 18px; font-size: 0.85rem;">
                                        <i class="fas fa-pen-to-square"></i> Perbaiki & Ajukan Ulang Judul
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Info Diterima jika status Diterima -->
                        <?php if ($p['status'] === 'Diterima'): ?>
                            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a; border-radius: 8px; padding: 14px 18px; margin-bottom: 14px; color: #166534;">
                                <div style="font-weight: 700; margin-bottom: 4px;">
                                    <i class="fas fa-circle-check"></i> Judul Penelitian Disetujui (Diterima):
                                </div>
                                <div>Selamat! Usulan judul penelitian Anda telah disetujui oleh Dosen Pembimbing. Silakan lanjutkan ke penyusunan proposal seminar.</div>
                                <?php if (!empty($p['catatan_revisi'])): ?>
                                    <div style="margin-top: 6px; font-size: 0.88rem; color: #15803d;"><strong>Catatan / Arahan Dosen:</strong> <?= htmlspecialchars($p['catatan_revisi']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Alasan Penolakan jika status Ditolak -->
                        <?php if ($p['status'] === 'Ditolak' && !empty($p['alasan_penolakan'])): ?>
                            <div class="rejected-box">
                                <div style="font-weight: 700; margin-bottom: 4px;">
                                    <i class="fas fa-circle-xmark"></i> Alasan Penolakan oleh Dosen:
                                </div>
                                <div style="white-space: pre-line;"><?= htmlspecialchars($p['alasan_penolakan']); ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Riwayat Komunikasi & Balasan Diskusi -->
                        <div class="chat-thread-container">
                            <h5 style="font-size: 0.92rem; color: var(--secondary); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-comments" style="color: var(--primary);"></i> Riwayat Komunikasi & Tanggapan:
                            </h5>

                            <?php
                            $p_id = (int)$p['id'];
                            $query_chat = "SELECT * FROM tanggapan_pengajuan WHERE pengajuan_id = $p_id ORDER BY created_at ASC";
                            $res_chat   = mysqli_query($conn, $query_chat);
                            ?>

                            <?php if ($res_chat && mysqli_num_rows($res_chat) > 0): ?>
                                <div style="max-height: 250px; overflow-y: auto; padding: 10px; background: #fdfdfd; border-radius: 8px; border: 1px solid #f1f5f9; margin-bottom: 12px;">
                                    <?php while ($chat = mysqli_fetch_assoc($res_chat)): ?>
                                        <div class="chat-bubble <?= ($chat['pengirim_role'] === 'dosen') ? 'dosen' : 'mahasiswa'; ?>">
                                            <div>
                                                <strong>
                                                    <?php if ($chat['pengirim_role'] === 'dosen'): ?>
                                                        <i class="fas fa-user-tie"></i> Dosen (<?= htmlspecialchars($p['nama_dosen']); ?>):
                                                    <?php else: ?>
                                                        <i class="fas fa-user-graduate"></i> Anda (Mahasiswa):
                                                    <?php endif; ?>
                                                </strong>
                                            </div>
                                            <div style="margin-top: 4px;"><?= nl2br(htmlspecialchars($chat['pesan'])); ?></div>
                                            <div class="chat-meta">
                                                <span><i class="fas fa-tag"></i> <?= htmlspecialchars($chat['status_perubahan']); ?></span>
                                                <span><?= date('d/m/Y H:i', strtotime($chat['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p style="font-size: 0.82rem; color: #94a3b8; font-style: italic; margin-bottom: 10px;">
                                    Belum ada catatan atau tanggapan tambahan dari dosen.
                                </p>
                            <?php endif; ?>

                            <!-- Form Kirim Pesan / Tanggapan kepada Dosen -->
                            <?php if ($p['status'] !== 'Diterima' && $p['status'] !== 'Ditolak'): ?>
                                <form action="pengajuan_judul_mahasiswa.php" method="POST" style="display: flex; gap: 8px;">
                                    <input type="hidden" name="action" value="kirim_komentar">
                                    <input type="hidden" name="pengajuan_id" value="<?= $p['id']; ?>">
                                    <input type="text" name="pesan" placeholder="Ketik pesan atau pertanyaan untuk dosen..." required style="flex: 1; padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.85rem; outline: none;">
                                    <button type="submit" class="btn btn-outline-primary" style="padding: 9px 16px; font-size: 0.85rem;">
                                        <i class="fas fa-paper-plane"></i> Kirim
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="dashboard-panel" style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-lightbulb" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                    <h4 style="color: var(--secondary); margin-bottom: 6px;">Belum Ada Pengajuan Judul</h4>
                    <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 500px; margin: 0 auto 18px auto;">
                        Anda belum mengajukan judul penelitian atau skripsi. Klik tombol di bawah untuk mengajukan usulan judul pertama Anda kepada dosen pembimbing.
                    </p>
                    <button type="button" class="btn btn-primary" onclick="bukaModalBaru();">
                        <i class="fas fa-plus-circle"></i> Ajukan Judul Penelitian Sekarang
                    </button>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- ===================================================================
         MODAL 1: FORM PENGAJUAN JUDUL BARU
         =================================================================== -->
    <div class="modal-custom" id="modalBaru">
        <div class="modal-box">
            <div class="modal-header-orange">
                <h3 style="margin: 0; font-size: 1.15rem;"><i class="fas fa-lightbulb"></i> Formulir Pengajuan Judul Penelitian</h3>
                <button type="button" onclick="tutupModalBaru();" style="background: transparent; border: none; color: white; font-size: 1.4rem; cursor: pointer;">&times;</button>
            </div>
            <form action="pengajuan_judul_mahasiswa.php" method="POST" enctype="multipart/form-data" style="padding: 22px;">
                <input type="hidden" name="action" value="tambah_pengajuan">

                <div class="form-field" style="margin-bottom: 14px;">
                    <label for="dosen_id" style="font-weight: 700; font-size: 0.88rem; color: var(--secondary); margin-bottom: 6px; display: block;">
                        <i class="fas fa-user-tie" style="color: var(--primary);"></i> Dosen Tujuan Pengajuan:
                    </label>
                    <select id="dosen_id" name="dosen_id" required style="width: 100%; padding: 11px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem; background: white; outline: none; cursor: pointer;">
                        <option value="">-- Pilih Dosen Pembimbing dari Database --</option>
                        <?php if ($dosen_pilihan && mysqli_num_rows($dosen_pilihan) > 0): ?>
                            <?php mysqli_data_seek($dosen_pilihan, 0); ?>
                            <?php while ($d = mysqli_fetch_assoc($dosen_pilihan)): ?>
                                <option value="<?= $d['id']; ?>">
                                    <?= htmlspecialchars($d['nama_dosen']); ?>, <?= htmlspecialchars($d['gelar_akademik']); ?> (Sisa Kuota: <?= (int)$d['sisa_kuota']; ?>/<?= KUOTA_MAKSIMAL_DOSEN; ?> Mahasiswa)
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" disabled>-- Mohon maaf, saat ini semua dosen pembimbing telah mencapai kuota penuh (8/8) --</option>
                        <?php endif; ?>
                    </select>
                    <small style="color: #64748b; font-size: 0.78rem; display: block; margin-top: 5px;">
                        <i class="fas fa-info-circle" style="color: var(--primary);"></i> Ketentuan Kuota: Maksimal <?= KUOTA_MAKSIMAL_DOSEN; ?> mahasiswa bimbingan per dosen. Dosen yang kuotanya telah penuh (8/8) otomatis tidak ditampilkan.
                    </small>
                </div>

                <div class="form-field" style="margin-bottom: 14px;">
                    <label for="judul" style="font-weight: 700; font-size: 0.88rem; color: var(--secondary); margin-bottom: 6px; display: block;">
                        <i class="fas fa-book" style="color: var(--primary);"></i> Judul Penelitian / Skripsi:
                    </label>
                    <input type="text" id="judul" name="judul" placeholder="Contoh: Rancang Bangun Sistem Monitoring Kualitas Air..." required style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem; outline: none;">
                </div>

                <div class="form-field" style="margin-bottom: 14px;">
                    <label for="latar_belakang" style="font-weight: 700; font-size: 0.88rem; color: var(--secondary); margin-bottom: 6px; display: block;">
                        <i class="fas fa-align-left" style="color: var(--primary);"></i> Latar Belakang & Deskripsi Singkat Masalah:
                    </label>
                    <textarea id="latar_belakang" name="latar_belakang" rows="4" placeholder="Jelaskan latar belakang, rumusan masalah, dan tujuan penelitian..." required style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem; outline: none; font-family: inherit;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px;">
                    <div class="form-field">
                        <label for="file_dokumen" style="font-weight: 700; font-size: 0.82rem; color: var(--secondary); margin-bottom: 4px; display: block;">
                            <i class="fas fa-file-pdf" style="color: #dc2626;"></i> Dokumen Usulan (Opsional):
                        </label>
                        <input type="file" id="file_dokumen" name="file_dokumen" accept=".pdf,.doc,.docx" style="font-size: 0.8rem; width: 100%;">
                        <small style="color: #64748b; font-size: 0.75rem;">Format: PDF, DOC, DOCX (Maks 5MB)</small>
                    </div>

                    <div class="form-field">
                        <label for="file_gambar" style="font-weight: 700; font-size: 0.82rem; color: var(--secondary); margin-bottom: 4px; display: block;">
                            <i class="fas fa-image" style="color: #2563eb;"></i> Gambar / Diagram (Opsional):
                        </label>
                        <input type="file" id="file_gambar" name="file_gambar" accept="image/*" style="font-size: 0.8rem; width: 100%;">
                        <small style="color: #64748b; font-size: 0.75rem;">Format: JPG, PNG, WEBP (Maks 3MB)</small>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                    <button type="button" class="btn btn-outline-admin" onclick="tutupModalBaru();">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Usulan Judul</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===================================================================
         MODAL 2: FORM REVISI JUDUL
         =================================================================== -->
    <div class="modal-custom" id="modalRevisi">
        <div class="modal-box">
            <div class="modal-header-orange">
                <h3 style="margin: 0; font-size: 1.15rem;"><i class="fas fa-pen-to-square"></i> Perbaiki & Ajukan Ulang Judul</h3>
                <button type="button" onclick="tutupModalRevisi();" style="background: transparent; border: none; color: white; font-size: 1.4rem; cursor: pointer;">&times;</button>
            </div>
            <form action="pengajuan_judul_mahasiswa.php" method="POST" enctype="multipart/form-data" style="padding: 22px;">
                <input type="hidden" name="action" value="ajukan_revisi">
                <input type="hidden" name="pengajuan_id" id="revisi_pengajuan_id">

                <div class="form-field" style="margin-bottom: 14px;">
                    <label for="revisi_judul" style="font-weight: 700; font-size: 0.88rem; color: var(--secondary); margin-bottom: 6px; display: block;">
                        Judul Penelitian yang Diperbaiki:
                    </label>
                    <input type="text" id="revisi_judul" name="judul" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem; outline: none;">
                </div>

                <div class="form-field" style="margin-bottom: 14px;">
                    <label for="revisi_latar_belakang" style="font-weight: 700; font-size: 0.88rem; color: var(--secondary); margin-bottom: 6px; display: block;">
                        Latar Belakang / Rumusan Masalah Diperbarui:
                    </label>
                    <textarea id="revisi_latar_belakang" name="latar_belakang" rows="4" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.9rem; outline: none; font-family: inherit;"></textarea>
                </div>

                <div class="form-field" style="margin-bottom: 14px;">
                    <label for="catatan_mahasiswa" style="font-weight: 700; font-size: 0.88rem; color: var(--secondary); margin-bottom: 6px; display: block;">
                        <i class="fas fa-comment-dots" style="color: var(--primary);"></i> Penjelasan Perubahan yang Dilakukan (Catatan Mahasiswa):
                    </label>
                    <textarea id="catatan_mahasiswa" name="catatan_mahasiswa" rows="2" placeholder="Jelaskan poin perbaikan yang telah Anda lakukan sesuai catatan dosen..." required style="width: 100%; padding: 9px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: 0.88rem; outline: none; font-family: inherit;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px;">
                    <div class="form-field">
                        <label for="revisi_file_dokumen" style="font-weight: 700; font-size: 0.82rem; color: var(--secondary); margin-bottom: 4px; display: block;">
                            Ganti Dokumen Usulan Baru (Opsional):
                        </label>
                        <input type="file" id="revisi_file_dokumen" name="file_dokumen" accept=".pdf,.doc,.docx" style="font-size: 0.8rem; width: 100%;">
                    </div>

                    <div class="form-field">
                        <label for="revisi_file_gambar" style="font-weight: 700; font-size: 0.82rem; color: var(--secondary); margin-bottom: 4px; display: block;">
                            Ganti Gambar Diagram Baru (Opsional):
                        </label>
                        <input type="file" id="revisi_file_gambar" name="file_gambar" accept="image/*" style="font-size: 0.8rem; width: 100%;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                    <button type="button" class="btn btn-outline-admin" onclick="tutupModalRevisi();">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Kirimkan Perbaikan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer style="text-align: center; padding: 20px; font-size: 0.85rem; color: #64748b; background: white; border-top: 1px solid var(--border-color); margin-top: 40px;">
        &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Jurusan Teknologi Informasi dan Komputer (TIK), Politeknik Negeri Lhokseumawe.
    </footer>

    <!-- JavaScript Interaktif -->
    <script>
        function bukaModalBaru() {
            document.getElementById('modalBaru').classList.add('show');
            document.getElementById('judul').focus();
        }

        function tutupModalBaru() {
            document.getElementById('modalBaru').classList.remove('show');
        }

        function bukaModalRevisi(data) {
            document.getElementById('revisi_pengajuan_id').value = data.id;
            document.getElementById('revisi_judul').value = data.judul;
            document.getElementById('revisi_latar_belakang').value = data.latar_belakang;
            document.getElementById('modalRevisi').classList.add('show');
            document.getElementById('catatan_mahasiswa').focus();
        }

        function tutupModalRevisi() {
            document.getElementById('modalRevisi').classList.remove('show');
        }

        window.onclick = function(event) {
            const mBaru = document.getElementById('modalBaru');
            const mRevisi = document.getElementById('modalRevisi');
            if (event.target === mBaru) {
                tutupModalBaru();
            }
            if (event.target === mRevisi) {
                tutupModalRevisi();
            }
        };
    </script>
    <!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
    <script src="notifikasi.js"></script>
</body>
</html>
