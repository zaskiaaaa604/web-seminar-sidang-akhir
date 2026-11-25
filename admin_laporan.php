<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: admin_laporan.php (Pusat Cetak dan Unduh Laporan PDF & Excel)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';
require_once 'laporan_helper.php';

// Validasi Keamanan: Hanya admin yang berhak
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$admin_id = $_SESSION['admin_id'] ?? 0;
$nama     = $_SESSION['nama'] ?? 'Administrator';

// 1. DAFTAR JENIS LAPORAN
$daftar_laporan = getDaftarJenisLaporan();
$jenis = trim($_GET['jenis'] ?? 'mahasiswa');
if (!isset($daftar_laporan[$jenis])) {
    $jenis = 'mahasiswa';
}
$info_laporan = $daftar_laporan[$jenis];

// 2. TANGKAP FILTER DARI FORM
$filters = [
    'tanggal_mulai'   => trim($_GET['tanggal_mulai'] ?? ''),
    'tanggal_selesai' => trim($_GET['tanggal_selesai'] ?? ''),
    'status'          => trim($_GET['status'] ?? ''),
    'mahasiswa_id'    => (int)($_GET['mahasiswa_id'] ?? 0),
    'dosen_id'        => (int)($_GET['dosen_id'] ?? 0),
    'ruangan_id'      => (int)($_GET['ruangan_id'] ?? 0),
    'jenis_kegiatan'  => trim($_GET['jenis_kegiatan'] ?? ''),
    'search'          => trim($_GET['search'] ?? '')
];

// 3. AMBIL DATA LAPORAN (UNTUK PRATINJAU)
$laporan_data = ambilDataLaporan($conn, $jenis, $filters);
$meta         = $laporan_data['meta'];
$headers      = $laporan_data['headers'];
$rows         = $laporan_data['rows'];

// Master data untuk dropdown filter
$opt_mhs   = mysqli_query($conn, "SELECT id, nim, nama FROM mahasiswa ORDER BY nama ASC");
$opt_dosen = mysqli_query($conn, "SELECT id, nidn, nama_dosen, gelar_akademik FROM dosen ORDER BY nama_dosen ASC");
$opt_ruang = mysqli_query($conn, "SELECT id, kode_ruangan, nama_ruangan, gedung FROM ruangan ORDER BY kode_ruangan ASC");

// Query string untuk link unduh dan cetak
$query_params = array_merge(['jenis' => $jenis], array_filter($filters, function($v) { return $v !== '' && $v !== 0; }));
$url_export_pdf   = 'export_laporan_pdf.php?' . http_build_query($query_params);
$url_export_excel = 'export_laporan_excel.php?' . http_build_query($query_params);
$url_cetak        = 'cetak_laporan.php?' . http_build_query($query_params);
$url_reset        = 'admin_laporan.php?jenis=' . urlencode($jenis);

$active_menu = 'laporan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($info_laporan['judul']); ?> - Panel Admin SIM-SEMSID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .laporan-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .laporan-pill-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #334155;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .laporan-pill-item:hover {
            border-color: #ea580c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.12);
            color: #ea580c;
        }
        .laporan-pill-item.active {
            background: linear-gradient(135deg, #1e3a8a, #0f172a);
            border-color: #1e3a8a;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.25);
        }
        .laporan-pill-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
            background: rgba(234, 88, 12, 0.12);
            color: #ea580c;
        }
        .laporan-pill-item.active .laporan-pill-icon {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }
        .laporan-pill-title {
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.25;
        }
        
        /* Action Button Bar */
        .btn-laporan-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-pdf {
            background: #dc2626;
            color: #ffffff;
        }
        .btn-pdf:hover {
            background: #b91c1c;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
            color: #ffffff;
        }
        .btn-excel {
            background: #16a34a;
            color: #ffffff;
        }
        .btn-excel:hover {
            background: #15803d;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
            color: #ffffff;
        }
        .btn-print {
            background: #0284c7;
            color: #ffffff;
        }
        .btn-print:hover {
            background: #0369a1;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
            color: #ffffff;
        }
        .btn-filter-submit {
            background: #ea580c;
            color: #ffffff;
        }
        .btn-filter-submit:hover {
            background: #c2410c;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.25);
            color: #ffffff;
        }

        .filter-panel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }
        .filter-field label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--admin-navy);
            margin-bottom: 5px;
        }
        .filter-field input, .filter-field select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.86rem;
            background: #ffffff;
            color: #1e293b;
        }
        .filter-field input:focus, .filter-field select:focus {
            outline: none;
            border-color: #ea580c;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        .badge-status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-align: center;
        }
    </style>
