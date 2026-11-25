<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: laporan_helper.php
 * FITUR: Helper Data dan Query Builder Laporan PDF & Excel (10 Jenis Laporan)
 * ===================================================================
 */

require_once 'koneksi.php';

/**
 * Mendapatkan daftar 10 jenis laporan yang tersedia
 */
function getDaftarJenisLaporan() {
    return [
        'mahasiswa' => [
            'id' => 'mahasiswa',
            'judul' => 'Laporan Data Mahasiswa',
            'deskripsi' => 'Data lengkap mahasiswa aktif, program studi, dan tanggal registrasi sistem',
            'icon' => 'fa-user-graduate',
            'warna' => '#2563eb'
        ],
        'dosen' => [
            'id' => 'dosen',
            'judul' => 'Laporan Data Dosen',
            'deskripsi' => 'Data dosen pengajar, NIDN, gelar akademik, kuota bimbingan, dan status keaktifan',
            'icon' => 'fa-user-tie',
            'warna' => '#0d9488'
        ],
        'pendaftaran_seminar' => [
            'id' => 'pendaftaran_seminar',
            'judul' => 'Laporan Pendaftaran Seminar Proposal',
            'deskripsi' => 'Rekap pendaftaran seminar proposal tugas akhir mahasiswa beserta status verifikasi',
            'icon' => 'fa-chalkboard-user',
            'warna' => '#ea580c'
        ],
        'pendaftaran_sidang' => [
            'id' => 'pendaftaran_sidang',
            'judul' => 'Laporan Pendaftaran Sidang Akhir',
            'deskripsi' => 'Rekap pendaftaran sidang tugas akhir skripsi mahasiswa dan status berkas ujian',
            'icon' => 'fa-graduation-cap',
            'warna' => '#7c3aed'
        ],
        'jadwal_seminar' => [
            'id' => 'jadwal_seminar',
            'judul' => 'Laporan Jadwal Seminar Proposal',
            'deskripsi' => 'Agenda jadwal pelaksanaan seminar proposal mahasiswa, ruangan, dan dosen penguji',
            'icon' => 'fa-calendar-days',
            'warna' => '#0284c7'
        ],
        'jadwal_sidang' => [
            'id' => 'jadwal_sidang',
            'judul' => 'Laporan Jadwal Sidang Akhir',
            'deskripsi' => 'Agenda jadwal pelaksanaan sidang skripsi mahasiswa, ruangan, dan dewan penguji',
            'icon' => 'fa-award',
            'warna' => '#059669'
        ],
        'pengajuan_judul' => [
            'id' => 'pengajuan_judul',
            'judul' => 'Laporan Pengajuan Judul',
            'deskripsi' => 'Daftar pengajuan judul skripsi mahasiswa, calon pembimbing, dan status persetujuan',
            'icon' => 'fa-book-bookmark',
            'warna' => '#d97706'
        ],
        'bimbingan' => [
            'id' => 'bimbingan',
            'judul' => 'Laporan Riwayat Bimbingan Mahasiswa dan Dosen',
            'deskripsi' => 'Rekap log catatan bimbingan skripsi antara mahasiswa dan dosen pembimbing',
            'icon' => 'fa-comments',
            'warna' => '#4f46e5'
        ],
        'kuota_dosen' => [
            'id' => 'kuota_dosen',
            'judul' => 'Laporan Kuota Bimbingan Dosen',
            'deskripsi' => 'Monitoring kapasitas kuota maksimal bimbingan setiap dosen (Maksimal 8 mahasiswa)',
            'icon' => 'fa-chart-pie',
            'warna' => '#be123c'
        ],
        'ruangan' => [
            'id' => 'ruangan',
            'judul' => 'Laporan Penggunaan dan Pemesanan Ruangan',
            'deskripsi' => 'Rekap histori penggunaan dan pemesanan ruangan seminar serta sidang tugas akhir',
            'icon' => 'fa-door-open',
            'warna' => '#475569'
        ]
    ];
}

/**
 * Format tanggal Indonesia (contoh: 24 November 2026)
 */
function tglIndo($tanggal, $tampilkan_hari = false) {
    if (empty($tanggal) || $tanggal === '0000-00-00') return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    $timestamp = strtotime($tanggal);
    if (!$timestamp) return $tanggal;

    $d = date('j', $timestamp);
    $m = $bulan[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp);

    $str = "$d $m $y";
    if ($tampilkan_hari) {
        $h = $hari[(int)date('w', $timestamp)];
        $str = "$h, $str";
    }
    return $str;
}

