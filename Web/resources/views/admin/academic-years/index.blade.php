@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== HEADER & ACTION BAR ===== --}}
    @include('admin.academic-years.components.header')

    {{-- ===== FLASH & ERROR ALERTS ===== --}}
    @include('admin.academic-years.components.alerts')

    {{-- ===== MINI KPI SUMMARY CARDS ===== --}}
    @include('admin.academic-years.components.stats-cards')

    {{-- ===== SEARCH & FILTER ===== --}}
    @include('admin.academic-years.components.filter-bar')

    {{-- ===== DATA TABLE ===== --}}
    @include('admin.academic-years.components.table')

</div>
@endsection

@push('modals')
    {{-- Modals Edit Tahun Ajaran --}}
    @include('admin.academic-years.components.edit-modals')

    {{-- Modal Tambah Tahun Ajaran --}}
    @include('admin.academic-years.components.create-modal')
@endpush

@push('scripts')
    {{-- Academic Years Modular ESM Orchestrator --}}
    <script type="module" src="{{ asset('js/admin/academic-years/academic-years.js') }}"></script>
@endpush
