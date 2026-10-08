<?php

namespace App\Http\Requests\Web\Academic;

use App\Models\AcademicYear;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/', 'unique:academic_years,name'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(AcademicYear::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tahun ajaran wajib diisi.',
            'name.regex' => 'Format tahun ajaran harus YYYY/YYYY, contoh: 2026/2027.',
            'name.unique' => 'Tahun ajaran ini sudah terdaftar.',
            'start_date.date' => 'Tanggal mulai tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'status.required' => 'Status tahun ajaran wajib dipilih.',
            'status.in' => 'Status tahun ajaran yang dipilih tidak valid.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('name')) {
                return;
            }

            $years = explode('/', (string) $this->input('name'));

            if (count($years) === 2 && (int) $years[1] !== (int) $years[0] + 1) {
                $validator->errors()->add('name', 'Tahun ajaran harus berurutan, contoh: 2026/2027.');
            }
        });
    }
}
