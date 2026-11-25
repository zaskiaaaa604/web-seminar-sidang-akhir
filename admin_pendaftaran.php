<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: admin_pendaftaran.php (Manajemen Data Pendaftaran Seminar & Sidang)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';
require_once 'notifikasi_helper.php';
require_once 'jadwal_bentrok_helper.php';

// Validasi Keamanan: Hanya admin yang berhak
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$nama     = $_SESSION['nama'];

$error_message = '';
$success_message = '';
$bentrok_error = null;

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah_sukses') $success_message = "Data pendaftaran baru berhasil disimpan!";
    elseif ($_GET['pesan'] === 'edit_sukses') $success_message = "Data pendaftaran berhasil diperbarui!";
    elseif ($_GET['pesan'] === 'hapus_sukses') $success_message = "Data pendaftaran berhasil dihapus!";
    elseif ($_GET['pesan'] === 'status_sukses') $success_message = "Status pendaftaran berhasil diperbarui!";
}

// 1. TAMBAH PENDAFTARAN BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $mahasiswa_id        = (int)($_POST['mahasiswa_id'] ?? 0);
    $jenis               = trim($_POST['jenis'] ?? 'Seminar Proposal');
    $kategori            = ($jenis === 'Seminar Proposal') ? 'seminar' : 'sidang';
    $judul               = trim($_POST['judul'] ?? '');
    $dosen_pembimbing_id = (int)($_POST['dosen_pembimbing_id'] ?? 0);
    $dosen_penguji_id    = (int)($_POST['dosen_penguji_id'] ?? 0);
    $ruangan_id          = !empty($_POST['ruangan_id']) ? (int)$_POST['ruangan_id'] : null;
    $tanggal             = !empty($_POST['tanggal']) ? $_POST['tanggal'] : null;
    $jam_mulai           = !empty($_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
    $jam_selesai         = !empty($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
    $status              = trim($_POST['status'] ?? 'Terjadwal');
    $catatan             = trim($_POST['catatan'] ?? '');

    if ($mahasiswa_id <= 0 || empty($judul) || $dosen_pembimbing_id <= 0 || $dosen_penguji_id <= 0) {
        $error_message = "Mahasiswa, judul, dosen pembimbing, dan dosen penguji wajib diisi!";
    } else {
        // Cek Bentrok jika tanggal dan jam diisi
        if (!empty($tanggal) && !empty($jam_mulai) && !empty($jam_selesai)) {
            mysqli_begin_transaction($conn);
            $data_cek = [
                'mahasiswa_id'        => $mahasiswa_id,
                'dosen_pembimbing_id' => $dosen_pembimbing_id,
                'dosen_penguji_id'    => $dosen_penguji_id,
                'ruangan_id'          => $ruangan_id,
                'tanggal'             => $tanggal,
                'jam_mulai'           => $jam_mulai,
                'jam_selesai'         => $jam_selesai,
                'exclude_id'          => 0
            ];
            $hasil_cek = cekBentrokJadwal($conn, $data_cek, true);
            if ($hasil_cek['bentrok']) {
                mysqli_rollback($conn);
                $bentrok_error = $hasil_cek;
            }
        }

        if (empty($bentrok_error)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO pendaftaran_seminar_sidang (mahasiswa_id, jenis, kategori, judul, dosen_pembimbing_id, dosen_penguji_id, ruangan_id, tanggal, jam_mulai, jam_selesai, status, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isssiiisssss", $mahasiswa_id, $jenis, $kategori, $judul, $dosen_pembimbing_id, $dosen_penguji_id, $ruangan_id, $tanggal, $jam_mulai, $jam_selesai, $status, $catatan);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                if (!empty($tanggal) && !empty($jam_mulai) && !empty($jam_selesai)) {
                    mysqli_commit($conn);
                }

                // NOTIFIKASI OTOMATIS: Beritahu Mahasiswa dan Dosen
                $q_info = mysqli_query($conn, "SELECT m.nama AS nama_mhs, r.nama_ruangan FROM mahasiswa m LEFT JOIN ruangan r ON r.id = " . ($ruangan_id ? $ruangan_id : 0) . " WHERE m.id = $mahasiswa_id LIMIT 1");
                $row_inf = mysqli_fetch_assoc($q_info);
                $nama_mhs = $row_inf['nama_mhs'] ?? 'Mahasiswa';
                $nama_ruangan = $row_inf['nama_ruangan'] ?? 'Ruangan Ditentukan Kemudian';

                kirimNotifikasi($conn, 'mahasiswa', $mahasiswa_id, "Status & Jadwal $jenis", "Data pendaftaran $jenis Anda telah diproses oleh admin dengan status: [$status]." . ($tanggal ? (" Jadwal: $tanggal pukul $jam_mulai - $jam_selesai WIB di $nama_ruangan.") : ''), 'dashboard_mahasiswa.php', 'jadwal');
                if ($dosen_pembimbing_id > 0) {
                    kirimNotifikasi($conn, 'dosen', $dosen_pembimbing_id, "Jadwal $jenis Mahasiswa Bimbingan", "Anda dijadwalkan sebagai Pembimbing $jenis untuk mahasiswa $nama_mhs." . ($tanggal ? (" Jadwal: $tanggal pukul $jam_mulai di $nama_ruangan.") : " Status: $status."), 'dashboard_dosen.php', 'jadwal');
                }
                if ($dosen_penguji_id > 0) {
                    kirimNotifikasi($conn, 'dosen', $dosen_penguji_id, "Jadwal $jenis Mahasiswa Ujian", "Anda dijadwalkan sebagai Penguji $jenis untuk mahasiswa $nama_mhs." . ($tanggal ? (" Jadwal: $tanggal pukul $jam_mulai di $nama_ruangan.") : " Status: $status."), 'dashboard_dosen.php', 'jadwal');
                }

                header("Location: admin_pendaftaran.php?pesan=tambah_sukses");
                exit;
            } else {
                if (!empty($tanggal) && !empty($jam_mulai) && !empty($jam_selesai)) {
                    mysqli_rollback($conn);
                }
                $error_message = "Gagal menyimpan pendaftaran: " . mysqli_error($conn);
            }
        }
    }
}

