<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: admin_mahasiswa.php (Manajemen Data Mahasiswa TIK)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';

// Validasi Keamanan Hak Akses Admin
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$nama     = $_SESSION['nama'];

$error_message = '';
$success_message = '';

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah_sukses') $success_message = "Data mahasiswa baru berhasil ditambahkan!";
    elseif ($_GET['pesan'] === 'edit_sukses') $success_message = "Data mahasiswa berhasil diperbarui!";
    elseif ($_GET['pesan'] === 'hapus_sukses') $success_message = "Data mahasiswa berhasil dihapus!";
    elseif ($_GET['pesan'] === 'reset_sukses') $success_message = "Password mahasiswa berhasil direset ke tanggal lahir (Format DD-MM-YYYY)!";
    elseif ($_GET['pesan'] === 'nim_ada') $error_message = "NIM tersebut sudah terdaftar di sistem!";
    elseif ($_GET['pesan'] === 'gagal') $error_message = "Terjadi kesalahan saat memproses data mahasiswa.";
}

// 1. TAMBAH MAHASISWA BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $nim           = trim($_POST['nim'] ?? '');
    $nama_mhs      = trim($_POST['nama'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $program_studi = trim($_POST['program_studi'] ?? 'Teknologi Rekayasa Perangkat Lunak');
    $email         = trim($_POST['email'] ?? '');
    $no_hp         = trim($_POST['no_hp'] ?? '');

    if (empty($nim) || empty($nama_mhs) || empty($tanggal_lahir)) {
        $error_message = "NIM, nama lengkap, dan tanggal lahir wajib diisi!";
    } else {
        // Cek apakah NIM sudah ada
        $cek_stmt = mysqli_prepare($conn, "SELECT id FROM mahasiswa WHERE nim = ? LIMIT 1");
        mysqli_stmt_bind_param($cek_stmt, "s", $nim);
        mysqli_stmt_execute($cek_stmt);
        mysqli_stmt_store_result($cek_stmt);
        if (mysqli_stmt_num_rows($cek_stmt) > 0) {
            mysqli_stmt_close($cek_stmt);
            header("Location: admin_mahasiswa.php?pesan=nim_ada");
            exit;
        }
        mysqli_stmt_close($cek_stmt);

        // Password awal adalah DD-MM-YYYY dari tanggal lahir
        $pass_awal = date('d-m-Y', strtotime($tanggal_lahir));
        $pass_hash = password_hash($pass_awal, PASSWORD_DEFAULT);
        $is_changed = 0;

        $stmt = mysqli_prepare($conn, "INSERT INTO mahasiswa (nim, nama, tanggal_lahir, password, is_password_changed, program_studi, email, no_hp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssssisss", $nim, $nama_mhs, $tanggal_lahir, $pass_hash, $is_changed, $program_studi, $email, $no_hp);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_mahasiswa.php?pesan=tambah_sukses");
            exit;
        } else {
            $error_message = "Gagal menyimpan mahasiswa: " . mysqli_error($conn);
        }
    }
}

// 2. EDIT MAHASISWA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id            = (int)($_POST['id'] ?? 0);
    $nim           = trim($_POST['nim'] ?? '');
    $nama_mhs      = trim($_POST['nama'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $program_studi = trim($_POST['program_studi'] ?? 'Teknologi Rekayasa Perangkat Lunak');
    $email         = trim($_POST['email'] ?? '');
    $no_hp         = trim($_POST['no_hp'] ?? '');

    if ($id <= 0 || empty($nim) || empty($nama_mhs) || empty($tanggal_lahir)) {
        $error_message = "NIM, nama lengkap, dan tanggal lahir wajib diisi!";
    } else {
        // Cek duplikasi NIM untuk mahasiswa lain
        $cek_stmt = mysqli_prepare($conn, "SELECT id FROM mahasiswa WHERE nim = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($cek_stmt, "si", $nim, $id);
        mysqli_stmt_execute($cek_stmt);
        mysqli_stmt_store_result($cek_stmt);
        if (mysqli_stmt_num_rows($cek_stmt) > 0) {
            mysqli_stmt_close($cek_stmt);
            header("Location: admin_mahasiswa.php?pesan=nim_ada");
            exit;
        }
        mysqli_stmt_close($cek_stmt);

        $stmt = mysqli_prepare($conn, "UPDATE mahasiswa SET nim=?, nama=?, tanggal_lahir=?, program_studi=?, email=?, no_hp=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssssssi", $nim, $nama_mhs, $tanggal_lahir, $program_studi, $email, $no_hp, $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_mahasiswa.php?pesan=edit_sukses");
            exit;
        } else {
            $error_message = "Gagal memperbarui data: " . mysqli_error($conn);
        }
    }
}

// 3. RESET PASSWORD MAHASISWA (Kembali ke Tanggal Lahir DD-MM-YYYY)
if (isset($_GET['action']) && $_GET['action'] === 'reset_pass' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $q_mhs = mysqli_query($conn, "SELECT tanggal_lahir FROM mahasiswa WHERE id = $id LIMIT 1");
        if ($mhs_row = mysqli_fetch_assoc($q_mhs)) {
            $default_pass = date('d-m-Y', strtotime($mhs_row['tanggal_lahir']));
            $hashed = password_hash($default_pass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE mahasiswa SET password = ?, is_password_changed = 0 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $hashed, $id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: admin_mahasiswa.php?pesan=reset_sukses");
                exit;
            }
        }
    }
    header("Location: admin_mahasiswa.php?pesan=gagal");
    exit;
}

// 4. HAPUS MAHASISWA
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM mahasiswa WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: admin_mahasiswa.php?pesan=hapus_sukses");
            exit;
        }
    }
    header("Location: admin_mahasiswa.php?pesan=gagal");
    exit;
}

