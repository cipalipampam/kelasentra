<?php

namespace App\Services\Web\Academic;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        if (! $classroom->is_active) {
            throw ValidationException::withMessages([
                'classroom_id' => "Rombel {$classroom->name} tidak aktif sehingga tidak dapat menerima penugasan mengajar.",
            ]);
        }

        $classroom->loadMissing('academicYear');
        if (! $classroom->academicYear?->isOperable()) {
            throw ValidationException::withMessages([
                'classroom_id' => "Tahun ajaran rombel {$classroom->name} sudah selesai sehingga tidak dapat menerima penugasan mengajar.",
            ]);
        }

        return DB::transaction(function () use ($classroom, $subject, $teacher) {
            $active = TeachingAssignment::query()
                ->where('classroom_id', $classroom->getKey())
                ->where('subject_id', $subject->getKey())
                ->first();

            if ($active !== null && (int) $active->teacher_id === (int) $teacher->getKey()) {
                return $active;
            }

            $oldAssignmentId = $active?->getKey();
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
                $newAssignment = $restorable->refresh();
            } else {
                $newAssignment = TeachingAssignment::create([
                    'classroom_id' => $classroom->getKey(),
                    'subject_id' => $subject->getKey(),
                    'teacher_id' => $teacher->getKey(),
                ]);
            }

            if ($oldAssignmentId !== null) {
                // A-01: Pindahkan seluruh slot jadwal aktif dari penugasan lama ke penugasan baru
                // agar tidak ada slot yang menjadi orphan menunjuk assignment soft-deleted.
                Schedule::where('teaching_assignment_id', $oldAssignmentId)
                    ->update(['teaching_assignment_id' => $newAssignment->getKey()]);
            }

            return $newAssignment;
        });
    }
}
