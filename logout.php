<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * FILE: logout.php
 * ===================================================================
 * Deskripsi: Menghapus seluruh session pengguna yang sedang aktif
 * dan mengembalikan pengguna ke halaman utama.
 */

session_start();

// Hapus semua variabel session
$_SESSION = [];

// Hapus cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Alihkan kembali ke halaman utama dengan pesan sukses logout
header("Location: index.php?pesan=logout_berhasil");
exit;
?>
