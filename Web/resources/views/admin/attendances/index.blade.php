@extends('admin.layouts.app')

@section('content')
@php
    $scopeQuery = $attendanceType ? ['scope' => $attendanceType] : [];
@endphp
<div class="py-2">

    {{-- ===== PAGE HEADER ===== --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.65rem; letter-spacing: -0.4px;">
                {{ $attendanceType === 'siswa' ? 'Data Presensi Siswa' : ($attendanceType === 'employee' ? 'Data Presensi Guru & Pegawai' : 'Semua Riwayat Presensi') }}
            </h1>
            <p class="text-muted small mb-0">
                Pantau riwayat presensi, tinjau bukti permohonan izin/sakit, dan ekspor laporan resmi.
            </p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0 d-flex align-items-center justify-content-md-end gap-2 flex-wrap">
            {{-- Tombol Cetak / Print --}}
            <a href="{{ route('admin.attendances.print', request()->query()) }}" target="_blank" class="btn btn-outline-secondary shadow-sm py-2 px-3" style="font-size: 0.85rem;">
                <i class="bi bi-printer me-1"></i>Cetak Dokumen
            </a>

            {{-- Export Dropdown --}}
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle shadow-sm py-2 px-3" type="button" data-bs-toggle="dropdown" style="font-size: 0.85rem;">
                    <i class="bi bi-download me-1"></i>Ekspor Laporan
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg py-2">
                    <li>
                        <a class="dropdown-item py-2" href="{{ route($attendanceRouteName, array_merge(request()->query(), ['export' => 'excel'])) }}">
                            <i class="bi bi-file-earmark-excel me-2 text-success"></i>Microsoft Excel (.xlsx)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="{{ route($attendanceRouteName, array_merge(request()->query(), ['export' => 'csv'])) }}">
                            <i class="bi bi-filetype-csv me-2 text-info"></i>CSV Spreadsheet (.csv)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="{{ route($attendanceRouteName, array_merge(request()->query(), ['export' => 'pdf'])) }}">
                            <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Dokumen PDF (.pdf)
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2" href="{{ route($attendanceRouteName, array_merge(request()->query(), ['export' => 'zip'])) }}">
                            <i class="bi bi-file-earmark-zip me-2 text-warning"></i>Semua Format (.zip)
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Tombol Catat Manual --}}
            <a href="{{ route('admin.attendances.create', $scopeQuery) }}" class="btn btn-primary shadow-sm py-2 px-3" style="font-size: 0.85rem;">
                <i class="bi bi-plus-lg me-1"></i>Catat Presensi Manual
            </a>
        </div>
    </div>

    {{-- ===== FILTER CARD ===== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form action="{{ route($attendanceRouteName) }}" method="GET" class="js-live-filter">
                @if($attendanceType)<input type="hidden" name="scope" value="{{ $attendanceType }}">@endif
                <div class="row g-3 align-items-end">
                    <div class="{{ $attendanceType === 'employee' ? 'col-lg-5' : ($attendanceType === 'siswa' ? 'col-lg-4' : 'col-lg-3') }} col-md-6">
                        <label for="search" class="form-label text-dark small fw-semibold">Pencarian Nama</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" id="search" class="form-control border-start-0"
                                   placeholder="Ketik nama anggota..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="{{ $attendanceType === 'employee' ? 'col-lg-4' : 'col-lg-2' }} col-md-6">
                        <label for="date" class="form-label text-dark small fw-semibold">Tanggal Spesifik</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ request('date', $date ?? '') }}">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label for="month" class="form-label text-dark small fw-semibold">Bulan</label>
                        <select name="month" id="month" class="form-select">
                            <option value="">Semua Bulan</option>
                            @for ($m = 1; $m <= 12; $m++)
                                @php $mVal = sprintf('%02d', $m); @endphp
                                <option value="{{ $mVal }}" {{ request('month') == $mVal ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(2025, $m, 1)->translatedFormat('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-4">
                        <label for="year" class="form-label text-dark small fw-semibold">Tahun</label>
                        <select name="year" id="year" class="form-select">
                            @php $currentYear = date('Y'); @endphp
                            @for ($y = $currentYear; $y >= $currentYear - 4; $y--)
                                <option value="{{ $y }}" {{ request('year', $currentYear) == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="{{ $attendanceType === 'siswa' ? 'col-lg-3' : 'col-lg-2' }} col-md-4" id="classroom-filter-container" style="{{ $attendanceType === 'siswa' || request('role') == 'siswa' ? '' : 'display:none;' }}">
                        <label for="classroom_id" class="form-label text-dark small fw-semibold">Rombel</label>
                        <select name="classroom_id" id="classroom_id" class="form-select">
                            <option value="">Semua Rombel</option>
                            @foreach($classrooms as $classroom)
                                <option value="{{ $classroom->id }}" {{ (string) request('classroom_id') === (string) $classroom->id ? 'selected' : '' }}>
                                    {{ $classroom->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div id="role-filter-container" class="{{ $attendanceType ? 'd-none' : (request('role') == 'siswa' ? 'col-lg-2' : 'col-lg-4') }} col-md-6">
                        <label for="role" class="form-label text-dark small fw-semibold">Filter Peran</label>
                        <select name="role" id="role" class="form-select" onchange="toggleClassroomFilter()">
                            <option value="">Semua Anggota</option>
                            <option value="siswa"    {{ request('role') == 'siswa'    ? 'selected' : '' }}>Siswa</option>
                            <option value="employee" {{ request('role') == 'employee' ? 'selected' : '' }}>Semua Pegawai</option>
                            <option value="guru"     {{ request('role') == 'guru'     ? 'selected' : '' }}>Guru</option>
                            <option value="staff"    {{ request('role') == 'staff'    ? 'selected' : '' }}>Staff</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                    <div class="d-flex align-items-center gap-2">
                        @if(request('approval') === 'pending')
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-2 px-2">
                                <i class="bi bi-funnel-fill me-1"></i>Filter Aktif: Menunggu Persetujuan
                            </span>
                            <a href="{{ route($attendanceRouteName, request()->except('approval')) }}" class="btn btn-sm btn-link text-decoration-none p-0 text-danger small">
                                [Hapus Filter Ini]
                            </a>
                        @endif
                    </div>
                    <div>
                        <a href="{{ route($attendanceRouteName) }}" class="btn btn-sm btn-light text-secondary border py-1 px-3" style="font-size: 0.8rem;">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 d-flex align-items-center mb-4 shadow-sm" role="alert" style="background-color: #ecfdf5; color: #065f46; border-left: 4px solid #059669 !important;">
            <i class="bi bi-check-circle-fill me-2 fs-5 text-success"></i>
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
                            <th>Nama Anggota</th>
                            <th>Peran / Info</th>
                            <th>Status Kehadiran</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Bukti Foto</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $attendance)
                            <tr>
                                <td class="ps-4 text-muted small">
                                    {{ ($attendances->currentPage() - 1) * $attendances->perPage() + $loop->iteration }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-placeholder">
                                            {{ strtoupper(substr($attendance->user->name ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $attendance->user->name ?? 'Unknown' }}</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">{{ $attendance->user->email ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($attendance->user->hasRole('siswa'))
                                        <span class="badge bg-light text-primary border border-primary-subtle px-2 py-1" style="font-size: 0.72rem;">Siswa</span>
                                        @if($attendance->user->student)
                                            <span class="text-muted small ms-1">{{ $attendance->user->student->class_name }}</span>
                                        @endif
                                    @elseif($attendance->user->hasRole('guru'))
                                        <span class="badge bg-light text-success border border-success-subtle px-2 py-1" style="font-size: 0.72rem;">Guru</span>
                                        @if($attendance->user->employee)
                                            <span class="text-muted small ms-1">{{ $attendance->user->employee->position }}</span>
                                        @endif
                                    @elseif($attendance->user->hasRole('staff'))
                                        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.72rem;">Staff</span>
                                    @else
                                        <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.72rem;">Admin</span>
                                    @endif
                                </td>
                                <td>
                                    @if($attendance->status == 'present')
                                        @if($attendance->is_late)
                                            <span class="badge-status badge-late"><i class="bi bi-clock-history"></i>Terlambat</span>
                                        @else
                                            <span class="badge-status badge-present"><i class="bi bi-check2"></i>Hadir</span>
                                        @endif
                                    @elseif($attendance->status == 'permission')
                                        <span class="badge-status badge-permission">
                                            <i class="bi bi-file-text"></i>Izin
                                            @if($attendance->is_approved === true)
                                                <i class="bi bi-check-all ms-1 text-success"></i>
                                            @elseif($attendance->is_approved === false)
                                                <i class="bi bi-x ms-1 text-danger"></i>
                                            @else
                                                <span class="badge bg-warning text-dark ms-1" style="font-size:0.6rem;">Review</span>
                                            @endif
                                        </span>
                                    @elseif($attendance->status == 'sick')
                                        <span class="badge-status badge-sick">
                                            <i class="bi bi-bandaid"></i>Sakit
                                            @if($attendance->is_approved === true)
                                                <i class="bi bi-check-all ms-1 text-success"></i>
                                            @elseif($attendance->is_approved === false)
                                                <i class="bi bi-x ms-1 text-danger"></i>
                                            @else
                                                <span class="badge bg-warning text-dark ms-1" style="font-size:0.6rem;">Review</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="badge-status badge-absent"><i class="bi bi-x-circle"></i>Alfa</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-medium text-dark" style="font-size: 0.85rem;">
                                        <i class="bi bi-box-arrow-in-right me-1 text-success"></i>
                                        {{ \Carbon\Carbon::parse($attendance->recorded_at)->format('H:i') }} WIB
                                    </div>
                                    <div class="text-muted small" style="font-size: 0.72rem;">
                                        {{ \Carbon\Carbon::parse($attendance->recorded_at)->translatedFormat('d M Y') }}
                                    </div>
                                </td>
                                <td>
                                    @if($attendance->check_out_time)
                                        <div class="fw-medium text-dark" style="font-size: 0.85rem;">
                                            <i class="bi bi-box-arrow-right me-1 text-warning"></i>
                                            {{ $attendance->check_out_time->format('H:i') }} WIB
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($attendance->proof_image)
                                        <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 rounded-pill" style="font-size: 0.75rem;"
                                                onclick="showProofModal('{{ route('admin.attendances.proof', $attendance) }}', '{{ addslashes($attendance->user->name) }}')">
                                            <i class="bi bi-image me-1"></i>Lihat Bukti
                                        </button>
                                    @else
                                        <span class="text-muted small fst-italic">—</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        @if(($attendance->status == 'permission' || $attendance->status == 'sick') && $attendance->is_approved === null)
                                            <form action="{{ route('admin.attendances.approve', array_merge(['id' => $attendance->id], $scopeQuery)) }}" method="POST" class="d-inline">
                                                @csrf
                                                @if($attendanceType)<input type="hidden" name="scope" value="{{ $attendanceType }}">@endif
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-action btn-action-approve" title="Setujui Pengajuan" onclick="return confirm('Setujui pengajuan izin/sakit ini?')">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.attendances.approve', array_merge(['id' => $attendance->id], $scopeQuery)) }}" method="POST" class="d-inline">
                                                @csrf
                                                @if($attendanceType)<input type="hidden" name="scope" value="{{ $attendanceType }}">@endif
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn-action btn-action-delete" title="Tolak Pengajuan (Tercatat Alfa)" onclick="return confirm('Tolak permohonan ini?')">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.attendances.show', array_merge(['id' => $attendance->id], $scopeQuery)) }}"
                                           class="btn-action btn-action-view" title="Detail Presensi">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.attendances.edit', array_merge(['id' => $attendance->id], $scopeQuery)) }}"
                                           class="btn-action btn-action-edit" title="Ubah Data">
                                           <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button"
                                           class="btn-action btn-action-delete" title="Hapus"
                                           data-delete-url="{{ route('admin.attendances.destroy', array_merge(['id' => $attendance->id], $scopeQuery)) }}"
                                           data-delete-name="presensi {{ $attendance->user->name }} tanggal {{ \Carbon\Carbon::parse($attendance->recorded_at)->format('d M Y') }}">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="mb-3 text-muted"><i class="bi bi-calendar-x fs-1"></i></div>
                                    <h6 class="text-dark fw-medium">Tidak Ada Catatan Presensi</h6>
                                    <p class="text-muted small mb-0">Sesuaikan filter tanggal atau rombel untuk menemukan data yang dicari.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($attendances->hasPages())
                <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="text-muted small">
                        Menampilkan <strong class="text-dark">{{ $attendances->firstItem() ?? 0 }}-{{ $attendances->lastItem() ?? 0 }}</strong> dari total <strong class="text-dark">{{ $attendances->total() }}</strong> catatan
                    </div>
                    <div class="pagination-modern">
                        {{ $attendances->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ===== MODAL QUICK PREVIEW BUKTI ===== --}}
<div class="modal fade" id="proofModal" tabindex="-1" aria-labelledby="proofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold text-dark" id="proofModalLabel">
                    <i class="bi bi-file-earmark-image text-primary me-2"></i>Bukti Dokumen / Surat
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 text-center bg-light">
                <div id="proofLoading" class="py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <img id="proofModalImage" src="" alt="Bukti Surat" class="img-fluid rounded-3 border shadow-sm d-none" style="max-height: 480px; object-fit: contain;">
                <p id="proofModalCaption" class="text-muted small mt-2 mb-0"></p>
            </div>
            <div class="modal-footer border-top py-2">
                <a id="proofDownloadBtn" href="" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Buka Tab Baru
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleClassroomFilter() {
    const roleSelect = document.getElementById('role');
    const classroomContainer = document.getElementById('classroom-filter-container');
    const roleContainer = document.getElementById('role-filter-container');
    if (roleSelect.value === 'siswa') {
        classroomContainer.style.display = 'block';
        roleContainer.className = 'col-lg-2 col-md-6';
    } else {
        classroomContainer.style.display = 'none';
        roleContainer.className = 'col-lg-4 col-md-6';
        document.getElementById('classroom_id').value = '';
    }
}

function showProofModal(url, userName) {
    const modalEl = document.getElementById('proofModal');
    const imgEl = document.getElementById('proofModalImage');
    const loadingEl = document.getElementById('proofLoading');
    const captionEl = document.getElementById('proofModalCaption');
    const downloadBtn = document.getElementById('proofDownloadBtn');

    loadingEl.classList.remove('d-none');
    imgEl.classList.add('d-none');
    captionEl.textContent = `Surat/Bukti dari: ${userName}`;
    downloadBtn.href = url;

    imgEl.onload = function() {
        loadingEl.classList.add('d-none');
        imgEl.classList.remove('d-none');
    };
    imgEl.onerror = function() {
        loadingEl.classList.add('d-none');
        captionEl.textContent = 'Gagal memuat gambar bukti.';
    };
    imgEl.src = url;

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}
</script>
@endsection
