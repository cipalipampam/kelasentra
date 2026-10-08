<?php

namespace App\Services\Web\Academic;

use App\Models\AcademicYear;
use App\Models\AppNotification;
use App\Models\Classroom;
use App\Models\PromotionBatch;
use App\Models\PromotionBatchItem;
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

    /**
     * Daftar rombel tahun ajaran aktif, terurut tingkat → jurusan → sesi.
     * Dipakai untuk filter dan tampilan, termasuk rombel yang sudah penuh.
     */
    public function getActiveYearClassrooms(): Collection
    {
        $academicYear = AcademicYear::activeName();

        if (! $academicYear) {
            return new Collection;
        }

        return $this->sortByLevelMajorSection(
            Classroom::query()
                ->where('academic_year', $academicYear)
                ->where('is_active', true)
                ->with(['homeroomTeacher:id,name'])
                ->withCount([
                    'students as active_students_count' => fn ($query) => $query->where('academic_status', 'active'),
                ])
                ->get()
        );
    }

    /**
     * Rombel yang masih boleh menerima siswa baru, yaitu rombel aktif pada
     * tahun ajaran aktif yang kursinya belum habis.
     *
     * $includeClassroomId menahan rombel siswa yang sedang diedit agar tetap
     * bisa dipilih walau sudah penuh atau berasal dari tahun ajaran lama.
     */
    public function getEnrollableClassrooms(?int $includeClassroomId = null): Collection
    {
        $classrooms = $this->getActiveYearClassrooms()
            ->filter(fn (Classroom $classroom) => $classroom->active_students_count < $classroom->maxStudents());

        if ($includeClassroomId && ! $classrooms->contains('id', $includeClassroomId)) {
            $current = Classroom::query()
                ->with(['homeroomTeacher:id,name'])
                ->withCount([
                    'students as active_students_count' => fn ($query) => $query->where('academic_status', 'active'),
                ])
                ->find($includeClassroomId);

            if ($current) {
                $classrooms->push($current);
            }
        }

        return $this->sortByLevelMajorSection($classrooms);
    }

    private function sortByLevelMajorSection(Collection $classrooms): Collection
    {
        $majorRank = array_flip(config('classroom.majors', []));

        return $classrooms
            ->sortBy(fn (Classroom $classroom) => [
                (string) $classroom->level,
                $majorRank[$classroom->major] ?? PHP_INT_MAX,
                (string) $classroom->section,
            ])
            ->values();
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

    /**
     * Ringkasan tiap kelompok sesi rombel (tingkat + jurusan + tahun ajaran)
     * beserta jumlah siswa aktif dan sisa kursinya.
     *
     * Dipakai form tambah rombel untuk mengusulkan nomor sesi: sesi baru
     * hanya diusulkan ketika sesi terakhir pada kelompok itu sudah penuh.
     *
     * @return array<string, list<array{section: string, active_students: int, remaining: int, is_full: bool}>>
     */
    public function getSectionOverview(): array
    {
        return Classroom::query()
            ->withCount([
                'students as active_students_count' => fn ($query) => $query->where('academic_status', 'active'),
            ])
            ->get(['id', 'level', 'major', 'academic_year', 'section'])
            ->groupBy(fn (Classroom $classroom) => $classroom->sectionKey())
            ->map(fn (Collection $classrooms) => $classrooms
                ->sortBy(fn (Classroom $classroom) => $classroom->hasNumericSection() ? (int) $classroom->section : PHP_INT_MAX)
                ->map(fn (Classroom $classroom) => [
                    'section' => (string) $classroom->section,
                    'active_students' => $classroom->active_students_count,
                    'remaining' => max(0, $classroom->maxStudents() - $classroom->active_students_count),
                    'is_full' => $classroom->active_students_count >= $classroom->maxStudents(),
                ])
                ->values()
                ->all())
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
     *
     * Aturan bisnis (kenaikan bertahap satu tingkat, jurusan tidak berubah,
     * kapasitas rombel, dan tingkat yang boleh lulus) ditegakkan di sini
     * agar berlaku untuk semua jalur pemanggil, bukan hanya form admin.
     */
    public function processPromotion(array $data, User $actor): PromotionBatch
    {
        return DB::transaction(function () use ($data, $actor) {
            $action = $data['action'];
            $sourceClass = Classroom::findOrFail($data['source_classroom_id']);
            $targetClass = ! empty($data['target_classroom_id'])
                ? Classroom::findOrFail($data['target_classroom_id'])
                : null;

            $studentIds = array_values(array_unique($data['student_ids'] ?? []));

            // Hanya siswa yang masih aktif di rombel asal yang boleh diproses.
            $students = Student::with('user')
                ->whereIn('id', $studentIds)
                ->where('classroom_id', $sourceClass->id)
                ->where('academic_status', 'active')
                ->get();

            if ($students->count() !== count($studentIds)) {
                throw ValidationException::withMessages([
                    'student_ids' => 'Sebagian siswa terpilih sudah tidak aktif di rombel asal. Muat ulang halaman lalu pilih kembali.',
                ]);
            }

            if ($students->isEmpty()) {
                throw ValidationException::withMessages([
                    'student_ids' => 'Tidak ada siswa aktif yang dapat diproses.',
                ]);
            }

            $isPromotion = $action === PromotionBatch::ACTION_PROMOTE;

            $blockReason = $isPromotion
                ? $sourceClass->promotionBlockReason($targetClass, $students->count())
                : $sourceClass->graduationBlockReason();

            if ($blockReason !== null) {
                throw ValidationException::withMessages([
                    $isPromotion ? 'target_classroom_id' : 'source_classroom_id' => $blockReason,
                ]);
            }

            $closesSourceClassroom = $sourceClass->is_active
                && $sourceClass->activeStudentCount() === $students->count();

            $batch = PromotionBatch::create([
                'action' => $action,
                'source_classroom_id' => $sourceClass->id,
                'source_classroom_name' => $sourceClass->name,
                'source_academic_year' => $sourceClass->academic_year,
                'target_classroom_id' => $targetClass?->id,
                'target_classroom_name' => $targetClass?->name,
                'target_academic_year' => $targetClass?->academic_year,
                'source_classroom_closed' => $closesSourceClassroom,
                'homeroom_teacher_id' => $closesSourceClassroom ? $sourceClass->homeroom_teacher_id : null,
                'homeroom_teacher_name' => $closesSourceClassroom ? $sourceClass->homeroomTeacher?->name : null,
                'performed_by' => $actor->id,
                'performed_by_name' => $actor->name,
                'student_count' => $students->count(),
            ]);

            $now = now();
            $itemRows = [];

            foreach ($students as $student) {
                $itemRows[] = [
                    'promotion_batch_id' => $batch->id,
                    'student_id' => $student->id,
                    'student_name' => $student->user?->name ?? 'Siswa #'.$student->id,
                    'student_nis' => $student->nis,
                    'from_classroom_id' => $student->classroom_id,
                    'from_classroom_name' => $sourceClass->name,
                    'from_grade' => $student->getRawOriginal('grade'),
                    'from_academic_status' => $student->academic_status,
                    'to_classroom_id' => $isPromotion ? $targetClass->id : null,
                    'to_academic_status' => $isPromotion ? 'active' : 'graduated',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $student->update([
                    'classroom_id' => $isPromotion ? $targetClass->id : null,
                    'grade' => $isPromotion ? $targetClass->name : $student->grade,
                    'academic_status' => $isPromotion ? 'active' : 'graduated',
                ]);

                // Dibuat satu per satu, bukan bulk insert, karena model
                // memancarkan event realtime saat notifikasi dibuat.
                if ($student->user_id) {
                    AppNotification::create([
                        'user_id' => $student->user_id,
                        'title' => $isPromotion ? 'Kenaikan Kelas Baru' : 'Kelulusan Siswa',
                        'body' => $isPromotion
                            ? "Selamat, Anda telah dipindahkan ke rombel {$targetClass->name} untuk tahun ajaran {$targetClass->academic_year}."
                            : "Status akademik Anda telah diperbarui menjadi Alumni (Lulus) dari rombel {$sourceClass->name}.",
                        'type' => 'announcement',
                        'data' => $isPromotion
                            ? [
                                'action' => 'class_promotion',
                                'classroom_id' => $targetClass->id,
                                'classroom_name' => $targetClass->name,
                            ]
                            : [
                                'action' => 'graduation',
                                'previous_classroom_id' => $sourceClass->id,
                            ],
                        'is_read' => false,
                    ]);
                }
            }

            // Jejak audit ditulis sekali untuk seluruh siswa.
            PromotionBatchItem::insert($itemRows);

            // Rombel asal yang tidak menyisakan siswa aktif lagi dianggap selesai:
            // ditutup dan wali kelasnya dilepas agar gurunya bisa dirotasi ke
            // rombel lain, termasuk pada tahun ajaran yang sama.
            if ($closesSourceClassroom) {
                $sourceClass->update([
                    'is_active' => false,
                    'homeroom_teacher_id' => null,
                ]);
            }

            return $batch;
        });
    }

    /**
     * Riwayat eksekusi promosi terbaru untuk ditampilkan pada halaman admin.
     */
    public function getPromotionHistory(int $limit = 10): Collection
    {
        return PromotionBatch::query()
            ->withCount('items')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Mengembalikan seluruh siswa pada satu batch ke kondisi sebelum diproses.
     *
     * Siswa yang datanya sudah berubah lagi setelah batch berjalan dilewati
     * agar pembatalan tidak menimpa perubahan yang lebih baru.
     *
     * @return array{restored: int, skipped: int, missing_classroom: int, reopened: bool, homeroom_teacher_name: ?string}
     */
    public function revertBatch(PromotionBatch $batch, User $actor): array
    {
        if ($batch->isReverted()) {
            throw ValidationException::withMessages([
                'promotion_batch' => 'Batch ini sudah pernah dibatalkan sebelumnya.',
            ]);
        }

        return DB::transaction(function () use ($batch, $actor) {
            $batch->load('items.student');

            $restored = 0;
            $skipped = 0;
            $missingClassroom = 0;

            foreach ($batch->items as $item) {
                $student = $item->student;

                // Rombel asal sudah dihapus, sehingga posisi asal tidak dapat
                // dipulihkan tanpa meninggalkan siswa aktif tanpa rombel.
                if ($item->from_classroom_id === null) {
                    $missingClassroom++;

                    continue;
                }

                if ($student === null
                    || (int) $student->classroom_id !== (int) $item->to_classroom_id
                    || $student->academic_status !== $item->to_academic_status) {
                    $skipped++;

                    continue;
                }

                $student->update([
                    'classroom_id' => $item->from_classroom_id,
                    'grade' => $item->from_grade,
                    'academic_status' => $item->from_academic_status,
                ]);

                if ($student->user_id) {
                    AppNotification::create([
                        'user_id' => $student->user_id,
                        'title' => 'Koreksi Data Akademik',
                        'body' => $batch->action === PromotionBatch::ACTION_PROMOTE
                            ? "Penempatan rombel Anda dikembalikan ke {$item->from_classroom_name}."
                            : 'Status kelulusan Anda dibatalkan dan dikembalikan ke status sebelumnya.',
                        'type' => 'announcement',
                        'data' => [
                            'action' => 'promotion_reverted',
                            'promotion_batch_id' => $batch->id,
                        ],
                        'is_read' => false,
                    ]);
                }

                $restored++;
            }

            $batch->update([
                'reverted_at' => now(),
                'reverted_by' => $actor->id,
                'reverted_by_name' => $actor->name,
            ]);

            $reopened = $this->reopenSourceClassroom($batch, $restored);

            return [
                'restored' => $restored,
                'skipped' => $skipped,
                'missing_classroom' => $missingClassroom,
                'reopened' => $reopened['reopened'],
                'homeroom_teacher_name' => $reopened['homeroom_teacher_name'],
            ];
        });
    }

    /**
     * Membuka kembali rombel asal yang ditutup batch ini, lengkap dengan wali
     * kelasnya.
     *
     * Wali kelas hanya dikembalikan bila kursinya belum dipakai rombel lain
     * pada tahun ajaran yang sama, agar aturan satu guru satu rombel per tahun
     * ajaran tidak dilanggar.
     *
     * @return array{reopened: bool, homeroom_teacher_name: ?string}
     */
    private function reopenSourceClassroom(PromotionBatch $batch, int $restoredStudents): array
    {
        $nothingToDo = ['reopened' => false, 'homeroom_teacher_name' => null];

        if (! $batch->source_classroom_closed || $restoredStudents === 0) {
            return $nothingToDo;
        }

        $sourceClass = $batch->source_classroom_id !== null
            ? Classroom::find($batch->source_classroom_id)
            : null;

        if ($sourceClass === null) {
            return $nothingToDo;
        }

        $homeroomTeacherId = $batch->homeroom_teacher_id;

        if ($homeroomTeacherId !== null) {
            $isTakenElsewhere = Classroom::query()
                ->where('homeroom_teacher_id', $homeroomTeacherId)
                ->where('academic_year', $sourceClass->academic_year)
                ->whereKeyNot($sourceClass->id)
                ->exists();

            if ($isTakenElsewhere) {
                $homeroomTeacherId = null;
            }
        }

        $sourceClass->update([
            'is_active' => true,
            'homeroom_teacher_id' => $homeroomTeacherId,
        ]);

        return [
            'reopened' => true,
            'homeroom_teacher_name' => $homeroomTeacherId !== null ? $batch->homeroom_teacher_name : null,
        ];
    }
}
