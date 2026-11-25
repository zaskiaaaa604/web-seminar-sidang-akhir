<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: cetak_laporan.php
 * FITUR: Tampilan Cetak Resmi SIM-SEMSID (Print Preview + Auto window.print)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';
require_once 'laporan_helper.php';

// Validasi Keamanan: Hanya admin yang berhak
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Akses ditolak. Fitur cetak laporan hanya dapat diakses oleh Administrator SIM-SEMSID.");
}

$jenis = trim($_GET['jenis'] ?? 'mahasiswa');
$filters = [
    'tanggal_mulai' => trim($_GET['tanggal_mulai'] ?? ''),
    'tanggal_selesai' => trim($_GET['tanggal_selesai'] ?? ''),
    'status' => trim($_GET['status'] ?? ''),
    'mahasiswa_id' => (int)($_GET['mahasiswa_id'] ?? 0),
    'dosen_id' => (int)($_GET['dosen_id'] ?? 0),
    'ruangan_id' => (int)($_GET['ruangan_id'] ?? 0),
    'jenis_kegiatan' => trim($_GET['jenis_kegiatan'] ?? ''),
    'search' => trim($_GET['search'] ?? '')
];

$data_laporan = ambilDataLaporan($conn, $jenis, $filters);
$meta = $data_laporan['meta'];
$headers = $data_laporan['headers'];
$rows = $data_laporan['rows'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak <?= htmlspecialchars($meta['judul']); ?> - SIM-SEMSID PNL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
        }
        .page-container {
            max-width: 1080px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px 45px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .no-print-bar {
            max-width: 1080px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: 0.2s ease;
        }
        .btn-primary { background: #ea580c; color: white; }
        .btn-primary:hover { background: #c2410c; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }

        /* KOP SURAT RESMI */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .kop-table td {
            vertical-align: middle;
            padding: 0;
        }
        .kop-logo {
            width: 85px;
            text-align: center;
        }
        .kop-logo-box {
            display: inline-block;
            width: 72px;
            height: 72px;
            background: #ea580c;
            color: #ffffff;
            border-radius: 8px;
            font-size: 30pt;
            font-weight: 800;
            line-height: 72px;
            text-align: center;
        }
        .kop-text {
            text-align: center;
            padding-left: 15px;
        }
        .kop-instansi {
            font-size: 11pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kop-kampus {
            font-size: 15pt;
            font-weight: 900;
            color: #1e3a8a;
            text-transform: uppercase;
            margin: 2px 0;
            letter-spacing: 0.5px;
        }
        .kop-jurusan {
            font-size: 11pt;
            font-weight: 700;
            color: #ea580c;
            text-transform: uppercase;
        }
        .kop-prodi {
            font-size: 10pt;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
        }
        .kop-kontak {
            font-size: 8.5pt;
            color: #64748b;
            margin-top: 4px;
        }
        .garis-kop-tebal {
            border-bottom: 3px solid #1e3a8a;
            margin-top: 8px;
        }
        .garis-kop-tipis {
            border-bottom: 1px solid #ea580c;
            margin-top: 2px;
            margin-bottom: 20px;
        }

        .judul-laporan {
            text-align: center;
            margin-bottom: 16px;
        }
        .judul-laporan h2 {
            margin: 0;
            font-size: 14pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
        }
        .judul-laporan .sub-periode {
            font-size: 10pt;
            color: #475569;
            margin-top: 4px;
            font-weight: 600;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 16px;
            font-size: 9pt;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 3px 6px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        table.data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: 700;
            text-align: center;
            padding: 8px 6px;
            border: 1px solid #0f172a;
            text-transform: uppercase;
            font-size: 8pt;
        }
        table.data-table td {
            padding: 7px 8px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .ttd-container {
            width: 100%;
            margin-top: 35px;
            page-break-inside: avoid;
        }
        .ttd-box {
            float: right;
            width: 270px;
            text-align: center;
            font-size: 9.5pt;
        }
        .ttd-nama {
            font-weight: 800;
            text-decoration: underline;
            color: #0f172a;
        }
        .clear { clear: both; }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .page-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            @page {
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <a href="admin_laporan.php?jenis=<?= urlencode($jenis); ?>" class="btn-action btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Menu Laporan
        </a>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print();" class="btn-action btn-primary">
                <i class="fas fa-print"></i> Cetak Dokumen
            </button>
        </div>
    </div>

    <div class="page-container">
        <!-- KOP SURAT RESMI -->
        <table class="kop-table">
            <tr>
                <td class="kop-logo">
                    <div class="kop-logo-box">PNL</div>
                </td>
                <td class="kop-text">
                    <div class="kop-instansi">KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI</div>
                    <div class="kop-kampus">POLITEKNIK NEGERI LHOKSEUMAWE</div>
                    <div class="kop-jurusan">JURUSAN TEKNOLOGI INFORMASI DAN KOMPUTER</div>
                    <div class="kop-prodi">PROGRAM STUDI TEKNOLOGI REKAYASA PERANGKAT LUNAK</div>
                    <div class="kop-kontak">Jl. Banda Aceh - Medan Km. 280.3 Buketrata, Lhokseumawe, Aceh 24301 &bull; Laman: www.pnl.ac.id &bull; Email: tik@pnl.ac.id</div>
                </td>
            </tr>
        </table>
        <div class="garis-kop-tebal"></div>
        <div class="garis-kop-tipis"></div>

        <!-- JUDUL LAPORAN -->
        <div class="judul-laporan">
            <h2><?= htmlspecialchars($meta['judul']); ?></h2>
            <div class="sub-periode">Periode: <?= htmlspecialchars($meta['periode']); ?></div>
        </div>

        <!-- METADATA LAPORAN -->
        <table class="meta-table">
            <tr>
                <td style="width: 15%; font-weight: bold;">Dicetak Oleh</td>
                <td style="width: 35%;">: <?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator SIM-SEMSID'); ?></td>
                <td style="width: 18%; font-weight: bold;">Tanggal Cetak</td>
                <td style="width: 32%;">: <?= htmlspecialchars($meta['tgl_cetak']); ?> (<?= date('H:i'); ?> WIB)</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Total Data</td>
                <td>: <strong><?= number_format($meta['total_data'], 0, ',', '.'); ?></strong> baris data</td>
                <td style="font-weight: bold;">Sistem Aplikasi</td>
                <td>: SIM-SEMSID PNL TIK v2.0</td>
            </tr>
        </table>

        <!-- TABEL DATA LAPORAN -->
        <table class="data-table">
            <thead>
                <tr>
                    <?php foreach ($headers as $col): ?>
                        <th><?= htmlspecialchars($col); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?= count($headers); ?>" class="text-center" style="padding: 30px; color: #64748b; font-style: italic;">
                            Tidak ada data yang ditemukan untuk filter laporan ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php foreach ($r as $key => $val): ?>
                                <?php 
                                    $align_class = '';
                                    if ($key === 'no' || $key === 'nim' || $key === 'nidn' || $key === 'tanggal' || $key === 'waktu' || $key === 'tgl_daftar' || $key === 'status') {
                                        $align_class = 'text-center';
                                    }
                                ?>
                                <td class="<?= $align_class; ?>"><?= ($key === 'jadwal') ? $val : htmlspecialchars($val); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- BLOK TANDA TANGAN -->
        <div class="ttd-container">
            <div class="ttd-box">
                <div>Lhokseumawe, <?= htmlspecialchars($meta['tgl_cetak']); ?></div>
                <div style="margin-top: 4px; font-weight: 700; color: #1e3a8a;">Mengetahui / Mengesahkan,</div>
                <div style="font-size: 8.5pt; color: #475569;">Koordinator Program Studi TIK / Admin SIM-SEMSID</div>
                <div style="height: 65px;"></div>
                <div class="ttd-nama"><?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator Prodi TIK'); ?></div>
                <div style="font-size: 8pt; color: #64748b;">NIP. 19850720 201404 1 001</div>
            </div>
            <div class="clear"></div>
        </div>
    </div>

    <script>
        // Otomatis memicu dialog cetak browser saat halaman selesai dimuat
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 400);
        };
    </script>
</body>
</html>
