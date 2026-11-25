<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * PROGRAM STUDI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: login_dosen.php (Halaman Login Khusus Dosen)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Jika sudah login, alihkan ke Dashboard sesuai role masing-masing
if (isset($_SESSION['login']) && $_SESSION['login'] === true && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'dosen') {
        header("Location: dashboard_dosen.php");
        exit;
    } elseif ($_SESSION['role'] === 'mahasiswa') {
        header("Location: dashboard_mahasiswa.php");
        exit;
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: dashboard_admin.php");
        exit;
    }
}

$error_message = '';
$nidn_value = htmlspecialchars($_GET['nidn'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nidn     = trim($_POST['nidn'] ?? '');
    $password = $_POST['password'] ?? '';
    $nidn_value = htmlspecialchars($nidn);

    if (empty($nidn) || empty($password)) {
        $error_message = "NIDN dan Password dosen wajib diisi!";
    } else {
        $query = "SELECT id, nidn, nama_dosen, gelar_akademik, password, program_studi, email, status FROM dosen WHERE nidn = ? LIMIT 1";
        $stmt  = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $nidn);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($res)) {
                if ($row['status'] === 'Tidak Aktif') {
                    $error_message = "Akun dosen ini berstatus <strong>Tidak Aktif</strong>. Silakan hubungi Administrator Prodi TIK.";
                } else {
                    $is_password_valid = false;
                    
                    // Cek jika password sudah di-hash
                    if (!empty($row['password'])) {
                        $is_password_valid = password_verify($password, $row['password']);
                    }

                    // Dukungan password default awal: "dosen123" atau NIDN dosen
                    if (!$is_password_valid && ($password === 'dosen123' || $password === $row['nidn'])) {
                        $is_password_valid = true;
                        // Simpan otomatis dalam bentuk hash aman
                        $auto_hash = password_hash($password, PASSWORD_DEFAULT);
                        $up = mysqli_prepare($conn, "UPDATE dosen SET password = ? WHERE id = ?");
                        if ($up) {
                            mysqli_stmt_bind_param($up, "si", $auto_hash, $row['id']);
                            mysqli_stmt_execute($up);
                            mysqli_stmt_close($up);
                        }
                    }

                    if ($is_password_valid) {
                        // Set Session Dosen
                        $_SESSION['login']         = true;
                        $_SESSION['role']          = 'dosen';
                        $_SESSION['dosen_id']      = (int)$row['id'];
                        $_SESSION['nidn']          = $row['nidn'];
                        $_SESSION['nama']          = $row['nama_dosen'];
                        $_SESSION['gelar']         = $row['gelar_akademik'];
                        $_SESSION['program_studi'] = $row['program_studi'];
                        $_SESSION['email']         = $row['email'];

                        header("Location: dashboard_dosen.php");
                        exit;
                    } else {
                        $error_message = "Password yang Anda masukkan salah. Password default awal adalah <code>dosen123</code> atau NIDN Anda!";
                    }
                }
            } else {
                $error_message = "NIDN tidak ditemukan dalam database dosen. Pastikan NIDN Anda sudah didaftarkan oleh Administrator Prodi!";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Terjadi gangguan koneksi database.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Dosen - SIM-SEMSID Politeknik Negeri Lhokseumawe</title>
    
    <!-- FontAwesome Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- File CSS Utama -->
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
                <a href="index.php" class="btn btn-outline-admin" style="font-size: 0.85rem; padding: 8px 16px;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </header>

    <!-- Kartu Login Dosen -->
    <main class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-icon-circle" style="background: linear-gradient(135deg, var(--primary), #fb923c);">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <span class="auth-badge" style="background-color: var(--primary-light); color: var(--primary); border: 1px solid var(--primary-border);">
                    <i class="fas fa-user-tie"></i> Dosen Pembimbing & Penguji
                </span>
                <h2>Login Dosen Pengajar</h2>
                <p>Portal peninjauan usulan judul, bimbingan penelitian, dan penilaian seminar/sidang.</p>
            </div>

            <!-- Pesan Notifikasi Error -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.1rem; margin-top: 2px;"></i>
                    <div><?= $error_message; ?></div>
                </div>
            <?php endif; ?>

            <!-- Formulir Login -->
            <form action="login_dosen.php" method="POST" class="auth-form">
                <div class="form-field">
                    <label for="nidn"><i class="fas fa-id-card"></i> NIDN (Nomor Induk Dosen Nasional):</label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-badge"></i>
                        <input type="text" id="nidn" name="nidn" placeholder="Contoh: 0012057501" value="<?= $nidn_value; ?>" required autofocus>
                    </div>
                </div>

                <div class="form-field">
                    <label for="password"><i class="fas fa-lock"></i> Password Akun Dosen:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-key"></i>
                        <input type="password" id="password" name="password" class="has-toggle" placeholder="Masukkan password Anda" required>
                        <button type="button" class="btn-toggle-password" onclick="togglePassword('password', this)" title="Tampilkan password">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; margin-top: 6px;">
                    <i class="fas fa-sign-in-alt"></i> Masuk Portal Dosen
                </button>
            </form>

            <!-- Petunjuk Akun Demonstrasi Dosen -->
            <div class="demo-account-hint" style="background-color: var(--primary-light); border-color: var(--primary-border); color: #9a3412;">
                <strong><i class="fas fa-info-circle"></i> Akun Demonstrasi Dosen TIK:</strong>
                <p style="margin-top: 4px; font-size: 0.82rem; line-height: 1.5;">
                    NIDN: <code>0012057501</code> (Dr. Ir. Hendra Setiawan, M.T.)<br>
                    NIDN Alternatif: <code>0023087802</code> (Prof. Dr. Sri Wahyuni, M.Kom.)<br>
                    Password Awal: <code>dosen123</code>
                </p>
            </div>

            <div class="auth-footer-links">
                <p>Bukan Dosen? <a href="login_mahasiswa.php"><i class="fas fa-user-graduate"></i> Login Mahasiswa</a> &bull; <a href="login_admin.php"><i class="fas fa-user-shield"></i> Login Admin</a></p>
                <p style="margin-top: 8px;"><a href="jadwal_kalender.php"><i class="fas fa-calendar-day"></i> Lihat Jadwal & Kalender Tanpa Login</a></p>
            </div>
        </div>
    </main>

    <!-- Footer Ringkas -->
    <footer style="text-align: center; padding: 16px; font-size: 0.82rem; color: #64748b; background: white; border-top: 1px solid var(--border-color);">
        &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Jurusan Teknologi Informasi dan Komputer (TIK), Politeknik Negeri Lhokseumawe.
    </footer>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye';
                btn.setAttribute('title', 'Sembunyikan password');
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye-slash';
                btn.setAttribute('title', 'Tampilkan password');
            }
        }
    </script>
</body>
</html>
