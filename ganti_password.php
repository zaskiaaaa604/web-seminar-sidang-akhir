<?php
session_start();
// Fitur Ganti Password telah dihapus dari antarmuka pengguna sesuai permintaan sistem
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: dashboard_admin.php");
    } elseif ($_SESSION['role'] === 'dosen') {
        header("Location: dashboard_dosen.php");
    } else {
        header("Location: dashboard_mahasiswa.php");
    }
} else {
    header("Location: index.php");
}
exit;
