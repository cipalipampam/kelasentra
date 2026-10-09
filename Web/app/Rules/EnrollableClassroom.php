<?php

namespace App\Rules;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Memastikan rombel yang dipilih untuk seorang siswa berada pada tahun ajaran
 * yang masih berjalan (aktif atau akan datang) dan masih punya kursi.
 *
 * Saat mengedit, hitungan kursi mengecualikan siswa itu sendiri agar rombel
 * yang sudah tepat penuh tidak menolak penyimpanan perubahan data lain.
 */
class EnrollableClassroom implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreStudentId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $classroom = Classroom::find($value);

        if (! $classroom) {
            $fail('Rombel yang dipilih tidak ditemukan.');

            return;
        }

        $currentClassroomId = $this->ignoreStudentId
            ? Student::whereKey($this->ignoreStudentId)->value('classroom_id')
            : null;

        // Siswa yang tetap di rombelnya sekarang tidak menempati kursi baru,
        // sehingga pengecekan tahun ajaran dan kapasitas dilewati.
        if ($currentClassroomId === $classroom->id) {
            return;
        }

        // Rombel tahun ajaran aktif maupun yang akan datang boleh menerima
        // siswa, karena rombel tahun ajaran berikutnya memang disiapkan lebih
        // dulu sebelum tahun ajarannya berganti.
        if (! $classroom->is_active || ! in_array($classroom->academic_year, AcademicYear::selectableNames(), true)) {
            $fail("Rombel {$classroom->name} bukan rombel tahun ajaran aktif atau yang akan datang, sehingga belum dapat menerima siswa.");

            return;
        }

        $occupied = $classroom->students()
            ->where('academic_status', 'active')
            ->count();

        if ($occupied >= $classroom->maxStudents()) {
            $fail("Rombel {$classroom->name} sudah penuh ({$classroom->maxStudents()} siswa). Silakan pilih rombel lain.");
        }
    }
}
