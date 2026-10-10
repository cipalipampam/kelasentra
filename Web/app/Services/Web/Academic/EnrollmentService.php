<?php

namespace App\Services\Web\Academic;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya penulis data enrollment.
 *
 * Menjaga invariant: maksimal satu enrollment berjalan per siswa, kapasitas
 * rombel tidak terlampaui, dan riwayat penempatan lama tidak dihapus.
 */
class EnrollmentService
{
    /**
     * Menempatkan siswa pada sebuah rombel.
     *
     * Kapasitas rombel dikunci dengan row lock agar dua pendaftaran bersamaan
     * tidak melewati batas. Enrollment berjalan sebelumnya ditutup, bukan dihapus.
     */
    public function place(Student $student, Classroom $classroom, ?string $startedAt = null): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $classroom, $startedAt) {
            /** @var Classroom $locked */
            $locked = Classroom::query()->whereKey($classroom->getKey())->lockForUpdate()->firstOrFail();

            $startDate = $startedAt ?? now()->toDateString();

            $current = $student->currentEnrollment()->first();

            // Siswa yang sudah berada di rombel ini tidak menempati kursi baru.
            if ($current !== null && (int) $current->classroom_id === (int) $locked->getKey()) {
                return $current;
            }

            $existingForYear = StudentEnrollment::query()
                ->where('student_id', $student->getKey())
                ->where('academic_year_id', $locked->academic_year_id)
                ->first();

            $occupant = $existingForYear ?? $current;

            // Kursi dihitung dari enrollment berjalan rombel tujuan, dan siswa
            // yang pindah dari rombel ini tidak menempati kursi tambahan.
            $occupied = StudentEnrollment::query()
                ->where('classroom_id', $locked->getKey())
                ->whereNull('ended_at')
                ->when($occupant !== null, fn ($query) => $query->whereKeyNot($occupant->getKey()))
                ->count();

            if ($occupied >= $locked->maxStudents()) {
                throw ValidationException::withMessages([
                    'classroom_id' => "Rombel {$locked->name} sudah penuh ({$locked->maxStudents()} siswa). Silakan pilih rombel lain.",
                ]);
            }

            if ($current !== null) {
                $current->update(['ended_at' => $startDate]);
            }

            if ($existingForYear !== null) {
                $existingForYear->update([
                    'classroom_id' => $locked->getKey(),
                    'started_at' => $existingForYear->started_at ?? $startDate,
                    'ended_at' => null,
                ]);

                return $existingForYear;
            }

            return StudentEnrollment::create([
                'student_id' => $student->getKey(),
                'academic_year_id' => $locked->academic_year_id,
                'classroom_id' => $locked->getKey(),
                'started_at' => $startDate,
            ]);
        });
    }

    /**
     * Menutup enrollment berjalan tanpa membuat pengganti.
     * Dipakai untuk siswa pindah, keluar, atau lulus.
     */
    public function closeCurrent(Student $student, ?string $endedAt = null): void
    {
        $student->currentEnrollment()->first()?->update([
            'ended_at' => $endedAt ?? now()->toDateString(),
        ]);
    }
}
