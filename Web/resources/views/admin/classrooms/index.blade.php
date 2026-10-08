@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== HEADER & BREADCRUMB ===== --}}
    @include('admin.classrooms.components.header')

    {{-- ===== FLASH & ERROR ALERTS ===== --}}
    @include('admin.classrooms.components.alerts')

    {{-- ===== MINI KPI SUMMARY CARDS ===== --}}
    @include('admin.classrooms.components.stats-cards')

    {{-- ===== SEARCH & FILTER ===== --}}
    @include('admin.classrooms.components.filter-bar')

    {{-- ===== DATA TABLE ===== --}}
    @include('admin.classrooms.components.table')

</div>
@endsection

@push('modals')
    {{-- Modals Edit Rombel --}}
    @include('admin.classrooms.components.edit-modals')

    {{-- Modal Tambah Rombel --}}
    @include('admin.classrooms.components.create-modal')
@endpush

@push('scripts')
    {{-- Classrooms Modular ESM Orchestrator --}}
    <script type="module" src="{{ asset('js/admin/classrooms/classrooms.js') }}"></script>
@endpush
