<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - JURUSAN TIK
 * FILE: dokumen_mahasiswa.php (Upload Dokumen Persyaratan Mahasiswa)
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

// Direktori Unggahan Khusus Dokumen
$upload_dir = __DIR__ . '/uploads/dokumen/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ===================================================================
// 1. PROSES FORMULIR UPLOAD DOKUMEN BARU
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_dokumen') {
    $jenis_dokumen = trim($_POST['jenis_dokumen'] ?? '');
    $judul_dokumen = trim($_POST['judul_dokumen'] ?? '');
    $keterangan    = trim($_POST['keterangan'] ?? '');

    $allowed_jenis = [
        'File proposal tugas akhir',
        'File laporan tugas akhir',
        'Bukti persyaratan seminar',
        'Dokumen persyaratan sidang',
        'Dokumen pendukung lainnya'
    ];

    if (empty($jenis_dokumen) || !in_array($jenis_dokumen, $allowed_jenis)) {
        $error_message = "Silakan pilih kategori jenis dokumen yang valid.";
    } elseif (empty($judul_dokumen)) {
        $error_message = "Judul atau nama dokumen wajib diisi.";
    } elseif (!isset($_FILES['file_dokumen']) || $_FILES['file_dokumen']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['file_dokumen']['error'] ?? 0;
        $error_message = ($error_code === UPLOAD_ERR_INI_SIZE || $error_code === UPLOAD_ERR_FORM_SIZE) 
            ? "Ukuran file terlalu besar! Maksimal 10 MB." 
            : "Silakan pilih berkas dokumen yang akan diunggah.";
    } else {
        $file       = $_FILES['file_dokumen'];
        $file_name  = basename($file['name']);
        $file_size  = (int)$file['size'];
        $file_tmp   = $file['tmp_name'];
        $ext        = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validasi Ekstensi Aman
        $allowed_exts = ['pdf', 'doc', 'docx', 'zip'];
        // Validasi Ukuran Maksimal 10 MB
        $max_size = 10 * 1024 * 1024;

        // Validasi MIME Type Asli dengan Fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        $allowed_mimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed'
        ];

        // Cegah file berbahaya atau ekstensi bertingkat (.php.pdf)
        if (!in_array($ext, $allowed_exts) || preg_match('/\.(php|phtml|exe|sh|pl|cgi|py|asp|aspx)/i', $file_name)) {
            $error_message = "Format file tidak diizinkan! Hanya dokumen berformat PDF, DOC, DOCX, dan ZIP yang diperbolehkan.";
        } elseif ($file_size > $max_size) {
            $error_message = "Ukuran file melebihi batas maksimal (10 MB). Ukuran file Anda: " . round($file_size / (1024 * 1024), 2) . " MB.";
        } elseif (!in_array($mime_type, $allowed_mimes) && $ext !== 'docx') { // beberapa server mendeteksi zip untuk docx
            $error_message = "Tipe konten berkas tidak valid ($mime_type). Harap unggah dokumen resmi.";
        } else {
            // Generate nama file acak & unik untuk mencegah collision dan overwrite
            $safe_filename = 'dok_mhs_' . $mahasiswa_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest_path     = $upload_dir . $safe_filename;

            if (move_uploaded_file($file_tmp, $dest_path)) {
                // Simpan metadata ke tabel dokumen_mahasiswa
                $stmt_insert = mysqli_prepare($conn, "
                    INSERT INTO dokumen_mahasiswa 
                    (mahasiswa_id, jenis_dokumen, judul_dokumen, nama_file_asli, nama_file_simpan, tipe_file, ukuran_file, keterangan, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu pemeriksaan')
                ");
                mysqli_stmt_bind_param(
                    $stmt_insert, 
                    "isssssis", 
                    $mahasiswa_id, 
                    $jenis_dokumen, 
                    $judul_dokumen, 
                    $file_name, 
                    $safe_filename, 
                    $mime_type, 
                    $file_size, 
                    $keterangan
                );

                if (mysqli_stmt_execute($stmt_insert)) {
                    $new_dok_id = mysqli_insert_id($conn);

                    // Buat Notifikasi Otomatis untuk Admin
                    $notif_pesan = "Mahasiswa $nama (NIM: $nim) telah mengunggah berkas '$jenis_dokumen': $judul_dokumen.";
                    $notif_judul = "Dokumen Baru Diunggah";
                    $notif_tautan = "admin_dokumen.php?id=" . $new_dok_id;
                    $stmt_notif = mysqli_prepare($conn, "
                        INSERT INTO notifikasi (penerima_role, penerima_id, judul, pesan, tautan, tipe, is_read) 
                        VALUES ('admin', 0, ?, ?, ?, 'dokumen', 0)
                    ");
                    mysqli_stmt_bind_param($stmt_notif, "sss", $notif_judul, $notif_pesan, $notif_tautan);
                    mysqli_stmt_execute($stmt_notif);

                    $success_message = "Berkas <strong>" . htmlspecialchars($file_name) . "</strong> berhasil diunggah dan diteruskan ke Administrator untuk pemeriksaan!";
                } else {
                    // Hapus file fisik jika insert DB gagal
                    if (file_exists($dest_path)) unlink($dest_path);
                    $error_message = "Terjadi kesalahan database: " . mysqli_error($conn);
                }
            } else {
                $error_message = "Gagal memindahkan file yang diunggah ke direktori server. Periksa hak akses folder uploads.";
            }
        }
    }
}

// ===================================================================
// 2. PROSES HAPUS DOKUMEN (Hanya jika status belum diterima)
// ===================================================================
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus' && isset($_GET['id'])) {
    $hapus_id = (int)$_GET['id'];
    $stmt_cek = mysqli_prepare($conn, "SELECT nama_file_simpan, status FROM dokumen_mahasiswa WHERE id = ? AND mahasiswa_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_cek, "ii", $hapus_id, $mahasiswa_id);
    mysqli_stmt_execute($stmt_cek);
    $res_cek = mysqli_stmt_get_result($stmt_cek);
    $doc = mysqli_fetch_assoc($res_cek);

    if ($doc) {
        if ($doc['status'] === 'Diterima') {
            $error_message = "Dokumen yang telah berstatus 'Diterima' tidak dapat dihapus secara mandiri.";
        } else {
            $file_del = $upload_dir . $doc['nama_file_simpan'];
            if (file_exists($file_del)) {
                unlink($file_del);
            }
            $stmt_del = mysqli_prepare($conn, "DELETE FROM dokumen_mahasiswa WHERE id = ? AND mahasiswa_id = ?");
            mysqli_stmt_bind_param($stmt_del, "ii", $hapus_id, $mahasiswa_id);
            mysqli_stmt_execute($stmt_del);
            $success_message = "Dokumen berhasil dihapus dari daftar persyaratan.";
        }
    }
}

// ===================================================================
// 3. AMBIL DAFTAR DOKUMEN MILIK MAHASISWA SENDIRI
// ===================================================================
$stmt_list = mysqli_prepare($conn, "
    SELECT * FROM dokumen_mahasiswa 
    WHERE mahasiswa_id = ? 
    ORDER BY created_at DESC
");
mysqli_stmt_bind_param($stmt_list, "i", $mahasiswa_id);
mysqli_stmt_execute($stmt_list);
$res_docs = mysqli_stmt_get_result($stmt_list);

$total_docs = mysqli_num_rows($res_docs);

// Hitung dokumen yang menunggu, diterima, perlu perbaikan
$count_menunggu = 0;
$count_diterima = 0;
$count_revisi   = 0;

$docs_data = [];
while ($d = mysqli_fetch_assoc($res_docs)) {
    if ($d['status'] === 'Menunggu pemeriksaan') $count_menunggu++;
    elseif ($d['status'] === 'Diterima') $count_diterima++;
    elseif ($d['status'] === 'Perlu perbaikan') $count_revisi++;
    $docs_data[] = $d;
}

// Format Ukuran File Human-Readable
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Dokumen Persyaratan - SIM-SEM SID PNL</title>

    <!-- FontAwesome & CSS Utama -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .upload-dropzone {
            border: 2px dashed #94a3b8;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .upload-dropzone:hover, .upload-dropzone.dragover {
            border-color: #ea580c;
            background: #fff7ed;
        }
        .upload-dropzone input[type="file"] {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-preview-card {
            display: none;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 14px;
            align-items: center;
            justify-content: space-between;
        }
        .status-badge-custom {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .badge-menunggu {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .badge-diterima {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .badge-revisi {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>

    <!-- Header Dashboard Mahasiswa -->
    <header class="dashboard-header">
        <div class="container header-container">
            <div class="header-brand">
                <a href="index.php" class="logo-area">
                    <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="logo-text">
                        <h1>SIM-SEM SID</h1>
                        <span>POLITEKNIK NEGERI LHOKSEUMAWE</span>
                    </div>
                </a>
            </div>

            <div class="user-profile-menu">
                <div class="user-badge-info">
                    <div class="user-name"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($nama); ?></div>
                    <div class="user-role">Mahasiswa &bull; NIM: <?= htmlspecialchars($nim); ?></div>
                </div>
                <!-- Ikon Lonceng Notifikasi Real-time -->
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <a href="logout.php" class="btn-logout" onclick="return confirm('Apakah Anda yakin ingin keluar dari akun?');">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

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
                <a href="bimbingan_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-book-reader"></i> Riwayat Bimbingan
                </a>
                <a href="dokumen_mahasiswa.php" class="btn btn-primary" style="font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-file-arrow-up"></i> Upload Dokumen
                </a>
                <a href="index.php" class="btn btn-outline-admin" style="margin-left: auto; font-size: 0.88rem; padding: 8px 16px;">
                    <i class="fas fa-external-link-alt"></i> Lihat Halaman Utama
                </a>
            </div>

            <!-- Pesan Notifikasi Sukses / Gagal -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <i class="fas fa-check-circle" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div><?= $success_message; ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div><?= $error_message; ?></div>
                </div>
            <?php endif; ?>

            <!-- Banner Sambutan & Ringkasan Dokumen -->
            <div class="welcome-banner" style="margin-bottom: 25px;">
                <div class="welcome-text">
                    <h2>Unggah Dokumen Seminar & Sidang Akhir 📄</h2>
                    <p>
                        Unggah berkas digital proposal tugas akhir, laporan akhir, bukti bebas laboratorium, dan syarat pendaftaran ujian lainnya. 
                        Seluruh berkas akan diperiksa secara bertahap oleh Administrator Jurusan TIK.
                    </p>
                </div>
                <div class="welcome-stats" style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                        <span style="font-size: 1.4rem; font-weight: 800; display: block;"><?= $total_docs; ?></span>
                        <span style="font-size: 0.76rem; text-transform: uppercase;">Total Dokumen</span>
                    </div>
                    <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                        <span style="font-size: 1.4rem; font-weight: 800; display: block; color: #fde047;"><?= $count_menunggu; ?></span>
                        <span style="font-size: 0.76rem; text-transform: uppercase;">Menunggu</span>
                    </div>
                    <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                        <span style="font-size: 1.4rem; font-weight: 800; display: block; color: #86efac;"><?= $count_diterima; ?></span>
                        <span style="font-size: 0.76rem; text-transform: uppercase;">Diterima</span>
                    </div>
                    <div style="background: rgba(255,255,255,0.15); padding: 10px 16px; border-radius: 8px; text-align: center;">
                        <span style="font-size: 1.4rem; font-weight: 800; display: block; color: #fca5a5;"><?= $count_revisi; ?></span>
                        <span style="font-size: 0.76rem; text-transform: uppercase;">Perlu Revisi</span>
                    </div>
                </div>
            </div>

            <!-- Bagian Dua Kolom: Form Upload & Petunjuk Teknis -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 30px;">

                <!-- Form Upload Dokumen -->
                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <h3><i class="fas fa-cloud-arrow-up" style="color: var(--primary);"></i> Formulir Unggah Dokumen</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="dokumen_mahasiswa.php" enctype="multipart/form-data" id="formUploadDokumen">
                            <input type="hidden" name="action" value="upload_dokumen">

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.88rem;">
                                    Jenis Dokumen <span style="color: #ef4444;">*</span>
                                </label>
                                <select name="jenis_dokumen" id="jenis_dokumen" class="form-control" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                                    <option value="">-- Pilih Kategori Dokumen --</option>
                                    <option value="File proposal tugas akhir">File proposal tugas akhir</option>
                                    <option value="File laporan tugas akhir">File laporan tugas akhir</option>
                                    <option value="Bukti persyaratan seminar">Bukti persyaratan seminar</option>
                                    <option value="Dokumen persyaratan sidang">Dokumen persyaratan sidang</option>
                                    <option value="Dokumen pendukung lainnya">Dokumen pendukung lainnya</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.88rem;">
                                    Judul / Nama Dokumen <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" name="judul_dokumen" id="judul_dokumen" class="form-control" placeholder="Contoh: Proposal Tugas Akhir Versi ACC Pembimbing" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;">
                            </div>

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.88rem;">
                                    Pilih Berkas Dokumen <span style="color: #ef4444;">*</span>
                                </label>
                                <div class="upload-dropzone" id="dropzoneBox">
                                    <input type="file" name="file_dokumen" id="file_dokumen" accept=".pdf,.doc,.docx,.zip" required>
                                    <i class="fas fa-file-circle-plus" style="font-size: 2.2rem; color: #ea580c; margin-bottom: 8px; display: block;"></i>
                                    <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">Klik atau Tarik File ke Sini</div>
                                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">Mendukung format: PDF, DOC, DOCX, ZIP (Maksimal 10 MB)</div>
                                </div>

                                <!-- Box Preview Informasi File yang Dipilih -->
                                <div class="file-preview-card" id="filePreviewCard">
                                    <div style="display: flex; align-items: center; gap: 12px; overflow: hidden;">
                                        <i class="fas fa-file-pdf" id="fileIconPreview" style="font-size: 1.8rem; color: #ea580c; flex-shrink: 0;"></i>
                                        <div style="overflow: hidden;">
                                            <div id="previewFileName" style="font-weight: 700; font-size: 0.9rem; color: #0f172a; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;">-</div>
                                            <div style="font-size: 0.78rem; color: #64748b;">
                                                <span id="previewFileType">Tipe: -</span> &bull; <span id="previewFileSize">Ukuran: -</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" id="btnCancelFile" title="Batalkan file ini" style="background: none; border: none; color: #ef4444; font-size: 1.1rem; cursor: pointer; padding: 4px 8px;">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.88rem;">
                                    Catatan / Keterangan Tambahan (Opsional)
                                </label>
                                <textarea name="keterangan" rows="2" class="form-control" placeholder="Contoh: Sudah diverifikasi dan disetujui Dosen Pembimbing Utama." style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem;"></textarea>
                            </div>

                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn btn-primary" id="btnSubmitUpload" style="flex: 1; justify-content: center; padding: 11px;">
                                    <i class="fas fa-upload"></i> Unggah Dokumen
                                </button>
                                <button type="reset" class="btn btn-outline-admin" id="btnResetForm" style="padding: 11px 16px;">
                                    <i class="fas fa-rotate-left"></i> Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Petunjuk Keamanan & Ketentuan Dokumen -->
                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <h3><i class="fas fa-circle-info" style="color: #0284c7;"></i> Panduan & Ketentuan Berkas</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; gap: 14px; margin-bottom: 18px;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">1</div>
                            <div>
                                <h5 style="margin: 0 0 4px; font-size: 0.95rem; color: #0f172a;">Format Dokumen Resmi</h5>
                                <p style="margin: 0; font-size: 0.84rem; color: #64748b; line-height: 1.45;">
                                    Gunakan format <strong>PDF</strong> untuk naskah proposal dan laporan skripsi lengkap beserta lembar pengesahan tanda tangan. Format <strong>ZIP</strong> dapat digunakan jika menyertakan lampiran kode sumber/dataset.
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; gap: 14px; margin-bottom: 18px;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">2</div>
                            <div>
                                <h5 style="margin: 0 0 4px; font-size: 0.95rem; color: #0f172a;">Batasan Ukuran File</h5>
                                <p style="margin: 0; font-size: 0.84rem; color: #64748b; line-height: 1.45;">
                                    Batas kapasitas maksimal berkas per unggahan adalah <strong>10 Megabytes (MB)</strong>. Kompres berkas gambar/PDF jika ukurannya terlalu besar.
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; gap: 14px; margin-bottom: 18px;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0;">3</div>
                            <div>
                                <h5 style="margin: 0 0 4px; font-size: 0.95rem; color: #0f172a;">Alur Status Pemeriksaan</h5>
                                <p style="margin: 0; font-size: 0.84rem; color: #64748b; line-height: 1.45;">
                                    Setelah diunggah, status awal adalah <span class="status-badge-custom badge-menunggu" style="font-size: 0.72rem;">Menunggu pemeriksaan</span>. Jika admin mendeteksi ketidaksesuaian, status berubah menjadi <span class="status-badge-custom badge-revisi" style="font-size: 0.72rem;">Perlu perbaikan</span> dengan catatan arahan revisi.
                                </p>
                            </div>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px;">
                            <div style="font-weight: 700; color: #1e3a8a; font-size: 0.86rem; margin-bottom: 4px;">
                                <i class="fas fa-shield-halved"></i> Perlindungan Privasi Data
                            </div>
                            <div style="font-size: 0.82rem; color: #64748b; line-height: 1.45;">
                                Berkas Anda tersimpan dalam ruang penyimpanan terenkripsi yang hanya dapat diakses oleh Anda dan Administrator Program Studi TIK PNL.
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Tabel Riwayat Dokumen yang Diunggah Mahasiswa -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <h3><i class="fas fa-folder-open" style="color: var(--primary);"></i> Daftar Dokumen Persyaratan yang Sudah Diunggah</h3>
                    <span style="font-size: 0.82rem; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 20px;">
                        Total: <strong><?= $total_docs; ?> Berkas</strong>
                    </span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($docs_data)): ?>
                        <div style="text-align: center; padding: 50px 20px; color: #64748b;">
                            <i class="fas fa-folder-open" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                            <h4 style="color: #334155; margin: 0 0 6px;">Belum Ada Dokumen yang Diunggah</h4>
                            <p style="margin: 0; font-size: 0.88rem;">Gunakan formulir di atas untuk mengunggah proposal, laporan, atau bukti syarat seminar/sidang Anda.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                                <thead>
                                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                                        <th style="padding: 12px 16px; width: 40px;">No</th>
                                        <th style="padding: 12px 16px;">Jenis & Judul Dokumen</th>
                                        <th style="padding: 12px 16px;">Nama Berkas</th>
                                        <th style="padding: 12px 16px;">Ukuran</th>
                                        <th style="padding: 12px 16px;">Waktu Unggah</th>
                                        <th style="padding: 12px 16px;">Status Pemeriksaan</th>
                                        <th style="padding: 12px 16px;">Catatan Admin</th>
                                        <th style="padding: 12px 16px; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($docs_data as $idx => $d): 
                                        $badge_class = 'badge-menunggu';
                                        $badge_icon  = 'fa-clock';
                                        if ($d['status'] === 'Diterima') {
                                            $badge_class = 'badge-diterima';
                                            $badge_icon  = 'fa-circle-check';
                                        } elseif ($d['status'] === 'Perlu perbaikan') {
                                            $badge_class = 'badge-revisi';
                                            $badge_icon  = 'fa-circle-exclamation';
                                        }
                                        $file_url = 'uploads/dokumen/' . urlencode($d['nama_file_simpan']);
                                    ?>
                                        <tr style="border-bottom: 1px solid #e2e8f0;">
                                            <td style="padding: 14px 16px; font-weight: 700; color: #64748b;"><?= $idx + 1; ?></td>
                                            <td style="padding: 14px 16px;">
                                                <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($d['judul_dokumen']); ?></div>
                                                <div style="font-size: 0.78rem; color: #ea580c; font-weight: 600; margin-top: 2px;">
                                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($d['jenis_dokumen']); ?>
                                                </div>
                                                <?php if (!empty($d['keterangan'])): ?>
                                                    <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px; font-style: italic;">
                                                        "<?= htmlspecialchars($d['keterangan']); ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 14px 16px;">
                                                <span style="font-family: monospace; font-size: 0.82rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; display: inline-block; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($d['nama_file_asli']); ?>">
                                                    <?= htmlspecialchars($d['nama_file_asli']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 14px 16px; color: #475569; font-size: 0.82rem;">
                                                <?= formatBytes($d['ukuran_file']); ?>
                                            </td>
                                            <td style="padding: 14px 16px; color: #475569; font-size: 0.82rem;">
                                                <?= date('d M Y, H:i', strtotime($d['created_at'])); ?> WIB
                                            </td>
                                            <td style="padding: 14px 16px;">
                                                <span class="status-badge-custom <?= $badge_class; ?>">
                                                    <i class="fas <?= $badge_icon; ?>"></i> <?= htmlspecialchars($d['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 14px 16px; font-size: 0.82rem;">
                                                <?php if (!empty($d['catatan_admin'])): ?>
                                                    <div style="background: #fff7ed; border-left: 3px solid #ea580c; padding: 6px 10px; border-radius: 4px; color: #9a3412;">
                                                        <?= htmlspecialchars($d['catatan_admin']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8;">Belum ada catatan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 14px 16px; text-align: center; white-space: nowrap;">
                                                <a href="<?= $file_url; ?>" target="_blank" download="<?= htmlspecialchars($d['nama_file_asli']); ?>" class="btn btn-outline-admin" style="padding: 5px 10px; font-size: 0.78rem;" title="Unduh Berkas">
                                                    <i class="fas fa-download"></i> Unduh
                                                </a>
                                                <?php if ($d['status'] !== 'Diterima'): ?>
                                                    <a href="dokumen_mahasiswa.php?aksi=hapus&id=<?= $d['id']; ?>" class="btn btn-outline-admin" onclick="return confirm('Apakah Anda yakin ingin menghapus berkas ini?');" style="padding: 5px 10px; font-size: 0.78rem; color: #dc2626; border-color: #fca5a5;" title="Hapus Berkas">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container footer-bottom">
            <p>&copy; <?= date('Y'); ?> <strong>SIM-SEM SID</strong> - Politeknik Negeri Lhokseumawe. Program Studi Teknologi Informasi dan Komputer (TIK).</p>
        </div>
    </footer>

    <!-- JavaScript Interaktif Preview File & Validasi -->
    <script>
        const fileInput = document.getElementById('file_dokumen');
        const previewCard = document.getElementById('filePreviewCard');
        const previewName = document.getElementById('previewFileName');
        const previewType = document.getElementById('previewFileType');
        const previewSize = document.getElementById('previewFileSize');
        const previewIcon = document.getElementById('fileIconPreview');
        const btnCancel = document.getElementById('btnCancelFile');
        const btnReset = document.getElementById('btnResetForm');
        const dropzone = document.getElementById('dropzoneBox');

        function formatBytesJS(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    previewName.textContent = file.name;
                    previewType.textContent = 'Tipe: ' + (file.type || 'Dokumen ' + file.name.split('.').pop().toUpperCase());
                    previewSize.textContent = 'Ukuran: ' + formatBytesJS(file.size);

                    const ext = file.name.split('.').pop().toLowerCase();
                    if (ext === 'pdf') {
                        previewIcon.className = 'fas fa-file-pdf';
                        previewIcon.style.color = '#ef4444';
                    } else if (ext === 'doc' || ext === 'docx') {
                        previewIcon.className = 'fas fa-file-word';
                        previewIcon.style.color = '#2563eb';
                    } else if (ext === 'zip') {
                        previewIcon.className = 'fas fa-file-zipper';
                        previewIcon.style.color = '#ca8a04';
                    } else {
                        previewIcon.className = 'fas fa-file-alt';
                        previewIcon.style.color = '#64748b';
                    }

                    previewCard.style.display = 'flex';
                } else {
                    previewCard.style.display = 'none';
                }
            });
        }

        if (btnCancel) {
            btnCancel.addEventListener('click', function() {
                fileInput.value = '';
                previewCard.style.display = 'none';
            });
        }

        if (btnReset) {
            btnReset.addEventListener('click', function() {
                fileInput.value = '';
                previewCard.style.display = 'none';
            });
        }

        // Drag and drop visual cues
        if (dropzone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
            });
        }
    </script>
</body>
</html>
