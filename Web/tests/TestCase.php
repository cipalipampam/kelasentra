<?php

namespace Tests;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Resolve sebuah tahun ajaran berdasarkan nama menjadi id, membuatnya bila belum ada.
     *
     * Status ditebak dari urutan nama: tahun pertama menjadi periode berjalan,
     * nama yang lebih baru menjadi "akan datang" (maksimal satu), sisanya selesai.
     */
    protected function yearId(string $name): int
    {
        $existing = AcademicYear::query()->where('name', $name)->first();

        if ($existing !== null) {
            return $existing->getKey();
        }

        $current = AcademicYear::currentYear();

        $status = match (true) {
            $current === null => AcademicYear::STATUS_CURRENT,
            $name > $current->name => AcademicYear::STATUS_UPCOMING,
            default => AcademicYear::STATUS_CLOSED,
        };

        if ($status === AcademicYear::STATUS_UPCOMING && AcademicYear::nextYear() !== null) {
            $status = AcademicYear::STATUS_CLOSED;
        }

        return AcademicYear::create(['name' => $name, 'status' => $status])->getKey();
    }

    /**
     * Membuat siswa sekaligus menempatkannya di sebuah rombel lewat enrollment.
     */
    protected function enrollStudent(Classroom $classroom, string $nis, string $status = 'active', ?User $user = null): Student
    {
        $user ??= tap(User::factory()->create(), fn (User $account) => $account->assignRole('siswa'));

        $student = Student::create([
            'user_id' => $user->getKey(),
            'nis' => $nis,
            'academic_status' => $status,
            'entry_academic_year_id' => $classroom->academic_year_id,
        ]);

        StudentEnrollment::create([
            'student_id' => $student->getKey(),
            'academic_year_id' => $classroom->academic_year_id,
            'classroom_id' => $classroom->getKey(),
            'started_at' => now()->toDateString(),
        ]);

        return $student;
    }

    protected function makeSubject(string $name = 'Mata Pelajaran'): Subject
    {
        return Subject::create([
            'code' => 'MAP-'.uniqid(),
            'name' => $name,
        ]);
    }

    /**
     * Membuat jadwal dari atribut gaya lama (classroom_id/subject_id/teacher_id).
     * Penugasan mengajar yang sesuai dibuat atau dipakai ulang secara otomatis.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createSchedule(array $attributes): Schedule
    {
        $classroom = Classroom::findOrFail($attributes['classroom_id']);
        $subject = Subject::findOrFail($attributes['subject_id']);
        $teacher = User::findOrFail($attributes['teacher_id']);

        $assignment = TeachingAssignment::withTrashed()->firstOrCreate([
            'classroom_id' => $classroom->getKey(),
            'subject_id' => $subject->getKey(),
            'teacher_id' => $teacher->getKey(),
        ]);

        if ($assignment->trashed()) {
            $assignment->restore();
        }

        unset($attributes['classroom_id'], $attributes['subject_id'], $attributes['teacher_id']);

        return Schedule::create(array_merge(
            ['teaching_assignment_id' => $assignment->getKey()],
            $attributes,
        ));
    }

    /**
     * Membuat penugasan mengajar beserta satu slot jadwal di dalamnya.
     */
    protected function makeSchedule(
        Classroom $classroom,
        Subject $subject,
        User $teacher,
        int $dayOfWeek = 1,
        string $startTime = '07:00:00',
        string $endTime = '08:00:00',
        array $attributes = [],
    ): Schedule {
        $assignment = TeachingAssignment::create([
            'classroom_id' => $classroom->getKey(),
            'subject_id' => $subject->getKey(),
            'teacher_id' => $teacher->getKey(),
        ]);

        return Schedule::create(array_merge([
            'teaching_assignment_id' => $assignment->getKey(),
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_active' => true,
        ], $attributes));
    }
}
