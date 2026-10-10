{{-- Action Bar Rombel & Kelas (Tanpa Redundansi Breadcrumb Navbar) --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-0" style="font-size: 1.25rem; letter-spacing: -0.3px;">
            Daftar Rombongan Belajar
        </h4>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.classrooms.promotion') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-up-right-circle me-1"></i>Kenaikan Kelas Massal
        </a>
        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#duplicateModal">
            <i class="bi bi-copy me-1"></i>Duplikasi Rombel
        </button>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg me-1"></i>Tambah Rombel
        </button>
    </div>
</div>
