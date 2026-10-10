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
    public function __construct(
        private readonly EnrollmentService $enrollments,
    ) {}

    public function getPaginatedClassrooms(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Classroom::with(['homeroomTeacher'])->withCount([
            'enrollments',
            'currentEnrollments as active_students_count',
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
                    ->orWhereHas('academicYear', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        // Secara default hanya tampilkan rombel dari tahun ajaran yang sedang
        // berjalan atau akan datang. Jika show_history aktif, tampilkan semua.
        if (empty($filters['show_history'])) {
            $query->whereHas('academicYear', fn ($q) => $q->whereIn('status', AcademicYear::OPERABLE_STATUSES));
        }

        return $query->orderBy('level')->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function getAllActiveClassrooms(): Collection
    {
        return Classroom::where('is_active', true)
            ->with('academicYear:id,name,start_year,status')
            ->withCount(['currentEnrollments as active_students_count'])
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    /**
     * Daftar rombel yang masih berjalan, yaitu rombel aktif pada tahun ajaran
     * aktif atau yang akan datang, terurut tingkat → jurusan → sesi → tahun.
     *
     * Tahun ajaran yang akan datang ikut disertakan karena rombelnya memang
     * sudah disiapkan sebelum tahun ajaran berganti.
     */
    public function getRunningClassrooms(): Collection
    {
        $academicYearIds = AcademicYear::operableIds();

        if ($academicYearIds === []) {
            return new Collection;
        }

        return $this->sortByLevelMajorSection(
            Classroom::query()
                ->whereIn('academic_year_id', $academicYearIds)
                ->where('is_active', true)
                ->with(['homeroomTeacher:id,name', 'academicYear:id,name,start_year,status'])
                ->withCount(['currentEnrollments as active_students_count'])
                ->get()
        );
    }

    /**
     * Seluruh rombel, termasuk tahun ajaran yang sudah ditutup, untuk filter
     * laporan historis: presensi periode lama harus tetap bisa ditelusuri.
     */
    public function getFilterableClassrooms(): Collection
    {
        return Classroom::query()
            ->with('academicYear:id,name,start_year,status')
            ->orderByDesc(
                AcademicYear::query()
                    ->select('start_year')
                    ->whereColumn('academic_years.id', 'classrooms.academic_year_id')
            )
            ->orderBy('level')
            ->orderBy('name')
            ->get();
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
        // Enrollment baru hanya untuk periode berjalan dan tepat satu periode berikutnya.
        $enrollableYearIds = collect([
            AcademicYear::currentYear()?->getKey(),
            AcademicYear::nextYear()?->getKey(),
        ])->filter()->all();

        $classrooms = $this->getRunningClassrooms()
            ->filter(fn (Classroom $classroom) => in_array($classroom->academic_year_id, $enrollableYearIds, true))
            ->filter(fn (Classroom $classroom) => $classroom->active_students_count < $classroom->maxStudents());

        if ($includeClassroomId && ! $classrooms->contains('id', $includeClassroomId)) {
            $current = Classroom::query()
                ->with(['homeroomTeacher:id,name', 'academicYear:id,name,start_year,status'])
                ->withCount(['currentEnrollments as active_students_count'])
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
                (int) ($classroom->academicYear?->start_year ?? 0),
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
            ->get(['homeroom_teacher_id', 'academic_year_id'])
            ->groupBy('homeroom_teacher_id')
            ->map(fn ($rows) => $rows->pluck('academic_year_id')->unique()->values()->all())
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
            ->withCount(['currentEnrollments as active_students_count'])
            ->get(['id', 'level', 'major', 'academic_year_id', 'section'])
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
            ->whereHas('currentEnrollment', fn ($enrollment) => $enrollment->where('classroom_id', $classroomId));

        if ($status !== null) {
            $query->where('academic_status', $status);
        }

        return $query->get()->sortBy(fn ($student) => $student->user?->name ?? $student->nis)->values();
    }

    public function createClassroom(array $data): Classroom
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        // Rombel yang tidak berjalan tidak menyimpan wali kelas.
        if (! $data['is_active']) {
            $data['homeroom_teacher_id'] = null;
        }

        return Classroom::create($data);
    }

    public function updateClassroom(Classroom $classroom, array $data): bool
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        // Menonaktifkan rombel berarti rombel itu berhenti berjalan, jadi wali
        // kelasnya dilepas agar gurunya bisa dirotasi ke rombel lain, termasuk
        // pada tahun ajaran yang sama.
        if (! $data['is_active']) {
            $data['homeroom_teacher_id'] = null;
        }

        return $classroom->update($data);
    }

    public function deleteClassroom(Classroom $classroom): bool
    {
        if ($classroom->enrollments()->count() > 0) {
            return false;
        }

        // Rombel yang dinonaktifkan melepas slot wali kelasnya: unique
        // (homeroom_teacher_id, academic_year_id) tetap berlaku untuk baris
        // ter-soft-delete, sehingga gurunya tidak boleh terikat di sana.
        $classroom->update(['homeroom_teacher_id' => null]);

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
            $students = Student::with(['user', 'currentEnrollment'])
                ->whereIn('id', $studentIds)
                ->whereHas('currentEnrollment', fn ($enrollment) => $enrollment->where('classroom_id', $sourceClass->id))
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

            $batch = PromotionBatch::create([
                'action' => $action,
                'source_classroom_id' => $sourceClass->id,
                'source_classroom_name' => $sourceClass->name,
                'source_academic_year' => $sourceClass->academicYear?->name,
                'target_classroom_id' => $targetClass?->id,
                'target_classroom_name' => $targetClass?->name,
                'target_academic_year' => $targetClass?->academicYear?->name,
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
                    'from_classroom_id' => $student->currentEnrollment?->classroom_id,
                    'from_classroom_name' => $sourceClass->name,
                    'from_grade' => $sourceClass->name,
                    'from_academic_status' => $student->academic_status,
                    'to_classroom_id' => $isPromotion ? $targetClass->id : null,
                    'to_academic_status' => $isPromotion ? 'active' : 'graduated',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($isPromotion) {
                    // Menutup enrollment rombel asal dan membuka enrollment rombel tujuan.
                    $this->enrollments->place($student, $targetClass);
                } else {
                    $this->enrollments->closeCurrent($student);
                    $student->update([
                        'academic_status' => 'graduated',
                        'graduated_at' => $now,
                        'graduation_academic_year_id' => $sourceClass->academic_year_id,
                    ]);
                }

                // Dibuat satu per satu, bukan bulk insert, karena model
                // memancarkan event realtime saat notifikasi dibuat.
                if ($student->user_id) {
                    AppNotification::create([
                        'user_id' => $student->user_id,
                        'title' => $isPromotion ? 'Kenaikan Kelas Baru' : 'Kelulusan Siswa',
                        'body' => $isPromotion
                            ? "Selamat, Anda telah dipindahkan ke rombel {$targetClass->name} untuk tahun ajaran {$targetClass->academicYear?->name}."
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
            // Rombel asal tidak ditutup: "selesai" ditentukan oleh tahun ajaran
            // yang berstatus closed, bukan oleh jumlah siswa yang tersisa.
            PromotionBatchItem::insert($itemRows);

            return $batch;
        });
    }

    /**
     * Menduplikasi struktur rombel dari satu tahun ajaran ke tahun ajaran lain.
     *
     * Hanya menyalin atribut struktural (nama, tingkat, jurusan, nomor sesi).
     * Wali kelas tidak ikut karena rotasi guru adalah keputusan manual admin.
     * Rombel yang sudah ada di tahun tujuan (nama sama) dilewati secara senyap.
     *
     * @param  int[]  $classroomIds  ID rombel yang dipilih dari TA sumber
     * @return array{created: int, skipped: int}
     */
    public function duplicateClassroomsFromYear(array $classroomIds, int $targetAcademicYearId): array
    {
        $targetYear = AcademicYear::findOrFail($targetAcademicYearId);

        if (! $targetYear->isOperable()) {
            throw ValidationException::withMessages([
                'target_academic_year_id' => 'Tahun ajaran tujuan sudah selesai dan tidak dapat menerima rombel baru.',
            ]);
        }

        $sources = Classroom::whereIn('id', $classroomIds)->get();

        // Nama rombel yang sudah ada di tahun tujuan (untuk cek duplikat).
        $existingNames = Classroom::where('academic_year_id', $targetAcademicYearId)
            ->whereNull('deleted_at')
            ->pluck('name')
            ->map(fn ($n) => strtolower($n))
            ->all();

        $created = 0;
        $skipped = 0;

        foreach ($sources as $source) {
            if (in_array(strtolower($source->name), $existingNames, true)) {
                $skipped++;
                continue;
            }

            Classroom::create([
                'name'               => $source->name,
                'level'              => $source->level,
                'major'              => $source->major,
                'section'            => $source->section,
                'academic_year_id'   => $targetAcademicYearId,
                'homeroom_teacher_id' => null,
                'is_active'          => true,
            ]);

            $existingNames[] = strtolower($source->name);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
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
     * @return array{restored: int, skipped: int, missing_classroom: int}
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

                $currentClassroomId = $student?->currentEnrollment()->value('classroom_id');

                if ($student === null
                    || (int) $currentClassroomId !== (int) $item->to_classroom_id
                    || $student->academic_status !== $item->to_academic_status) {
                    $skipped++;

                    continue;
                }

                // Status identitas dipulihkan lebih dulu agar invariant
                // "enrollment berjalan hanya untuk siswa aktif" tetap terjaga.
                $student->update([
                    'academic_status' => $item->from_academic_status,
                    'graduated_at' => null,
                    'graduation_academic_year_id' => null,
                ]);

                $fromClassroom = Classroom::find($item->from_classroom_id);

                if ($fromClassroom !== null) {
                    $this->enrollments->place($student, $fromClassroom);
                }

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

            return [
                'restored' => $restored,
                'skipped' => $skipped,
                'missing_classroom' => $missingClassroom,
            ];
        });
    }
}
