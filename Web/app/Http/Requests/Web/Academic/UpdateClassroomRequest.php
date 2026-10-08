<?php

namespace App\Http\Requests\Web\Academic;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Rules\EligibleHomeroomTeacher;
use App\Rules\HomeroomTeacherAvailable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $classroom = $this->route('classroom');
        $classroom = $classroom instanceof Classroom ? $classroom : null;
        $academicYear = $this->input('academic_year');

        // Rombel lama boleh mempertahankan tahun ajaran yang sudah diarsipkan,
        // tetapi penggantian ke tahun ajaran lain wajib memakai tahun ajaran valid.
        $keepsCurrentYear = $classroom !== null && $academicYear === $classroom->academic_year;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                // Nama rombel unik per tahun ajaran.
                Rule::unique('classrooms', 'name')
                    ->where(fn ($query) => $query->where('academic_year', $academicYear))
                    ->ignore($classroom?->id),
            ],
            'level' => ['required', Rule::in(Classroom::LEVELS)],
            'major' => [
                Rule::requiredIf(fn () => Classroom::majorIsRequiredForLevel($this->input('level'))),
                'nullable',
                'string',
                Rule::in(config('classroom.majors')),
            ],
            'section' => ['required', 'string', 'max:10'],
            'academic_year' => array_values(array_filter([
                'required',
                'string',
                'max:20',
                // Hanya tahun ajaran aktif atau yang akan datang yang boleh dipakai.
                $keepsCurrentYear
                    ? null
                    : Rule::exists('academic_years', 'name')->whereIn('status', AcademicYear::SELECTABLE_STATUSES),
            ])),
            'homeroom_teacher_id' => [
                'nullable',
                'exists:users,id',
                new EligibleHomeroomTeacher,
                new HomeroomTeacherAvailable($academicYear, $classroom?->id),
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
            'academic_year.required' => 'Tahun ajaran wajib diisi.',
            'academic_year.exists' => 'Tahun ajaran tidak valid. Hanya tahun ajaran aktif atau yang akan datang yang dapat dipilih.',
            'homeroom_teacher_id.exists' => 'Wali kelas yang dipilih tidak valid.',
        ];
    }
}

