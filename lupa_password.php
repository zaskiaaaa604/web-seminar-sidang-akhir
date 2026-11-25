<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * PROGRAM STUDI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: lupa_password.php (Pemulihan Akun & Bantuan Admin)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Jika sudah login, alihkan ke Dashboard sesuai role masing-masing
if (isset($_SESSION['login']) && $_SESSION['login'] === true && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'mahasiswa') {
        header("Location: dashboard_mahasiswa.php");
        exit;
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: dashboard_admin.php");
        exit;
    } elseif ($_SESSION['role'] === 'dosen') {
        header("Location: dashboard_dosen.php");
        exit;
    }
}

$step = 1; // Step 1: Input NIM & Tanggal Lahir, Step 2: Input Password Baru
$error_message = '';
$nim_val = '';
$tgl_val = '';
$mahasiswa_verified_id = null;
$mahasiswa_verified_nama = '';

// Proses Langkah 1: Verifikasi Identitas menggunakan NIM & Tanggal Lahir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verifikasi') {
    $nim           = trim($_POST['nim'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');

    $nim_val = htmlspecialchars($nim);
    $tgl_val = htmlspecialchars($tanggal_lahir);

    if (empty($nim) || empty($tanggal_lahir)) {
        $error_message = "NIM dan Tanggal Lahir wajib diisi untuk verifikasi identitas!";
    } else {
        $query = "SELECT id, nim, nama, tanggal_lahir FROM mahasiswa WHERE nim = ? LIMIT 1";
        $stmt  = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $nim);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($res)) {
                // Periksa kesesuaian tanggal lahir yang terdaftar (mendukung format standar YYYY-MM-DD dan variasi input)
                $tgl_input_norm = $tanggal_lahir;
                $t_stamp = strtotime($tanggal_lahir);
                if ($t_stamp) {
                    $tgl_input_norm = date('Y-m-d', $t_stamp);
                }

                if ($row['tanggal_lahir'] === $tanggal_lahir || $row['tanggal_lahir'] === $tgl_input_norm) {
                    // Verifikasi Berhasil! Simpan id ke session untuk keamanan (tamper-proof)
                    $_SESSION['reset_mhs_id']   = (int)$row['id'];
                    $_SESSION['reset_mhs_nama'] = $row['nama'];
                    $step = 2;
                    $mahasiswa_verified_id   = $row['id'];
                    $mahasiswa_verified_nama = $row['nama'];
                } else {
                    $error_message = "Tanggal lahir yang Anda masukkan tidak cocok dengan data pendaftaran NIM ini!";
                }
            } else {
                $error_message = "NIM tidak ditemukan dalam sistem. Pastikan Anda sudah membuat akun terlebih dahulu!";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Terjadi gangguan sistem koneksi database.";
        }
    }
}

