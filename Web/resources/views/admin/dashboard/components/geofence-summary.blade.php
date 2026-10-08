{{-- Geofence & Parameter Operasional Presensi --}}
<div class="card border-0 shadow-sm h-100">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h6 class="fw-bold text-dark m-0">
            <i class="bi bi-sliders text-primary me-2"></i>Parameter Operasional Presensi
        </h6>
        <a href="{{ route('admin.settings.index') }}" class="btn btn-sm btn-link text-decoration-none p-0 text-primary" style="font-size: 0.8rem;">
            Ubah Pengaturan →
        </a>
    </div>
    <div class="card-body p-3 p-md-4">
        <div class="row g-3">
            <div class="col-sm-6">
                <div class="p-3 rounded-3 bg-light border">
                    <span class="text-muted small d-block mb-1">Jam Masuk (Check-In)</span>
                    <strong class="text-dark fs-6">{{ $setting['check_in_start'] ?? '06:00' }} - {{ $setting['check_in_end'] ?? '07:00' }} WIB</strong>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="p-3 rounded-3 bg-light border">
                    <span class="text-muted small d-block mb-1">Jam Pulang (Check-Out)</span>
                    <strong class="text-dark fs-6">{{ $setting['check_out_start'] ?? '15:00' }} - {{ $setting['check_out_end'] ?? '18:00' }} WIB</strong>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="p-3 rounded-3 bg-light border">
                    <span class="text-muted small d-block mb-1">Radius Geofence GPS</span>
                    <strong class="text-dark fs-6">{{ $setting['office_radius'] ?? '50' }} Meter</strong>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="p-3 rounded-3 bg-light border">
                    <span class="text-muted small d-block mb-1">Toleransi Keterlambatan</span>
                    <strong class="text-dark fs-6">{{ $setting['late_tolerance_minutes'] ?? '15' }} Menit</strong>
                </div>
            </div>
        </div>
    </div>
</div>

