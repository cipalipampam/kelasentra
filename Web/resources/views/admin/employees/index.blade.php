@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== BREADCRUMB & PAGE HEADER ===== --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted small">
                    <i class="bi bi-house-door me-1"></i>Dashboard
                </a>
                <span class="text-muted small">/</span>
                <span class="text-muted small">Data Guru & Pegawai</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <h1 class="fw-bold text-dark mb-0" style="font-size: 1.65rem; letter-spacing: -0.4px;">
                    Direktori Guru & Pegawai
                </h1>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1" style="font-size: 0.75rem;">
                    {{ $employees->total() }} Personil
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Kelola data pengajar, staf tata usaha, NIP, jabatan struktural, dan kredensial akun.
            </p>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <a href="{{ route('admin.employees.create') }}" class="btn btn-primary shadow-xs py-2 px-3">
                <i class="bi bi-person-plus-fill me-1"></i>Tambah Guru / Pegawai
            </a>
        </div>
    </div>

    {{-- ===== SEARCH & FILTER ===== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form action="{{ route('admin.employees.index') }}" method="GET" class="row g-3 align-items-end js-live-filter">
                <div class="col-lg-5 col-md-12">
                    <label for="search" class="form-label small fw-semibold text-secondary">Pencarian Personil</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="search" class="form-control border-start-0 ps-0"
                               placeholder="Cari nama, NIP, email, atau jabatan..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-lg-3 col-md-5">
                    <label for="role" class="form-label small fw-semibold text-secondary">Klasifikasi Peran</label>
                    <select name="role" id="role" class="form-select">
                        <option value="">Semua Peran (Guru & Staf)</option>
                        <option value="guru" {{ request('role') == 'guru' ? 'selected' : '' }}>Guru / Tenaga Pengajar</option>
                        <option value="staff" {{ request('role') == 'staff' ? 'selected' : '' }}>Staf Administrasi / TU</option>
                    </select>
                </div>
                <div class="col-lg-4 col-md-7">
                    <label for="per_page" class="form-label small fw-semibold text-secondary">Baris Data</label>
                    <select name="per_page" id="per_page" class="form-select">
                        <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10 Baris</option>
                        <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25 Baris</option>
                        <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50 Baris</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 d-flex align-items-center mb-4 shadow-xs" role="alert" style="border-radius: 10px;">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ===== DATA TABLE ===== --}}
    <div class="js-live-results">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 50px;">No</th>
                            <th>Pegawai / Guru</th>
                            <th>NIP</th>
                            <th>Jabatan / Posisi</th>
                            <th>Klasifikasi</th>
                            <th>Status</th>
                            <th>Kontak & Email</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $index => $user)
                            <tr>
                                <td class="ps-4 text-muted small">
                                    {{ ($employees->currentPage() - 1) * $employees->perPage() + $loop->iteration }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        @if(isset($user->employee->profile_picture) && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->employee->profile_picture))
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($user->employee->profile_picture) }}" 
                                                 alt="Profile" class="avatar-img shadow-xs">
                                        @else
                                            <div class="avatar-placeholder">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $user->name }}</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">
                                                Terdaftar: {{ $user->created_at ? $user->created_at->format('d M Y') : '-' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if(isset($user->employee->nip) && $user->employee->nip)
                                        <div class="fw-medium text-dark">{{ $user->employee->nip }}</div>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(isset($user->employee->position) && $user->employee->position)
                                        <div class="text-secondary small fw-medium">{{ $user->employee->position }}</div>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($user->hasRole('guru'))
                                        <span class="badge-status badge-present">
                                            <i class="bi bi-mortarboard-fill me-1"></i>Guru
                                        </span>
                                    @elseif($user->hasRole('staff'))
                                        <span class="badge-status badge-sick">
                                            <i class="bi bi-person-badge-fill me-1"></i>Staf TU
                                        </span>
                                    @else
                                        <span class="badge bg-light text-secondary border px-2 py-1">Lainnya</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $employmentStatus = $user->employee->employment_status ?? null;
                                        $statusLabels = \App\Models\Employee::statusLabels();
                                    @endphp
                                    @if($employmentStatus === \App\Models\Employee::STATUS_ACTIVE)
                                        <span class="badge-status badge-present">
                                            <i class="bi bi-check-circle me-1"></i>Aktif
                                        </span>
                                    @elseif($employmentStatus === null)
                                        <span class="text-muted small">-</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="bi bi-pause-circle me-1"></i>{{ $statusLabels[$employmentStatus] ?? $employmentStatus }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-secondary small">
                                        <i class="bi bi-envelope me-1 text-muted"></i>{{ $user->email }}
                                    </div>
                                    @if(isset($user->employee->phone_number) && $user->employee->phone_number)
                                        <div class="text-muted small mt-0.5" style="font-size: 0.75rem;">
                                            <i class="bi bi-whatsapp me-1 text-success"></i>{{ $user->employee->phone_number }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <a href="{{ route('admin.employees.show', $user->id) }}" class="btn-action btn-action-view" title="Lihat Profil">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.employees.edit', $user->id) }}" class="btn-action btn-action-edit" title="Edit Data">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button" class="btn-action btn-action-delete" title="Hapus"
                                            data-delete-url="{{ route('admin.employees.destroy', $user->id) }}"
                                            data-delete-name="{{ $user->name }}">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="mb-3 text-muted"><i class="bi bi-person-x fs-1"></i></div>
                                    <h6 class="text-dark fw-medium">Belum Ada Data Pegawai</h6>
                                    <p class="text-muted small mb-3">Tidak ditemukan data pegawai yang sesuai dengan kriteria filter.</p>
                                    <a href="{{ route('admin.employees.create') }}" class="btn btn-sm btn-primary px-3">
                                        <i class="bi bi-person-plus-fill me-1"></i>Tambah Guru / Pegawai
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($employees->hasPages())
                <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="text-muted small">
                        Menampilkan <strong class="text-dark">{{ $employees->firstItem() ?? 0 }}-{{ $employees->lastItem() ?? 0 }}</strong> dari total <strong class="text-dark">{{ $employees->total() }}</strong> pegawai
                    </div>
                    <div class="pagination-modern">
                        {{ $employees->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function registerEmployeeDirectoryWebSocket() {
    if (typeof window.Echo === 'undefined') return;

    let refreshTimer;
    window.Echo.private('admin.directory')
        .listen('.DirectoryChanged', (data) => {
            if (data.audience !== 'employee') return;
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => window.refreshLiveResults?.(), 150);
        });
}

if (typeof window.Echo !== 'undefined') {
    registerEmployeeDirectoryWebSocket();
} else {
    window.addEventListener('echo:ready', registerEmployeeDirectoryWebSocket, { once: true });
}
</script>
@endpush
