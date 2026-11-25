<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * FILE: login_admin.php (Halaman Login Admin)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Jika sudah login, alihkan ke Dashboard sesuai role masing-masing
if (isset($_SESSION['login']) && $_SESSION['login'] === true && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: dashboard_admin.php");
        exit;
    } elseif ($_SESSION['role'] === 'mahasiswa') {
        header("Location: dashboard_mahasiswa.php");
        exit;
    } elseif ($_SESSION['role'] === 'dosen') {
        header("Location: dashboard_dosen.php");
        exit;
    }
}

$error_message = '';
$username_value = '';

// Memproses data formulir saat tombol login ditekan (metode POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $username_value = htmlspecialchars($username);

    // 1. Validasi Input Tidak Boleh Kosong
    if (empty($username) || empty($password)) {
        $error_message = "Username dan Password admin wajib diisi!";
    } else {
        // 2. Ambil data admin dari MySQL berdasarkan Username menggunakan Prepared Statement
        $query = "SELECT id, username, password, nama_lengkap FROM admin WHERE username = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                // 3. Verifikasi Password yang dienkripsi menggunakan password_verify()
                if (password_verify($password, $row['password'])) {
                    // Password cocok! Buat Session Login Admin
                    $_SESSION['login'] = true;
                    $_SESSION['role'] = 'admin';
                    $_SESSION['admin_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['nama'] = $row['nama_lengkap'];

                    // Arahkan ke Dashboard Admin
                    header("Location: dashboard_admin.php");
                    exit;
                } else {
                    $error_message = "Password admin salah. Silakan coba lagi!";
                }
            } else {
                $error_message = "Username admin tidak ditemukan dalam sistem!";
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
    <title>Login Administrator - SIM-SEMSID Politeknik Negeri Lhokseumawe</title>
    
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

    <!-- Kartu Login Admin -->
    <main class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-icon-circle" style="background: linear-gradient(135deg, var(--primary), #fb923c);">
                    <i class="fas fa-user-shield"></i>
                </div>
                <span class="auth-badge" style="background-color: var(--primary-light); color: var(--primary); border: 1px solid var(--primary-border);">
                    <i class="fas fa-shield-halved"></i> Administrator Jurusan TIK
                </span>
                <h2>Login Administrator</h2>
                <p>Portal khusus pengelola seminar & sidang Jurusan Teknologi Informasi dan Komputer (TIK).</p>
            </div>

            <!-- Pesan Notifikasi Akses Terbatas untuk Pengunjung -->
            <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'auth_required'): ?>
                <?php
                    $menu_req = $_GET['menu'] ?? 'data';
                    $menu_labels = [
                        'pendaftaran' => 'Data Pendaftaran',
                        'dosen'       => 'Data Dosen',
                        'mahasiswa'   => 'Data Mahasiswa',
                        'judul'       => 'Pengajuan Judul',
                        'kuota'       => 'Kuota Bimbingan Dosen'
                    ];
                    $label_fitur = $menu_labels[$menu_req] ?? 'Fitur Pengelolaan Data';
                ?>
                <div class="alert" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; margin-bottom: 20px; border-radius: 8px; padding: 12px 16px; display: flex; align-items: flex-start; gap: 10px; font-size: 0.88rem; line-height: 1.5;">
                    <i class="fas fa-shield-halved" style="font-size: 1.25rem; color: #2563eb; margin-top: 2px;"></i>
                    <div>
                        <strong>Akses Memerlukan Login Admin:</strong><br>
                        Menu <strong><?= htmlspecialchars($label_fitur); ?></strong> dilindungi hak akses administrator. Silakan login terlebih dahulu untuk mengakses atau mengelola data.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pesan Notifikasi Error -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.1rem; margin-top: 2px;"></i>
                    <div><?= htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Formulir Login -->
            <form action="login_admin.php" method="POST" class="auth-form">
                <div class="form-field">
                    <label for="username"><i class="fas fa-user"></i> Username Admin:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user-circle"></i>
                        <input type="text" id="username" name="username" placeholder="Masukkan username admin" value="<?= $username_value; ?>" required autofocus>
                    </div>
                </div>

                <div class="form-field">
                    <label for="password"><i class="fas fa-lock"></i> Password:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-key"></i>
                        <input type="password" id="password" name="password" class="has-toggle" placeholder="Masukkan password admin" required>
                        <button type="button" class="btn-toggle-password" onclick="togglePassword('password', this)" title="Tampilkan password">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; margin-top: 6px;">
                    <i class="fas fa-shield-halved"></i> Masuk Administrator
                </button>
            </form>

            <!-- Petunjuk Akun Demonstrasi untuk Pemula -->
            <div class="demo-account-hint" style="background-color: var(--primary-light); border-color: var(--primary-border); color: #9a3412;">
                <strong><i class="fas fa-shield-alt"></i> Akun Administrator Demo:</strong><br>
                Username: <code>admin</code><br>
                Password: <code>admin123</code>
            </div>

            <div class="auth-footer-links">
                <p>Bukan admin pengelola? <a href="login_mahasiswa.php"><i class="fas fa-user-graduate"></i> Login Mahasiswa</a> &bull; <a href="login_dosen.php"><i class="fas fa-chalkboard-teacher"></i> Login Dosen</a></p>
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
