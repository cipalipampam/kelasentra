{{-- Kartu Seleksi Siswa & Eksekusi --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                        <i class="bi bi-people-fill me-2 text-primary"></i>2. Seleksi Siswa Rombel
                    </h6>
                    <span id="selectedBadge" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                        0 Siswa Terpilih
                    </span>
                </div>
            </div>
            <div class="col-md-6 text-md-end mt-2 mt-md-0 d-flex justify-content-md-end gap-2">
                <div class="input-group input-group-sm" style="max-width: 240px;">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="studentSearchInput" class="form-control border-start-0 ps-0" placeholder="Cari nama atau NIS...">
                </div>
                <button type="button" id="btnSelectAll" class="btn btn-sm btn-outline-primary px-2.5">
                    <i class="bi bi-check-all me-1"></i>Pilih Semua
                </button>
                <button type="button" id="btnDeselectAll" class="btn btn-sm btn-outline-secondary px-2.5">
                    Batal Semua
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">

        {{-- LOADING SPINNER --}}
        <div id="loadingIndicator" class="text-center py-5 d-none">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Memuat...</span>
            </div>
            <p class="text-muted small mt-2 mb-0">Sedang memuat data siswa kelas...</p>
        </div>

        {{-- EMPTY STATE: BELUM PILIH KELAS ASAL --}}
        <div id="emptySelectClass" class="text-center py-5 {{ $selectedClassroom ? 'd-none' : '' }}">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2"
                 style="width: 48px; height: 48px; background: #eff6ff; color: #3b82f6;">
                <i class="bi bi-arrow-up-circle fs-4"></i>
            </div>
            <h6 class="fw-semibold text-dark mb-1" style="font-size: 0.92rem;">Pilih Kelas Asal Terlebih Dahulu</h6>
            <p class="text-muted small mb-0" style="max-width: 380px; margin: 0 auto; font-size: 0.8rem;">
                Tentukan rombel asal pada formulir di atas untuk memuat daftar siswa yang terdaftar.
            </p>
        </div>

        {{-- EMPTY STATE: KELAS TIDAK MEMILIKI SISWA --}}
        <div id="emptyNoStudents" class="text-center py-5 d-none">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2"
                 style="width: 48px; height: 48px; background: #fff1f2; color: #e11d48;">
                <i class="bi bi-person-x fs-4"></i>
            </div>
            <h6 class="fw-semibold text-dark mb-1" style="font-size: 0.92rem;">Tidak Ada Siswa Aktif</h6>
            <p class="text-muted small mb-0" style="max-width: 380px; margin: 0 auto; font-size: 0.8rem;">
                Kelas asal yang dipilih saat ini tidak memiliki siswa dengan status akademik aktif.
            </p>
        </div>

        {{-- TABEL SISWA --}}
        <div id="tableContainer" class="table-responsive {{ $selectedClassroom && $students->count() > 0 ? '' : 'd-none' }}">
            <table class="table table-hover align-middle mb-0" id="studentsTable">
                <thead>
                    <tr>
                        <th style="width: 48px;" class="text-center">
                            <input type="checkbox" id="masterCheckbox" class="form-check-input" checked title="Pilih Semua">
                        </th>
                        <th style="width: 50px;">No</th>
                        <th>Nama Lengkap Siswa</th>
                        <th style="width: 140px;">NIS / NISN</th>
                        <th style="width: 100px;">L/P</th>
                        <th style="width: 110px;">Status</th>
                        <th>Tujuan Pemindahan</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody">
                    @if($selectedClassroom && $students->count() > 0)
                        @foreach($students as $index => $student)
                            <tr class="student-row" data-name="{{ strtolower($student->user?->name ?? '') }}" data-nis="{{ $student->nis ?? '' }}">
                                <td class="text-center">
                                    <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="form-check-input student-checkbox" checked>
                                </td>
                                <td class="text-muted small">{{ $index + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-placeholder" style="width: 28px; height: 28px; font-size: 0.65rem;">
                                            {{ strtoupper(substr($student->user?->name ?? 'S', 0, 1)) }}
                                        </div>
                                        <div>
                                            <span class="fw-semibold text-dark d-block student-name" style="font-size: 0.88rem;">
                                                {{ $student->user?->name ?? 'Siswa #'.$student->id }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">
                                    <span class="fw-medium text-dark">{{ $student->nis ?? '-' }}</span>
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">NISN: {{ $student->nisn ?? '-' }}</span>
                                </td>
                                <td class="small">
                                    <span class="badge {{ $student->gender === 'P' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }}" style="font-size: 0.72rem;">
                                        {{ $student->gender === 'P' ? 'Perempuan' : 'Laki-laki' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status badge-present" style="font-size: 0.72rem;">
                                        <i class="bi bi-check-circle me-1"></i>Aktif
                                    </span>
                                </td>
                                <td class="target-indicator small text-muted">
                                    <span class="target-label">Mengikuti Pengaturan di Atas</span>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

    </div>
    <div class="card-footer bg-white py-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="small text-muted" id="selectionSummaryText">
            Silakan tentukan rombel dan centang siswa yang berhak diproses.
        </div>
        <div>
            <button type="button" id="btnOpenConfirmModal" class="btn btn-primary py-2 px-4 shadow-xs fw-semibold" disabled>
                <i class="bi bi-arrow-repeat me-1"></i>Proses Kenaikan Kelas
            </button>
        </div>
    </div>
</div>
