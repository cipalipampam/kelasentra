/**
 * Dashboard Orchestrator
 * Entry point untuk memuat dan menginisialisasi seluruh Island pada halaman Dashboard.
 */
import { initDashboardClock } from './islands/clock-island.js';
import { initWeeklyChart } from './islands/chart-island.js';
import { initDashboardRealtime } from './islands/realtime-island.js';
import { initProofModal } from './islands/proof-modal-island.js';

function bootstrapDashboard() {
    initDashboardClock();
    initWeeklyChart();
    initDashboardRealtime();
    initProofModal();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapDashboard);
} else {
    bootstrapDashboard();
}

