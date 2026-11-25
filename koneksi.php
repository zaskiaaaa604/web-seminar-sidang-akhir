<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * FILE: koneksi.php
 * ===================================================================
 * Deskripsi: File penghubung antara skrip PHP Native dan Database MySQL.
 * Digunakan oleh semua halaman yang membutuhkan akses ke database.
 */

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "db_seminar_sidang";

// Membuat koneksi
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Cek koneksi
if (!$conn) {
    die("
        <div style='font-family: sans-serif; padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin: 20px;'>
            <h3><i class='fas fa-exclamation-triangle'></i> Koneksi Database Gagal!</h3>
            <p>Pastikan modul <strong>MySQL</strong> pada XAMPP Control Panel sudah dalam keadaan <strong>Running</strong>.</p>
            <p>Pesan error: " . mysqli_connect_error() . "</p>
        </div>
    ");
}

// Set charset utf8mb4 agar mendukung karakter khusus
mysqli_set_charset($conn, "utf8mb4");

// ===================================================================
// KONSTANTA & FUNGSI BANTU KUOTA MAHASISWA BIMBINGAN DOSEN
// ===================================================================
// Batas kuota maksimal mahasiswa bimbingan per dosen
if (!defined('KUOTA_MAKSIMAL_DOSEN')) {
    define('KUOTA_MAKSIMAL_DOSEN', 8);
}

/**
 * Menghitung jumlah mahasiswa bimbingan yang usulan judulnya telah diterima (status = 'Diterima')
 * untuk seorang dosen secara dinamis dari database MySQL.
 * 
 * @param mysqli $conn Koneksi database
 * @param int $dosen_id ID dosen yang diperiksa
 * @return int Jumlah mahasiswa bimbingan aktif yang diterima
 */
function hitungBimbinganDosen($conn, $dosen_id) {
    $dosen_id = (int)$dosen_id;
    $query = "SELECT COUNT(DISTINCT mahasiswa_id) AS total FROM pengajuan_judul WHERE dosen_id = $dosen_id AND status = 'Diterima'";
    $res   = mysqli_query($conn, $query);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return (int)$row['total'];
    }
    return 0;
}

// Sertakan otomatis modul helper notifikasi otomatis
require_once __DIR__ . '/notifikasi_helper.php';
?>
