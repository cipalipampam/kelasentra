<?php

namespace App\Services\Web\Academic;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Carbon\Carbon;
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
     * 5. Provenance batch dicatat bila penempatan berasal dari batch promosi/retain.
     */
    public function place(
        Student $student,
        Classroom $classroom,
        ?string $startedAt = null,
        ?int $promotionBatchId = null
    ): StudentEnrollment {
        return DB::transaction(function () use ($student, $classroom, $startedAt, $promotionBatchId) {
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
                if ($promotionBatchId !== null && $current->promotion_batch_id !== $promotionBatchId) {
                    $current->update(['promotion_batch_id' => $promotionBatchId]);
                }

                return $current;
            }

            // Kursi dihitung dari enrollment berjalan rombel tujuan, dan siswa
            // yang pindah dari rombel ini tidak menempati kursi tambahan.
            $occupied = StudentEnrollment::query()
                ->where('classroom_id', $locked->getKey())
                ->whereNull('ended_at')
                ->when($current !== null, fn ($query) => $query->whereKeyNot($current->getKey()))
                ->count();

            if ($occupied >= $locked->maxStudents()) {
                throw ValidationException::withMessages([
                    'classroom_id' => "Rombel {$locked->name} sudah penuh ({$locked->maxStudents()} siswa). Silakan pilih rombel lain.",
                ]);
            }

            if ($current !== null) {
                // A-06: Tutup enrollment berjalan tanpa tumpang tindih tanggal.
                // Jika started_at sebelumnya sebelum startDate, akhiri sehari sebelum startDate.
                // Jika started_at sama/lebih baru, samakan ended_at dengan started_at.
                $currentStart = $current->started_at ? $current->started_at->toDateString() : $startDate;
                $previousEndedAt = $currentStart < $startDate
                    ? Carbon::parse($startDate)->subDay()->toDateString()
                    : $currentStart;

                $current->update(['ended_at' => $previousEndedAt]);
            }

            // A-02: Selalu buat baris enrollment baru (append-only history)
            // agar perpindahan rombel dalam tahun ajaran sama tidak menghapus histori lama.
            $newEnrollment = StudentEnrollment::create([
                'student_id' => $student->getKey(),
                'academic_year_id' => $locked->academic_year_id,
                'classroom_id' => $locked->getKey(),
                'started_at' => $startDate,
                'promotion_batch_id' => $promotionBatchId,
            ]);

            $student->unsetRelation('currentEnrollment');

            return $newEnrollment;
        });
    }

    /**
     * Membatalkan penempatan promosi: menutup enrollment tujuan (tetap mencatat batch ID)
     * dan membuka kembali enrollment asal.
     */
    public function revertPromotion(
        Student $student,
        Classroom $fromClassroom,
        int $toClassroomId,
        int $batchId
    ): void {
        $fromClassroom->loadMissing('academicYear');
        if (! $fromClassroom->academicYear?->isOperable()) {
            throw ValidationException::withMessages([
                'academic_year' => "Tahun ajaran rombel {$fromClassroom->name} sudah selesai sehingga tidak dapat dibuka kembali.",
            ]);
        }

        // 1. Tutup enrollment berjalan di rombel tujuan (tetap mempertahankan promotion_batch_id)
        StudentEnrollment::query()
            ->where('student_id', $student->getKey())
            ->where('classroom_id', $toClassroomId)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now()->toDateString(),
            ]);

        // 2. Buka kembali enrollment rombel asal
        $sourceEnrollment = StudentEnrollment::query()
            ->where('student_id', $student->getKey())
            ->where('classroom_id', $fromClassroom->getKey())
            ->where('academic_year_id', $fromClassroom->academic_year_id)
            ->first();

        if ($sourceEnrollment !== null) {
            $sourceEnrollment->update(['ended_at' => null]);
        } else {
            StudentEnrollment::create([
                'student_id' => $student->getKey(),
                'classroom_id' => $fromClassroom->getKey(),
                'academic_year_id' => $fromClassroom->academic_year_id,
                'started_at' => now()->toDateString(),
                'ended_at' => null,
            ]);
        }

        $student->unsetRelation('currentEnrollment');
    }

    /**
     * Membatalkan kelulusan: membuka kembali enrollment rombel asal yang sebelumnya ditutup.
     */
    public function revertGraduation(Student $student, Classroom $fromClassroom): void
    {
        $fromClassroom->loadMissing('academicYear');
        if (! $fromClassroom->academicYear?->isOperable()) {
            throw ValidationException::withMessages([
                'academic_year' => "Tahun ajaran rombel {$fromClassroom->name} sudah selesai sehingga tidak dapat dibuka kembali.",
            ]);
        }
        $sourceEnrollment = StudentEnrollment::query()
            ->where('student_id', $student->getKey())
            ->where('classroom_id', $fromClassroom->getKey())
            ->where('academic_year_id', $fromClassroom->academic_year_id)
            ->first();

        if ($sourceEnrollment !== null) {
            $sourceEnrollment->update(['ended_at' => null]);
        } else {
            StudentEnrollment::create([
                'student_id' => $student->getKey(),
                'classroom_id' => $fromClassroom->getKey(),
                'academic_year_id' => $fromClassroom->academic_year_id,
                'started_at' => now()->toDateString(),
                'ended_at' => null,
            ]);
        }

        $student->unsetRelation('currentEnrollment');
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
