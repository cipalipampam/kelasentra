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
                <span class="text-muted small">Jadwal Pelajaran</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <h1 class="fw-bold text-dark mb-0" style="font-size: 1.65rem; letter-spacing: -0.4px;">
                    Jadwal Pelajaran
                </h1>
                {{-- @if($activeClassroom)
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1" style="font-size: 0.78rem;">
                        {{ $activeClassroom->name }}
                    </span>
                @endif --}}
            </div>
            {{-- <p class="text-muted small mb-0 mt-1">
                Penyusunan jadwal KBM mingguan (Full Day School: Senin – Jumat 07.00 s.d. 16.00 WIB) dengan validasi linieritas & anti-bentrok.
            </p> --}}
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex flex-wrap justify-content-md-end gap-2">
            <button class="btn btn-primary shadow-sm py-2 px-3" data-bs-toggle="modal" data-bs-target="#createScheduleModal">
                <i class="bi bi-plus-circle-fill me-1"></i>Tambah Jadwal Baru
            </button>
    </div>

    {{-- ===== ALERT NOTIFICATIONS ===== --}}
    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3 p-3 mb-4 d-flex align-items-center justify-content-between" style="background-color: #f0fdf4; border-left: 4px solid #16a34a !important;">
            <div class="d-flex align-items-center gap-2.5 text-success">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <span class="fw-semibold small">{{ session('success') }}</span>
            </div>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-3 p-3.5 mb-4" style="background-color: #fef2f2; border-left: 4px solid #dc2626 !important;">
            <div class="d-flex align-items-start justify-content-between">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center shrink-0 mt-0.5"
                         style="width: 36px; height: 36px; background: #fee2e2; color: #dc2626;">
                        <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-danger mb-1" style="font-size: 0.92rem;">Gagal Menyimpan Jadwal KBM</h6>
                        <p class="text-secondary small mb-2">Terjadi kesalahan validasi atau pelanggaran aturan operasional:</p>
                        <ul class="mb-0 ps-3 text-dark small">
                            @foreach ($errors->all() as $error)
                                <li class="fw-medium text-danger">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-sm ms-3" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

    {{-- ===== KPI MINI CARDS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small d-block">Total Slot KBM</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1">{{ $schedules->count() }}</h4>
                        {{-- <span class="text-muted" style="font-size: 0.75rem;">Sesi pembelajaran di kelas terpilih</span> --}}
                    </div>
                    <div class="rounded-3 d-flex align-items-center justify-content-center"
                         style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb;">
                        <i class="bi bi-calendar3 fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small d-block">Alokasi Beban Tatap Muka</span>
                        <h4 class="fw-bold text-primary mb-0 mt-1">{{ $totalJp }} JP</h4>
                    </div>
                    <div class="rounded-3 d-flex align-items-center justify-content-center"
                         style="width: 44px; height: 44px; background: #f0fdf4; color: #16a34a;">
                        <i class="bi bi-clock-history fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small d-block">Kepatuhan Operasional</span>
                        <h4 class="fw-bold text-dark mb-0 mt-1">Senin – Jumat</h4>
                    </div>
                    <div class="rounded-3 d-flex align-items-center justify-content-center"
                         style="width: 44px; height: 44px; background: #faf5ff; color: #9333ea;">
                        <i class="bi bi-shield-check fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== FILTER BAR ===== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('admin.schedules.index') }}" class="js-live-filter row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold mb-1">Tahun Ajaran</label>
                    <select name="academic_year_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" @selected($selectedAcademicYearId == $year->id)>
                                {{ $year->name }} — {{ $year->statusLabel() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label text-muted small fw-semibold mb-1">Pilih Rombel / Kelas</label>
                    <select name="classroom_id" class="form-select form-select-sm">
                        @foreach($classrooms as $cls)
                            <option value="{{ $cls->id }}" {{ $selectedClassroomId == $cls->id ? 'selected' : '' }}>
                                {{ $cls->name }} (Tingkat {{ $cls->level }} - {{ $cls->major ?? 'Umum' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-semibold mb-1">Filter Hari</label>
                    <select name="day_of_week" class="form-select form-select-sm">
                        <option value="">Semua Hari (Senin - Jumat)</option>
                        <option value="1" {{ $selectedDay == 1 ? 'selected' : '' }}>Senin</option>
                        <option value="2" {{ $selectedDay == 2 ? 'selected' : '' }}>Selasa</option>
                        <option value="3" {{ $selectedDay == 3 ? 'selected' : '' }}>Rabu</option>
                        <option value="4" {{ $selectedDay == 4 ? 'selected' : '' }}>Kamis</option>
                        <option value="5" {{ $selectedDay == 5 ? 'selected' : '' }}>Jumat</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== MATRIKS JADWAL PER HARI ===== --}}
    <div class="js-live-results">
    <div class="row g-4">
        @php
            $daysMeta = [
                1 => ['name' => 'Senin', 'icon' => 'bi-sun-fill', 'color' => '#3b82f6'],
                2 => ['name' => 'Selasa', 'icon' => 'bi-brightness-high-fill', 'color' => '#10b981'],
                3 => ['name' => 'Rabu', 'icon' => 'bi-cloud-sun-fill', 'color' => '#f59e0b'],
                4 => ['name' => 'Kamis', 'icon' => 'bi-lightning-charge-fill', 'color' => '#8b5cf6'],
                5 => ['name' => 'Jumat', 'icon' => 'bi-heart-fill', 'color' => '#ec4899'],
            ];
        @endphp

        @foreach($daysMeta as $dNum => $dMeta)
            @if(!$selectedDay || $selectedDay == $dNum)
            <div class="{{ $selectedDay ? 'col-12' : 'col-lg-6 col-xl-4' }}">
                <div class="card border-0 shadow-sm h-100 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                  style="width: 28px; height: 28px; background-color: {{ $dMeta['color'] }}15; color: {{ $dMeta['color'] }};">
                                <i class="bi {{ $dMeta['icon'] }}" style="font-size: 0.85rem;"></i>
                            </span>
                            <h6 class="mb-0 text-dark fw-bold">{{ $dMeta['name'] }}</h6>
                        </div>
                        <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 0.75rem;">
                            {{ $daySchedules[$dNum]->count() }} Sesi · {{ $daySchedules[$dNum]->sum('jp_count') }} JP
                        </span>
                    </div>
                    <div class="card-body p-3">
                        @if($daySchedules[$dNum]->isEmpty())
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-minus fs-3 opacity-50 d-block mb-2"></i>
                                <span class="small">Belum ada jadwal KBM di hari {{ $dMeta['name'] }}.</span>
                            </div>
                        @else
                        @php
                            $timelineItems = collect();

                            foreach($daySchedules[$dNum] as $item) {
                                $timelineItems->push([
                                    'type' => 'kbm',
                                    'start_time' => substr($item->start_time, 0, 5),
                                    'data' => $item,
                                ]);
                            }

                            // Istirahat I (09:30 - 10:00 WIB)
                            $timelineItems->push([
                                'type' => 'break_1',
                                'start_time' => '09:30',
                                'end_time' => '10:00',
                                'title' => 'Istirahat I (Dhuha & Camilan)',
                                'duration' => '30 Mnt',
                            ]);

                            // Istirahat II (Ishoma Utama)
                            $timelineItems->push([
                                'type' => 'break_2',
                                'start_time' => ($dNum == 5 ? '11:30' : '11:45'),
                                'end_time' => '13:00',
                                'title' => ($dNum == 5 ? 'Ishoma Sholat Jumat & Makan Siang' : 'Ishoma Utama & Sholat Dzuhur'),
                                'duration' => ($dNum == 5 ? '90 Mnt' : '75 Mnt'),
                            ]);

                            $timelineItems = $timelineItems->sortBy('start_time');
                        @endphp

                        <div class="d-flex flex-column gap-2">
                            @foreach($timelineItems as $timeline)
                                @if($timeline['type'] === 'kbm')
                                    @php $item = $timeline['data']; @endphp
                                    <div class="p-2.5 rounded-3 border bg-white position-relative hover-shadow transition-all"
                                         style="border-left: 4px solid {{ $item->subject->color_code }} !important;">
                                        <div class="d-flex align-items-start justify-content-between mb-1">
                                            <div class="d-flex align-items-center gap-1.5">
                                                <span class="badge bg-secondary-subtle text-secondary px-2 py-0.5" style="font-size: 0.72rem; font-family: monospace;">
                                                    <i class="bi bi-clock me-1"></i>{{ substr($item->start_time, 0, 5) }} - {{ substr($item->end_time, 0, 5) }}
                                                </span>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-1.5 py-0.5" style="font-size: 0.7rem;">
                                                    {{ $item->jp_count }} JP
                                                </span>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light border-0 py-0 px-1 text-muted" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-1" style="font-size: 0.8rem;">
                                                    <li>
                                                        <button class="dropdown-item py-1.5" data-bs-toggle="modal" data-bs-target="#editScheduleModal{{ $item->id }}">
                                                            <i class="bi bi-pencil-square text-primary me-2"></i>Edit Slot
                                                        </button>
                                                    </li>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <button class="dropdown-item py-1.5 text-danger"
                                                                data-delete-url="{{ route('admin.schedules.destroy', $item->id) }}"
                                                                data-delete-name="Jadwal {{ $item->subject->name }} ({{ $dMeta['name'] }} {{ substr($item->start_time,0,5) }})">
                                                            <i class="bi bi-trash3 me-2"></i>Hapus Jadwal
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <h6 class="text-dark fw-bold mb-1" style="font-size: 0.9rem;">
                                            {{ $item->subject->name }}
                                        </h6>

                                        <div class="d-flex align-items-center justify-content-between mt-2 pt-1 border-top" style="font-size: 0.76rem;">
                                            <div class="text-truncate me-2 text-dark">
                                                <i class="bi bi-person me-1 text-muted"></i>{{ $item->teacher->name }}
                                            </div>
                                            @if($item->room)
                                                <span class="text-muted text-nowrap">
                                                    <i class="bi bi-geo-alt me-0.5"></i>{{ $item->room }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($timeline['type'] === 'break_1')
                                    <div class="p-2 rounded-3 border border-dashed d-flex align-items-center justify-content-between"
                                         style="background-color: #fffbe6; border-color: #ffe58f !important;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge text-dark px-2 py-0.5" style="font-size: 0.72rem; font-family: monospace; background-color: #fff1b8; border: 1px solid #ffe58f;">
                                                <i class="bi bi-cup-hot-fill text-warning me-1"></i>{{ $timeline['start_time'] }} - {{ $timeline['end_time'] }}
                                            </span>
                                            <span class="fw-semibold text-dark" style="font-size: 0.8rem;">{{ $timeline['title'] }}</span>
                                        </div>
                                        <span class="badge bg-white text-muted border px-1.5 py-0.5" style="font-size: 0.68rem;">{{ $timeline['duration'] }}</span>
                                    </div>
                                @elseif($timeline['type'] === 'break_2')
                                    <div class="p-2 rounded-3 border border-dashed d-flex align-items-center justify-content-between"
                                         style="background-color: #e6f7ff; border-color: #91caff !important;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge text-dark px-2 py-0.5" style="font-size: 0.72rem; font-family: monospace; background-color: #bae0ff; border: 1px solid #91caff;">
                                                <i class="bi bi-moon-stars-fill text-primary me-1"></i>{{ $timeline['start_time'] }} - {{ $timeline['end_time'] }}
                                            </span>
                                            <span class="fw-semibold text-dark" style="font-size: 0.8rem;">{{ $timeline['title'] }}</span>
                                        </div>
                                        <span class="badge bg-white text-muted border px-1.5 py-0.5" style="font-size: 0.68rem;">{{ $timeline['duration'] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        @endforeach
    </div>
    </div>{{-- /.js-live-results --}}

</div>
@endsection

@push('modals')
{{-- CREATE MODAL --}}
<div class="modal fade" id="createScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <form action="{{ route('admin.schedules.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb;">
                            <i class="bi bi-calendar-plus-fill fs-5"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-dark mb-0">Tambah Jadwal KBM Baru</h6>
                            <p class="text-muted small mb-0">Rombel: <strong>{{ $activeClassroom?->name }}</strong></p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body py-3 px-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-dark fw-semibold small">Rombel / Kelas <span class="text-danger">*</span></label>
                            <select name="classroom_id" class="form-select" required>
                                @foreach($classrooms as $cls)
                                    <option value="{{ $cls->id }}" {{ $selectedClassroomId == $cls->id ? 'selected' : '' }}>
                                        {{ $cls->name }} (Tingkat {{ $cls->level }} - {{ $cls->major ?? 'Umum' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-semibold small">Hari Pembelajaran <span class="text-danger">*</span></label>
                            <select name="day_of_week" class="form-select" required>
                                <option value="1" {{ old('day_of_week', 1) == 1 ? 'selected' : '' }}>Senin</option>
                                <option value="2" {{ old('day_of_week') == 2 ? 'selected' : '' }}>Selasa</option>
                                <option value="3" {{ old('day_of_week') == 3 ? 'selected' : '' }}>Rabu</option>
                                <option value="4" {{ old('day_of_week') == 4 ? 'selected' : '' }}>Kamis</option>
                                <option value="5" {{ old('day_of_week') == 5 ? 'selected' : '' }}>Jumat</option>
                            </select>
                            <div class="form-text">Full Day School (Senin - Jumat)</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-semibold small">Ruang Belajar / Lab</label>
                            <input type="text" name="room" class="form-control" placeholder="R.101 / Lab Komputer" value="{{ old('room') }}" maxlength="50">
                        </div>

                        <div class="col-12">
                            <label class="form-label text-dark fw-semibold small">Mata Pelajaran <span class="text-danger">*</span></label>
                            <select name="subject_id" id="create_subject_id" class="form-select" required onchange="handleSubjectChange(this.value, 'create_teacher_id')">
                                <option value="" disabled selected>— Pilih Mata Pelajaran —</option>
                                @foreach($subjects->groupBy('cluster') as $clusterKey => $clusterSubs)
                                    <optgroup label="Rumpun {{ strtoupper($clusterKey) }}">
                                        @foreach($clusterSubs as $sub)
                                            <option value="{{ $sub->id }}" {{ old('subject_id') == $sub->id ? 'selected' : '' }}>
                                                {{ $sub->name }} ({{ $sub->code }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-dark fw-semibold small">Guru Pengampu (Linier) <span class="text-danger">*</span></label>
                            <select name="teacher_id" id="create_teacher_id" class="form-select" required>
                                <option value="" disabled selected>— Pilih Mata Pelajaran Terlebih Dahulu —</option>
                                @foreach($teachers as $tch)
                                    <option value="{{ $tch->id }}" {{ old('teacher_id') == $tch->id ? 'selected' : '' }}>
                                        {{ $tch->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Opsi guru difilter secara otomatis berdasarkan linieritas mapel</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-semibold small">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="create_start_time" class="form-control" value="{{ old('start_time', '07:15') }}" required onchange="calculateJpPreview('create')">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-semibold small">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="create_end_time" class="form-control" value="{{ old('end_time', '08:45') }}" required onchange="calculateJpPreview('create')">
                        </div>

                        <div class="col-12">
                            <div class="p-2.5 rounded-3 bg-light border d-flex align-items-center justify-content-between">
                                <span class="small text-muted">Estimasi Jam Pelajaran (JP):</span>
                                <span class="badge bg-primary text-white fs-6" id="create_jp_preview">2 JP (90 Menit)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-sm btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i>Simpan Jadwal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- EDIT MODALS --}}
@foreach($schedules as $sched)
<div class="modal fade" id="editScheduleModal{{ $sched->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <form action="{{ route('admin.schedules.update', $sched->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 44px; height: 44px; background: #eff6ff; color: #2563eb;">
                            <i class="bi bi-pencil-square fs-5"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold text-dark mb-0">Edit Jadwal Pembelajaran</h6>
                            <p class="text-muted small mb-0">{{ $sched->classroom->name }} · {{ $sched->day_name }}</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
                <div class="modal-body py-3 px-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-dark fw-semibold small">Rombel / Kelas <span class="text-danger">*</span></label>
                            <select name="classroom_id" class="form-select" required>
                                @foreach($classrooms as $cls)
                                    <option value="{{ $cls->id }}" {{ old('classroom_id', $sched->classroom_id) == $cls->id ? 'selected' : '' }}>
                                        {{ $cls->name }} (Tingkat {{ $cls->level }} - {{ $cls->major ?? 'Umum' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-semibold small">Hari Pembelajaran <span class="text-danger">*</span></label>
                            <select name="day_of_week" class="form-select" required>
                                <option value="1" {{ old('day_of_week', $sched->day_of_week) == 1 ? 'selected' : '' }}>Senin</option>
                                <option value="2" {{ old('day_of_week', $sched->day_of_week) == 2 ? 'selected' : '' }}>Selasa</option>
                                <option value="3" {{ old('day_of_week', $sched->day_of_week) == 3 ? 'selected' : '' }}>Rabu</option>
                                <option value="4" {{ old('day_of_week', $sched->day_of_week) == 4 ? 'selected' : '' }}>Kamis</option>
                                <option value="5" {{ old('day_of_week', $sched->day_of_week) == 5 ? 'selected' : '' }}>Jumat</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-semibold small">Ruang Belajar / Lab</label>
                            <input type="text" name="room" class="form-control" placeholder="R.101 / Lab Komputer"
                                   value="{{ old('room', $sched->room) }}" maxlength="50">
                        </div>

                        <div class="col-12">
                            <label class="form-label text-dark fw-semibold small">Mata Pelajaran <span class="text-danger">*</span></label>
                            <select name="subject_id" id="edit_subject_id_{{ $sched->id }}" class="form-select" required
                                    onchange="handleSubjectChange(this.value, 'edit_teacher_id_{{ $sched->id }}')">
                                @foreach($subjects->groupBy('cluster') as $clusterKey => $clusterSubs)
                                    <optgroup label="Rumpun {{ strtoupper($clusterKey) }}">
                                        @foreach($clusterSubs as $sub)
                                            <option value="{{ $sub->id }}" {{ old('subject_id', $sched->subject_id) == $sub->id ? 'selected' : '' }}>
                                                {{ $sub->name }} ({{ $sub->code }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-dark fw-semibold small">Guru Pengampu (Linier) <span class="text-danger">*</span></label>
                            <select name="teacher_id" id="edit_teacher_id_{{ $sched->id }}" class="form-select" required>
                                @foreach($teachers as $tch)
                                    <option value="{{ $tch->id }}" {{ old('teacher_id', $sched->teacher_id) == $tch->id ? 'selected' : '' }}>
                                        {{ $tch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-semibold small">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="edit_start_time_{{ $sched->id }}" class="form-control"
                                   value="{{ old('start_time', substr($sched->start_time, 0, 5)) }}" required
                                   onchange="calculateJpPreview('edit_{{ $sched->id }}')">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark fw-semibold small">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="edit_end_time_{{ $sched->id }}" class="form-control"
                                   value="{{ old('end_time', substr($sched->end_time, 0, 5)) }}" required
                                   onchange="calculateJpPreview('edit_{{ $sched->id }}')">
                        </div>

                        <div class="col-12">
                            <div class="p-2.5 rounded-3 bg-light border d-flex align-items-center justify-content-between">
                                <span class="small text-muted">Estimasi Jam Pelajaran (JP):</span>
                                <span class="badge bg-primary text-white fs-6" id="edit_{{ $sched->id }}_jp_preview">
                                    {{ $sched->jp_count }} JP ({{ $sched->duration_in_minutes }} Menit)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-sm btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold shadow-sm">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endpush

@push('scripts')
<script>
// Filter guru otomatis berdasarkan linieritas mapel
function handleSubjectChange(subjectId, teacherSelectId) {
    const teacherSelect = document.getElementById(teacherSelectId);
    if (!teacherSelect || !subjectId) return;

    teacherSelect.disabled = true;
    teacherSelect.innerHTML = '<option value="">Memuat daftar guru linier...</option>';

    fetch(`/admin/schedules/teachers-by-subject/${subjectId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(json => {
        teacherSelect.disabled = false;
        teacherSelect.innerHTML = '';

        if (!json.success || json.data.length === 0) {
            teacherSelect.innerHTML = '<option value="" disabled selected>— Tidak ada guru yang linier dengan mapel ini —</option>';
            return;
        }

        teacherSelect.innerHTML = '<option value="" disabled selected>— Pilih Guru Pengampu —</option>';
        json.data.forEach(teacher => {
            const opt = document.createElement('option');
            opt.value = teacher.id;
            opt.textContent = `${teacher.name} ${teacher.is_primary ? '(Mapel Utama)' : '(Serumpun)'}`;
            teacherSelect.appendChild(opt);
        });
    })
    .catch(err => {
        console.error(err);
        teacherSelect.disabled = false;
        teacherSelect.innerHTML = '<option value="" disabled>Gagal memuat daftar guru</option>';
    });
}

// Live calculation JP (1 JP = 45 menit)
function calculateJpPreview(prefix) {
    const startInput = document.getElementById(prefix + '_start_time');
    const endInput = document.getElementById(prefix + '_end_time');
    const previewEl = document.getElementById(prefix + '_jp_preview');

    if (!startInput || !endInput || !previewEl) return;

    const startVal = startInput.value;
    const endVal = endInput.value;

    if (!startVal || !endVal) return;

    const [startH, startM] = startVal.split(':').map(Number);
    const [endH, endM] = endVal.split(':').map(Number);

    const totalMinutes = (endH * 60 + endM) - (startH * 60 + startM);
    if (totalMinutes <= 0) {
        previewEl.className = 'badge bg-danger text-white fs-6';
        previewEl.textContent = 'Jam selesai tidak valid!';
        return;
    }

    const jp = Math.round(totalMinutes / 45);
    previewEl.className = 'badge bg-primary text-white fs-6';
    previewEl.textContent = `${jp} JP (${totalMinutes} Menit)`;
}
</script>
@endpush

