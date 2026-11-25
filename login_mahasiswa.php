<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * FILE: login_mahasiswa.php (Halaman Login Mahasiswa)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Jika sudah login, alihkan ke Dashboard sesuai role masing-masing
if (isset($_SESSION['login']) && $_SESSION['login'] === true && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'mahasiswa') {
        header("Location: dashboard_mahasiswa.php");
        exit;
    } elseif ($_SESSION['role'] === 'dosen') {
        header("Location: dashboard_dosen.php");
        exit;
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: dashboard_admin.php");
        exit;
    }
}

$error_message = '';
$success_message = '';
$nim_value = htmlspecialchars($_GET['nim'] ?? '');

// Menangkap notifikasi dari halaman registrasi / lupa password
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'daftar_berhasil') {
        $success_message = "Pendaftaran akun berhasil! Silakan login menggunakan NIM dan password baru yang telah Anda buat.";
    } elseif ($_GET['pesan'] === 'reset_berhasil') {
        $success_message = "Password berhasil diperbarui! Silakan login menggunakan password baru Anda.";
    }
}

// Memproses data formulir saat tombol login ditekan (metode POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim = trim($_POST['nim'] ?? '');
    $password = $_POST['password'] ?? '';
    $nim_value = htmlspecialchars($nim);

    // 1. Validasi Input Tidak Boleh Kosong
    if (empty($nim) || empty($password)) {
        $error_message = "NIM dan Password wajib diisi!";
    } else {
        // 2. Ambil data mahasiswa dari MySQL berdasarkan NIM menggunakan Prepared Statement
        $query = "SELECT id, nim, nama, password, program_studi, email FROM mahasiswa WHERE nim = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $nim);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                // 3. Verifikasi Password yang dienkripsi menggunakan password_verify()
                $is_password_valid = password_verify($password, $row['password']);

                // Dukungan ramah jika mahasiswa memasukkan format tanggal lahir variasi
                if (!$is_password_valid) {
                    $time_input = strtotime($password);
                    if ($time_input) {
                        $alt_pass = date('d-m-Y', $time_input);
                        $is_password_valid = password_verify($alt_pass, $row['password']);
                    }
                }

                if ($is_password_valid) {
                    // Password cocok! Buat Session Login
                    $_SESSION['login'] = true;
                    $_SESSION['role'] = 'mahasiswa';
                    $_SESSION['mahasiswa_id'] = $row['id'];
                    $_SESSION['nim'] = $row['nim'];
                    $_SESSION['nama'] = $row['nama'];
                    $_SESSION['program_studi'] = $row['program_studi'];
                    $_SESSION['email'] = $row['email'];

                    // Arahkan langsung ke Dashboard Mahasiswa
                    header("Location: dashboard_mahasiswa.php");
                    exit;
                } else {
                    $error_message = "Password yang Anda masukkan salah. Gunakan tanggal lahir Anda (Format: DD-MM-YYYY) jika belum pernah mengganti password!";
                }
            } else {
                $error_message = "NIM tidak terdaftar di sistem. Jika Anda belum pernah membuat akun, silakan klik tombol <strong>Daftar di Sini</strong> terlebih dahulu!";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Terjadi kesalahan pada sistem database.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Mahasiswa - SIM-SEMSID Politeknik Negeri Lhokseumawe</title>
    
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

    <!-- Kartu Login Mahasiswa -->
    <main class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-icon-circle">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <span class="auth-badge mahasiswa">Jurusan Teknologi Informasi dan Komputer (TIK)</span>
                <h2>Login Mahasiswa</h2>
                <p>Masukkan Nomor Induk Mahasiswa (NIM) dan password akun Anda.</p>
            </div>

            <!-- Pesan Notifikasi Sukses Pendaftaran / Reset Password -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div><?= htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Pesan Notifikasi Error -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.1rem; margin-top: 2px;"></i>
                    <div><?= $error_message; ?></div>
                </div>
            <?php endif; ?>

            <!-- Formulir Login -->
            <form action="login_mahasiswa.php" method="POST" class="auth-form">
                <div class="form-field">
                    <label for="nim"><i class="fas fa-id-badge"></i> NIM (Nomor Induk Mahasiswa):</label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" id="nim" name="nim" placeholder="Contoh: 2026583050016" value="<?= $nim_value; ?>" required autofocus>
                    </div>
                </div>

                <div class="form-field">
                    <label for="password"><i class="fas fa-lock"></i> Password:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-key"></i>
                        <input type="password" id="password" name="password" class="has-toggle" placeholder="Masukkan password Anda" required>
                        <button type="button" class="btn-toggle-password" onclick="togglePassword('password', this)" title="Tampilkan password">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; margin-top: 6px;">
                    <i class="fas fa-sign-in-alt"></i> Masuk Sekarang
                </button>

                <a href="register_mahasiswa.php" class="btn btn-outline-primary" style="width: 100%; padding: 11px; font-size: 0.92rem; text-decoration: none; margin-top: 4px;">
                    <i class="fas fa-user-plus"></i> Belum Punya Akun? Daftar di Sini
                </a>
            </form>

            <!-- Petunjuk Akses Mahasiswa -->
            <div class="demo-account-hint">
                <strong><i class="fas fa-info-circle"></i> Ketentuan Akses Mahasiswa:</strong>
                <p style="margin-top: 4px; font-size: 0.82rem; color: #78350f; line-height: 1.5;">
                    Bagi mahasiswa yang belum memiliki akun, silakan klik tombol <strong>Daftar di Sini</strong> untuk mendaftarkan akun secara mandiri.
                </p>
                <p style="margin-top: 6px; font-size: 0.8rem; color: #92400e;">
                    <i class="fas fa-key"></i> Password awal akun adalah tanggal lahir masing-masing mahasiswa (Format: <code>DD-MM-YYYY</code>, contoh: <code>28-08-2008</code>).
                </p>
            </div>

            <div class="auth-footer-links">
                <p><a href="lupa_password.php"><i class="fas fa-question-circle"></i> Lupa Password / Pemulihan Akun</a></p>
                <p style="margin-top: 8px;">Bukan mahasiswa? <a href="login_dosen.php"><i class="fas fa-chalkboard-teacher"></i> Login Dosen</a> &bull; <a href="login_admin.php"><i class="fas fa-user-shield"></i> Login Admin</a></p>
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
