<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function createClassroom(string $name = 'X-MIPA 1', string $level = '10', string $year = '2026/2027'): Classroom
    {
        return Classroom::create([
            'name' => $name,
            'level' => $level,
            'major' => 'MIPA',
            'section' => '1',
            'academic_year' => $year,
            'is_active' => true,
        ]);
    }

    private function createStudentInClassroom(Classroom $classroom, string $nis = '1001'): Student
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        return Student::create([
            'user_id' => $user->id,
            'classroom_id' => $classroom->id,
            'nis' => $nis,
            'nisn' => '00'.$nis,
            'grade' => $classroom->name,
            'academic_status' => 'active',
        ]);
    }

    public function test_admin_can_access_promotion_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.classrooms.promotion'));

        $response->assertOk()
            ->assertViewIs('admin.classrooms.promotion')
            ->assertSee('Kenaikan Kelas')
            ->assertSee('Kelulusan Massal');
    }

    public function test_non_admin_cannot_access_promotion_page(): void
    {
        $studentUser = User::factory()->create();
        $studentUser->assignRole('siswa');

        $response = $this->actingAs($studentUser)->get(route('admin.classrooms.promotion'));

        $response->assertForbidden();
    }

    public function test_admin_can_get_students_by_classroom_via_ajax(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->createClassroom('X-MIPA 1', '10');
        $student1 = $this->createStudentInClassroom($classroom, '1001');
        $student2 = $this->createStudentInClassroom($classroom, '1002');

        $response = $this->actingAs($admin)
            ->get(route('admin.classrooms.students', $classroom->id));

        $response->assertOk();

        // Endpoint mengirim HTML baris tabel dari partial yang sama dengan render server-side.
        $this->assertSame(2, substr_count($response->getContent(), 'student-checkbox'));
        $this->assertStringContainsString($student1->user->name, $response->getContent());
        $this->assertStringContainsString($student2->user->name, $response->getContent());
    }

    public function test_admin_can_promote_selected_students_to_target_classroom(): void
    {
        $admin = $this->createAdmin();
        $sourceClass = $this->createClassroom('X-MIPA 1', '10', '2025/2026');
        $targetClass = $this->createClassroom('XI-MIPA 1', '11', '2026/2027');

        $studentA = $this->createStudentInClassroom($sourceClass, '1001');
        $studentB = $this->createStudentInClassroom($sourceClass, '1002');
        $studentC = $this->createStudentInClassroom($sourceClass, '1003'); // tidak dipromosikan (tinggal kelas)

        $payload = [
            'action' => 'promote',
            'source_classroom_id' => $sourceClass->id,
            'target_classroom_id' => $targetClass->id,
            'student_ids' => [$studentA->id, $studentB->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.promotion.process'), $payload);

        $response->assertRedirect(route('admin.classrooms.promotion'))
            ->assertSessionHas('success');

        // Verifikasi studentA dan studentB telah pindah
        $this->assertDatabaseHas('students', [
            'id' => $studentA->id,
            'classroom_id' => $targetClass->id,
            'grade' => $targetClass->name,
            'academic_status' => 'active',
        ]);

        $this->assertDatabaseHas('students', [
            'id' => $studentB->id,
            'classroom_id' => $targetClass->id,
            'grade' => $targetClass->name,
            'academic_status' => 'active',
        ]);

        // Verifikasi studentC tetap di kelas asal
        $this->assertDatabaseHas('students', [
            'id' => $studentC->id,
            'classroom_id' => $sourceClass->id,
            'academic_status' => 'active',
        ]);

        // Verifikasi notifikasi dibuat untuk siswa yang dipromosikan
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $studentA->user_id,
            'title' => 'Kenaikan Kelas Baru',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $studentB->user_id,
            'title' => 'Kenaikan Kelas Baru',
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $studentC->user_id,
            'title' => 'Kenaikan Kelas Baru',
        ]);
    }

    public function test_admin_can_graduate_students(): void
    {
        $admin = $this->createAdmin();
        $class12 = $this->createClassroom('XII-MIPA 1', '12', '2025/2026');

        $student1 = $this->createStudentInClassroom($class12, '1201');
        $student2 = $this->createStudentInClassroom($class12, '1202');

        $payload = [
            'action' => 'graduate',
            'source_classroom_id' => $class12->id,
            'student_ids' => [$student1->id, $student2->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.promotion.process'), $payload);

        $response->assertRedirect(route('admin.classrooms.promotion'))
            ->assertSessionHas('success');

        // Verifikasi kedua siswa menjadi graduated dan classroom_id null
        $this->assertDatabaseHas('students', [
            'id' => $student1->id,
            'classroom_id' => null,
            'academic_status' => 'graduated',
        ]);

        $this->assertDatabaseHas('students', [
            'id' => $student2->id,
            'classroom_id' => null,
            'academic_status' => 'graduated',
        ]);

        // Verifikasi notifikasi kelulusan
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $student1->user_id,
            'title' => 'Kelulusan Siswa',
        ]);
    }

    public function test_validation_fails_if_source_and_target_are_identical(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->createClassroom('X-MIPA 1', '10');
        $student = $this->createStudentInClassroom($classroom, '1001');

        $payload = [
            'action' => 'promote',
            'source_classroom_id' => $classroom->id,
            'target_classroom_id' => $classroom->id,
            'student_ids' => [$student->id],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.promotion.process'), $payload);

        $response->assertSessionHasErrors(['target_classroom_id']);
    }
}
