/**
 * Realtime Island
 * Mengelola langganan Laravel Echo WebSocket untuk pembaruan instan
 * metrik KPI dan baris tabel presensi terkini.
 */
export function initDashboardRealtime() {
    function registerListeners() {
        if (typeof window.Echo === 'undefined') return;

        // Listener 1: Presensi Baru Tercatat
        window.Echo.private('admin.attendance')
            .listen('.AttendanceLogged', (data) => {
                if (data.action === 'check_out') return;

                const tbody = document.getElementById('ws-attendance-tbody');
                if (tbody) {
                    const statusMap = {
                        present:    { label: 'Hadir',  badgeClass: 'badge-present', icon: 'bi-check2' },
                        permission: { label: 'Izin',   badgeClass: 'badge-permission', icon: 'bi-file-text' },
                        sick:       { label: 'Sakit',  badgeClass: 'badge-sick', icon: 'bi-bandaid' },
                        absent:     { label: 'Alfa',   badgeClass: 'badge-absent', icon: 'bi-x-circle' },
                    };
                    const s = statusMap[data.status] ?? statusMap.absent;
                    const initial = (data.user_name ?? '?').charAt(0).toUpperCase();

                    const row = document.createElement('tr');
                    row.className = 'ws-new-row';
                    row.innerHTML = `
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-placeholder">${initial}</div>
                                <div>
                                    <div class="fw-semibold text-dark" style="font-size: 0.88rem;">${data.user_name ?? 'Unknown'}</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">Baru Saja</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.72rem;">Siswa</span></td>
                        <td>
                            <span class="badge-status ${s.badgeClass}">
                                <i class="bi ${s.icon}"></i>${s.label}
                            </span>
                        </td>
                        <td><span class="fw-medium text-dark" style="font-size: 0.85rem;">${data.time ?? ''} WIB</span></td>
                        <td><span class="text-muted small">—</span></td>
                        <td><span class="text-primary small" style="font-size:0.75rem;"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Live GPS</span></td>
                        <td class="pe-4 text-end">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.7rem;">Baru Masuk</span>
                        </td>
                    `;
                    tbody.prepend(row);
                }

                // Efek animasi denyut pada indikator Live Sync
                const dot = document.getElementById('ws-live-dot');
                if (dot) {
                    dot.style.transform = 'scale(1.6)';
                    setTimeout(() => dot.style.transform = 'scale(1)', 600);
                }
            });

        // Listener 2: Pembaruan Metrik Statistik KPI
        window.Echo.private('admin.dashboard')
            .listen('.DashboardStatsUpdated', (data) => {
                if (data.total_present !== undefined) {
                    const el = document.getElementById('ws-present-count');
                    if (el) el.textContent = data.total_present;
                }
                if (data.total_late !== undefined) {
                    const el = document.getElementById('ws-late-count');
                    if (el) el.textContent = data.total_late;
                }
                if (data.pending_approvals !== undefined) {
                    const el = document.getElementById('ws-pending-approval-count');
                    if (el) el.textContent = data.pending_approvals;
                }
            });
    }

    if (typeof window.Echo !== 'undefined') {
        registerListeners();
    } else {
        window.addEventListener('echo:ready', registerListeners, { once: true });
    }
}

