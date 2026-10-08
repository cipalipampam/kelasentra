/**
 * Classrooms Page Orchestrator (ESM)
 * Mengelola inisialisasi modul UI/UX pada halaman manajemen rombel & kelas.
 */
import { initClassroomNamingAssistant } from './islands/naming-assistant-island.js';

function bootstrapClassrooms() {
    initClassroomNamingAssistant();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapClassrooms);
} else {
    bootstrapClassrooms();
}

