/**
 * Duplicate Classroom Modal Island
 *
 * Mengelola interaktivitas modal duplikasi rombel:
 * - Populate tabel rombel berdasarkan TA sumber yang dipilih
 * - Checkbox "Pilih Semua" / "Batalkan Semua"
 * - Enable/disable tombol submit berdasarkan pilihan
 */

const LEVEL_LABELS = { '10': 'Kelas X', '11': 'Kelas XI', '12': 'Kelas XII' };

function buildClassroomRow(classroom) {
    return `
        <tr>
            <td class="ps-3">
                <input type="checkbox" class="form-check-input dup-classroom-check"
                       name="classroom_ids[]" value="${classroom.id}" checked>
            </td>
            <td class="fw-semibold text-dark" style="font-size:0.85rem;">${escapeHtml(classroom.name)}</td>
            <td>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size:0.72rem;">
                    ${LEVEL_LABELS[classroom.level] ?? 'Kelas ' + classroom.level}
                </span>
            </td>
            <td class="text-dark small">${escapeHtml(classroom.major ?? '—')}</td>
            <td class="text-center text-dark small">${escapeHtml(classroom.section)}</td>
        </tr>
    `;
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.dup-classroom-check:checked').length;
    const total   = document.querySelectorAll('.dup-classroom-check').length;
    const counter = document.getElementById('dup_selected_count');
    const submit  = document.getElementById('dup_submit_btn');

    if (counter) counter.textContent = `${checked} dari ${total} rombel dipilih.`;
    if (submit)  submit.disabled = checked === 0;
}

export function initDuplicateModalIsland() {
    const sourceSelect  = document.getElementById('dup_source_year');
    const tbody         = document.getElementById('dup_classroom_rows');
    const listWrap      = document.getElementById('dup_classroom_list_wrap');
    const checkAll      = document.getElementById('dup_check_all');
    const btnSelectAll  = document.getElementById('dup_select_all');
    const btnDeselectAll = document.getElementById('dup_deselect_all');

    if (!sourceSelect || !tbody) return;

    // Populate tabel saat TA sumber berubah
    sourceSelect.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        const classrooms = JSON.parse(option.dataset.classrooms ?? '[]');

        if (!classrooms.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        Tahun ajaran ini belum memiliki rombel.
                    </td>
                </tr>`;
            listWrap.style.display = 'none';
            return;
        }

        tbody.innerHTML = classrooms.map(buildClassroomRow).join('');
        listWrap.style.display = '';
        updateSelectedCount();

        // Re-attach change listeners untuk checkbox baru
        document.querySelectorAll('.dup-classroom-check').forEach(cb => {
            cb.addEventListener('change', () => {
                const allChecked = document.querySelectorAll('.dup-classroom-check:not(:checked)').length === 0;
                if (checkAll) checkAll.checked = allChecked;
                updateSelectedCount();
            });
        });
    });

    // "Pilih Semua" header checkbox
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.dup-classroom-check').forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });
    }

    // Tombol teks "Pilih Semua"
    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', () => {
            document.querySelectorAll('.dup-classroom-check').forEach(cb => cb.checked = true);
            if (checkAll) checkAll.checked = true;
            updateSelectedCount();
        });
    }

    // Tombol teks "Batalkan Semua"
    if (btnDeselectAll) {
        btnDeselectAll.addEventListener('click', () => {
            document.querySelectorAll('.dup-classroom-check').forEach(cb => cb.checked = false);
            if (checkAll) checkAll.checked = false;
            updateSelectedCount();
        });
    }

    // Reset modal saat ditutup
    const modal = document.getElementById('duplicateModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', () => {
            sourceSelect.value = '';
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">Pilih tahun ajaran sumber untuk melihat daftar rombel.</td></tr>`;
            listWrap.style.display = 'none';
            const submit = document.getElementById('dup_submit_btn');
            if (submit) submit.disabled = true;
        });
    }
}

