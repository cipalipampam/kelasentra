{{-- Grafik Tren 7 Hari Terakhir --}}
@php
    $chartLabels = [];
    $ontimeValues = [];
    $lateValues = [];
    if (!empty($weeklyData)) {
        foreach ($weeklyData as $dateKey => $data) {
            $chartLabels[] = $data['label'];
            $ontimeValues[] = $data['ontime'];
            $lateValues[] = $data['late'];
        }
    }
    $chartPayload = json_encode([
        'labels' => $chartLabels,
        'ontime' => $ontimeValues,
        'late' => $lateValues
    ]);
@endphp

<div class="card border-0 shadow-sm h-100">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 border-bottom">
        <div>
            <h6 class="fw-bold text-dark m-0">
                <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Tren Kehadiran 7 Hari Terakhir
            </h6>
            <span class="text-muted small" style="font-size: 0.78rem;">Perbandingan Hadir Tepat Waktu vs Terlambat</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="d-none d-sm-flex align-items-center gap-2 small text-muted">
                <span class="d-inline-block rounded-circle" style="width: 10px; height: 10px; background: #2563eb;"></span> Tepat Waktu
                <span class="d-inline-block rounded-circle ms-2" style="width: 10px; height: 10px; background: #f59e0b;"></span> Terlambat
            </div>
            <a href="{{ route('admin.attendances.students') }}" class="btn btn-sm btn-outline-primary py-1 px-3" style="font-size: 0.78rem;">
                Lihat Semua →
            </a>
        </div>
    </div>
    <div class="card-body p-4" style="height: 290px;">
        <canvas id="weeklyChart"></canvas>
    </div>
    {{-- Semantic Island Data Provider --}}
    <script type="application/json" id="weekly-chart-data">
        {!! $chartPayload !!}
    </script>
</div>