// Proses Langkah 2: Simpan Password Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $mahasiswa_id        = (int)($_SESSION['reset_mhs_id'] ?? 0);
    $password_baru       = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';
    $step = 2; // Tetap di step 2 jika ada error validasi
    $mahasiswa_verified_nama = $_SESSION['reset_mhs_nama'] ?? 'Mahasiswa';

    if ($mahasiswa_id <= 0) {
        $error_message = "Sesi verifikasi telah kedaluwarsa. Silakan ulangi langkah verifikasi identitas.";
        $step = 1;
    } elseif (empty($password_baru) || empty($konfirmasi_password)) {
        $error_message = "Password baru dan konfirmasi password wajib diisi!";
    } elseif (strlen($password_baru) < 6) {
        $error_message = "Password baru minimal terdiri dari 6 karakter!";
    } elseif ($password_baru !== $konfirmasi_password) {
        $error_message = "Konfirmasi password tidak cocok dengan password baru!";
    } else {
        // Enkripsi password baru dengan password_hash()
        $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);

        $update_query = "UPDATE mahasiswa SET password = ? WHERE id = ?";
        $update_stmt  = mysqli_prepare($conn, $update_query);

        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "si", $password_hash, $mahasiswa_id);
            if (mysqli_stmt_execute($update_stmt)) {
                mysqli_stmt_close($update_stmt);

                // Hapus data sesi reset
                unset($_SESSION['reset_mhs_id'], $_SESSION['reset_mhs_nama']);

                // Berhasil reset password! Alihkan ke halaman login
                header("Location: login_mahasiswa.php?pesan=reset_berhasil");
                exit;
            } else {
                $error_message = "Gagal memperbarui password di database.";
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $error_message = "Terjadi gangguan sistem.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password & Bantuan Admin - SIM-SEMSID</title>
    
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Utama -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

    <!-- Header Sederhana -->
    <header class="auth-header-bar">
        <div class="container nav-container">
            <a href="index.php" class="logo-area">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text">
                    <h1>SIM-SEMSID</h1>
                    <span>POLITEKNIK NEGERI LHOKSEUMAWE</span>
                </div>
            </a>
            <div>
                <a href="login_mahasiswa.php" class="btn btn-outline-admin" style="font-size: 0.85rem; padding: 8px 16px;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Login
                </a>
            </div>
        </div>
    </header>

    <!-- Kartu Pemulihan Akun -->
    <main class="auth-wrapper">
        <div class="auth-card" style="max-width: 480px;">
            <div class="auth-header">
                <div class="auth-icon-circle" style="background: linear-gradient(135deg, #2563eb, #3b82f6);">
                    <i class="fas fa-user-shield"></i>
                </div>
                <span class="auth-badge mahasiswa">Program Studi Teknologi Rekayasa Perangkat Lunak</span>
                <h2>Pemulihan Akun Mahasiswa</h2>
                <p>Verifikasi data pendaftaran Anda untuk mereset password akun.</p>
            </div>

            <!-- Pesan Error -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.1rem; margin-top: 2px;"></i>
                    <div><?= $error_message; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <!-- LANGKAH 1: VERIFIKASI IDENTITAS DENGAN NIM & TANGGAL LAHIR -->
                <form action="lupa_password.php" method="POST" class="auth-form">
                    <input type="hidden" name="action" value="verifikasi">

                    <div class="form-field">
                        <label for="nim"><i class="fas fa-id-badge"></i> NIM Mahasiswa:</label>
                        <div class="input-with-icon">
                            <i class="fas fa-id-card"></i>
                            <input type="text" id="nim" name="nim" placeholder="Masukkan NIM Anda" value="<?= $nim_val; ?>" required autofocus>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="tanggal_lahir"><i class="fas fa-calendar-day"></i> Tanggal Lahir Terdaftar:</label>
                        <div class="input-with-icon">
                            <i class="fas fa-calendar-alt"></i>
                            <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= $tgl_val; ?>" required>
                        </div>
                        <small style="color: #64748b; font-size: 0.8rem;">
                            *Masukkan tanggal lahir yang Anda isi saat pendaftaran akun pertama kali.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; margin-top: 6px;">
                        <i class="fas fa-check-circle"></i> Verifikasi Identitas
                    </button>
                </form>

            <?php else: ?>
                <!-- LANGKAH 2: INPUT PASSWORD BARU SETELAH IDENTITAS TERVERIFIKASI -->
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <i class="fas fa-circle-check" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div>
                        <strong>Identitas Terverifikasi!</strong><br>
                        Halo, <strong><?= htmlspecialchars($mahasiswa_verified_nama); ?></strong>. Silakan buat password baru untuk akun Anda.
                    </div>
                </div>

                <form action="lupa_password.php" method="POST" class="auth-form">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="mahasiswa_id" value="<?= $mahasiswa_verified_id; ?>">

                    <div class="form-field">
                        <label for="password_baru"><i class="fas fa-lock"></i> Password Baru:</label>
                        <div class="input-with-icon">
                            <i class="fas fa-key"></i>
                            <input type="password" id="password_baru" name="password_baru" placeholder="Minimal 6 karakter" required autofocus>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="konfirmasi_password"><i class="fas fa-check-double"></i> Konfirmasi Password Baru:</label>
                        <div class="input-with-icon">
                            <i class="fas fa-check-circle"></i>
                            <input type="password" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ketik ulang password baru Anda" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; margin-top: 6px;">
                        <i class="fas fa-save"></i> Simpan Password Baru
                    </button>
                </form>
            <?php endif; ?>

            <!-- KOTAK BANTUAN ADMIN & SEKRETARIAT PRODI -->
            <div style="margin-top: 25px; padding: 16px; background: #f8fafc; border: 1.5px dashed var(--border-color); border-radius: var(--radius-sm); font-size: 0.85rem;">
                <h4 style="font-size: 0.95rem; color: var(--secondary); margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-headset" style="color: var(--primary);"></i> Butuh Bantuan Admin Prodi?
                </h4>
                <p style="color: var(--text-muted); margin-bottom: 8px;">
                    Jika Anda lupa tanggal lahir yang didaftarkan atau akun terkendala, silakan hubungi pengelola program studi TIK dengan membawa KTM:
                </p>
                <ul style="list-style: none; padding: 0; margin: 0; color: #475569; display: flex; flex-direction: column; gap: 4px;">
                    <li><i class="fas fa-building" style="color: var(--primary); width: 18px;"></i> Ruang Jurusan TIK Lt. 1, PNL</li>
                    <li><i class="fas fa-envelope" style="color: var(--primary); width: 18px;"></i> admin.tik@pnl.ac.id</li>
                    <li><i class="fas fa-phone-alt" style="color: var(--primary); width: 18px;"></i> (0645) 42615 / Ext. Prodi TIK</li>
                </ul>
            </div>

            <div class="auth-footer-links">
                <p><a href="login_mahasiswa.php"><i class="fas fa-arrow-left"></i> Kembali ke Halaman Login</a></p>
            </div>
        </div>
    </main>

    <!-- Footer Ringkas -->
    <footer style="text-align: center; padding: 16px; font-size: 0.82rem; color: #64748b; background: white; border-top: 1px solid var(--border-color);">
        &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Program Studi Teknologi Rekayasa Perangkat Lunak, Politeknik Negeri Lhokseumawe.
    </footer>

</body>
</html>
