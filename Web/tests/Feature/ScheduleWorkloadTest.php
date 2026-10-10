<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use App\Services\Web\Academic\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleWorkloadTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleService $scheduleService;
    private User $teacher;
    private Subject $mainSubject;
    private Subject $cognateSubject;
    private Subject $unrelatedSubject;
    private Classroom $classroomA;
    private Classroom $classroomB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'guru']);

        $this->scheduleService = app(ScheduleService::class);

        $this->teacher = User::factory()->create(['name' => 'Budi Santoso']);
        $this->teacher->assignRole('guru');

        $this->mainSubject = Subject::create([
            'code' => 'MAT-W',
            'name' => 'Matematika Wajib',
            'cluster' => 'mipa',
            'color_code' => '#3B82F6',
            'is_active' => true,
        ]);

        $this->cognateSubject = Subject::create([
            'code' => 'INF',
            'name' => 'Informatika',
            'cluster' => 'mipa',
            'color_code' => '#10B981',
            'is_active' => true,
        ]);

        $this->unrelatedSubject = Subject::create([
            'code' => 'GEO',
            'name' => 'Geografi',
            'cluster' => 'ips',
            'color_code' => '#F59E0B',
            'is_active' => true,
        ]);

        // Hubungkan guru dengan mapel (MAT-W = Utama, INF = Serumpun)
        $this->teacher->subjects()->attach([
            $this->mainSubject->id => ['is_primary' => true],
            $this->cognateSubject->id => ['is_primary' => false],
        ]);

        $this->classroomA = Classroom::create([
            'name' => 'X-MIPA 1',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $this->yearId('2026/2027'),
        ]);

        $this->classroomB = Classroom::create([
            'name' => 'XI-MIPA 1',
            'level' => '11',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $this->yearId('2026/2027'),
        ]);
    }

    public function test_schedule_model_calculates_minutes_and_jp_correctly(): void
    {
        $sched = $this->createSchedule([
            'classroom_id' => $this->classroomA->id,
            'subject_id' => $this->mainSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '07:15:00',
            'end_time' => '08:45:00', // 90 menit = 2 JP
            'room' => 'R.101',
        ]);

        $this->assertEquals(90, $sched->duration_in_minutes);
        $this->assertEquals(2, $sched->jp_count);
        $this->assertEquals('07:15 - 08:45', $sched->formatted_time_range);
        $this->assertEquals('Senin', $sched->day_name);
    }

    public function test_cannot_schedule_outside_school_days_or_hours(): void
    {
        // Sabtu (day 6) ditolak
        $this->expectException(ValidationException::class);
        $this->scheduleService->createSchedule([
            'classroom_id' => $this->classroomA->id,
            'subject_id' => $this->mainSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 6,
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
        ]);
    }

    public function test_cannot_schedule_outside_07_to_16(): void
    {
        $this->expectException(ValidationException::class);
        $this->scheduleService->createSchedule([
            'classroom_id' => $this->classroomA->id,
            'subject_id' => $this->mainSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '15:30:00',
            'end_time' => '16:30:00', // Melebihi 16:00
        ]);
    }

    public function test_teacher_cannot_teach_non_linear_subject(): void
    {
        // Mencoba menugaskan Geografi padahal guru hanya pegang Matematika & Informatika
        $this->expectException(ValidationException::class);
        $this->scheduleService->createSchedule([
            'classroom_id' => $this->classroomA->id,
            'subject_id' => $this->unrelatedSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 2,
            'start_time' => '08:00:00',
            'end_time' => '09:30:00',
        ]);
    }

    public function test_prevents_teacher_schedule_clash(): void
    {
        // Buat jadwal pertama di kelas A
        $this->createSchedule([
            'classroom_id' => $this->classroomA->id,
            'subject_id' => $this->mainSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '07:15:00',
            'end_time' => '08:45:00',
            'is_active' => true,
        ]);

        // Coba jadwalkan guru yang sama di kelas B pada jam yang beririsan
        $this->expectException(ValidationException::class);
        $this->scheduleService->createSchedule([
            'classroom_id' => $this->classroomB->id,
            'subject_id' => $this->cognateSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00', // Beririsan dengan 07:15 - 08:45
            'end_time' => '09:30:00',
        ]);
    }

    public function test_prevents_classroom_schedule_clash(): void
    {
        $otherTeacher = User::factory()->create(['name' => 'Guru Lain']);
        $otherTeacher->assignRole('guru');
        $otherTeacher->subjects()->attach($this->mainSubject->id, ['is_primary' => true]);

        // Kelas A sudah ada jadwal pada Senin 07:15 - 08:45
        $this->createSchedule([
            'classroom_id' => $this->classroomA->id,
            'subject_id' => $this->mainSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '07:15:00',
            'end_time' => '08:45:00',
            'is_active' => true,
        ]);

        // Guru lain mencoba masuk ke kelas A pada jam yang sama
        $this->expectException(ValidationException::class);
        $this->scheduleService->createSchedule([
            'classroom_id' => $this->classroomA->id,
            'subject_id' => $this->mainSubject->id,
            'teacher_id' => $otherTeacher->id,
            'day_of_week' => 1,
            'start_time' => '07:15:00',
            'end_time' => '08:45:00',
        ]);
    }

    public function test_teacher_weekly_workload_calculation_and_status(): void
    {
        // 1. Tambah 1 jadwal 90 menit (2 JP) -> status underload (<24 JP)
        $this->createSchedule([
            'classroom_id' => $this->classroomA->id,
            'subject_id' => $this->mainSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '07:15:00',
            'end_time' => '08:45:00',
            'is_active' => true,
        ]);

        $workload = $this->scheduleService->calculateTeacherWeeklyWorkload($this->teacher);
        $this->assertEquals(2, $workload['total_jp']);
        $this->assertEquals('underload', $workload['status']);
        $this->assertEquals('warning', $workload['status_color']);
        $this->assertEquals(8, $workload['target_percentage']); // 2 / 24 * 100 = 8%

        // 2. Tambah jadwal hingga mencapai 24 JP (11 sesi 90 menit lagi = 22 JP, total 24 JP)
        $days = [1, 2, 3, 4, 5];
        $times = [
            ['09:00:00', '10:30:00'],
            ['10:45:00', '12:15:00'],
            ['13:00:00', '14:30:00'],
        ];

        $added = 0;
        foreach ($days as $d) {
            foreach ($times as $t) {
                if ($added >= 11) break 2;
                $this->createSchedule([
                    'classroom_id' => $this->classroomB->id,
                    'subject_id' => $this->cognateSubject->id,
                    'teacher_id' => $this->teacher->id,
                    'day_of_week' => $d,
                    'start_time' => $t[0],
                    'end_time' => $t[1],
                    'is_active' => true,
                ]);
                $added++;
            }
        }

        $workloadOptimal = $this->scheduleService->calculateTeacherWeeklyWorkload($this->teacher);
        $this->assertEquals(24, $workloadOptimal['total_jp']);
        $this->assertEquals('optimal', $workloadOptimal['status']);
        $this->assertEquals('success', $workloadOptimal['status_color']);
        $this->assertEquals(100, $workloadOptimal['target_percentage']);
    }

    public function test_admin_can_view_employee_detail_with_teacher_workload(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->createSchedule([
            'classroom_id' => $this->classroomA->id,
            'subject_id' => $this->mainSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '07:15:00',
            'end_time' => '08:45:00',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.employees.show', $this->teacher->id));

        $response->assertOk()
            ->assertSee('Beban Mengajar')
            ->assertSee('Jam Pelajaran (JP)')
            ->assertSee('2')
            ->assertSee('JP / minggu')
            ->assertSee('Matematika Wajib');
    }
}
