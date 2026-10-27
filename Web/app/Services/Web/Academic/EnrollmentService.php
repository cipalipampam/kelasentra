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
     * Invariant ditegakkan di sini sebagai boundary penulis tunggal:
     * 1. Siswa harus berstatus akademik aktif (spec §4.3).
     * 2. Rombel harus berstatus aktif (spec §4.4).
     * 3. Tahun ajaran rombel harus operable (current atau upcoming) (spec §4.4).
     * 4. Kapasitas rombel tidak boleh terlampaui (row lock).
     */
    public function place(Student $student, Classroom $classroom, ?string $startedAt = null): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $classroom, $startedAt) {
            /** @var Classroom $locked */
            $locked = Classroom::query()
                ->with('academicYear')
                ->whereKey($classroom->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $student->isActive()) {
                throw ValidationException::withMessages([
                    'academic_status' => "Siswa {$student->nis} berstatus '{$student->academic_status}', hanya siswa aktif yang dapat memiliki enrollment.",
                ]);
            }

            if (! $locked->is_active) {
                throw ValidationException::withMessages([
                    'classroom_id' => "Rombel {$locked->name} tidak aktif sehingga tidak dapat menerima siswa.",
                ]);
            }

            if (! $locked->academicYear?->isOperable()) {
                throw ValidationException::withMessages([
                    'classroom_id' => "Tahun ajaran rombel {$locked->name} sudah selesai sehingga tidak dapat menerima siswa.",
                ]);
            }

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
     * Operasi bersifat idempoten.
     */
    public function closeCurrent(Student $student, ?string $endedAt = null): void
    {
        StudentEnrollment::query()
            ->where('student_id', $student->getKey())
            ->whereNull('ended_at')
            ->update([
                'ended_at' => $endedAt ?? now()->toDateString(),
            ]);

        $student->unsetRelation('currentEnrollment');
    }
}
