<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: export_laporan_excel.php
 * FITUR: Ekspor Laporan Resmi SIM-SEMSID Berformat Excel .xlsx (PhpSpreadsheet)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';
require_once 'laporan_helper.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

// Inisialisasi Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr(str_replace('Laporan ', '', $meta['judul']), 0, 31));

// Konversi indeks kolom angka ke huruf Excel (1 -> A, 2 -> B, dst)
function getColumnLetter($index) {
    return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
}

$total_kolom = count($headers);
$last_col_letter = getColumnLetter($total_kolom);

// 1. HEADER KOP SURAT EXCEL
$sheet->setCellValue('A1', 'KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI');
$sheet->setCellValue('A2', 'POLITEKNIK NEGERI LHOKSEUMAWE - JURUSAN TIK');
$sheet->setCellValue('A3', 'PROGRAM STUDI TEKNOLOGI REKAYASA PERANGKAT LUNAK (TIK)');
$sheet->setCellValue('A4', strtoupper($meta['judul']));
$sheet->setCellValue('A5', 'Periode: ' . $meta['periode'] . ' | Tanggal Cetak: ' . $meta['tgl_cetak'] . ' (' . date('H:i') . ' WIB)');

// Merge judul kop dari kolom A sampai kolom terakhir
for ($r = 1; $r <= 5; $r++) {
    $sheet->mergeCells("A{$r}:{$last_col_letter}{$r}");
    $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

// Styling Judul Kop
$sheet->getStyle('A1:A3')->getFont()->setSize(10)->setBold(true)->getColor()->setRGB('334155');
$sheet->getStyle('A4')->getFont()->setSize(14)->setBold(true)->getColor()->setRGB('1E3A8A');
$sheet->getStyle('A5')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('64748B');

// 2. HEADER TABEL (Baris 7)
$row_header = 7;
$sheet->getRowDimension($row_header)->setRowHeight(28);

foreach ($headers as $idx => $header_text) {
    $col_letter = getColumnLetter($idx + 1);
    $cell_coord = "{$col_letter}{$row_header}";
    $sheet->setCellValue($cell_coord, $header_text);
}

// Styling Header Tabel (Navy Theme #1E3A8A dengan teks putih tebal)
$header_range = "A{$row_header}:{$last_col_letter}{$row_header}";
$sheet->getStyle($header_range)->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 10,
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1E3A8A']
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '0F172A']
        ]
    ]
]);

// 3. PENGISIAN DATA ROW
$current_row = $row_header + 1;
if (!empty($rows)) {
    foreach ($rows as $item) {
        $col_idx = 1;
        $sheet->getRowDimension($current_row)->setRowHeight(22);

        foreach ($item as $key => $val) {
            $col_letter = getColumnLetter($col_idx);
            $cell_coord = "{$col_letter}{$current_row}";

            // Bersihkan format html jika ada tag <br>
            $clean_val = strip_tags(str_replace('<br>', ' - ', $val));
            $sheet->setCellValue($cell_coord, $clean_val);

            // Alignment otomatis berdasarkan jenis kolom
            $align_h = Alignment::HORIZONTAL_LEFT;
            if ($key === 'no' || $key === 'nim' || $key === 'nidn' || $key === 'tanggal' || $key === 'waktu' || $key === 'tgl_daftar' || $key === 'status') {
                $align_h = Alignment::HORIZONTAL_CENTER;
            }

            $sheet->getStyle($cell_coord)->getAlignment()
                ->setHorizontal($align_h)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $col_idx++;
        }

        // Zebra striping halus untuk baris genap
        if ($current_row % 2 === 0) {
            $sheet->getStyle("A{$current_row}:{$last_col_letter}{$current_row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F8FAFC');
        }

        $current_row++;
    }

    $last_data_row = $current_row - 1;

    // Pasang border tipis pada seluruh tabel data
    $data_range = "A" . ($row_header + 1) . ":{$last_col_letter}{$last_data_row}";
    $sheet->getStyle($data_range)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1']
            ]
        ]
    ]);

    // Aktifkan fitur Filter Otomatis Excel
    $sheet->setAutoFilter("A{$row_header}:{$last_col_letter}{$last_data_row}");
} else {
    // Jika tidak ada data
    $sheet->mergeCells("A{$current_row}:{$last_col_letter}{$current_row}");
    $sheet->setCellValue("A{$current_row}", 'Tidak ada data yang ditemukan untuk filter laporan ini.');
    $sheet->getStyle("A{$current_row}")->applyFromArray([
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'font' => ['italic' => true, 'color' => ['rgb' => '64748B']]
    ]);
    $current_row++;
}

// 4. AUTO-SIZE LEBAR KOLOM MENYESUAIKAN ISI
for ($c = 1; $c <= $total_kolom; $c++) {
    $col_letter = getColumnLetter($c);
    $sheet->getColumnDimension($col_letter)->setAutoSize(true);
}

// Berikan padding lebar minimum agar tidak terpotong
$sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(8); // Kolom No

// 5. TANDA TANGAN DI BAGIAN BAWAH
$ttd_row_start = $current_row + 2;
$ttd_col = getColumnLetter(max(1, $total_kolom - 2));

$sheet->setCellValue("{$ttd_col}" . ($ttd_row_start), "Lhokseumawe, " . $meta['tgl_cetak']);
$sheet->setCellValue("{$ttd_col}" . ($ttd_row_start + 1), "Mengetahui / Mengesahkan,");
$sheet->setCellValue("{$ttd_col}" . ($ttd_row_start + 2), "Koordinator Program Studi TIK / Admin SIM-SEMSID");
$sheet->setCellValue("{$ttd_col}" . ($ttd_row_start + 6), ($_SESSION['nama'] ?? 'Administrator SIM-SEMSID'));
$sheet->setCellValue("{$ttd_col}" . ($ttd_row_start + 7), "NIP. 19850720 201404 1 001");

$sheet->getStyle("{$ttd_col}" . ($ttd_row_start) . ":{$last_col_letter}" . ($ttd_row_start + 7))
    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("{$ttd_col}" . ($ttd_row_start + 6))->getFont()->setBold(true)->setUnderline(true);

// 6. OUTPUT STREAM FILE .XLSX KE BROWSER
$nama_file = "Laporan_" . ucwords(str_replace('_', '', $jenis)) . "_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nama_file . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
