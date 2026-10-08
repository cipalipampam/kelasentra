{{-- Mini KPI Summary Cards: Rombel & Status --}}
@php
    $totalCount = $stats['total'] ?? $classrooms->total();
    $activeCount = $stats['active'] ?? $classrooms->getCollection()->where('is_active', true)->count();
    $inactiveCount = $stats['inactive'] ?? $classrooms->getCollection()->where('is_active', false)->count();
@endphp

<div class="row g-3 mb-4">
    {{-- Card 1: Total Rombel --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Rombel</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: #eff6ff; color: #2563eb;">
                        <i class="bi bi-diagram-3 fs-6"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold text-dark mb-0" style="font-size: 1.75rem; letter-spacing: -0.5px;">{{ $totalCount }}</h3>
                    <span class="text-muted small ms-1">Kelas Terdaftar</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Card 2: Rombel Aktif --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Rombel Aktif</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: #ecfdf5; color: #059669;">
                        <i class="bi bi-check-circle fs-6"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold text-success mb-0" style="font-size: 1.75rem; letter-spacing: -0.5px;">{{ $activeCount }}</h3>
                    <span class="badge-status badge-present ms-1" style="font-size: 0.72rem;">Sedang Berjalan</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Card 3: Tidak Aktif --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Tidak Aktif / Diarsipkan</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0;">
                        <i class="bi bi-pause-circle fs-6"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold text-secondary mb-0" style="font-size: 1.75rem; letter-spacing: -0.5px;">{{ $inactiveCount }}</h3>
                    <span class="badge-status badge-absent ms-1" style="font-size: 0.72rem;">Nonaktif</span>
                </div>
            </div>
        </div>
    </div>
</div>

