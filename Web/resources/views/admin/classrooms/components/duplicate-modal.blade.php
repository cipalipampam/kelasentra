{{-- Modal Duplikasi Rombel Antar Tahun Ajaran --}}
<div class="modal fade" id="duplicateModal" tabindex="-1" aria-labelledby="duplicateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form action="{{ route('admin.classrooms.duplicate') }}" method="POST" id="duplicateClassroomForm">
                @csrf
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 44px; height: 44px; background: #f0fdf4; color: #16a34a;">
                            <i class="bi bi-copy fs-5"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-dark mb-0" id="duplicateModalLabel">Duplikasi Rombel Antar Tahun Ajaran</h6>
                            <p class="text-muted small mb-0">Salin struktur rombel dari tahun ajaran lain ke tahun ajaran tujuan</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body py-3 px-4">
                    {{-- Info --}}
                    <div class="alert alert-info border-0 py-2 px-3 mb-3 d-flex gap-2 align-items-start" style="font-size: 0.82rem; background: #eff6ff;">
                        <i class="bi bi-info-circle-fill text-primary mt-1 flex-shrink-0"></i>
                        <span>Hanya struktur rombel (nama, tingkat, jurusan, nomor sesi) yang disalin. <strong>Wali kelas dan data siswa tidak ikut</strong> — penetapan wali kelas dilakukan manual setelah duplikasi.</span>
                    </div>

                    <div class="row g-3">
                        {{-- Tahun Ajaran Sumber --}}
                        <div class="col-md-6">
                            <label for="dup_source_year" class="form-label text-dark fw-semibold small">
                                Salin Dari (Tahun Ajaran Sumber) <span class="text-danger">*</span>
                            </label>
                            <select name="source_academic_year_id" id="dup_source_year" class="form-select"
                                    data-dup-source required>
                                <option value="">— Pilih Tahun Ajaran —</option>
                                @foreach($allAcademicYears as $year)
                                    <option value="{{ $year->id }}"
                                            data-classrooms="{{ json_encode(
                                                $year->classrooms->map(fn ($c) => [
                                                    'id'      => $c->id,
                                                    'name'    => $c->name,
                                                    'level'   => $c->level,
                                                    'major'   => $c->major,
                                                    'section' => $c->section,
                                                ])->values()
                                            , JSON_UNESCAPED_SLASHES) }}">
                                        {{ $year->name }}
                                        @if($year->status === \App\Models\AcademicYear::STATUS_CURRENT)
                                            (Berjalan)
                                        @elseif($year->status === \App\Models\AcademicYear::STATUS_UPCOMING)
                                            (Akan Datang)
                                        @else
                                            (Selesai)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Tahun Ajaran Tujuan --}}
                        <div class="col-md-6">
                            <label for="dup_target_year" class="form-label text-dark fw-semibold small">
                                Salin Ke (Tahun Ajaran Tujuan) <span class="text-danger">*</span>
                            </label>
                            <select name="target_academic_year_id" id="dup_target_year" class="form-select"
                                    data-dup-target required>
                                <option value="">— Pilih Tahun Ajaran —</option>
                                @foreach($academicYears as $year)
                                    <option value="{{ $year->id }}">
                                        {{ $year->name }} — {{ $year->statusLabel() }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small text-muted">Hanya tahun ajaran yang masih aktif atau akan datang.</div>
                        </div>

                        {{-- Daftar Rombel Preview --}}
                        <div class="col-12" id="dup_classroom_list_wrap" style="display: none;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="form-label text-dark fw-semibold small mb-0">
                                    Pilih Rombel yang Akan Diduplikasi <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 small text-primary" id="dup_select_all">
                                        Pilih Semua
                                    </button>
                                    <span class="text-muted small">·</span>
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 small text-secondary" id="dup_deselect_all">
                                        Batalkan Semua
                                    </button>
                                </div>
                            </div>

                            <div class="border rounded-3 overflow-hidden" style="max-height: 280px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0 small">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 40px;" class="ps-3">
                                                <input type="checkbox" class="form-check-input" id="dup_check_all">
                                            </th>
                                            <th>Nama Rombel</th>
                                            <th style="width: 90px;">Tingkat</th>
                                            <th>Jurusan</th>
                                            <th style="width: 70px;" class="text-center">Sesi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dup_classroom_rows">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                Pilih tahun ajaran sumber untuk melihat daftar rombel.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="form-text small text-muted mt-1" id="dup_selected_count">0 rombel dipilih.</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-sm btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success px-4 fw-semibold shadow-xs" id="dup_submit_btn" disabled>
                        <i class="bi bi-copy me-1"></i>Duplikasi Rombel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
