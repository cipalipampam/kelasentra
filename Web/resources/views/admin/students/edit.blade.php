@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== PAGE HEADER ===== --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('admin.students.index') }}" class="text-decoration-none text-muted small">
                    <i class="bi bi-people me-1"></i>Data Siswa
                </a>
                <span class="text-muted small">/</span>
                <span class="text-muted small">Ubah Data</span>
            </div>
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.65rem; letter-spacing: -0.4px;">
                Ubah Profil Siswa
            </h1>
            <p class="text-muted small mb-0">Perbarui informasi akademik dan akun untuk siswa <strong class="text-dark">{{ $student->name }}</strong>.</p>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary py-2 px-3" style="font-size: 0.85rem;">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
            </a>
        </div>
    </div>

    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert" style="background-color: #fff1f2; color: #9f1239; border-left: 4px solid #e11d48 !important;">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5 text-danger"></i>
                <h6 class="mb-0 fw-bold">Gagal Menyimpan Perubahan</h6>
            </div>
            <ul class="mb-0 small ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.students.update', $student->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Account Section Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="bi bi-shield-lock text-primary me-2"></i>Keamanan Akun & Kredensial
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label text-dark fw-semibold small">
                                    Nama Lengkap Siswa <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control border-start-0 @error('name') is-invalid @enderror"
                                           id="name" name="name" value="{{ old('name', $student->name) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label text-dark fw-semibold small">
                                    Alamat Email Siswa <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 @error('email') is-invalid @enderror"
                                           id="email" name="email" value="{{ old('email', $student->email) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label text-dark fw-semibold small">
                                    Ganti Kata Sandi <span class="text-muted fw-normal">(Kosongkan jika tetap)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control border-start-0 @error('password') is-invalid @enderror"
                                           id="password" name="password" placeholder="Isi hanya jika ingin mereset">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Profile Section Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="bi bi-mortarboard text-primary me-2"></i>Data Akademik & Identitas Pribadi
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nisn" class="form-label text-dark fw-semibold small">NISN</label>
                                <input type="text" class="form-control @error('nisn') is-invalid @enderror"
                                       id="nisn" name="nisn" value="{{ old('nisn', $student->student->nisn ?? '') }}" placeholder="10 digit NISN">
                            </div>
                            <div class="col-md-6">
                                <label for="nis" class="form-label text-dark fw-semibold small">NIS (Nomor Induk Sekolah)</label>
                                <input type="text" class="form-control @error('nis') is-invalid @enderror"
                                       id="nis" name="nis" value="{{ old('nis', $student->student->nis ?? '') }}" placeholder="Nomor induk lokal">
                            </div>
                            <div class="col-md-6">
                                @php($studentRecord = $student->student)
                                @php($rombelRequired = ! $studentRecord || $studentRecord->isActive())
                                <label for="classroom_id" class="form-label text-dark fw-semibold small">
                                    Rombel @if($rombelRequired)<span class="text-danger">*</span>@endif
                                </label>
                                <select class="form-select @error('classroom_id') is-invalid @enderror"
                                        id="classroom_id" name="classroom_id"
                                        @if($rombelRequired) required @endif
                                        {{ $classrooms->isEmpty() ? 'disabled' : '' }}>
                                    <option value="">-- Pilih Rombel --</option>
                                    @foreach($classrooms as $classroom)
                                        <option value="{{ $classroom->id }}" @selected((string) old('classroom_id', $studentRecord->classroom_id ?? '') === (string) $classroom->id)>
                                            {{ $classroom->name }} — TA {{ $classroom->academic_year }} (sisa {{ $classroom->maxStudents() - $classroom->active_students_count }} kursi)
                                        </option>
                                    @endforeach
                                </select>
                                @if(! $rombelRequired)
                                    <div class="form-text text-muted small mt-1">
                                        Siswa ini berstatus non-aktif (alumni/mutasi), jadi rombel boleh dikosongkan.
                                    </div>
                                @elseif($classrooms->isEmpty())
                                    <div class="form-text text-danger mt-1">
                                        Belum ada rombel aktif dengan kursi tersisa. Buat rombel dulu di menu Rombel.
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label text-dark fw-semibold small">Jenis Kelamin</label>
                                <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                    <option value="">-- Pilih Jenis Kelamin --</option>
                                    <option value="male" {{ old('gender', $student->student->gender ?? '') == 'male' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="female" {{ old('gender', $student->student->gender ?? '') == 'female' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="place_of_birth" class="form-label text-dark fw-semibold small">Kota Kelahiran</label>
                                <input type="text" class="form-control @error('place_of_birth') is-invalid @enderror"
                                       id="place_of_birth" name="place_of_birth" value="{{ old('place_of_birth', $student->student->place_of_birth ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label text-dark fw-semibold small">Tanggal Lahir</label>
                                <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror"
                                       id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $student->student->date_of_birth ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label for="religion" class="form-label text-dark fw-semibold small">Agama</label>
                                <select class="form-select @error('religion') is-invalid @enderror" id="religion" name="religion">
                                    <option value="">-- Pilih Agama --</option>
                                    <option value="islam" {{ old('religion', $student->student->religion ?? '') == 'islam' ? 'selected' : '' }}>Islam</option>
                                    <option value="kristen" {{ old('religion', $student->student->religion ?? '') == 'kristen' ? 'selected' : '' }}>Kristen Protestan</option>
                                    <option value="katholik" {{ old('religion', $student->student->religion ?? '') == 'katholik' ? 'selected' : '' }}>Katolik</option>
                                    <option value="hindu" {{ old('religion', $student->student->religion ?? '') == 'hindu' ? 'selected' : '' }}>Hindu</option>
                                    <option value="buddha" {{ old('religion', $student->student->religion ?? '') == 'buddha' ? 'selected' : '' }}>Buddha</option>
                                    <option value="konghucu" {{ old('religion', $student->student->religion ?? '') == 'konghucu' ? 'selected' : '' }}>Konghucu</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label text-dark fw-semibold small">Nomor Telepon / WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">+62</span>
                                    <input type="text" class="form-control @error('phone_number') is-invalid @enderror"
                                           id="phone_number" name="phone_number" value="{{ old('phone_number', $student->student->phone_number ?? '') }}" placeholder="812xxxxxxxx">
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label text-dark fw-semibold small">Alamat Tempat Tinggal</label>
                                <textarea class="form-control @error('address') is-invalid @enderror"
                                          id="address" name="address" rows="3">{{ old('address', $student->student->address ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Media Section --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="bi bi-camera text-primary me-2"></i>Pas Foto Siswa
                        </h6>
                    </div>
                    <div class="card-body p-4 text-center bg-light">
                        <div class="mb-3">
                            <div id="avatar-preview-box" class="mx-auto rounded-3 mb-3 d-flex align-items-center justify-content-center bg-white border shadow-xs overflow-hidden" style="width: 140px; height: 180px;">
                                @if(isset($student->student->profile_picture) && \Illuminate\Support\Facades\Storage::disk('public')->exists($student->student->profile_picture))
                                    <img id="avatar-preview-img" src="{{ \Illuminate\Support\Facades\Storage::url($student->student->profile_picture) }}"
                                         alt="Profile" class="w-100 h-100" style="object-fit: cover;">
                                    <i id="avatar-preview-icon" class="bi bi-person-bounding-box text-muted display-4 d-none"></i>
                                @else
                                    <img id="avatar-preview-img" src="" alt="Preview Foto" class="d-none w-100 h-100" style="object-fit: cover;">
                                    <i id="avatar-preview-icon" class="bi bi-person-bounding-box text-muted display-4"></i>
                                @endif
                            </div>
                            <p class="text-muted small mb-0">Pas foto identitas siswa saat ini.</p>
                        </div>
                        <div class="text-start">
                            <label for="profile_picture" class="form-label text-dark small fw-semibold">Ganti Pas Foto</label>
                            <input type="file" class="form-control @error('profile_picture') is-invalid @enderror"
                                   id="profile_picture" name="profile_picture" accept="image/*" onchange="previewStudentPhoto(this)">
                            <div class="form-text text-muted small mt-1">Kosongkan jika tidak ingin mengubah foto. Maksimal 2MB.</div>
                        </div>
                    </div>
                </div>

                {{-- Action Card --}}
                <div class="card border-0 shadow-sm position-sticky" style="top: 2rem;">
                    <div class="card-body p-4">
                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold shadow-xs mb-3">
                            <i class="bi bi-arrow-repeat me-1.5"></i>Simpan Perubahan
                        </button>
                        <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary w-100 py-2" style="font-size: 0.85rem;">
                            Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function previewStudentPhoto(input) {
    const previewImg = document.getElementById('avatar-preview-img');
    const previewIcon = document.getElementById('avatar-preview-icon');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewImg.classList.remove('d-none');
            previewIcon.classList.add('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endsection
