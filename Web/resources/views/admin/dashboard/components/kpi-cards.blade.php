{{-- 4 Metrik Kartu KPI Utama --}}
<div class="row g-3 mb-4">
    {{-- Card 1: Hadir Hari Ini --}}
    <div class="col-xl-3 col-sm-6">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-body p-3 p-md-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Hadir Hari Ini</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: #eff6ff; color: #2563eb;">
                        <i class="bi bi-person-check fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <h2 id="ws-present-count" class="fw-bold text-dark mb-0" style="font-size: 1.85rem; letter-spacing: -0.5px;">{{ $stats['total_present'] ?? $todayPresensiCount }}</h2>
                    @php
                        $rate = $totalStudents > 0 ? round((($stats['student_present'] ?? $stats['total_present'] ?? 0) / $totalStudents) * 100) : 0;
                    @endphp
                    <span class="badge-status badge-present ms-auto" style="font-size: 0.72rem;">
                        {{ $rate }}% Siswa
                    </span>
                </div>
                <div class="d-flex align-items-center justify-content-between text-muted" style="font-size: 0.76rem;">
                    <span>Siswa: <strong class="text-dark">{{ $stats['student_present'] ?? 0 }}</strong>/{{ $totalStudents }}</span>
                    <span>Pegawai: <strong class="text-dark">{{ $stats['employee_present'] ?? 0 }}</strong>/{{ $totalEmployees }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Card 2: Terlambat --}}
    <div class="col-xl-3 col-sm-6">
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-body p-3 p-md-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Terlambat</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: #fffbeb; color: #d97706;">
                        <i class="bi bi-clock-history fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <h2 id="ws-late-count" class="fw-bold text-dark mb-0" style="font-size: 1.85rem; letter-spacing: -0.5px;">{{ $stats['total_late'] ?? 0 }}</h2>
                    <span class="badge-status badge-late ms-auto" style="font-size: 0.72rem;">
                        Toleransi {{ $setting['late_tolerance_minutes'] ?? 15 }}m
                    </span>
                </div>
                <div class="text-muted" style="font-size: 0.76rem;">
                    Batas masuk: <strong class="text-dark">{{ $setting['check_in_end'] ?? '07:00' }} WIB</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Card 3: Izin / Sakit Menunggu Persetujuan --}}
    <div class="col-xl-3 col-sm-6">
        <a href="{{ route('admin.attendances.index', ['approval' => 'pending', 'date' => null]) }}" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm rounded-3">
                <div class="card-body p-3 p-md-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Izin / Sakit Pending</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: #f0f9ff; color: #0284c7;">
                            <i class="bi bi-file-earmark-medical fs-5"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <h2 id="ws-pending-approval-count" class="fw-bold text-dark mb-0" style="font-size: 1.85rem; letter-spacing: -0.5px;">{{ $pendingApprovals }}</h2>
                        @if($pendingApprovals > 0)
                            <span class="badge-status badge-sick ms-auto" style="font-size: 0.72rem;">
                                Perlu Tindakan
                            </span>
                        @else
                            <span class="badge-status badge-present ms-auto" style="font-size: 0.72rem;">
                                Selesai
                            </span>
                        @endif
                    </div>
                    <div class="text-muted" style="font-size: 0.76rem;">
                        {{ $pendingApprovals > 0 ? 'Menunggu verifikasi admin' : 'Semua pengajuan telah diproses' }}
                    </div>
                </div>
            </div>
        </a>
    </div>

    {{-- Card 4: Belum Hadir / Alfa --}}
    <div class="col-xl-3 col-sm-6">
        @php
            $absentEst = max(0, ($totalStudents ?? 0) - ($stats['student_present'] ?? 0) - ($stats['total_permission'] ?? 0));
        @endphp
        <div class="card h-100 border-0 shadow-sm rounded-3">
            <div class="card-body p-3 p-md-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Belum Hadir (Siswa)</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: #fff1f2; color: #e11d48;">
                        <i class="bi bi-person-x fs-5"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2 mb-2">
                    <h2 class="fw-bold text-dark mb-0" style="font-size: 1.85rem; letter-spacing: -0.5px;">{{ $absentEst }}</h2>
                    <span class="badge-status badge-absent ms-auto" style="font-size: 0.72rem;">
                        Auto-Alfa 15:00
                    </span>
                </div>
                <div class="text-muted" style="font-size: 0.76rem;">
                    Belum presensi hingga saat ini
                </div>
            </div>
        </div>
    </div>
</div>