// 2. EDIT PENDAFTARAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id                  = (int)($_POST['id'] ?? 0);
    $mahasiswa_id        = (int)($_POST['mahasiswa_id'] ?? 0);
    $jenis               = trim($_POST['jenis'] ?? 'Seminar Proposal');
    $kategori            = ($jenis === 'Seminar Proposal') ? 'seminar' : 'sidang';
    $judul               = trim($_POST['judul'] ?? '');
    $dosen_pembimbing_id = (int)($_POST['dosen_pembimbing_id'] ?? 0);
    $dosen_penguji_id    = (int)($_POST['dosen_penguji_id'] ?? 0);
    $ruangan_id          = !empty($_POST['ruangan_id']) ? (int)$_POST['ruangan_id'] : null;
    $tanggal             = !empty($_POST['tanggal']) ? $_POST['tanggal'] : null;
    $jam_mulai           = !empty($_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
    $jam_selesai         = !empty($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
    $status              = trim($_POST['status'] ?? 'Terjadwal');
    $catatan             = trim($_POST['catatan'] ?? '');

    if ($id > 0 && $mahasiswa_id > 0 && !empty($judul)) {
        // Cek Bentrok jika tanggal dan jam diisi
        if (!empty($tanggal) && !empty($jam_mulai) && !empty($jam_selesai)) {
            mysqli_begin_transaction($conn);
            $data_cek = [
                'mahasiswa_id'        => $mahasiswa_id,
                'dosen_pembimbing_id' => $dosen_pembimbing_id,
                'dosen_penguji_id'    => $dosen_penguji_id,
                'ruangan_id'          => $ruangan_id,
                'tanggal'             => $tanggal,
                'jam_mulai'           => $jam_mulai,
                'jam_selesai'         => $jam_selesai,
                'exclude_id'          => $id
            ];
            $hasil_cek = cekBentrokJadwal($conn, $data_cek, true);
            if ($hasil_cek['bentrok']) {
                mysqli_rollback($conn);
                $bentrok_error = $hasil_cek;
            }
        }

        if (empty($bentrok_error)) {
            $stmt = mysqli_prepare($conn, "UPDATE pendaftaran_seminar_sidang SET mahasiswa_id=?, jenis=?, kategori=?, judul=?, dosen_pembimbing_id=?, dosen_penguji_id=?, ruangan_id=?, tanggal=?, jam_mulai=?, jam_selesai=?, status=?, catatan=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "isssiiisssssi", $mahasiswa_id, $jenis, $kategori, $judul, $dosen_pembimbing_id, $dosen_penguji_id, $ruangan_id, $tanggal, $jam_mulai, $jam_selesai, $status, $catatan, $id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                if (!empty($tanggal) && !empty($jam_mulai) && !empty($jam_selesai)) {
                    mysqli_commit($conn);
                }

                // NOTIFIKASI OTOMATIS: Beritahu Mahasiswa dan Dosen atas perubahan jadwal/verifikasi
                $q_info = mysqli_query($conn, "SELECT m.nama AS nama_mhs, r.nama_ruangan FROM mahasiswa m LEFT JOIN ruangan r ON r.id = " . ($ruangan_id ? $ruangan_id : 0) . " WHERE m.id = $mahasiswa_id LIMIT 1");
                $row_inf = mysqli_fetch_assoc($q_info);
                $nama_mhs = $row_inf['nama_mhs'] ?? 'Mahasiswa';
                $nama_ruangan = $row_inf['nama_ruangan'] ?? 'Ruangan Ditentukan Kemudian';

                kirimNotifikasi($conn, 'mahasiswa', $mahasiswa_id, "Pembaruan Status & Jadwal $jenis", "Administrator telah memperbarui status pendaftaran $jenis Anda menjadi: [$status]." . ($tanggal ? (" Jadwal: $tanggal pukul $jam_mulai - $jam_selesai WIB di $nama_ruangan.") : ''), 'dashboard_mahasiswa.php', 'verifikasi');
                if ($dosen_pembimbing_id > 0) {
                    kirimNotifikasi($conn, 'dosen', $dosen_pembimbing_id, "Pembaruan Jadwal $jenis", "Terdapat pembaruan data/jadwal $jenis untuk mahasiswa $nama_mhs." . ($tanggal ? (" Jadwal: $tanggal pukul $jam_mulai di $nama_ruangan.") : " Status: $status."), 'dashboard_dosen.php', 'jadwal');
                }
                if ($dosen_penguji_id > 0) {
                    kirimNotifikasi($conn, 'dosen', $dosen_penguji_id, "Pembaruan Jadwal $jenis", "Terdapat pembaruan data/jadwal $jenis untuk mahasiswa $nama_mhs." . ($tanggal ? (" Jadwal: $tanggal pukul $jam_mulai di $nama_ruangan.") : " Status: $status."), 'dashboard_dosen.php', 'jadwal');
                }

                header("Location: admin_pendaftaran.php?pesan=edit_sukses");
                exit;
            } else {
                if (!empty($tanggal) && !empty($jam_mulai) && !empty($jam_selesai)) {
                    mysqli_rollback($conn);
                }
                $error_message = "Gagal memperbarui: " . mysqli_error($conn);
            }
        }
    }
}

