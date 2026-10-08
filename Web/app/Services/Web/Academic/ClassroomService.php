<?php

namespace App\Services\Web\Academic;

use App\Models\AppNotification;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassroomService
{
    public function getPaginatedClassrooms(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Classroom::with(['homeroomTeacher'])->withCount([
            'students',
            'students as active_students_count' => fn ($studentQuery) => $studentQuery->where('academic_status', 'active'),
        ]);

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (! empty($filters['major'])) {
            $query->where('major', $filters['major']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('academic_year', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('level')->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function getAllActiveClassrooms(): Collection
    {
        return Classroom::where('is_active', true)
            ->withCount([
                'students as active_students_count' => fn ($query) => $query->where('academic_status', 'active'),
            ])
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    public function getEligibleHomeroomTeachers(): Collection
    {
        // Hanya guru berstatus aktif yang boleh ditunjuk sebagai wali kelas.
        return User::query()
            ->eligibleHomeroomTeacher()
            ->with('employee:id,user_id,employment_status')
            ->orderBy('name')
            ->get();
    }

    /**
     * Peta guru => daftar tahun ajaran tempat ia sudah menjadi wali kelas.
     *
     * @return array<int, list<string>>
     */
    public function getHomeroomAssignments(): array
    {
        return Classroom::query()
            ->whereNotNull('homeroom_teacher_id')
            ->get(['homeroom_teacher_id', 'academic_year'])
            ->groupBy('homeroom_teacher_id')
            ->map(fn ($rows) => $rows->pluck('academic_year')->unique()->values()->all())
            ->all();
    }

    public function getStudentsByClassroom(int $classroomId, ?string $status = 'active'): Collection
    {
        $query = Student::with('user')
            ->where('classroom_id', $classroomId);

        if ($status !== null) {
            $query->where('academic_status', $status);
        }

        return $query->get()->sortBy(fn ($student) => $student->user?->name ?? $student->nis)->values();
    }

    public function createClassroom(array $data): Classroom
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return Classroom::create($data);
    }

    public function updateClassroom(Classroom $classroom, array $data): bool
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $classroom->update($data);
    }

    public function deleteClassroom(Classroom $classroom): bool
    {
        if ($classroom->students()->count() > 0) {
            return false;
        }

        return (bool) $classroom->delete();
    }

    /**
     * Memproses kenaikan kelas massal atau kelulusan siswa terpilih.
     */
    public function processPromotion(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $action = $data['action'];
            $sourceClassroomId = (int) $data['source_classroom_id'];
            $targetClassroomId = ! empty($data['target_classroom_id']) ? (int) $data['target_classroom_id'] : null;
            $studentIds = $data['student_ids'] ?? [];

            $sourceClass = Classroom::findOrFail($sourceClassroomId);
            $targetClass = $targetClassroomId ? Classroom::findOrFail($targetClassroomId) : null;

            // Ambil siswa yang valid dan memang berada di sourceClassroom
            $students = Student::with('user')
                ->whereIn('id', $studentIds)
                ->where('classroom_id', $sourceClassroomId)
                ->get();

            // Kapasitas rombel tujuan tidak boleh terlampaui.
            if ($action === 'promote' && $targetClass && $students->isNotEmpty() && ! $targetClass->hasRoomFor($students->count())) {
                throw ValidationException::withMessages([
                    'target_classroom_id' => sprintf(
                        'Kelas tujuan %s hanya menyisakan %d kursi, sedangkan %d siswa akan dipindahkan. Kapasitas maksimal %d siswa per rombel.',
                        $targetClass->name,
                        $targetClass->remainingCapacity(),
                        $students->count(),
                        $targetClass->maxStudents(),
                    ),
                ]);
            }

            $processedCount = 0;

            foreach ($students as $student) {
                if ($action === 'promote' && $targetClass) {
                    $student->update([
                        'classroom_id' => $targetClass->id,
                        'grade' => $targetClass->name,
                        'academic_status' => 'active',
                    ]);

                    if ($student->user_id) {
                        AppNotification::create([
                            'user_id' => $student->user_id,
                            'title' => 'Kenaikan Kelas Baru',
                            'body' => "Selamat, Anda telah dipindahkan ke rombel {$targetClass->name} untuk tahun ajaran {$targetClass->academic_year}.",
                            'type' => 'announcement',
                            'data' => [
                                'action' => 'class_promotion',
                                'classroom_id' => $targetClass->id,
                                'classroom_name' => $targetClass->name,
                            ],
                            'is_read' => false,
                        ]);
                    }
                } elseif ($action === 'graduate') {
                    $student->update([
                        'classroom_id' => null,
                        'academic_status' => 'graduated',
                    ]);

                    if ($student->user_id) {
                        AppNotification::create([
                            'user_id' => $student->user_id,
                            'title' => 'Kelulusan Siswa',
                            'body' => "Status akademik Anda telah diperbarui menjadi Alumni (Lulus) dari rombel {$sourceClass->name}.",
                            'type' => 'announcement',
                            'data' => [
                                'action' => 'graduation',
                                'previous_classroom_id' => $sourceClass->id,
                            ],
                            'is_read' => false,
                        ]);
                    }
                }

                $processedCount++;
            }

            return [
                'action' => $action,
                'source_class' => $sourceClass->name,
                'target_class' => $targetClass?->name ?? 'Alumni / Lulus',
                'processed_count' => $processedCount,
            ];
        });
    }
}
