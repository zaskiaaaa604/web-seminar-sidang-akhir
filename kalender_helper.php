<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: kalender_helper.php (Helper Data & Kategori Kalender Akademik)
 * ===================================================================
 */

if (!function_exists('getKategoriKalenderList')) {
    /**
     * Daftar 9 Kategori Kegiatan Akademik Resmi sesuai ketentuan
     */
    function getKategoriKalenderList() {
        return [
            'Awal perkuliahan' => [
                'nama' => 'Awal perkuliahan',
                'warna_bg' => '#dbeafe',
                'warna_teks' => '#1e40af',
                'warna_border' => '#93c5fd',
                'warna_badge' => '#2563eb',
                'ikon' => 'fa-graduation-cap',
                'deskripsi_singkat' => 'Hari pertama perkuliahan & masa KRS'
            ],
            'Pendaftaran seminar proposal' => [
                'nama' => 'Pendaftaran seminar proposal',
                'warna_bg' => '#fef3c7',
                'warna_teks' => '#92400e',
                'warna_border' => '#fcd34d',
                'warna_badge' => '#d97706',
                'ikon' => 'fa-file-signature',
                'deskripsi_singkat' => 'Pendaftaran & pengumpulan berkas sempro'
            ],
            'Pelaksanaan seminar proposal' => [
                'nama' => 'Pelaksanaan seminar proposal',
                'warna_bg' => '#ffedd5',
                'warna_teks' => '#9a3412',
                'warna_border' => '#fdba74',
                'warna_badge' => '#ea580c',
                'ikon' => 'fa-chalkboard-user',
                'deskripsi_singkat' => 'Ujian presentasi seminar proposal'
            ],
            'Pendaftaran sidang akhir' => [
                'nama' => 'Pendaftaran sidang akhir',
                'warna_bg' => '#ede9fe',
                'warna_teks' => '#5b21b6',
                'warna_border' => '#c4b5fd',
                'warna_badge' => '#7c3aed',
                'ikon' => 'fa-file-circle-check',
                'deskripsi_singkat' => 'Pendaftaran ujian sidang skripsi akhir'
            ],
            'Pelaksanaan sidang akhir' => [
                'nama' => 'Pelaksanaan sidang akhir',
                'warna_bg' => '#d1fae5',
                'warna_teks' => '#065f46',
                'warna_border' => '#6ee7b7',
                'warna_badge' => '#059669',
                'ikon' => 'fa-user-graduate',
                'deskripsi_singkat' => 'Ujian sidang akhir tugas akhir / skripsi'
            ],
            'Batas pengumpulan berkas' => [
                'nama' => 'Batas pengumpulan berkas',
                'warna_bg' => '#ffe4e6',
                'warna_teks' => '#9f1239',
                'warna_border' => '#fda4af',
                'warna_badge' => '#e11d48',
                'ikon' => 'fa-triangle-exclamation',
                'deskripsi_singkat' => 'Batas akhir dokumen & administrasi'
            ],
            'Ujian akhir semester' => [
                'nama' => 'Ujian akhir semester',
                'warna_bg' => '#e0f2fe',
                'warna_teks' => '#075985',
                'warna_border' => '#7dd3fc',
                'warna_badge' => '#0284c7',
                'ikon' => 'fa-pen-ruler',
                'deskripsi_singkat' => 'Pelaksanaan evaluasi akhir semester (UAS)'
            ],
            'Libur akademik' => [
                'nama' => 'Libur akademik',
                'warna_bg' => '#fee2e2',
                'warna_teks' => '#991b1b',
                'warna_border' => '#fca5a5',
                'warna_badge' => '#dc2626',
                'ikon' => 'fa-umbrella-beach',
                'deskripsi_singkat' => 'Libur semester & jeda akademik'
            ],
            'Kegiatan akademik lainnya' => [
                'nama' => 'Kegiatan akademik lainnya',
                'warna_bg' => '#f1f5f9',
                'warna_teks' => '#334155',
                'warna_border' => '#cbd5e1',
                'warna_badge' => '#475569',
                'ikon' => 'fa-calendar-check',
                'deskripsi_singkat' => 'Workshop, sertifikasi, kuliah umum, dll'
            ]
        ];
    }
}

