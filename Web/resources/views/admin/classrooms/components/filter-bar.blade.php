{{-- Filter & Pencarian Rombel --}}
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-body p-3 p-md-3">
        <form action="{{ route('admin.classrooms.index') }}" method="GET" class="row g-3 align-items-end js-live-filter">
            <input type="hidden" name="show_history" value="{{ request('show_history') ? '1' : '' }}">
            <div class="col-lg-4 col-md-12">
                <label for="search" class="form-label small fw-semibold text-secondary mb-1">Cari Rombel</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="search" class="form-control border-start-0 ps-0"
                           placeholder="Nama rombel atau tahun ajaran..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-lg-3 col-md-5">
                <label for="level" class="form-label small fw-semibold text-secondary mb-1">Tingkat</label>
                <select name="level" id="level" class="form-select">
                    <option value="">Semua Tingkat</option>
                    <option value="10" {{ request('level') == '10' ? 'selected' : '' }}>Kelas X</option>
                    <option value="11" {{ request('level') == '11' ? 'selected' : '' }}>Kelas XI</option>
                    <option value="12" {{ request('level') == '12' ? 'selected' : '' }}>Kelas XII</option>
                </select>
            </div>
            <div class="col-lg-5 col-md-7">
                <label for="major" class="form-label small fw-semibold text-secondary mb-1">Jurusan</label>
                <select name="major" id="major" class="form-select">
                    <option value="">Semua Jurusan</option>
                    @foreach($majors as $major)
                        <option value="{{ $major }}" {{ request('major') === $major ? 'selected' : '' }}>{{ $major }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-12 d-flex align-items-center justify-content-end">
                @if(request('show_history'))
                    <a href="{{ route('admin.classrooms.index', array_merge(request()->except(['show_history', 'page']), ['level' => request('level'), 'major' => request('major'), 'search' => request('search')])) }}"
                       class="btn btn-sm btn-outline-secondary border d-flex align-items-center gap-2" style="font-size: 0.8rem;">
                        <i class="bi bi-eye-slash"></i> Sembunyikan Riwayat
                    </a>
                @else
                    <a href="{{ route('admin.classrooms.index', array_merge(request()->query(), ['show_history' => '1'])) }}"
                       class="btn btn-sm btn-outline-secondary border d-flex align-items-center gap-2" style="font-size: 0.8rem;">
                        <i class="bi bi-clock-history"></i> Tampilkan Riwayat
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

