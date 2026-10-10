<?php

namespace App\Services\Web\Academic;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mengelola penugasan guru (teaching assignment).
 *
 * Aturan: satu mapel pada satu rombel hanya punya satu penugasan aktif.
 * Guru pengganti dibuat sebagai penugasan baru, sedangkan penugasan lama
 * di-soft-delete agar jadwal dan riwayatnya tetap utuh.
 */
class TeachingAssignmentService
{
    public function assign(Classroom $classroom, Subject $subject, User $teacher): TeachingAssignment
    {
        return DB::transaction(function () use ($classroom, $subject, $teacher) {
            $active = TeachingAssignment::query()
                ->where('classroom_id', $classroom->getKey())
                ->where('subject_id', $subject->getKey())
                ->first();

            if ($active !== null && (int) $active->teacher_id === (int) $teacher->getKey()) {
                return $active;
            }

            $active?->delete();

            // Penugasan yang pernah ada untuk kombinasi ini dipakai ulang agar
            // tidak melanggar unique (rombel, mapel, guru).
            $restorable = TeachingAssignment::withTrashed()
                ->where('classroom_id', $classroom->getKey())
                ->where('subject_id', $subject->getKey())
                ->where('teacher_id', $teacher->getKey())
                ->first();

            if ($restorable !== null) {
                $restorable->restore();

                return $restorable->refresh();
            }

            return TeachingAssignment::create([
                'classroom_id' => $classroom->getKey(),
                'subject_id' => $subject->getKey(),
                'teacher_id' => $teacher->getKey(),
            ]);
        });
    }
}
