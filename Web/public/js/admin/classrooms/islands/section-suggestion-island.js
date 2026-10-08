/**
 * Section Suggestion Island
 * Mengusulkan nomor sesi rombel pada form tambah rombel berdasarkan rombel
 * yang sudah ada pada tingkat, jurusan, dan tahun ajaran yang sama.
 *
 * Aturannya: sesi baru hanya diusulkan ketika sesi terakhir sudah penuh.
 * Bila sesi terakhir masih punya kursi, nomor sesi dipertahankan dan admin
 * diberi tahu agar siswa masuk ke rombel yang sudah ada.
 */
export function initSectionSuggestionIsland() {
    const sectionInput = document.getElementById('create_section');
    const levelSelect = document.getElementById('create_level');
    const majorSelect = document.getElementById('create_major');
    const yearSelect = document.getElementById('create_academic_year');
    const hint = document.getElementById('create_section_hint');

    if (!sectionInput || !levelSelect || !majorSelect || !yearSelect) return;

    const overview = parseOverview(sectionInput.dataset.sectionOverview);

    // Isian manual admin tidak boleh ditimpa.
    let autoFilled = sectionInput.value === '';
    // Mencegah event `input` yang kita picu sendiri dianggap isian manual.
    let applying = false;

    function setHint(message, isWarning = false) {
        if (!hint) return;

        hint.textContent = message;
        hint.classList.toggle('text-danger', isWarning);
        hint.classList.toggle('text-muted', !isWarning);
    }

    function isManualEntry() {
        return !autoFilled && sectionInput.value !== '';
    }

    function applySection(value) {
        applying = true;
        sectionInput.value = value;
        // Beri tahu Naming Assistant agar nama rombel ikut menyesuaikan.
        sectionInput.dispatchEvent(new Event('input', { bubbles: true }));
        applying = false;
        autoFilled = true;
    }

    function suggest() {
        const level = levelSelect.value;
        const year = yearSelect.value;

        if (!level || !year) {
            setHint('Nomor sesi terisi otomatis setelah tingkat, jurusan, dan tahun ajaran dipilih.');

            return;
        }

        const key = `${level}|${majorSelect.value || ''}|${year}`;
        const sessions = Array.isArray(overview[key]) ? overview[key] : [];
        const numeric = sessions.filter(session => /^\d+$/.test(session.section));

        let recommended = '1';
        let message = `Belum ada rombel lain untuk tingkat ini di tahun ajaran ${year} — sesi dimulai dari 1.`;
        let isWarning = false;

        if (numeric.length > 0) {
            const highest = numeric.reduce((max, session) => Math.max(max, Number(session.section)), 0);
            const last = numeric.find(session => Number(session.section) === highest);

            if (last && last.is_full) {
                recommended = String(highest + 1);
                message = `Sesi ${highest} sudah penuh (${last.active_students} siswa) — lanjut otomatis ke sesi ${recommended}.`;
            } else {
                recommended = String(highest);
                message = `Sesi ${highest} masih menyisakan ${last ? last.remaining : 0} kursi — siswa sebaiknya masuk ke rombel ini sebelum membuka sesi baru.`;
                isWarning = true;
            }
        }

        if (isManualEntry()) {
            setHint(`Nomor sesi diisi manual. Saran sistem: sesi ${recommended}.`);

            return;
        }

        applySection(recommended);
        setHint(message, isWarning);
    }

    sectionInput.addEventListener('input', () => {
        if (applying) return;

        autoFilled = false;
        suggest();
    });

    [levelSelect, majorSelect, yearSelect].forEach(control => {
        control.addEventListener('change', suggest);
    });

    if (autoFilled) {
        suggest();
    } else {
        setHint('Nomor sesi diisi manual. Ubah tingkat, jurusan, atau tahun ajaran untuk melihat saran sistem.');
    }
}

function parseOverview(raw) {
    try {
        const parsed = JSON.parse(raw || '{}');

        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
        return {};
    }
}
