<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Rules\EnrollableClassroom;
use App\Services\Web\Academic\ClassroomService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Keterkaitan data siswa dengan rombel: siswa aktif wajib berada di satu
 * rombel tahun ajaran aktif, dan kapasitas rombel tidak boleh dilampaui.
 */
class StudentRombelIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        AcademicYear::create(['name' => '2026/2027', 'status' => AcademicYear::STATUS_ACTIVE]);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function createClassroom(array $attributes = []): Classroom
    {
        return Classroom::create(array_merge([
            'name' => 'XI MIPA 1',
            'level' => '11',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ], $attributes));
    }

    private function createStudent(Classroom $classroom, array $attributes = []): Student
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        return Student::create(array_merge([
            'user_id' => $user->id,
            'classroom_id' => $classroom->id,
            'grade' => $classroom->name,
            'academic_status' => 'active',
        ], $attributes));
    }

    private function fillClassroom(Classroom $classroom, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createStudent($classroom, ['nis' => 'N'.$classroom->id.'-'.$i]);
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Siswa Baru',
            'email' => 'siswa.baru@sekolah.test',
            'password' => 'password123',
            'gender' => 'male',
        ], $overrides);
    }

    public function test_store_requires_a_rombel(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.students.store'), $this->payload());

        $response->assertSessionHasErrors('classroom_id');
        $this->assertSame(0, Student::count());
    }

    public function test_store_places_the_student_in_the_chosen_rombel_and_derives_grade(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->createClassroom(['name' => 'X BAHASA 2', 'level' => '10', 'major' => 'BAHASA']);

        $response = $this->actingAs($admin)->post(route('admin.students.store'), $this->payload([
            'classroom_id' => $classroom->id,
        ]));

        $response->assertRedirect(route('admin.students.index'));
        $response->assertSessionHasNoErrors();

        $student = Student::firstOrFail();
        $this->assertSame($classroom->id, $student->classroom_id);
        $this->assertSame('X BAHASA 2', $student->getRawOriginal('grade'));
        $this->assertSame('active', $student->academic_status);
    }

    public function test_store_rejects_a_full_rombel(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->createClassroom();
        $this->fillClassroom($classroom, Classroom::studentCapacity());

        $response = $this->actingAs($admin)->post(route('admin.students.store'), $this->payload([
            'classroom_id' => $classroom->id,
        ]));

        $response->assertSessionHasErrors('classroom_id');
        $this->assertSame(Classroom::studentCapacity(), Student::count());
    }

    public function test_store_rejects_a_rombel_from_a_past_academic_year(): void
    {
        $admin = $this->createAdmin();
        AcademicYear::create(['name' => '2025/2026', 'status' => AcademicYear::STATUS_ARCHIVED]);

        $classroom = $this->createClassroom(['name' => 'XI MIPA 1', 'academic_year' => '2025/2026']);

        $response = $this->actingAs($admin)->post(route('admin.students.store'), $this->payload([
            'classroom_id' => $classroom->id,
        ]));

        $response->assertSessionHasErrors('classroom_id');
    }

    public function test_store_rejects_an_inactive_rombel(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->createClassroom(['is_active' => false]);

        $response = $this->actingAs($admin)->post(route('admin.students.store'), $this->payload([
            'classroom_id' => $classroom->id,
        ]));

        $response->assertSessionHasErrors('classroom_id');
    }

    public function test_moving_a_student_into_a_full_rombel_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $origin = $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);
        $target = $this->createClassroom(['name' => 'XI MIPA 2', 'section' => '2']);

        $student = $this->createStudent($origin);
        $this->fillClassroom($target, Classroom::studentCapacity());

        $response = $this->actingAs($admin)->put(route('admin.students.update', $student->user_id), $this->payload([
            'name' => 'Nama Diubah',
            'email' => $student->user->email,
            'classroom_id' => $target->id,
        ]));

        $response->assertSessionHasErrors('classroom_id');
        $this->assertSame($origin->id, $student->fresh()->classroom_id);
    }

    public function test_editing_a_student_in_a_full_rombel_still_works(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->createClassroom();
        $this->fillClassroom($classroom, Classroom::studentCapacity());

        $student = $classroom->students()->firstOrFail();

        $response = $this->actingAs($admin)->put(route('admin.students.update', $student->user_id), $this->payload([
            'name' => 'Nama Diperbarui',
            'email' => $student->user->email,
            'classroom_id' => $classroom->id,
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame('Nama Diperbarui', $student->user->fresh()->name);
        $this->assertSame($classroom->id, $student->fresh()->classroom_id);
    }

    public function test_editing_an_alumnus_does_not_require_a_rombel(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->createClassroom();

        $alumnus = $this->createStudent($classroom, [
            'classroom_id' => null,
            'academic_status' => 'graduated',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.students.update', $alumnus->user_id), $this->payload([
            'name' => 'Alumni Diperbarui',
            'email' => $alumnus->user->email,
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertNull($alumnus->fresh()->classroom_id);
        $this->assertSame('graduated', $alumnus->fresh()->academic_status);
    }

    public function test_index_filters_students_by_rombel(): void
    {
        $admin = $this->createAdmin();
        $first = $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);
        $second = $this->createClassroom(['name' => 'XI MIPA 2', 'section' => '2']);

        $studentA = $this->createStudent($first);
        $studentB = $this->createStudent($second);

        $response = $this->actingAs($admin)->get(route('admin.students.index', ['classroom_id' => $first->id]));

        $response->assertOk()
            ->assertSee($studentA->user->name)
            ->assertDontSee($studentB->user->name)
            ->assertSee('name="classroom_id"', false);
    }

    public function test_index_filter_lists_running_classrooms_only(): void
    {
        $admin = $this->createAdmin();
        $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);

        AcademicYear::create(['name' => '2027/2028', 'status' => AcademicYear::STATUS_UPCOMING]);
        $upcoming = $this->createClassroom(['name' => 'XI MIPA 2', 'section' => '2', 'academic_year' => '2027/2028']);

        AcademicYear::create(['name' => '2025/2026', 'status' => AcademicYear::STATUS_ARCHIVED]);
        $past = $this->createClassroom(['name' => 'XI MIPA 9', 'section' => '9', 'academic_year' => '2025/2026']);

        $response = $this->actingAs($admin)->get(route('admin.students.index'));

        $response->assertOk()
            ->assertSee('XI MIPA 1')
            ->assertSee($upcoming->name)
            ->assertDontSee($past->name);
    }

    public function test_upcoming_year_classroom_can_receive_new_students(): void
    {
        $admin = $this->createAdmin();
        AcademicYear::create(['name' => '2027/2028', 'status' => AcademicYear::STATUS_UPCOMING]);

        $upcoming = $this->createClassroom([
            'name' => 'X MIPA 1',
            'level' => '10',
            'academic_year' => '2027/2028',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.students.store'), $this->payload([
            'classroom_id' => $upcoming->id,
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame($upcoming->id, Student::firstOrFail()->classroom_id);
    }

    public function test_attendance_page_filters_records_by_rombel(): void
    {
        $admin = $this->createAdmin();
        $first = $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);
        $second = $this->createClassroom(['name' => 'XI MIPA 2', 'section' => '2']);

        $studentA = $this->createStudent($first, ['nis' => 'N-A']);
        $studentB = $this->createStudent($second, ['nis' => 'N-B']);

        foreach ([$studentA, $studentB] as $student) {
            Attendance::create([
                'user_id' => $student->user_id,
                'recorded_at' => now(),
                'status' => 'present',
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.attendances.students', [
            'date' => now()->toDateString(),
            'classroom_id' => $first->id,
        ]));

        $response->assertOk()
            ->assertSee($studentA->user->name)
            ->assertDontSee($studentB->user->name);
    }

    public function test_enrollable_classrooms_exclude_full_ones_but_keep_the_current_rombel(): void
    {
        $open = $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);
        $full = $this->createClassroom(['name' => 'XI MIPA 2', 'section' => '2']);
        $this->fillClassroom($full, Classroom::studentCapacity());

        $service = app(ClassroomService::class);

        $this->assertSame([$open->id], $service->getEnrollableClassrooms()->pluck('id')->all());
        $this->assertSame(
            [$open->id, $full->id],
            $service->getEnrollableClassrooms($full->id)->pluck('id')->all()
        );
    }

    public function test_enrollable_classrooms_are_ordered_by_level_major_and_section(): void
    {
        $this->createClassroom(['name' => 'XI BAHASA 1', 'level' => '11', 'major' => 'BAHASA']);
        $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'major' => 'MIPA']);
        $this->createClassroom(['name' => 'XI MIPA 2', 'section' => '2']);
        $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);

        $names = app(ClassroomService::class)->getEnrollableClassrooms()->pluck('name')->all();

        $this->assertSame(['X MIPA 1', 'XI MIPA 1', 'XI MIPA 2', 'XI BAHASA 1'], $names);
    }

    public function test_rule_accepts_a_rombel_that_is_not_yet_full(): void
    {
        $classroom = $this->createClassroom();

        $validator = Validator::make(['classroom_id' => $classroom->id], [
            'classroom_id' => [new EnrollableClassroom],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_rule_ignores_an_empty_value_so_alumni_can_be_saved(): void
    {
        $validator = Validator::make(['classroom_id' => null], [
            'classroom_id' => [new EnrollableClassroom],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_edit_page_keeps_the_current_rombel_selectable_when_it_is_full(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->createClassroom();
        $this->fillClassroom($classroom, Classroom::studentCapacity());

        $student = $classroom->students()->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.students.edit', $student->user_id));

        $response->assertOk()
            ->assertSee('name="classroom_id"', false)
            ->assertSee($classroom->name);
    }

    public function test_create_page_warns_when_no_rombel_can_accept_students(): void
    {
        $admin = $this->createAdmin();
        $classroom = $this->createClassroom();
        $this->fillClassroom($classroom, Classroom::studentCapacity());

        $response = $this->actingAs($admin)->get(route('admin.students.create'));

        $response->assertOk()->assertSee('Belum ada rombel aktif dengan kursi tersisa');
    }
}