// 3. HAPUS PENDAFTARAN
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    mysqli_query($conn, "DELETE FROM pendaftaran_seminar_sidang WHERE id = $del_id");
    header("Location: admin_pendaftaran.php?pesan=hapus_sukses");
    exit;
}

// 4. FILTER & QUERY DATA
$filter_kategori = trim($_GET['kategori'] ?? '');
$filter_status   = trim($_GET['status'] ?? '');
$search          = trim($_GET['search'] ?? '');

$sql = "SELECT p.*, m.nama AS nama_mahasiswa, m.nim, 
               d1.nama_dosen AS nama_pembimbing, d1.gelar_akademik AS gelar_pembimbing,
               d2.nama_dosen AS nama_penguji, d2.gelar_akademik AS gelar_penguji,
               r.nama_ruangan, r.gedung
        FROM pendaftaran_seminar_sidang p
        JOIN mahasiswa m ON p.mahasiswa_id = m.id
        JOIN dosen d1 ON p.dosen_pembimbing_id = d1.id
        JOIN dosen d2 ON p.dosen_penguji_id = d2.id
        LEFT JOIN ruangan r ON p.ruangan_id = r.id
        WHERE 1=1";
$params = [];
$types  = "";

if (!empty($filter_kategori) && in_array($filter_kategori, ['seminar', 'sidang'])) {
    $sql .= " AND p.kategori = ?";
    $params[] = $filter_kategori;
    $types .= "s";
}

