<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TEKNOLOGI REKAYASA PERANGKAT LUNAK
 * FILE: kalender_akademik.php (Halaman Kalender Akademik Publik)
 * ===================================================================
 */

session_start();
require_once 'koneksi.php';
require_once 'kalender_helper.php';

$is_logged_in = isset($_SESSION['login']) && $_SESSION['login'] === true;
$user_role    = $_SESSION['role'] ?? '';
$user_name    = $_SESSION['nama'] ?? '';

// Penentuan Bulan & Tahun yang ditampilkan
// Default: ambil dari URL, jika tidak ada gunakan November 2027 (karena data skripsi/jadwal aktif ada di bulan ini), atau bulan berjalan
$bulan_pilihan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : 11;
$tahun_pilihan = isset($_GET['tahun']) ? (int)$_GET['tahun'] : 2027;

if ($bulan_pilihan < 1 || $bulan_pilihan > 12) $bulan_pilihan = 11;
if ($tahun_pilihan < 2020 || $tahun_pilihan > 2035) $tahun_pilihan = 2027;

// Hitung bulan sebelumnya dan bulan berikutnya
$prev_time = strtotime("$tahun_pilihan-$bulan_pilihan-01 -1 month");
$prev_bulan = (int)date('n', $prev_time);
$prev_tahun = (int)date('Y', $prev_time);

$next_time = strtotime("$tahun_pilihan-$bulan_pilihan-01 +1 month");
$next_bulan = (int)date('n', $next_time);
$next_tahun = (int)date('Y', $next_time);

// Ambil semua kegiatan kalender dan jadwal resmi yang sudah disahkan
$semua_kegiatan = ambilSemuaKegiatanKalender($conn, $bulan_pilihan, $tahun_pilihan, true);

// Petakan kegiatan berdasarkan tanggal (Y-m-d)
$events_by_date = [];
$kategori_counts = [];
foreach ($semua_kegiatan as $event) {
    $kat = $event['kategori'];
    $kategori_counts[$kat] = ($kategori_counts[$kat] ?? 0) + 1;

    $mulai = new DateTime($event['tanggal_mulai']);
    $selesai = new DateTime($event['tanggal_selesai']);
    $selesai->modify('+1 day');

    $period = new DatePeriod($mulai, new DateInterval('P1D'), $selesai);
    foreach ($period as $dt) {
        $tgl_str = $dt->format('Y-m-d');
        if (!isset($events_by_date[$tgl_str])) {
            $events_by_date[$tgl_str] = [];
        }
        $events_by_date[$tgl_str][] = $event;
    }
}

// Data kalender bulan ini: jumlah hari, hari pertama (1: Senin s.d 7: Minggu)
$total_hari_bulan_ini = (int)date('t', strtotime("$tahun_pilihan-$bulan_pilihan-01"));
$hari_pertama = (int)date('N', strtotime("$tahun_pilihan-$bulan_pilihan-01")); // 1 (Senin) - 7 (Minggu)

// Data bulan sebelumnya untuk padding hari awal
$total_hari_bulan_lalu = (int)date('t', $prev_time);

// Tanggal hari ini
$today_str = date('Y-m-d');

