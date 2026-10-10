<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\Web\Academic\ClassroomService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saran nomor sesi rombel: sesi baru hanya diusulkan bila sesi terakhir
 * pada tingkat + jurusan + tahun ajaran yang sama sudah penuh.
 */
class ClassroomSectionSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function service(): ClassroomService
    {
        return app(ClassroomService::class);
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
            'academic_year_id' => $this->yearId('2026/2027'),
            'is_active' => true,
        ], $attributes));
    }

    private function fillClassroom(Classroom $classroom, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->enrollStudent($classroom, 'N'.$classroom->id.'-'.$i);
        }
    }

    public function test_overview_groups_sessions_by_level_major_and_year(): void
    {
        $year = $this->yearId('2026/2027');
        $nextYear = $this->yearId('2027/2028');

        $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);
        $this->createClassroom(['name' => 'XI MIPA 2', 'section' => '2']);
        $this->createClassroom(['name' => 'XI IPS 1', 'major' => 'IPS']);
        $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1', 'academic_year_id' => $nextYear]);

        $overview = $this->service()->getSectionOverview();

        $this->assertArrayHasKey("11|MIPA|{$year}", $overview);
        $this->assertArrayHasKey("11|IPS|{$year}", $overview);
        $this->assertArrayHasKey("11|MIPA|{$nextYear}", $overview);
        $this->assertCount(2, $overview["11|MIPA|{$year}"]);
        $this->assertSame(['1', '2'], array_column($overview["11|MIPA|{$year}"], 'section'));
    }

    public function test_overview_marks_a_session_full_only_at_capacity(): void
    {
        $almostFull = $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);
        $this->fillClassroom($almostFull, Classroom::studentCapacity() - 1);

        $full = $this->createClassroom(['name' => 'XI MIPA 2', 'section' => '2']);
        $this->fillClassroom($full, Classroom::studentCapacity());

        $sessions = $this->service()->getSectionOverview()['11|MIPA|'.$this->yearId('2026/2027')];

        $this->assertFalse($sessions[0]['is_full']);
        $this->assertSame(1, $sessions[0]['remaining']);
        $this->assertTrue($sessions[1]['is_full']);
        $this->assertSame(0, $sessions[1]['remaining']);
    }

    public function test_overview_ignores_students_that_are_not_active(): void
    {
        $classroom = $this->createClassroom(['section' => '1']);
        $this->fillClassroom($classroom, Classroom::studentCapacity());

        // Siswa non-aktif berarti enrollment berjalannya ditutup.
        $classroom->currentEnrollments()->update(['ended_at' => now()->toDateString()]);

        $sessions = $this->service()->getSectionOverview()['11|MIPA|'.$this->yearId('2026/2027')];

        $this->assertFalse($sessions[0]['is_full']);
        $this->assertSame(Classroom::studentCapacity(), $sessions[0]['remaining']);
    }

    public function test_classrooms_without_major_form_their_own_group(): void
    {
        $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'major' => 'MIPA']);
        $this->createClassroom(['name' => 'X BAHASA 1', 'level' => '10', 'major' => null, 'section' => '1']);

        $overview = $this->service()->getSectionOverview();
        $year = $this->yearId('2026/2027');

        $this->assertArrayHasKey("10|MIPA|{$year}", $overview);
        $this->assertArrayHasKey("10||{$year}", $overview);
    }

    public function test_numeric_sessions_are_sorted_before_non_numeric_once(): void
    {
        $this->createClassroom(['name' => 'XI MIPA A', 'section' => 'A']);
        $this->createClassroom(['name' => 'XI MIPA 2', 'section' => '2']);
        $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);

        $sessions = $this->service()->getSectionOverview()['11|MIPA|'.$this->yearId('2026/2027')];

        $this->assertSame(['1', '2', 'A'], array_column($sessions, 'section'));
    }

    public function test_index_page_embeds_the_section_overview_for_the_form(): void
    {
        $admin = $this->createAdmin();
        $year = $this->yearId('2026/2027');

        $classroom = $this->createClassroom(['name' => 'XI MIPA 1', 'section' => '1']);
        $this->fillClassroom($classroom, Classroom::studentCapacity());

        $response = $this->actingAs($admin)->get(route('admin.classrooms.index'));

        $response->assertOk()
            ->assertSee('data-section-overview', false)
            ->assertSee("11|MIPA|{$year}", false)
            ->assertSee('create_section_hint', false);
    }

    public function test_classroom_exposes_section_key_and_numeric_detection(): void
    {
        $year = $this->yearId('2026/2027');
        $withMajor = $this->createClassroom(['level' => '11', 'major' => 'MIPA', 'section' => '2']);
        $withoutMajor = $this->createClassroom(['name' => 'X BAHASA A', 'level' => '10', 'major' => null, 'section' => 'A']);

        $this->assertSame("11|MIPA|{$year}", $withMajor->sectionKey());
        $this->assertSame("10||{$year}", $withoutMajor->sectionKey());
        $this->assertTrue($withMajor->hasNumericSection());
        $this->assertFalse($withoutMajor->hasNumericSection());
    }
}