// Filter & Pencarian
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM mahasiswa WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (nim LIKE ? OR nama LIKE ? OR email LIKE ? OR no_hp LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "ssss";
}

if ($filter_status === 'changed') {
    $sql .= " AND is_password_changed = 1";
} elseif ($filter_status === 'default') {
    $sql .= " AND is_password_changed = 0";
}

$sql .= " ORDER BY nim ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result_mhs = mysqli_stmt_get_result($stmt);
} else {
    $result_mhs = mysqli_query($conn, $sql);
}

// Statistik Mahasiswa
$total_mhs = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM mahasiswa"));
$pass_changed = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM mahasiswa WHERE is_password_changed = 1"));
$pass_default = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM mahasiswa WHERE is_password_changed = 0"));

$active_menu = 'mahasiswa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Mahasiswa - Panel Admin SIM-SEMSID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>

<div class="admin-app">
    <!-- Sidebar Navy -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Topbar -->
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button class="admin-sidebar-toggle" id="sidebarToggle" title="Buka/Tutup Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="admin-breadcrumb">
                    <a href="dashboard_admin.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
                    <span>/</span>
                    <span class="current">Data Mahasiswa</span>
                </div>
            </div>
            <div class="admin-topbar-right">
                <!-- Komponen Lonceng Notifikasi Admin -->
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <div class="topbar-date-pill">
                    <i class="fas fa-calendar-day"></i> <?= date('d M Y'); ?>
                </div>
                <div class="admin-badge-role">
                    <i class="fas fa-shield-halved"></i> Panel Administrator
                </div>
            </div>
        </header>

        <!-- Content Body -->
        <main class="admin-content-body">
            <!-- Welcome / Page Header -->
            <div class="welcome-admin-banner">
                <div class="welcome-admin-info">
                    <h1><i class="fas fa-user-graduate" style="color: #60a5fa; margin-right: 8px;"></i> Data Mahasiswa Program Studi TIK</h1>
                    <p>Kelola data mahasiswa, NIM, identitas tanggal lahir untuk verifikasi login awal, kontak, serta reset kata sandi mahasiswa Politeknik Negeri Lhokseumawe.</p>
                </div>
                <div>
                    <button class="btn-admin btn-admin-primary" onclick="bukaModalTambah()">
                        <i class="fas fa-user-plus"></i> Tambah Mahasiswa
                    </button>
                </div>
            </div>

            <!-- Feedback Alerts -->
            <?php if (!empty($success_message)): ?>
                <div class="admin-alert admin-alert-success">
                    <i class="fas fa-circle-check"></i>
                    <div><?= htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="admin-alert admin-alert-danger">
                    <i class="fas fa-triangle-exclamation"></i>
                    <div><?= htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <!-- Mini Summary Cards -->
            <div class="admin-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Total Mahasiswa</div>
                        <div class="stat-card-icon icon-blue"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $total_mhs; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-id-card"></i> Terdaftar di prodi TIK</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Password Sudah Diganti</div>
                        <div class="stat-card-icon icon-green"><i class="fas fa-user-shield"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $pass_changed; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-check"></i> Sudah mengamankan akun</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">Password Default</div>
                        <div class="stat-card-icon icon-orange"><i class="fas fa-key"></i></div>
                    </div>
                    <div class="stat-card-value"><?= $pass_default; ?></div>
                    <div class="stat-card-desc"><i class="fas fa-clock"></i> Menggunakan tanggal lahir (DD-MM-YYYY)</div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="admin-table-card">
                <div class="admin-table-header">
                    <div>
                        <h2 class="admin-table-title"><i class="fas fa-table-list" style="color: var(--primary);"></i> Daftar Mahasiswa Terdaftar</h2>
                        <p style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">Data mahasiswa yang berhak mengajukan judul, seminar proposal, dan sidang tugas akhir</p>
                    </div>

                    <!-- Toolbar Filter -->
                    <form method="GET" action="admin_mahasiswa.php" class="admin-toolbar-filter">
                        <div class="admin-search-input">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" name="search" placeholder="Cari NIM, Nama, Email..." value="<?= htmlspecialchars($search); ?>">
                        </div>
                        <select name="status" class="admin-filter-select" onchange="this.form.submit()">
                            <option value="">Semua Status Akun</option>
                            <option value="changed" <?= ($filter_status === 'changed') ? 'selected' : ''; ?>>Password Baru</option>
                            <option value="default" <?= ($filter_status === 'default') ? 'selected' : ''; ?>>Password Default (Tgl Lahir)</option>
                        </select>
                        <button type="submit" class="btn-admin btn-admin-secondary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($search) || !empty($filter_status)): ?>
                            <a href="admin_mahasiswa.php" class="btn-admin btn-admin-secondary" title="Reset Filter"><i class="fas fa-rotate-left"></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>NIM</th>
                                <th>Nama Lengkap</th>
                                <th>Program Studi</th>
                                <th>Tanggal Lahir (Verifikasi)</th>
                                <th>Kontak</th>
                                <th>Status Keamanan Akun</th>
                                <th style="text-align: center; width: 170px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result_mhs) > 0): ?>
                                <?php $no = 1; while ($row = mysqli_fetch_assoc($result_mhs)): ?>
                                    <tr>
                                        <td><strong><?= $no++; ?></strong></td>
                                        <td><span class="badge-role badge-role-admin" style="font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars($row['nim']); ?></span></td>
                                        <td>
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;"><?= htmlspecialchars($row['nama']); ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;">Terdaftar: <?= date('d M Y', strtotime($row['created_at'])); ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($row['program_studi']); ?></td>
                                        <td>
                                            <span style="font-family: monospace; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; color: #334155;">
                                                <i class="fas fa-cake-candles" style="color: #ea580c; margin-right: 4px;"></i>
                                                <?= date('d-m-Y', strtotime($row['tanggal_lahir'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.8rem; color: #334155;"><i class="fas fa-envelope" style="color: #3b82f6; width: 14px;"></i> <?= htmlspecialchars($row['email'] ?: '-'); ?></div>
                                            <div style="font-size: 0.8rem; color: #334155;"><i class="fas fa-phone" style="color: #10b981; width: 14px;"></i> <?= htmlspecialchars($row['no_hp'] ?: '-'); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($row['is_password_changed'] == 1): ?>
                                                <span class="badge-status badge-selesai"><i class="fas fa-check-circle"></i> Password Baru</span>
                                            <?php else: ?>
                                                <span class="badge-status badge-menunggu"><i class="fas fa-clock"></i> Password Awal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center;">
                                                <button class="btn-action btn-action-edit" title="Edit Data Mahasiswa" onclick='bukaModalEdit(<?= json_encode($row); ?>)'>
                                                    <i class="fas fa-pen-to-square"></i>
                                                </button>
                                                <a href="admin_mahasiswa.php?action=reset_pass&id=<?= $row['id']; ?>" class="btn-action btn-action-detail" title="Reset Password ke Tanggal Lahir (DD-MM-YYYY)" onclick="return confirm('Apakah Anda yakin ingin mereset password mahasiswa <?= htmlspecialchars(addslashes($row['nama'])); ?> ke tanggal lahirnya (format DD-MM-YYYY)?');">
                                                    <i class="fas fa-key"></i>
                                                </a>
                                                <a href="admin_mahasiswa.php?action=hapus&id=<?= $row['id']; ?>" class="btn-action btn-action-delete" title="Hapus Mahasiswa" onclick="return confirm('Apakah Anda yakin ingin menghapus data mahasiswa <?= htmlspecialchars(addslashes($row['nama'])); ?>? Tindakan ini tidak dapat dibatalkan.');">
                                                    <i class="fas fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-user-graduate" style="font-size: 3rem; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                        Belum ada data mahasiswa yang cocok dengan kriteria pencarian.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- MODAL TAMBAH MAHASISWA -->
<div class="admin-modal" id="modalTambah">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><i class="fas fa-user-plus" style="color: var(--primary);"></i> Tambah Mahasiswa Baru</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalTambah')">&times;</button>
        </div>
        <form method="POST" action="admin_mahasiswa.php">
            <input type="hidden" name="action" value="tambah">
            <div class="admin-modal-body">
                <div class="form-admin-group">
                    <label class="form-admin-label">NIM (Nomor Induk Mahasiswa) *</label>
                    <input type="text" name="nim" class="form-admin-control" placeholder="Contoh: 2026583050016" required>
                    <small style="color: #64748b; font-size: 0.75rem;">NIM digunakan sebagai username login utama mahasiswa.</small>
                </div>

                <div class="form-admin-group">
                    <label class="form-admin-label">Nama Lengkap Mahasiswa *</label>
                    <input type="text" name="nama" class="form-admin-control" placeholder="Nama lengkap sesuai KTM" required>
                </div>

                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">Tanggal Lahir (Verifikasi & Password Awal) *</label>
                        <input type="date" name="tanggal_lahir" class="form-admin-control" required>
                        <small style="color: #ea580c; font-size: 0.75rem; font-weight: 600;">Password awal otomatis: DD-MM-YYYY</small>
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">Program Studi</label>
                        <input type="text" name="program_studi" class="form-admin-control" value="Teknologi Rekayasa Perangkat Lunak" readonly style="background: #f1f5f9;">
                    </div>
                </div>

                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">Email Mahasiswa</label>
                        <input type="email" name="email" class="form-admin-control" placeholder="nama@pnl.ac.id">
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">No. WhatsApp / HP</label>
                        <input type="text" name="no_hp" class="form-admin-control" placeholder="08xxxxxxxxxx">
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalTambah')">Batal</button>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="fas fa-save"></i> Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT MAHASISWA -->
<div class="admin-modal" id="modalEdit">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><i class="fas fa-user-pen" style="color: #2563eb;"></i> Edit Data Mahasiswa</h3>
            <button class="btn-admin-close" onclick="tutupModal('modalEdit')">&times;</button>
        </div>
        <form method="POST" action="admin_mahasiswa.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="admin-modal-body">
                <div class="form-admin-group">
                    <label class="form-admin-label">NIM (Nomor Induk Mahasiswa) *</label>
                    <input type="text" name="nim" id="edit_nim" class="form-admin-control" required>
                </div>

                <div class="form-admin-group">
                    <label class="form-admin-label">Nama Lengkap Mahasiswa *</label>
                    <input type="text" name="nama" id="edit_nama" class="form-admin-control" required>
                </div>

                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">Tanggal Lahir *</label>
                        <input type="date" name="tanggal_lahir" id="edit_tanggal_lahir" class="form-admin-control" required>
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">Program Studi</label>
                        <input type="text" name="program_studi" id="edit_program_studi" class="form-admin-control" readonly style="background: #f1f5f9;">
                    </div>
                </div>

                <div class="form-admin-row">
                    <div class="form-admin-group">
                        <label class="form-admin-label">Email Mahasiswa</label>
                        <input type="email" name="email" id="edit_email" class="form-admin-control">
                    </div>
                    <div class="form-admin-group">
                        <label class="form-admin-label">No. WhatsApp / HP</label>
                        <input type="text" name="no_hp" id="edit_no_hp" class="form-admin-control">
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-admin btn-admin-secondary" onclick="tutupModal('modalEdit')">Batal</button>
                <button type="submit" class="btn-admin btn-admin-primary"><i class="fas fa-save"></i> Perbarui Data</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Responsive Sidebar Toggle
    const sidebar = document.getElementById('adminSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }

    function bukaModalTambah() {
        document.getElementById('modalTambah').classList.add('active');
    }

    function bukaModalEdit(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nim').value = data.nim;
        document.getElementById('edit_nama').value = data.nama;
        document.getElementById('edit_tanggal_lahir').value = data.tanggal_lahir;
        document.getElementById('edit_program_studi').value = data.program_studi || 'Teknologi Rekayasa Perangkat Lunak';
        document.getElementById('edit_email').value = data.email || '';
        document.getElementById('edit_no_hp').value = data.no_hp || '';
        document.getElementById('modalEdit').classList.add('active');
    }

    function tutupModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('admin-modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

<!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
<script src="notifikasi.js"></script>
</body>
</html>
