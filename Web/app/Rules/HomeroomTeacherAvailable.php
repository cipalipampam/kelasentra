<?php

namespace App\Rules;

use App\Models\AcademicYear;
use App\Models\Classroom;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Memastikan satu guru hanya menjadi wali kelas pada satu rombel
 * dalam tahun ajaran yang sama.
 */
class HomeroomTeacherAvailable implements ValidationRule
{
    public function __construct(
        private readonly ?int $academicYearId,
        private readonly ?int $ignoreClassroomId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $this->academicYearId === null) {
            return;
        }

        $alreadyAssigned = Classroom::query()
            ->where('homeroom_teacher_id', $value)
            ->where('academic_year_id', $this->academicYearId)
            ->when(
                $this->ignoreClassroomId !== null,
                fn ($query) => $query->whereKeyNot($this->ignoreClassroomId),
            )
            ->exists();

        if ($alreadyAssigned) {
            $yearName = AcademicYear::query()->whereKey($this->academicYearId)->value('name');
            $fail("Guru ini sudah menjadi wali kelas pada rombel lain di tahun ajaran {$yearName}.");
        }
    }
}
