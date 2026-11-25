<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE
 * PROGRAM STUDI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: register_mahasiswa.php (Pendaftaran Akun Mahasiswa Pertama Kali)
 * ===================================================================
 * Ketentuan:
 * 1. Mahasiswa mengisi sendiri tanggal lahirnya (tidak diisi otomatis oleh sistem).
 * 2. Password awal mahasiswa adalah tanggal lahir yang diisi sendiri (format: DD-MM-YYYY).
 * 3. Mahasiswa mengisi NIM, nama lengkap, tanggal lahir, dan konfirmasi password.
 * 4. Sistem memvalidasi NIM belum terdaftar (1 NIM hanya untuk 1 akun).
 * 5. Password dienkripsi dengan password_hash().
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

$error_message = '';
$nim_val = '';
$nama_val = '';
$tgl_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim                 = trim($_POST['nim'] ?? '');
    $nama                = trim($_POST['nama'] ?? '');
    $tanggal_lahir       = trim($_POST['tanggal_lahir'] ?? '');
    $konfirmasi_password = trim($_POST['konfirmasi_password'] ?? '');

    $nim_val  = htmlspecialchars($nim);
    $nama_val = htmlspecialchars($nama);
    // Tanggal lahir TIDAK diisi otomatis; jika gagal validasi, pertahankan inputan pengguna
    $tgl_val  = htmlspecialchars($tanggal_lahir);

    // 1. Validasi Input Tidak Boleh Kosong
    if (empty($nim) || empty($nama) || empty($tanggal_lahir) || empty($konfirmasi_password)) {
        $error_message = "Seluruh kolom formulir pendaftaran wajib diisi!";
    } else {
        // 2. Olah Tanggal Lahir menjadi Password Awal (Format Standar DD-MM-YYYY)
        $time = strtotime($tanggal_lahir);
        if (!$time) {
            $error_message = "Format tanggal lahir tidak valid. Silakan pilih tanggal yang benar!";
        } else {
            $pass_ddmmyyyy = date('d-m-Y', $time); // Format utama: DD-MM-YYYY (contoh: 16-05-2004)
            $pass_yyyymmdd = date('Y-m-d', $time); // Format HTML date: YYYY-MM-DD
            $pass_digits   = date('dmY', $time);   // Format angka: 16052004

            // 3. Validasi Konfirmasi Password (Harus cocok dengan tanggal lahir yang dipilih)
            if ($konfirmasi_password !== $pass_ddmmyyyy && $konfirmasi_password !== $pass_yyyymmdd && $konfirmasi_password !== $pass_digits) {
                $error_message = "Konfirmasi password tidak cocok dengan tanggal lahir yang Anda pilih. Password awal Anda adalah tanggal lahir dengan format <strong>DD-MM-YYYY</strong> (Contoh: $pass_ddmmyyyy).";
            } else {
                // 4. Validasi Keunikan NIM: Pastikan NIM belum pernah terdaftar
                $check_query = "SELECT id FROM mahasiswa WHERE nim = ? LIMIT 1";
                $check_stmt  = mysqli_prepare($conn, $check_query);

                if ($check_stmt) {
                    mysqli_stmt_bind_param($check_stmt, "s", $nim);
                    mysqli_stmt_execute($check_stmt);
                    $check_res = mysqli_stmt_get_result($check_stmt);

                    if (mysqli_num_rows($check_res) > 0) {
                        // NIM sudah terdaftar! Tolak pendaftaran ganda
                        $error_message = "NIM <strong>$nim_val</strong> sudah terdaftar dalam sistem! Satu NIM tidak bisa digunakan untuk membuat akun lebih dari satu kali. Silakan langsung login atau gunakan fitur Lupa Password.";
                    } else {
                        // 5. Enkripsi Password Awal (Tanggal Lahir DD-MM-YYYY) dengan password_hash()
                        $password_to_hash = $pass_ddmmyyyy;
                        $password_hash    = password_hash($password_to_hash, PASSWORD_DEFAULT);
                        $prodi_default    = 'Teknologi Rekayasa Perangkat Lunak';

                        // 6. Simpan Akun Mahasiswa Baru ke Database MySQL
                        $insert_query = "INSERT INTO mahasiswa (nim, nama, tanggal_lahir, password, is_password_changed, program_studi) VALUES (?, ?, ?, ?, 1, ?)";
                        $insert_stmt  = mysqli_prepare($conn, $insert_query);

                        if ($insert_stmt) {
                            mysqli_stmt_bind_param($insert_stmt, "sssss", $nim, $nama, $pass_yyyymmdd, $password_hash, $prodi_default);
                            
                            if (mysqli_stmt_execute($insert_stmt)) {
                                mysqli_stmt_close($insert_stmt);
                                mysqli_stmt_close($check_stmt);

                                // Pendaftaran Sukses: Alihkan ke halaman login
                                header("Location: login_mahasiswa.php?pesan=daftar_berhasil&nim=" . urlencode($nim));
                                exit;
                            } else {
                                $error_message = "Gagal menyimpan akun ke database. Silakan coba lagi!";
                            }
                            mysqli_stmt_close($insert_stmt);
                        } else {
                            $error_message = "Terjadi gangguan pada sistem pendaftaran database.";
                        }
                    }
                    mysqli_stmt_close($check_stmt);
                } else {
                    $error_message = "Terjadi gangguan koneksi database.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Akun Mahasiswa - SIM-SEMSID</title>
    
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
                    <i class="fas fa-arrow-left"></i> Sudah Punya Akun? Login
                </a>
            </div>
        </div>
    </header>

    <!-- Kartu Pendaftaran Akun Mahasiswa -->
    <main class="auth-wrapper">
        <div class="auth-card" style="max-width: 500px;">
            <div class="auth-header">
                <div class="auth-icon-circle" style="background: linear-gradient(135deg, var(--primary), #fb923c);">
                    <i class="fas fa-user-plus"></i>
                </div>
                <span class="auth-badge mahasiswa">Jurusan Teknologi Informasi dan Komputer (TIK)</span>
                <h2>Pendaftaran Akun Mahasiswa</h2>
                <p>Silakan isi data Anda secara mandiri untuk membuat akun portal seminar & sidang.</p>
            </div>

            <!-- Pesan Error -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.2rem; margin-top: 2px;"></i>
                    <div><?= $error_message; ?></div>
                </div>
            <?php endif; ?>

            <!-- Formulir Pendaftaran -->
            <form action="register_mahasiswa.php" method="POST" class="auth-form">
                <!-- 1. NIM -->
                <div class="form-field">
                    <label for="nim"><i class="fas fa-id-badge"></i> NIM (Nomor Induk Mahasiswa):</label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" id="nim" name="nim" placeholder="Contoh: 2026583050016" value="<?= $nim_val; ?>" required autofocus>
                    </div>
                    <small style="color: #64748b; font-size: 0.78rem;">*Satu NIM hanya bisa digunakan membuat 1 akun.</small>
                </div>

                <!-- 2. Nama Lengkap -->
                <div class="form-field">
                    <label for="nama"><i class="fas fa-user"></i> Nama Lengkap Mahasiswa:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user-tag"></i>
                        <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap sesuai KTM" value="<?= $nama_val; ?>" required>
                    </div>
                </div>

                <!-- 3. Tanggal Lahir (Diisi sendiri oleh mahasiswa, TIDAK diisi otomatis oleh sistem) -->
                <div class="form-field">
                    <label for="tanggal_lahir"><i class="fas fa-calendar-day"></i> Tanggal Lahir (Password Awal Anda):</label>
                    <div class="input-with-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= $tgl_val; ?>" required onchange="updatePasswordHint(this.value)">
                    </div>
                    <small id="tgl_hint" style="color: #ea580c; font-size: 0.78rem; font-weight: 600; margin-top: 2px;">
                        *Tanggal lahir yang Anda pilih di atas otomatis menjadi password awal Anda (Format: DD-MM-YYYY).
                    </small>
                </div>

                <!-- 4. Konfirmasi Password -->
                <div class="form-field">
                    <label for="konfirmasi_password"><i class="fas fa-lock"></i> Konfirmasi Password (Ketik Ulang Tanggal Lahir):</label>
                    <div class="input-with-icon">
                        <i class="fas fa-key"></i>
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="has-toggle" placeholder="Ketik tanggal lahir Anda (Format: DD-MM-YYYY)" required>
                        <button type="button" class="btn-toggle-password" onclick="togglePassword('konfirmasi_password', this)" title="Tampilkan password">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                    <small style="color: #64748b; font-size: 0.78rem;">
                        *Ketik ulang tanggal lahir Anda (Contoh: <code>28-08-2008</code>) untuk konfirmasi.
                    </small>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem; margin-top: 6px;">
                    <i class="fas fa-user-check"></i> Daftarkan Akun Saya
                </button>
            </form>

            <div class="auth-footer-links">
                <p>Sudah pernah membuat akun? <a href="login_mahasiswa.php"><i class="fas fa-sign-in-alt"></i> Langsung Login di Sini</a></p>
                <p style="margin-top: 6px;">Lupa password? <a href="lupa_password.php"><i class="fas fa-circle-question"></i> Pemulihan Akun</a></p>
            </div>
        </div>
    </main>

    <!-- Footer Ringkas -->
    <footer style="text-align: center; padding: 16px; font-size: 0.82rem; color: #64748b; background: white; border-top: 1px solid var(--border-color);">
        &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Jurusan Teknologi Informasi dan Komputer (TIK), Politeknik Negeri Lhokseumawe.
    </footer>

    <!-- Script Pendukung Format Tanggal Lahir & Toggle Password -->
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

        function updatePasswordHint(val) {
            const hint = document.getElementById('tgl_hint');
            if (val) {
                const parts = val.split('-');
                if (parts.length === 3) {
                    const formatted = parts[2] + '-' + parts[1] + '-' + parts[0];
                    hint.innerHTML = '<i class="fas fa-check-circle"></i> Password awal Anda akan diset ke: <strong>' + formatted + '</strong>. Masukkan format ini pada Konfirmasi Password.';
                    hint.style.color = '#15803d';
                }
            }
        }
    </script>

</body>
</html>
