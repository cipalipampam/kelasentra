<?php

namespace App\Http\Requests\Web\Student;

use App\Models\User;
use App\Rules\EnrollableClassroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->hasRole('admin');
    }

    public function rules()
    {
        $userId = $this->route('student');
        $user = User::with('student')->findOrFail($userId);
        $studentId = $user->student ? $user->student->id : null;

        // Rombel wajib hanya bila siswa berstatus aktif (termasuk bila status
        // baru yang diminta adalah aktif). Status non-aktif menutup enrollment.
        $requestedStatus = $this->input('academic_status', $user->student?->academic_status ?? 'active');
        $isActiveStudent = $requestedStatus === 'active';

        return [
            'name' => 'required|string|max:255',
            // Kunci bisnis milik baris non-aktif tidak menghalangi pemakaian ulang.
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)->whereNull('deleted_at')],
            'password' => 'nullable|min:6',
            'nis' => ['nullable', Rule::unique('students', 'nis')->ignore($studentId)->whereNull('deleted_at')],
            'nisn' => ['nullable', Rule::unique('students', 'nisn')->ignore($studentId)->whereNull('deleted_at')],
            'classroom_id' => [
                $isActiveStudent ? 'required' : 'nullable',
                'integer',
                'exists:classrooms,id',
                new EnrollableClassroom($studentId),
            ],
            'academic_status' => ['nullable', Rule::in(['active', 'transferred', 'dropped'])],
            'gender' => 'nullable|in:male,female',
            'place_of_birth' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'religion' => 'nullable|string',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'profile_picture' => 'nullable|image|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap siswa wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique' => 'Alamat email ini sudah terdaftar untuk pengguna lain.',
            'password.min' => 'Kata sandi baru minimal terdiri dari 6 karakter.',
            'nis.unique' => 'Nomor Induk Siswa (NIS) ini sudah terdaftar.',
            'nisn.unique' => 'NISN ini sudah terdaftar untuk siswa lain.',
            'classroom_id.required' => 'Rombel wajib dipilih agar siswa langsung punya kelas.',
            'classroom_id.exists' => 'Rombel yang dipilih tidak valid.',
            'academic_status.in' => 'Status akademik yang dipilih tidak valid. Kelulusan hanya lewat proses kelulusan.',
            'profile_picture.image' => 'Foto profil harus berupa file gambar.',
            'profile_picture.max' => 'Ukuran foto profil maksimal 2MB.',
        ];
    }
}
