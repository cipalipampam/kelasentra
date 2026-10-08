/**
 * Promotion Island
 * Mengelola alur interaktif Kenaikan Kelas & Kelulusan Massal:
 * - Pergantian jenis aksi (Kenaikan vs Kelulusan)
 * - Pengambilan dinamis data siswa via AJAX saat rombel asal dipilih
 * - Seleksi checkbox (master, select all, deselect all)
 * - Pencarian instan client-side pada tabel siswa
 * - Modal konfirmasi protektif sebelum eksekusi massal
 */
export function initPromotionIsland() {
    const sourceClassSelect = document.getElementById('source_classroom_id');
    const targetClassSelect = document.getElementById('target_classroom_id');
    const targetContainer = document.getElementById('targetClassContainer');
    const actionPromote = document.getElementById('actionPromote');
    const actionGraduate = document.getElementById('actionGraduate');
    const targetCapacityHint = document.getElementById('targetCapacityHint');

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

    // Elemen modal konfirmasi
    const modalEl = document.getElementById('confirmModal');
    const modalSourceClass = document.getElementById('modalSourceClass');
    const modalActionType = document.getElementById('modalActionType');
    const modalTargetClass = document.getElementById('modalTargetClass');
    const modalStudentCount = document.getElementById('modalStudentCount');

    if (!sourceClassSelect || !actionPromote || !actionGraduate) return;

    const baseUrl = sourceClassSelect.dataset.studentsBaseUrl || '/admin/classrooms';

    function updateTargetLabels(text) {
        document.querySelectorAll('.target-label').forEach(el => {
            el.textContent = text;
        });
    }

    function updateTargetLabelsFromSelect() {
        if (!targetClassSelect) return;
        const selectedOpt = targetClassSelect.options[targetClassSelect.selectedIndex];
        const text = selectedOpt && selectedOpt.value ? '➔ ' + selectedOpt.text : 'Mengikuti Kelas Tujuan';
        updateTargetLabels(text);
    }

    // Tampilkan sisa kuota rombel tujuan agar admin sadar batas 30 siswa.
    function updateTargetCapacityHint() {
        if (!targetCapacityHint || !targetClassSelect) return;

        const selectedOpt = targetClassSelect.options[targetClassSelect.selectedIndex];
        const isGraduating = actionGraduate.checked;

        if (!selectedOpt || !selectedOpt.value || isGraduating) {
            targetCapacityHint.classList.remove('text-danger');
            targetCapacityHint.classList.add('text-muted');
            targetCapacityHint.textContent = 'Kapasitas maksimal 30 siswa per rombel.';
            return;
        }

        const remaining = Number(selectedOpt.dataset.remaining ?? 0);
        const capacity = Number(selectedOpt.dataset.capacity ?? 30);
        const name = selectedOpt.dataset.name || selectedOpt.text;

        targetCapacityHint.textContent = `Sisa kursi ${name}: ${remaining} dari ${capacity}.`;
        targetCapacityHint.classList.toggle('text-danger', remaining === 0);
        targetCapacityHint.classList.toggle('text-muted', remaining > 0);
    }

    // ── Toggle action (Promote vs Graduate) ──────────────────────────────────
    function handleActionChange() {
        if (actionGraduate.checked) {
            if (targetContainer) targetContainer.style.display = 'none';
            if (targetClassSelect) {
                targetClassSelect.removeAttribute('required');
                targetClassSelect.value = '';
            }
            if (btnOpenConfirmModal) {
                btnOpenConfirmModal.innerHTML = '<i class="bi bi-mortarboard me-1"></i>Proses Kelulusan Siswa';
            }
            updateTargetLabels('Status Baru: Alumni (Lulus)');
        } else {
            if (targetContainer) targetContainer.style.display = 'block';
            if (targetClassSelect) targetClassSelect.setAttribute('required', 'required');
            if (btnOpenConfirmModal) {
                btnOpenConfirmModal.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Proses Kenaikan Kelas';
            }
            updateTargetLabelsFromSelect();
        }
        updateTargetCapacityHint();
        updateButtonState();
    }

    actionPromote.addEventListener('change', handleActionChange);
    actionGraduate.addEventListener('change', handleActionChange);

    if (targetClassSelect) {
        targetClassSelect.addEventListener('change', function () {
            if (actionPromote.checked) {
                updateTargetLabelsFromSelect();
            }
            updateTargetCapacityHint();
            updateButtonState();
        });
    }

    // ── Load students on source class change ────────────────────────────────
    sourceClassSelect.addEventListener('change', function () {
        const classId = this.value;
        if (!classId) {
            if (tableContainer) tableContainer.classList.add('d-none');
            if (emptyNoStudents) emptyNoStudents.classList.add('d-none');
            if (emptySelectClass) emptySelectClass.classList.remove('d-none');
            if (studentTableBody) studentTableBody.innerHTML = '';
            updateCounters();
            return;
        }

        // Disable options in target class that match source class
        if (targetClassSelect) {
            Array.from(targetClassSelect.options).forEach(opt => {
                opt.disabled = (opt.value === classId);
            });
            if (targetClassSelect.value === classId) {
                targetClassSelect.value = '';
            }
        }

        // Fetch via AJAX
        if (loadingIndicator) loadingIndicator.classList.remove('d-none');
        if (emptySelectClass) emptySelectClass.classList.add('d-none');
        if (emptyNoStudents) emptyNoStudents.classList.add('d-none');
        if (tableContainer) tableContainer.classList.add('d-none');

        fetch(`${baseUrl}/${classId}/students`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (loadingIndicator) loadingIndicator.classList.add('d-none');
                if (data.success && data.students && data.students.length > 0) {
                    renderStudents(data.students);
                    if (tableContainer) tableContainer.classList.remove('d-none');
                } else {
                    if (studentTableBody) studentTableBody.innerHTML = '';
                    if (emptyNoStudents) emptyNoStudents.classList.remove('d-none');
                }
                updateCounters();
            })
            .catch(err => {
                console.error('Failed to load students:', err);
                if (loadingIndicator) loadingIndicator.classList.add('d-none');
                alert('Gagal memuat data siswa kelas. Silakan coba lagi.');
            });
    });

    function renderStudents(students) {
        if (!studentTableBody) return;
        studentTableBody.innerHTML = '';
        const targetText = actionGraduate.checked
            ? 'Status Baru: Alumni (Lulus)'
            : (targetClassSelect && targetClassSelect.selectedIndex > 0 ? '➔ ' + targetClassSelect.options[targetClassSelect.selectedIndex].text : 'Mengikuti Kelas Tujuan');

        students.forEach((student, index) => {
            const initial = (student.name || 'S').charAt(0).toUpperCase();
            const genderLabel = student.gender === 'P' ? 'Perempuan' : 'Laki-laki';
            const genderBadge = student.gender === 'P' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary';

            const tr = document.createElement('tr');
            tr.className = 'student-row';
            tr.dataset.name = (student.name || '').toLowerCase();
            tr.dataset.nis = student.nis || '';

            tr.innerHTML = `
                <td class="text-center">
                    <input type="checkbox" name="student_ids[]" value="${student.id}" class="form-check-input student-checkbox" checked>
                </td>
                <td class="text-muted small">${index + 1}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-placeholder" style="width: 28px; height: 28px; font-size: 0.65rem;">
                            ${initial}
                        </div>
                        <div>
                            <span class="fw-semibold text-dark d-block student-name" style="font-size: 0.88rem;">${student.name}</span>
                        </div>
                    </div>
                </td>
                <td class="small">
                    <span class="fw-medium text-dark">${student.nis}</span>
                    <span class="text-muted d-block" style="font-size: 0.72rem;">NISN: ${student.nisn}</span>
                </td>
                <td class="small">
                    <span class="badge ${genderBadge}" style="font-size: 0.72rem;">
                        ${genderLabel}
                    </span>
                </td>
                <td>
                    <span class="badge-status badge-present" style="font-size: 0.72rem;">
                        <i class="bi bi-check-circle me-1"></i>Aktif
                    </span>
                </td>
                <td class="target-indicator small text-muted">
                    <span class="target-label">${targetText}</span>
                </td>
            `;
            studentTableBody.appendChild(tr);
        });

        attachCheckboxListeners();
        if (masterCheckbox) masterCheckbox.checked = true;
    }

    function attachCheckboxListeners() {
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', function () {
                updateMasterCheckbox();
                updateCounters();
            });
        });
    }

    function updateMasterCheckbox() {
        if (!masterCheckbox) return;
        const checkboxes = document.querySelectorAll('.student-checkbox');
        const checked = document.querySelectorAll('.student-checkbox:checked');
        masterCheckbox.checked = (checkboxes.length > 0 && checkboxes.length === checked.length);
    }

    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function () {
            const isChecked = this.checked;
            document.querySelectorAll('.student-checkbox').forEach(cb => {
                const row = cb.closest('tr');
                if (row && row.style.display !== 'none') {
                    cb.checked = isChecked;
                }
            });
            updateCounters();
        });
    }

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function () {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = true);
            if (masterCheckbox) masterCheckbox.checked = true;
            updateCounters();
        });
    }

    if (btnDeselectAll) {
        btnDeselectAll.addEventListener('click', function () {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
            if (masterCheckbox) masterCheckbox.checked = false;
            updateCounters();
        });
    }

    // ── Live Client-side Search in Table ─────────────────────────────────────
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.student-row').forEach(row => {
                const name = row.dataset.name || '';
                const nis = row.dataset.nis || '';
                if (name.includes(term) || nis.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // ── Counters and Button State ───────────────────────────────────────────
    function updateCounters() {
        const total = document.querySelectorAll('.student-checkbox').length;
        const checked = document.querySelectorAll('.student-checkbox:checked').length;

        if (selectedBadge) selectedBadge.textContent = `${checked} Siswa Terpilih`;
        if (selectionSummaryText) {
            if (total > 0) {
                selectionSummaryText.innerHTML = `<strong>${checked}</strong> dari <strong>${total}</strong> siswa terpilih untuk diproses.`;
            } else {
                selectionSummaryText.textContent = 'Silakan tentukan rombel dan centang siswa yang berhak naik kelas.';
            }
        }

        updateButtonState();
    }

    function updateButtonState() {
        if (!btnOpenConfirmModal) return;
        const checked = document.querySelectorAll('.student-checkbox:checked').length;
        const hasSource = sourceClassSelect.value !== '';
        const isGraduate = actionGraduate.checked;
        const hasTarget = isGraduate || (targetClassSelect && targetClassSelect.value !== '' && targetClassSelect.value !== sourceClassSelect.value);

        btnOpenConfirmModal.disabled = !(hasSource && hasTarget && checked > 0);
    }

    // ── Open Confirmation Modal ─────────────────────────────────────────────
    if (btnOpenConfirmModal && modalEl) {
        btnOpenConfirmModal.addEventListener('click', function () {
            const sourceOpt = sourceClassSelect.options[sourceClassSelect.selectedIndex];
            const isGraduate = actionGraduate.checked;
            const targetOpt = targetClassSelect ? targetClassSelect.options[targetClassSelect.selectedIndex] : null;
            const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;

            if (modalSourceClass) modalSourceClass.textContent = sourceOpt ? sourceOpt.text : '-';
            if (modalActionType) modalActionType.textContent = isGraduate ? 'Kelulusan Siswa (Alumni)' : 'Kenaikan Kelas Baru';
            if (modalTargetClass) modalTargetClass.textContent = isGraduate ? 'Alumni (Status: Graduated)' : (targetOpt ? targetOpt.text : '-');
            if (modalStudentCount) modalStudentCount.textContent = `${checkedCount} Siswa`;

            if (window.bootstrap?.Modal) {
                const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        });
    }

    // Inisialisasi awal
    attachCheckboxListeners();
    handleActionChange();
    updateCounters();
}

