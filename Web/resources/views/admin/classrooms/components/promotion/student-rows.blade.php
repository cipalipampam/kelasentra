{{--
    Satu-satunya sumber markup baris siswa.

    Dipakai dua jalur sekaligus: render awal oleh server (agar halaman tetap
    berfungsi tanpa JS) dan respons AJAX saat rombel asal diganti.
--}}
@foreach($students as $index => $student)
    <tr class="student-row" data-name="{{ strtolower($student->user?->name ?? '') }}" data-nis="{{ $student->nis ?? '' }}">
        <td class="text-center">
            <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="form-check-input student-checkbox" checked>
        </td>
        <td class="text-muted small">{{ $index + 1 }}</td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-placeholder" style="width: 28px; height: 28px; font-size: 0.65rem;">
                    {{ strtoupper(substr($student->user?->name ?? 'S', 0, 1)) }}
                </div>
                <div>
                    <span class="fw-semibold text-dark d-block student-name" style="font-size: 0.88rem;">
                        {{ $student->user?->name ?? 'Siswa #'.$student->id }}
                    </span>
                </div>
            </div>
        </td>
        <td class="small">
            <span class="fw-medium text-dark">{{ $student->nis ?? '-' }}</span>
            <span class="text-muted d-block" style="font-size: 0.72rem;">NISN: {{ $student->nisn ?? '-' }}</span>
        </td>
        <td class="small">
            <span class="badge {{ $student->gender === 'female' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }}" style="font-size: 0.72rem;">
                {{ $student->gender === 'female' ? 'Perempuan' : 'Laki-laki' }}
            </span>
        </td>
        <td>
            <span class="badge-status badge-present" style="font-size: 0.72rem;">
                <i class="bi bi-check-circle me-1"></i>Aktif
            </span>
        </td>
        <td class="target-indicator small text-muted">
            <span class="target-label">Mengikuti Pengaturan di Atas</span>
        </td>
    </tr>
@endforeach
