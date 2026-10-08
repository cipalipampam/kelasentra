{{-- Modal Tambah Tahun Ajaran --}}
@php
    // Tahun pertama kali dibuat sebaiknya langsung berstatus aktif.
    $defaultCreateStatus = $stats['active'] === 0
        ? \App\Models\AcademicYear::STATUS_ACTIVE
        : \App\Models\AcademicYear::STATUS_UPCOMING;
@endphp
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createAcademicYearModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form action="{{ route('admin.academic-years.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb;">
                            <i class="bi bi-calendar-range-fill fs-5"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-dark mb-0" id="createAcademicYearModalLabel">Tambah Tahun Ajaran</h6>
                            <p class="text-muted small mb-0">Daftarkan periode tahun ajaran baru</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3 px-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="create_year_name" class="form-label text-dark fw-semibold small">
                                Tahun Ajaran <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" id="create_year_name" class="form-control"
                                   placeholder="Contoh: 2026/2027" value="{{ old('name') }}"
                                   data-year-name-input required>
                            <div class="form-text small text-muted">Format YYYY/YYYY dan harus berurutan.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="create_year_start" class="form-label text-dark fw-semibold small">Tanggal Mulai</label>
                            <input type="date" name="start_date" id="create_year_start" class="form-control"
                                   value="{{ old('start_date') }}" data-year-start-input>
                        </div>

                        <div class="col-md-6">
                            <label for="create_year_end" class="form-label text-dark fw-semibold small">Tanggal Selesai</label>
                            <input type="date" name="end_date" id="create_year_end" class="form-control"
                                   value="{{ old('end_date') }}" data-year-end-input>
                        </div>

                        <div class="col-12">
                            <label for="create_year_status" class="form-label text-dark fw-semibold small">
                                Status <span class="text-danger">*</span>
                            </label>
                            <select name="status" id="create_year_status" class="form-select" required>
                                @foreach($statuses as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}" {{ old('status', $defaultCreateStatus) === $statusValue ? 'selected' : '' }}>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small text-muted">
                                Hanya tahun ajaran <strong>Aktif</strong> atau <strong>Akan Datang</strong> yang dapat dipakai rombel baru.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-sm btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold shadow-xs">
                        <i class="bi bi-plus-lg me-1"></i>Simpan Tahun Ajaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
