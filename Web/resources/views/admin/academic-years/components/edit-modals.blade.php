{{-- Modals Edit Tahun Ajaran --}}
@foreach($academicYears as $academicYear)
    <div class="modal fade" id="editAcademicYearModal{{ $academicYear->id }}" tabindex="-1"
         aria-labelledby="editAcademicYearModalLabel{{ $academicYear->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <form action="{{ route('admin.academic-years.update', $academicYear->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header border-0 pb-0 pt-4 px-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb;">
                                <i class="bi bi-pencil-square fs-5"></i>
                            </div>
                            <div>
                                <h6 class="modal-title fw-bold text-dark mb-0" id="editAcademicYearModalLabel{{ $academicYear->id }}">
                                    Edit Tahun Ajaran — {{ $academicYear->name }}
                                </h6>
                                <p class="text-muted small mb-0">Perbarui periode dan status tahun ajaran</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3 px-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label text-dark fw-semibold small">
                                    Tahun Ajaran <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="name" class="form-control"
                                       value="{{ old('name', $academicYear->name) }}"
                                       placeholder="Contoh: 2026/2027" data-year-name-input required>
                                <div class="form-text small text-muted">
                                    Mengubah nama akan memutus keterkaitan dengan rombel yang memakai nama lama.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-dark fw-semibold small">Tanggal Mulai</label>
                                <input type="date" name="start_date" class="form-control"
                                       value="{{ old('start_date', $academicYear->start_date?->format('Y-m-d')) }}"
                                       data-year-start-input>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-dark fw-semibold small">Tanggal Selesai</label>
                                <input type="date" name="end_date" class="form-control"
                                       value="{{ old('end_date', $academicYear->end_date?->format('Y-m-d')) }}"
                                       data-year-end-input>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-dark fw-semibold small">
                                    Status <span class="text-danger">*</span>
                                </label>
                                <select name="status" class="form-select" required>
                                    @foreach($statuses as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}" {{ old('status', $academicYear->status) === $statusValue ? 'selected' : '' }}>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text small text-muted">
                                    Menetapkan status <strong>Aktif</strong> akan mengarsipkan tahun ajaran aktif lainnya.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4">
                        <button type="button" class="btn btn-sm btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold shadow-xs">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
