<?php

namespace App\Http\Requests\Web\Academic;

use App\Rules\EligibleTeachingTeacher;
use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['required', 'exists:users,id', new EligibleTeachingTeacher($this->integer('subject_id') ?: null)],
            'day_of_week' => ['required', 'integer', 'between:1,5'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'classroom_id.required' => 'Rombel kelas wajib dipilih.',
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'teacher_id.required' => 'Guru pengampu wajib dipilih.',
            'day_of_week.between' => 'Hari KBM hanya berlaku untuk Senin sampai Jumat (Full Day School).',
            'start_time.required' => 'Jam mulai mengajar wajib diisi.',
            'end_time.required' => 'Jam selesai mengajar wajib diisi.',
            'end_time.after' => 'Jam selesai harus lebih besar dari jam mulai.',
        ];
    }
}
