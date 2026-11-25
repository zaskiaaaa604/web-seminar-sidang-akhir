/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TRPL
 * FILE: notifikasi.js (Skrip AJAX Notifikasi Otomatis Interval 10 Detik)
 * ===================================================================
 */

let notifikasiDataCache = [];
let notifikasiPollingTimer = null;

// Fungsi Menampilkan / Menyembunyikan Dropdown Notifikasi
function toggleDropdownNotifikasi(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const panel = document.getElementById('notifikasiDropdownPanel');
    if (!panel) return;

    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'block';
        // Muat data notifikasi terbaru saat dropdown dibuka
        muatNotifikasiAjax(true);
    } else {
        panel.style.display = 'none';
    }
}

// Tutup dropdown jika mengklik area luar
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notifikasiDropdownWrapper');
    const panel = document.getElementById('notifikasiDropdownPanel');
    if (wrapper && panel && !wrapper.contains(e.target)) {
        panel.style.display = 'none';
    }
});

// Fungsi Memuat Data Notifikasi Melalui AJAX
function muatNotifikasiAjax(isManualClick = false) {
    fetch('api_notifikasi.php?action=get_summary&limit=6', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Sesi tidak aktif atau error jaringan');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateBadgeNotifikasi(data.unread_count);
            renderDaftarNotifikasi(data.notifications);
        }
    })
    .catch(err => {
        // Abaikan error background polling jika sesi telah logout
        if (isManualClick) {
            console.error('Gagal mengambil notifikasi:', err);
        }
    });
}

// Memperbarui Tampilan Badge Jumlah Notifikasi Belum Dibaca
function updateBadgeNotifikasi(count) {
    const badge = document.getElementById('notifikasiBadge');
    if (!badge) return;

    const unread = parseInt(count, 10) || 0;
    if (unread > 0) {
        badge.innerText = unread > 99 ? '99+' : unread;
        badge.style.display = 'flex';
    } else {
        badge.innerText = '0';
        badge.style.display = 'none';
    }
}

// Merender Daftar Notifikasi ke dalam Panel Dropdown
function renderDaftarNotifikasi(notifications) {
    const body = document.getElementById('notifikasiDropdownBody');
    if (!body) return;

    if (!notifications || notifications.length === 0) {
        body.innerHTML = `
            <div class="notifikasi-empty">
                <i class="far fa-bell-slash"></i>
                <p style="margin: 0; font-weight: 600; color: #64748b;">Tidak ada notifikasi baru</p>
                <span style="font-size: 0.75rem; color: #94a3b8;">Semua pemberitahuan telah dibaca</span>
            </div>
        `;
        return;
    }

    let html = '';
    notifications.forEach(item => {
        const isUnread = parseInt(item.is_read, 10) === 0;
        let iconClass = 'fas fa-info';
        let iconBg = 'background: #e2e8f0; color: #475569;';

        switch (item.tipe) {
            case 'diterima':
                iconClass = 'fas fa-check';
                iconBg = 'background: #dcfce7; color: #166534;';
                break;
            case 'ditolak':
                iconClass = 'fas fa-times';
                iconBg = 'background: #fee2e2; color: #991b1b;';
                break;
            case 'revisi':
                iconClass = 'fas fa-pen';
                iconBg = 'background: #fef3c7; color: #92400e;';
                break;
            case 'pengajuan':
                iconClass = 'fas fa-lightbulb';
                iconBg = 'background: #e0f2fe; color: #075985;';
                break;
            case 'jadwal':
                iconClass = 'fas fa-calendar-alt';
                iconBg = 'background: #f3e8ff; color: #6b21a8;';
                break;
            case 'verifikasi':
                iconClass = 'fas fa-shield-alt';
                iconBg = 'background: #ffedd5; color: #c2410c;';
                break;
            case 'komentar':
                iconClass = 'fas fa-comment-dots';
                iconBg = 'background: #fef9c3; color: #854d0e;';
                break;
        }

        const linkUrl = 'api_notifikasi.php?action=redirect&id=' + item.id;

        html += `
            <a href="${linkUrl}" class="notifikasi-dropdown-item ${isUnread ? 'unread' : ''}">
                <div class="notif-item-icon" style="${iconBg}">
                    <i class="${iconClass}"></i>
                </div>
                <div class="notif-item-text">
                    <div class="notif-item-title">${escapeHtml(item.judul)}</div>
                    <div class="notif-item-desc">${escapeHtml(item.pesan)}</div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                        <span class="notif-item-time"><i class="far fa-clock"></i> ${escapeHtml(item.waktu_lalu)}</span>
                        ${isUnread ? '<span style="font-size: 0.68rem; color: #f97316; font-weight: 700;">Baru</span>' : ''}
                    </div>
                </div>
            </a>
        `;
    });

    body.innerHTML = html;
}

// Fungsi AJAX: Tandai Semua Notifikasi Sudah Dibaca
function tandaiSemuaNotifikasiAjax(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    fetch('api_notifikasi.php?action=mark_all_read', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateBadgeNotifikasi(0);
            muatNotifikasiAjax(false);
        }
    })
    .catch(err => console.error('Gagal menandai semua notifikasi:', err));
}

// Utility: Escape HTML untuk mencegah XSS
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
}

// Inisialisasi Saat Dokumen Selesai Dimuat
document.addEventListener('DOMContentLoaded', function() {
    // 1. Muat data notifikasi awal
    muatNotifikasiAjax(false);

    // 2. Pasang interval pemeriksaan polling setiap 10 detik (sesuai spesifikasi ketentuan E.3)
    if (notifikasiPollingTimer) clearInterval(notifikasiPollingTimer);
    notifikasiPollingTimer = setInterval(function() {
        muatNotifikasiAjax(false);
    }, 10000);
});
