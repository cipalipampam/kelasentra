{{-- Modal Tambah Rombel Baru --}}
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form action="{{ route('admin.classrooms.store') }}" method="POST" id="createClassroomForm">
                @csrf
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb;">
                            <i class="bi bi-diagram-3-fill fs-5"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-dark mb-0" id="createModalLabel">Tambah Rombel Baru</h6>
                            <p class="text-muted small mb-0">Daftarkan rombongan belajar baru ke dalam sistem</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3 px-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label for="create_name" class="form-label text-dark fw-semibold small mb-0">
                                    Nama Rombel <span class="text-danger">*</span>
                                </label>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-primary small" id="btn-suggest-name" style="font-size: 0.75rem; display: none;">
                                    <i class="bi bi-magic me-1"></i>Gunakan Saran Otomatis
                                </button>
                            </div>
                            <input type="text" name="name" id="create_name" class="form-control"
                                   placeholder="Contoh: X IPA 1, XI TKJ 2" value="{{ old('name') }}" required>
                            <div class="form-text small text-muted">Format standar: [Tingkat Romawi] [Jurusan] [Nomor Rombel]</div>
                        </div>

                        <div class="col-md-4">
                            <label for="create_level" class="form-label text-dark fw-semibold small">
                                Tingkat <span class="text-danger">*</span>
                            </label>
                            <select name="level" id="create_level" class="form-select" required>
                                <option value="">— Pilih Tingkat —</option>
                                <option value="10" {{ old('level') == '10' ? 'selected' : '' }}>Kelas X (Sepuluh)</option>
                                <option value="11" {{ old('level') == '11' ? 'selected' : '' }}>Kelas XI (Sebelas)</option>
                                <option value="12" {{ old('level') == '12' ? 'selected' : '' }}>Kelas XII (Dua Belas)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="create_major" class="form-label text-dark fw-semibold small">
                                Jurusan <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="major" id="create_major" class="form-control"
                                   placeholder="Contoh: IPA, IPS, TKJ, RPL" value="{{ old('major') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label for="create_section" class="form-label text-dark fw-semibold small">
                                Nomor / Sesi Rombel <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="section" id="create_section" class="form-control"
                                   placeholder="Contoh: 1, 2, A, B" value="{{ old('section') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="create_academic_year" class="form-label text-dark fw-semibold small">
                                Tahun Ajaran <span class="text-danger">*</span>
                            </label>
                            @php
                                $defaultYear = date('Y') . '/' . (date('Y') + 1);
                            @endphp
                            <input type="text" name="academic_year" id="create_academic_year" class="form-control"
                                   placeholder="Contoh: {{ $defaultYear }}" value="{{ old('academic_year', $defaultYear) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="create_homeroom_teacher" class="form-label text-dark fw-semibold small">
                                Wali Kelas
                            </label>
                            <select name="homeroom_teacher_id" id="create_homeroom_teacher" class="form-select">
                                <option value="">— Belum Ditentukan —</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" {{ old('homeroom_teacher_id') == $teacher->id ? 'selected' : '' }}>
                                        {{ $teacher->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" name="is_active"
                                       id="isActiveCreate" checked value="1">
                                <label class="form-check-label text-dark small fw-medium" for="isActiveCreate">
                                    Rombel Aktif (tampil dan bisa digunakan untuk pencatatan presensi siswa)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-sm btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold shadow-xs">
                        <i class="bi bi-plus-lg me-1"></i>Simpan Rombel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