$kategori_master = getKategoriKalenderList();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalender Akademik - SIM-SEMSID Politeknik Negeri Lhokseumawe</title>
    
    <!-- Link FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Link File CSS Utama -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php 
    $active_menu = 'kalender';
    include 'public_sidebar.php'; 
    ?>
    <!-- Backdrop Gelap untuk Layar Mobile -->
    <div class="site-sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Area Konten Utama Sebelah Kanan Sidebar -->
    <div class="site-content-wrapper">

        <!-- 1. Topbar Informasi -->
        <div class="topbar">
            <div class="container topbar-content">
                <div class="topbar-info">
                    <span><i class="fas fa-university"></i> Politeknik Negeri Lhokseumawe</span>
                    <span><i class="fas fa-laptop-code"></i> Jurusan TIK</span>
                    <span><i class="fas fa-code-branch"></i> Program Studi Teknologi Rekayasa Perangkat Lunak</span>
                </div>
                <div class="topbar-info">
                    <span><i class="fas fa-clock"></i> Pelayanan: Senin - Jumat (08:00 - 16:00 WIB)</span>
                </div>
            </div>
        </div>

        <!-- 2. Navbar -->
        <header class="navbar">
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

                <ul class="nav-menu" id="navMenu">
                    <li><a href="index.php#beranda" class="nav-link">Beranda</a></li>
                    <li><a href="index.php#alur" class="nav-link">Alur Pelayanan</a></li>
                    <li><a href="index.php#ruangan" class="nav-link">Ketersediaan Ruangan</a></li>
                    <li><a href="kalender_akademik.php" class="nav-link active">Kalender Akademik</a></li>
                </ul>

                <div class="nav-buttons">
                    <?php if ($is_logged_in): ?>
                        <?php if ($user_role === 'mahasiswa'): ?>
                            <a href="dashboard_mahasiswa.php" class="btn btn-primary" title="Buka Dashboard Mahasiswa">
                                <i class="fas fa-user-circle"></i> Dashboard (<?= htmlspecialchars($user_name); ?>)
                            </a>
                        <?php elseif ($user_role === 'dosen'): ?>
                            <a href="dashboard_dosen.php" class="btn btn-primary" title="Buka Dashboard Dosen">
                                <i class="fas fa-chalkboard-teacher"></i> Dashboard Dosen
                            </a>
                        <?php else: ?>
                            <a href="dashboard_admin.php" class="btn btn-primary" title="Buka Dashboard Admin">
                                <i class="fas fa-shield-halved"></i> Dashboard Admin
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-outline-admin" title="Keluar Akun" style="color: #dc2626; border-color: #fca5a5;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="login_mahasiswa.php" class="btn btn-primary" title="Masuk sebagai Mahasiswa">
                            <i class="fas fa-user-graduate"></i> Login Mahasiswa
                        </a>
                        <a href="login_dosen.php" class="btn btn-outline-admin" title="Masuk sebagai Dosen">
                            <i class="fas fa-chalkboard-teacher"></i> Login Dosen
                        </a>
                        <a href="login_admin.php" class="btn btn-outline-admin" title="Masuk sebagai Admin Pengelola">
                            <i class="fas fa-user-shield"></i> Login Admin
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Tombol Toggle Sidebar Khusus Mobile / Tablet -->
                <button class="btn-sidebar-toggle" id="sidebarToggleBtn" aria-label="Buka Menu Sidebar">
                    <i class="fas fa-bars-staggered"></i> Menu
                </button>

                <button class="menu-toggle" id="menuToggle" aria-label="Buka Menu Navigasi">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </header>

        <!-- 3. Konten Utama Kalender Akademik -->
        <main style="padding: 35px 0 60px;">
            <div class="container">
                <!-- Breadcrumb -->
                <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">
                    <a href="index.php" style="color: var(--primary); text-decoration: none;"><i class="fas fa-house"></i> Beranda</a>
                    <span style="margin: 0 8px;">/</span>
                    <span style="color: #0f172a; font-weight: 600;">Kalender Akademik & Agenda Kegiatan</span>
                </div>

                <!-- Header Judul -->
                <div class="section-title" style="text-align: left; margin-bottom: 25px;">
                    <span class="badge" style="background-color: var(--primary-light); color: var(--primary); padding: 6px 14px; border-radius: 50px; font-weight: 700; border: 1px solid var(--primary-border);">
                        <i class="fas fa-calendar-days"></i> Kalender Akademik Resmi
                    </span>
                    <h2 style="margin-top: 10px;">Kalender Akademik & Jadwal Pelaksanaan Ujian</h2>
                    <p style="margin: 0; max-width: 850px;">
                        Pantau seluruh timeline akademik perkuliahan, periode pendaftaran seminar & sidang, serta jadwal ujian resmi mahasiswa yang telah disahkan oleh Program Studi Teknologi Rekayasa Perangkat Lunak.
                    </p>
                </div>

                <!-- KARTU KALENDER UTAMA -->
                <div class="calendar-card">
                    <!-- Toolbar Navigasi Bulan & Mode Tampilan -->
                    <div class="calendar-toolbar">
                        <!-- Kontrol Bulan Sebelumnya, Judul Bulan, & Bulan Berikutnya -->
                        <div class="calendar-month-nav">
                            <a href="kalender_akademik.php?bulan=<?= $prev_bulan; ?>&tahun=<?= $prev_tahun; ?>" 
                               class="calendar-btn-nav" 
                               title="Bulan Sebelumnya (<?= getNamaBulanIndonesia($prev_bulan) . ' ' . $prev_tahun; ?>)">
                                <i class="fas fa-chevron-left"></i>
                            </a>

                            <div style="display: flex; align-items: center; gap: 10px;">
                                <h3 class="calendar-month-title" id="calendarTitle">
                                    <?= getNamaBulanIndonesia($bulan_pilihan); ?> <?= $tahun_pilihan; ?>
                                </h3>

                                <!-- Pemilih Cepat Bulan & Tahun -->
                                <form method="GET" action="kalender_akademik.php" style="display: inline-flex; gap: 6px; align-items: center;">
                                    <select name="bulan" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600; font-size: 0.85rem; background: #ffffff;">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?= $m; ?>" <?= ($m === $bulan_pilihan) ? 'selected' : ''; ?>>
                                                <?= getNamaBulanIndonesia($m); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <select name="tahun" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600; font-size: 0.85rem; background: #ffffff;">
                                        <?php for ($y = 2025; $y <= 2029; $y++): ?>
                                            <option value="<?= $y; ?>" <?= ($y === $tahun_pilihan) ? 'selected' : ''; ?>>
                                                <?= $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </form>
                            </div>

                            <a href="kalender_akademik.php?bulan=<?= $next_bulan; ?>&tahun=<?= $next_tahun; ?>" 
                               class="calendar-btn-nav" 
                               title="Bulan Berikutnya (<?= getNamaBulanIndonesia($next_bulan) . ' ' . $next_tahun; ?>)">
                                <i class="fas fa-chevron-right"></i>
                            </a>

                            <a href="kalender_akademik.php?bulan=11&tahun=2027" 
                               class="btn btn-outline-admin" 
                               style="padding: 7px 12px; font-size: 0.82rem; border-radius: 8px;" 
                               title="Lihat Jadwal Terpadu">
                                <i class="fas fa-calendar-check"></i> Nov 2027 (Aktif)
                            </a>
                        </div>

                        <!-- Switcher: Tampilan Kalender Bulanan vs Tampilan Daftar -->
                        <div class="calendar-view-switcher">
                            <button type="button" class="calendar-view-btn active" id="btnViewCalendar" onclick="switchView('calendar')">
                                <i class="fas fa-calendar-days"></i> Kalender Bulanan
                            </button>
                            <button type="button" class="calendar-view-btn" id="btnViewList" onclick="switchView('list')">
                                <i class="fas fa-list-ul"></i> Tampilan Daftar (<?= count($semua_kegiatan); ?>)
                            </button>
                        </div>
                    </div>

                    <!-- Legend Kategori Kegiatan Akademik (Warna Berbeda untuk Setiap Kategori) -->
                    <div class="calendar-legend-box">
                        <div class="calendar-legend-title">
                            <i class="fas fa-tags"></i> Kategori Kegiatan (Klik untuk Filter Kategori):
                        </div>
                        <div class="calendar-legend-items">
                            <span class="calendar-legend-badge active-filter" 
                                  style="background: #0f172a; color: #ffffff;" 
                                  onclick="filterKategori('semua', this)">
                                <i class="fas fa-asterisk"></i> Semua Kategori (<?= count($semua_kegiatan); ?>)
                            </span>
                            <?php foreach ($kategori_master as $kat_key => $k_info): ?>
                                <?php $count = $kategori_counts[$kat_key] ?? 0; ?>
                                <span class="calendar-legend-badge" 
                                      data-kategori="<?= htmlspecialchars($kat_key); ?>"
                                      style="background: <?= $k_info['warna_bg']; ?>; color: <?= $k_info['warna_teks']; ?>; border-color: <?= $k_info['warna_border']; ?>;"
                                      onclick="filterKategori('<?= htmlspecialchars($kat_key); ?>', this)">
                                    <i class="fas <?= $k_info['ikon']; ?>"></i>
                                    <?= htmlspecialchars($k_info['nama']); ?>
                                    <?php if ($count > 0): ?>
                                        <span style="background: <?= $k_info['warna_badge']; ?>; color: white; padding: 1px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: 2px;">
                                            <?= $count; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ==================== A. TAMPILAN GRID KALENDER BULANAN ==================== -->
                    <div id="viewCalendarContainer">
                        <!-- Header 7 Kolom Hari (Senin - Minggu) -->
                        <div class="calendar-grid-header">
                            <div class="calendar-grid-day-name">Senin</div>
                            <div class="calendar-grid-day-name">Selasa</div>
                            <div class="calendar-grid-day-name">Rabu</div>
                            <div class="calendar-grid-day-name">Kamis</div>
                            <div class="calendar-grid-day-name">Jumat</div>
                            <div class="calendar-grid-day-name weekend">Sabtu</div>
                            <div class="calendar-grid-day-name weekend">Minggu</div>
                        </div>

                        <!-- Grid Sel Hari dalam Bulan -->
                        <div class="calendar-grid-body">
                            <?php
                            // 1. Sel hari dari bulan sebelumnya (Padding awal)
                            $padding_awal = $hari_pertama - 1; // Jika Senin (1) -> 0 sel, jika Selasa (2) -> 1 sel
                            for ($p = $padding_awal; $p > 0; $p--) {
                                $tgl_pad = $total_hari_bulan_lalu - $p + 1;
                                echo '<div class="calendar-day-cell other-month">';
                                echo '  <div class="calendar-day-header"><span class="calendar-day-num">' . $tgl_pad . '</span></div>';
                                echo '</div>';
                            }

                            // 2. Sel hari aktif bulan ini (1 s.d $total_hari_bulan_ini)
                            for ($d = 1; $d <= $total_hari_bulan_ini; $d++) {
                                $current_date_str = sprintf('%04d-%02d-%02d', $tahun_pilihan, $bulan_pilihan, $d);
                                $is_today = ($current_date_str === $today_str);
                                $events_today = $events_by_date[$current_date_str] ?? [];
                                $total_events_today = count($events_today);

                                echo '<div class="calendar-day-cell ' . ($is_today ? 'is-today' : '') . '" data-date="' . $current_date_str . '">';
                                echo '  <div class="calendar-day-header">';
                                echo '      <span class="calendar-day-num">' . $d . '</span>';
                                if ($is_today) {
                                    echo '  <span style="font-size: 0.65rem; background: var(--primary); color: white; padding: 1px 5px; border-radius: 4px; font-weight: 700;">HARI INI</span>';
                                }
                                echo '  </div>';

                                echo '  <div class="calendar-day-events">';
                                $display_limit = 3;
                                $rendered = 0;

                                foreach ($events_today as $ev) {
                                    $rendered++;
                                    if ($rendered <= $display_limit) {
                                        $k_inf = $ev['kategori_info'];
                                        $badge_border = $k_inf['warna_badge'];
                                        $badge_bg = $k_inf['warna_bg'];
                                        $badge_color = $k_inf['warna_teks'];
                                        $ikon_ev = $k_inf['ikon'];

                                        $json_detail = htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8');

                                        echo '<div class="calendar-event-chip" ';
                                        echo '     data-kategori="' . htmlspecialchars($ev['kategori']) . '" ';
                                        echo '     style="background-color: ' . $badge_bg . '; color: ' . $badge_color . '; border-left-color: ' . $badge_border . ';" ';
                                        echo '     onclick="openEventDetail(' . $json_detail . ')" ';
                                        echo '     title="' . htmlspecialchars($ev['nama_kegiatan'] . ' (' . $ev['waktu_kegiatan'] . ')') . '">';
                                        echo '  <i class="fas ' . $ikon_ev . '"></i>';
                                        echo '  <span>' . htmlspecialchars($ev['nama_kegiatan']) . '</span>';
                                        echo '</div>';
                                    }
                                }

                                if ($total_events_today > $display_limit) {
                                    $sisa = $total_events_today - $display_limit;
                                    echo '<div class="calendar-event-more" onclick="showAllEventsDay(\'' . $current_date_str . '\')">';
                                    echo '  <i class="fas fa-ellipsis"></i> +' . $sisa . ' kegiatan lainnya';
                                    echo '</div>';
                                }

                                echo '  </div>';
                                echo '</div>';
                            }

                            // 3. Sel hari dari bulan berikutnya (Padding akhir agar kelipatan 7)
                            $total_sel = $padding_awal + $total_hari_bulan_ini;
                            $padding_akhir = (7 - ($total_sel % 7)) % 7;
                            for ($np = 1; $np <= $padding_akhir; $np++) {
                                echo '<div class="calendar-day-cell other-month">';
                                echo '  <div class="calendar-day-header"><span class="calendar-day-num">' . $np . '</span></div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- ==================== B. TAMPILAN DAFTAR KEGIATAN (LIST VIEW) ==================== -->
                    <div id="viewListContainer" class="calendar-list-container" style="display: none;">
                        <!-- Pencarian & Filter Cepat Daftar Kegiatan -->
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; align-items: center; justify-content: space-between;">
                            <div style="flex: 1; min-width: 250px; position: relative;">
                                <i class="fas fa-magnifying-glass" style="position: absolute; left: 14px; top: 12px; color: #94a3b8;"></i>
                                <input type="text" id="inputSearchList" 
                                       placeholder="Cari kegiatan akademik atau nama mahasiswa..." 
                                       oninput="searchListKegiatan()" 
                                       style="width: 100%; padding: 10px 14px 10px 38px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.9rem;">
                            </div>
                            <div>
                                <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">
                                    Menampilkan <strong id="countFilteredList"><?= count($semua_kegiatan); ?></strong> kegiatan di bulan ini
                                </span>
                            </div>
                        </div>

                        <?php if (empty($semua_kegiatan)): ?>
                            <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                                <i class="fas fa-calendar-xmark" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px; display: block;"></i>
                                <h4 style="color: #334155;">Tidak Ada Kegiatan Akademik di Bulan Ini</h4>
                                <p style="font-size: 0.88rem;">Silakan pilih bulan lain menggunakan navigasi di bagian atas.</p>
                            </div>
                        <?php else: ?>
                            <div id="listKegiatanItems">
                                <?php foreach ($semua_kegiatan as $ev): ?>
                                    <?php 
                                        $k_inf = $ev['kategori_info'];
                                        $tgl_m = date('d', strtotime($ev['tanggal_mulai']));
                                        $bln_m = getNamaBulanIndonesia(date('n', strtotime($ev['tanggal_mulai'])));
                                        $tgl_s = date('d', strtotime($ev['tanggal_selesai']));
                                        $bln_s = getNamaBulanIndonesia(date('n', strtotime($ev['tanggal_selesai'])));

                                        $rentang_tgl = ($ev['tanggal_mulai'] === $ev['tanggal_selesai']) ? "$tgl_m $bln_m " . date('Y', strtotime($ev['tanggal_mulai'])) : "$tgl_m $bln_m - $tgl_s $bln_s " . date('Y', strtotime($ev['tanggal_selesai']));
                                        $json_detail = htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="calendar-list-card" 
                                         data-nama="<?= strtolower(htmlspecialchars($ev['nama_kegiatan'] . ' ' . $ev['deskripsi'])); ?>"
                                         data-kategori="<?= htmlspecialchars($ev['kategori']); ?>"
                                         style="border-left-color: <?= $k_inf['warna_badge']; ?>;">
                                        
                                        <!-- Tanggal Kotak Kiri -->
                                        <div class="calendar-list-date-badge" style="border: 1px solid <?= $k_inf['warna_border']; ?>; background: <?= $k_inf['warna_bg']; ?>;">
                                            <div class="calendar-list-date-day" style="color: <?= $k_inf['warna_teks']; ?>;"><?= $tgl_m; ?></div>
                                            <div class="calendar-list-date-month" style="color: <?= $k_inf['warna_teks']; ?>;"><?= substr($bln_m, 0, 3); ?></div>
                                        </div>

                                        <!-- Konten Kegiatan -->
                                        <div class="calendar-list-content">
                                            <div class="calendar-list-header">
                                                <h4 class="calendar-list-title"><?= htmlspecialchars($ev['nama_kegiatan']); ?></h4>
                                                <span class="badge" style="background: <?= $k_inf['warna_bg']; ?>; color: <?= $k_inf['warna_teks']; ?>; border: 1px solid <?= $k_inf['warna_border']; ?>; font-size: 0.72rem; padding: 2px 8px; border-radius: 20px;">
                                                    <i class="fas <?= $k_inf['ikon']; ?>"></i> <?= htmlspecialchars($ev['kategori']); ?>
                                                </span>
                                                <?php if ($ev['is_jadwal_resmi']): ?>
                                                    <span class="badge" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-size: 0.7rem; padding: 2px 7px; border-radius: 20px;">
                                                        <i class="fas fa-certificate"></i> Ujian Terjadwal Resmi
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="calendar-list-meta">
                                                <span><i class="fas fa-calendar-day" style="color: var(--primary);"></i> <?= $rentang_tgl; ?></span>
                                                <span><i class="fas fa-clock" style="color: #ea580c;"></i> <?= htmlspecialchars($ev['waktu_kegiatan']); ?></span>
                                                <span><i class="fas fa-building-columns"></i> Semester <?= htmlspecialchars($ev['semester']); ?> (TA <?= htmlspecialchars($ev['tahun_akademik']); ?>)</span>
                                            </div>

                                            <p class="calendar-list-desc">
                                                <?= nl2br(htmlspecialchars($ev['deskripsi'])); ?>
                                            </p>
                                        </div>

                                        <!-- Tombol Aksi Detail -->
                                        <div>
                                            <button type="button" class="btn btn-outline-admin" 
                                                    style="padding: 7px 12px; font-size: 0.8rem; border-radius: 8px;"
                                                    onclick="openEventDetail(<?= $json_detail; ?>)">
                                                <i class="fas fa-circle-info"></i> Detail
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>

        <!-- 4. Footer -->
        <footer class="footer">
            <div class="container footer-bottom" style="border: none; padding: 25px 0;">
                <p>&copy; <?= date('Y'); ?> <strong>SIM-SEMSID</strong> - Program Studi Teknologi Rekayasa Perangkat Lunak, Politeknik Negeri Lhokseumawe.</p>
            </div>
        </footer>

    </div> <!-- /.site-content-wrapper -->

    <!-- ==================== MODAL DETAIL KEGIATAN ==================== -->
    <div class="calendar-modal-backdrop" id="modalEventDetail" onclick="closeEventDetailOnBackdrop(event)">
        <div class="calendar-modal-content">
            <div class="calendar-modal-header">
                <div>
                    <span id="modalKategoriBadge" class="badge" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 20px;">
                        <!-- Kategori diisi JS -->
                    </span>
                    <h3 id="modalEventTitle">Judul Kegiatan</h3>
                </div>
                <button type="button" class="calendar-modal-close" onclick="closeEventDetail()">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>

            <div class="calendar-modal-body">
                <!-- Grid Informasi Waktu & Semester -->
                <div class="calendar-modal-info-grid">
                    <div class="calendar-modal-info-item">
                        <label><i class="fas fa-calendar-day"></i> Tanggal Pelaksanaan:</label>
                        <span id="modalEventDate">-</span>
                    </div>
                    <div class="calendar-modal-info-item">
                        <label><i class="fas fa-clock"></i> Waktu Kegiatan:</label>
                        <span id="modalEventTime">-</span>
                    </div>
                    <div class="calendar-modal-info-item">
                        <label><i class="fas fa-graduation-cap"></i> Semester & Tahun:</label>
                        <span id="modalEventSemester">-</span>
                    </div>
                    <div class="calendar-modal-info-item">
                        <label><i class="fas fa-shield-halved"></i> Status Publikasi:</label>
                        <span id="modalEventStatus" style="color: #16a34a; font-weight: 700;">Dipublikasikan</span>
                    </div>
                </div>

                <!-- Bagian Khusus Jika Berasal dari Jadwal Seminar / Sidang Resmi Mahasiswa -->
                <div id="modalExamDetailBox" style="display: none; background: #fff7ed; border: 1px solid #fed7aa; padding: 14px; border-radius: 10px; margin-bottom: 16px;">
                    <div style="font-weight: 800; font-size: 0.8rem; text-transform: uppercase; color: #c2410c; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-id-card"></i> Informasi Ujian Resmi Mahasiswa:
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; font-size: 0.85rem;">
                        <div><strong>Mahasiswa:</strong> <span id="modalExamMhs">-</span></div>
                        <div><strong>NIM:</strong> <span id="modalExamNim">-</span></div>
                        <div><strong>Ruangan:</strong> <span id="modalExamRuangan">-</span></div>
                        <div><strong>Status:</strong> <span id="modalExamStatus">-</span></div>
                        <div style="grid-column: 1 / -1;"><strong>Pembimbing:</strong> <span id="modalExamPembimbing">-</span></div>
                        <div style="grid-column: 1 / -1;"><strong>Penguji:</strong> <span id="modalExamPenguji">-</span></div>
                    </div>
                </div>

                <!-- Deskripsi Kegiatan -->
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 6px;">
                        <i class="fas fa-align-left"></i> Keterangan & Deskripsi:
                    </label>
                    <div class="calendar-modal-desc" id="modalEventDesc">
                        Deskripsi kegiatan akan ditampilkan di sini.
                    </div>
                </div>
            </div>

            <div class="calendar-modal-footer">
                <button type="button" class="btn btn-outline-admin" onclick="closeEventDetail()" style="padding: 8px 18px; font-size: 0.88rem;">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== JAVASCRIPT KALENDER ==================== -->
    <script>
        // 1. Switch Tampilan (Kalender Bulanan vs Daftar)
        function switchView(view) {
            const calView = document.getElementById('viewCalendarContainer');
            const listView = document.getElementById('viewListContainer');
            const btnCal = document.getElementById('btnViewCalendar');
            const btnList = document.getElementById('btnViewList');

            if (view === 'calendar') {
                calView.style.display = '';
                listView.style.display = 'none';
                btnCal.classList.add('active');
                btnList.classList.remove('active');
            } else {
                calView.style.display = 'none';
                listView.style.display = '';
                btnCal.classList.remove('active');
                btnList.classList.add('active');
            }
        }

        // 2. Filter Berdasarkan Kategori Kegiatan
        let currentKategoriFilter = 'semua';
        function filterKategori(kat, element) {
            currentKategoriFilter = kat;

            // Update badge aktif
            document.querySelectorAll('.calendar-legend-badge').forEach(function(b) {
                b.classList.remove('active-filter');
            });
            if (element) {
                element.classList.add('active-filter');
            }

            // Filter di tampilan kalender bulanan (chip)
            const chips = document.querySelectorAll('.calendar-event-chip');
            chips.forEach(function(chip) {
                const chipKat = chip.getAttribute('data-kategori');
                if (kat === 'semua' || chipKat === kat) {
                    chip.style.display = '';
                } else {
                    chip.style.display = 'none';
                }
            });

            // Filter di tampilan daftar kegiatan (card)
            filterListView();
        }

        // 3. Pencarian & Filter di Tampilan Daftar
        function searchListKegiatan() {
            filterListView();
        }

        function filterListView() {
            const keyword = (document.getElementById('inputSearchList') ? document.getElementById('inputSearchList').value : '').toLowerCase().trim();
            const cards = document.querySelectorAll('.calendar-list-card');
            let count = 0;

            cards.forEach(function(card) {
                const nama = card.getAttribute('data-nama') || '';
                const kat = card.getAttribute('data-kategori') || '';

                const matchKeyword = !keyword || (nama.indexOf(keyword) !== -1);
                const matchKat = (currentKategoriFilter === 'semua') || (kat === currentKategoriFilter);

                if (matchKeyword && matchKat) {
                    card.style.display = '';
                    count++;
                } else {
                    card.style.display = 'none';
                }
            });

            const countEl = document.getElementById('countFilteredList');
            if (countEl) countEl.innerText = count;
        }

        // 4. Modal Detail Kegiatan
        function openEventDetail(eventData) {
            if (!eventData) return;

            document.getElementById('modalEventTitle').innerText = eventData.nama_kegiatan || '-';
            
            // Format Badge Kategori
            const kInfo = eventData.kategori_info || {};
            const badgeEl = document.getElementById('modalKategoriBadge');
            badgeEl.innerText = eventData.kategori || 'Kegiatan';
            badgeEl.style.backgroundColor = kInfo.warna_bg || '#f1f5f9';
            badgeEl.style.color = kInfo.warna_teks || '#334155';
            badgeEl.style.border = '1px solid ' + (kInfo.warna_border || '#cbd5e1');

            // Format Tanggal
            let rentang = eventData.tanggal_mulai;
            if (eventData.tanggal_selesai && eventData.tanggal_selesai !== eventData.tanggal_mulai) {
                rentang += ' s.d ' + eventData.tanggal_selesai;
            }
            document.getElementById('modalEventDate').innerText = rentang;
            document.getElementById('modalEventTime').innerText = eventData.waktu_kegiatan || 'Sepanjang Hari';
            document.getElementById('modalEventSemester').innerText = 'Semester ' + (eventData.semester || 'Ganjil') + ' (' + (eventData.tahun_akademik || '2026/2027') + ')';
            document.getElementById('modalEventDesc').innerHTML = (eventData.deskripsi || '-').replace(/\n/g, '<br>');

            // Detail Ujian Resmi Mahasiswa
            const examBox = document.getElementById('modalExamDetailBox');
            if (eventData.is_jadwal_resmi) {
                examBox.style.display = 'block';
                document.getElementById('modalExamMhs').innerText = eventData.mahasiswa_nama || '-';
                document.getElementById('modalExamNim').innerText = eventData.mahasiswa_nim || '-';
                document.getElementById('modalExamRuangan').innerText = eventData.ruangan || '-';
                document.getElementById('modalExamStatus').innerText = eventData.status_jadwal || '-';
                document.getElementById('modalExamPembimbing').innerText = eventData.pembimbing || '-';
                document.getElementById('modalExamPenguji').innerText = eventData.penguji || '-';
            } else {
                examBox.style.display = 'none';
            }

            const modal = document.getElementById('modalEventDetail');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeEventDetail() {
            const modal = document.getElementById('modalEventDetail');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        function closeEventDetailOnBackdrop(e) {
            if (e.target && e.target.id === 'modalEventDetail') {
                closeEventDetail();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventDetail();
            }
        });

        // 5. Lihat Semua Kegiatan pada Suatu Hari
        function showAllEventsDay(dateStr) {
            switchView('list');
            const searchInput = document.getElementById('inputSearchList');
            if (searchInput) {
                searchInput.value = dateStr;
                searchListKegiatan();
            }
        }
    </script>
</body>
</html>
