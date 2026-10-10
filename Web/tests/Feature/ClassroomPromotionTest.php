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
            'academic_year_id' => $this->yearId($year),
            'is_active' => true,
        ]);
    }

    private function createStudentInClassroom(Classroom $classroom, string $nis = '1001'): Student
    {
        return $this->enrollStudent($classroom, $nis);
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

        // Verifikasi studentA dan studentB telah pindah lewat enrollment baru
        $this->assertSame($targetClass->id, $studentA->fresh()->currentEnrollment->classroom_id);
        $this->assertSame($targetClass->id, $studentB->fresh()->currentEnrollment->classroom_id);
        $this->assertSame('active', $studentA->fresh()->academic_status);

        // Verifikasi studentC tetap di kelas asal
        $this->assertSame($sourceClass->id, $studentC->fresh()->currentEnrollment->classroom_id);

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

        // Verifikasi kedua siswa lulus: enrollment terakhir ditutup
        foreach ([$student1, $student2] as $student) {
            $fresh = $student->fresh();
            $this->assertSame('graduated', $fresh->academic_status);
            $this->assertNull($fresh->currentEnrollment);
            $this->assertNotNull($fresh->graduated_at);
            $this->assertNotNull($fresh->enrollments()->first()->ended_at);
        }

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
