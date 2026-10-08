/**
 * Homeroom Teacher Island
 * Mencegah satu guru dipilih sebagai wali kelas pada dua rombel
 * di tahun ajaran yang sama, dan menyembunyikan guru yang tidak aktif
 * (guru nonaktif sudah difilter di sisi server).
 */
export function initHomeroomTeacherIsland() {
    document.querySelectorAll('select[data-homeroom-teacher-select]').forEach((teacherSelect) => {
        const form = teacherSelect.closest('form');
        const yearSelect = form?.querySelector('select[data-academic-year-select]');
        if (!yearSelect) return;

        function sync() {
            const year = yearSelect.value;

            Array.from(teacherSelect.options).forEach((option) => {
                if (!option.value) return;

                if (option.dataset.originalLabel === undefined) {
                    option.dataset.originalLabel = option.textContent.trim();
                }

                const isTaken = year !== '' && parseAssignedYears(option).includes(year);
                option.disabled = isTaken;
                option.textContent = isTaken
                    ? `${option.dataset.originalLabel} — sudah wali kelas ${year}`
                    : option.dataset.originalLabel;
            });

            const selected = teacherSelect.selectedOptions[0];
            if (selected && selected.disabled) {
                teacherSelect.value = '';
            }
        }

        yearSelect.addEventListener('change', sync);
        sync();
    });
}

function parseAssignedYears(option) {
    try {
        const parsed = JSON.parse(option.dataset.assignedYears || '[]');

        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
}
