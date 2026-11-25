<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: export_laporan_pdf.php
 * FITUR: Ekspor Laporan Resmi SIM-SEMSID Berformat PDF (Dompdf)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';
require_once 'laporan_helper.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Validasi Keamanan: Hanya admin yang berhak mengunduh laporan
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Akses ditolak. Fitur laporan hanya dapat diakses oleh Administrator SIM-SEMSID.");
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

// Tentukan orientasi: landscape jika kolom >= 6, portrait jika sedikit
$orientasi = (count($headers) >= 6) ? 'landscape' : 'portrait';

// Bangun HTML Dokumen Laporan
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($meta['judul']); ?> - SIM-SEMSID PNL</title>
    <style>
        @page {
            margin: 1.2cm 1.2cm 1.5cm 1.2cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            line-height: 1.35;
        }
        /* Kop Surat Resmi PNL */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .kop-table td {
            vertical-align: middle;
            padding: 0;
        }
        .kop-logo {
            width: 75px;
            text-align: center;
        }
        .kop-logo-box {
            display: inline-block;
            width: 62px;
            height: 62px;
            background: #ea580c;
            color: #ffffff;
            border-radius: 8px;
            font-size: 26pt;
            font-weight: bold;
            line-height: 62px;
            text-align: center;
        }
        .kop-text {
            text-align: center;
            padding-left: 10px;
        }
        .kop-instansi {
            font-size: 10pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kop-kampus {
            font-size: 13pt;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            margin: 2px 0;
            letter-spacing: 0.5px;
        }
        .kop-jurusan {
            font-size: 9.5pt;
            font-weight: 700;
            color: #ea580c;
            text-transform: uppercase;
        }
        .kop-prodi {
            font-size: 8.5pt;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
        }
        .kop-kontak {
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 3px;
        }
        /* Garis Ganda Pemisah Kop */
        .garis-kop-tebal {
            border-bottom: 2.5px solid #1e3a8a;
            margin-top: 6px;
        }
        .garis-kop-tipis {
            border-bottom: 0.75px solid #ea580c;
            margin-top: 1.5px;
            margin-bottom: 12px;
        }
        /* Judul Laporan */
        .judul-laporan {
            text-align: center;
            margin-bottom: 10px;
        }
        .judul-laporan h2 {
            margin: 0;
            font-size: 12pt;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .judul-laporan .sub-periode {
            font-size: 8.5pt;
            color: #475569;
            margin-top: 3px;
            font-weight: 600;
        }
        /* Info Metadata Filter */
        .meta-table {
            width: 100%;
            margin-bottom: 10px;
            font-size: 8pt;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2px 4px;
        }
        /* Tabel Data */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        table.data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: 700;
            text-align: center;
            padding: 6px 5px;
            border: 0.5px solid #0f172a;
            text-transform: uppercase;
            font-size: 7.5pt;
            letter-spacing: 0.3px;
        }
        table.data-table td {
            padding: 5px 6px;
            border: 0.5px solid #cbd5e1;
            vertical-align: top;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: 700; }
        
        .badge-status {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: 700;
            text-align: center;
        }
        .status-sukses { background: #dcfce7; color: #166534; }
        .status-proses { background: #fef3c7; color: #92400e; }
        .status-tolak { background: #fee2e2; color: #991b1b; }
        .status-info { background: #e0f2fe; color: #075985; }

        /* Blok Tanda Tangan */
        .ttd-container {
            width: 100%;
            margin-top: 24px;
            page-break-inside: avoid;
        }
        .ttd-box {
            float: right;
            width: 250px;
            text-align: center;
            font-size: 8.5pt;
        }
        .ttd-nama {
            font-weight: 800;
            text-decoration: underline;
            color: #0f172a;
        }
        .clear { clear: both; }
    </style>
</head>
<body>

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
                    <td colspan="<?= count($headers); ?>" class="text-center" style="padding: 20px; color: #64748b; font-style: italic;">
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
                                
                                // Render format status
                                $rendered_val = $val;
                                if ($key === 'status') {
                                    $rendered_val = htmlspecialchars($val);
                                } elseif ($key === 'jadwal') {
                                    // Sudah diformat tanggal & jam
                                } else {
                                    $rendered_val = htmlspecialchars($val);
                                }
                            ?>
                            <td class="<?= $align_class; ?>"><?= ($key === 'jadwal') ? $val : $rendered_val; ?></td>
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
            <div style="font-size: 8pt; color: #475569;">Koordinator Program Studi TIK / Admin SIM-SEMSID</div>
            <div style="height: 55px;"></div>
            <div class="ttd-nama"><?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator Prodi TIK'); ?></div>
            <div style="font-size: 7.5pt; color: #64748b;">NIP. 19850720 201404 1 001</div>
        </div>
        <div class="clear"></div>
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

// Inisialisasi Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('isPhpEnabled', true); // Diperlukan untuk penomoran halaman otomatis canvas

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', $orientasi);
$dompdf->render();

// Script PHP untuk penomoran halaman di footer Dompdf
$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
$size = 7.5;
$canvas->page_text(
    $canvas->get_width() - 140,
    $canvas->get_height() - 25,
    "Halaman {PAGE_NUM} dari {PAGE_COUNT}",
    $font,
    $size,
    [0.4, 0.45, 0.5]
);
$canvas->page_text(
    34,
    $canvas->get_height() - 25,
    "SIM-SEMSID Politeknik Negeri Lhokseumawe | Dokumen Resmi Hasil Cetak Sistem",
    $font,
    $size,
    [0.4, 0.45, 0.5]
);

// Nama file PDF yang diunduh
$nama_file = "Laporan_" . ucwords(str_replace('_', '', $jenis)) . "_" . date('Ymd_His') . ".pdf";

// Unduh file ke browser
$dompdf->stream($nama_file, ["Attachment" => true]);
exit;
