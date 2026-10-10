<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Web\Academic\ScheduleService;
use App\Services\Web\Academic\TeachingAssignmentService;
use App\Services\Web\Employee\EmployeeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Penugasan mengajar: satu penugasan aktif per rombel + mapel, guru pengganti
 * sebagai penugasan baru, kelayakan guru, dan penjadwalan yang sadar tahun ajaran.
 */
class TeachingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function classroom(string $name, string $year = '2026/2027', string $level = '10', string $section = '1'): Classroom
    {
        return Classroom::create([
            'name' => $name,
            'level' => $level,
            'major' => 'MIPA',
            'section' => $section,
            'academic_year_id' => $this->yearId($year),
            'is_active' => true,
        ]);
    }

    private function teacherFor(Subject $subject, string $status = 'active'): User
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('guru');
        $teacher->employee()->create([
            'is_teacher' => true,
            'employment_status' => $status,
        ]);
        $teacher->subjects()->attach($subject->getKey(), ['is_primary' => true]);

        return $teacher;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Classroom $classroom, Subject $subject, User $teacher, string $start = '07:00', string $end = '08:00'): array
    {
        return [
            'classroom_id' => $classroom->getKey(),
            'subject_id' => $subject->getKey(),
            'teacher_id' => $teacher->getKey(),
            'day_of_week' => 1,
            'start_time' => $start,
            'end_time' => $end,
            'is_active' => '1',
        ];
    }

    public function test_replacing_a_teacher_keeps_the_previous_assignment_as_history(): void
    {
        $admin = $this->admin();
        $classroom = $this->classroom('X MIPA 1');
        $subject = $this->makeSubject();
        $first = $this->teacherFor($subject);
        $second = $this->teacherFor($subject);

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($classroom, $subject, $first, '07:00', '08:00'))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($classroom, $subject, $second, '08:00', '09:00'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TeachingAssignment::query()->count());
        $this->assertSame(2, TeachingAssignment::withTrashed()->count());
        $this->assertTrue(
            TeachingAssignment::withTrashed()->where('teacher_id', $first->getKey())->firstOrFail()->trashed()
        );
    }

    public function test_staff_cannot_be_assigned_as_a_teaching_teacher(): void
    {
        $admin = $this->admin();
        $classroom = $this->classroom('X MIPA 1');
        $subject = $this->makeSubject();

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->employee()->create(['is_teacher' => false, 'employment_status' => 'active']);
        $staff->subjects()->attach($subject->getKey());

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($classroom, $subject, $staff))
            ->assertSessionHasErrors('teacher_id');
    }

    public function test_resigned_teacher_cannot_receive_a_new_assignment(): void
    {
        $admin = $this->admin();
        $classroom = $this->classroom('X MIPA 1');
        $subject = $this->makeSubject();
        $resigned = $this->teacherFor($subject, 'resigned');

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($classroom, $subject, $resigned))
            ->assertSessionHasErrors('teacher_id');
    }

    public function test_teacher_clash_is_scoped_to_the_same_academic_year(): void
    {
        $admin = $this->admin();
        $current = $this->classroom('X MIPA 1', '2026/2027');
        $nextYear = $this->classroom('X MIPA 2', '2027/2028', '10', '2');
        $subject = $this->makeSubject();
        $teacher = $this->teacherFor($subject);

        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($current, $subject, $teacher, '07:00', '08:00'))
            ->assertSessionHasNoErrors();

        // Jam yang sama pada tahun ajaran berbeda bukan bentrok.
        $this->actingAs($admin)
            ->post(route('admin.schedules.store'), $this->payload($nextYear, $subject, $teacher, '07:00', '08:00'))
            ->assertSessionHasNoErrors();
    }

    public function test_workload_only_counts_the_current_academic_year(): void
    {
        $current = $this->classroom('X MIPA 1', '2026/2027');
        $nextYear = $this->classroom('X MIPA 2', '2027/2028', '10', '2');
        $subject = $this->makeSubject();
        $teacher = $this->teacherFor($subject);

        $this->makeSchedule($current, $subject, $teacher, 1, '07:15:00', '08:45:00');
        $this->makeSchedule($nextYear, $subject, $teacher, 1, '07:15:00', '08:45:00');

        $workload = app(ScheduleService::class)->calculateTeacherWeeklyWorkload($teacher);

        $this->assertSame(2, $workload['total_jp']);
        $this->assertSame(1, $workload['slots_count']);
    }

    public function test_employee_is_marked_as_teacher_based_on_the_role(): void
    {
        $service = app(EmployeeService::class);

        $guru = $service->createEmployee([
            'name' => 'Guru Aktif',
            'email' => 'guru@sekolah.test',
            'password' => 'password123',
            'role' => 'guru',
        ]);

        $staff = $service->createEmployee([
            'name' => 'Staff Sekolah',
            'email' => 'staff@sekolah.test',
            'password' => 'password123',
            'role' => 'staff',
        ]);

        $this->assertTrue($guru->employee->is_teacher);
        $this->assertFalse($staff->employee->is_teacher);
    }

    public function test_role_change_is_blocked_while_teaching_assignments_exist(): void
    {
        $service = app(EmployeeService::class);
        $classroom = $this->classroom('X MIPA 1');
        $subject = $this->makeSubject();

        $teacher = $service->createEmployee([
            'name' => 'Guru Pengampu',
            'email' => 'pengampu@sekolah.test',
            'password' => 'password123',
            'role' => 'guru',
        ]);
        $teacher->subjects()->attach($subject->getKey(), ['is_primary' => true]);

        app(TeachingAssignmentService::class)->assign($classroom, $subject, $teacher);

        $this->expectException(ValidationException::class);

        $service->updateEmployee($teacher, [
            'name' => $teacher->name,
            'email' => $teacher->email,
            'role' => 'staff',
        ]);
    }
}