/**
 * Format string periode laporan berdasarkan input filter
 */
function formatPeriodeLaporan($tgl_mulai, $tgl_selesai) {
    if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
        if ($tgl_mulai === $tgl_selesai) {
            return tglIndo($tgl_mulai);
        }
        return tglIndo($tgl_mulai) . " s/d " . tglIndo($tgl_selesai);
    } elseif (!empty($tgl_mulai)) {
        return "Mulai " . tglIndo($tgl_mulai);
    } elseif (!empty($tgl_selesai)) {
        return "Sampai dengan " . tglIndo($tgl_selesai);
    }
    return "Semua Periode (Seluruh Waktu)";
}

/**
 * Mengambil data laporan sesuai jenis dan filter yang dipilih
 * 
 * @param mysqli $conn Koneksi database
 * @param string $jenis Jenis laporan
 * @param array $filters Array filter (tanggal_mulai, tanggal_selesai, status, mahasiswa_id, dosen_id, ruangan_id, search)
 * @return array ['meta' => array, 'headers' => array, 'rows' => array, 'summary' => array]
 */
function ambilDataLaporan($conn, $jenis, $filters = []) {
    $daftar = getDaftarJenisLaporan();
    if (!isset($daftar[$jenis])) {
        $jenis = 'mahasiswa';
    }
    $info = $daftar[$jenis];

    $tgl_mulai     = trim($filters['tanggal_mulai'] ?? '');
    $tgl_selesai   = trim($filters['tanggal_selesai'] ?? '');
    $status        = trim($filters['status'] ?? '');
    $mahasiswa_id  = (int)($filters['mahasiswa_id'] ?? 0);
    $dosen_id      = (int)($filters['dosen_id'] ?? 0);
    $ruangan_id    = (int)($filters['ruangan_id'] ?? 0);
    $jenis_keg     = trim($filters['jenis_kegiatan'] ?? '');
    $search        = trim($filters['search'] ?? '');

    $headers = [];
    $rows = [];
    $summary = [];

    switch ($jenis) {
        // ===================================================================
        // 1. LAPORAN DATA MAHASISWA
        // ===================================================================
        case 'mahasiswa':
            $headers = ['No', 'NIM', 'Nama Mahasiswa', 'Program Studi', 'Email', 'No. Handphone', 'Tanggal Registrasi'];
            $sql = "SELECT * FROM mahasiswa WHERE 1=1";
            $params = [];
            $types = "";

            if (!empty($tgl_mulai)) {
                $sql .= " AND DATE(created_at) >= ?";
                $params[] = $tgl_mulai;
                $types .= "s";
            }
            if (!empty($tgl_selesai)) {
                $sql .= " AND DATE(created_at) <= ?";
                $params[] = $tgl_selesai;
                $types .= "s";
            }
            if (!empty($search)) {
                $sql .= " AND (nim LIKE ? OR nama LIKE ? OR email LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= "sss";
            }
            if ($mahasiswa_id > 0) {
                $sql .= " AND id = ?";
                $params[] = $mahasiswa_id;
                $types .= "i";
            }
            $sql .= " ORDER BY nim ASC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = [
                    'no' => $no++,
                    'nim' => $r['nim'],
                    'nama' => $r['nama'],
                    'prodi' => $r['program_studi'] ?: 'Teknologi Rekayasa Perangkat Lunak',
                    'email' => $r['email'] ?: '-',
                    'no_hp' => $r['no_hp'] ?: '-',
                    'tanggal' => tglIndo(substr($r['created_at'], 0, 10))
                ];
            }
            mysqli_stmt_close($stmt);
            break;

        // ===================================================================
        // 2. LAPORAN DATA DOSEN
        // ===================================================================
        case 'dosen':
            $headers = ['No', 'NIDN', 'Nama Dosen & Gelar', 'Program Studi', 'Email', 'No. Handphone', 'Bimbingan Aktif', 'Status'];
            $sql = "SELECT d.*, 
                           COALESCE(b.total_bimbingan, 0) AS total_bimbingan 
                    FROM dosen d
                    LEFT JOIN (
                        SELECT dosen_id, COUNT(DISTINCT mahasiswa_id) AS total_bimbingan
                        FROM pengajuan_judul
                        WHERE status = 'Diterima'
                        GROUP BY dosen_id
                    ) b ON d.id = b.dosen_id
                    WHERE 1=1";
            $params = [];
            $types = "";

            if (!empty($status)) {
                $sql .= " AND d.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            if ($dosen_id > 0) {
                $sql .= " AND d.id = ?";
                $params[] = $dosen_id;
                $types .= "i";
            }
            if (!empty($search)) {
                $sql .= " AND (d.nidn LIKE ? OR d.nama_dosen LIKE ? OR d.email LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= "sss";
            }
            $sql .= " ORDER BY d.nama_dosen ASC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $gelar = !empty($r['gelar_akademik']) ? ', ' . $r['gelar_akademik'] : '';
                $rows[] = [
                    'no' => $no++,
                    'nidn' => $r['nidn'],
                    'nama' => $r['nama_dosen'] . $gelar,
                    'prodi' => $r['program_studi'] ?: 'Teknologi Rekayasa Perangkat Lunak',
                    'email' => $r['email'] ?: '-',
                    'no_hp' => $r['no_hp'] ?: '-',
                    'kuota' => $r['total_bimbingan'] . ' / ' . KUOTA_MAKSIMAL_DOSEN . ' Mhs',
                    'status' => $r['status'] ?: 'Aktif'
                ];
            }
            mysqli_stmt_close($stmt);
            break;

        // ===================================================================
        // 3. LAPORAN PENDAFTARAN SEMINAR PROPOSAL
        // ===================================================================
        case 'pendaftaran_seminar':
            $headers = ['No', 'Tgl Daftar', 'NIM', 'Nama Mahasiswa', 'Judul Proposal Skripsi', 'Dosen Pembimbing', 'Dosen Penguji', 'Ruangan', 'Jadwal Ujian', 'Status'];
            $sql = "SELECT p.*, m.nama AS nama_mhs, m.nim, 
                           d1.nama_dosen AS nama_pembimbing, d1.gelar_akademik AS gelar_pembimbing,
                           d2.nama_dosen AS nama_penguji, d2.gelar_akademik AS gelar_penguji,
                           r.nama_ruangan, r.kode_ruangan
                    FROM pendaftaran_seminar_sidang p
                    JOIN mahasiswa m ON p.mahasiswa_id = m.id
                    LEFT JOIN dosen d1 ON p.dosen_pembimbing_id = d1.id
                    LEFT JOIN dosen d2 ON p.dosen_penguji_id = d2.id
                    LEFT JOIN ruangan r ON p.ruangan_id = r.id
                    WHERE p.kategori = 'seminar'";
            $params = [];
            $types = "";

            if (!empty($tgl_mulai)) {
                $sql .= " AND DATE(p.created_at) >= ?";
                $params[] = $tgl_mulai;
                $types .= "s";
            }
            if (!empty($tgl_selesai)) {
                $sql .= " AND DATE(p.created_at) <= ?";
                $params[] = $tgl_selesai;
                $types .= "s";
            }
            if (!empty($status)) {
                $sql .= " AND p.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            if ($mahasiswa_id > 0) {
                $sql .= " AND p.mahasiswa_id = ?";
                $params[] = $mahasiswa_id;
                $types .= "i";
            }
            if ($dosen_id > 0) {
                $sql .= " AND (p.dosen_pembimbing_id = ? OR p.dosen_penguji_id = ?)";
                $params[] = $dosen_id; $params[] = $dosen_id;
                $types .= "ii";
            }
            if (!empty($search)) {
                $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ? OR p.judul LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= "sss";
            }
            $sql .= " ORDER BY p.created_at DESC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $dp = $r['nama_pembimbing'] ? ($r['nama_pembimbing'] . ($r['gelar_pembimbing'] ? ', ' . $r['gelar_pembimbing'] : '')) : '-';
                $du = $r['nama_penguji'] ? ($r['nama_penguji'] . ($r['gelar_penguji'] ? ', ' . $r['gelar_penguji'] : '')) : '-';
                $jadwal_str = '-';
                if (!empty($r['tanggal'])) {
                    $jadwal_str = tglIndo($r['tanggal']) . '<br>' . substr($r['jam_mulai'], 0, 5) . '-' . substr($r['jam_selesai'], 0, 5) . ' WIB';
                }
                $ruang_str = $r['nama_ruangan'] ? ($r['kode_ruangan'] . ' - ' . $r['nama_ruangan']) : '-';

                $rows[] = [
                    'no' => $no++,
                    'tgl_daftar' => tglIndo(substr($r['created_at'], 0, 10)),
                    'nim' => $r['nim'],
                    'nama' => $r['nama_mhs'],
                    'judul' => $r['judul'],
                    'pembimbing' => $dp,
                    'penguji' => $du,
                    'ruangan' => $ruang_str,
                    'jadwal' => $jadwal_str,
                    'status' => $r['status']
                ];
            }
            mysqli_stmt_close($stmt);
            break;

        // ===================================================================
        // 4. LAPORAN PENDAFTARAN SIDANG AKHIR
        // ===================================================================
        case 'pendaftaran_sidang':
            $headers = ['No', 'Tgl Daftar', 'NIM', 'Nama Mahasiswa', 'Judul Skripsi / Tugas Akhir', 'Dosen Pembimbing', 'Dosen Penguji', 'Ruangan', 'Jadwal Sidang', 'Status'];
            $sql = "SELECT p.*, m.nama AS nama_mhs, m.nim, 
                           d1.nama_dosen AS nama_pembimbing, d1.gelar_akademik AS gelar_pembimbing,
                           d2.nama_dosen AS nama_penguji, d2.gelar_akademik AS gelar_penguji,
                           r.nama_ruangan, r.kode_ruangan
                    FROM pendaftaran_seminar_sidang p
                    JOIN mahasiswa m ON p.mahasiswa_id = m.id
                    LEFT JOIN dosen d1 ON p.dosen_pembimbing_id = d1.id
                    LEFT JOIN dosen d2 ON p.dosen_penguji_id = d2.id
                    LEFT JOIN ruangan r ON p.ruangan_id = r.id
                    WHERE p.kategori = 'sidang'";
            $params = [];
            $types = "";

            if (!empty($tgl_mulai)) {
                $sql .= " AND DATE(p.created_at) >= ?";
                $params[] = $tgl_mulai;
                $types .= "s";
            }
            if (!empty($tgl_selesai)) {
                $sql .= " AND DATE(p.created_at) <= ?";
                $params[] = $tgl_selesai;
                $types .= "s";
            }
            if (!empty($status)) {
                $sql .= " AND p.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            if ($mahasiswa_id > 0) {
                $sql .= " AND p.mahasiswa_id = ?";
                $params[] = $mahasiswa_id;
                $types .= "i";
            }
            if ($dosen_id > 0) {
                $sql .= " AND (p.dosen_pembimbing_id = ? OR p.dosen_penguji_id = ?)";
                $params[] = $dosen_id; $params[] = $dosen_id;
                $types .= "ii";
            }
            if (!empty($search)) {
                $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ? OR p.judul LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= "sss";
            }
            $sql .= " ORDER BY p.created_at DESC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $dp = $r['nama_pembimbing'] ? ($r['nama_pembimbing'] . ($r['gelar_pembimbing'] ? ', ' . $r['gelar_pembimbing'] : '')) : '-';
                $du = $r['nama_penguji'] ? ($r['nama_penguji'] . ($r['gelar_penguji'] ? ', ' . $r['gelar_penguji'] : '')) : '-';
                $jadwal_str = '-';
                if (!empty($r['tanggal'])) {
                    $jadwal_str = tglIndo($r['tanggal']) . '<br>' . substr($r['jam_mulai'], 0, 5) . '-' . substr($r['jam_selesai'], 0, 5) . ' WIB';
                }
                $ruang_str = $r['nama_ruangan'] ? ($r['kode_ruangan'] . ' - ' . $r['nama_ruangan']) : '-';

                $rows[] = [
                    'no' => $no++,
                    'tgl_daftar' => tglIndo(substr($r['created_at'], 0, 10)),
                    'nim' => $r['nim'],
                    'nama' => $r['nama_mhs'],
                    'judul' => $r['judul'],
                    'pembimbing' => $dp,
                    'penguji' => $du,
                    'ruangan' => $ruang_str,
                    'jadwal' => $jadwal_str,
                    'status' => $r['status']
                ];
            }
            mysqli_stmt_close($stmt);
            break;

        // ===================================================================
        // 5. LAPORAN JADWAL SEMINAR PROPOSAL
        // ===================================================================
        case 'jadwal_seminar':
            $headers = ['No', 'Tanggal', 'Waktu (WIB)', 'Ruangan', 'NIM', 'Nama Mahasiswa', 'Judul Seminar Proposal', 'Dosen Pembimbing', 'Dosen Penguji', 'Status'];
            $sql = "SELECT p.*, m.nama AS nama_mhs, m.nim, 
                           d1.nama_dosen AS nama_pembimbing, d1.gelar_akademik AS gelar_pembimbing,
                           d2.nama_dosen AS nama_penguji, d2.gelar_akademik AS gelar_penguji,
                           r.nama_ruangan, r.kode_ruangan, r.gedung
                    FROM pendaftaran_seminar_sidang p
                    JOIN mahasiswa m ON p.mahasiswa_id = m.id
                    LEFT JOIN dosen d1 ON p.dosen_pembimbing_id = d1.id
                    LEFT JOIN dosen d2 ON p.dosen_penguji_id = d2.id
                    LEFT JOIN ruangan r ON p.ruangan_id = r.id
                    WHERE p.kategori = 'seminar' AND p.tanggal IS NOT NULL";
            $params = [];
            $types = "";

            if (!empty($tgl_mulai)) {
                $sql .= " AND p.tanggal >= ?";
                $params[] = $tgl_mulai;
                $types .= "s";
            }
            if (!empty($tgl_selesai)) {
                $sql .= " AND p.tanggal <= ?";
                $params[] = $tgl_selesai;
                $types .= "s";
            }
            if (!empty($status)) {
                $sql .= " AND p.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            if ($mahasiswa_id > 0) {
                $sql .= " AND p.mahasiswa_id = ?";
                $params[] = $mahasiswa_id;
                $types .= "i";
            }
            if ($dosen_id > 0) {
                $sql .= " AND (p.dosen_pembimbing_id = ? OR p.dosen_penguji_id = ?)";
                $params[] = $dosen_id; $params[] = $dosen_id;
                $types .= "ii";
            }
            if ($ruangan_id > 0) {
                $sql .= " AND p.ruangan_id = ?";
                $params[] = $ruangan_id;
                $types .= "i";
            }
            if (!empty($search)) {
                $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ? OR p.judul LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= "sss";
            }
            $sql .= " ORDER BY p.tanggal ASC, p.jam_mulai ASC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $dp = $r['nama_pembimbing'] ? ($r['nama_pembimbing'] . ($r['gelar_pembimbing'] ? ', ' . $r['gelar_pembimbing'] : '')) : '-';
                $du = $r['nama_penguji'] ? ($r['nama_penguji'] . ($r['gelar_penguji'] ? ', ' . $r['gelar_penguji'] : '')) : '-';
                $waktu = substr($r['jam_mulai'], 0, 5) . ' - ' . substr($r['jam_selesai'], 0, 5);
                $ruang = $r['nama_ruangan'] ? ($r['nama_ruangan'] . ' (' . $r['gedung'] . ')') : '-';

                $rows[] = [
                    'no' => $no++,
                    'tanggal' => tglIndo($r['tanggal']),
                    'waktu' => $waktu,
                    'ruangan' => $ruang,
                    'nim' => $r['nim'],
                    'nama' => $r['nama_mhs'],
                    'judul' => $r['judul'],
                    'pembimbing' => $dp,
                    'penguji' => $du,
                    'status' => $r['status']
                ];
            }
            mysqli_stmt_close($stmt);
            break;

        // ===================================================================
        // 6. LAPORAN JADWAL SIDANG AKHIR
        // ===================================================================
        case 'jadwal_sidang':
            $headers = ['No', 'Tanggal', 'Waktu (WIB)', 'Ruangan', 'NIM', 'Nama Mahasiswa', 'Judul Skripsi / Tugas Akhir', 'Dosen Pembimbing', 'Dosen Penguji', 'Status'];
            $sql = "SELECT p.*, m.nama AS nama_mhs, m.nim, 
                           d1.nama_dosen AS nama_pembimbing, d1.gelar_akademik AS gelar_pembimbing,
                           d2.nama_dosen AS nama_penguji, d2.gelar_akademik AS gelar_penguji,
                           r.nama_ruangan, r.kode_ruangan, r.gedung
                    FROM pendaftaran_seminar_sidang p
                    JOIN mahasiswa m ON p.mahasiswa_id = m.id
                    LEFT JOIN dosen d1 ON p.dosen_pembimbing_id = d1.id
                    LEFT JOIN dosen d2 ON p.dosen_penguji_id = d2.id
                    LEFT JOIN ruangan r ON p.ruangan_id = r.id
                    WHERE p.kategori = 'sidang' AND p.tanggal IS NOT NULL";
            $params = [];
            $types = "";

            if (!empty($tgl_mulai)) {
                $sql .= " AND p.tanggal >= ?";
                $params[] = $tgl_mulai;
                $types .= "s";
            }
            if (!empty($tgl_selesai)) {
                $sql .= " AND p.tanggal <= ?";
                $params[] = $tgl_selesai;
                $types .= "s";
            }
            if (!empty($status)) {
                $sql .= " AND p.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            if ($mahasiswa_id > 0) {
                $sql .= " AND p.mahasiswa_id = ?";
                $params[] = $mahasiswa_id;
                $types .= "i";
            }
            if ($dosen_id > 0) {
                $sql .= " AND (p.dosen_pembimbing_id = ? OR p.dosen_penguji_id = ?)";
                $params[] = $dosen_id; $params[] = $dosen_id;
                $types .= "ii";
            }
            if ($ruangan_id > 0) {
                $sql .= " AND p.ruangan_id = ?";
                $params[] = $ruangan_id;
                $types .= "i";
            }
            if (!empty($search)) {
                $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ? OR p.judul LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= "sss";
            }
            $sql .= " ORDER BY p.tanggal ASC, p.jam_mulai ASC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $dp = $r['nama_pembimbing'] ? ($r['nama_pembimbing'] . ($r['gelar_pembimbing'] ? ', ' . $r['gelar_pembimbing'] : '')) : '-';
                $du = $r['nama_penguji'] ? ($r['nama_penguji'] . ($r['gelar_penguji'] ? ', ' . $r['gelar_penguji'] : '')) : '-';
                $waktu = substr($r['jam_mulai'], 0, 5) . ' - ' . substr($r['jam_selesai'], 0, 5);
                $ruang = $r['nama_ruangan'] ? ($r['nama_ruangan'] . ' (' . $r['gedung'] . ')') : '-';

                $rows[] = [
                    'no' => $no++,
                    'tanggal' => tglIndo($r['tanggal']),
                    'waktu' => $waktu,
                    'ruangan' => $ruang,
                    'nim' => $r['nim'],
                    'nama' => $r['nama_mhs'],
                    'judul' => $r['judul'],
                    'pembimbing' => $dp,
                    'penguji' => $du,
                    'status' => $r['status']
                ];
            }
            mysqli_stmt_close($stmt);
            break;

        // ===================================================================
        // 7. LAPORAN PENGAJUAN JUDUL
        // ===================================================================
        case 'pengajuan_judul':
            $headers = ['No', 'Tgl Pengajuan', 'NIM', 'Nama Mahasiswa', 'Judul Usulan Skripsi', 'Dosen Pembimbing', 'Status Pengajuan', 'Catatan / Alasan'];
            $sql = "SELECT pj.*, m.nama AS nama_mhs, m.nim, 
                           d.nama_dosen, d.gelar_akademik
                    FROM pengajuan_judul pj
                    JOIN mahasiswa m ON pj.mahasiswa_id = m.id
                    JOIN dosen d ON pj.dosen_id = d.id
                    WHERE 1=1";
            $params = [];
            $types = "";

            if (!empty($tgl_mulai)) {
                $sql .= " AND DATE(pj.created_at) >= ?";
                $params[] = $tgl_mulai;
                $types .= "s";
            }
            if (!empty($tgl_selesai)) {
                $sql .= " AND DATE(pj.created_at) <= ?";
                $params[] = $tgl_selesai;
                $types .= "s";
            }
            if (!empty($status)) {
                $sql .= " AND pj.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            if ($mahasiswa_id > 0) {
                $sql .= " AND pj.mahasiswa_id = ?";
                $params[] = $mahasiswa_id;
                $types .= "i";
            }
            if ($dosen_id > 0) {
                $sql .= " AND pj.dosen_id = ?";
                $params[] = $dosen_id;
                $types .= "i";
            }
            if (!empty($search)) {
                $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ? OR pj.judul LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= "sss";
            }
            $sql .= " ORDER BY pj.created_at DESC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $dosen_str = $r['nama_dosen'] . (!empty($r['gelar_akademik']) ? ', ' . $r['gelar_akademik'] : '');
                $catatan = !empty($r['catatan_revisi']) ? $r['catatan_revisi'] : (!empty($r['alasan_penolakan']) ? $r['alasan_penolakan'] : '-');

                $rows[] = [
                    'no' => $no++,
                    'tanggal' => tglIndo(substr($r['created_at'], 0, 10)),
                    'nim' => $r['nim'],
                    'nama' => $r['nama_mhs'],
                    'judul' => $r['judul'],
                    'dosen' => $dosen_str,
                    'status' => $r['status'] ?: 'Menunggu',
                    'catatan' => $catatan
                ];
            }
            mysqli_stmt_close($stmt);
            break;

        // ===================================================================
        // 8. LAPORAN RIWAYAT BIMBINGAN MAHASISWA DAN DOSEN
        // ===================================================================
        case 'bimbingan':
            $headers = ['No', 'Tgl Bimbingan', 'NIM', 'Nama Mahasiswa', 'Dosen Pembimbing', 'Materi Bimbingan', 'Hasil / Perkembangan', 'Status', 'Catatan / Arahan Dosen'];
            $sql = "SELECT rb.*, m.nama AS nama_mhs, m.nim, 
                           d.nama_dosen, d.gelar_akademik
                    FROM riwayat_bimbingan rb
                    JOIN mahasiswa m ON rb.mahasiswa_id = m.id
                    JOIN dosen d ON rb.dosen_id = d.id
                    WHERE 1=1";
            $params = [];
            $types = "";

            if (!empty($tgl_mulai)) {
                $sql .= " AND rb.tanggal_bimbingan >= ?";
                $params[] = $tgl_mulai;
                $types .= "s";
            }
            if (!empty($tgl_selesai)) {
                $sql .= " AND rb.tanggal_bimbingan <= ?";
                $params[] = $tgl_selesai;
                $types .= "s";
            }
            if (!empty($status)) {
                $sql .= " AND rb.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            if ($mahasiswa_id > 0) {
                $sql .= " AND rb.mahasiswa_id = ?";
                $params[] = $mahasiswa_id;
                $types .= "i";
            }
            if ($dosen_id > 0) {
                $sql .= " AND rb.dosen_id = ?";
                $params[] = $dosen_id;
                $types .= "i";
            }
            if (!empty($search)) {
                $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ? OR rb.materi_bimbingan LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= "sss";
            }
            $sql .= " ORDER BY rb.tanggal_bimbingan DESC, rb.id DESC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $dosen_str = $r['nama_dosen'] . (!empty($r['gelar_akademik']) ? ', ' . $r['gelar_akademik'] : '');
                $rows[] = [
                    'no' => $no++,
                    'tanggal' => tglIndo($r['tanggal_bimbingan']),
                    'nim' => $r['nim'],
                    'nama' => $r['nama_mhs'],
                    'dosen' => $dosen_str,
                    'materi' => $r['materi_bimbingan'],
                    'hasil' => $r['hasil_perkembangan'] ?: '-',
                    'status' => $r['status'] ?: 'Menunggu',
                    'komentar' => $r['komentar_dosen'] ?: '-'
                ];
            }
            mysqli_stmt_close($stmt);
            break;

        // ===================================================================
        // 9. LAPORAN KUOTA BIMBINGAN DOSEN
        // ===================================================================
        case 'kuota_dosen':
            $headers = ['No', 'NIDN', 'Nama Dosen & Gelar', 'Program Studi', 'Kuota Maksimal', 'Mahasiswa Bimbingan', 'Sisa Slot Kuota', 'Persentase Terisi', 'Status Ketersediaan'];
            $sql = "SELECT d.*, 
                           COALESCE(b.total_bimbingan, 0) AS total_bimbingan,
                           (" . KUOTA_MAKSIMAL_DOSEN . " - COALESCE(b.total_bimbingan, 0)) AS sisa_kuota
                    FROM dosen d
                    LEFT JOIN (
                        SELECT dosen_id, COUNT(DISTINCT mahasiswa_id) AS total_bimbingan
                        FROM pengajuan_judul
                        WHERE status = 'Diterima'
                        GROUP BY dosen_id
                    ) b ON d.id = b.dosen_id
                    WHERE 1=1";
            $params = [];
            $types = "";

            if ($dosen_id > 0) {
                $sql .= " AND d.id = ?";
                $params[] = $dosen_id;
                $types .= "i";
            }
            if ($status === 'tersedia') {
                $sql .= " AND COALESCE(b.total_bimbingan, 0) < " . KUOTA_MAKSIMAL_DOSEN;
            } elseif ($status === 'penuh') {
                $sql .= " AND COALESCE(b.total_bimbingan, 0) >= " . KUOTA_MAKSIMAL_DOSEN;
            }
            if (!empty($search)) {
                $sql .= " AND (d.nidn LIKE ? OR d.nama_dosen LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like;
                $types .= "ss";
            }
            $sql .= " ORDER BY d.nama_dosen ASC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $gelar = !empty($r['gelar_akademik']) ? ', ' . $r['gelar_akademik'] : '';
                $terisi = (int)$r['total_bimbingan'];
                $maks = KUOTA_MAKSIMAL_DOSEN;
                $sisa = max(0, $maks - $terisi);
                $persen = round(($terisi / $maks) * 100) . '%';
                $status_k = ($terisi >= $maks) ? 'Penuh' : 'Tersedia';

                $rows[] = [
                    'no' => $no++,
                    'nidn' => $r['nidn'],
                    'nama' => $r['nama_dosen'] . $gelar,
                    'prodi' => $r['program_studi'] ?: 'Teknologi Rekayasa Perangkat Lunak',
                    'maks' => $maks . ' Mahasiswa',
                    'terisi' => $terisi . ' Mahasiswa',
                    'sisa' => $sisa . ' Slot',
                    'persen' => $persen,
                    'status' => $status_k
                ];
            }
            mysqli_stmt_close($stmt);
            break;

        // ===================================================================
        // 10. LAPORAN PENGGUNAAN DAN PEMESANAN RUANGAN
        // ===================================================================
        case 'ruangan':
            $headers = ['No', 'Ruangan & Gedung', 'Tanggal Pemakaian', 'Waktu (WIB)', 'Kegiatan', 'NIM & Mahasiswa', 'Judul Acara', 'Dosen Pembimbing', 'Status'];
            $sql = "SELECT p.*, r.nama_ruangan, r.kode_ruangan, r.gedung,
                           m.nama AS nama_mhs, m.nim,
                           d1.nama_dosen AS nama_pembimbing, d1.gelar_akademik AS gelar_pembimbing
                    FROM pendaftaran_seminar_sidang p
                    JOIN ruangan r ON p.ruangan_id = r.id
                    JOIN mahasiswa m ON p.mahasiswa_id = m.id
                    LEFT JOIN dosen d1 ON p.dosen_pembimbing_id = d1.id
                    WHERE p.ruangan_id IS NOT NULL AND p.tanggal IS NOT NULL";
            $params = [];
            $types = "";

            if (!empty($tgl_mulai)) {
                $sql .= " AND p.tanggal >= ?";
                $params[] = $tgl_mulai;
                $types .= "s";
            }
            if (!empty($tgl_selesai)) {
                $sql .= " AND p.tanggal <= ?";
                $params[] = $tgl_selesai;
                $types .= "s";
            }
            if (!empty($status)) {
                $sql .= " AND p.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            if ($ruangan_id > 0) {
                $sql .= " AND p.ruangan_id = ?";
                $params[] = $ruangan_id;
                $types .= "i";
            }
            if (!empty($jenis_keg)) {
                $sql .= " AND p.jenis = ?";
                $params[] = $jenis_keg;
                $types .= "s";
            }
            if (!empty($search)) {
                $sql .= " AND (r.nama_ruangan LIKE ? OR r.kode_ruangan LIKE ? OR m.nama LIKE ? OR p.judul LIKE ?)";
                $like = "%$search%";
                $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
                $types .= "ssss";
            }
            $sql .= " ORDER BY p.tanggal ASC, p.jam_mulai ASC";

            $stmt = mysqli_prepare($conn, $sql);
            if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            $no = 1;
            while ($r = mysqli_fetch_assoc($res)) {
                $dp = $r['nama_pembimbing'] ? ($r['nama_pembimbing'] . ($r['gelar_pembimbing'] ? ', ' . $r['gelar_pembimbing'] : '')) : '-';
                $ruang_str = $r['kode_ruangan'] . ' - ' . $r['nama_ruangan'] . ' (' . $r['gedung'] . ')';
                $waktu = substr($r['jam_mulai'], 0, 5) . ' - ' . substr($r['jam_selesai'], 0, 5);

                $rows[] = [
                    'no' => $no++,
                    'ruangan' => $ruang_str,
                    'tanggal' => tglIndo($r['tanggal']),
                    'waktu' => $waktu,
                    'kegiatan' => $r['jenis'],
                    'mahasiswa' => $r['nim'] . ' - ' . $r['nama_mhs'],
                    'judul' => $r['judul'],
                    'pembimbing' => $dp,
                    'status' => $r['status']
                ];
            }
            mysqli_stmt_close($stmt);
            break;
    }

    $periode_str = formatPeriodeLaporan($tgl_mulai, $tgl_selesai);

    return [
        'meta' => [
            'jenis' => $jenis,
            'judul' => $info['judul'],
            'deskripsi' => $info['deskripsi'],
            'periode' => $periode_str,
            'tgl_cetak' => tglIndo(date('Y-m-d')),
            'waktu_cetak' => date('d/m/Y H:i') . ' WIB',
            'total_data' => count($rows),
            'filters' => [
                'tanggal_mulai' => $tgl_mulai,
                'tanggal_selesai' => $tgl_selesai,
                'status' => $status,
                'mahasiswa_id' => $mahasiswa_id,
                'dosen_id' => $dosen_id,
                'ruangan_id' => $ruangan_id,
                'jenis_kegiatan' => $jenis_keg,
                'search' => $search
            ]
        ],
        'headers' => $headers,
        'rows' => $rows
    ];
}
