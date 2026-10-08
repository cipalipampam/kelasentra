<?php

namespace App\Http\Requests\Web\Employee;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->hasRole('admin');
    }

    public function rules()
    {
        $userId = $this->route('employee');
        $user = User::with('employee')->findOrFail($userId);
        $employeeId = $user->employee ? $user->employee->id : null;

        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|min:6',
            'role' => 'required|in:guru,staff',
            'nip' => ['nullable', Rule::unique('employees', 'nip')->ignore($employeeId)],
            'position' => 'nullable|string|max:100',
            'employment_status' => ['nullable', Rule::in(Employee::STATUSES)],
            'gender' => 'nullable|in:male,female',
            'place_of_birth' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'religion' => 'nullable|string',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'profile_picture' => 'nullable|image|max:2048',
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
            'primary_subject_id' => 'nullable|exists:subjects,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap pegawai wajib diisi.',
            'name.max' => 'Nama tidak boleh melebihi :max karakter.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar oleh pengguna lain.',
            'password.min' => 'Kata sandi baru minimal berjumlah :min karakter.',
            'role.required' => 'Klasifikasi peran wajib dipilih (Guru atau Staf).',
            'role.in' => 'Peran yang dipilih tidak valid.',
            'employment_status.in' => 'Status kepegawaian yang dipilih tidak valid.',
            'nip.unique' => 'Nomor Induk Pegawai (NIP) ini sudah terdaftar pada akun lain.',
            'gender.in' => 'Pilihan jenis kelamin tidak valid.',
            'date_of_birth.date' => 'Format tanggal lahir tidak valid.',
            'profile_picture.image' => 'Berkas pas foto harus berformat gambar (JPG, PNG, JPEG).',
            'profile_picture.max' => 'Ukuran pas foto maksimal 2 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'email' => 'alamat email',
            'password' => 'kata sandi baru',
            'role' => 'peran kepegawaian',
            'nip' => 'NIP',
            'position' => 'jabatan / posisi',
            'employment_status' => 'status kepegawaian',
            'gender' => 'jenis kelamin',
            'place_of_birth' => 'tempat lahir',
            'date_of_birth' => 'tanggal lahir',
            'religion' => 'agama',
            'address' => 'alamat domisili',
            'phone_number' => 'nomor telepon / WhatsApp',
            'profile_picture' => 'pas foto',
        ];
    }
}
