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
                <span class="text-muted small">Tambah Siswa Baru</span>
            </div>
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.65rem; letter-spacing: -0.4px;">
                Pendaftaran Siswa Baru
            </h1>
            <p class="text-muted small mb-0">
                Registrasi data identitas, akademik, dan akun akses sistem presensi untuk siswa.
            </p>
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
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5 text-danger"></i>
                <h6 class="mb-0 fw-bold">Gagal Menyimpan Data</h6>
            </div>
            <ul class="mb-0 small ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.students.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Account Credentials Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="bi bi-shield-lock text-primary me-2"></i>Akun & Akses Aplikasi Siswa
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
                                           id="name" name="name" value="{{ old('name') }}"
                                           placeholder="Masukkan nama lengkap siswa sesuai ijazah/akta" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label text-dark fw-semibold small">
                                    Alamat Email Siswa <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 @error('email') is-invalid @enderror"
                                           id="email" name="email" value="{{ old('email') }}"
                                           placeholder="nama.siswa@sekolah.sch.id" required>
                                </div>
                                <div class="form-text text-muted small">Digunakan untuk login di aplikasi presensi mobile.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label text-dark fw-semibold small">
                                    Kata Sandi Akses <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control border-start-0 @error('password') is-invalid @enderror"
                                           id="password" name="password" placeholder="Minimal 6 karakter" required>
                                </div>
                                <div class="form-text text-muted small">Siswa dapat mengubah kata sandi ini nantinya.</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Academic & Personal Information Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="bi bi-mortarboard text-primary me-2"></i>Data Akademik & Identitas Pribadi
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nisn" class="form-label text-dark fw-semibold small">NISN (Nomor Induk Siswa Nasional)</label>
                                <input type="text" class="form-control @error('nisn') is-invalid @enderror"
                                       id="nisn" name="nisn" value="{{ old('nisn') }}" placeholder="10 digit nomor NISN">
                            </div>
                            <div class="col-md-6">
                                <label for="nis" class="form-label text-dark fw-semibold small">NIS (Nomor Induk Sekolah)</label>
                                <input type="text" class="form-control @error('nis') is-invalid @enderror"
                                       id="nis" name="nis" value="{{ old('nis') }}" placeholder="ID lokal sekolah">
                            </div>
                            <div class="col-md-6">
                                <label for="classroom_id" class="form-label text-dark fw-semibold small">
                                    Rombel <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('classroom_id') is-invalid @enderror"
                                        id="classroom_id" name="classroom_id" required
                                        {{ $classrooms->isEmpty() ? 'disabled' : '' }}>
                                    <option value="">-- Pilih Rombel --</option>
                                    @foreach($classrooms as $classroom)
                                        <option value="{{ $classroom->id }}" @selected((string) old('classroom_id') === (string) $classroom->id)>
                                            {{ $classroom->name }} (sisa {{ $classroom->maxStudents() - $classroom->active_students_count }} kursi)
                                        </option>
                                    @endforeach
                                </select>
                                @if($classrooms->isEmpty())
                                    <div class="form-text text-danger mt-1">
                                        Belum ada rombel aktif dengan kursi tersisa. Buat rombel dulu di menu Rombel.
                                    </div>
                                @else
                                    <div class="form-text text-muted small mt-1">
                                        Rombel menentukan kelas dan tahun ajaran siswa, sekaligus dipakai untuk jadwal serta presensi.
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label text-dark fw-semibold small">Jenis Kelamin</label>
                                <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                    <option value="">-- Pilih Jenis Kelamin --</option>
                                    <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="place_of_birth" class="form-label text-dark fw-semibold small">Tempat Lahir</label>
                                <input type="text" class="form-control @error('place_of_birth') is-invalid @enderror"
                                       id="place_of_birth" name="place_of_birth" value="{{ old('place_of_birth') }}" placeholder="Kota kelahiran">
                            </div>
                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label text-dark fw-semibold small">Tanggal Lahir</label>
                                <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror"
                                       id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}">
                            </div>
                            <div class="col-md-6">
                                <label for="religion" class="form-label text-dark fw-semibold small">Agama</label>
                                <select class="form-select @error('religion') is-invalid @enderror" id="religion" name="religion">
                                    <option value="">-- Pilih Agama --</option>
                                    <option value="islam" {{ old('religion') == 'islam' ? 'selected' : '' }}>Islam</option>
                                    <option value="kristen" {{ old('religion') == 'kristen' ? 'selected' : '' }}>Kristen Protestan</option>
                                    <option value="katholik" {{ old('religion') == 'katholik' ? 'selected' : '' }}>Katolik</option>
                                    <option value="hindu" {{ old('religion') == 'hindu' ? 'selected' : '' }}>Hindu</option>
                                    <option value="buddha" {{ old('religion') == 'buddha' ? 'selected' : '' }}>Buddha</option>
                                    <option value="konghucu" {{ old('religion') == 'konghucu' ? 'selected' : '' }}>Konghucu</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label text-dark fw-semibold small">Nomor Telepon / WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">+62</span>
                                    <input type="text" class="form-control @error('phone_number') is-invalid @enderror"
                                           id="phone_number" name="phone_number" value="{{ old('phone_number') }}" placeholder="812xxxxxxxx">
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label text-dark fw-semibold small">Alamat Tempat Tinggal</label>
                                <textarea class="form-control @error('address') is-invalid @enderror"
                                          id="address" name="address" rows="3"
                                          placeholder="Alamat lengkap domisili siswa...">{{ old('address') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Photo Profile Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="bi bi-camera text-primary me-2"></i>Pas Foto Siswa
                        </h6>
                    </div>
                    <div class="card-body p-4 text-center bg-light">
                        <div class="mb-3">
                            <div id="avatar-preview-box" class="mx-auto rounded-3 mb-3 d-flex align-items-center justify-content-center bg-white border shadow-xs overflow-hidden" style="width: 140px; height: 180px;">
                                <img id="avatar-preview-img" src="" alt="Preview Foto" class="d-none w-100 h-100" style="object-fit: cover;">
                                <i id="avatar-preview-icon" class="bi bi-person-bounding-box text-muted display-4"></i>
                            </div>
                            <p class="text-muted small mb-0">Foto akan tampil pada kartu identitas digital siswa.</p>
                        </div>
                        <div class="text-start">
                            <label for="profile_picture" class="form-label text-dark small fw-semibold">Pilih Berkas Foto</label>
                            <input type="file" class="form-control @error('profile_picture') is-invalid @enderror"
                                   id="profile_picture" name="profile_picture" accept="image/*" onchange="previewStudentPhoto(this)">
                            <div class="form-text text-muted small mt-1">Format: JPG atau PNG (Maksimal 2MB).</div>
                        </div>
                    </div>
                </div>

                {{-- Submit Action Card --}}
                <div class="card border-0 shadow-sm position-sticky" style="top: 2rem;">
                    <div class="card-body p-4">
                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold shadow-xs mb-3"
                                {{ $classrooms->isEmpty() ? 'disabled' : '' }}>
                            <i class="bi bi-check2-circle me-1.5"></i>Daftarkan Siswa
                        </button>
                        <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary w-100 py-2" style="font-size: 0.85rem;">
                            Batal
                        </a>
                        <p class="text-muted small text-center mb-0 mt-3 px-2" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle me-1 text-primary"></i>Data siswa akan otomatis tersinkronisasi ke seluruh sistem presensi.
                        </p>
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
    } else {
        previewImg.classList.add('d-none');
        previewIcon.classList.remove('d-none');
    }
}
</script>
@endsection
