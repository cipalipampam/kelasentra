{{-- Action Bar Tahun Ajaran --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-0" style="font-size: 1.25rem; letter-spacing: -0.3px;">
            Tahun Ajaran
        </h4>
        <p class="text-muted small mb-0 mt-1">
            Kelola periode tahun ajaran yang boleh digunakan oleh rombel dan kenaikan kelas.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('admin.classrooms.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-diagram-3 me-1"></i>Rombel & Kelas
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg me-1"></i>Tambah Tahun Ajaran
        </button>
    </div>
</div>
