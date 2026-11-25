<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: api_kalender.php (API JSON Kalender Akademik)
 * ===================================================================
 */

header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';
require_once 'kalender_helper.php';

$action = $_GET['action'] ?? 'get_events';

if ($action === 'get_events') {
    $bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 11;
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 2027;
    $kategori_filter = trim($_GET['kategori'] ?? '');

    // Validasi bulan (1 - 12)
    if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
    if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');

    $semua_kegiatan = ambilSemuaKegiatanKalender($conn, $bulan, $tahun, true);

    // Filter kategori jika diminta
    if (!empty($kategori_filter) && $kategori_filter !== 'semua') {
        $semua_kegiatan = array_values(array_filter($semua_kegiatan, function($k) use ($kategori_filter) {
            return $k['kategori'] === $kategori_filter;
        }));
    }

    // Petakan kegiatan per tanggal untuk memudahkan kalender grid bulanan
    $events_by_date = [];
    $kategori_counts = [];

    foreach ($semua_kegiatan as $event) {
        $kat = $event['kategori'];
        $kategori_counts[$kat] = ($kategori_counts[$kat] ?? 0) + 1;

        // Iterasi dari tanggal mulai sampai selesai
        $mulai = new DateTime($event['tanggal_mulai']);
        $selesai = new DateTime($event['tanggal_selesai']);
        $selesai->modify('+1 day'); // include end date

        $interval = new DateInterval('P1D');
        $period = new DatePeriod($mulai, $interval, $selesai);

        foreach ($period as $dt) {
            $tgl_str = $dt->format('Y-m-d');
            if (!isset($events_by_date[$tgl_str])) {
                $events_by_date[$tgl_str] = [];
            }
            $events_by_date[$tgl_str][] = $event;
        }
    }

    echo json_encode([
        'status' => 'success',
        'bulan' => $bulan,
        'tahun' => $tahun,
        'nama_bulan' => getNamaBulanIndonesia($bulan),
        'total_kegiatan' => count($semua_kegiatan),
        'events' => $semua_kegiatan,
        'events_by_date' => $events_by_date,
        'kategori_counts' => $kategori_counts
    ]);
    exit;
}

if ($action === 'detail') {
    $id = trim($_GET['id'] ?? '');
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID kegiatan tidak valid']);
        exit;
    }

    // Jika id berawalan 'jadwal_' berarti dari pendaftaran_seminar_sidang
    if (strpos($id, 'jadwal_') === 0) {
        $id_jadwal = (int)substr($id, 7);
        $sql = "SELECT p.*, m.nim, m.nama AS nama_mhs, 
                       dp.nama_dosen AS nama_pembimbing, dp.gelar_akademik AS gelar_pembimbing,
                       du.nama_dosen AS nama_penguji, du.gelar_akademik AS gelar_penguji,
                       r.kode_ruangan, r.nama_ruangan, r.gedung
                FROM pendaftaran_seminar_sidang p
                JOIN mahasiswa m ON p.mahasiswa_id = m.id
                LEFT JOIN dosen dp ON p.dosen_pembimbing_id = dp.id
                LEFT JOIN dosen du ON p.dosen_penguji_id = du.id
                LEFT JOIN ruangan r ON p.ruangan_id = r.id
                WHERE p.id = $id_jadwal LIMIT 1";
        $q = mysqli_query($conn, $sql);
        $j = mysqli_fetch_assoc($q);
        if ($j) {
            $is_seminar = ($j['kategori'] === 'seminar' || stripos($j['jenis'], 'Seminar') !== false);
            $kat_nama = $is_seminar ? 'Pelaksanaan seminar proposal' : 'Pelaksanaan sidang akhir';
            $jam_str = (!empty($j['jam_mulai']) && !empty($j['jam_selesai'])) ? substr($j['jam_mulai'], 0, 5) . ' - ' . substr($j['jam_selesai'], 0, 5) . ' WIB' : 'Waktu Ditentukan';

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => $id,
                    'nama_kegiatan' => ($is_seminar ? 'Seminar Proposal: ' : 'Sidang Akhir: ') . $j['nama_mhs'],
                    'kategori' => $kat_nama,
                    'kategori_info' => getKategoriInfo($kat_nama),
                    'tanggal_mulai' => $j['tanggal'],
                    'tanggal_selesai' => $j['tanggal'],
                    'waktu_kegiatan' => $jam_str,
                    'deskripsi' => "Judul Penelitian: \"" . $j['judul'] . "\"",
                    'semester' => 'Ganjil',
                    'tahun_akademik' => '2026/2027',
                    'status_publikasi' => 'Dipublikasikan',
                    'is_jadwal_resmi' => true,
                    'detail_mahasiswa' => [
                        'nama' => $j['nama_mhs'],
                        'nim' => $j['nim'],
                        'judul' => $j['judul'],
                        'ruangan' => ($j['nama_ruangan'] ?? 'Ruangan Ujian') . ' (' . ($j['kode_ruangan'] ?? '') . ')',
                        'gedung' => $j['gedung'] ?? '',
                        'pembimbing' => trim(($j['nama_pembimbing'] ?? '-') . (!empty($j['gelar_pembimbing']) ? ', ' . $j['gelar_pembimbing'] : '')),
                        'penguji' => trim(($j['nama_penguji'] ?? '-') . (!empty($j['gelar_penguji']) ? ', ' . $j['gelar_penguji'] : '')),
                        'status_ujian' => $j['status']
                    ]
                ]
            ]);
            exit;
        }
    } else {
        $id_kal = (int)$id;
        $q = mysqli_query($conn, "SELECT * FROM kalender_akademik WHERE id = $id_kal LIMIT 1");
        $k = mysqli_fetch_assoc($q);
        if ($k) {
            $k['kategori_info'] = getKategoriInfo($k['kategori']);
            $k['is_jadwal_resmi'] = false;
            echo json_encode(['status' => 'success', 'data' => $k]);
            exit;
        }
    }

    echo json_encode(['status' => 'error', 'message' => 'Kegiatan tidak ditemukan']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenal']);
