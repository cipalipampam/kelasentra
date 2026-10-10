<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private Classroom $classroom;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'guru']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['name' => 'Hendra Kusuma']);
        $this->teacher->assignRole('guru');

        $this->subject = Subject::create([
            'code' => 'MAT-W',
            'name' => 'Matematika Wajib',
            'cluster' => 'mipa',
            'color_code' => '#3B82F6',
            'is_active' => true,
        ]);

        $this->teacher->subjects()->attach($this->subject->id, ['is_primary' => true]);

        $this->classroom = Classroom::create([
            'name' => 'X-MIPA 1',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $this->yearId('2026/2027'),
        ]);
    }

    public function test_admin_can_view_schedules_index(): void
    {
        $this->makeSchedule($this->classroom, $this->subject, $this->teacher, 1, '07:15:00', '08:45:00', ['room' => 'R.101']);

        $response = $this->actingAs($this->admin)->get(route('admin.schedules.index', [
            'classroom_id' => $this->classroom->id,
        ]));

        $response->assertOk()
            ->assertSee('Jadwal Pelajaran')
            ->assertSee('X-MIPA 1')
            ->assertSee('Matematika Wajib')
            ->assertSee('Hendra Kusuma')
            ->assertSee('2 JP');
    }

    public function test_admin_can_store_valid_schedule(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.schedules.store'), [
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '11:30',
            'room' => 'R.102',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.schedules.index', ['classroom_id' => $this->classroom->id]));
        $response->assertSessionHas('success');

        $schedule = Schedule::firstOrFail();
        $this->assertSame(2, $schedule->day_of_week);
        $this->assertSame('R.102', $schedule->room);
        $this->assertSame($this->classroom->id, $schedule->classroom->id);
        $this->assertSame($this->subject->id, $schedule->subject->id);
        $this->assertSame($this->teacher->id, $schedule->teacher->id);
    }

    public function test_admin_can_update_schedule(): void
    {
        $schedule = $this->makeSchedule($this->classroom, $this->subject, $this->teacher, 3, '07:15:00', '08:45:00', ['room' => 'R.101']);

        $response = $this->actingAs($this->admin)->put(route('admin.schedules.update', $schedule->id), [
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 3,
            'start_time' => '07:30',
            'end_time' => '09:00',
            'room' => 'Lab Komputer 1',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.schedules.index', ['classroom_id' => $this->classroom->id]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'room' => 'Lab Komputer 1',
        ]);
    }

    public function test_admin_can_delete_schedule(): void
    {
        $schedule = $this->makeSchedule($this->classroom, $this->subject, $this->teacher, 4, '07:15:00', '08:45:00');

        $response = $this->actingAs($this->admin)->delete(route('admin.schedules.destroy', $schedule->id));

        $response->assertRedirect(route('admin.schedules.index', ['classroom_id' => $this->classroom->id]));
        $response->assertSessionHas('success');

        // Riwayat jadwal tetap tersimpan: nonaktif, bukan hilang.
        $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
    }

    public function test_ajax_get_teachers_by_subject_returns_linear_teachers(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.schedules.teachers-by-subject', $this->subject->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->teacher->id)
            ->assertJsonPath('data.0.is_primary', true);
    }

    public function test_store_schedule_fails_when_clash_occurs(): void
    {
        // Buat jadwal 1: X-MIPA 1, Senin 07:15-08:45
        $this->makeSchedule($this->classroom, $this->subject, $this->teacher, 1, '07:15:00', '08:45:00');

        // Coba buat jadwal 2 bertabrakan di kelas lain dengan guru yang sama pada waktu yang sama
        $classroom2 = Classroom::create([
            'name' => 'X-MIPA 2',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '2',
            'academic_year_id' => $this->yearId('2026/2027'),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.schedules.store'), [
            'classroom_id' => $classroom2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '07:15',
            'end_time' => '08:45',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['teacher_id']);
    }

    public function test_store_schedule_fails_when_overlapping_break_time(): void
    {
        // Coba buat KBM jam 09:15 - 10:15 (menabrak Istirahat I 09:30 - 10:00)
        $response = $this->actingAs($this->admin)->post(route('admin.schedules.store'), [
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '09:15',
            'end_time' => '10:15',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['start_time']);
    }
}
