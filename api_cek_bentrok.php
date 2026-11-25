<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: api_cek_bentrok.php (API Validasi Bentrok Jadwal Real-Time)
 * ===================================================================
 */

header('Content-Type: application/json; charset=utf-8');
require_once 'koneksi.php';
require_once 'jadwal_bentrok_helper.php';

session_start();
// Hanya pengguna yang terautentikasi (admin) yang dapat mengakses
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses tidak diizinkan. Silakan login terlebih dahulu.'
    ]);
    exit;
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

$data_cek = [
    'mahasiswa_id'        => (int)($input['mahasiswa_id'] ?? 0),
    'dosen_pembimbing_id' => (int)($input['dosen_pembimbing_id'] ?? 0),
    'dosen_penguji_id'    => (int)($input['dosen_penguji_id'] ?? 0),
    'ruangan_id'          => !empty($input['ruangan_id']) ? (int)$input['ruangan_id'] : null,
    'tanggal'             => trim($input['tanggal'] ?? ''),
    'jam_mulai'           => trim($input['jam_mulai'] ?? ''),
    'jam_selesai'         => trim($input['jam_selesai'] ?? ''),
    'exclude_id'          => (int)($input['exclude_id'] ?? 0)
];

// Lakukan pengecekan bentrok jadwal
$hasil = cekBentrokJadwal($conn, $data_cek, false);

echo json_encode([
    'status' => 'success',
    'bentrok' => $hasil['bentrok'],
    'penyebab' => $hasil['penyebab'],
    'pesan' => $hasil['pesan'],
    'jadwal_konflik' => $hasil['jadwal_konflik']
]);
