<?php

namespace App\Http\Requests\Web\Academic;

use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;

class ProcessClassPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:promote,graduate'],
            'source_classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'target_classroom_id' => [
                'required_if:action,promote',
                'nullable',
                'different:source_classroom_id',
                'exists:classrooms,id',
            ],
            'student_ids' => ['required', 'array', 'min:1', 'max:'.Classroom::studentCapacity()],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Jenis aksi (Kenaikan Kelas atau Kelulusan) wajib dipilih.',
            'action.in' => 'Pilihan aksi tidak valid.',
            'source_classroom_id.required' => 'Kelas asal wajib dipilih.',
            'source_classroom_id.exists' => 'Kelas asal yang dipilih tidak ditemukan.',
            'target_classroom_id.required_if' => 'Kelas tujuan wajib dipilih jika memilih aksi Kenaikan Kelas.',
            'target_classroom_id.different' => 'Kelas tujuan tidak boleh sama dengan kelas asal.',
            'target_classroom_id.exists' => 'Kelas tujuan yang dipilih tidak ditemukan.',
            'student_ids.required' => 'Minimal satu siswa harus dipilih untuk diproses.',
            'student_ids.min' => 'Paling sedikit satu siswa harus dipilih.',
            'student_ids.max' => 'Maksimal :max siswa dapat diproses sekaligus.',
            'student_ids.*.exists' => 'Data siswa yang dipilih tidak valid.',
            'student_ids.*.distinct' => 'Daftar siswa tidak boleh berisi data ganda.',
        ];
    }
}
