<?php

namespace App\Services\Api\Academic;

use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\User;

class ScheduleService
{
    /** Return null when the account has no active academic schedule context. */
    public function forUser(User $user, ?int $dayOfWeek): ?array
    {
        $dayOfWeek ??= now()->dayOfWeekIso;

        $academicYear = AcademicYear::currentYear();
        $classroomId = $user->student?->currentEnrollment?->classroom_id;

        if ($user->hasRole('guru') && ($user->employee?->is_teacher ?? true)) {
            $role = 'teacher';
            $query = Schedule::query()
                ->whereHas('assignment', fn ($assignment) => $assignment->where('teacher_id', $user->getKey()));
        } elseif ($classroomId !== null && $user->student->academic_status === 'active') {
            $role = 'student';
            $query = Schedule::query()
                ->whereHas('assignment', fn ($assignment) => $assignment->where('classroom_id', $classroomId));
        } else {
            return null;
        }

        $schedules = $query
            ->where('is_active', true)
            // Jadwal yang tampil hanya milik tahun ajaran yang sedang berjalan.
            ->when($academicYear !== null, fn ($base) => $base->whereHas(
                'assignment.classroom',
                fn ($classroom) => $classroom->where('academic_year_id', $academicYear->getKey()),
            ))
            ->where('day_of_week', $dayOfWeek)
            ->with([
                'assignment.classroom:id,name,level,major,section',
                'assignment.subject:id,code,name,color_code',
                'assignment.teacher:id,name',
            ])
            ->orderBy('start_time')
            ->get()
            ->map(fn (Schedule $schedule) => $this->scheduleData($schedule));

        return [
            'role' => $role,
            'day_of_week' => $dayOfWeek,
            'schedules' => $schedules,
        ];
    }

    private function scheduleData(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'day_of_week' => $schedule->day_of_week,
            'day_name' => $schedule->day_name,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'room' => $schedule->room,
            'classroom' => $schedule->classroom,
            'subject' => $schedule->subject,
            'teacher' => $schedule->teacher,
        ];
    }
}
