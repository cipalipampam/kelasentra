<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomFilterAndDuplicateTest extends TestCase
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

    public function test_default_index_only_displays_classrooms_from_operable_academic_years(): void
    {
        $admin = $this->createAdmin();

        $closedYear = AcademicYear::create([
            'name' => '2023/2024',
            'status' => AcademicYear::STATUS_CLOSED,
        ]);

        $currentYear = AcademicYear::create([
            'name' => '2024/2025',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);

        $closedClass = Classroom::create([
            'name' => 'X-MIPA 1 Past',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $closedYear->id,
            'is_active' => true,
        ]);

        $currentClass = Classroom::create([
            'name' => 'X-MIPA 1 Current',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $currentYear->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.classrooms.index'));

        $response->assertOk();
        $classrooms = $response->viewData('classrooms');
        $this->assertTrue($classrooms->contains('id', $currentClass->id));
        $this->assertFalse($classrooms->contains('id', $closedClass->id));
        $response->assertSee('X-MIPA 1 Current');
        $response->assertDontSee('Menampilkan semua rombel termasuk riwayat');
    }

    public function test_index_with_show_history_displays_all_classrooms_including_closed_years(): void
    {
        $admin = $this->createAdmin();

        $closedYear = AcademicYear::create([
            'name' => '2023/2024',
            'status' => AcademicYear::STATUS_CLOSED,
        ]);

        $currentYear = AcademicYear::create([
            'name' => '2024/2025',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);

        Classroom::create([
            'name' => 'X-MIPA 1 Past',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $closedYear->id,
            'is_active' => true,
        ]);

        Classroom::create([
            'name' => 'X-MIPA 1 Current',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $currentYear->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.classrooms.index', ['show_history' => '1']));

        $response->assertOk();
        $response->assertSee('X-MIPA 1 Current');
        $response->assertSee('X-MIPA 1 Past');
        $response->assertSee('Menampilkan semua rombel termasuk riwayat');
    }

    public function test_admin_can_batch_duplicate_classrooms_to_upcoming_year(): void
    {
        $admin = $this->createAdmin();

        $sourceYear = AcademicYear::create([
            'name' => '2024/2025',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);

        $targetYear = AcademicYear::create([
            'name' => '2025/2026',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);

        $class1 = Classroom::create([
            'name' => 'X-1',
            'level' => '10',
            'major' => null,
            'section' => '1',
            'academic_year_id' => $sourceYear->id,
            'is_active' => true,
        ]);

        $class2 = Classroom::create([
            'name' => 'XI-MIPA 1',
            'level' => '11',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $sourceYear->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.classrooms.duplicate'), [
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $targetYear->id,
            'classroom_ids' => [$class1->id, $class2->id],
        ]);

        $response->assertRedirect(route('admin.classrooms.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('classrooms', [
            'name' => 'X-1',
            'level' => '10',
            'major' => null,
            'section' => '1',
            'academic_year_id' => $targetYear->id,
            'homeroom_teacher_id' => null,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('classrooms', [
            'name' => 'XI-MIPA 1',
            'level' => '11',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $targetYear->id,
            'homeroom_teacher_id' => null,
            'is_active' => true,
        ]);
    }

    public function test_duplicate_skips_classrooms_whose_name_already_exists_in_target_year(): void
    {
        $admin = $this->createAdmin();

        $sourceYear = AcademicYear::create([
            'name' => '2024/2025',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);

        $targetYear = AcademicYear::create([
            'name' => '2025/2026',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);

        $class1 = Classroom::create([
            'name' => 'X-1',
            'level' => '10',
            'major' => null,
            'section' => '1',
            'academic_year_id' => $sourceYear->id,
            'is_active' => true,
        ]);

        // Pre-create X-1 in target year
        Classroom::create([
            'name' => 'X-1',
            'level' => '10',
            'major' => null,
            'section' => '1',
            'academic_year_id' => $targetYear->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.classrooms.duplicate'), [
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $targetYear->id,
            'classroom_ids' => [$class1->id],
        ]);

        $response->assertRedirect(route('admin.classrooms.index'));
        $response->assertSessionHas('success');

        // Total X-1 should be 2 (1 in source, 1 in target), not duplicated again
        $this->assertSame(2, Classroom::where('name', 'X-1')->count());
    }

    public function test_duplicate_fails_if_target_year_is_closed(): void
    {
        $admin = $this->createAdmin();

        $sourceYear = AcademicYear::create([
            'name' => '2024/2025',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);

        $closedTargetYear = AcademicYear::create([
            'name' => '2023/2024',
            'status' => AcademicYear::STATUS_CLOSED,
        ]);

        $class1 = Classroom::create([
            'name' => 'X-1',
            'level' => '10',
            'major' => null,
            'section' => '1',
            'academic_year_id' => $sourceYear->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.classrooms.duplicate'), [
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $closedTargetYear->id,
            'classroom_ids' => [$class1->id],
        ]);

        $this->assertSame(0, Classroom::where('academic_year_id', $closedTargetYear->id)->count());
    }

    public function test_duplicate_validation_requires_different_source_and_target_years(): void
    {
        $admin = $this->createAdmin();

        $sourceYear = AcademicYear::create([
            'name' => '2024/2025',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);

        $class1 = Classroom::create([
            'name' => 'X-1',
            'level' => '10',
            'major' => null,
            'section' => '1',
            'academic_year_id' => $sourceYear->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.classrooms.duplicate'), [
            'source_academic_year_id' => $sourceYear->id,
            'target_academic_year_id' => $sourceYear->id,
            'classroom_ids' => [$class1->id],
        ]);

        $response->assertSessionHasErrors('target_academic_year_id');
    }
}
