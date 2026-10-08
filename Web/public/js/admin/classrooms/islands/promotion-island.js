/**
 * Promotion Island
 * Mengelola alur interaktif Kenaikan Kelas & Kelulusan Massal:
 * - Pergantian jenis aksi (Kenaikan vs Kelulusan)
 * - Penyaringan kelas tujuan sesuai aturan kenaikan bertahap
 * - Pengambilan daftar siswa via AJAX (server mengirim HTML baris tabel)
 * - Seleksi checkbox yang menghormati filter pencarian
 * - Modal konfirmasi protektif sebelum eksekusi massal
 */
export function initPromotionIsland() {
    const sourceClassSelect = document.getElementById('source_classroom_id');
    const targetClassSelect = document.getElementById('target_classroom_id');
    const targetContainer = document.getElementById('targetClassContainer');
    const actionPromote = document.getElementById('actionPromote');
    const actionGraduate = document.getElementById('actionGraduate');
    const targetCapacityHint = document.getElementById('targetCapacityHint');
    const targetRuleHint = document.getElementById('targetRuleHint');

    const loadingIndicator = document.getElementById('loadingIndicator');
    const emptySelectClass = document.getElementById('emptySelectClass');
    const emptyNoStudents = document.getElementById('emptyNoStudents');
    const tableContainer = document.getElementById('tableContainer');
    const studentTableBody = document.getElementById('studentTableBody');
    const masterCheckbox = document.getElementById('masterCheckbox');

    const selectedBadge = document.getElementById('selectedBadge');
    const selectionSummaryText = document.getElementById('selectionSummaryText');
    const btnOpenConfirmModal = document.getElementById('btnOpenConfirmModal');
    const btnSelectAll = document.getElementById('btnSelectAll');
    const btnDeselectAll = document.getElementById('btnDeselectAll');
    const searchInput = document.getElementById('studentSearchInput');

    const modalEl = document.getElementById('confirmModal');
    const modalSourceClass = document.getElementById('modalSourceClass');
    const modalActionType = document.getElementById('modalActionType');
    const modalTargetClass = document.getElementById('modalTargetClass');
    const modalStudentCount = document.getElementById('modalStudentCount');

    if (!sourceClassSelect || !actionPromote || !actionGraduate) return;

    const baseUrl = sourceClassSelect.dataset.studentsBaseUrl || '/admin/classrooms';
    const NEXT_LEVEL = { '10': '11', '11': '12' };
    const defaultCapacityHint = targetCapacityHint ? targetCapacityHint.textContent.trim() : '';
    const emptyStateDefaults = {
        title: emptyNoStudents?.querySelector('h6')?.textContent.trim() ?? '',
        text: emptyNoStudents?.querySelector('p')?.textContent.trim() ?? '',
    };

    // ── Query pembantu ──────────────────────────────────────────────────────
    function selectedSource() {
        return sourceClassSelect.options[sourceClassSelect.selectedIndex];
    }

    function selectedTarget() {
        return targetClassSelect ? targetClassSelect.options[targetClassSelect.selectedIndex] : null;
    }

    function isGraduating() {
        return actionGraduate.checked;
    }

    /** Baris yang sedang tampil (menghormati filter pencarian). */
    function visibleRows() {
        return Array.from(document.querySelectorAll('.student-row'))
            .filter(row => row.style.display !== 'none');
    }

    function visibleCheckboxes() {
        return visibleRows()
            .map(row => row.querySelector('.student-checkbox'))
            .filter(Boolean);
    }

    function checkedCount() {
        return document.querySelectorAll('.student-checkbox:checked').length;
    }

    // ── Aturan kenaikan kelas ───────────────────────────────────────────────
    // Cerminan dari Classroom::promotionBlockReason agar admin tidak perlu
    // menebak; server tetap menjadi penentu akhir.
    function promotionBlockReason(option) {
        const source = selectedSource();
        if (!source || !source.value || !option || !option.value) return null;

        const nextLevel = NEXT_LEVEL[source.dataset.level];
        if (!nextLevel) {
            return 'Rombel tingkat XII tidak dapat dinaikkan. Gunakan aksi Kelulusan.';
        }

        if (option.dataset.level !== nextLevel) {
            return `Kenaikan kelas harus bertahap satu tingkat. Tingkat ${source.dataset.level} hanya dapat naik ke tingkat ${nextLevel}.`;
        }

        const sourceMajor = source.dataset.major || '';
        const targetMajor = option.dataset.major || '';
        if (sourceMajor !== '' && sourceMajor !== targetMajor) {
            return `Jurusan tidak boleh berubah. ${source.dataset.name} berjurusan ${sourceMajor}.`;
        }

        if (option.dataset.year <= source.dataset.year) {
            return `Tahun ajaran kelas tujuan harus lebih baru dari ${source.dataset.year}.`;
        }

        return null;
    }

    /** Nonaktifkan kelas tujuan yang tidak memenuhi aturan kenaikan kelas. */
    function syncTargetOptions() {
        if (!targetClassSelect) return;

        const sourceId = selectedSource()?.value ?? '';

        Array.from(targetClassSelect.options).forEach(option => {
            if (!option.value) return;

            option.disabled = option.value === sourceId || promotionBlockReason(option) !== null;
        });

        if (targetClassSelect.selectedOptions[0]?.disabled) {
            targetClassSelect.value = '';
        }
    }

    function updateTargetRuleHint() {
        if (!targetRuleHint) return;

        const source = selectedSource();

        const hide = () => {
            targetRuleHint.classList.add('d-none');
            targetRuleHint.textContent = '';
        };

        if (isGraduating() || !source?.value) {
            hide();

            return;
        }

        const nextLevel = NEXT_LEVEL[source.dataset.level];
        if (!nextLevel) {
            targetRuleHint.textContent = 'Rombel tingkat XII tidak dapat dinaikkan. Gunakan aksi Kelulusan di atas.';
            targetRuleHint.classList.remove('d-none');

            return;
        }

        const hasAvailableTarget = Array.from(targetClassSelect?.options ?? [])
            .some(option => option.value && !option.disabled);

        if (!hasAvailableTarget) {
            targetRuleHint.textContent = `Belum ada rombel tingkat ${nextLevel} dengan jurusan yang sama dan tahun ajaran lebih baru. Tambahkan rombelnya lebih dahulu.`;
            targetRuleHint.classList.remove('d-none');

            return;
        }

        hide();
    }

    function updateTargetCapacityHint() {
        if (!targetCapacityHint) return;

        if (isGraduating()) {
            targetCapacityHint.classList.remove('text-danger');
            targetCapacityHint.classList.add('text-muted');
            targetCapacityHint.textContent = 'Proses kelulusan tidak terikat kapasitas rombel.';

            return;
        }

        const option = selectedTarget();
        if (!option || !option.value) {
            targetCapacityHint.classList.remove('text-danger');
            targetCapacityHint.classList.add('text-muted');
            targetCapacityHint.textContent = defaultCapacityHint;

            return;
        }

        const remaining = Number(option.dataset.remaining ?? 0);
        const capacity = Number(option.dataset.capacity ?? 0);
        const selected = checkedCount();
        const isOverloaded = remaining < selected;

        targetCapacityHint.textContent = isOverloaded
            ? `Sisa kursi ${option.dataset.name} hanya ${remaining}, sedangkan ${selected} siswa dipilih.`
            : `Sisa kursi ${option.dataset.name}: ${remaining} dari ${capacity}.`;
        targetCapacityHint.classList.toggle('text-danger', isOverloaded);
        targetCapacityHint.classList.toggle('text-muted', !isOverloaded);
    }

    function updateTargetLabels(text) {
        document.querySelectorAll('.target-label').forEach(el => {
            el.textContent = text;
        });
    }

    function targetLabelFromSelect() {
        const option = selectedTarget();

        return option && option.value ? '➔ ' + option.text : 'Mengikuti Kelas Tujuan';
    }

    function showEmptyStudents(title, text) {
        if (!emptyNoStudents) return;

        const titleEl = emptyNoStudents.querySelector('h6');
        const textEl = emptyNoStudents.querySelector('p');

        if (titleEl) titleEl.textContent = title ?? emptyStateDefaults.title;
        if (textEl) textEl.textContent = text ?? emptyStateDefaults.text;

        emptyNoStudents.classList.remove('d-none');
    }

    // ── Toggle action (Kenaikan vs Kelulusan) ───────────────────────────────
    function handleActionChange() {
        const graduating = isGraduating();

        if (targetContainer) targetContainer.style.display = graduating ? 'none' : 'block';

        if (targetClassSelect) {
            if (graduating) {
                targetClassSelect.removeAttribute('required');
                targetClassSelect.value = '';
            } else {
                targetClassSelect.setAttribute('required', 'required');
            }
        }

        if (btnOpenConfirmModal) {
            btnOpenConfirmModal.innerHTML = graduating
                ? '<i class="bi bi-mortarboard me-1"></i>Proses Kelulusan Siswa'
                : '<i class="bi bi-arrow-repeat me-1"></i>Proses Kenaikan Kelas';
        }

        syncTargetOptions();
        updateTargetRuleHint();
        updateTargetLabels(graduating ? 'Status Baru: Alumni (Lulus)' : targetLabelFromSelect());
        updateCounters();
    }

    actionPromote.addEventListener('change', handleActionChange);
    actionGraduate.addEventListener('change', handleActionChange);

    if (targetClassSelect) {
        targetClassSelect.addEventListener('change', function () {
            if (!isGraduating()) updateTargetLabels(targetLabelFromSelect());
            updateCounters();
        });
    }

    // ── Pemuatan siswa saat rombel asal berubah ─────────────────────────────
    async function loadStudents(classId) {
        if (loadingIndicator) loadingIndicator.classList.remove('d-none');
        if (emptySelectClass) emptySelectClass.classList.add('d-none');
        if (emptyNoStudents) emptyNoStudents.classList.add('d-none');
        if (tableContainer) tableContainer.classList.add('d-none');

        try {
            const response = await fetch(`${baseUrl}/${classId}/students`, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) throw new Error(`Gagal memuat siswa (HTTP ${response.status})`);

            const html = (await response.text()).trim();

            if (studentTableBody) studentTableBody.innerHTML = html;

            if (html === '') {
                showEmptyStudents();
            } else if (tableContainer) {
                tableContainer.classList.remove('d-none');
            }
        } catch (error) {
            console.error('Failed to load students:', error);
            if (studentTableBody) studentTableBody.innerHTML = '';
            showEmptyStudents('Gagal Memuat Data Siswa', 'Terjadi masalah saat mengambil daftar siswa. Silakan pilih ulang rombel asal.');
        } finally {
            if (loadingIndicator) loadingIndicator.classList.add('d-none');
            attachCheckboxListeners();
            updateTargetLabels(isGraduating() ? 'Status Baru: Alumni (Lulus)' : targetLabelFromSelect());
            updateCounters();
        }
    }

    sourceClassSelect.addEventListener('change', function () {
        const classId = this.value;

        if (targetClassSelect) targetClassSelect.value = '';

        syncTargetOptions();
        updateTargetRuleHint();

        if (!classId) {
            if (tableContainer) tableContainer.classList.add('d-none');
            if (emptyNoStudents) emptyNoStudents.classList.add('d-none');
            if (emptySelectClass) emptySelectClass.classList.remove('d-none');
            if (studentTableBody) studentTableBody.innerHTML = '';
            updateCounters();

            return;
        }

        loadStudents(classId);
    });

    // ── Seleksi siswa ───────────────────────────────────────────────────────
    function attachCheckboxListeners() {
        document.querySelectorAll('.student-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => updateCounters());
        });
    }

    function updateMasterCheckbox() {
        if (!masterCheckbox) return;

        const boxes = visibleCheckboxes();
        masterCheckbox.checked = boxes.length > 0 && boxes.every(box => box.checked);
    }

    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function () {
            const isChecked = this.checked;
            visibleCheckboxes().forEach(box => {
                box.checked = isChecked;
            });
            updateCounters();
        });
    }

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function () {
            visibleCheckboxes().forEach(box => {
                box.checked = true;
            });
            updateCounters();
        });
    }

    if (btnDeselectAll) {
        btnDeselectAll.addEventListener('click', function () {
            visibleCheckboxes().forEach(box => {
                box.checked = false;
            });
            updateCounters();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();

            document.querySelectorAll('.student-row').forEach(row => {
                const name = row.dataset.name || '';
                const nis = row.dataset.nis || '';
                row.style.display = (name.includes(term) || nis.includes(term)) ? '' : 'none';
            });

            updateCounters();
        });
    }

    // ── Penghitung & status tombol ──────────────────────────────────────────
    function updateCounters() {
        const total = document.querySelectorAll('.student-checkbox').length;
        const checked = checkedCount();

        if (selectedBadge) selectedBadge.textContent = `${checked} Siswa Terpilih`;

        if (selectionSummaryText) {
            if (total > 0) {
                const visible = visibleCheckboxes().length;
                const filteredNote = visible === total ? '' : ` (${visible} tampil setelah difilter)`;
                selectionSummaryText.innerHTML = `<strong>${checked}</strong> dari <strong>${total}</strong> siswa terpilih untuk diproses${filteredNote}.`;
            } else {
                selectionSummaryText.textContent = 'Silakan tentukan rombel dan centang siswa yang berhak diproses.';
            }
        }

        updateMasterCheckbox();
        updateTargetCapacityHint();
        updateButtonState();
    }

    function updateButtonState() {
        if (!btnOpenConfirmModal) return;

        const source = selectedSource();
        const target = selectedTarget();
        const checked = checkedCount();

        let isValid = Boolean(source?.value) && checked > 0;

        if (isGraduating()) {
            isValid = isValid && source.dataset.level === '12';
        } else {
            isValid = isValid
                && Boolean(target?.value)
                && target.value !== source.value
                && promotionBlockReason(target) === null
                && Number(target.dataset.remaining ?? 0) >= checked;
        }

        btnOpenConfirmModal.disabled = !isValid;
    }

    // ── Modal konfirmasi ────────────────────────────────────────────────────
    if (btnOpenConfirmModal && modalEl) {
        btnOpenConfirmModal.addEventListener('click', function () {
            const source = selectedSource();
            const graduating = isGraduating();
            const target = selectedTarget();

            if (modalSourceClass) modalSourceClass.textContent = source ? source.text : '-';
            if (modalActionType) modalActionType.textContent = graduating ? 'Kelulusan Siswa (Alumni)' : 'Kenaikan Kelas Baru';
            if (modalTargetClass) modalTargetClass.textContent = graduating ? 'Alumni (Status: Graduated)' : (target ? target.text : '-');
            if (modalStudentCount) modalStudentCount.textContent = `${checkedCount()} Siswa`;

            if (window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    }

    // Inisialisasi awal
    attachCheckboxListeners();
    handleActionChange();
}
