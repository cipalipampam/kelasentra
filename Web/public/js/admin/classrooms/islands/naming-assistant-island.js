/**
 * Naming Assistant Island
 * Membantu admin menstandarisasi nama rombel otomatis (e.g. "X TKJ 1")
 * berdasarkan input tingkat, jurusan, dan nomor rombel.
 */
export function initClassroomNamingAssistant() {
    const levelSelect = document.getElementById('create_level');
    const majorInput = document.getElementById('create_major');
    const sectionInput = document.getElementById('create_section');
    const nameInput = document.getElementById('create_name');
    const suggestBtn = document.getElementById('btn-suggest-name');

    if (!levelSelect || !majorInput || !sectionInput || !nameInput) return;

    const romanMap = {
        '10': 'X',
        '11': 'XI',
        '12': 'XII'
    };

    function generateSuggestedName() {
        const lvl = romanMap[levelSelect.value] || '';
        const mjr = majorInput.value.trim().toUpperCase();
        const sec = sectionInput.value.trim().toUpperCase();

        const parts = [lvl, mjr, sec].filter(Boolean);
        return parts.join(' ');
    }

    function checkSuggestion() {
        const suggestion = generateSuggestedName();
        if (suggestion && (!nameInput.value || nameInput.dataset.autoFilled === 'true')) {
            nameInput.value = suggestion;
            nameInput.dataset.autoFilled = 'true';
        }

        if (suggestBtn) {
            if (suggestion && nameInput.value !== suggestion) {
                suggestBtn.style.display = 'inline-block';
                suggestBtn.title = `Gunakan nama: "${suggestion}"`;
            } else {
                suggestBtn.style.display = 'none';
            }
        }
    }

    nameInput.addEventListener('input', () => {
        // Jika user sengaja mengetik manual secara custom, jangan auto-overwrite
        nameInput.dataset.autoFilled = 'false';
        if (suggestBtn) suggestBtn.style.display = 'none';
    });

    levelSelect.addEventListener('change', checkSuggestion);
    majorInput.addEventListener('input', checkSuggestion);
    majorInput.addEventListener('change', checkSuggestion);
    sectionInput.addEventListener('input', checkSuggestion);

    if (suggestBtn) {
        suggestBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const suggestion = generateSuggestedName();
            if (suggestion) {
                nameInput.value = suggestion;
                nameInput.dataset.autoFilled = 'true';
                suggestBtn.style.display = 'none';
            }
        });
    }
}

