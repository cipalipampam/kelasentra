<?php

namespace App\Services\Api\Academic;

use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\ScheduleAttendance;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassAttendanceService
{
    public function isAssignedTeacher(User $user, Schedule $schedule): bool
    {
        return (int) $schedule->teacher?->getKey() === (int) $user->getKey() || $user->hasRole('admin');
    }

    public function getRosterData(Schedule $schedule, ?string $rawDate): array
    {
        $date = $this->date($rawDate);

        return [
            'schedule' => $this->scheduleData($schedule),
            'attendance_date' => $date,
            'students' => $this->roster($schedule, $date),
        ];
    }

    public function date(?string $date): string
    {
        return Carbon::parse($date ?? now())->toDateString();
    }

    public function roster(Schedule $schedule, string $date): array
    {
        $students = Student::query()
            ->whereHas('enrollments', fn ($enrollment) => $enrollment
                ->where('classroom_id', $schedule->classroom_id)
                ->forDate($date)
            )
            ->with([
                'user:id,name',
                'scheduleAttendances' => fn ($query) => $query
                    ->where('schedule_id', $schedule->id)
                    ->whereDate('attendance_date', $date),
            ])
            ->orderBy('nis')
            ->get();

        $dailyLeaveStatuses = Attendance::query()
            ->whereIn('user_id', $students->pluck('user_id'))
            ->whereDate('attendance_date', $date)
            ->whereIn('status', ['sick', 'permission'])
            ->where('is_approved', true)
            ->pluck('status', 'user_id');

        return $students->map(function (Student $student) use ($dailyLeaveStatuses) {
            $savedAttendance = $student->scheduleAttendances->first();
            $defaultStatus = $dailyLeaveStatuses->get($student->user_id, 'present');

            return [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->user?->name,
                'status' => $savedAttendance?->status ?? $defaultStatus,
                'notes' => $savedAttendance?->notes,
                'is_saved' => (bool) $savedAttendance,
                'is_prefilled_from_daily_attendance' => ! $savedAttendance && $defaultStatus !== 'present',
            ];
        })->values()->all();
    }

    /**
     * Mengambil enrollment yang aktif untuk daftar siswa pada tanggal kejadian,
     * yang harus cocok dengan rombel schedule.
     *
     * @param  iterable<int>  $studentIds
     * @return Collection<int, StudentEnrollment> Keyed by student_id
     */
    public function resolveEnrollmentsForDate(iterable $studentIds, string $date, ?int $classroomId = null): Collection
    {
        $ids = collect($studentIds)->filter()->unique()->values()->all();

        if (empty($ids)) {
            return collect();
        }

        return StudentEnrollment::query()
            ->whereIn('student_id', $ids)
            ->when($classroomId !== null, fn ($query) => $query->where('classroom_id', $classroomId))
            ->forDate($date)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');
    }

    public function hasOnlyEligibleStudents(Schedule $schedule, array $attendances, ?string $rawDate = null): bool
    {
        $date = $this->date($rawDate);
        $studentIds = collect($attendances)->pluck('student_id')->filter()->unique();

        if ($studentIds->isEmpty()) {
            return false;
        }

        $enrollments = $this->resolveEnrollmentsForDate($studentIds, $date, $schedule->classroom_id);

        return $enrollments->count() === $studentIds->count();
    }

    public function store(User $teacher, Schedule $schedule, ?string $rawDate, array $attendances): array
    {
        if (! $schedule->is_active) {
            throw ValidationException::withMessages([
                'schedule' => 'Jadwal ini sudah tidak aktif.',
            ]);
        }

        $date = $this->date($rawDate);
        $studentIds = collect($attendances)->pluck('student_id')->filter()->unique();

        if ($studentIds->isEmpty()) {
            throw ValidationException::withMessages([
                'attendances' => 'Daftar presensi siswa wajib diisi.',
            ]);
        }

        $enrollments = $this->resolveEnrollmentsForDate($studentIds, $date, $schedule->classroom_id);

        if ($enrollments->count() !== $studentIds->count()) {
            throw ValidationException::withMessages([
                'attendances' => 'Daftar presensi memuat siswa yang bukan anggota aktif kelas ini pada tanggal presensi.',
            ]);
        }

        $now = now();

        $records = collect($attendances)->map(fn (array $attendance) => [
            'schedule_id' => $schedule->id,
            'student_id' => $attendance['student_id'],
            'student_enrollment_id' => $enrollments->get($attendance['student_id'])->getKey(),
            'teacher_id' => $teacher->id,
            'attendance_date' => $date,
            'status' => $attendance['status'],
            'notes' => $attendance['notes'] ?? null,
            'recorded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        $createdNotifications = DB::transaction(function () use ($records, $schedule, $date) {
            ScheduleAttendance::upsert(
                $records,
                ['schedule_id', 'student_id', 'attendance_date'],
                ['student_enrollment_id', 'teacher_id', 'status', 'notes', 'recorded_at', 'updated_at'],
            );

            return $this->createAbsenceNotifications($schedule, $date);
        });

        return [
            'schedule_id' => $schedule->id,
            'attendance_date' => $date,
            'recorded_students' => count($records),
            'created_notifications' => $createdNotifications,
        ];
    }

    public function scheduleData(Schedule $schedule): array
    {
        $schedule->loadMissing(['assignment.classroom:id,name', 'assignment.subject:id,code,name']);

        return [
            'id' => $schedule->id,
            'classroom' => $schedule->classroom,
            'subject' => $schedule->subject,
            'day_of_week' => $schedule->day_of_week,
            'day_name' => $schedule->day_name,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
        ];
    }

    /** A re-submitted attendance payload must not create duplicate absence notices. */
    private function createAbsenceNotifications(Schedule $schedule, string $date): int
    {
        $absences = ScheduleAttendance::query()
            ->where('schedule_id', $schedule->id)
            ->whereDate('attendance_date', $date)
            ->where('status', 'absent')
            ->with('student.user:id,name')
            ->get();

        $schedule->loadMissing(['assignment.classroom:id,name', 'assignment.subject:id,name']);
        $created = 0;

        foreach ($absences as $absence) {
            $studentUser = $absence->student?->user;

            if (! $studentUser) {
                continue;
            }

            $notification = AppNotification::query()
                ->where('user_id', $studentUser->id)
                ->where('type', 'attendance')
                ->where('data->schedule_attendance_id', $absence->id)
                ->firstOrCreate([], [
                    'user_id' => $studentUser->id,
                    'title' => 'Presensi mata pelajaran',
                    'body' => sprintf(
                        'Anda tercatat alfa pada %s di kelas %s tanggal %s.',
                        $schedule->subject?->name,
                        $schedule->classroom?->name,
                        $date,
                    ),
                    'type' => 'attendance',
                    'data' => [
                        'screen' => 'schedule_attendance',
                        'schedule_id' => $schedule->id,
                        'schedule_attendance_id' => $absence->id,
                        'attendance_date' => $date,
                    ],
                ]);

            $created += $notification->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }
}
