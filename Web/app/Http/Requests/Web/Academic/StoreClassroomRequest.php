<?php

namespace App\Http\Requests\Web\Academic;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Rules\EligibleHomeroomTeacher;
use App\Rules\HomeroomTeacherAvailable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                // Nama rombel unik per tahun ajaran.
                Rule::unique('classrooms', 'name')
                    ->where(fn ($query) => $query->where('academic_year', $this->input('academic_year'))),
            ],
            'level' => ['required', Rule::in(Classroom::LEVELS)],
            'major' => [
                Rule::requiredIf(fn () => Classroom::majorIsRequiredForLevel($this->input('level'))),
                'nullable',
                'string',
                Rule::in(config('classroom.majors')),
            ],
            'section' => [
                'required',
                'string',
                'max:10',
                // Satu nomor sesi hanya boleh dipakai sekali per tingkat + jurusan + tahun ajaran.
                Rule::unique('classrooms', 'section')
                    ->where(fn ($query) => $query
                        ->where('level', $this->input('level'))
                        ->where('major', $this->input('major'))
                        ->where('academic_year', $this->input('academic_year'))),
            ],
            'academic_year' => [
                'required',
                'string',
                'max:20',
                // Hanya tahun ajaran aktif atau yang akan datang yang boleh dipakai.
                Rule::exists('academic_years', 'name')
                    ->whereIn('status', AcademicYear::SELECTABLE_STATUSES),
            ],
            'homeroom_teacher_id' => [
                'nullable',
                'exists:users,id',
                new EligibleHomeroomTeacher,
                new HomeroomTeacherAvailable($this->input('academic_year')),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama rombel/kelas wajib diisi.',
            'name.unique' => 'Nama rombel ini sudah digunakan pada tahun ajaran yang sama.',
            'level.required' => 'Tingkat kelas wajib dipilih.',
            'level.in' => 'Tingkat kelas harus salah satu dari X, XI, atau XII.',
            'major.required' => 'Jurusan wajib dipilih untuk tingkat XI dan XII.',
            'major.in' => 'Jurusan yang dipilih tidak valid.',
            'section.required' => 'Nomor rombel wajib diisi.',
            'section.unique' => 'Nomor sesi ini sudah dipakai pada tingkat, jurusan, dan tahun ajaran yang sama.',
            'academic_year.required' => 'Tahun ajaran wajib diisi.',
            'academic_year.exists' => 'Tahun ajaran tidak valid. Hanya tahun ajaran aktif atau yang akan datang yang dapat dipilih.',
            'homeroom_teacher_id.exists' => 'Wali kelas yang dipilih tidak valid.',
        ];
    }
}

