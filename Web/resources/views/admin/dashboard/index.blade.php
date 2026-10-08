@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== FLASH MESSAGES ===== --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="background-color: #ecfdf5; color: #065f46; border-left: 4px solid #059669 !important;">
            <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
            <div class="grow">
                <strong>Berhasil!</strong> {{ session('success') }}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="background-color: #fff1f2; color: #9f1239; border-left: 4px solid #e11d48 !important;">
            <i class="bi bi-exclamation-octagon-fill fs-5 me-2 text-danger"></i>
            <div class="grow">
                <strong>Perhatian:</strong> {{ session('error') }}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ===== ISLAND COMPONENTS ===== --}}
    @include('admin.dashboard.components.header')
    @include('admin.dashboard.components.kpi-cards')

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            @include('admin.dashboard.components.weekly-chart')
        </div>
        <div class="col-xl-5">
            @include('admin.dashboard.components.pending-approvals')
        </div>
    </div>

    @include('admin.dashboard.components.live-attendance')

    <div class="row g-4">
        <div class="col-lg-6">
            @include('admin.dashboard.components.geofence-summary')
        </div>
        <div class="col-lg-6">
            @include('admin.dashboard.components.recent-announcements')
        </div>
    </div>

</div>

{{-- ===== MODAL QUICK PREVIEW BUKTI ===== --}}
@include('admin.dashboard.components.proof-modal')
@endsection

@push('scripts')
{{-- Chart.js Core Library --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
{{-- Dashboard Modular ESM Orchestrator --}}
<script type="module" src="{{ asset('js/admin/dashboard/dashboard.js') }}"></script>
@endpush
