<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomRulesTest extends TestCase
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

    private function createAcademicYear(string $name, string $status = AcademicYear::STATUS_CURRENT): AcademicYear
    {
        return AcademicYear::create(['name' => $name, 'status' => $status]);
    }

    private function createTeacher(string $status = Employee::STATUS_ACTIVE): User
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('guru');
        $teacher->employee()->create(['employment_status' => $status]);

        return $teacher;
    }

    private function createClassroom(array $attributes = []): Classroom
    {
        return Classroom::create(array_merge([
            'name' => 'X MIPA 1',
            'level' => '10',
            'major' => null,
            'section' => '1',
            'academic_year_id' => $this->yearId('2026/2027'),
            'is_active' => true,
        ], $attributes));
    }

    private function createStudentIn(Classroom $classroom, int $index): Student
    {
        return $this->enrollStudent($classroom, 'S'.$classroom->id.'-'.$index);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'X MIPA 1',
            'level' => '10',
            'section' => '1',
            'academic_year_id' => $this->yearId('2026/2027'),
            'is_active' => '1',
        ], $overrides);
    }

    // ── Rule 3: nama rombel unik per tahun ajaran ────────────────────────────

    public function test_classroom_name_must_be_unique_within_the_same_academic_year(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $this->createClassroom(['name' => 'X MIPA 1']);

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['name' => 'X MIPA 1', 'section' => '2']));

        $response->assertSessionHasErrors('name');
        $this->assertSame(1, Classroom::query()->where('name', 'X MIPA 1')->count());
    }

    public function test_same_classroom_name_is_allowed_in_a_different_academic_year(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $this->createAcademicYear('2027/2028', AcademicYear::STATUS_UPCOMING);
        $this->createClassroom(['name' => 'X MIPA 1', 'academic_year_id' => $this->yearId('2026/2027')]);

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['name' => 'X MIPA 1', 'academic_year_id' => $this->yearId('2027/2028')]));

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.classrooms.index'));
        $this->assertSame(2, Classroom::query()->where('name', 'X MIPA 1')->count());
    }

    // ── Nomor sesi unik per tingkat + jurusan + tahun ajaran ─────────────────

    public function test_session_number_must_be_unique_within_the_same_level_and_year(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $this->createClassroom(['name' => 'X MIPA 1', 'section' => '1']);

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['name' => 'X MIPA 1A', 'section' => '1']));

        $response->assertSessionHasErrors('section');
        $this->assertSame(1, Classroom::query()->where('section', '1')->count());
    }

    public function test_same_session_number_is_allowed_for_a_different_major(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'major' => 'MIPA', 'section' => '1']);

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload([
                'name' => 'X IPS 1',
                'major' => 'IPS',
                'section' => '1',
            ]));

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.classrooms.index'));
        $this->assertSame(2, Classroom::query()->where('section', '1')->count());
    }

    public function test_same_session_number_is_allowed_in_a_different_academic_year(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $this->createAcademicYear('2027/2028', AcademicYear::STATUS_UPCOMING);
        $this->createClassroom(['name' => 'X MIPA 1', 'academic_year_id' => $this->yearId('2026/2027')]);

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['academic_year_id' => $this->yearId('2027/2028')]));

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.classrooms.index'));
        $this->assertSame(2, Classroom::query()->where('section', '1')->count());
    }

    public function test_classroom_can_keep_its_own_session_number_when_updated(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $classroom = $this->createClassroom(['name' => 'X MIPA 1', 'section' => '1']);

        $response = $this->actingAs($admin)
            ->put(route('admin.classrooms.update', $classroom->id), $this->validPayload([
                'name' => 'X MIPA 1',
                'section' => '1',
            ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame('1', $classroom->fresh()->section);
    }

    // ── Rombel nonaktif tidak menyimpan wali kelas ──────────────────────────

    public function test_deactivating_a_classroom_releases_its_homeroom_teacher(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $teacher = $this->createTeacher();
        $classroom = $this->createClassroom(['name' => 'X MIPA 1', 'homeroom_teacher_id' => $teacher->id]);

        $response = $this->actingAs($admin)
            ->put(route('admin.classrooms.update', $classroom->id), $this->validPayload([
                'name' => 'X MIPA 1',
                'homeroom_teacher_id' => $teacher->id,
                'is_active' => '0',
            ]));

        $response->assertSessionHasNoErrors();

        $classroom->refresh();
        $this->assertFalse((bool) $classroom->is_active);
        $this->assertNull($classroom->homeroom_teacher_id);
    }

    public function test_teacher_released_by_deactivation_can_take_another_classroom(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $teacher = $this->createTeacher();
        $deactivated = $this->createClassroom(['name' => 'X MIPA 1', 'homeroom_teacher_id' => $teacher->id]);
        $other = $this->createClassroom(['name' => 'X MIPA 2', 'section' => '2']);

        $this->actingAs($admin)->put(route('admin.classrooms.update', $deactivated->id), $this->validPayload([
            'name' => 'X MIPA 1',
            'is_active' => '0',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put(route('admin.classrooms.update', $other->id), $this->validPayload([
                'name' => 'X MIPA 2',
                'section' => '2',
                'homeroom_teacher_id' => $teacher->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($teacher->id, $other->fresh()->homeroom_teacher_id);
    }

    public function test_teacher_released_by_deletion_can_take_another_classroom(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $teacher = $this->createTeacher();
        $deleted = $this->createClassroom(['name' => 'X MIPA 1', 'homeroom_teacher_id' => $teacher->id]);
        $other = $this->createClassroom(['name' => 'X MIPA 2', 'section' => '2']);

        $this->actingAs($admin)
            ->delete(route('admin.classrooms.destroy', $deleted->id))
            ->assertSessionHas('success');

        // Rombel terhapus tidak boleh menyandera slot wali kelas: unique
        // (homeroom_teacher_id, academic_year_id) tetap berlaku untuk baris nonaktif.
        $this->actingAs($admin)
            ->put(route('admin.classrooms.update', $other->id), $this->validPayload([
                'name' => 'X MIPA 2',
                'section' => '2',
                'homeroom_teacher_id' => $teacher->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($teacher->id, $other->fresh()->homeroom_teacher_id);
        $this->assertSoftDeleted('classrooms', ['id' => $deleted->id]);
    }

    public function test_inactive_classroom_does_not_keep_a_homeroom_teacher(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $teacher = $this->createTeacher();

        $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload([
                'name' => 'X MIPA 2',
                'section' => '2',
                'homeroom_teacher_id' => $teacher->id,
                'is_active' => '0',
            ]))
            ->assertSessionHasNoErrors();

        $classroom = Classroom::query()->where('name', 'X MIPA 2')->firstOrFail();
        $this->assertFalse((bool) $classroom->is_active);
        $this->assertNull($classroom->homeroom_teacher_id);
    }

    // ── Rule 2: jurusan enum, wajib untuk XI/XII ─────────────────────────────

    public function test_major_is_required_for_level_11_and_12(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');

        foreach (['11', '12'] as $level) {
            $response = $this->actingAs($admin)
                ->post(route('admin.classrooms.store'), $this->validPayload([
                    'name' => 'Kelas '.$level,
                    'level' => $level,
                    'major' => null,
                ]));

            $response->assertSessionHasErrors('major');
        }
    }

    public function test_major_is_optional_for_level_10(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['level' => '10', 'major' => null]));

        $response->assertSessionHasNoErrors();
        $this->assertNull(Classroom::query()->where('name', 'X MIPA 1')->value('major'));
    }

    public function test_major_must_be_one_of_the_allowed_values(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['level' => '11', 'major' => 'IPA']));

        $response->assertSessionHasErrors('major');
    }

    public function test_major_accepts_every_configured_value(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');

        foreach (config('classroom.majors') as $index => $major) {
            $response = $this->actingAs($admin)
                ->post(route('admin.classrooms.store'), $this->validPayload([
                    'name' => 'XI '.$major,
                    'level' => '11',
                    'section' => (string) ($index + 1),
                    'major' => $major,
                ]));

            $response->assertSessionHasNoErrors();
        }

        $this->assertSame(3, Classroom::query()->count());
    }

    // ── Rule 6: tingkat valid ────────────────────────────────────────────────

    public function test_level_must_be_one_of_x_xi_xii(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['level' => '9']));

        $response->assertSessionHasErrors('level');
    }

    // ── Rule 5: tahun ajaran valid ───────────────────────────────────────────

    public function test_academic_year_must_be_operable(): void
    {
        $admin = $this->createAdmin();
        $closed = $this->createAcademicYear('2025/2026', AcademicYear::STATUS_CLOSED);

        $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['academic_year_id' => $closed->id]))
            ->assertSessionHasErrors('academic_year_id');

        $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload([
                'academic_year_id' => 999999,
                'name' => 'Kelas Baru',
            ]))
            ->assertSessionHasErrors('academic_year_id');
    }

    public function test_upcoming_academic_year_is_accepted(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2027/2028', AcademicYear::STATUS_UPCOMING);

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['academic_year_id' => $this->yearId('2027/2028')]));

        $response->assertSessionHasNoErrors();
    }

    // ── Rule 4: wali kelas harus guru aktif ──────────────────────────────────

    public function test_teacher_on_leave_cannot_be_assigned_as_homeroom_teacher(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $teacher = $this->createTeacher(Employee::STATUS_LEAVE);

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['homeroom_teacher_id' => $teacher->id]));

        $response->assertSessionHasErrors('homeroom_teacher_id');
    }

    public function test_retired_and_resigned_teachers_cannot_be_assigned(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');

        foreach ([Employee::STATUS_RETIRED, Employee::STATUS_RESIGNED] as $index => $status) {
            $teacher = $this->createTeacher($status);

            $this->actingAs($admin)
                ->post(route('admin.classrooms.store'), $this->validPayload([
                    'name' => 'Kelas '.$index,
                    'homeroom_teacher_id' => $teacher->id,
                ]))
                ->assertSessionHasErrors('homeroom_teacher_id');
        }
    }

    public function test_active_teacher_can_be_assigned_as_homeroom_teacher(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $teacher = $this->createTeacher();

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['homeroom_teacher_id' => $teacher->id]));

        $response->assertSessionHasNoErrors();
        $this->assertSame($teacher->id, Classroom::query()->where('name', 'X MIPA 1')->value('homeroom_teacher_id'));
    }

    public function test_non_teacher_cannot_be_assigned_as_homeroom_teacher(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload(['homeroom_teacher_id' => $staff->id]));

        $response->assertSessionHasErrors('homeroom_teacher_id');
    }

    // ── Rule 7: satu guru satu rombel per tahun ajaran ───────────────────────

    public function test_teacher_cannot_be_homeroom_for_two_classrooms_in_the_same_academic_year(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $teacher = $this->createTeacher();
        $this->createClassroom(['name' => 'X MIPA 1', 'homeroom_teacher_id' => $teacher->id]);

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload([
                'name' => 'X MIPA 2',
                'section' => '2',
                'homeroom_teacher_id' => $teacher->id,
            ]));

        $response->assertSessionHasErrors('homeroom_teacher_id');
    }

    public function test_teacher_may_become_homeroom_in_a_different_academic_year(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $this->createAcademicYear('2027/2028', AcademicYear::STATUS_UPCOMING);
        $teacher = $this->createTeacher();
        $this->createClassroom(['name' => 'X MIPA 1', 'homeroom_teacher_id' => $teacher->id]);

        $response = $this->actingAs($admin)
            ->post(route('admin.classrooms.store'), $this->validPayload([
                'name' => 'X MIPA 1',
                'academic_year_id' => $this->yearId('2027/2028'),
                'homeroom_teacher_id' => $teacher->id,
            ]));

        $response->assertSessionHasNoErrors();
    }

    public function test_classroom_can_keep_its_own_homeroom_teacher_when_updated(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $teacher = $this->createTeacher();
        $classroom = $this->createClassroom(['name' => 'X MIPA 1', 'homeroom_teacher_id' => $teacher->id]);

        $response = $this->actingAs($admin)
            ->put(route('admin.classrooms.update', $classroom->id), $this->validPayload([
                'name' => 'X MIPA 1',
                'section' => '2',
                'homeroom_teacher_id' => $teacher->id,
            ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame('2', $classroom->fresh()->section);
    }

    // ── Rule 1: kapasitas rombel maksimal 30 siswa ───────────────────────────

    public function test_promotion_is_rejected_when_target_classroom_exceeds_capacity(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);

        // Target sudah berisi kapasitas maksimal - 1 siswa.
        for ($i = 0; $i < Classroom::studentCapacity() - 1; $i++) {
            $this->createStudentIn($target, $i);
        }

        $movingA = $this->createStudentIn($source, 1);
        $movingB = $this->createStudentIn($source, 2);

        $response = $this->actingAs($admin)->post(route('admin.classrooms.promotion.process'), [
            'action' => 'promote',
            'source_classroom_id' => $source->id,
            'target_classroom_id' => $target->id,
            'student_ids' => [$movingA->id, $movingB->id],
        ]);

        $response->assertSessionHasErrors('target_classroom_id');
        $this->assertSame($source->id, $movingA->fresh()->classroom_id);
    }

    public function test_promotion_is_allowed_exactly_up_to_capacity(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);

        for ($i = 0; $i < Classroom::studentCapacity() - 2; $i++) {
            $this->createStudentIn($target, $i);
        }

        $movingA = $this->createStudentIn($source, 1);
        $movingB = $this->createStudentIn($source, 2);

        $response = $this->actingAs($admin)->post(route('admin.classrooms.promotion.process'), [
            'action' => 'promote',
            'source_classroom_id' => $source->id,
            'target_classroom_id' => $target->id,
            'student_ids' => [$movingA->id, $movingB->id],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame($target->id, $movingA->fresh()->classroom_id);
        $this->assertSame(Classroom::studentCapacity(), $target->activeStudentCount());
    }

    // ── Halaman index ───────────────────────────────────────────────────────

    public function test_admin_can_view_classroom_index_with_rules_applied(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');
        $activeTeacher = $this->createTeacher();
        $retiredTeacher = $this->createTeacher(Employee::STATUS_RETIRED);

        $classroom = $this->createClassroom([
            'name' => 'X MIPA 1',
            'level' => '11',
            'major' => 'MIPA',
            'homeroom_teacher_id' => $activeTeacher->id,
        ]);
        $this->createStudentIn($classroom, 1);

        $response = $this->actingAs($admin)->get(route('admin.classrooms.index'));

        $response->assertOk()
            ->assertViewIs('admin.classrooms.index')
            ->assertSee($activeTeacher->name)
            ->assertDontSee($retiredTeacher->name)
            ->assertSee('X MIPA 1')
            ->assertSee('1/'.Classroom::studentCapacity())
            ->assertSee('data-assigned-years', false);
    }

    public function test_promotion_page_renders_classroom_capacity_information(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027');

        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10']);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA']);
        $this->createStudentIn($target, 1);

        $response = $this->actingAs($admin)->get(route('admin.classrooms.promotion'));

        $response->assertOk()
            ->assertViewIs('admin.classrooms.promotion')
            ->assertSee('XI MIPA 1')
            ->assertSee('data-capacity="'.Classroom::studentCapacity().'"', false)
            ->assertSee('data-remaining="'.(Classroom::studentCapacity() - 1).'"', false)
            ->assertSee('Kapasitas maksimal '.Classroom::studentCapacity().' siswa per rombel.');
    }

    // ── Rule: tahun ajaran rombel tidak boleh diubah ─────────────────────────

    public function test_classroom_academic_year_cannot_be_changed_after_creation(): void
    {
        $admin = $this->createAdmin();
        $this->createAcademicYear('2026/2027', AcademicYear::STATUS_CURRENT);
        $other = $this->createAcademicYear('2027/2028', AcademicYear::STATUS_UPCOMING);
        $classroom = $this->createClassroom(['name' => 'X MIPA 1']);

        $response = $this->actingAs($admin)->put(
            route('admin.classrooms.update', $classroom->id),
            $this->validPayload(['name' => 'X MIPA 1', 'academic_year_id' => $other->id]),
        );

        $response->assertSessionHasErrors('academic_year_id');
        $this->assertSame($this->yearId('2026/2027'), $classroom->fresh()->academic_year_id);
    }
}
