<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ScheduleAttendance;
use App\Models\Student;
use App\Models\User;
use App\Services\Shared\Attendance\AttendanceContext;
use App\Services\Web\Academic\EnrollmentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Presensi sebagai riwayat: konteks akademik dibekukan saat pencatatan,
 * dan penghapusan data (soft delete) tidak boleh menghilangkan histori.
 */
class AttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        AcademicYear::create(['name' => '2026/2027', 'status' => AcademicYear::STATUS_CURRENT]);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function classroom(array $attributes = []): Classroom
    {
        return Classroom::create(array_merge([
            'name' => 'XI MIPA 1',
            'level' => '11',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $this->yearId('2026/2027'),
            'is_active' => true,
        ], $attributes));
    }

    private function recordAttendance(Student $student, string $date = '2026-09-21'): Attendance
    {
        return Attendance::create(array_merge(AttendanceContext::forUser($student->user), [
            'user_id' => $student->user_id,
            'recorded_at' => $date.' 07:00:00',
            'status' => 'present',
        ]));
    }

    public function test_gate_attendance_freezes_the_academic_context(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->classroom();
        $student = $this->enrollStudent($classroom, 'S-001');

        $this->actingAs($admin)->post(route('admin.attendances.store'), [
            'user_id' => $student->user_id,
            'recorded_at' => '2026-09-21 07:00:00',
            'status' => 'present',
            'scope' => 'siswa',
        ])->assertSessionHasNoErrors();

        $attendance = Attendance::firstOrFail();

        $this->assertSame($classroom->academic_year_id, $attendance->academic_year_id);
        $this->assertSame($classroom->id, $attendance->classroom_id);
    }

    public function test_history_stays_with_the_rombel_recorded_at_that_time(): void
    {
        $admin = $this->createAdmin();
        $old = $this->classroom();
        $new = $this->classroom(['name' => 'XI MIPA 2', 'section' => '2']);
        $student = $this->enrollStudent($old, 'S-002');

        $this->recordAttendance($student);

        app(EnrollmentService::class)->place($student, $new);

        $this->actingAs($admin)
            ->get(route('admin.attendances.students', ['classroom_id' => $old->id, 'date' => '2026-09-21']))
            ->assertOk()
            ->assertSee($student->user->name);

        $this->actingAs($admin)
            ->get(route('admin.attendances.students', ['classroom_id' => $new->id, 'date' => '2026-09-21']))
            ->assertOk()
            ->assertDontSee($student->user->name);
    }

    public function test_subject_attendance_stores_the_enrollment_snapshot(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('guru');
        $classroom = $this->classroom();
        $subject = $this->makeSubject('Matematika');
        $schedule = $this->makeSchedule($classroom, $subject, $teacher);
        $student = $this->enrollStudent($classroom, 'S-003');
        $enrollmentId = $student->currentEnrollment->id;

        Sanctum::actingAs($teacher);

        $this->postJson("/api/v1/schedules/{$schedule->id}/class-attendance", [
            'attendance_date' => '2026-09-21',
            'attendances' => [[
                'student_id' => $student->id,
                'status' => 'absent',
            ]],
        ])->assertOk();

        $record = ScheduleAttendance::firstOrFail();

        $this->assertSame($enrollmentId, $record->student_enrollment_id);
        $this->assertSame($enrollmentId, $record->enrollment?->id);
    }

    public function test_deleting_a_schedule_keeps_subject_attendance_history_readable(): void
    {
        $admin = $this->createAdmin();
        $teacher = User::factory()->create();
        $teacher->assignRole('guru');
        $classroom = $this->classroom();
        $subject = $this->makeSubject('Fisika');
        $schedule = $this->makeSchedule($classroom, $subject, $teacher);
        $student = $this->enrollStudent($classroom, 'S-004');

        $record = ScheduleAttendance::create([
            'schedule_id' => $schedule->id,
            'student_id' => $student->id,
            'student_enrollment_id' => $student->currentEnrollment->id,
            'teacher_id' => $teacher->id,
            'attendance_date' => '2026-09-21',
            'status' => 'present',
            'recorded_at' => '2026-09-21 07:00:00',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.schedules.destroy', $schedule->id))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
        $this->assertDatabaseHas('schedule_attendances', ['id' => $record->id]);

        $record->refresh();

        // Riwayat tetap bisa menjelaskan dirinya walau jadwalnya sudah nonaktif.
        $this->assertSame('Fisika', $record->schedule->subject?->name);
        $this->assertSame($classroom->name, $record->schedule->classroom?->name);
        $this->assertSame($teacher->name, $record->schedule->teacher?->name);
    }

    public function test_soft_deleted_student_frees_the_nis_and_email_for_reuse(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->classroom();
        $student = $this->enrollStudent($classroom, 'N-100');
        $email = $student->user->email;

        $this->actingAs($admin)
            ->delete(route('admin.students.destroy', $student->user_id))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->assertSoftDeleted('users', ['id' => $student->user_id]);
        // Kursi di rombel kembali kosong karena enrollment berjalan ditutup.
        $this->assertSame(0, $classroom->refresh()->activeStudentCount());

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'name' => 'Siswa Pengganti',
            'email' => $email,
            'password' => 'password123',
            'gender' => 'male',
            'nis' => 'N-100',
            'classroom_id' => $classroom->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Student::where('nis', 'N-100')->count());
    }

    public function test_soft_deleted_user_cannot_log_in(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->classroom();
        $student = $this->enrollStudent($classroom, 'N-200');
        $credentials = ['email' => $student->user->email, 'password' => 'password'];

        $this->assertTrue(Auth::validate($credentials));
        $this->post('/api/v1/login', $credentials)->assertOk();

        $this->actingAs($admin)->delete(route('admin.students.destroy', $student->user_id));

        $this->assertFalse(Auth::validate($credentials));
        $this->post('/api/v1/login', $credentials)->assertStatus(401);
    }

    public function test_two_active_students_cannot_share_the_same_nis(): void
    {
        $classroom = $this->classroom();
        $this->enrollStudent($classroom, 'N-300');

        $this->expectException(QueryException::class);
        $this->enrollStudent($classroom, 'N-300');
    }

    public function test_permanent_deletion_is_blocked_while_history_exists(): void
    {
        $classroom = $this->classroom();
        $student = $this->enrollStudent($classroom, 'N-400');
        $this->recordAttendance($student);

        $this->expectException(QueryException::class);
        $student->user->forceDelete();
    }

    public function test_deleting_a_student_revokes_the_mobile_token(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->classroom();
        $student = $this->enrollStudent($classroom, 'N-500');
        $student->user->createToken('mobile-app');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->actingAs($admin)->delete(route('admin.students.destroy', $student->user_id));

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
