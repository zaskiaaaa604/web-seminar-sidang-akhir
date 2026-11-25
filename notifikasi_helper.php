<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TIK
 * FILE: notifikasi_helper.php (Helper Sistem Notifikasi Otomatis)
 * ===================================================================
 */

if (!function_exists('kirimNotifikasi')) {
    /**
     * Mengirim notifikasi otomatis ke penerima tertentu (Mahasiswa, Dosen, atau Admin).
     * Dilengkapi pencegahan notifikasi duplikat dalam rentang waktu singkat.
     *
     * @param mysqli $conn
     * @param string $penerima_role 'mahasiswa' | 'dosen' | 'admin'
     * @param int $penerima_id ID akun pengguna penerima
     * @param string $judul Judul singkat notifikasi
     * @param string $pesan Detail isi pesan notifikasi
     * @param string|null $tautan Alamat halaman saat notifikasi diklik
     * @param string $tipe Kategori notifikasi ('pengajuan', 'revisi', 'diterima', 'ditolak', 'jadwal', 'verifikasi', 'komentar', 'info')
     * @return bool True jika berhasil disimpan, False jika gagal atau duplikat
     */
    function kirimNotifikasi($conn, $penerima_role, $penerima_id, $judul, $pesan, $tautan = null, $tipe = 'info') {
        $allowed_roles = ['mahasiswa', 'dosen', 'admin'];
        if (!$conn || !in_array($penerima_role, $allowed_roles) || (int)$penerima_id <= 0) {
            return false;
        }

        $penerima_id = (int)$penerima_id;
        $judul = trim($judul);
        $pesan = trim($pesan);

        // 1. Cegah Duplikasi Notifikasi (Jika ada pesan dan judul identik yang dikirim ke penerima sama dalam 10 detik terakhir)
        $cek_sql = "SELECT id FROM notifikasi 
                    WHERE penerima_role = ? AND penerima_id = ? AND judul = ? AND pesan = ? 
                      AND created_at >= NOW() - INTERVAL 10 SECOND 
                    LIMIT 1";
        $cek_stmt = mysqli_prepare($conn, $cek_sql);
        if ($cek_stmt) {
            mysqli_stmt_bind_param($cek_stmt, "siss", $penerima_role, $penerima_id, $judul, $pesan);
            mysqli_stmt_execute($cek_stmt);
            mysqli_stmt_store_result($cek_stmt);
            if (mysqli_stmt_num_rows($cek_stmt) > 0) {
                mysqli_stmt_close($cek_stmt);
                return true; // Sudah ada notifikasi yang identik baru saja dibuat, hindari duplikasi
            }
            mysqli_stmt_close($cek_stmt);
        }

        // 2. Simpan Notifikasi Baru Menggunakan Prepared Statement
        $insert_sql = "INSERT INTO notifikasi (penerima_role, penerima_id, judul, pesan, tautan, tipe, is_read) 
                       VALUES (?, ?, ?, ?, ?, ?, 0)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sissss", $penerima_role, $penerima_id, $judul, $pesan, $tautan, $tipe);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        }

        return false;
    }
}

if (!function_exists('kirimNotifikasiAdmin')) {
    /**
     * Mengirim notifikasi otomatis ke semua Administrator aktif.
     *
     * @param mysqli $conn
     * @param string $judul
     * @param string $pesan
     * @param string|null $tautan
     * @param string $tipe
     * @return int Jumlah admin yang berhasil dikirimi notifikasi
     */
    function kirimNotifikasiAdmin($conn, $judul, $pesan, $tautan = null, $tipe = 'info') {
        if (!$conn) return 0;
        $count = 0;
        $res = mysqli_query($conn, "SELECT id FROM admin");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                if (kirimNotifikasi($conn, 'admin', (int)$row['id'], $judul, $pesan, $tautan, $tipe)) {
                    $count++;
                }
            }
        }
        return $count;
    }
}

if (!function_exists('hitungNotifikasiBelumDibaca')) {
    /**
     * Menghitung jumlah notifikasi yang belum dibaca (is_read = 0) untuk akun pengguna aktif.
     *
     * @param mysqli $conn
     * @param string $penerima_role
     * @param int $penerima_id
     * @return int
     */
    function hitungNotifikasiBelumDibaca($conn, $penerima_role, $penerima_id) {
        if (!$conn || (int)$penerima_id <= 0) return 0;
        $sql = "SELECT COUNT(*) AS total FROM notifikasi 
                WHERE penerima_role = ? AND penerima_id = ? AND is_read = 0";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $pid = (int)$penerima_id;
            mysqli_stmt_bind_param($stmt, "si", $penerima_role, $pid);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);
            return (int)($row['total'] ?? 0);
        }
        return 0;
    }
}

