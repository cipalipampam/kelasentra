<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearWebTest extends TestCase
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

    private function createAcademicYear(string $name, string $status = AcademicYear::STATUS_UPCOMING): AcademicYear
    {
        return AcademicYear::create(['name' => $name, 'status' => $status]);
    }

    public function test_admin_can_view_academic_year_index(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027', AcademicYear::STATUS_CURRENT);

        $response = $this->actingAs($admin)->get(route('admin.academic-years.index'));

        $response->assertOk()
            ->assertViewIs('admin.academic-years.index')
            ->assertSee('2026/2027');
    }

    public function test_non_admin_cannot_access_academic_years(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('guru');

        $this->actingAs($teacher)->get(route('admin.academic-years.index'))->assertForbidden();
    }

    public function test_admin_can_store_academic_year(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);

        $response->assertRedirect(route('admin.academic-years.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('academic_years', [
            'name' => '2026/2027',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);
    }

    public function test_academic_year_name_must_follow_yyyy_slash_yyyy_format(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2026-2027',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_academic_year_must_be_consecutive(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2026/2029',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_academic_year_name_must_be_unique(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');

        $response = $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2026/2027',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_only_one_academic_year_may_be_running(): void
    {
        $admin = $this->createAdmin();
        $previous = $this->createAcademicYear('2026/2027', AcademicYear::STATUS_CURRENT);

        $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2027/2028',
            'status' => AcademicYear::STATUS_CURRENT,
        ])->assertSessionHasNoErrors();

        $this->assertSame(AcademicYear::STATUS_CLOSED, $previous->fresh()->status);
        $this->assertSame(1, AcademicYear::query()->where('status', AcademicYear::STATUS_CURRENT)->count());
    }

    public function test_admin_can_activate_an_academic_year(): void
    {
        $admin = $this->createAdmin();
        $current = $this->createAcademicYear('2026/2027', AcademicYear::STATUS_CURRENT);
        $upcoming = $this->createAcademicYear('2027/2028', AcademicYear::STATUS_UPCOMING);

        $response = $this->actingAs($admin)->post(route('admin.academic-years.activate', $upcoming->id));

        $response->assertRedirect(route('admin.academic-years.index'))->assertSessionHas('success');

        $this->assertSame(AcademicYear::STATUS_CURRENT, $upcoming->fresh()->status);
        $this->assertSame(AcademicYear::STATUS_CLOSED, $current->fresh()->status);
    }

    public function test_academic_year_in_use_by_classroom_cannot_be_deleted(): void
    {
        $admin = $this->createAdmin();
        $academicYear = $this->createAcademicYear('2026/2027', AcademicYear::STATUS_CURRENT);

        Classroom::create([
            'name' => 'X MIPA 1',
            'level' => '10',
            'section' => '1',
            'academic_year_id' => $academicYear->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.academic-years.destroy', $academicYear->id));

        $response->assertRedirect(route('admin.academic-years.index'))->assertSessionHas('error');
        $this->assertDatabaseHas('academic_years', ['id' => $academicYear->id]);
    }

    public function test_unused_academic_year_can_be_deleted(): void
    {
        $admin = $this->createAdmin();
        $academicYear = $this->createAcademicYear('2027/2028');

        $response = $this->actingAs($admin)->delete(route('admin.academic-years.destroy', $academicYear->id));

        $response->assertRedirect(route('admin.academic-years.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('academic_years', ['id' => $academicYear->id]);
    }

    public function test_admin_can_update_academic_year(): void
    {
        $admin = $this->createAdmin();
        $academicYear = $this->createAcademicYear('2026/2027', AcademicYear::STATUS_UPCOMING);

        $response = $this->actingAs($admin)->put(route('admin.academic-years.update', $academicYear->id), [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);

        $response->assertRedirect(route('admin.academic-years.index'))->assertSessionHas('success');

        $academicYear->refresh();
        $this->assertSame(AcademicYear::STATUS_CURRENT, $academicYear->status);
        $this->assertSame('2026-07-01', $academicYear->start_date->format('Y-m-d'));
    }

    public function test_end_date_must_not_precede_start_date(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2026-06-01',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    public function test_only_one_upcoming_academic_year_may_exist(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027', AcademicYear::STATUS_UPCOMING);

        $response = $this->actingAs($admin)->post(route('admin.academic-years.store'), [
            'name' => '2027/2028',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_start_year_is_derived_from_name(): void
    {
        $academicYear = $this->createAcademicYear('2026/2027');

        $this->assertSame(2026, $academicYear->start_year);
    }

    public function test_database_rejects_a_second_running_academic_year(): void
    {
        $this->createAcademicYear('2026/2027', AcademicYear::STATUS_CURRENT);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AcademicYear::create(['name' => '2027/2028', 'status' => AcademicYear::STATUS_CURRENT]);
    }

    public function test_database_rejects_a_second_upcoming_academic_year(): void
    {
        $this->createAcademicYear('2026/2027', AcademicYear::STATUS_UPCOMING);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AcademicYear::create(['name' => '2027/2028', 'status' => AcademicYear::STATUS_UPCOMING]);
    }
}
