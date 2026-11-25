<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: jadwal_bentrok_helper.php
 * FITUR: Pencegahan Jadwal Bentrok Otomatis (Interval Waktu & Transaksi)
 * ===================================================================
 */

if (!function_exists('cekBentrokJadwal')) {
    /**
     * Memeriksa apakah jadwal yang diajukan bertabrakan dengan jadwal yang sudah ada.
     * 
     * Aturan Interval Waktu:
     * Jadwal bentrok jika: waktu_mulai_baru < waktu_selesai_lama DAN waktu_selesai_baru > waktu_mulai_lama
     * 
     * Memeriksa:
     * 1. Mahasiswa (tidak boleh 2 jadwal seminar/sidang bersamaan)
     * 2. Dosen Pembimbing (tidak boleh sibuk sebagai pembimbing/penguji di waktu sama)
     * 3. Dosen Penguji (tidak boleh sibuk sebagai penguji/pembimbing di waktu sama)
     * 4. Ruangan (satu ruangan tidak boleh 2 kegiatan di waktu sama)
     * 
     * @param mysqli $conn Koneksi database
     * @param array $data Data jadwal yang akan diperiksa
     * @param bool $for_update Gunakan SELECT ... FOR UPDATE untuk mekanisme penguncian transaksi
     * @return array Hasil pemeriksaan ['bentrok' => bool, 'penyebab' => string, 'pesan' => string, 'jadwal_konflik' => array]
     */
    function cekBentrokJadwal($conn, $data, $for_update = false) {
        $mahasiswa_id        = (int)($data['mahasiswa_id'] ?? 0);
        $dosen_pembimbing_id = (int)($data['dosen_pembimbing_id'] ?? 0);
        $dosen_penguji_id    = (int)($data['dosen_penguji_id'] ?? 0);
        $ruangan_id          = !empty($data['ruangan_id']) ? (int)$data['ruangan_id'] : null;
        $tanggal             = trim($data['tanggal'] ?? '');
        $jam_mulai           = trim($data['jam_mulai'] ?? '');
        $jam_selesai         = trim($data['jam_selesai'] ?? '');
        $exclude_id          = (int)($data['exclude_id'] ?? 0);

        // Standarisasi format jam (pastikan minimal HH:MM)
        if (strlen($jam_mulai) === 5) $jam_mulai .= ':00';
        if (strlen($jam_selesai) === 5) $jam_selesai .= ':00';

        // 1. Validasi Keberadaan Waktu
        if (empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) {
            return [
                'bentrok' => false,
                'penyebab' => '',
                'pesan' => '',
                'jadwal_konflik' => null
            ];
        }

        // 2. Validasi Urutan Waktu: jam mulai harus lebih awal dari jam selesai
        if ($jam_mulai >= $jam_selesai) {
            return [
                'bentrok' => true,
                'penyebab' => 'waktu_tidak_valid',
                'pesan' => "Waktu tidak valid: Jam selesai (" . substr($jam_selesai, 0, 5) . " WIB) harus lebih akhir dari jam mulai (" . substr($jam_mulai, 0, 5) . " WIB)!",
                'jadwal_konflik' => null
            ];
        }

        // 3. Validasi Dosen Pembimbing dan Penguji tidak boleh orang yang sama
        if ($dosen_pembimbing_id > 0 && $dosen_penguji_id > 0 && $dosen_pembimbing_id === $dosen_penguji_id) {
            $q_dosen = mysqli_query($conn, "SELECT nama_dosen, gelar_akademik FROM dosen WHERE id = $dosen_pembimbing_id LIMIT 1");
            $d_nama = ($row_d = mysqli_fetch_assoc($q_dosen)) ? ($row_d['nama_dosen'] . ', ' . $row_d['gelar_akademik']) : 'Dosen Tersebut';

            return [
                'bentrok' => true,
                'penyebab' => 'dosen_sama',
                'pesan' => "Dosen pembimbing dan dosen penguji tidak boleh dosen yang sama ($d_nama) pada satu jadwal ujian!",
                'jadwal_konflik' => null
            ];
        }

        $lock_clause = $for_update ? " FOR UPDATE" : "";

        // Template Query Pengecekan Bentrok Lintas Seminar dan Sidang
        // Status yang dihitung bentrok: 'Menunggu Verifikasi', 'Terjadwal', 'Sedang Berlangsung', 'Selesai'
        // Status 'Ditolak' tidak dihitung bentrok
        $base_sql = "SELECT p.*, 
                            m.nama AS nama_mhs, m.nim,
                            d1.nama_dosen AS nama_pembimbing, d1.gelar_akademik AS gelar_pembimbing,
                            d2.nama_dosen AS nama_penguji, d2.gelar_akademik AS gelar_penguji,
                            r.nama_ruangan, r.kode_ruangan
                     FROM pendaftaran_seminar_sidang p
                     JOIN mahasiswa m ON p.mahasiswa_id = m.id
                     LEFT JOIN dosen d1 ON p.dosen_pembimbing_id = d1.id
                     LEFT JOIN dosen d2 ON p.dosen_penguji_id = d2.id
                     LEFT JOIN ruangan r ON p.ruangan_id = r.id
                     WHERE p.tanggal = ?
                       AND (? < p.jam_selesai AND ? > p.jam_mulai)
                       AND p.status IN ('Menunggu Verifikasi', 'Terjadwal', 'Sedang Berlangsung', 'Selesai')
                       AND p.id != ?";

        // ===============================================================
        // A. CEK BENTROK: MAHASISWA
        // Mahasiswa tidak boleh memiliki 2 jadwal ujian yang bertabrakan
        // ===============================================================
        if ($mahasiswa_id > 0) {
            $sql_mhs = $base_sql . " AND p.mahasiswa_id = ?" . $lock_clause;
            $stmt = mysqli_prepare($conn, $sql_mhs);
            mysqli_stmt_bind_param($stmt, "sssii", $tanggal, $jam_mulai, $jam_selesai, $exclude_id, $mahasiswa_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                mysqli_stmt_close($stmt);
                $jam_lama = substr($row['jam_mulai'], 0, 5) . '–' . substr($row['jam_selesai'], 0, 5) . ' WIB';
                $ruang_lama = $row['nama_ruangan'] ?? 'Ruangan Ditentukan';
                return [
                    'bentrok' => true,
                    'penyebab' => 'mahasiswa',
                    'pesan' => "Jadwal tidak dapat disimpan karena mahasiswa " . $row['nama_mhs'] . " (NIM: " . $row['nim'] . ") sudah memiliki jadwal " . $row['jenis'] . " pada pukul $jam_lama di $ruang_lama.",
                    'jadwal_konflik' => formatDetailKonflik($row)
                ];
            }
            mysqli_stmt_close($stmt);
        }

        // ===============================================================
        // B. CEK BENTROK: DOSEN PEMBIMBING
        // Dosen pembimbing tidak boleh ditugaskan pada 2 kegiatan bertabrakan
        // (baik sebagai pembimbing maupun sebagai penguji di kegiatan lain)
        // ===============================================================
        if ($dosen_pembimbing_id > 0) {
            $sql_pembimbing = $base_sql . " AND (p.dosen_pembimbing_id = ? OR p.dosen_penguji_id = ?)" . $lock_clause;
            $stmt = mysqli_prepare($conn, $sql_pembimbing);
            mysqli_stmt_bind_param($stmt, "sssiii", $tanggal, $jam_mulai, $jam_selesai, $exclude_id, $dosen_pembimbing_id, $dosen_pembimbing_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                mysqli_stmt_close($stmt);
                $dosen_nama = ($row['dosen_pembimbing_id'] == $dosen_pembimbing_id) 
                    ? ($row['nama_pembimbing'] . (!empty($row['gelar_pembimbing']) ? ', ' . $row['gelar_pembimbing'] : ''))
                    : ($row['nama_penguji'] . (!empty($row['gelar_penguji']) ? ', ' . $row['gelar_penguji'] : ''));

                $peran_lama = ($row['dosen_pembimbing_id'] == $dosen_pembimbing_id) ? 'Dosen Pembimbing' : 'Dosen Penguji';
                $jam_lama = substr($row['jam_mulai'], 0, 5) . '–' . substr($row['jam_selesai'], 0, 5) . ' WIB';
                $ruang_lama = $row['nama_ruangan'] ?? 'Ruangan Ujian';

                return [
                    'bentrok' => true,
                    'penyebab' => 'dosen_pembimbing',
                    'pesan' => "Jadwal tidak dapat disimpan karena dosen pembimbing ($dosen_nama) sudah bertugas sebagai $peran_lama pada jadwal " . $row['jenis'] . " (Mahasiswa: " . $row['nama_mhs'] . ") pukul $jam_lama di $ruang_lama.",
                    'jadwal_konflik' => formatDetailKonflik($row)
                ];
            }
            mysqli_stmt_close($stmt);
        }

        // ===============================================================
        // C. CEK BENTROK: DOSEN PENGUJI
        // Dosen penguji tidak boleh menjadi penguji/pembimbing di 2 kegiatan bertabrakan
        // ===============================================================
        if ($dosen_penguji_id > 0) {
            $sql_penguji = $base_sql . " AND (p.dosen_penguji_id = ? OR p.dosen_pembimbing_id = ?)" . $lock_clause;
            $stmt = mysqli_prepare($conn, $sql_penguji);
            mysqli_stmt_bind_param($stmt, "sssiii", $tanggal, $jam_mulai, $jam_selesai, $exclude_id, $dosen_penguji_id, $dosen_penguji_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                mysqli_stmt_close($stmt);
                $dosen_nama = ($row['dosen_penguji_id'] == $dosen_penguji_id) 
                    ? ($row['nama_penguji'] . (!empty($row['gelar_penguji']) ? ', ' . $row['gelar_penguji'] : ''))
                    : ($row['nama_pembimbing'] . (!empty($row['gelar_pembimbing']) ? ', ' . $row['gelar_pembimbing'] : ''));

                $peran_lama = ($row['dosen_penguji_id'] == $dosen_penguji_id) ? 'Dosen Penguji' : 'Dosen Pembimbing';
                $jam_lama = substr($row['jam_mulai'], 0, 5) . '–' . substr($row['jam_selesai'], 0, 5) . ' WIB';
                $ruang_lama = $row['nama_ruangan'] ?? 'Ruangan Ujian';

                return [
                    'bentrok' => true,
                    'penyebab' => 'dosen_penguji',
                    'pesan' => "Jadwal tidak dapat disimpan karena dosen penguji ($dosen_nama) sudah memiliki jadwal " . $row['jenis'] . " pada pukul $jam_lama di $ruang_lama (Mahasiswa: " . $row['nama_mhs'] . ").",
                    'jadwal_konflik' => formatDetailKonflik($row)
                ];
            }
            mysqli_stmt_close($stmt);
        }

        // ===============================================================
        // D. CEK BENTROK: RUANGAN
        // Satu ruangan tidak boleh digunakan untuk 2 kegiatan pada waktu yang bertabrakan
        // ===============================================================
        if ($ruangan_id && $ruangan_id > 0) {
            $sql_ruang = $base_sql . " AND p.ruangan_id = ?" . $lock_clause;
            $stmt = mysqli_prepare($conn, $sql_ruang);
            mysqli_stmt_bind_param($stmt, "sssii", $tanggal, $jam_mulai, $jam_selesai, $exclude_id, $ruangan_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                mysqli_stmt_close($stmt);
                $ruang_nama = $row['nama_ruangan'] ?? ('Ruang ID ' . $ruangan_id);
                $jam_lama = substr($row['jam_mulai'], 0, 5) . '–' . substr($row['jam_selesai'], 0, 5) . ' WIB';
                return [
                    'bentrok' => true,
                    'penyebab' => 'ruangan',
                    'pesan' => "Jadwal tidak dapat disimpan karena ruangan $ruang_nama sudah digunakan untuk kegiatan " . $row['jenis'] . " (Mahasiswa: " . $row['nama_mhs'] . ") pada pukul $jam_lama.",
                    'jadwal_konflik' => formatDetailKonflik($row)
                ];
            }
            mysqli_stmt_close($stmt);
        }

        // Jika semua pengecekan lolos tanpa ada satupun bentrokan
        return [
            'bentrok' => false,
            'penyebab' => '',
            'pesan' => 'Jadwal aman dan tidak bertabrakan dengan kegiatan lain.',
            'jadwal_konflik' => null
        ];
    }
}

