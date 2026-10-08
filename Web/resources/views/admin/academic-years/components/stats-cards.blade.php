{{-- Mini KPI Summary Cards: Tahun Ajaran --}}
<div class="row g-3 mb-4">
    {{-- Card 1: Total --}}
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Periode</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: #eff6ff; color: #2563eb;">
                        <i class="bi bi-calendar-range fs-6"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold text-dark mb-0" style="font-size: 1.75rem; letter-spacing: -0.5px;">{{ $stats['total'] }}</h3>
                    <span class="text-muted small ms-1">Tahun Ajaran</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Card 2: Aktif --}}
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Sedang Berjalan</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: #ecfdf5; color: #059669;">
                        <i class="bi bi-check-circle fs-6"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold text-success mb-0" style="font-size: 1.75rem; letter-spacing: -0.5px;">{{ $stats['active'] }}</h3>
                    <span class="badge-status badge-present ms-1" style="font-size: 0.72rem;">Aktif</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Card 3: Akan Datang --}}
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Akan Datang</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: #f0f9ff; color: #0284c7;">
                        <i class="bi bi-hourglass-split fs-6"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold text-primary mb-0" style="font-size: 1.75rem; letter-spacing: -0.5px;">{{ $stats['upcoming'] }}</h3>
                    <span class="badge-status badge-permission ms-1" style="font-size: 0.72rem;">Dipersiapkan</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Card 4: Diarsipkan --}}
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.05em;">Diarsipkan</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 34px; height: 34px; background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0;">
                        <i class="bi bi-archive fs-6"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold text-secondary mb-0" style="font-size: 1.75rem; letter-spacing: -0.5px;">{{ $stats['archived'] }}</h3>
                    <span class="text-muted small ms-1">Arsip</span>
                </div>
            </div>
        </div>
    </div>
</div>
