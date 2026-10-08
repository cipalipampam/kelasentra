{{-- Filter & Pencarian Tahun Ajaran --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3 p-md-3">
        <form action="{{ route('admin.academic-years.index') }}" method="GET" class="row g-3 align-items-end js-live-filter">
            <div class="col-lg-6 col-md-7">
                <label for="search" class="form-label small fw-semibold text-secondary mb-1">Cari Tahun Ajaran</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="search" class="form-control border-start-0 ps-0"
                           placeholder="Contoh: 2026/2027" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-lg-6 col-md-5">
                <label for="status" class="form-label small fw-semibold text-secondary mb-1">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach($statuses as $statusValue => $statusLabel)
                        <option value="{{ $statusValue }}" {{ request('status') === $statusValue ? 'selected' : '' }}>
                            {{ $statusLabel }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>
