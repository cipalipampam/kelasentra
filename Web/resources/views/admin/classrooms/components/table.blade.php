{{-- Tabel Data Rombongan Belajar --}}
@if($showHistory)
<div class="alert alert-secondary border d-flex align-items-center gap-2 py-2 px-3 mb-3" style="font-size: 0.82rem;">
    <i class="bi bi-clock-history text-secondary"></i>
    <span>Menampilkan semua rombel termasuk riwayat dari tahun ajaran yang sudah selesai.</span>
</div>
@endif
<div class="js-live-results">
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th>Nama Rombel</th>
                        <th style="width: 95px;">Tingkat</th>
                        <th style="width: 120px;">Jurusan</th>
                        <th style="width: 80px;" class="text-center">Rombel</th>
                        <th style="width: 130px;">Tahun Ajaran</th>
                        <th>Wali Kelas</th>
                        <th style="width: 120px;" class="text-center">Jml Siswa</th>
                        <th style="width: 100px;">Status</th>
                        <th class="text-end pe-4" style="width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classrooms as $classroom)
                    <tr>
                        <td class="ps-4 text-muted small">
                            {{ ($classrooms->currentPage() - 1) * $classrooms->perPage() + $loop->iteration }}
                        </td>
                        <td>
                            <div class="fw-semibold text-dark" style="font-size: 0.88rem;">{{ $classroom->name }}</div>
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size: 0.75rem;">
                                Kelas {{ $classroom->level }}
                            </span>
                        </td>
                        <td class="text-dark small">{{ $classroom->major }}</td>
                        <td class="text-dark small text-center">{{ $classroom->section }}</td>
                        <td class="text-dark small">{{ $classroom->academicYear?->name }}</td>
                        <td>
                            @if($classroom->homeroomTeacher)
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-placeholder" style="width: 28px; height: 28px; font-size: 0.65rem;">
                                        {{ strtoupper(substr($classroom->homeroomTeacher->name, 0, 2)) }}
                                    </div>
                                    <span class="text-dark small">{{ $classroom->homeroomTeacher->name }}</span>
                                </div>
                            @else
                                <span class="text-muted small fst-italic">Belum ditentukan</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @php $isFull = $classroom->active_students_count >= $studentCapacity; @endphp
                            <span class="badge {{ $isFull ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-secondary-subtle text-secondary' }} border px-2 py-1"
                                  style="font-size: 0.75rem;"
                                  title="{{ $isFull ? 'Rombel sudah penuh' : 'Sisa kuota '.($studentCapacity - $classroom->active_students_count).' siswa' }}">
                                <i class="bi bi-people-fill me-1"></i>{{ $classroom->active_students_count }}/{{ $studentCapacity }}
                            </span>
                        </td>
                        <td>
                            @if($classroom->is_active)
                                <span class="badge-status badge-present" style="font-size: 0.72rem;">
                                    <i class="bi bi-check-circle me-1"></i>Aktif
                                </span>
                            @else
                                <span class="badge-status badge-absent" style="font-size: 0.72rem;">
                                    <i class="bi bi-x-circle me-1"></i>Nonaktif
                                </span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <button type="button" class="btn-action btn-action-edit"
                                    data-bs-toggle="modal" data-bs-target="#editModal{{ $classroom->id }}" title="Edit Rombel">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                @if($classroom->students_count == 0)
                                    <button type="button" class="btn-action btn-action-delete"
                                        data-delete-url="{{ route('admin.classrooms.destroy', $classroom->id) }}"
                                        data-delete-name="{{ $classroom->name }}" title="Hapus Rombel">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                @else
                                    <button type="button" class="btn-action btn-action-delete opacity-50"
                                        disabled title="Tidak dapat dihapus — masih ada {{ $classroom->students_count }} siswa terdaftar">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="py-5 text-center text-muted">
                            <i class="bi bi-diagram-3 fs-1 d-block mb-3 opacity-50"></i>
                            <h6 class="text-dark fw-medium">Belum Ada Rombel</h6>
                            <p class="small mb-3">Tidak ditemukan rombongan belajar yang sesuai dengan kriteria pencarian.</p>
                            <button type="button" class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#createModal">
                                <i class="bi bi-plus-lg me-1"></i>Tambah Rombel Baru
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($classrooms->hasPages())
            <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div class="text-muted small">
                    Menampilkan <strong class="text-dark">{{ $classrooms->firstItem() ?? 0 }}-{{ $classrooms->lastItem() ?? 0 }}</strong>
                    dari total <strong class="text-dark">{{ $classrooms->total() }}</strong> rombel
                </div>
                <div class="pagination-modern">
                    {{ $classrooms->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </div>
</div>

