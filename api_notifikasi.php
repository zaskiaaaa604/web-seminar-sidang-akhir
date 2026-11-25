<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TIK
 * FILE: api_notifikasi.php (Endpoint AJAX Notifikasi Real-time)
 * ===================================================================
 */

session_start();
require_once __DIR__ . '/koneksi.php';

// Pastikan sesi login aktif
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Sesi tidak valid atau telah berakhir. Silakan login kembali.'
    ]);
    exit;
}

$user_role = $_SESSION['role'];
$user_id   = 0;

if ($user_role === 'mahasiswa') {
    $user_id = (int)($_SESSION['mahasiswa_id'] ?? 0);
} elseif ($user_role === 'dosen') {
    $user_id = (int)($_SESSION['dosen_id'] ?? 0);
} elseif ($user_role === 'admin') {
    $user_id = (int)($_SESSION['admin_id'] ?? 0);
}

if ($user_id <= 0) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Identitas pengguna tidak ditemukan.'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_summary';

// -------------------------------------------------------------------
// 1. ACTION: REDIRECT (Tandai Dibaca lalu Buka Tautan Tujuan)
// -------------------------------------------------------------------
if ($action === 'redirect') {
    $notif_id = (int)($_GET['id'] ?? 0);
    $target_url = 'index.php';

    if ($user_role === 'mahasiswa') $target_url = 'dashboard_mahasiswa.php';
    elseif ($user_role === 'dosen') $target_url = 'dashboard_dosen.php';
    elseif ($user_role === 'admin') $target_url = 'dashboard_admin.php';

    if ($notif_id > 0) {
        // Ambil tautan notifikasi dan pastikan milik user yang sedang aktif
        $stmt = mysqli_prepare($conn, "SELECT tautan FROM notifikasi WHERE id = ? AND penerima_role = ? AND penerima_id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isi", $notif_id, $user_role, $user_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                if (!empty($row['tautan'])) {
                    $target_url = $row['tautan'];
                }
            }
            mysqli_stmt_close($stmt);
        }

        // Tandai sudah dibaca
        tandaiNotifikasiDibaca($conn, $notif_id, $user_role, $user_id);
    }

    header("Location: " . $target_url);
    exit;
}

// Untuk aksi berbasis AJAX JSON:
header('Content-Type: application/json; charset=utf-8');

// -------------------------------------------------------------------
// 2. ACTION: GET_SUMMARY (Hitung Belum Dibaca & Daftar Notifikasi Terbaru)
// -------------------------------------------------------------------
if ($action === 'get_summary') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;
    $unread_count = hitungNotifikasiBelumDibaca($conn, $user_role, $user_id);
    $notifications = ambilNotifikasiTerbaru($conn, $user_role, $user_id, $limit);

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'notifications' => $notifications,
        'user' => [
            'role' => $user_role,
            'id' => $user_id
        ],
        'timestamp' => time()
    ]);
    exit;
}

// -------------------------------------------------------------------
// 3. ACTION: MARK_READ (Tandai Satu Notifikasi Sudah Dibaca)
// -------------------------------------------------------------------
if ($action === 'mark_read') {
    $notif_id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $ok = tandaiNotifikasiDibaca($conn, $notif_id, $user_role, $user_id);
    $unread_count = hitungNotifikasiBelumDibaca($conn, $user_role, $user_id);

    echo json_encode([
        'success' => $ok,
        'unread_count' => $unread_count,
        'message' => $ok ? 'Notifikasi telah ditandai sudah dibaca.' : 'Gagal menandai notifikasi.'
    ]);
    exit;
}

// -------------------------------------------------------------------
// 4. ACTION: MARK_ALL_READ (Tandai Semua Notifikasi Sudah Dibaca)
// -------------------------------------------------------------------
if ($action === 'mark_all_read') {
    $ok = tandaiSemuaNotifikasiDibaca($conn, $user_role, $user_id);

    echo json_encode([
        'success' => $ok,
        'unread_count' => 0,
        'message' => 'Semua notifikasi berhasil ditandai sudah dibaca.'
    ]);
    exit;
}

// Aksi tidak dikenali
http_response_code(400);
echo json_encode([
    'success' => false,
    'message' => 'Aksi tidak valid.'
]);
exit;
