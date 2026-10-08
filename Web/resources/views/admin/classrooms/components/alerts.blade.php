{{-- Flash Messages & Error Validasi --}}
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="background-color: #ecfdf5; color: #065f46; border-left: 4px solid #059669 !important;">
        <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
        <div class="grow">
            <strong>Berhasil!</strong> {{ session('success') }}
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-4" role="alert" style="background-color: #fff1f2; color: #9f1239; border-left: 4px solid #e11d48 !important;">
        <i class="bi bi-exclamation-octagon-fill fs-5 me-2 text-danger"></i>
        <div class="grow">
            <strong>Perhatian:</strong> {{ session('error') }}
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="background-color: #fff1f2; color: #9f1239; border-left: 4px solid #e11d48 !important;">
        <div class="d-flex align-items-center mb-1">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5 text-danger"></i>
            <h6 class="mb-0 fw-bold">Gagal Menyimpan Data Rombel</h6>
        </div>
        <ul class="mb-0 small ps-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