if (!function_exists('getKategoriInfo')) {
    function getKategoriInfo($kategori) {
        $list = getKategoriKalenderList();
        if (isset($list[$kategori])) {
            return $list[$kategori];
        }
        return [
            'nama' => $kategori,
            'warna_bg' => '#f1f5f9',
            'warna_teks' => '#334155',
            'warna_border' => '#cbd5e1',
            'warna_badge' => '#64748b',
            'ikon' => 'fa-calendar-day',
            'deskripsi_singkat' => 'Agenda Kegiatan'
        ];
    }
}

if (!function_exists('getNamaBulanIndonesia')) {
    function getNamaBulanIndonesia($bulan) {
        $bulan = (int)$bulan;
        $nama = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $nama[$bulan] ?? '';
    }
}

if (!function_exists('ambilSemuaKegiatanKalender')) {
    /**
     * Menggabungkan kegiatan dari tabel kalender_akademik dan jadwal resmi dari pendaftaran_seminar_sidang
     * Menjamin:
     * 1. Jadwal seminar/sidang yang disahkan otomatis muncul di kalender.
     * 2. Jadwal yang belum disetujui (Menunggu/Ditolak) tidak ditampilkan.
     * 3. Jika jadwal diubah di admin, kalender otomatis ter-update (real-time query).
     * 4. Tidak ada duplikasi data di database.
     */
    function ambilSemuaKegiatanKalender($conn, $bulan = null, $tahun = null, $hanya_publikasi = true) {
        $kegiatan = [];

        // Filter rentang tanggal jika bulan dan tahun ditentukan
        $where_kalender = [];
        $where_jadwal = [];

        if ($hanya_publikasi) {
            $where_kalender[] = "status_publikasi = 'Dipublikasikan'";
        }

        if ($bulan && $tahun) {
            $tgl_awal_bulan = sprintf('%04d-%02d-01', $tahun, $bulan);
            $tgl_akhir_bulan = date('Y-m-t', strtotime($tgl_awal_bulan));

            // Untuk event multi-hari: event aktif jika mulai <= akhir_bulan dan selesai >= awal_bulan
            $where_kalender[] = "(tanggal_mulai <= '$tgl_akhir_bulan' AND tanggal_selesai >= '$tgl_awal_bulan')";
            $where_jadwal[] = "(p.tanggal BETWEEN '$tgl_awal_bulan' AND '$tgl_akhir_bulan')";
        }

        // 1. Ambil data dari tabel kalender_akademik
        $sql_kalender = "SELECT * FROM kalender_akademik";
        if (!empty($where_kalender)) {
            $sql_kalender .= " WHERE " . implode(' AND ', $where_kalender);
        }
        $sql_kalender .= " ORDER BY tanggal_mulai ASC, id ASC";

        $res_kalender = mysqli_query($conn, $sql_kalender);
        if ($res_kalender) {
            while ($row = mysqli_fetch_assoc($res_kalender)) {
                $row['tipe_sumber'] = 'agenda_prodi';
                $row['is_jadwal_resmi'] = false;
                $row['kategori_info'] = getKategoriInfo($row['kategori']);
                $kegiatan[] = $row;
            }
        }

        // 2. Ambil data jadwal resmi dari tabel pendaftaran_seminar_sidang
        // HANYA status: 'Terjadwal', 'Sedang Berlangsung', 'Selesai'
        $where_jadwal[] = "p.status IN ('Terjadwal', 'Sedang Berlangsung', 'Selesai')";
        $where_jadwal[] = "p.tanggal IS NOT NULL";

        $sql_jadwal = "SELECT p.*, 
                              m.nim, m.nama AS nama_mhs,
                              dp.nama_dosen AS nama_pembimbing, dp.gelar_akademik AS gelar_pembimbing,
                              du.nama_dosen AS nama_penguji, du.gelar_akademik AS gelar_penguji,
                              r.kode_ruangan, r.nama_ruangan, r.gedung
                       FROM pendaftaran_seminar_sidang p
                       JOIN mahasiswa m ON p.mahasiswa_id = m.id
                       LEFT JOIN dosen dp ON p.dosen_pembimbing_id = dp.id
                       LEFT JOIN dosen du ON p.dosen_penguji_id = du.id
                       LEFT JOIN ruangan r ON p.ruangan_id = r.id
                       WHERE " . implode(' AND ', $where_jadwal) . "
                       ORDER BY p.tanggal ASC, p.jam_mulai ASC";

        $res_jadwal = mysqli_query($conn, $sql_jadwal);
        if ($res_jadwal) {
            while ($j = mysqli_fetch_assoc($res_jadwal)) {
                $is_seminar = ($j['kategori'] === 'seminar' || stripos($j['jenis'], 'Seminar') !== false);
                $kategori_nama = $is_seminar ? 'Pelaksanaan seminar proposal' : 'Pelaksanaan sidang akhir';
                
                $jam_str = '';
                if (!empty($j['jam_mulai']) && !empty($j['jam_selesai'])) {
                    $jam_str = substr($j['jam_mulai'], 0, 5) . ' - ' . substr($j['jam_selesai'], 0, 5) . ' WIB';
                } elseif (!empty($j['jam_mulai'])) {
                    $jam_str = substr($j['jam_mulai'], 0, 5) . ' WIB';
                } else {
                    $jam_str = 'Waktu Ditentukan';
                }

                $ruang_str = !empty($j['nama_ruangan']) ? $j['nama_ruangan'] . ' (' . ($j['kode_ruangan'] ?? '') . ')' : 'Ruangan Terjadwal';
                $pembimbing_str = trim(($j['nama_pembimbing'] ?? '-') . (!empty($j['gelar_pembimbing']) ? ', ' . $j['gelar_pembimbing'] : ''));
                $penguji_str = trim(($j['nama_penguji'] ?? '-') . (!empty($j['gelar_penguji']) ? ', ' . $j['gelar_penguji'] : ''));

                $deskripsi_jadwal = "Jadwal resmi " . ($is_seminar ? 'Seminar Proposal' : 'Sidang Akhir Skripsi') . " mahasiswa: " . $j['nama_mhs'] . " (NIM: " . $j['nim'] . ")." . PHP_EOL .
                                    "Judul: \"" . $j['judul'] . "\"." . PHP_EOL .
                                    "Lokasi: " . $ruang_str . " - " . ($j['gedung'] ?? '') . "." . PHP_EOL .
                                    "Pembimbing: " . $pembimbing_str . " | Penguji: " . $penguji_str . "." . PHP_EOL .
                                    "Status: " . $j['status'];

                $kegiatan[] = [
                    'id' => 'jadwal_' . $j['id'],
                    'id_asli' => $j['id'],
                    'nama_kegiatan' => ($is_seminar ? 'Sempro: ' : 'Sidang: ') . $j['nama_mhs'],
                    'kategori' => $kategori_nama,
                    'tanggal_mulai' => $j['tanggal'],
                    'tanggal_selesai' => $j['tanggal'],
                    'waktu_kegiatan' => $jam_str,
                    'deskripsi' => $deskripsi_jadwal,
                    'semester' => 'Ganjil',
                    'tahun_akademik' => '2026/2027',
                    'status_publikasi' => 'Dipublikasikan',
                    'tipe_sumber' => 'jadwal_resmi_mahasiswa',
                    'is_jadwal_resmi' => true,
                    'mahasiswa_nama' => $j['nama_mhs'],
                    'mahasiswa_nim' => $j['nim'],
                    'judul_penelitian' => $j['judul'],
                    'ruangan' => $ruang_str,
                    'gedung' => $j['gedung'] ?? '',
                    'pembimbing' => $pembimbing_str,
                    'penguji' => $penguji_str,
                    'status_jadwal' => $j['status'],
                    'kategori_info' => getKategoriInfo($kategori_nama)
                ];
            }
        }

        // Urutkan kegiatan berdasarkan tanggal mulai dan waktu
        usort($kegiatan, function($a, $b) {
            $tglA = $a['tanggal_mulai'];
            $tglB = $b['tanggal_mulai'];
            if ($tglA === $tglB) {
                return strcmp($a['waktu_kegiatan'], $b['waktu_kegiatan']);
            }
            return strcmp($tglA, $tglB);
        });

        return $kegiatan;
    }
}
