{{-- Header Dashboard: Badge Sekolah, Judul Halaman, Jam Digital & Status Live Sync --}}
<div class="row mb-4 align-items-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge px-2 py-1 text-primary fw-semibold" style="background-color: #eff6ff; border: 1px solid #bfdbfe; font-size: 0.75rem;">
                <i class="bi bi-building me-1"></i>{{ $setting['school_name'] ?? 'Kelasentra School' }}
            </span>
            <span class="text-muted small">•</span>
            <span class="text-muted small">Tahun Ajaran {{ date('Y') }}/{{ date('Y') + 1 }}</span>
        </div>
        <h1 class="fw-bold text-dark mb-1" style="font-size: 1.65rem; letter-spacing: -0.4px;">
            Dashboard Overview
        </h1>
        <p class="text-muted small mb-0">
            Selamat datang kembali, <strong class="text-dark">{{ Auth::user()->name ?? 'Administrator' }}</strong>. Pantau presensi, geofence, dan persetujuan secara real-time.
        </p>
    </div>
    <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
        <div class="d-inline-flex flex-wrap align-items-center gap-2">
            {{-- Date & Live Clock --}}
            <div class="d-inline-flex align-items-center bg-white px-3 py-2 rounded-3 border shadow-sm">
                <i class="bi bi-calendar3 me-2 text-primary"></i>
                <span class="text-secondary fw-medium small">{{ now()->translatedFormat('l, d F Y') }}</span>
                <span class="text-muted mx-2">|</span>
                <i class="bi bi-clock me-1 text-muted"></i>
                <span id="dashboard-clock" class="text-dark fw-bold small">{{ now()->format('H:i') }}</span>
                <span class="text-muted small ms-1">WIB</span>
            </div>
            {{-- WebSocket Live Indicator --}}
            <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 bg-white border shadow-sm">
                <span id="ws-live-dot" class="pulse-dot"></span>
                <span class="small fw-semibold text-success">Live Sync</span>
            </div>
        </div>
    </div>
</div>

