<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use App\Services\Shared\Attendance\AttendanceContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_student_only_receives_active_schedule_for_own_classroom(): void
    {
        $classroom = $this->classroom();
        $student = User::factory()->create();
        $this->enrollStudent($classroom, 'S-001', 'active', $student);

        $ownSchedule = $this->schedule($classroom, 1);
        $this->schedule($this->classroom(), 1);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/v1/schedules?day_of_week=1');

        $response->assertOk()
            ->assertJsonPath('data.role', 'student')
            ->assertJsonCount(1, 'data.schedules')
            ->assertJsonPath('data.schedules.0.id', $ownSchedule->id);
    }

    public function test_assigned_teacher_can_prefill_and_upsert_class_attendance(): void
    {
        $classroom = $this->classroom();
        $teacher = User::factory()->create();
        $schedule = $this->schedule($classroom, 1, $teacher);
        $studentUser = User::factory()->create();
        $student = $this->enrollStudent($classroom, 'S-002', 'active', $studentUser);
        Attendance::create(array_merge(AttendanceContext::forUser($studentUser), [
            'user_id' => $studentUser->id,
            'recorded_at' => '2026-09-21 07:00:00',
            'status' => 'permission',
            'is_approved' => true,
        ]));

        Sanctum::actingAs($teacher);

        $this->getJson("/api/v1/schedules/{$schedule->id}/class-attendance?attendance_date=2026-09-21")
            ->assertOk()
            ->assertJsonPath('data.students.0.status', 'permission')
            ->assertJsonPath('data.students.0.is_prefilled_from_daily_attendance', true);

        $payload = [
            'attendance_date' => '2026-09-21',
            'attendances' => [[
                'student_id' => $student->id,
                'status' => 'late',
                'notes' => 'Datang setelah bel berbunyi.',
            ]],
        ];

        $this->postJson("/api/v1/schedules/{$schedule->id}/class-attendance", $payload)
            ->assertOk()
            ->assertJsonPath('data.recorded_students', 1);

        $payload['attendances'][0]['status'] = 'absent';
        $this->postJson("/api/v1/schedules/{$schedule->id}/class-attendance", $payload)
            ->assertOk()
            ->assertJsonPath('data.created_notifications', 1);
        $this->postJson("/api/v1/schedules/{$schedule->id}/class-attendance", $payload)
            ->assertOk()
            ->assertJsonPath('data.created_notifications', 0);

        $this->assertDatabaseCount('schedule_attendances', 1);
        $this->assertDatabaseHas('schedule_attendances', [
            'schedule_id' => $schedule->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-09-21',
            'status' => 'absent',
        ]);
        $this->assertDatabaseCount('app_notifications', 1);
    }

    public function test_notification_endpoints_are_scoped_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Jadwal berubah',
            'body' => 'Ruang pelajaran diperbarui.',
            'type' => 'schedule',
        ]);
        $otherNotification = AppNotification::create([
            'user_id' => $otherUser->id,
            'title' => 'Pribadi',
            'body' => 'Notifikasi pengguna lain.',
            'type' => 'system',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications?filter=unread')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $notification->id);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);
        $this->patchJson("/api/v1/notifications/{$otherNotification->id}/read")
            ->assertNotFound();

        $this->patchJson('/api/v1/notifications/non-numeric-id/read')
            ->assertNotFound();
    }

    public function test_teacher_receives_assigned_schedule(): void
    {
        $this->seed(RoleSeeder::class);
        $teacher = User::factory()->create();
        $teacher->assignRole('guru');

        $classroom = $this->classroom();
        $schedule = $this->schedule($classroom, 2, $teacher);

        Sanctum::actingAs($teacher);

        $response = $this->getJson('/api/v1/schedules?day_of_week=2');

        $response->assertOk()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonCount(1, 'data.schedules')
            ->assertJsonPath('data.schedules.0.id', $schedule->id);
    }

    public function test_inactive_schedule_cannot_record_attendance(): void
    {
        $classroom = $this->classroom();
        $teacher = User::factory()->create();
        $schedule = $this->schedule($classroom, 1, $teacher);
        $schedule->update(['is_active' => false]);

        $student = $this->enrollStudent($classroom, 'S-999');

        Sanctum::actingAs($teacher);

        $payload = [
            'attendances' => [[
                'student_id' => $student->id,
                'status' => 'present',
            ]],
        ];

        $this->postJson("/api/v1/schedules/{$schedule->id}/class-attendance", $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_ineligible_student_cannot_be_recorded(): void
    {
        $classroom = $this->classroom();
        $otherClassroom = $this->classroom();
        $teacher = User::factory()->create();
        $schedule = $this->schedule($classroom, 1, $teacher);

        $foreignStudent = $this->enrollStudent($otherClassroom, 'S-FOREIGN');

        Sanctum::actingAs($teacher);

        $payload = [
            'attendances' => [[
                'student_id' => $foreignStudent->id,
                'status' => 'present',
            ]],
        ];

        $this->postJson("/api/v1/schedules/{$schedule->id}/class-attendance", $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_student_profile_payload_keeps_the_grade_field(): void
    {
        $classroom = $this->classroom();
        $studentUser = User::factory()->create();
        $studentUser->assignRole('siswa');
        $this->enrollStudent($classroom, 'S-777', 'active', $studentUser);

        Sanctum::actingAs($studentUser);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.role_name', 'siswa')
            ->assertJsonPath('data.student.grade', $classroom->name);
    }

    public function test_dashboard_includes_unread_notifications_count(): void
    {
        $user = User::factory()->create();
        AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Pengumuman Baru',
            'body' => 'Ujian akhir semester.',
            'type' => 'announcement',
            'is_read' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.unread_notifications_count', 1);
    }

    private function classroom(): Classroom
    {
        return Classroom::create([
            'name' => 'X-MIPA '.fake()->unique()->numberBetween(1, 99),
            'level' => '10',
            'major' => 'MIPA',
            'section' => (string) fake()->unique()->numberBetween(1, 99),
            'academic_year_id' => $this->yearId('2026/2027'),
        ]);
    }

    private function schedule(Classroom $classroom, int $dayOfWeek, ?User $teacher = null): Schedule
    {
        return $this->createSchedule([
            'classroom_id' => $classroom->id,
            'subject_id' => Subject::create([
                'code' => 'MAP-'.fake()->unique()->numberBetween(1, 9999),
                'name' => 'Mata Pelajaran',
            ])->id,
            'teacher_id' => ($teacher ?? User::factory()->create())->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '07:00:00',
            'end_time' => '08:00:00',
        ]);
    }
}
