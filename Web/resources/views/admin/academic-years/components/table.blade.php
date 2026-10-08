{{-- Tabel Data Tahun Ajaran --}}
<div class="js-live-results">
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 50px;">No</th>
                        <th>Tahun Ajaran</th>
                        <th style="width: 230px;">Periode</th>
                        <th style="width: 150px;" class="text-center">Jumlah Rombel</th>
                        <th style="width: 140px;">Status</th>
                        <th class="text-end pe-4" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($academicYears as $academicYear)
                    <tr>
                        <td class="ps-4 text-muted small">
                            {{ ($academicYears->currentPage() - 1) * $academicYears->perPage() + $loop->iteration }}
                        </td>
                        <td>
                            <div class="fw-semibold text-dark" style="font-size: 0.88rem;">{{ $academicYear->name }}</div>
                        </td>
                        <td class="text-dark small">
                            @if($academicYear->start_date || $academicYear->end_date)
                                {{ $academicYear->start_date?->format('d M Y') ?? '-' }}
                                <span class="text-muted">s.d.</span>
                                {{ $academicYear->end_date?->format('d M Y') ?? '-' }}
                            @else
                                <span class="text-muted small fst-italic">Belum ditentukan</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-1" style="font-size: 0.75rem;">
                                <i class="bi bi-diagram-3 me-1"></i>{{ $academicYear->classrooms_count }} Rombel
                            </span>
                        </td>
                        <td>
                            @if($academicYear->isActive())
                                <span class="badge-status badge-present" style="font-size: 0.72rem;">
                                    <i class="bi bi-check-circle me-1"></i>Aktif
                                </span>
                            @elseif($academicYear->status === \App\Models\AcademicYear::STATUS_UPCOMING)
                                <span class="badge-status badge-permission" style="font-size: 0.72rem;">
                                    <i class="bi bi-hourglass-split me-1"></i>Akan Datang
                                </span>
                            @else
                                <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.72rem;">
                                    <i class="bi bi-archive me-1"></i>Diarsipkan
                                </span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                @if(! $academicYear->isActive())
                                    <form action="{{ route('admin.academic-years.activate', $academicYear->id) }}" method="POST"
                                          data-confirm-form="Jadikan {{ $academicYear->name }} sebagai tahun ajaran aktif? Tahun ajaran aktif sebelumnya akan diarsipkan.">
                                        @csrf
                                        <button type="submit" class="btn-action btn-action-approve" title="Jadikan Tahun Ajaran Aktif">
                                            <i class="bi bi-check2-circle"></i>
                                        </button>
                                    </form>
                                @endif
                                <button type="button" class="btn-action btn-action-edit"
                                    data-bs-toggle="modal" data-bs-target="#editAcademicYearModal{{ $academicYear->id }}" title="Edit Tahun Ajaran">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                @if($academicYear->classrooms_count == 0)
                                    <button type="button" class="btn-action btn-action-delete"
                                        data-delete-url="{{ route('admin.academic-years.destroy', $academicYear->id) }}"
                                        data-delete-name="tahun ajaran {{ $academicYear->name }}" title="Hapus Tahun Ajaran">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                @else
                                    <button type="button" class="btn-action btn-action-delete opacity-50" disabled
                                        title="Tidak dapat dihapus — masih digunakan oleh {{ $academicYear->classrooms_count }} rombel">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-5 text-center text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-3 opacity-50"></i>
                            <h6 class="text-dark fw-medium">Belum Ada Tahun Ajaran</h6>
                            <p class="small mb-3">Tambahkan tahun ajaran agar rombel baru dapat dibuat.</p>
                            <button type="button" class="btn btn-sm btn-primary px-3" data-bs-toggle="modal" data-bs-target="#createModal">
                                <i class="bi bi-plus-lg me-1"></i>Tambah Tahun Ajaran
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($academicYears->hasPages())
            <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div class="text-muted small">
                    Menampilkan <strong class="text-dark">{{ $academicYears->firstItem() ?? 0 }}-{{ $academicYears->lastItem() ?? 0 }}</strong>
                    dari total <strong class="text-dark">{{ $academicYears->total() }}</strong> tahun ajaran
                </div>
                <div class="pagination-modern">
                    {{ $academicYears->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </div>
</div>
