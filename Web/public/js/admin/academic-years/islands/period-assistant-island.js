/**
 * Period Assistant Island
 * Mengisi tanggal mulai/selesai tahun ajaran secara otomatis
 * (Juli tahun pertama s.d. Juni tahun berikutnya) bila masih kosong.
 */
export function initPeriodAssistantIsland() {
    document.querySelectorAll('[data-year-name-input]').forEach((nameInput) => {
        const form = nameInput.closest('form');
        const startInput = form?.querySelector('[data-year-start-input]');
        const endInput = form?.querySelector('[data-year-end-input]');

        if (!startInput || !endInput) return;

        function suggestPeriod() {
            const match = /^(\d{4})\/(\d{4})$/.exec(nameInput.value.trim());
            if (!match) return;

            const [, startYear, endYear] = match;

            if (!startInput.value) {
                startInput.value = `${startYear}-07-01`;
            }

            if (!endInput.value) {
                endInput.value = `${endYear}-06-30`;
            }
        }

        nameInput.addEventListener('change', suggestPeriod);
    });
}
