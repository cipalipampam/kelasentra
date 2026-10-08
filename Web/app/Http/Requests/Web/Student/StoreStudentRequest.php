<?php

namespace App\Http\Requests\Web\Student;

use App\Rules\EnrollableClassroom;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->hasRole('admin');
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'nis' => 'nullable|unique:students,nis',
            'nisn' => 'nullable|unique:students,nisn',
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id', new EnrollableClassroom],
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
            'password.required' => 'Kata sandi akun wajib diisi.',
            'password.min' => 'Kata sandi minimal terdiri dari 6 karakter.',
            'nis.unique' => 'Nomor Induk Siswa (NIS) ini sudah terdaftar.',
            'nisn.unique' => 'NISN ini sudah terdaftar untuk siswa lain.',
            'classroom_id.required' => 'Rombel wajib dipilih agar siswa langsung punya kelas.',
            'classroom_id.exists' => 'Rombel yang dipilih tidak valid.',
            'profile_picture.image' => 'Foto profil harus berupa file gambar.',
            'profile_picture.max' => 'Ukuran foto profil maksimal 2MB.',
        ];
    }
}
