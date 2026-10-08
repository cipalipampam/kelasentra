@extends('admin.layouts.app')

@section('content')
<div class="py-2">

    {{-- ===== BREADCRUMB & PAGE HEADER ===== --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted small">
                    <i class="bi bi-house-door me-1"></i>Dashboard
                </a>
                <span class="text-muted small">/</span>
                <a href="{{ route('admin.employees.index') }}" class="text-decoration-none text-muted small">
                    Data Guru & Pegawai
                </a>
                <span class="text-muted small">/</span>
                <span class="text-muted small">Ubah Data</span>
            </div>
            <h1 class="fw-bold text-dark mb-1" style="font-size: 1.65rem; letter-spacing: -0.4px;">
                Ubah Data Pegawai
            </h1>
            <p class="text-muted small mb-0">
                Perbarui data profil, peran institusi, dan kredensial akses untuk <strong class="text-dark">{{ $employee->name }}</strong>.
            </p>
        </div>
        <div class="col-md-5 text-md-end mt-3 mt-md-0">
            <a href="{{ route('admin.employees.index') }}" class="btn btn-outline-secondary py-2 px-3" style="font-size: 0.85rem;">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Direktori
            </a>
        </div>
    </div>

    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-4" role="alert" style="background-color: #fff1f2; color: #9f1239; border-left: 4px solid #e11d48 !important;">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5 text-danger"></i>
                <h6 class="mb-0 fw-bold">Gagal Menyimpan Perubahan Data</h6>
            </div>
            <ul class="mb-0 small ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.employees.update', $employee->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Account Credentials Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="bi bi-shield-lock text-primary me-2"></i>Akun & Hak Akses Sistem
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="name" class="form-label text-dark fw-semibold small">
                                    Nama Lengkap Pegawai <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control border-start-0 @error('name') is-invalid @enderror"
                                           id="name" name="name" value="{{ old('name', $employee->name) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label text-dark fw-semibold small">
                                    Klasifikasi Peran <span class="text-danger">*</span>
                                </label>
                                @php
                                    $currentRole = old('role') ?? ($employee->hasRole('guru') ? 'guru' : ($employee->hasRole('staff') ? 'staff' : ''));
                                @endphp
                                <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                                    <option value="">-- Pilih Klasifikasi Peran --</option>
                                    <option value="guru" {{ $currentRole == 'guru' ? 'selected' : '' }}>Guru / Tenaga Pendidik</option>
                                    <option value="staff" {{ $currentRole == 'staff' ? 'selected' : '' }}>Staf Administrasi / Tata Usaha</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label text-dark fw-semibold small">
                                    Alamat Email Resmi <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0 @error('email') is-invalid @enderror"
                                           id="email" name="email" value="{{ old('email', $employee->email) }}" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label for="password" class="form-label text-dark fw-semibold small">
                                    Ganti Kata Sandi <span class="text-muted fw-normal">(Kosongkan jika tidak ingin mengubah)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control border-start-0 @error('password') is-invalid @enderror"
                                           id="password" name="password" placeholder="Isi hanya bila hendak memperbarui sandi (min. 6 karakter)">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Professional & Personal Dossier Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="bi bi-briefcase text-primary me-2"></i>Data Kepegawaian & Identitas Diri
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nip" class="form-label text-dark fw-semibold small">NIP / NUPTK</label>
                                <input type="text" class="form-control @error('nip') is-invalid @enderror"
                                       id="nip" name="nip" value="{{ old('nip', $employee->employee->nip ?? '') }}" placeholder="18 digit NIP resmi">
                            </div>
                            <div class="col-md-6">
                                <label for="position" class="form-label text-dark fw-semibold small">Jabatan / Posisi Kerja</label>
                                <input type="text" class="form-control @error('position') is-invalid @enderror"
                                       id="position" name="position" value="{{ old('position', $employee->employee->position ?? '') }}"
                                       placeholder="Contoh: Guru Bahasa Inggris, Staf Keuangan">
                            </div>
                            <div class="col-md-6">
                                <label for="employment_status" class="form-label text-dark fw-semibold small">Status Kepegawaian</label>
                                <select class="form-select @error('employment_status') is-invalid @enderror"
                                        id="employment_status" name="employment_status">
                                    @php $currentStatus = old('employment_status', $employee->employee->employment_status ?? \App\Models\Employee::STATUS_ACTIVE); @endphp
                                    @foreach(\App\Models\Employee::statusLabels() as $statusValue => $statusLabel)
                                        <option value="{{ $statusValue }}" {{ $currentStatus === $statusValue ? 'selected' : '' }}>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text text-muted small">Guru berstatus selain Aktif tidak dapat ditunjuk sebagai wali kelas.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label text-dark fw-semibold small">Jenis Kelamin</label>
                                <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                    <option value="">-- Pilih Jenis Kelamin --</option>
                                    <option value="male" {{ old('gender', $employee->employee->gender ?? '') == 'male' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="female" {{ old('gender', $employee->employee->gender ?? '') == 'female' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label text-dark fw-semibold small">Nomor WhatsApp / HP</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">+62</span>
                                    <input type="text" class="form-control @error('phone_number') is-invalid @enderror"
                                           id="phone_number" name="phone_number" value="{{ old('phone_number', $employee->employee->phone_number ?? '') }}" placeholder="812xxxxxxxx">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="place_of_birth" class="form-label text-dark fw-semibold small">Tempat Lahir</label>
                                <input type="text" class="form-control @error('place_of_birth') is-invalid @enderror"
                                       id="place_of_birth" name="place_of_birth" value="{{ old('place_of_birth', $employee->employee->place_of_birth ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label text-dark fw-semibold small">Tanggal Lahir</label>
                                <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror"
                                       id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $employee->employee->date_of_birth ?? '') }}">
                            </div>
                            <div class="col-md-12">
                                <label for="religion" class="form-label text-dark fw-semibold small">Agama</label>
                                <select class="form-select @error('religion') is-invalid @enderror" id="religion" name="religion">
                                    <option value="">-- Pilih Agama --</option>
                                    <option value="islam" {{ old('religion', $employee->employee->religion ?? '') == 'islam' ? 'selected' : '' }}>Islam</option>
                                    <option value="kristen" {{ old('religion', $employee->employee->religion ?? '') == 'kristen' ? 'selected' : '' }}>Kristen Protestan</option>
                                    <option value="katholik" {{ old('religion', $employee->employee->religion ?? '') == 'katholik' ? 'selected' : '' }}>Katolik</option>
                                    <option value="hindu" {{ old('religion', $employee->employee->religion ?? '') == 'hindu' ? 'selected' : '' }}>Hindu</option>
                                    <option value="buddha" {{ old('religion', $employee->employee->religion ?? '') == 'buddha' ? 'selected' : '' }}>Buddha</option>
                                    <option value="konghucu" {{ old('religion', $employee->employee->religion ?? '') == 'konghucu' ? 'selected' : '' }}>Konghucu</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label text-dark fw-semibold small">Alamat Tempat Tinggal</label>
                                <textarea class="form-control @error('address') is-invalid @enderror"
                                          id="address" name="address" rows="3">{{ old('address', $employee->employee->address ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Teacher Subjects Card (Hanya relevan jika Peran = Guru) --}}
                <div class="card border-0 shadow-sm mb-4" id="teacher-subjects-card" style="display: {{ $currentRole == 'guru' ? 'block' : 'none' }};">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 text-dark fw-bold">
                                <i class="bi bi-journal-check text-primary me-2"></i>Mata Pelajaran yang Diampu (Kurikulum Merdeka)
                            </h6>
                            <p class="text-muted small mb-0 mt-0.5">Pilih mapel utama dan mapel serumpun untuk pemenuhan beban tatap muka (24–40 JP/minggu).</p>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        @php
                            $clusterLabels = [
                                'mipa' => ['label' => 'Rumpun MIPA & Teknologi', 'badge' => 'bg-primary-subtle text-primary border-primary-subtle'],
                                'bahasa' => ['label' => 'Rumpun Bahasa & Komunikasi', 'badge' => 'bg-success-subtle text-success border-success-subtle'],
                                'ips' => ['label' => 'Rumpun Ilmu Pengetahuan Sosial (IPS)', 'badge' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle'],
                                'umum' => ['label' => 'Rumpun Umum, Agama & Pengembangan Diri', 'badge' => 'bg-secondary-subtle text-secondary border-secondary-subtle'],
                            ];
                            $existingSubjects = $employee->subjects->pluck('id')->toArray();
                            $selectedSubjects = old('subject_ids', $existingSubjects);
                            $existingPrimary = $employee->subjects->firstWhere('pivot.is_primary', true)?->id;
                            $primarySubjectId = old('primary_subject_id', $existingPrimary);
                        @endphp

                        @foreach($subjects as $cluster => $clusterSubjects)
                            <div class="mb-3 p-3 rounded border bg-light bg-opacity-50">
                                <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="fw-semibold small text-dark">
                                        {{ $clusterLabels[$cluster]['label'] ?? strtoupper($cluster) }}
                                    </span>
                                    <span class="badge border {{ $clusterLabels[$cluster]['badge'] ?? 'bg-light text-dark' }} px-2 py-1" style="font-size: 0.7rem;">
                                        {{ count($clusterSubjects) }} Mapel
                                    </span>
                                </div>
                                <div class="row g-2">
                                    @foreach($clusterSubjects as $subj)
                                        <div class="col-md-6">
                                            <div class="form-check p-2 rounded border bg-white d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center gap-2">
                                                    <input class="form-check-input ms-1 subject-checkbox" type="checkbox"
                                                           name="subject_ids[]" value="{{ $subj->id }}"
                                                           id="subj_{{ $subj->id }}"
                                                           {{ in_array($subj->id, $selectedSubjects) ? 'checked' : '' }}
                                                           onchange="handleSubjectSelection(this, '{{ $subj->id }}')">
                                                    <label class="form-check-label text-dark small fw-medium cursor-pointer" for="subj_{{ $subj->id }}">
                                                        <span class="badge me-1" style="background-color: {{ $subj->color_code }}!important; color: white;">
                                                            {{ $subj->code }}
                                                        </span>
                                                        {{ $subj->name }}
                                                    </label>
                                                </div>
                                                <div class="form-check form-check-inline m-0 me-1" title="Tandai sebagai Mapel Utama">
                                                    <input class="form-check-input primary-radio" type="radio"
                                                           name="primary_subject_id" value="{{ $subj->id }}"
                                                           id="primary_{{ $subj->id }}"
                                                           {{ $primarySubjectId == $subj->id ? 'checked' : '' }}
                                                           {{ in_array($subj->id, $selectedSubjects) ? '' : 'disabled' }}>
                                                    <label class="form-check-label text-muted" for="primary_{{ $subj->id }}" style="font-size: 0.68rem;">
                                                        Utama
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Photo Profile Card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 text-dark fw-bold">
                            <i class="bi bi-camera text-primary me-2"></i>Pas Foto Pegawai
                        </h6>
                    </div>
                    <div class="card-body p-4 text-center bg-light">
                        <div class="mb-3">
                            <div id="avatar-preview-box" class="mx-auto rounded-3 mb-3 d-flex align-items-center justify-content-center bg-white border shadow-xs overflow-hidden" style="width: 140px; height: 180px;">
                                @if(isset($employee->employee->profile_picture) && \Illuminate\Support\Facades\Storage::disk('public')->exists($employee->employee->profile_picture))
                                    <img id="avatar-preview-img" src="{{ \Illuminate\Support\Facades\Storage::url($employee->employee->profile_picture) }}"
                                         alt="Profile" class="w-100 h-100" style="object-fit: cover;">
                                    <i id="avatar-preview-icon" class="bi bi-person-workspace text-muted display-4 d-none"></i>
                                @else
                                    <img id="avatar-preview-img" src="" alt="Preview Foto" class="d-none w-100 h-100" style="object-fit: cover;">
                                    <i id="avatar-preview-icon" class="bi bi-person-workspace text-muted display-4"></i>
                                @endif
                            </div>
                            <p class="text-muted small mb-0">Pas foto aktif pada profil presensi pegawai.</p>
                        </div>
                        <div class="text-start">
                            <label for="profile_picture" class="form-label text-dark small fw-semibold">Ganti Pas Foto</label>
                            <input type="file" class="form-control @error('profile_picture') is-invalid @enderror"
                                   id="profile_picture" name="profile_picture" accept="image/*" onchange="previewEmployeePhoto(this)">
                            <div class="form-text text-muted small mt-1">Kosongkan jika tidak ingin mengganti foto saat ini.</div>
                        </div>
                    </div>
                </div>

                {{-- Submit Action Card --}}
                <div class="card border-0 shadow-sm position-sticky" style="top: 2rem;">
                    <div class="card-body p-4">
                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold shadow-xs mb-3">
                            <i class="bi bi-check2-circle me-1.5"></i>Simpan Perubahan
                        </button>
                        <a href="{{ route('admin.employees.index') }}" class="btn btn-outline-secondary w-100 py-2" style="font-size: 0.85rem;">
                            Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function previewEmployeePhoto(input) {
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
