<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Web\Employee\EmployeeService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmployeeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->service = app(EmployeeService::class);
    }

    public function test_failed_employee_creation_leaves_no_partial_user(): void
    {
        $this->service->createEmployee($this->employeePayload([
            'email' => 'existing@sekolah.test',
            'nip' => 'EMP-001',
        ]));

        try {
            $this->service->createEmployee($this->employeePayload([
                'email' => 'rollback@sekolah.test',
                'nip' => 'EMP-001',
            ]));
            $this->fail('Expected a duplicate NIP error.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('users', ['email' => 'rollback@sekolah.test']);
        }
    }

    public function test_deleting_teacher_releases_homeroom_and_soft_deletes_assignments(): void
    {
        $subject = $this->makeSubject();
        $teacher = $this->teacher($subject);
        $classroom = $this->classroom('X MIPA 1', AcademicYear::STATUS_CURRENT, $teacher->id);
        $assignment = TeachingAssignment::create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->service->deleteEmployee($teacher);

        $this->assertDatabaseHas('classrooms', [
            'id' => $classroom->id,
            'homeroom_teacher_id' => null,
        ]);
        $this->assertSoftDeleted('teaching_assignments', ['id' => $assignment->id]);
    }

    public function test_teacher_with_only_closed_year_assignment_can_become_staff(): void
    {
        $subject = $this->makeSubject();
        $teacher = $this->teacher($subject);
        $classroom = $this->classroom('X MIPA Lama', AcademicYear::STATUS_CLOSED);
        TeachingAssignment::create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->service->updateEmployee($teacher, $this->employeePayload([
            'name' => $teacher->name,
            'email' => $teacher->email,
            'role' => 'staff',
        ]));

        $this->assertTrue($teacher->fresh()->hasRole('staff'));
        $this->assertFalse($teacher->fresh()->employee->is_teacher);
    }

    public function test_teacher_with_operable_assignment_cannot_become_staff(): void
    {
        $subject = $this->makeSubject();
        $teacher = $this->teacher($subject);
        $classroom = $this->classroom('X MIPA Aktif', AcademicYear::STATUS_CURRENT);
        TeachingAssignment::create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->updateEmployee($teacher, $this->employeePayload([
            'name' => $teacher->name,
            'email' => $teacher->email,
            'role' => 'staff',
        ]));
    }

    public function test_teacher_with_assignment_in_deleted_classroom_can_become_staff(): void
    {
        $subject = $this->makeSubject();
        $teacher = $this->teacher($subject);
        $classroom = $this->classroom('X MIPA Dihapus', AcademicYear::STATUS_CURRENT);
        TeachingAssignment::create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
        $classroom->delete();

        $this->service->updateEmployee($teacher, $this->employeePayload([
            'name' => $teacher->name,
            'email' => $teacher->email,
            'role' => 'staff',
        ]));

        $this->assertTrue($teacher->fresh()->hasRole('staff'));
    }

    public function test_cannot_remove_subject_with_operable_assignment_and_update_rolls_back(): void
    {
        $subject = $this->makeSubject('Matematika Wajib');
        $otherSubject = $this->makeSubject('Fisika');
        $teacher = $this->teacher($subject, [$subject->id, $otherSubject->id]);
        $classroom = $this->classroom('X MIPA 1', AcademicYear::STATUS_CURRENT);
        TeachingAssignment::create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
        $originalName = $teacher->name;

        try {
            $this->service->updateEmployee($teacher, $this->employeePayload([
                'name' => 'Nama Tidak Boleh Tersimpan',
                'email' => $teacher->email,
                'role' => 'guru',
                'subject_ids' => [$otherSubject->id],
                'primary_subject_id' => $otherSubject->id,
            ]));
            $this->fail('Expected active subject qualification to be protected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subject_ids', $exception->errors());
        }

        $this->assertSame($originalName, $teacher->fresh()->name);
        $this->assertCount(2, $teacher->fresh()->subjects);
    }

    /** @param list<int>|null $subjectIds */
    private function teacher(Subject $primarySubject, ?array $subjectIds = null): User
    {
        $subjectIds ??= [$primarySubject->id];

        return $this->service->createEmployee($this->employeePayload([
            'email' => 'teacher-'.uniqid().'@sekolah.test',
            'role' => 'guru',
            'subject_ids' => $subjectIds,
            'primary_subject_id' => $primarySubject->id,
        ]));
    }

    private function classroom(string $name, string $yearStatus, ?int $homeroomTeacherId = null): Classroom
    {
        $year = AcademicYear::create([
            'name' => $yearStatus === AcademicYear::STATUS_CLOSED ? '2024/2025' : '2026/2027',
            'status' => $yearStatus,
        ]);

        return Classroom::create([
            'name' => $name,
            'level' => '10',
            'major' => 'MIPA',
            'section' => '1',
            'academic_year_id' => $year->id,
            'homeroom_teacher_id' => $homeroomTeacherId,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pegawai Test',
            'email' => 'employee-'.uniqid().'@sekolah.test',
            'password' => 'password123',
            'role' => 'staff',
            'nip' => null,
        ], $overrides);
    }
}
