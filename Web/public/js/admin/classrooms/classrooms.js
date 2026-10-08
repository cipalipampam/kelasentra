/**
 * Classrooms Page Orchestrator (ESM)
 * Mengelola inisialisasi modul UI/UX pada halaman manajemen rombel & kelas.
 */
import { initClassroomNamingAssistant } from './islands/naming-assistant-island.js';
import { initMajorRequirementIsland } from './islands/major-requirement-island.js';
import { initHomeroomTeacherIsland } from './islands/homeroom-teacher-island.js';
import { initSectionSuggestionIsland } from './islands/section-suggestion-island.js';

function bootstrapClassrooms() {
    initClassroomNamingAssistant();
    initMajorRequirementIsland();
    initHomeroomTeacherIsland();
    initSectionSuggestionIsland();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapClassrooms);
} else {
    bootstrapClassrooms();
}

