<?php

namespace App\Http\Requests\Web\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Kode milik mata pelajaran non-aktif tidak menghalangi pemakaian ulang.
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:100'],
            'cluster' => ['required', 'string', 'in:mipa,bahasa,ips,umum'],
            'color_code' => ['required', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode mata pelajaran wajib diisi.',
            'code.unique' => 'Kode mata pelajaran ini sudah digunakan.',
            'name.required' => 'Nama mata pelajaran wajib diisi.',
            'color_code.required' => 'Warna aksen wajib dipilih.',
        ];
    }
}
