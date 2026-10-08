{{-- Kartu Pengaturan Rombel & Jenis Proses --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
            <i class="bi bi-sliders me-2 text-primary"></i>1. Pengaturan Rombel & Jenis Proses
        </h6>
        <span class="text-muted small" style="font-size: 0.78rem;">
            <i class="bi bi-info-circle me-1"></i>Siswa yang tidak dicentang tetap di kelas asal
        </span>
    </div>
    <div class="card-body p-3 p-md-4">
        <div class="row g-3">
            {{-- KELAS ASAL --}}
            <div class="col-lg-4 col-md-6">
                <label for="source_classroom_id" class="form-label fw-semibold text-dark small mb-1">
                    Kelas Asal <span class="text-danger">*</span>
                </label>
                <select name="source_classroom_id" id="source_classroom_id"
                        class="form-select @error('source_classroom_id') is-invalid @enderror"
                        data-students-base-url="{{ url('/admin/classrooms') }}" required>
                    <option value="">-- Pilih Kelas Asal --</option>
                    @foreach($allClassrooms as $classroom)
                        <option value="{{ $classroom->id }}"
                            data-name="{{ $classroom->name }}"
                            data-level="{{ $classroom->level }}"
                            data-major="{{ $classroom->major ?? '' }}"
                            data-year="{{ $classroom->academic_year }}"
                            {{ (old('source_classroom_id', request('source_classroom_id')) == $classroom->id) ? 'selected' : '' }}>
                            Tingkat {{ $classroom->level }} - {{ $classroom->name }} ({{ $classroom->academic_year }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- JENIS AKSI (SEPARATE TOGGLE CARDS DENGAN GAP) --}}
            <div class="col-lg-4 col-md-6">
                <label class="form-label fw-semibold text-dark small mb-1">
                    Tipe Aksi <span class="text-danger">*</span>
                </label>
                <div class="d-flex gap-2">
                    <div class="action-toggle-card flex-fill">
                        <input type="radio" class="btn-check" name="action" id="actionPromote" value="promote" autocomplete="off" {{ old('action', 'promote') === 'promote' ? 'checked' : '' }}>
                        <label class="btn action-toggle-btn action-toggle-promote w-100 py-2 px-3 text-center d-flex align-items-center justify-content-center gap-1.5" for="actionPromote">
                            <i class="bi bi-arrow-up-right-circle fs-6"></i>
                            <span class="fw-semibold small">Kenaikan Kelas</span>
                        </label>
                    </div>

                    <div class="action-toggle-card flex-fill">
                        <input type="radio" class="btn-check" name="action" id="actionGraduate" value="graduate" autocomplete="off" {{ old('action') === 'graduate' ? 'checked' : '' }}>
                        <label class="btn action-toggle-btn action-toggle-graduate w-100 py-2 px-3 text-center d-flex align-items-center justify-content-center gap-1.5" for="actionGraduate">
                            <i class="bi bi-mortarboard fs-6"></i>
                            <span class="fw-semibold small">Kelulusan</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- KELAS TUJUAN --}}
            <div class="col-lg-4 col-md-12" id="targetClassContainer">
                <label for="target_classroom_id" class="form-label fw-semibold text-dark small mb-1">
                    Kelas Tujuan <span class="text-danger">*</span>
                </label>
                <select name="target_classroom_id" id="target_classroom_id" class="form-select @error('target_classroom_id') is-invalid @enderror">
                    <option value="">-- Pilih Kelas Tujuan --</option>
                    @foreach($allClassrooms as $classroom)
                        <option value="{{ $classroom->id }}"
                            data-name="{{ $classroom->name }}"
                            data-level="{{ $classroom->level }}"
                            data-major="{{ $classroom->major ?? '' }}"
                            data-year="{{ $classroom->academic_year }}"
                            data-remaining="{{ max(0, $studentCapacity - $classroom->active_students_count) }}"
                            data-capacity="{{ $studentCapacity }}"
                            {{ old('target_classroom_id') == $classroom->id ? 'selected' : '' }}>
                            Tingkat {{ $classroom->level }} - {{ $classroom->name }} ({{ $classroom->academic_year }})
                        </option>
                    @endforeach
                </select>
                <div class="form-text small text-danger d-none" id="targetRuleHint"></div>
                @error('target_classroom_id')
                    <div class="form-text small text-danger">{{ $message }}</div>
                @else
                    <div class="form-text small text-muted" id="targetCapacityHint">
                        Kapasitas maksimal {{ $studentCapacity }} siswa per rombel.
                    </div>
                @enderror
            </div>
        </div>
    </div>
</div>
