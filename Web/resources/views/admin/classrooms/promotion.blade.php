@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== HEADER & BREADCRUMB ===== --}}
    @include('admin.classrooms.components.promotion.header')

    {{-- ===== FLASH & ERROR ALERTS ===== --}}
    @include('admin.classrooms.components.promotion.alerts')

    {{-- ===== FORM UTAMA PROMOSI ===== --}}
    <form id="promotionForm" action="{{ route('admin.classrooms.promotion.process') }}" method="POST">
        @csrf

        {{-- LANGKAH 1: PENGATURAN ROMBEL & JENIS PROSES --}}
        @include('admin.classrooms.components.promotion.config-card')

        {{-- LANGKAH 2: SELEKSI SISWA & EKSEKUSI --}}
        @include('admin.classrooms.components.promotion.students-selection')

    </form>

    {{-- ===== RIWAYAT EKSEKUSI & PEMBATALAN ===== --}}
    @include('admin.classrooms.components.promotion.history')

</div>
@endsection

@push('modals')
    {{-- MODAL KONFIRMASI PROTEKTIF EKSEKUSI --}}
    @include('admin.classrooms.components.promotion.confirm-modal')
@endpush

@push('scripts')
    {{-- Promotion Modular ESM Orchestrator --}}
    <script type="module" src="{{ asset('js/admin/classrooms/promotion.js') }}"></script>
@endpush
