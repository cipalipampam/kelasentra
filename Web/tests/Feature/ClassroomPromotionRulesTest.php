<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\PromotionBatch;
use App\Models\PromotionBatchItem;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan kenaikan kelas massal: naik bertahap satu tingkat, jurusan tetap,
 * kelulusan hanya untuk tingkat XII, serta jejak audit dan pembatalannya.
 */
class ClassroomPromotionRulesTest extends TestCase
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

    private function createClassroom(array $attributes = []): Classroom
    {
        return Classroom::create(array_merge([
            'name' => 'X MIPA 1',
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $this->yearId('2026/2027'),
            'is_active' => true,
        ], $attributes));
    }

    private function createStudentIn(Classroom $classroom, int $index, string $status = 'active'): Student
    {
        return $this->enrollStudent($classroom, 'S'.$classroom->id.'-'.$index, $status);
    }

    private function createTeacher(string $name = 'Ibu Ratna Permata'): User
    {
        $teacher = User::factory()->create(['name' => $name]);
        $teacher->assignRole('guru');

        return $teacher;
    }

    private function promote(User $admin, Classroom $source, ?Classroom $target, array $studentIds)
    {
        return $this->actingAs($admin)->post(route('admin.classrooms.promotion.process'), [
            'action' => 'promote',
            'source_classroom_id' => $source->id,
            'target_classroom_id' => $target?->id,
            'student_ids' => $studentIds,
        ]);
    }

    private function graduate(User $admin, Classroom $source, array $studentIds)
    {
        return $this->actingAs($admin)->post(route('admin.classrooms.promotion.process'), [
            'action' => 'graduate',
            'source_classroom_id' => $source->id,
            'student_ids' => $studentIds,
        ]);
    }

    // ── Kenaikan bertahap satu tingkat ──────────────────────────────────────

    public function test_promotion_cannot_skip_a_level(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XII MIPA 1', 'level' => '12', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])
            ->assertSessionHasErrors('target_classroom_id');

        $this->assertSame($source->id, $student->fresh()->classroom_id);
    }

    public function test_promotion_cannot_move_backwards(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])
            ->assertSessionHasErrors('target_classroom_id');
    }

    public function test_level_xii_classroom_cannot_be_promoted(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'XII MIPA 1', 'level' => '12', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])
            ->assertSessionHasErrors('target_classroom_id');
    }

    public function test_staged_promotion_is_accepted(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XII MIPA 1', 'level' => '12', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame($target->id, $student->classroom_id);
        $this->assertSame($target->name, $student->grade);
    }

    // ── Jurusan tidak boleh berpindah ───────────────────────────────────────

    public function test_major_cannot_change_during_promotion(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI IPS 1', 'level' => '11', 'major' => 'IPS', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])
            ->assertSessionHasErrors('target_classroom_id');
    }

    public function test_class_x_without_major_may_choose_any_major(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X BAHASA 1', 'level' => '10', 'major' => null, 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI IPS 1', 'level' => '11', 'major' => 'IPS', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])->assertSessionHasNoErrors();

        $this->assertSame($target->id, $student->fresh()->classroom_id);
    }

    // ── Tahun ajaran tujuan ─────────────────────────────────────────────────

    public function test_target_academic_year_must_be_newer(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $student = $this->createStudentIn($source, 1);

        $sameYear = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2026/2027')]);
        $this->promote($admin, $source, $sameYear, [$student->id])
            ->assertSessionHasErrors('target_classroom_id');

        $olderYear = $this->createClassroom(['name' => 'XI MIPA 2', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2025/2026')]);
        $this->promote($admin, $source, $olderYear, [$student->id])
            ->assertSessionHasErrors('target_classroom_id');
    }

    // ── Kelulusan ───────────────────────────────────────────────────────────

    public function test_graduation_is_rejected_for_non_final_level(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $student = $this->createStudentIn($source, 1);

        $this->graduate($admin, $source, [$student->id])
            ->assertSessionHasErrors('source_classroom_id');

        $this->assertSame('active', $student->fresh()->academic_status);
    }

    public function test_graduation_is_accepted_for_level_xii(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'XII MIPA 1', 'level' => '12', 'academic_year_id' => $this->yearId('2026/2027')]);
        $student = $this->createStudentIn($source, 1);

        $this->graduate($admin, $source, [$student->id])->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertNull($student->classroom_id);
        $this->assertSame('graduated', $student->academic_status);
    }

    // ── Rombel tidak ditutup otomatis saat siswanya habis ───────────────────
    // "Selesai" ditentukan oleh status tahun ajaran, bukan jumlah siswa.

    public function test_graduating_every_student_keeps_the_source_classroom_active(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();
        $source = $this->createClassroom([
            'name' => 'XII MIPA 1',
            'level' => '12',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $first = $this->createStudentIn($source, 1);
        $second = $this->createStudentIn($source, 2);

        $this->graduate($admin, $source, [$first->id, $second->id])->assertSessionHasNoErrors();

        $source->refresh();
        $this->assertTrue((bool) $source->is_active);
        $this->assertSame($teacher->id, $source->homeroom_teacher_id);
        $this->assertSame(0, $source->activeStudentCount());
    }

    public function test_promoting_every_student_keeps_the_source_classroom_active(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();
        $source = $this->createClassroom([
            'name' => 'X MIPA 1',
            'level' => '10',
            'academic_year_id' => $this->yearId('2026/2027'),
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])->assertSessionHasNoErrors();

        $source->refresh();
        $this->assertTrue((bool) $source->is_active);
        $this->assertSame($teacher->id, $source->homeroom_teacher_id);
    }

    public function test_reverting_a_graduation_restores_the_enrollment(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacher();
        $source = $this->createClassroom([
            'name' => 'XII MIPA 1',
            'level' => '12',
            'homeroom_teacher_id' => $teacher->id,
        ]);
        $student = $this->createStudentIn($source, 1);

        $this->graduate($admin, $source, [$student->id])->assertSessionHasNoErrors();
        $batch = PromotionBatch::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.classrooms.promotion.revert', $batch->id))
            ->assertSessionHasNoErrors();

        $this->assertSame($source->id, $student->fresh()->classroom_id);
        $this->assertSame('active', $student->fresh()->academic_status);
    }

    // ── Tinggal kelas & tahun tujuan tepat satu langkah ──────────────────────

    public function test_a_student_may_stay_in_the_same_level_next_year(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom([
            'name' => 'X MIPA 1',
            'level' => '10',
            'section' => '1',
            'academic_year_id' => $this->yearId('2027/2028'),
        ]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])->assertSessionHasNoErrors();

        $enrollment = $student->fresh()->currentEnrollment;
        $this->assertSame($target->id, $enrollment->classroom_id);
        $this->assertSame('10', $enrollment->classroom->level);
    }

    public function test_target_year_must_be_exactly_the_next_year(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $far = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'academic_year_id' => $this->yearId('2028/2029')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $far, [$student->id])
            ->assertSessionHasErrors('target_classroom_id');

        $this->assertSame($source->id, $student->fresh()->currentEnrollment->classroom_id);
    }

    // ── Keutuhan daftar siswa ───────────────────────────────────────────────

    public function test_revert_skips_students_when_the_source_classroom_is_gone(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])->assertSessionHasNoErrors();
        $batch = PromotionBatch::firstOrFail();

        // Rombel asal tidak lagi dapat dihapus karena menyimpan histori enrollment,
        // sehingga jejak auditnya dikosongkan untuk mensimulasikan kondisi itu.
        $batch->items()->update(['from_classroom_id' => null]);

        $this->actingAs($admin)->post(route('admin.classrooms.promotion.revert', $batch->id));

        $student->refresh();
        $this->assertSame($target->id, $student->classroom_id);
        $this->assertSame('active', $student->academic_status);
    }

    public function test_promotion_into_an_inactive_classroom_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom([
            'name' => 'XI MIPA 1',
            'level' => '11',
            'academic_year_id' => $this->yearId('2027/2028'),
            'is_active' => false,
        ]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])
            ->assertSessionHasErrors('target_classroom_id');

        $this->assertSame($source->id, $student->fresh()->classroom_id);
    }

    public function test_students_must_still_be_active_in_the_source_classroom(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);

        $active = $this->createStudentIn($source, 1);
        $dropped = $this->createStudentIn($source, 2, 'dropped');

        $this->promote($admin, $source, $target, [$active->id, $dropped->id])
            ->assertSessionHasErrors('student_ids');

        $this->assertSame($source->id, $active->fresh()->classroom_id);
    }

    public function test_duplicate_student_ids_are_rejected(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id, $student->id])
            ->assertSessionHasErrors();

        $this->assertSame($source->id, $student->fresh()->classroom_id);
    }

    public function test_student_selection_cannot_exceed_classroom_capacity(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);

        $ids = [];
        for ($i = 0; $i <= Classroom::studentCapacity(); $i++) {
            $ids[] = $this->createStudentIn($source, $i)->id;
        }

        $this->promote($admin, $source, $target, $ids)
            ->assertSessionHasErrors('student_ids');
    }

    // ── Jejak audit ─────────────────────────────────────────────────────────

    public function test_promotion_is_recorded_as_an_audit_batch(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])->assertSessionHasNoErrors();

        $batch = PromotionBatch::query()->latest('id')->firstOrFail();

        $this->assertSame('promote', $batch->action);
        $this->assertSame($source->name, $batch->source_classroom_name);
        $this->assertSame($target->name, $batch->target_classroom_name);
        $this->assertSame($admin->name, $batch->performed_by_name);
        $this->assertSame(1, $batch->student_count);
        $this->assertFalse($batch->isReverted());

        $this->assertDatabaseHas('promotion_batch_items', [
            'promotion_batch_id' => $batch->id,
            'student_id' => $student->id,
            'from_classroom_id' => $source->id,
            'from_academic_status' => 'active',
            'to_classroom_id' => $target->id,
            'to_academic_status' => 'active',
        ]);
    }

    // ── Pembatalan batch ────────────────────────────────────────────────────

    public function test_reverting_a_batch_restores_previous_state(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $studentA = $this->createStudentIn($source, 1);
        $studentB = $this->createStudentIn($source, 2);

        $this->promote($admin, $source, $target, [$studentA->id, $studentB->id])->assertSessionHasNoErrors();

        $batch = PromotionBatch::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.classrooms.promotion.revert', $batch->id))
            ->assertRedirect(route('admin.classrooms.promotion'))
            ->assertSessionHas('success');

        foreach ([$studentA, $studentB] as $student) {
            $student->refresh();
            $this->assertSame($source->id, $student->classroom_id);
            $this->assertSame($source->name, $student->grade);
            $this->assertSame('active', $student->academic_status);
        }

        $batch->refresh();
        $this->assertTrue($batch->isReverted());
        $this->assertSame($admin->name, $batch->reverted_by_name);
    }

    public function test_revert_skips_students_changed_since_the_batch(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $elsewhere = $this->createClassroom(['name' => 'XI IPS 1', 'level' => '11', 'major' => 'IPS', 'academic_year_id' => $this->yearId('2027/2028')]);

        $studentA = $this->createStudentIn($source, 1);
        $studentB = $this->createStudentIn($source, 2);

        $this->promote($admin, $source, $target, [$studentA->id, $studentB->id])->assertSessionHasNoErrors();

        // Siswa B dipindahkan lagi setelah batch berjalan.
        StudentEnrollment::query()
            ->where('student_id', $studentB->id)
            ->whereNull('ended_at')
            ->update(['classroom_id' => $elsewhere->id]);

        $batch = PromotionBatch::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.classrooms.promotion.revert', $batch->id));

        $this->assertSame($source->id, $studentA->fresh()->classroom_id);
        $this->assertSame($elsewhere->id, $studentB->fresh()->classroom_id);
    }

    public function test_batch_cannot_be_reverted_twice(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id])->assertSessionHasNoErrors();

        $batch = PromotionBatch::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.classrooms.promotion.revert', $batch->id));
        $this->actingAs($admin)->post(route('admin.classrooms.promotion.revert', $batch->id))
            ->assertSessionHasErrors('promotion_batch');
    }

    public function test_promotion_history_is_listed_on_the_page(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'X MIPA 1', 'level' => '10', 'academic_year_id' => $this->yearId('2026/2027')]);
        $target = $this->createClassroom(['name' => 'XI MIPA 1', 'level' => '11', 'major' => 'MIPA', 'academic_year_id' => $this->yearId('2027/2028')]);
        $student = $this->createStudentIn($source, 1);

        $this->promote($admin, $source, $target, [$student->id]);

        $this->actingAs($admin)
            ->get(route('admin.classrooms.promotion'))
            ->assertOk()
            ->assertSee('Riwayat Eksekusi Terakhir')
            ->assertSee($source->name)
            ->assertSee($target->name)
            ->assertSee('Batalkan');
    }

    public function test_batch_items_are_kept_in_sync_with_batch_count(): void
    {
        $admin = $this->createAdmin();
        $source = $this->createClassroom(['name' => 'XII MIPA 1', 'level' => '12', 'academic_year_id' => $this->yearId('2026/2027')]);
        $studentA = $this->createStudentIn($source, 1);
        $studentB = $this->createStudentIn($source, 2);

        $this->graduate($admin, $source, [$studentA->id, $studentB->id])->assertSessionHasNoErrors();

        $batch = PromotionBatch::query()->latest('id')->firstOrFail();

        $this->assertSame(2, $batch->student_count);
        $this->assertSame(2, PromotionBatchItem::query()->where('promotion_batch_id', $batch->id)->count());
        $this->assertSame(2, $batch->items()->count());
    }
}
