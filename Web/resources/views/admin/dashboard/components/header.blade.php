{{-- Header Bar Minimalis: Status Waktu & Live Sync --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-0" style="font-size: 1.25rem; letter-spacing: -0.3px;">
            Ringkasan Kehadiran
        </h4>
    </div>
    <div class="d-inline-flex align-items-center gap-2 bg-white px-3 py-1.5 rounded-pill border shadow-xs small">
        <i class="bi bi-calendar3 text-muted"></i>
        <span class="text-secondary fw-medium">{{ now()->translatedFormat('l, d F Y') }}</span>
        <span class="text-muted opacity-50">|</span>
        <i class="bi bi-clock text-muted"></i>
        <span id="dashboard-clock" class="text-dark fw-bold">{{ now()->format('H:i') }}</span>
        <span class="text-muted">WIB</span>
        <span class="text-muted opacity-50">|</span>
        <span id="ws-live-dot" class="pulse-dot"></span>
        <span class="fw-semibold text-success" style="font-size: 0.78rem;">Live Sync</span>
    </div>
</div>
