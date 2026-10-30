<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudAuthorizationP2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->teacher = User::factory()->create(['name' => 'Guru Pengajar']);
        $this->teacher->assignRole('guru');
        $this->teacher->employee()->create();

        $yearId = $this->yearId('2026/2027');
        $classroom = Classroom::create([
            'name' => 'X MIPA 1',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $yearId,
        ]);

        $this->studentUser = User::factory()->create(['name' => 'Siswa Belajar']);
        $this->enrollStudent($classroom, 'NIS-P2-1', 'active', $this->studentUser);
    }

    /**
     * A-03: Route CRUD siswa harus menolak (404) akses terhadap ID pengguna non-siswa.
     */
    public function test_student_routes_reject_non_student_users(): void
    {
        // Akses ID admin via route siswa
        $this->actingAs($this->admin)
            ->get(route('admin.students.show', $this->admin->id))
            ->assertNotFound();

        // Akses ID guru via route siswa
        $this->actingAs($this->admin)
            ->get(route('admin.students.edit', $this->teacher->id))
            ->assertNotFound();

        // Update ID guru via route siswa
        $this->actingAs($this->admin)
            ->put(route('admin.students.update', $this->teacher->id), [
                'name' => 'Guru Diubah Siswa',
                'email' => $this->teacher->email,
            ])
            ->assertNotFound();

        // Hapus ID guru via route siswa
        $this->actingAs($this->admin)
            ->delete(route('admin.students.destroy', $this->teacher->id))
            ->assertNotFound();

        // Pastikan akun guru tidak terhapus
        $this->assertNotSoftDeleted('users', ['id' => $this->teacher->id]);
    }

    /**
     * A-12: Route CRUD pegawai/guru harus menolak (404) akses terhadap ID pengguna non-pegawai (admin atau siswa).
     */
    public function test_employee_routes_reject_non_employee_users(): void
    {
        // Akses ID admin via route pegawai
        $this->actingAs($this->admin)
            ->get(route('admin.employees.show', $this->admin->id))
            ->assertNotFound();

        // Akses ID siswa via route pegawai
        $this->actingAs($this->admin)
            ->get(route('admin.employees.edit', $this->studentUser->id))
            ->assertNotFound();

        // Update ID siswa via route pegawai
        $this->actingAs($this->admin)
            ->put(route('admin.employees.update', $this->studentUser->id), [
                'name' => 'Siswa Diubah Pegawai',
                'email' => $this->studentUser->email,
                'role' => 'guru',
            ])
            ->assertNotFound();

        // Hapus ID siswa via route pegawai
        $this->actingAs($this->admin)
            ->delete(route('admin.employees.destroy', $this->studentUser->id))
            ->assertNotFound();

        // Pastikan akun siswa tidak terhapus
        $this->assertNotSoftDeleted('users', ['id' => $this->studentUser->id]);
    }

    /**
     * A-09: duplicateClassrooms harus menolak jika classroom_ids tidak berasal dari source_academic_year_id.
     */
    public function test_duplicate_classrooms_rejects_classrooms_from_different_academic_year(): void
    {
        $yearA = AcademicYear::create([
            'name' => '2024/2025',
            'status' => AcademicYear::STATUS_CLOSED,
        ]);

        $yearB = AcademicYear::where('status', AcademicYear::STATUS_CURRENT)->firstOrFail();

        $targetUpcoming = AcademicYear::create([
            'name' => '2027/2028',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);

        $classroomInYearA = Classroom::create([
            'name' => 'X-A',
            'level' => '10',
            'section' => '1',
            'academic_year_id' => $yearA->id,
        ]);

        $classroomInYearB = Classroom::create([
            'name' => 'X-B',
            'level' => '10',
            'section' => '2',
            'academic_year_id' => $yearB->id,
        ]);

        // Kirim source_academic_year_id = Year B, tapi classroom_ids berisi ID dari Year A
        $response = $this->actingAs($this->admin)->post(route('admin.classrooms.duplicate'), [
            'source_academic_year_id' => $yearB->id,
            'target_academic_year_id' => $targetUpcoming->id,
            'classroom_ids' => [$classroomInYearA->id],
        ]);

        $response->assertSessionHasErrors('classroom_ids');
        $this->assertDatabaseMissing('classrooms', [
            'name' => 'X-A',
            'academic_year_id' => $targetUpcoming->id,
        ]);
    }

    /**
     * A-10: Store & Update Schedule harus menolak mata pelajaran yang berstatus tidak aktif.
     */
    public function test_schedule_store_and_update_reject_inactive_subject(): void
    {
        $classroom = Classroom::firstOrFail();

        $inactiveSubject = Subject::create([
            'code' => 'INACT-1',
            'name' => 'Mapel Nonaktif',
            'cluster' => 'umum',
            'color_code' => '#64748B',
            'is_active' => false,
        ]);

        $this->teacher->subjects()->attach($inactiveSubject->id, ['is_primary' => true]);

        // Coba buat jadwal dengan mapel nonaktif via POST request
        $response = $this->actingAs($this->admin)->post(route('admin.schedules.store'), [
            'classroom_id' => $classroom->id,
            'subject_id' => $inactiveSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'start_time' => '07:15',
            'end_time' => '08:45',
        ]);

        $response->assertSessionHasErrors('subject_id');

        // Buat jadwal valid dulu dengan mapel aktif
        $activeSubject = Subject::create([
            'code' => 'ACT-1',
            'name' => 'Mapel Aktif',
            'cluster' => 'umum',
            'color_code' => '#10B981',
            'is_active' => true,
        ]);
        $this->teacher->subjects()->attach($activeSubject->id);

        $schedule = $this->createSchedule([
            'classroom_id' => $classroom->id,
            'subject_id' => $activeSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 2,
            'start_time' => '07:15',
            'end_time' => '08:45',
        ]);

        // Coba update jadwal ke mapel nonaktif via PUT request
        $updateResponse = $this->actingAs($this->admin)->put(route('admin.schedules.update', $schedule->id), [
            'classroom_id' => $classroom->id,
            'subject_id' => $inactiveSubject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 2,
            'start_time' => '07:15',
            'end_time' => '08:45',
        ]);

        $updateResponse->assertSessionHasErrors('subject_id');
    }
}
