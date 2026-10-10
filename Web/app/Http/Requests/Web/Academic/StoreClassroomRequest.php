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
                // Nama rombel unik per tahun ajaran, hanya antar rombel aktif.
                Rule::unique('classrooms', 'name')
                    ->where(fn ($query) => $query
                        ->where('academic_year_id', $this->input('academic_year_id'))
                        ->whereNull('deleted_at')),
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
                        ->where('academic_year_id', $this->input('academic_year_id'))
                        ->whereNull('deleted_at')),
            ],
            'academic_year_id' => [
                'required',
                'integer',
                // Hanya tahun ajaran yang belum selesai yang boleh dipakai.
                Rule::exists('academic_years', 'id')->whereIn('status', AcademicYear::OPERABLE_STATUSES),
            ],
            'homeroom_teacher_id' => [
                'nullable',
                'exists:users,id',
                new EligibleHomeroomTeacher,
                new HomeroomTeacherAvailable($this->integer('academic_year_id') ?: null),
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
            'academic_year_id.exists' => 'Tahun ajaran tidak valid. Hanya tahun ajaran yang belum selesai yang dapat dipilih.',
            'homeroom_teacher_id.exists' => 'Wali kelas yang dipilih tidak valid.',
        ];
    }
}