if (!empty($filter_status)) {
    $sql .= " AND p.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (m.nama LIKE ? OR m.nim LIKE ? OR p.judul LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}

$sql .= " ORDER BY p.id DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result_pendaftaran = mysqli_stmt_get_result($stmt);
} else {
    $result_pendaftaran = mysqli_query($conn, $sql);
}

// Master data untuk formulir modal
$opt_mhs   = mysqli_query($conn, "SELECT id, nim, nama FROM mahasiswa ORDER BY nama ASC");
$opt_dosen = mysqli_query($conn, "SELECT id, nidn, nama_dosen, gelar_akademik FROM dosen WHERE status = 'Aktif' ORDER BY nama_dosen ASC");
$opt_ruang = mysqli_query($conn, "SELECT id, kode_ruangan, nama_ruangan, gedung FROM ruangan ORDER BY kode_ruangan ASC");

$active_menu = 'pendaftaran';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pendaftaran Seminar & Sidang - Panel Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="admin-body">

    <?php include 'admin_sidebar.php'; ?>

    <div class="admin-main-wrapper">
        <header class="admin-topbar">
            <div class="admin-topbar-left">
                <button type="button" class="admin-mobile-toggle" onclick="toggleSidebar();"><i class="fas fa-bars"></i></button>
                <div class="admin-topbar-title">
                    <h1>Data Pendaftaran Seminar & Sidang</h1>
                    <span>Kelola seluruh usulan jadwal dan verifikasi pendaftaran mahasiswa</span>
                </div>
            </div>
            <div class="admin-topbar-right">
                <!-- Komponen Lonceng Notifikasi Admin -->
                <?php include 'komponen_notifikasi_lonceng.php'; ?>
                <button type="button" class="btn-admin-primary" onclick="bukaModalTambah();">
                    <i class="fas fa-plus"></i> Tambah Pendaftaran
                </button>
            </div>
        </header>

        <main class="admin-content-body">
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <i class="fas fa-circle-check"></i> <div><?= htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <i class="fas fa-triangle-exclamation"></i> <div><?= htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($bentrok_error)): ?>
                <?= renderAlertBentrok($bentrok_error); ?>
            <?php endif; ?>

            <!-- Panel Filter & Pencarian -->
            <div class="admin-card-panel" style="padding: 16px 20px; margin-bottom: 20px;">
                <form method="GET" action="admin_pendaftaran.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                    <div style="flex: 1; min-width: 220px;">
                        <input type="text" name="search" placeholder="Cari Nama / NIM / Judul..." value="<?= htmlspecialchars($search); ?>" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                    <div>
                        <select name="kategori" onchange="this.form.submit();" style="padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="">Semua Kategori</option>
                            <option value="seminar" <?= ($filter_kategori === 'seminar') ? 'selected' : ''; ?>>Seminar Proposal</option>
                            <option value="sidang" <?= ($filter_kategori === 'sidang') ? 'selected' : ''; ?>>Sidang Akhir Skripsi</option>
                        </select>
                    </div>
                    <div>
                        <select name="status" onchange="this.form.submit();" style="padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="">Semua Status</option>
                            <option value="Menunggu Verifikasi" <?= ($filter_status === 'Menunggu Verifikasi') ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                            <option value="Terjadwal" <?= ($filter_status === 'Terjadwal') ? 'selected' : ''; ?>>Terjadwal</option>
                            <option value="Sedang Berlangsung" <?= ($filter_status === 'Sedang Berlangsung') ? 'selected' : ''; ?>>Sedang Berlangsung</option>
                            <option value="Selesai" <?= ($filter_status === 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                            <option value="Ditolak" <?= ($filter_status === 'Ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                        </select>
                    </div>
                    <?php if (!empty($search) || !empty($filter_kategori) || !empty($filter_status)): ?>
                        <a href="admin_pendaftaran.php" class="btn-admin-outline" style="padding: 8px 12px; font-size: 0.85rem;"><i class="fas fa-rotate-left"></i> Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabel Data Pendaftaran -->
            <div class="admin-card-panel">
                <div class="admin-panel-header">
                    <h3><i class="fas fa-list-check" style="color: var(--admin-blue-primary);"></i> Daftar Seluruh Pendaftaran Seminar & Sidang</h3>
                    <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">
                        Total: <strong><?= $result_pendaftaran ? mysqli_num_rows($result_pendaftaran) : 0; ?></strong> Data
                    </span>
                </div>

                <div class="admin-table-responsive">
                    <table class="admin-data-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">No</th>
                                <th>Mahasiswa</th>
                                <th>Jenis & Judul Penelitian</th>
                                <th>Dosen Pembimbing & Penguji</th>
                                <th>Jadwal & Ruangan</th>
                                <th style="text-align: center; width: 130px;">Status</th>
                                <th style="text-align: center; width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_pendaftaran && mysqli_num_rows($result_pendaftaran) > 0): ?>
                                <?php $no = 1; while ($row = mysqli_fetch_assoc($result_pendaftaran)): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 700; color: #64748b;"><?= $no++; ?></td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--admin-navy);"><?= htmlspecialchars($row['nama_mahasiswa']); ?></div>
                                            <small style="color: var(--admin-text-muted); font-family: monospace;"><i class="fas fa-id-card"></i> <?= htmlspecialchars($row['nim']); ?></small>
                                        </td>
                                        <td>
                                            <span class="<?= ($row['kategori'] === 'seminar') ? 'badge-admin-blue' : 'badge-admin-orange'; ?>" style="margin-bottom: 4px;">
                                                <?= htmlspecialchars($row['jenis']); ?>
                                            </span>
                                            <div style="font-weight: 600; color: #1e293b; font-size: 0.86rem; margin-top: 4px; max-width: 300px;">
                                                <?= htmlspecialchars($row['judul']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.82rem; color: #334155;">
                                                <i class="fas fa-user-tie" style="color: var(--admin-blue-primary);"></i> <strong>Pembimbing:</strong> <?= htmlspecialchars($row['nama_pembimbing']); ?>
                                            </div>
                                            <div style="font-size: 0.82rem; color: #475569; margin-top: 2px;">
                                                <i class="fas fa-user-check" style="color: var(--admin-accent-orange);"></i> <strong>Penguji:</strong> <?= htmlspecialchars($row['nama_penguji']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.82rem; font-weight: 600; color: #1e293b;">
                                                <i class="fas fa-calendar-day" style="color: #64748b;"></i> <?= $row['tanggal'] ? date('d M Y', strtotime($row['tanggal'])) : '<span style="color:#94a3b8;">Belum diatur</span>'; ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #64748b;">
                                                <i class="fas fa-clock"></i> <?= $row['jam_mulai'] ? substr($row['jam_mulai'],0,5).' - '.substr($row['jam_selesai'],0,5).' WIB' : '-'; ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--admin-accent-orange); font-weight: 600;">
                                                <i class="fas fa-door-open"></i> <?= htmlspecialchars($row['nama_ruangan'] ?? 'Ruangan belum diatur'); ?>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php
                                            $st = $row['status'];
                                            $b_class = 'badge-admin-amber';
                                            if ($st === 'Terjadwal') $b_class = 'badge-admin-blue';
                                            elseif ($st === 'Sedang Berlangsung') $b_class = 'badge-admin-orange';
                                            elseif ($st === 'Selesai') $b_class = 'badge-admin-green';
                                            elseif ($st === 'Ditolak') $b_class = 'badge-admin-red';
                                            ?>
                                            <span class="<?= $b_class; ?>"><?= htmlspecialchars($st); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 4px; justify-content: center;">
                                                <button type="button" class="btn-admin-outline" style="padding: 4px 8px; font-size: 0.75rem;" onclick='bukaModalEdit(<?= json_encode($row); ?>);' title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="admin_pendaftaran.php?action=hapus&id=<?= $row['id']; ?>" class="btn-admin-outline" style="padding: 4px 8px; font-size: 0.75rem; color: #dc2626;" onclick="return confirm('Hapus pendaftaran mahasiswa ini?');" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">
                                        <i class="fas fa-folder-open" style="font-size: 2rem; display: block; margin-bottom: 8px;"></i>
                                        Tidak ada data pendaftaran yang sesuai dengan filter pencarian.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <footer class="admin-footer">
            &copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Program Studi Teknologi Rekayasa Perangkat Lunak, Politeknik Negeri Lhokseumawe.
        </footer>
    </div>

    <!-- Modal Form Tambah / Edit Pendaftaran -->
    <div id="modalFormPendaftaran" class="custom-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; padding: 20px; overflow-y: auto;">
        <div style="background: white; border-radius: var(--admin-radius); max-width: 650px; width: 100%; border: 1px solid #cbd5e1; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden;">
            <div style="background: linear-gradient(135deg, var(--admin-navy), #1e3a8a); color: white; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.15rem;" id="judulModalPendaftaran"><i class="fas fa-plus-circle"></i> Tambah Pendaftaran</h3>
                <button type="button" onclick="tutupModal();" style="background: transparent; border: none; color: white; font-size: 1.4rem; cursor: pointer;">&times;</button>
            </div>
            <form action="admin_pendaftaran.php" method="POST" style="padding: 24px;">
                <input type="hidden" name="action" id="form_action" value="tambah">
                <input type="hidden" name="id" id="form_id" value="">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                    <div>
                        <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Pilih Mahasiswa:</label>
                        <select name="mahasiswa_id" id="form_mhs" required style="width: 100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="">-- Pilih Mahasiswa --</option>
                            <?php mysqli_data_seek($opt_mhs, 0); while ($m = mysqli_fetch_assoc($opt_mhs)): ?>
                                <option value="<?= $m['id']; ?>"><?= htmlspecialchars($m['nama']); ?> (<?= htmlspecialchars($m['nim']); ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Jenis Ujian:</label>
                        <select name="jenis" id="form_jenis" required style="width: 100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="Seminar Proposal">Seminar Proposal</option>
                            <option value="Sidang Akhir Skripsi">Sidang Akhir Skripsi</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Judul Penelitian / Skripsi:</label>
                    <input type="text" name="judul" id="form_judul" required placeholder="Masukkan judul proposal / skripsi mahasiswa" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                    <div>
                        <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Dosen Pembimbing:</label>
                        <select name="dosen_pembimbing_id" id="form_pembimbing" required style="width: 100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="">-- Pilih Pembimbing --</option>
                            <?php mysqli_data_seek($opt_dosen, 0); while ($d = mysqli_fetch_assoc($opt_dosen)): ?>
                                <option value="<?= $d['id']; ?>"><?= htmlspecialchars($d['nama_dosen']); ?>, <?= htmlspecialchars($d['gelar_akademik']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Dosen Penguji:</label>
                        <select name="dosen_penguji_id" id="form_penguji" required style="width: 100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="">-- Pilih Penguji --</option>
                            <?php mysqli_data_seek($opt_dosen, 0); while ($d = mysqli_fetch_assoc($opt_dosen)): ?>
                                <option value="<?= $d['id']; ?>"><?= htmlspecialchars($d['nama_dosen']); ?>, <?= htmlspecialchars($d['gelar_akademik']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                    <div>
                        <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Ruangan:</label>
                        <select name="ruangan_id" id="form_ruangan" style="width: 100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="">-- Pilih Ruangan (Opsional) --</option>
                            <?php mysqli_data_seek($opt_ruang, 0); while ($r = mysqli_fetch_assoc($opt_ruang)): ?>
                                <option value="<?= $r['id']; ?>"><?= htmlspecialchars($r['kode_ruangan']); ?> - <?= htmlspecialchars($r['nama_ruangan']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Tanggal Pelaksanaan:</label>
                        <input type="date" name="tanggal" id="form_tanggal" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Jam Mulai:</label>
                        <input type="time" name="jam_mulai" id="form_jam_mulai" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                    <div>
                        <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Jam Selesai:</label>
                        <input type="time" name="jam_selesai" id="form_jam_selesai" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                    <div>
                        <label style="font-size: 0.84rem; font-weight: 700; color: var(--admin-navy); display: block; margin-bottom: 4px;">Status:</label>
                        <select name="status" id="form_status" required style="width: 100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                            <option value="Menunggu Verifikasi">Menunggu Verifikasi</option>
                            <option value="Terjadwal">Terjadwal</option>
                            <option value="Sedang Berlangsung">Sedang Berlangsung</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </div>
                </div>

                <!-- Wadah Peringatan Real-Time Jadwal Bentrok -->
                <div id="liveBentrokNotice" style="display: none; margin-bottom: 14px;"></div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                    <button type="button" class="btn-admin-outline" onclick="tutupModal();">Batal</button>
                    <button type="submit" class="btn-admin-primary"><i class="fas fa-save"></i> Simpan Pendaftaran</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('adminSidebar').classList.toggle('show'); }
        function bukaModalTambah() {
            document.getElementById('form_action').value = 'tambah';
            document.getElementById('form_id').value = '';
            document.getElementById('judulModalPendaftaran').innerHTML = '<i class="fas fa-plus-circle"></i> Tambah Pendaftaran Baru';
            const notice = document.getElementById('liveBentrokNotice');
            if (notice) notice.style.display = 'none';
            document.getElementById('modalFormPendaftaran').style.display = 'flex';
        }
        function bukaModalEdit(data) {
            document.getElementById('form_action').value = 'edit';
            document.getElementById('form_id').value = data.id;
            document.getElementById('form_mhs').value = data.mahasiswa_id;
            document.getElementById('form_jenis').value = data.jenis;
            document.getElementById('form_judul').value = data.judul;
            document.getElementById('form_pembimbing').value = data.dosen_pembimbing_id;
            document.getElementById('form_penguji').value = data.dosen_penguji_id;
            document.getElementById('form_ruangan').value = data.ruangan_id || '';
            document.getElementById('form_tanggal').value = data.tanggal || '';
            document.getElementById('form_jam_mulai').value = data.jam_mulai || '';
            document.getElementById('form_jam_selesai').value = data.jam_selesai || '';
            document.getElementById('form_status').value = data.status;
            document.getElementById('judulModalPendaftaran').innerHTML = '<i class="fas fa-edit"></i> Edit Pendaftaran Mahasiswa';
            const notice = document.getElementById('liveBentrokNotice');
            if (notice) notice.style.display = 'none';
            document.getElementById('modalFormPendaftaran').style.display = 'flex';
            checkLiveBentrokPendaftaran();
        }
        function tutupModal() {
            document.getElementById('modalFormPendaftaran').style.display = 'none';
        }
        window.onclick = function(e) {
            if (e.target === document.getElementById('modalFormPendaftaran')) tutupModal();
        };

        // Pemeriksaan Bentrok Real-Time via AJAX
        function checkLiveBentrokPendaftaran() {
            const mhs = document.getElementById('form_mhs').value;
            const dp = document.getElementById('form_pembimbing').value;
            const du = document.getElementById('form_penguji').value;
            const ruang = document.getElementById('form_ruangan').value;
            const tgl = document.getElementById('form_tanggal').value;
            const jm = document.getElementById('form_jam_mulai').value;
            const js = document.getElementById('form_jam_selesai').value;
            const exc = document.getElementById('form_id').value;
            const notice = document.getElementById('liveBentrokNotice');

            if (!notice) return;

            if (!tgl || !jm || !js) {
                notice.style.display = 'none';
                return;
            }

            if (jm >= js) {
                notice.style.display = 'block';
                notice.innerHTML = '<div style="background: #fff1f2; border: 1px solid #f43f5e; color: #9f1239; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;"><i class="fas fa-clock" style="color: #e11d48; margin-right: 6px;"></i>Jam selesai (' + js + ') harus lebih akhir dari jam mulai (' + jm + ')!</div>';
                return;
            }

            if (dp && du && dp === du) {
                notice.style.display = 'block';
                notice.innerHTML = '<div style="background: #fff1f2; border: 1px solid #f43f5e; color: #9f1239; padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;"><i class="fas fa-user-xmark" style="color: #e11d48; margin-right: 6px;"></i>Dosen Pembimbing dan Dosen Penguji tidak boleh orang yang sama!</div>';
                return;
            }

            const params = new URLSearchParams({
                mahasiswa_id: mhs || 0,
                dosen_pembimbing_id: dp || 0,
                dosen_penguji_id: du || 0,
                ruangan_id: ruang || 0,
                tanggal: tgl,
                jam_mulai: jm,
                jam_selesai: js,
                exclude_id: exc || 0
            });

            fetch('api_cek_bentrok.php?' + params.toString())
                .then(r => r.json())
                .then(res => {
                    if (res.bentrok) {
                        notice.style.display = 'block';
                        notice.innerHTML = '<div style="background: #fff1f2; border: 2px solid #f43f5e; color: #9f1239; padding: 12px 14px; border-radius: 8px; font-size: 0.86rem; font-weight: 700; line-height: 1.4;"><i class="fas fa-triangle-exclamation" style="color: #e11d48; margin-right: 6px; font-size: 1rem;"></i>' + res.pesan + '</div>';
                    } else {
                        notice.style.display = 'block';
                        notice.innerHTML = '<div style="background: #ecfdf5; border: 1px solid #10b981; color: #065f46; padding: 8px 12px; border-radius: 8px; font-size: 0.82rem; font-weight: 600;"><i class="fas fa-circle-check" style="color: #059669; margin-right: 6px;"></i>Jadwal aman! Tidak ada tabrakan waktu dosen, ruangan, maupun mahasiswa.</div>';
                    }
                })
                .catch(() => {
                    notice.style.display = 'none';
                });
        }

        ['form_mhs', 'form_pembimbing', 'form_penguji', 'form_ruangan', 'form_tanggal', 'form_jam_mulai', 'form_jam_selesai'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', checkLiveBentrokPendaftaran);
                el.addEventListener('input', checkLiveBentrokPendaftaran);
            }
        });
    </script>
    <!-- Skrip AJAX Notifikasi Otomatis Interval 10 Detik -->
    <script src="notifikasi.js"></script>
</body>
</html>