if (!function_exists('formatDetailKonflik')) {
    /**
     * Memformat detail jadwal yang bertabrakan untuk ditampilkan di UI alert peringatan
     */
    function formatDetailKonflik($row) {
        if (!$row) return null;
        return [
            'id' => (int)$row['id'],
            'jenis' => $row['jenis'],
            'kategori' => $row['kategori'],
            'judul' => $row['judul'],
            'mahasiswa' => $row['nama_mhs'],
            'nim' => $row['nim'],
            'pembimbing' => trim(($row['nama_pembimbing'] ?? '-') . (!empty($row['gelar_pembimbing']) ? ', ' . $row['gelar_pembimbing'] : '')),
            'penguji' => trim(($row['nama_penguji'] ?? '-') . (!empty($row['gelar_penguji']) ? ', ' . $row['gelar_penguji'] : '')),
            'ruangan' => $row['nama_ruangan'] ?? 'Ruangan Ditentukan',
            'kode_ruangan' => $row['kode_ruangan'] ?? '-',
            'tanggal' => $row['tanggal'],
            'jam_mulai' => substr($row['jam_mulai'], 0, 5),
            'jam_selesai' => substr($row['jam_selesai'], 0, 5),
            'status' => $row['status']
        ];
    }
}

if (!function_exists('renderAlertBentrok')) {
    /**
     * Menghasilkan HTML alert peringatan jadwal bentrok yang modern dan detail
     */
    function renderAlertBentrok($konflik_data) {
        if (empty($konflik_data) || empty($konflik_data['bentrok'])) return '';

        $pesan = htmlspecialchars($konflik_data['pesan']);
        $penyebab = htmlspecialchars($konflik_data['penyebab']);
        $jk = $konflik_data['jadwal_konflik'];

        $label_penyebab = [
            'mahasiswa' => 'Bentrokan Jadwal Mahasiswa',
            'dosen_pembimbing' => 'Bentrokan Jadwal Dosen Pembimbing',
            'dosen_penguji' => 'Bentrokan Jadwal Dosen Penguji',
            'dosen_sama' => 'Kombinasi Dosen Tidak Sah',
            'ruangan' => 'Bentrokan Pemakaian Ruangan',
            'waktu_tidak_valid' => 'Waktu Jadwal Tidak Sah'
        ][$penyebab] ?? 'Bentrokan Jadwal Terdeteksi';

        $html = '<div class="alert-bentrok-box" style="background: #fff1f2; border: 2px solid #f43f5e; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(244, 63, 94, 0.12);">';
        $html .= '  <div style="display: flex; align-items: flex-start; gap: 14px;">';
        $html .= '      <div style="width: 42px; height: 42px; border-radius: 10px; background: #ffe4e6; color: #e11d48; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;">';
        $html .= '          <i class="fas fa-triangle-exclamation"></i>';
        $html .= '      </div>';
        $html .= '      <div style="flex: 1;">';
        $html .= '          <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">';
        $html .= '              <span style="background: #e11d48; color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">' . $label_penyebab . '</span>';
        $html .= '              <strong style="color: #9f1239; font-size: 1.05rem;">Penyimpanan Jadwal Dibatalkan Otomatis</strong>';
        $html .= '          </div>';
        $html .= '          <p style="color: #881337; margin: 4px 0 10px; font-size: 0.92rem; line-height: 1.5; font-weight: 600;">' . $pesan . '</p>';

        if ($jk) {
            $html .= '      <div style="background: white; border: 1px solid #fecdd3; border-radius: 8px; padding: 12px 16px; margin-top: 10px;">';
            $html .= '          <div style="font-size: 0.78rem; font-weight: 800; color: #9f1239; text-transform: uppercase; margin-bottom: 6px;"><i class="fas fa-calendar-xmark"></i> Jadwal Yang Sedang Menggunakan Slot Waktu Tersebut:</div>';
            $html .= '          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; font-size: 0.85rem; color: #334155;">';
            $html .= '              <div><strong>Kegiatan:</strong> ' . htmlspecialchars($jk['jenis']) . '</div>';
            $html .= '              <div><strong>Mahasiswa:</strong> ' . htmlspecialchars($jk['mahasiswa']) . ' (' . htmlspecialchars($jk['nim']) . ')</div>';
            $html .= '              <div><strong>Waktu:</strong> ' . htmlspecialchars($jk['tanggal']) . ' (' . htmlspecialchars($jk['jam_mulai']) . ' - ' . htmlspecialchars($jk['jam_selesai']) . ' WIB)</div>';
            $html .= '              <div><strong>Ruangan:</strong> ' . htmlspecialchars($jk['ruangan']) . '</div>';
            $html .= '              <div style="grid-column: 1 / -1;"><strong>Pembimbing / Penguji:</strong> ' . htmlspecialchars($jk['pembimbing']) . ' / ' . htmlspecialchars($jk['penguji']) . '</div>';
            $html .= '          </div>';
            $html .= '      </div>';
        }

        $html .= '          <div style="margin-top: 12px; font-size: 0.82rem; color: #9f1239; display: flex; align-items: center; gap: 6px;">';
        $html .= '              <i class="fas fa-lightbulb" style="color: #e11d48;"></i>';
        $html .= '              <span><strong>Rekomendasi Admin:</strong> Silakan atur kembali waktu mulai/selesai, pilih tanggal pelaksanaan lain, ganti ruangan, atau pilih dosen lain yang sedang tidak terjadwal.</span>';
        $html .= '          </div>';
        $html .= '      </div>';
        $html .= '  </div>';
        $html .= '</div>';

        return $html;
    }
}
