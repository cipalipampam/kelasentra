@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== PAGE HEADER ===== --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted small">
                    <i class="bi bi-house-door me-1"></i>Dashboard
                </a>
                <span class="text-muted small">/</span>
                <a href="{{ route('admin.students.index') }}" class="text-decoration-none text-muted small">
                    Data Siswa
                </a>
                <span class="text-muted small">/</span>
                <span class="text-muted small">Profil</span>
            </div>
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.65rem; letter-spacing: -0.4px;">
                Profil Lengkap Siswa
            </h1>
            <p class="text-muted small mb-0">Berkas identitas dan ringkasan kehadiran untuk <strong class="text-dark">{{ $student->name }}</strong>.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary py-2 px-3 me-2" style="font-size: 0.85rem;">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
            </a>
            <a href="{{ route('admin.students.edit', $student->id) }}" class="btn btn-primary py-2 px-3" style="font-size: 0.85rem;">
                <i class="bi bi-pencil me-1"></i>Edit Data Siswa
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- ===== FOTO & RINGKASAN STATUS ===== --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 text-center">
                    @if(isset($student->student->profile_picture) && \Illuminate\Support\Facades\Storage::disk('public')->exists($student->student->profile_picture))
                        <div class="mx-auto rounded-4 mb-3 overflow-hidden border shadow-xs"
                             style="width: 150px; height: 190px;">
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($student->student->profile_picture) }}"
                                 alt="Profile" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                        </div>
                    @else
                        <div class="avatar-preview mx-auto rounded-4 mb-3 d-flex align-items-center justify-content-center bg-light border"
                             style="width: 150px; height: 190px;">
                            <i class="bi bi-person-bounding-box text-muted display-4"></i>
                        </div>
                    @endif

                    <h5 class="fw-bold text-dark mb-1">{{ $student->name }}</h5>
                    <p class="text-muted small mb-2">{{ $student->email }}</p>

                    <div class="d-flex align-items-center justify-content-center gap-2 mt-2 flex-wrap">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1" style="font-size: 0.75rem;">
                            <i class="bi bi-mortarboard me-1"></i>{{ $student->student->grade ?? 'Belum ada rombel' }}
                        </span>
                        @if($student->student?->classroom)
                            <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1" style="font-size: 0.75rem;">
                                <i class="bi bi-calendar3 me-1"></i>TA {{ $student->student->classroom->academicYear?->name }}
                            </span>
                        @endif
                        @php($studentStatus = $student->student?->academic_status ?? 'active')
                        @php($statusStyle = match ($studentStatus) {
                            'active' => 'bg-success-subtle text-success border-success-subtle',
                            'graduated' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                            'transferred' => 'bg-warning-subtle text-warning border-warning-subtle',
                            default => 'bg-danger-subtle text-danger border-danger-subtle',
                        })
                        <span class="badge {{ $statusStyle }} border px-2.5 py-1" style="font-size: 0.75rem;">
                            <i class="bi bi-check-circle-fill me-1"></i>{{ $student->student?->academicStatusLabel() ?? 'Siswa Aktif' }}
                        </span>
                    </div>

                    <div class="row g-2 mt-3 pt-3 border-top text-center">
                        <div class="col-6">
                            <span class="text-muted small d-block" style="font-size: 0.75rem;">NISN</span>
                            <strong class="text-dark">{{ $student->student->nisn ?? '-' }}</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted small d-block" style="font-size: 0.75rem;">NIS Sekolah</span>
                            <strong class="text-dark">{{ $student->student->nis ?? '-' }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Attendance Stats Card --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 text-dark fw-bold">
                        <i class="bi bi-pie-chart text-primary me-2"></i>Ringkasan Kehadiran
                    </h6>
                </div>
                <div class="card-body p-3">
                    @php
                        $attTotal = $student->attendances()->count();
                        $attPresent = $student->attendances()->where('status', 'present')->count();
                        $attLate = $student->attendances()->where('status', 'present')->where('is_late', true)->count();
                        $attPermission = $student->attendances()->whereIn('status', ['permission', 'sick'])->count();
                    @endphp
                    <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                        <span class="small text-muted">Total Presensi:</span>
                        <strong class="text-dark">{{ $attTotal }} kali</strong>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                        <span class="small text-muted">Hadir Tepat Waktu:</span>
                        <span class="badge bg-success-subtle text-success fw-bold">{{ $attPresent - $attLate }} kali</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                        <span class="small text-muted">Terlambat:</span>
                        <span class="badge bg-warning-subtle text-warning-emphasis fw-bold">{{ $attLate }} kali</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-2">
                        <span class="small text-muted">Izin / Sakit:</span>
                        <span class="badge bg-info-subtle text-info-emphasis fw-bold">{{ $attPermission }} kali</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== DETAIL INFORMASI & RIWAYAT TERAKHIR ===== --}}
        <div class="col-lg-8">
            {{-- Data Akademik & Pribadi --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-dark fw-bold">
                        <i class="bi bi-card-text text-primary me-2"></i>Identitas & Data Pribadi Siswa
                    </h6>
                    <span class="text-muted small">NISN: {{ $student->student->nisn ?? '-' }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Jenis Kelamin</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium">
                                @if(($student->student->gender ?? '') == 'male')
                                    <i class="bi bi-gender-male text-primary me-1"></i>Laki-laki
                                @elseif(($student->student->gender ?? '') == 'female')
                                    <i class="bi bi-gender-female text-danger me-1"></i>Perempuan
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Agama</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium">
                                {{ ucfirst($student->student->religion ?? '-') }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Tempat, Tanggal Lahir</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium">
                                {{ $student->student->place_of_birth ?? '-' }},
                                {{ isset($student->student->date_of_birth) ? \Carbon\Carbon::parse($student->student->date_of_birth)->translatedFormat('d F Y') : '-' }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Nomor Telepon / WhatsApp</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium d-flex align-items-center justify-content-between">
                                <span>{{ $student->student->phone_number ? '+62 ' . $student->student->phone_number : '-' }}</span>
                                @if($student->student->phone_number ?? false)
                                    <a href="https://wa.me/62{{ preg_replace('/^0/', '', $student->student->phone_number) }}" target="_blank" class="btn btn-sm btn-link text-success p-0" title="Hubungi via WhatsApp">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Alamat Tempat Tinggal</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark">
                                {{ $student->student->address ?? 'Alamat belum dilengkapi.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Riwayat Presensi Terakhir --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-dark fw-bold">
                        <i class="bi bi-clock-history text-primary me-2"></i>Riwayat Presensi Terbaru Siswa Ini
                    </h6>
                    <a href="{{ route('admin.attendances.index', ['search' => $student->name]) }}"
                       class="btn btn-sm btn-outline-primary py-1 px-2.5" style="font-size: 0.78rem;">
                        Lihat Semua Riwayat →
                    </a>
                </div>
                <div class="card-body p-0">
                    @php
                        $recentAttendances = $student->attendances()->latest('recorded_at')->take(5)->get() ?? collect();
                    @endphp

                    @if($recentAttendances->isEmpty())
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted fs-3 mb-2 d-block"></i>
                            <p class="text-muted small mb-0">Belum ada rekaman presensi untuk siswa ini.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Tanggal & Waktu</th>
                                        <th>Status</th>
                                        <th>Jam Pulang</th>
                                        <th class="pe-3 text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentAttendances as $att)
                                    <tr>
                                        <td class="ps-3">
                                            <strong class="text-dark small d-block">{{ \Carbon\Carbon::parse($att->recorded_at)->translatedFormat('d M Y') }}</strong>
                                            <span class="text-muted small">{{ \Carbon\Carbon::parse($att->recorded_at)->format('H:i') }} WIB</span>
                                        </td>
                                        <td>
                                            @if($att->status == 'present')
                                                @if($att->is_late)
                                                    <span class="badge-status badge-late"><i class="bi bi-clock-history"></i>Terlambat</span>
                                                @else
                                                    <span class="badge-status badge-present"><i class="bi bi-check2"></i>Hadir</span>
                                                @endif
                                            @elseif($att->status == 'permission')
                                                <span class="badge-status badge-permission"><i class="bi bi-file-text"></i>Izin</span>
                                            @elseif($att->status == 'sick')
                                                <span class="badge-status badge-sick"><i class="bi bi-bandaid"></i>Sakit</span>
                                            @else
                                                <span class="badge-status badge-absent"><i class="bi bi-x-circle"></i>Alfa</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($att->check_out_time)
                                                <span class="text-dark small fw-medium">{{ \Carbon\Carbon::parse($att->check_out_time)->format('H:i') }} WIB</span>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td class="pe-3 text-end">
                                            <a href="{{ route('admin.attendances.show', $att->id) }}" class="btn-action btn-action-view" title="Detail Presensi">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
