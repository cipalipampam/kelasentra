/**
 * Academic Years Page Orchestrator (ESM)
 * Mengelola inisialisasi modul UI/UX pada halaman manajemen tahun ajaran.
 */
import { initPeriodAssistantIsland } from './islands/period-assistant-island.js';
import { initConfirmSubmitIsland } from './islands/confirm-submit-island.js';

function bootstrapAcademicYears() {
    initPeriodAssistantIsland();
    initConfirmSubmitIsland();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapAcademicYears);
} else {
    bootstrapAcademicYears();
}
