@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== BREADCRUMB & PAGE HEADER ===== --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted small">
                    <i class="bi bi-house-door me-1"></i>Dashboard
                </a>
                <span class="text-muted small">/</span>
                <a href="{{ route('admin.employees.index') }}" class="text-decoration-none text-muted small">
                    Data Guru & Pegawai
                </a>
                <span class="text-muted small">/</span>
                <span class="text-muted small">Profil Personil</span>
            </div>
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.65rem; letter-spacing: -0.4px;">
                Profil Lengkap Pegawai
            </h1>
            <p class="text-muted small mb-0">
                Informasi identitas kedinasan, berkas profil, dan riwayat presensi untuk <strong class="text-dark">{{ $employee->name }}</strong>.
            </p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="{{ route('admin.employees.index') }}" class="btn btn-outline-secondary py-2 px-3 me-2" style="font-size: 0.85rem;">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Direktori
            </a>
            <a href="{{ route('admin.employees.edit', $employee->id) }}" class="btn btn-primary py-2 px-3" style="font-size: 0.85rem;">
                <i class="bi bi-pencil me-1"></i>Edit Data Pegawai
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- ===== FOTO & RINGKASAN STATUS ===== --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4 text-center">
                    @if(isset($employee->employee->profile_picture) && \Illuminate\Support\Facades\Storage::disk('public')->exists($employee->employee->profile_picture))
                        <div class="mx-auto rounded-4 mb-3 overflow-hidden border shadow-xs"
                             style="width: 150px; height: 190px;">
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($employee->employee->profile_picture) }}"
                                 alt="Profile" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                        </div>
                    @else
                        <div class="avatar-preview mx-auto rounded-4 mb-3 d-flex align-items-center justify-content-center bg-light border"
                             style="width: 150px; height: 190px;">
                            <i class="bi bi-person-workspace text-muted display-4"></i>
                        </div>
                    @endif

                    <h5 class="fw-bold text-dark mb-1">{{ $employee->name }}</h5>
                    <p class="text-muted small mb-2">{{ $employee->email }}</p>

                    <div class="d-flex align-items-center justify-content-center gap-2 mt-2 flex-wrap">
                        @if($employee->hasRole('guru'))
                            <span class="badge-status badge-present">
                                <i class="bi bi-mortarboard-fill me-1"></i>Guru / Pendidik
                            </span>
                        @elseif($employee->hasRole('staff'))
                            <span class="badge-status badge-sick">
                                <i class="bi bi-person-badge-fill me-1"></i>Staf Tata Usaha
                            </span>
                        @else
                            <span class="badge bg-light text-secondary border px-2.5 py-1">Lainnya</span>
                        @endif

                        @php $employeeStatus = $employee->employee->employment_status ?? \App\Models\Employee::STATUS_ACTIVE; @endphp
                        @if($employeeStatus === \App\Models\Employee::STATUS_ACTIVE)
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1" style="font-size: 0.75rem;">
                                <i class="bi bi-check-circle-fill me-1"></i>Aktif
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1" style="font-size: 0.75rem;">
                                <i class="bi bi-pause-circle-fill me-1"></i>{{ \App\Models\Employee::statusLabels()[$employeeStatus] ?? $employeeStatus }}
                            </span>
                        @endif
                    </div>

                    @if(isset($employee->employee->position) && $employee->employee->position)
                        <div class="text-secondary small fw-medium mt-2">
                            <i class="bi bi-briefcase me-1 text-muted"></i>{{ $employee->employee->position }}
                        </div>
                    @endif

                    <div class="row g-2 mt-3 pt-3 border-top text-center">
                        <div class="col-6">
                            <span class="text-muted small d-block" style="font-size: 0.75rem;">NIP / NUPTK</span>
                            <strong class="text-dark">{{ $employee->employee->nip ?? '-' }}</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted small d-block" style="font-size: 0.75rem;">Terdaftar Sejak</span>
                            <strong class="text-dark">{{ $employee->created_at ? $employee->created_at->format('d M Y') : '-' }}</strong>
                        </div>
                    </div>

                    @if(isset($employee->employee->phone_number) && $employee->employee->phone_number)
                        <div class="mt-3 pt-3 border-top">
                            <a href="https://wa.me/62{{ preg_replace('/^0/', '', $employee->employee->phone_number) }}" target="_blank"
                               class="btn btn-outline-success w-100 py-2 d-flex align-items-center justify-content-center gap-2 shadow-xs" style="font-size: 0.85rem;">
                                <i class="bi bi-whatsapp fs-6"></i>Hubungi via WhatsApp
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Attendance Stats Card --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 text-dark fw-bold">
                        <i class="bi bi-pie-chart text-primary me-2"></i>Ringkasan Kehadiran
                    </h6>
                </div>
                <div class="card-body p-3">
                    @php
                        $attTotal = $employee->attendances()->count();
                        $attPresent = $employee->attendances()->where('status', 'present')->count();
                        $attLate = $employee->attendances()->where('status', 'present')->where('is_late', true)->count();
                        $attPermission = $employee->attendances()->whereIn('status', ['permission', 'sick'])->count();
                    @endphp
                    <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                        <span class="small text-muted">Total Presensi:</span>
                        <strong class="text-dark">{{ $attTotal }} kali</strong>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                        <span class="small text-muted">Hadir Tepat Waktu:</span>
                        <span class="badge bg-success-subtle text-success fw-bold">{{ max(0, $attPresent - $attLate) }} kali</span>
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

            {{-- Delete Card Option --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100 py-2"
                        data-delete-url="{{ route('admin.employees.destroy', $employee->id) }}"
                        data-delete-name="{{ $employee->name }}">
                        <i class="bi bi-trash3 me-1"></i>Hapus Pegawai Ini
                    </button>
                </div>
            </div>
        </div>

        {{-- ===== DETAIL INFORMASI & RIWAYAT TERAKHIR ===== --}}
        <div class="col-lg-8">
            {{-- Data Kepegawaian & Pribadi --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-dark fw-bold">
                        <i class="bi bi-card-text text-primary me-2"></i>Identitas & Data Pribadi Pegawai
                    </h6>
                    <span class="text-muted small">NIP: {{ $employee->employee->nip ?? '-' }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Nama Lengkap</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-semibold">
                                {{ $employee->name }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Alamat Email Resmi</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium d-flex align-items-center justify-content-between">
                                <span>{{ $employee->email }}</span>
                                <button type="button" class="btn btn-sm btn-link text-muted p-0" onclick="copyToClipboard('{{ $employee->email }}')" title="Salin Email">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">NIP / NUPTK</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium d-flex align-items-center justify-content-between">
                                <span>{{ $employee->employee->nip ?? '-' }}</span>
                                @if(isset($employee->employee->nip) && $employee->employee->nip)
                                    <button type="button" class="btn btn-sm btn-link text-muted p-0" onclick="copyToClipboard('{{ $employee->employee->nip }}')" title="Salin NIP">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Jabatan / Posisi Kerja</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium">
                                {{ $employee->employee->position ?? '-' }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Jenis Kelamin</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium">
                                @if(($employee->employee->gender ?? '') == 'male')
                                    <i class="bi bi-gender-male text-primary me-1"></i>Laki-laki
                                @elseif(($employee->employee->gender ?? '') == 'female')
                                    <i class="bi bi-gender-female text-danger me-1"></i>Perempuan
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Agama</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium">
                                {{ ucfirst($employee->employee->religion ?? '-') }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Tempat, Tanggal Lahir</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium">
                                {{ $employee->employee->place_of_birth ?? '-' }},
                                {{ isset($employee->employee->date_of_birth) ? \Carbon\Carbon::parse($employee->employee->date_of_birth)->translatedFormat('d F Y') : '-' }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Nomor WhatsApp / HP</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark fw-medium d-flex align-items-center justify-content-between">
                                <span>{{ !empty($employee->employee?->phone_number) ? '+62 ' . $employee->employee->phone_number : '-' }}</span>
                                @if(!empty($employee->employee?->phone_number))
                                    <a href="https://wa.me/62{{ preg_replace('/^0/', '', $employee->employee->phone_number) }}" target="_blank" class="btn btn-sm btn-link text-success p-0" title="Hubungi via WhatsApp">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-semibold">Alamat Tempat Tinggal</label>
                            <div class="p-2.5 rounded-3 bg-light border text-dark">
                                {{ $employee->employee->address ?? 'Alamat domisili belum dilengkapi.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Beban Mengajar & Linieritas Mapel (Khusus Guru) --}}
            @if($employee->hasRole('guru') && $workload)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-dark fw-bold">
                        <i class="bi bi-mortarboard-fill text-primary me-2"></i>Beban Mengajar & Jam Pelajaran (JP)
                    </h6>
                    <span class="badge bg-{{ $workload['status_color'] }}-subtle text-{{ $workload['status_color'] }} border border-{{ $workload['status_color'] }}-subtle px-3 py-1.5" style="font-size: 0.8rem;">
                        <i class="bi bi-shield-check me-1"></i>{{ $workload['status_label'] }}
                    </span>
                </div>
                <div class="card-body p-4">
                    {{-- Summary Counter Cards --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 bg-light border text-center">
                                <span class="text-muted small d-block mb-1">Total Jam Tatap Muka</span>
                                <div class="d-flex align-items-baseline justify-content-center gap-1">
                                    <h3 class="fw-bold mb-0 text-primary">{{ $workload['total_jp'] }}</h3>
                                    <span class="text-muted small fw-semibold">JP / minggu</span>
                                </div>
                                <span class="badge bg-white border text-muted mt-2 px-2 py-0.5" style="font-size: 0.72rem;">
                                    1 JP = 45 menit
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 bg-light border text-center">
                                <span class="text-muted small d-block mb-1">Total Jam Riil Mengajar</span>
                                <div class="d-flex align-items-baseline justify-content-center gap-1">
                                    <h3 class="fw-bold mb-0 text-dark">{{ $workload['total_hours_real'] }}</h3>
                                    <span class="text-muted small fw-semibold">Jam / minggu</span>
                                </div>
                                <span class="badge bg-white border text-muted mt-2 px-2 py-0.5" style="font-size: 0.72rem;">
                                    {{ $workload['total_minutes'] }} menit pembelajaran
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 bg-light border text-center">
                                <span class="text-muted small d-block mb-1">Target Regulasi (Batas Min.)</span>
                                <div class="d-flex align-items-baseline justify-content-center gap-1">
                                    <h3 class="fw-bold mb-0 text-{{ $workload['status_color'] }}">{{ $workload['target_percentage'] }}%</h3>
                                    <span class="text-muted small fw-semibold">dari 24 JP</span>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-{{ $workload['status_color'] }}" role="progressbar"
                                         style="width: {{ $workload['target_percentage'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Breakdown Mapel yang Diampu --}}
                    <h6 class="text-dark fw-bold mb-3 small text-uppercase" style="letter-spacing: 0.5px;">
                        <i class="bi bi-journal-bookmark me-1 text-primary"></i>Distribusi Mata Pelajaran
                    </h6>
                    @if(empty($workload['subjects']))
                        <div class="alert alert-warning border-0 d-flex align-items-center mb-4">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                            <span class="small">Belum ada alokasi jadwal pelajaran aktif untuk guru ini. Silakan atur di menu Jadwal Pelajaran.</span>
                        </div>
                    @else
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr style="font-size: 0.8rem;">
                                        <th>Mata Pelajaran</th>
                                        <th style="width: 130px;">Kategori Linier</th>
                                        <th style="width: 120px;">Kelas Rombel</th>
                                        <th style="width: 100px;" class="text-center">Total Slot</th>
                                        <th style="width: 110px;" class="text-end">Alokasi JP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($workload['subjects'] as $subItem)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle" style="width: 10px; height: 10px; background-color: {{ $subItem['subject']->color_code }};"></div>
                                                <div>
                                                    <span class="fw-semibold text-dark">{{ $subItem['subject']->name }}</span>
                                                    <code class="small text-muted ms-1">({{ $subItem['subject']->code }})</code>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($subItem['is_primary'])
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size: 0.75rem;">
                                                    <i class="bi bi-star-fill me-1"></i>Mapel Utama
                                                </span>
                                            @else
                                                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1" style="font-size: 0.75rem;">
                                                    <i class="bi bi-arrow-left-right me-1"></i>Serumpun
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($subItem['classrooms'] as $clsName)
                                                    <span class="badge bg-secondary-subtle text-secondary px-1.5 py-0.5" style="font-size: 0.72rem;">{{ $clsName }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="text-center fw-medium text-dark">{{ $subItem['slots_count'] }} kali</td>
                                        <td class="text-end">
                                            <strong class="text-primary">{{ $subItem['total_jp'] }} JP</strong>
                                            <span class="text-muted small d-block" style="font-size: 0.72rem;">{{ $subItem['total_minutes'] }} mnt</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- Hari Mengajar Breakdown --}}
                    <h6 class="text-dark fw-bold mb-2 small text-uppercase" style="letter-spacing: 0.5px;">
                        <i class="bi bi-calendar-week me-1 text-primary"></i>Distribusi Hari KBM (Full Day School: Senin - Jumat)
                    </h6>
                    <div class="row g-2">
                        @foreach($workload['days'] as $dayNum => $dayData)
                        <div class="col">
                            <div class="p-2.5 rounded-3 border text-center {{ $dayData['jp'] > 0 ? 'bg-white shadow-xs' : 'bg-light opacity-75' }}">
                                <span class="small fw-semibold d-block text-dark">{{ $dayData['name'] }}</span>
                                <strong class="fs-5 d-block text-{{ $dayData['jp'] > 0 ? 'primary' : 'muted' }} my-1">{{ $dayData['jp'] }} <span style="font-size: 0.7rem;">JP</span></strong>
                                <span class="text-muted small" style="font-size: 0.7rem;">{{ $dayData['slots'] }} sesi</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Riwayat Presensi Terakhir --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-dark fw-bold">
                        <i class="bi bi-clock-history text-primary me-2"></i>Riwayat Presensi Terbaru Pegawai Ini
                    </h6>
                    <a href="{{ route('admin.attendances.index', ['search' => $employee->name]) }}"
                       class="btn btn-sm btn-outline-primary py-1 px-2.5" style="font-size: 0.78rem;">
                        Lihat Semua Riwayat →
                    </a>
                </div>
                <div class="card-body p-0">
                    @php
                        $recentAttendances = $employee->attendances()->latest('recorded_at')->take(5)->get() ?? collect();
                    @endphp

                    @if($recentAttendances->isEmpty())
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted fs-3 mb-2 d-block"></i>
                            <p class="text-muted small mb-0">Belum ada rekaman presensi untuk pegawai ini.</p>
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
