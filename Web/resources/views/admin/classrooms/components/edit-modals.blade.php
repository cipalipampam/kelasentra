{{-- Modals Edit Rombel --}}
@foreach($classrooms as $classroom)
    <div class="modal fade" id="editModal{{ $classroom->id }}" tabindex="-1" aria-labelledby="editModalLabel{{ $classroom->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <form action="{{ route('admin.classrooms.update', $classroom->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header border-0 pb-0 pt-4 px-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb;">
                                <i class="bi bi-pencil-square fs-5"></i>
                            </div>
                            <div>
                                <h6 class="modal-title fw-bold text-dark mb-0" id="editModalLabel{{ $classroom->id }}">
                                    Edit Rombel — {{ $classroom->name }}
                                </h6>
                                <p class="text-muted small mb-0">Perbarui informasi rombongan belajar</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3 px-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label text-dark fw-semibold small">
                                    Nama Rombel <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="name" class="form-control"
                                       placeholder="Contoh: X IPA 1" value="{{ old('name', $classroom->name) }}" required>
                                <div class="form-text small text-muted">Nama lengkap rombel, misal: X IPA 1, XI IPS 2</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-dark fw-semibold small">
                                    Tingkat <span class="text-danger">*</span>
                                </label>
                                <select name="level" class="form-select" data-level-select required>
                                    <option value="10" {{ old('level', $classroom->level) == 10 ? 'selected' : '' }}>Kelas X (Sepuluh)</option>
                                    <option value="11" {{ old('level', $classroom->level) == 11 ? 'selected' : '' }}>Kelas XI (Sebelas)</option>
                                    <option value="12" {{ old('level', $classroom->level) == 12 ? 'selected' : '' }}>Kelas XII (Dua Belas)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-dark fw-semibold small">
                                    Jurusan <span class="text-danger" data-major-required-asterisk>*</span>
                                </label>
                                <select name="major" class="form-select" data-major-select>
                                    <option value="">— Tanpa Jurusan —</option>
                                    @foreach($majors as $major)
                                        <option value="{{ $major }}" {{ old('major', $classroom->major) === $major ? 'selected' : '' }}>{{ $major }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text small text-muted">Wajib untuk tingkat XI dan XII.</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-dark fw-semibold small">
                                    Nomor / Sesi Rombel <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="section" class="form-control"
                                       placeholder="Contoh: 1, 2, A, B" value="{{ old('section', $classroom->section) }}" required>
                            </div>

                            @php
                                // Tahun ajaran rombel ikut ditampilkan walau sudah tidak operable,
                                // meskipun nilainya tidak boleh diubah lagi.
                                $editYears = $academicYears
                                    ->concat([$classroom->academicYear])
                                    ->filter()
                                    ->unique('id')
                                    ->sortByDesc('start_year')
                                    ->values();
                            @endphp
                            <div class="col-md-6">
                                <label class="form-label text-dark fw-semibold small">
                                    Tahun Ajaran <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" data-academic-year-select disabled>
                                    @foreach($editYears as $year)
                                        <option value="{{ $year->id }}"
                                                data-year-name="{{ $year->name }}"
                                                @selected((string) $classroom->academic_year_id === (string) $year->id)>
                                            {{ $year->name }}{{ $year->isOperable() ? '' : ' ('.$year->statusLabel().')' }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="academic_year_id" value="{{ $classroom->academic_year_id }}">
                                <div class="form-text small text-muted">Tahun ajaran rombel tidak dapat diubah setelah dibuat.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-dark fw-semibold small">
                                    Wali Kelas
                                </label>
                                <select name="homeroom_teacher_id" class="form-select" data-homeroom-teacher-select>
                                    <option value="">— Belum Ditentukan —</option>
                                    @foreach($teachers as $teacher)
                                        @php
                                            // Abaikan penugasan rombel ini sendiri agar guru yang sedang
                                            // menjabat tidak ikut ter-disable saat mengedit.
                                            $assignedYears = collect($homeroomAssignments[$teacher->id] ?? [])
                                                ->reject(fn ($yearId) => (int) $yearId === (int) $classroom->academic_year_id
                                                    && (int) $classroom->homeroom_teacher_id === (int) $teacher->id)
                                                ->values();
                                        @endphp
                                        <option value="{{ $teacher->id }}"
                                                data-assigned-years="{{ json_encode($assignedYears->all()) }}"
                                            {{ old('homeroom_teacher_id', $classroom->homeroom_teacher_id) == $teacher->id ? 'selected' : '' }}>
                                            {{ $teacher->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text small text-muted">Hanya guru berstatus aktif.</div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" name="is_active"
                                           id="isActiveEdit{{ $classroom->id }}"
                                           {{ old('is_active', $classroom->is_active) ? 'checked' : '' }} value="1">
                                    <label class="form-check-label text-dark small fw-medium"
                                           for="isActiveEdit{{ $classroom->id }}">
                                        Rombel Aktif (tampil dan bisa digunakan di sistem)
                                    </label>
                                </div>
                                <div class="form-text small text-muted">
                                    Menonaktifkan rombel akan melepas wali kelasnya agar bisa dirotasi ke rombel lain.
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