</head>
<body class="admin-body">

    <?php include 'admin_sidebar.php'; ?>

    <div class="admin-main-wrapper">
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button type="button" class="admin-mobile-toggle" onclick="toggleSidebar();"><i class="fas fa-bars"></i></button>
                <div class="admin-topbar-title">
                    <h1>Pusat Laporan & Rekapitulasi Data</h1>
                    <span>Cetak dan unduh laporan resmi berformat PDF dan Excel (10 Jenis Laporan SIM-SEMSID)</span>
                </div>
            </div>
            <div class="admin-topbar-right">
                <!-- Komponen Lonceng Notifikasi Admin -->
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <div style="font-size: 0.85rem; font-weight: 700; color: var(--admin-navy); background: #f1f5f9; padding: 7px 14px; border-radius: 8px;">
                    <i class="fas fa-calendar-check" style="color: #ea580c; margin-right: 6px;"></i>
                    <?= tglIndo(date('Y-m-d')); ?>
                </div>
            </div>
        </header>

        <main class="admin-content-body">

            <!-- PILIHAN 10 JENIS LAPORAN (SELECTOR PILLS) -->
            <div style="margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--admin-navy); display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-layer-group" style="color: #ea580c;"></i> Pilih Jenis Laporan (10 Kategori)
                    </h3>
                    <span style="font-size: 0.82rem; color: #64748b;">Klik salah satu untuk beralih jenis data</span>
                </div>

                <div class="laporan-card-grid">
                    <?php foreach ($daftar_laporan as $key => $lap): ?>
                        <a href="admin_laporan.php?jenis=<?= urlencode($key); ?>" class="laporan-pill-item <?= ($jenis === $key) ? 'active' : ''; ?>">
                            <div class="laporan-pill-icon">
                                <i class="fas <?= $lap['icon']; ?>"></i>
                            </div>
                            <div class="laporan-pill-title">
                                <?= htmlspecialchars($lap['judul']); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- PANEL FILTER LAPORAN -->
            <div class="admin-card-panel" style="margin-bottom: 20px;">
                <div class="admin-panel-header" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 16px;">
                    <div>
                        <h2 style="font-size: 1.15rem; color: var(--admin-navy); margin: 0; display: flex; align-items: center; gap: 10px;">
                            <i class="fas <?= $info_laporan['icon']; ?>" style="color: #ea580c;"></i>
                            <?= htmlspecialchars($info_laporan['judul']); ?>
                        </h2>
                        <span style="font-size: 0.84rem; color: #64748b; margin-top: 3px; display: block;">
                            <?= htmlspecialchars($info_laporan['deskripsi']); ?>
                        </span>
                    </div>
                </div>

                <form method="GET" action="admin_laporan.php" id="formFilterLaporan">
                    <input type="hidden" name="jenis" value="<?= htmlspecialchars($jenis); ?>">

                    <div class="filter-panel-grid">
                        <!-- Filter Tanggal Mulai -->
                        <div class="filter-field">
                            <label><i class="fas fa-calendar-day" style="color: #ea580c;"></i> Tanggal Mulai:</label>
                            <input type="date" name="tanggal_mulai" value="<?= htmlspecialchars($filters['tanggal_mulai']); ?>">
                        </div>

                        <!-- Filter Tanggal Selesai -->
                        <div class="filter-field">
                            <label><i class="fas fa-calendar-check" style="color: #ea580c;"></i> Tanggal Selesai:</label>
                            <input type="date" name="tanggal_selesai" value="<?= htmlspecialchars($filters['tanggal_selesai']); ?>">
                        </div>

                        <!-- Filter Mahasiswa (jika relevan) -->
                        <?php if (in_array($jenis, ['mahasiswa', 'pendaftaran_seminar', 'pendaftaran_sidang', 'jadwal_seminar', 'jadwal_sidang', 'pengajuan_judul', 'bimbingan'])): ?>
                            <div class="filter-field">
                                <label><i class="fas fa-user-graduate" style="color: #ea580c;"></i> Nama Mahasiswa:</label>
                                <select name="mahasiswa_id">
                                    <option value="">-- Semua Mahasiswa --</option>
                                    <?php mysqli_data_seek($opt_mhs, 0); while ($m = mysqli_fetch_assoc($opt_mhs)): ?>
                                        <option value="<?= $m['id']; ?>" <?= ($filters['mahasiswa_id'] == $m['id']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($m['nama']); ?> (<?= htmlspecialchars($m['nim']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Filter Dosen (jika relevan) -->
                        <?php if (in_array($jenis, ['dosen', 'pendaftaran_seminar', 'pendaftaran_sidang', 'jadwal_seminar', 'jadwal_sidang', 'pengajuan_judul', 'bimbingan', 'kuota_dosen'])): ?>
                            <div class="filter-field">
                                <label><i class="fas fa-user-tie" style="color: #ea580c;"></i> Nama Dosen:</label>
                                <select name="dosen_id">
                                    <option value="">-- Semua Dosen --</option>
                                    <?php mysqli_data_seek($opt_dosen, 0); while ($d = mysqli_fetch_assoc($opt_dosen)): ?>
                                        <option value="<?= $d['id']; ?>" <?= ($filters['dosen_id'] == $d['id']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($d['nama_dosen']); ?>, <?= htmlspecialchars($d['gelar_akademik']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Filter Ruangan (jika relevan) -->
                        <?php if (in_array($jenis, ['jadwal_seminar', 'jadwal_sidang', 'ruangan'])): ?>
                            <div class="filter-field">
                                <label><i class="fas fa-door-open" style="color: #ea580c;"></i> Ruangan:</label>
                                <select name="ruangan_id">
                                    <option value="">-- Semua Ruangan --</option>
                                    <?php mysqli_data_seek($opt_ruang, 0); while ($r = mysqli_fetch_assoc($opt_ruang)): ?>
                                        <option value="<?= $r['id']; ?>" <?= ($filters['ruangan_id'] == $r['id']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($r['kode_ruangan']); ?> - <?= htmlspecialchars($r['nama_ruangan']); ?> (<?= htmlspecialchars($r['gedung']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Filter Jenis Kegiatan (jika laporan ruangan) -->
                        <?php if ($jenis === 'ruangan'): ?>
                            <div class="filter-field">
                                <label><i class="fas fa-tags" style="color: #ea580c;"></i> Jenis Kegiatan:</label>
                                <select name="jenis_kegiatan">
                                    <option value="">-- Semua Kegiatan --</option>
                                    <option value="Seminar Proposal" <?= ($filters['jenis_kegiatan'] === 'Seminar Proposal') ? 'selected' : ''; ?>>Seminar Proposal</option>
                                    <option value="Sidang Akhir Skripsi" <?= ($filters['jenis_kegiatan'] === 'Sidang Akhir Skripsi') ? 'selected' : ''; ?>>Sidang Akhir Skripsi</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Filter Status (dinamis sesuai jenis) -->
                        <?php if (in_array($jenis, ['dosen', 'pendaftaran_seminar', 'pendaftaran_sidang', 'jadwal_seminar', 'jadwal_sidang', 'pengajuan_judul', 'bimbingan', 'kuota_dosen', 'ruangan'])): ?>
                            <div class="filter-field">
                                <label><i class="fas fa-circle-info" style="color: #ea580c;"></i> Status Data:</label>
                                <select name="status">
                                    <option value="">-- Semua Status --</option>
                                    <?php if ($jenis === 'dosen'): ?>
                                        <option value="Aktif" <?= ($filters['status'] === 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="Cuti" <?= ($filters['status'] === 'Cuti') ? 'selected' : ''; ?>>Cuti</option>
                                        <option value="Tidak Aktif" <?= ($filters['status'] === 'Tidak Aktif') ? 'selected' : ''; ?>>Tidak Aktif</option>
                                    <?php elseif (in_array($jenis, ['pendaftaran_seminar', 'pendaftaran_sidang', 'jadwal_seminar', 'jadwal_sidang', 'ruangan'])): ?>
                                        <option value="Menunggu Verifikasi" <?= ($filters['status'] === 'Menunggu Verifikasi') ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                                        <option value="Terjadwal" <?= ($filters['status'] === 'Terjadwal') ? 'selected' : ''; ?>>Terjadwal</option>
                                        <option value="Sedang Berlangsung" <?= ($filters['status'] === 'Sedang Berlangsung') ? 'selected' : ''; ?>>Sedang Berlangsung</option>
                                        <option value="Selesai" <?= ($filters['status'] === 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                                        <option value="Ditolak" <?= ($filters['status'] === 'Ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                                    <?php elseif ($jenis === 'pengajuan_judul'): ?>
                                        <option value="Menunggu" <?= ($filters['status'] === 'Menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                                        <option value="Sedang diperiksa" <?= ($filters['status'] === 'Sedang diperiksa') ? 'selected' : ''; ?>>Sedang diperiksa</option>
                                        <option value="Perlu revisi" <?= ($filters['status'] === 'Perlu revisi') ? 'selected' : ''; ?>>Perlu revisi</option>
                                        <option value="Diterima" <?= ($filters['status'] === 'Diterima') ? 'selected' : ''; ?>>Diterima</option>
                                        <option value="Ditolak" <?= ($filters['status'] === 'Ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                                    <?php elseif ($jenis === 'bimbingan'): ?>
                                        <option value="Menunggu" <?= ($filters['status'] === 'Menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                                        <option value="Sedang Diperiksa" <?= ($filters['status'] === 'Sedang Diperiksa') ? 'selected' : ''; ?>>Sedang Diperiksa</option>
                                        <option value="Perlu Revisi" <?= ($filters['status'] === 'Perlu Revisi') ? 'selected' : ''; ?>>Perlu Revisi</option>
                                        <option value="Disetujui" <?= ($filters['status'] === 'Disetujui') ? 'selected' : ''; ?>>Disetujui</option>
                                        <option value="Selesai" <?= ($filters['status'] === 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                                    <?php elseif ($jenis === 'kuota_dosen'): ?>
                                        <option value="tersedia" <?= ($filters['status'] === 'tersedia') ? 'selected' : ''; ?>>Slot Kuota Tersedia (&lt; 8 Mhs)</option>
                                        <option value="penuh" <?= ($filters['status'] === 'penuh') ? 'selected' : ''; ?>>Kuota Penuh (8/8 Mhs)</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Kata Kunci Pencarian -->
                        <div class="filter-field">
                            <label><i class="fas fa-magnifying-glass" style="color: #ea580c;"></i> Kata Kunci:</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']); ?>" placeholder="Cari nama, NIM, NIDN, judul...">
                        </div>
                    </div>

                    <!-- TOMBOL AKSI LENGKAP -->
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <!-- 1. Tombol Lihat Laporan -->
                            <button type="submit" class="btn-laporan-action btn-filter-submit">
                                <i class="fas fa-eye"></i> Lihat Laporan
                            </button>

                            <!-- 2. Tombol Reset Filter -->
                            <a href="<?= $url_reset; ?>" class="btn-admin-outline" style="padding: 9px 16px; font-size: 0.88rem; font-weight: 600;">
                                <i class="fas fa-rotate-left"></i> Reset Filter
                            </a>
                        </div>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <!-- 3. Tombol Download PDF -->
                            <a href="<?= $url_export_pdf; ?>" class="btn-laporan-action btn-pdf" title="Unduh dokumen resmi dalam format PDF standar A4">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </a>

                            <!-- 4. Tombol Download Excel -->
                            <a href="<?= $url_export_excel; ?>" class="btn-laporan-action btn-excel" title="Unduh lembar kerja spreadsheet Excel .xlsx">
                                <i class="fas fa-file-excel"></i> Download Excel
                            </a>

                            <!-- 5. Tombol Cetak -->
                            <a href="<?= $url_cetak; ?>" target="_blank" class="btn-laporan-action btn-print" title="Buka tampilan cetak bersih dengan kop surat PNL">
                                <i class="fas fa-print"></i> Cetak
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- KARTU RINGKASAN DATA PRATINJAU -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-database"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Data Ditemukan</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: var(--admin-navy);">
                            <?= number_format($meta['total_data'], 0, ',', '.'); ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">Baris</span>
                        </div>
                    </div>
                </div>

                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #fff7ed; color: #ea580c; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Periode Laporan</div>
                        <div style="font-size: 0.92rem; font-weight: 800; color: #ea580c;">
                            <?= htmlspecialchars($meta['periode']); ?>
                        </div>
                    </div>
                </div>

                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Status Format</div>
                        <div style="font-size: 0.92rem; font-weight: 800; color: #059669;">
                            Siap Ekspor (PDF & Excel .xlsx)
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABEL PRATINJAU DATA LAPORAN -->
            <div class="admin-card-panel">
                <div class="admin-panel-header" style="padding-bottom: 14px; border-bottom: 1px solid #f1f5f9;">
                    <div>
                        <h2 style="font-size: 1.1rem; color: var(--admin-navy); margin: 0; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-table" style="color: #ea580c;"></i> Pratinjau Tabel Laporan
                        </h2>
                        <span style="font-size: 0.82rem; color: #64748b; margin-top: 2px; display: block;">
                            Tampilan data persis seperti yang akan diekspor ke dokumen PDF dan berkas Excel
                        </span>
                    </div>
                    <div>
                        <span style="font-size: 0.8rem; background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 6px; color: #475569;">
                            <i class="fas fa-shield"></i> Terhubung Langsung Database MySQL
                        </span>
                    </div>
                </div>

                <div class="admin-table-responsive" style="margin-top: 12px; overflow-x: auto;">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <?php foreach ($headers as $col): ?>
                                    <th style="white-space: nowrap;"><?= htmlspecialchars($col); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="<?= count($headers); ?>" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-folder-open" style="font-size: 2.8rem; color: #cbd5e1; margin-bottom: 10px; display: block;"></i>
                                        <strong style="font-size: 1rem; color: #64748b;">Tidak Ada Data Ditemukan</strong>
                                        <p style="margin: 4px 0 0 0; font-size: 0.85rem;">Tidak ada rekaman data yang cocok dengan kriteria filter yang Anda tentukan.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $item): ?>
                                    <tr>
                                        <?php foreach ($item as $key => $val): ?>
                                            <?php
                                                $align_style = '';
                                                if ($key === 'no' || $key === 'nim' || $key === 'nidn' || $key === 'tanggal' || $key === 'waktu' || $key === 'tgl_daftar') {
                                                    $align_style = 'text-align: center; white-space: nowrap;';
                                                }
                                            ?>
                                            <td style="<?= $align_style; ?>">
                                                <?php if ($key === 'status'): ?>
                                                    <?php 
                                                        $badge_bg = '#f1f5f9';
                                                        $badge_color = '#334155';
                                                        if (in_array($val, ['Terjadwal', 'Disetujui', 'Diterima', 'Aktif', 'Tersedia', 'Selesai'])) {
                                                            $badge_bg = '#dcfce7'; $badge_color = '#15803d';
                                                        } elseif (in_array($val, ['Menunggu Verifikasi', 'Menunggu', 'Sedang Diperiksa', 'Sedang Berlangsung'])) {
                                                            $badge_bg = '#fef3c7'; $badge_color = '#b45309';
                                                        } elseif (in_array($val, ['Ditolak', 'Tidak Aktif', 'Penuh', 'Perlu Revisi'])) {
                                                            $badge_bg = '#fee2e2'; $badge_color = '#b91c1c';
                                                        }
                                                    ?>
                                                    <span class="badge-status-pill" style="background: <?= $badge_bg; ?>; color: <?= $badge_color; ?>;">
                                                        <?= htmlspecialchars($val); ?>
                                                    </span>
                                                <?php elseif ($key === 'jadwal'): ?>
                                                    <div style="font-size: 0.82rem; line-height: 1.35;"><?= $val; ?></div>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($val); ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; font-size: 0.84rem; color: #64748b;">
                    <div>
                        Menampilkan <strong><?= count($rows); ?></strong> dari <strong><?= $meta['total_data']; ?></strong> total rekaman data
                    </div>
                    <div>
                        <i class="fas fa-info-circle"></i> Gunakan tombol <strong>Download PDF</strong> atau <strong>Download Excel</strong> di atas untuk mengunduh berkas lengkap.
                    </div>
                </div>
            </div>

        </main>

        <footer class="admin-footer">
            &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Program Studi Teknologi Rekayasa Perangkat Lunak, Politeknik Negeri Lhokseumawe.
        </footer>
    </div>

    <script>
        function toggleSidebar() { 
            const sb = document.getElementById('adminSidebar');
            if (sb) sb.classList.toggle('show'); 
        }
    </script>
    <!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
    <script src="notifikasi.js"></script>
</body>
</html>
