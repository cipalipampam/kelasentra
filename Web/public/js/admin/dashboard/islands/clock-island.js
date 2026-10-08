/**
 * Clock Island
 * Mengelola jam digital real-time pada dashboard header.
 */
export function initDashboardClock() {
    const clockEl = document.getElementById('dashboard-clock');
    if (!clockEl) return;

    function updateTime() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        clockEl.textContent = `${h}:${m}`;
    }

    updateTime();
    setInterval(updateTime, 30000);
}

