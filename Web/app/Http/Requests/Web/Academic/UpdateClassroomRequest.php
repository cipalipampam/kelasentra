<?php

namespace App\Http\Requests\Web\Academic;

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
        $academicYearId = $classroom?->academic_year_id;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                // Nama rombel unik per tahun ajaran, hanya antar rombel aktif.
                Rule::unique('classrooms', 'name')
                    ->where(fn ($query) => $query
                        ->where('academic_year_id', $academicYearId)
                        ->whereNull('deleted_at'))
                    ->ignore($classroom?->id),
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
                        ->where('academic_year_id', $academicYearId)
                        ->whereNull('deleted_at'))
                    ->ignore($classroom?->id),
            ],
            // Tahun ajaran rombel tidak boleh diubah setelah dibuat.
            'academic_year_id' => ['required', 'integer', Rule::in([$academicYearId])],
            'homeroom_teacher_id' => [
                'nullable',
                'exists:users,id',
                new EligibleHomeroomTeacher,
                new HomeroomTeacherAvailable($academicYearId, $classroom?->id),
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
            'academic_year_id.required' => 'Tahun ajaran wajib diisi.',
            'academic_year_id.in' => 'Tahun ajaran rombel tidak dapat diubah setelah dibuat.',
            'homeroom_teacher_id.exists' => 'Wali kelas yang dipilih tidak valid.',
        ];
    }
}
