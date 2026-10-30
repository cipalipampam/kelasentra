<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\PromotionBatchItem;
use App\Models\Schedule;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use App\Services\Web\Academic\ClassroomService;
use App\Services\Web\Academic\EnrollmentService;
use App\Services\Web\Academic\ScheduleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicIntegrityP1Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function createTeacher(string $name, Subject $subject): User
    {
        $teacher = User::factory()->create(['name' => $name]);
        $teacher->assignRole('guru');
        $teacher->employee()->create();
        $teacher->subjects()->attach($subject->id, ['is_primary' => true]);

        return $teacher;
    }

    /**
     * A-01: Mengubah guru pada satu slot memindahkan seluruh slot aktif mapel tersebut,
     * sehingga tidak ada slot yang menjadi orphan menunjuk assignment soft-deleted.
     */
    public function test_reassigning_teacher_moves_all_slots_of_same_subject_and_prevents_orphan(): void
    {
        $classroom = Classroom::create([
            'name' => 'X MIPA 1',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $this->yearId('2026/2027'),
        ]);

        $subject = Subject::create([
            'code' => 'MAT-W',
            'name' => 'Matematika Wajib',
            'cluster' => 'mipa',
            'color_code' => '#3B82F6',
            'is_active' => true,
        ]);

        $teacherA = $this->createTeacher('Guru A', $subject);
        $teacherB = $this->createTeacher('Guru B', $subject);

        $scheduleService = app(ScheduleService::class);

        // Slot 1: Senin 07:15 - 08:45
        $slotMonday = $scheduleService->createSchedule([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherA->id,
            'day_of_week' => 1,
            'start_time' => '07:15',
            'end_time' => '08:45',
            'is_active' => true,
        ]);

        // Slot 2: Rabu 10:00 - 11:30
        $slotWednesday = $scheduleService->createSchedule([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherA->id,
            'day_of_week' => 3,
            'start_time' => '10:00',
            'end_time' => '11:30',
            'is_active' => true,
        ]);

        $oldAssignmentId = $slotMonday->teaching_assignment_id;
        $this->assertSame($oldAssignmentId, $slotWednesday->teaching_assignment_id);

        // Admin mengubah slot Senin ke Guru B
        $scheduleService->updateSchedule($slotMonday, [
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherB->id,
            'day_of_week' => 1,
            'start_time' => '07:15',
            'end_time' => '08:45',
            'is_active' => true,
        ]);

        $slotMonday->refresh();
        $slotWednesday->refresh();

        // Keduanya sekarang menunjuk penugasan Guru B yang aktif
        $this->assertSame((int) $slotMonday->teacher->id, (int) $teacherB->id);
        $this->assertSame((int) $slotWednesday->teacher->id, (int) $teacherB->id);
        $this->assertSame($slotMonday->teaching_assignment_id, $slotWednesday->teaching_assignment_id);
        $this->assertNotSame($oldAssignmentId, $slotMonday->teaching_assignment_id);

        // Tidak ada slot yang orphan (bisa diquery via whereHas)
        $activeCount = Schedule::whereHas('assignment', fn ($q) => $q->where('teacher_id', $teacherB->id))->count();
        $this->assertSame(2, $activeCount);

        // Penugasan lama terhapus soft delete
        $this->assertSoftDeleted('teaching_assignments', ['id' => $oldAssignmentId]);
    }

    /**
     * A-01: Pergantian guru ditolak bila guru baru bentrok pada slot lain mapel tersebut.
     */
    public function test_reassigning_teacher_is_rejected_if_new_teacher_clashes_on_other_slots_of_subject(): void
    {
        $yearId = $this->yearId('2026/2027');

        $class1 = Classroom::create([
            'name' => 'X MIPA 1',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $yearId,
        ]);

        $class2 = Classroom::create([
            'name' => 'X MIPA 2',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '2',
            'academic_year_id' => $yearId,
        ]);

        $subject = Subject::create([
            'code' => 'MAT-W',
            'name' => 'Matematika Wajib',
            'cluster' => 'mipa',
            'color_code' => '#3B82F6',
            'is_active' => true,
        ]);

        $teacherA = $this->createTeacher('Guru A', $subject);
        $teacherB = $this->createTeacher('Guru B', $subject);

        $scheduleService = app(ScheduleService::class);

        // Di Class 1: Slot Senin 07:15-08:45 & Slot Rabu 10:00-11:30 diampu Guru A
        $slotMonday = $scheduleService->createSchedule([
            'classroom_id' => $class1->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherA->id,
            'day_of_week' => 1,
            'start_time' => '07:15',
            'end_time' => '08:45',
            'is_active' => true,
        ]);

        $slotWednesday = $scheduleService->createSchedule([
            'classroom_id' => $class1->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherA->id,
            'day_of_week' => 3,
            'start_time' => '10:00',
            'end_time' => '11:30',
            'is_active' => true,
        ]);

        // Di Class 2: Guru B SUDAH punya jadwal Rabu 10:00-11:30
        $scheduleService->createSchedule([
            'classroom_id' => $class2->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherB->id,
            'day_of_week' => 3,
            'start_time' => '10:00',
            'end_time' => '11:30',
            'is_active' => true,
        ]);

        // Coba ganti slot Senin Class 1 ke Guru B. Ini harus ditolak karena slot Rabu Class 1
        // yang ikut berpindah ke Guru B akan bentrok dengan jadwal Guru B di Class 2!
        $this->expectException(ValidationException::class);

        $scheduleService->updateSchedule($slotMonday, [
            'classroom_id' => $class1->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherB->id,
            'day_of_week' => 1,
            'start_time' => '07:15',
            'end_time' => '08:45',
            'is_active' => true,
        ]);
    }

    /**
     * A-02 & A-06: Pindah rombel dalam tahun ajaran sama membuat enrollment baru,
     * menutup enrollment lama tanpa overlap tanggal, dan mempertahankan riwayat.
     */
    public function test_same_year_transfer_creates_new_enrollment_and_preserves_historical_roster(): void
    {
        $yearId = $this->yearId('2026/2027');

        $classA = Classroom::create([
            'name' => 'X MIPA 1',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $yearId,
        ]);

        $classB = Classroom::create([
            'name' => 'X MIPA 2',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '2',
            'academic_year_id' => $yearId,
        ]);

        $student = $this->enrollStudent($classA, 'NIS-1001', 'active', null, '2026-07-01');

        $this->assertDatabaseCount('student_enrollments', 1);

        // Transfer siswa ke Class B per 1 September 2026
        $newEnrollment = app(EnrollmentService::class)->place($student, $classB, '2026-09-01');

        // Harus ada 2 baris enrollment terpisah, bukan overwrite row lama
        $this->assertDatabaseCount('student_enrollments', 2);

        $oldEnrollment = StudentEnrollment::where('classroom_id', $classA->id)->firstOrFail();
        $this->assertSame($classB->id, $newEnrollment->classroom_id);
        $this->assertNull($newEnrollment->ended_at);
        $this->assertSame('2026-09-01', $newEnrollment->started_at->toDateString());

        // A-06: ended_at lama berakhir sehari sebelum tanggal transfer (tidak overlap)
        $this->assertSame('2026-08-31', $oldEnrollment->ended_at->toDateString());

        // Roster date check:
        // Pada 31 Agustus: siswa di Class A, bukan Class B
        $enrollmentAugust = $student->enrollmentForDate('2026-08-31');
        $this->assertNotNull($enrollmentAugust);
        $this->assertSame($classA->id, $enrollmentAugust->classroom_id);

        // Pada 1 September: siswa di Class B, bukan Class A
        $enrollmentSept = $student->enrollmentForDate('2026-09-01');
        $this->assertNotNull($enrollmentSept);
        $this->assertSame($classB->id, $enrollmentSept->classroom_id);
    }

    /**
     * A-11: Revert promosi tidak boleh membuka kembali enrollment pada tahun ajaran yang sudah closed.
     */
    public function test_revert_promotion_skips_when_source_academic_year_is_closed(): void
    {
        $yearSource = AcademicYear::create([
            'name' => '2025/2026',
            'status' => AcademicYear::STATUS_CURRENT,
        ]);

        $yearTarget = AcademicYear::create([
            'name' => '2026/2027',
            'status' => AcademicYear::STATUS_UPCOMING,
        ]);

        $classSource = Classroom::create([
            'name' => 'X MIPA 1',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $yearSource->id,
        ]);

        $classTarget = Classroom::create([
            'name' => 'XI MIPA 1',
            'level' => '11',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $yearTarget->id,
        ]);

        $student = $this->enrollStudent($classSource, 'NIS-PROM-1', 'active', null, '2025-07-01');

        $classroomService = app(ClassroomService::class);
        $batch = $classroomService->processPromotion([
            'academic_year_id' => $yearTarget->id,
            'source_classroom_id' => $classSource->id,
            'target_classroom_id' => $classTarget->id,
            'action' => 'promote',
            'student_ids' => [$student->id],
        ], $this->admin);

        // Tutup tahun ajaran sumber (menjadi closed)
        $yearSource->update(['status' => AcademicYear::STATUS_CLOSED]);
        $yearTarget->update(['status' => AcademicYear::STATUS_CURRENT]);

        // Coba batalkan (revert) promosi
        $result = $classroomService->revertBatch($batch, $this->admin, 'Koreksi');

        // Siswa dilewati karena tahun sumber sudah closed
        $this->assertSame(0, $result['restored']);
        $this->assertSame(1, $result['missing_classroom']);

        $item = PromotionBatchItem::where('promotion_batch_id', $batch->id)->first();
        $this->assertSame(PromotionBatchItem::REVERT_STATUS_MISSING_CLASSROOM, $item->revert_status);
        $this->assertStringContainsString('closed', $item->revert_note);

        // Siswa tetap di kelas target berjalan, tidak terlempar ke enrollment closed
        $this->assertSame($classTarget->id, $student->fresh()->currentEnrollment->classroom_id);
    }
}
