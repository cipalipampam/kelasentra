/**
 * Major Requirement Island
 * Menjadikan jurusan wajib ketika tingkat XI atau XII dipilih.
 * Kelas X belum masuk penjurusan sehingga jurusan boleh dikosongkan.
 */
export function initMajorRequirementIsland() {
    const LEVELS_REQUIRING_MAJOR = ['11', '12'];

    document.querySelectorAll('select[data-major-select]').forEach((majorSelect) => {
        const form = majorSelect.closest('form');
        const levelSelect = form?.querySelector('select[data-level-select]');
        if (!levelSelect) return;

        const asterisk = form.querySelector('[data-major-required-asterisk]');

        function sync() {
            const isRequired = LEVELS_REQUIRING_MAJOR.includes(levelSelect.value);
            majorSelect.required = isRequired;
            if (asterisk) asterisk.style.display = isRequired ? '' : 'none';
        }

        levelSelect.addEventListener('change', sync);
        sync();
    });
}
