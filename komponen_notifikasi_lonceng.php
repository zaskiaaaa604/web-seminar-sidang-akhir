<?php
/**
 * ===================================================================
 * SISTEM INFORMASI MANAJEMEN SEMINAR DAN SIDANG AKHIR MAHASISWA
 * POLITEKNIK NEGERI LHOKSEUMAWE - PRODI TIK
 * FILE: komponen_notifikasi_lonceng.php (Komponen UI Lonceng Notifikasi)
 * ===================================================================
 */
?>
<!-- Komponen Lonceng Notifikasi Otomatis SIM-SEMSID -->
<div class="notifikasi-dropdown-wrapper" id="notifikasiDropdownWrapper">
    <button type="button" class="btn-notifikasi-lonceng" id="btnNotifikasiLonceng" onclick="toggleDropdownNotifikasi(event);" aria-label="Notifikasi" title="Pusat Notifikasi">
        <i class="fas fa-bell"></i>
        <span class="notifikasi-badge" id="notifikasiBadge" style="display: none;">0</span>
    </button>

    <!-- Panel Dropdown Notifikasi -->
    <div class="notifikasi-dropdown-panel" id="notifikasiDropdownPanel" style="display: none;">
        <div class="notifikasi-dropdown-header">
            <h4><i class="fas fa-bell" style="color: var(--primary, #f97316);"></i> Notifikasi</h4>
            <div class="notifikasi-header-actions">
                <button type="button" class="btn-tandai-semua" onclick="tandaiSemuaNotifikasiAjax(event);" title="Tandai Semua Sudah Dibaca">
                    <i class="fas fa-check-double"></i> Tandai Dibaca
                </button>
            </div>
        </div>
        
        <div class="notifikasi-dropdown-body" id="notifikasiDropdownBody">
            <div class="notifikasi-empty">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Memuat notifikasi...</span>
            </div>
        </div>

        <div class="notifikasi-dropdown-footer">
            <a href="notifikasi.php">
                Lihat Semua Notifikasi <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>