if (!function_exists('ambilNotifikasiTerbaru')) {
    /**
     * Mengambil daftar notifikasi terbaru milik pengguna.
     *
     * @param mysqli $conn
     * @param string $penerima_role
     * @param int $penerima_id
     * @param int $limit
     * @return array
     */
    function ambilNotifikasiTerbaru($conn, $penerima_role, $penerima_id, $limit = 6) {
        $data = [];
        if (!$conn || (int)$penerima_id <= 0) return $data;
        $limit = max(1, (int)$limit);

        $sql = "SELECT id, judul, pesan, tautan, tipe, is_read, created_at 
                FROM notifikasi 
                WHERE penerima_role = ? AND penerima_id = ? 
                ORDER BY created_at DESC, id DESC 
                LIMIT ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $pid = (int)$penerima_id;
            mysqli_stmt_bind_param($stmt, "sii", $penerima_role, $pid, $limit);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $row['waktu_lalu'] = formatWaktuLalu($row['created_at']);
                $data[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        return $data;
    }
}

if (!function_exists('tandaiNotifikasiDibaca')) {
    /**
     * Menandai satu notifikasi tertentu sebagai sudah dibaca (is_read = 1).
     * Dilengkapi validasi kepemilikan untuk mencegah akses notifikasi pengguna lain.
     *
     * @param mysqli $conn
     * @param int $notif_id
     * @param string $penerima_role
     * @param int $penerima_id
     * @return bool
     */
    function tandaiNotifikasiDibaca($conn, $notif_id, $penerima_role, $penerima_id) {
        if (!$conn || (int)$notif_id <= 0 || (int)$penerima_id <= 0) return false;
        $sql = "UPDATE notifikasi SET is_read = 1 
                WHERE id = ? AND penerima_role = ? AND penerima_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $nid = (int)$notif_id;
            $pid = (int)$penerima_id;
            mysqli_stmt_bind_param($stmt, "isi", $nid, $penerima_role, $pid);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        }
        return false;
    }
}

if (!function_exists('tandaiSemuaNotifikasiDibaca')) {
    /**
     * Menandai seluruh notifikasi milik pengguna yang sedang login sebagai sudah dibaca.
     *
     * @param mysqli $conn
     * @param string $penerima_role
     * @param int $penerima_id
     * @return bool
     */
    function tandaiSemuaNotifikasiDibaca($conn, $penerima_role, $penerima_id) {
        if (!$conn || (int)$penerima_id <= 0) return false;
        $sql = "UPDATE notifikasi SET is_read = 1 
                WHERE penerima_role = ? AND penerima_id = ? AND is_read = 0";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $pid = (int)$penerima_id;
            mysqli_stmt_bind_param($stmt, "si", $penerima_role, $pid);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        }
        return false;
    }
}

if (!function_exists('formatWaktuLalu')) {
    /**
     * Mengubah timestamp database menjadi format ramah bahasa Indonesia.
     * Contoh: 'Baru saja', '5 menit lalu', '2 jam lalu', 'Kemarin 14:30', atau '25 Nov 2026'.
     *
     * @param string $datetime
     * @return string
     */
    function formatWaktuLalu($datetime) {
        $timestamp = strtotime($datetime);
        if (!$timestamp) return '-';
        $selisih = time() - $timestamp;

        if ($selisih < 30) {
            return 'Baru saja';
        } elseif ($selisih < 60) {
            return $selisih . ' detik lalu';
        } elseif ($selisih < 3600) {
            $menit = floor($selisih / 60);
            return $menit . ' menit lalu';
        } elseif ($selisih < 86400) {
            $jam = floor($selisih / 3600);
            return $jam . ' jam lalu';
        } elseif ($selisih < 172800) {
            return 'Kemarin, ' . date('H:i', $timestamp);
        } else {
            $bulan = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
                7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
            ];
            $b = $bulan[(int)date('n', $timestamp)] ?? date('M', $timestamp);
            return date('d', $timestamp) . ' ' . $b . ' ' . date('Y H:i', $timestamp);
        }
    }
}
