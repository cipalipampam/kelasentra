@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== PAGE HEADER ===== --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.65rem; letter-spacing: -0.4px;">
                Data Master Siswa
            </h1>
            <p class="text-muted small mb-0">
                Kelola profil siswa, data akademik, NISN, rombel, dan akun akses sistem.
            </p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="{{ route('admin.students.create') }}" class="btn btn-primary shadow-xs py-2 px-3">
                <i class="bi bi-person-plus-fill me-1"></i>Tambah Siswa Baru
            </a>
        </div>
    </div>

    {{-- ===== SEARCH & FILTER ===== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form action="{{ route('admin.students.index') }}" method="GET" class="row g-3 align-items-end js-live-filter">
                <div class="col-lg-5 col-md-12">
                    <label for="search" class="form-label">Pencarian Siswa</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" id="search" class="form-control"
                               placeholder="Cari nama, NISN, atau email..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label for="classroom_id" class="form-label">Filter Rombel</label>
                    <select name="classroom_id" id="classroom_id" class="form-select">
                        <option value="">Semua Rombel</option>
                        @foreach($classrooms as $classroom)
                            <option value="{{ $classroom->id }}" {{ (string) request('classroom_id') === (string) $classroom->id ? 'selected' : '' }}>
                                {{ $classroom->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4 col-md-6">
                    <label for="per_page" class="form-label">Tampilkan Data</label>
                    <select name="per_page" id="per_page" class="form-select">
                        <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10 Baris per Halaman</option>
                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25 Baris per Halaman</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 Baris per Halaman</option>
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
                            <th class="sortable">
                                <a href="{{ route('admin.students.index', array_merge(request()->query(), ['sort'=>'name','direction'=>request('sort')=='name'&&request('direction')=='asc'?'desc':'asc'])) }}"
                                   class="{{ request('sort')=='name' ? 'active-sort' : '' }}">
                                    Profil Siswa
                                    <i class="bi bi-arrow-down-up ms-1" style="font-size:0.7rem;"></i>
                                </a>
                            </th>
                            <th>NISN</th>
                            <th class="sortable">
                                <a href="{{ route('admin.students.index', array_merge(request()->query(), ['sort'=>'classroom','direction'=>request('sort')=='classroom'&&request('direction')=='asc'?'desc':'asc'])) }}"
                                   class="{{ request('sort')=='classroom' ? 'active-sort' : '' }}">
                                    Rombel
                                    <i class="bi bi-arrow-down-up ms-1" style="font-size:0.7rem;"></i>
                                </a>
                            </th>
                            <th>Email Akun</th>
                            <th>Jenis Kelamin</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $user)
                            <tr>
                                <td class="ps-4 text-muted small">
                                    {{ ($students->currentPage() - 1) * $students->perPage() + $loop->iteration }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        @if(isset($user->student->profile_picture) && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->student->profile_picture))
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($user->student->profile_picture) }}"
                                                 alt="Profile" class="avatar-img shadow-xs">
                                        @else
                                            <div class="avatar-placeholder">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $user->name }}</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">NIS: {{ $user->student->nis ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark">{{ $user->student->nisn ?? '-' }}</div>
                                </td>
                                <td>
                                    @if($user->student?->classroom)
                                        <span class="badge bg-light text-primary border px-2 py-1" style="font-size: 0.75rem;">
                                            {{ $user->student->classroom->name }}
                                        </span>
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ $user->student->classroom->academic_year }}</div>
                                    @elseif($user->student->grade ?? false)
                                        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.75rem;">
                                            {{ $user->student->grade }}
                                        </span>
                                        <div class="text-muted" style="font-size: 0.7rem;">Tanpa rombel (alumni)</div>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-secondary small">
                                        <i class="bi bi-envelope me-1 text-muted"></i>{{ $user->email }}
                                    </div>
                                </td>
                                <td>
                                    @if(isset($user->student->gender) && $user->student->gender == 'male')
                                        <span class="badge-status badge-sick"><i class="bi bi-gender-male"></i>Laki-laki</span>
                                    @elseif(isset($user->student->gender) && $user->student->gender == 'female')
                                        <span class="badge-status badge-absent" style="background: #fdf2f8; color: #db2777; border-color: #fbcfe8;"><i class="bi bi-gender-female"></i>Perempuan</span>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <a href="{{ route('admin.students.show', $user->id) }}"
                                           class="btn-action btn-action-view" title="Lihat Profil">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.students.edit', $user->id) }}"
                                           class="btn-action btn-action-edit" title="Edit Data">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button"
                                           class="btn-action btn-action-delete" title="Hapus"
                                           data-delete-url="{{ route('admin.students.destroy', $user->id) }}"
                                           data-delete-name="{{ $user->name }}">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="mb-3 text-muted"><i class="bi bi-people fs-1"></i></div>
                                    <h6 class="text-dark fw-medium">Belum Ada Data Siswa</h6>
                                    <p class="text-muted small mb-3">Tidak ditemukan data yang sesuai dengan kriteria pencarian.</p>
                                    <a href="{{ route('admin.students.create') }}" class="btn btn-sm btn-primary px-3">
                                        <i class="bi bi-person-plus-fill me-1"></i>Tambah Siswa
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($students->hasPages())
                <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="text-muted small">
                        Menampilkan <strong class="text-dark">{{ $students->firstItem() ?? 0 }}-{{ $students->lastItem() ?? 0 }}</strong> dari total <strong class="text-dark">{{ $students->total() }}</strong> siswa
                    </div>
                    <div class="pagination-modern">
                        {{ $students->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function registerStudentDirectoryWebSocket() {
    if (typeof window.Echo === 'undefined') return;

    let refreshTimer;
    window.Echo.private('admin.directory')
        .listen('.DirectoryChanged', (data) => {
            if (data.audience !== 'siswa') return;
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => window.refreshLiveResults?.(), 150);
        });
}

if (typeof window.Echo !== 'undefined') {
    registerStudentDirectoryWebSocket();
} else {
    window.addEventListener('echo:ready', registerStudentDirectoryWebSocket, { once: true });
}
</script>
@endpush
