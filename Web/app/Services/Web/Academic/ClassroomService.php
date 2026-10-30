<?php

namespace App\Services\Web\Academic;

use App\Models\AcademicYear;
use App\Models\AppNotification;
use App\Models\Classroom;
use App\Models\PromotionBatch;
use App\Models\PromotionBatchItem;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
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
     * Menjalankan locking deterministik:
     * 1. Lock rombel sumber dan target berurutan berdasarkan ID menaik.
     * 2. Lock siswa dan enrollment aktif berurutan berdasarkan ID menaik.
     * 3. Validasi ulang status siswa dan kapasitas rombel setelah lock diperoleh.
     * 4. Tangani kemungkinan deadlock/constraint conflict dengan pesan domain.
     */
    public function processPromotion(array $data, User $actor): PromotionBatch
    {
        try {
            return DB::transaction(function () use ($data, $actor) {
                $action = $data['action'] ?? PromotionBatch::ACTION_PROMOTE;
                $sourceClassId = (int) ($data['source_classroom_id'] ?? 0);
                $targetClassId = ! empty($data['target_classroom_id']) ? (int) $data['target_classroom_id'] : null;

                // 1. Kunci rombel sumber dan tujuan dalam urutan ID menaik untuk mencegah deadlock
                $classroomIds = array_values(array_filter([$sourceClassId, $targetClassId]));
                sort($classroomIds);

                $lockedClassrooms = Classroom::with('academicYear')
                    ->whereIn('id', $classroomIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                /** @var ?Classroom $sourceClass */
                $sourceClass = $lockedClassrooms->get($sourceClassId);
                /** @var ?Classroom $targetClass */
                $targetClass = $targetClassId ? $lockedClassrooms->get($targetClassId) : null;

                if ($sourceClass === null) {
                    throw ValidationException::withMessages([
                        'source_classroom_id' => 'Kelas asal tidak ditemukan.',
                    ]);
                }

                if ($action === PromotionBatch::ACTION_PROMOTE && $targetClass === null) {
                    throw ValidationException::withMessages([
                        'target_classroom_id' => 'Kelas tujuan tidak ditemukan.',
                    ]);
                }

                $studentIds = array_values(array_unique(array_map('intval', $data['student_ids'] ?? [])));
                sort($studentIds);

                if (empty($studentIds)) {
                    throw ValidationException::withMessages([
                        'student_ids' => 'Tidak ada siswa yang dipilih untuk diproses.',
                    ]);
                }

                // 2. Kunci baris siswa dan enrollment aktif dalam urutan ID menaik
                $students = Student::with(['user', 'currentEnrollment'])
                    ->whereIn('id', $studentIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                StudentEnrollment::query()
                    ->whereIn('student_id', $studentIds)
                    ->whereNull('ended_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                // 3. Validasi ulang membership dan status siswa setelah memperoleh lock
                if ($students->count() !== count($studentIds)) {
                    throw ValidationException::withMessages([
                        'student_ids' => 'Sebagian siswa terpilih sudah tidak aktif di rombel asal. Muat ulang halaman lalu pilih kembali.',
                    ]);
                }

                foreach ($students as $student) {
                    if (! $student->isActive()) {
                        throw ValidationException::withMessages([
                            'student_ids' => "Siswa {$student->nis} berstatus '{$student->academic_status}', hanya siswa aktif yang dapat diproses.",
                        ]);
                    }

                    $currentClassroomId = $student->currentEnrollment?->classroom_id;
                    if ($currentClassroomId === null || (int) $currentClassroomId !== (int) $sourceClass->id) {
                        throw ValidationException::withMessages([
                            'student_ids' => 'Sebagian siswa terpilih sudah tidak aktif di rombel asal. Muat ulang halaman lalu pilih kembali.',
                        ]);
                    }
                }

                $isPromotion = $action === PromotionBatch::ACTION_PROMOTE;

                // 4. Validasi ulang kapasitas rombel tujuan setelah memperoleh lock
                if ($isPromotion && $targetClass !== null) {
                    $occupied = StudentEnrollment::where('classroom_id', $targetClass->id)
                        ->whereNull('ended_at')
                        ->count();

                    $remaining = $targetClass->maxStudents() - $occupied;

                    if ($remaining < $students->count()) {
                        throw ValidationException::withMessages([
                            'target_classroom_id' => "Kapasitas rombel {$targetClass->name} tidak mencukupi (tersisa {$remaining} kursi, dibutuhkan {$students->count()}). Silakan pilih rombel lain.",
                        ]);
                    }
                }

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
                        // Menutup enrollment rombel asal dan membuka enrollment rombel tujuan dengan provenance batch.
                        $this->enrollments->place($student, $targetClass, null, $batch->id);
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
        } catch (QueryException $e) {
            if ($this->isDeadlockOrLockTimeout($e)) {
                throw ValidationException::withMessages([
                    'promotion_batch' => 'Terjadi konflik antrean data (deadlock/timeout). Silakan ulangi beberapa saat lagi.',
                ]);
            }

            if ($this->isConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'promotion_batch' => 'Terjadi konflik integritas data siswa atau penempatan rombel saat memproses batch.',
                ]);
            }

            throw $e;
        }
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
                'name' => $source->name,
                'level' => $source->level,
                'major' => $source->major,
                'section' => $source->section,
                'academic_year_id' => $targetAcademicYearId,
                'homeroom_teacher_id' => null,
                'is_active' => true,
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
     * Target enrollment ditutup (ended_at diisi) dengan tetap mempertahankan
     * promotion_batch_id untuk jejak audit, sedangkan enrollment asal dibuka kembali.
     *
     * @return array{restored: int, skipped: int, missing_classroom: int}
     */
    public function revertBatch(PromotionBatch $batch, User $actor, ?string $reason = null): array
    {
        try {
            return DB::transaction(function () use ($batch, $actor, $reason) {
                /** @var PromotionBatch $lockedBatch */
                $lockedBatch = PromotionBatch::query()
                    ->whereKey($batch->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedBatch->isReverted()) {
                    throw ValidationException::withMessages([
                        'promotion_batch' => 'Batch ini sudah pernah dibatalkan sebelumnya.',
                    ]);
                }

                $lockedBatch->load('items.student');

                // Kunci rombel terkait dalam urutan ID konsisten untuk mencegah deadlock
                $classroomIds = $lockedBatch->items
                    ->flatMap(fn ($item) => [$item->from_classroom_id, $item->to_classroom_id])
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if (! empty($classroomIds)) {
                    Classroom::query()
                        ->whereIn('id', $classroomIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();
                }

                // Kunci siswa dan enrollment aktif terkait dalam urutan ID konsisten
                $studentIds = $lockedBatch->items
                    ->pluck('student_id')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if (! empty($studentIds)) {
                    Student::query()
                        ->whereIn('id', $studentIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    StudentEnrollment::query()
                        ->whereIn('student_id', $studentIds)
                        ->whereNull('ended_at')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();
                }

                $restored = 0;
                $skipped = 0;
                $missingClassroom = 0;

                foreach ($lockedBatch->items as $item) {
                    $student = $item->student;

                    // A-11: Rombel asal sudah dihapus, tidak aktif, atau tahun ajarannya sudah selesai (closed).
                    $fromClassroom = $item->from_classroom_id
                        ? Classroom::with('academicYear')->find($item->from_classroom_id)
                        : null;

                    $yearOperable = $fromClassroom?->academicYear?->isOperable() ?? false;

                    if ($fromClassroom === null || ! $fromClassroom->is_active || ! $yearOperable) {
                        $note = ($fromClassroom === null || ! $fromClassroom->is_active)
                            ? 'Rombel asal sudah dihapus atau tidak aktif.'
                            : 'Tahun ajaran rombel asal sudah selesai (closed).';
                        $item->update([
                            'revert_status' => PromotionBatchItem::REVERT_STATUS_MISSING_CLASSROOM,
                            'revert_note' => $note,
                        ]);
                        $missingClassroom++;

                        continue;
                    }

                    $currentEnrollment = $student?->currentEnrollment()->first();
                    $currentClassroomId = $currentEnrollment?->classroom_id;

                    $hasChanged = $student === null
                        || $student->academic_status !== $item->to_academic_status
                        || ($item->to_classroom_id !== null && (int) $currentClassroomId !== (int) $item->to_classroom_id)
                        || ($item->to_classroom_id === null && $currentEnrollment !== null);

                    if ($hasChanged) {
                        $item->update([
                            'revert_status' => PromotionBatchItem::REVERT_STATUS_SKIPPED,
                            'revert_note' => 'Data siswa atau status penempatan telah berubah sejak batch dijalankan.',
                        ]);
                        $skipped++;

                        continue;
                    }

                    // 1. Status identitas dipulihkan lebih dulu agar invariant
                    // "enrollment berjalan hanya untuk siswa aktif" tetap terjaga.
                    $student->update([
                        'academic_status' => $item->from_academic_status,
                        'graduated_at' => null,
                        'graduation_academic_year_id' => null,
                    ]);

                    // 2. Pulihkan enrollment sesuai jenis batch
                    if ($lockedBatch->action === PromotionBatch::ACTION_PROMOTE && $item->to_classroom_id !== null) {
                        $this->enrollments->revertPromotion($student, $fromClassroom, (int) $item->to_classroom_id, $lockedBatch->id);
                    } else {
                        $this->enrollments->revertGraduation($student, $fromClassroom);
                    }

                    // 3. Catat status audit item
                    $item->update([
                        'revert_status' => PromotionBatchItem::REVERT_STATUS_RESTORED,
                        'revert_note' => null,
                    ]);

                    if ($student->user_id) {
                        AppNotification::create([
                            'user_id' => $student->user_id,
                            'title' => 'Koreksi Data Akademik',
                            'body' => $lockedBatch->action === PromotionBatch::ACTION_PROMOTE
                                ? "Penempatan rombel Anda dikembalikan ke {$item->from_classroom_name}."
                                : 'Status kelulusan Anda dibatalkan dan dikembalikan ke status sebelumnya.',
                            'type' => 'announcement',
                            'data' => [
                                'action' => 'promotion_reverted',
                                'promotion_batch_id' => $lockedBatch->id,
                            ],
                            'is_read' => false,
                        ]);
                    }

                    $restored++;
                }

                $lockedBatch->update([
                    'reverted_at' => now(),
                    'reverted_by' => $actor->id,
                    'reverted_by_name' => $actor->name,
                    'revert_reason' => $reason,
                ]);

                return [
                    'restored' => $restored,
                    'skipped' => $skipped,
                    'missing_classroom' => $missingClassroom,
                ];
            });
        } catch (QueryException $e) {
            if ($this->isDeadlockOrLockTimeout($e)) {
                throw ValidationException::withMessages([
                    'promotion_batch' => 'Terjadi konflik antrean data (deadlock/timeout). Silakan ulangi beberapa saat lagi.',
                ]);
            }

            if ($this->isConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'promotion_batch' => 'Terjadi konflik integritas data siswa atau penempatan rombel saat membatalkan batch.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * Preview kenaikan kelas atau kelulusan siswa sebelum eksekusi (ephemeral review, tidak menulis ke database).
     *
     * @param array{
     *     action?: string,
     *     source_classroom_id?: int,
     *     target_classroom_id?: ?int,
     *     student_ids?: array<int>
     * } $data
     * @return array{
     *     is_valid: bool,
     *     action: string,
     *     source_classroom: ?array,
     *     target_classroom: ?array,
     *     summary: array{selected_count: int, valid_count: int, ineligible_count: int},
     *     students: list<array>,
     *     block_reason: ?string,
     *     warnings: list<string>
     * }
     */
    public function previewPromotion(array $data): array
    {
        $action = $data['action'] ?? PromotionBatch::ACTION_PROMOTE;
        $isPromotion = $action === PromotionBatch::ACTION_PROMOTE;

        $sourceClass = ! empty($data['source_classroom_id'])
            ? Classroom::with('academicYear')->find($data['source_classroom_id'])
            : null;

        $targetClass = (! empty($data['target_classroom_id']) && $isPromotion)
            ? Classroom::with('academicYear')->find($data['target_classroom_id'])
            : null;

        $studentIds = array_values(array_unique(array_map('intval', $data['student_ids'] ?? [])));

        $students = ! empty($studentIds)
            ? Student::with(['user', 'currentEnrollment.classroom'])
                ->whereIn('id', $studentIds)
                ->get()
                ->keyBy('id')
            : collect();

        $classifiedStudents = [];
        $validStudentIds = [];
        $ineligibleStudentIds = [];

        foreach ($studentIds as $id) {
            $student = $students->get($id);
            if ($student === null) {
                $classifiedStudents[] = [
                    'id' => $id,
                    'name' => "Siswa #{$id}",
                    'nis' => null,
                    'status' => 'ineligible',
                    'reason' => 'Siswa tidak ditemukan dalam sistem.',
                ];
                $ineligibleStudentIds[] = $id;

                continue;
            }

            if (! $student->isActive()) {
                $classifiedStudents[] = [
                    'id' => $student->id,
                    'name' => $student->user?->name ?? "Siswa #{$student->id}",
                    'nis' => $student->nis,
                    'status' => 'ineligible',
                    'reason' => "Status akademik tidak aktif ({$student->academicStatusLabel()}).",
                ];
                $ineligibleStudentIds[] = $id;

                continue;
            }

            $currentClassroomId = $student->currentEnrollment?->classroom_id;
            if ($sourceClass === null || (int) $currentClassroomId !== (int) $sourceClass->id) {
                $currentClassName = $student->currentEnrollment?->classroom?->name ?? 'tanpa rombel';
                $classifiedStudents[] = [
                    'id' => $student->id,
                    'name' => $student->user?->name ?? "Siswa #{$student->id}",
                    'nis' => $student->nis,
                    'status' => 'ineligible',
                    'reason' => "Siswa tidak berada di rombel asal (saat ini di {$currentClassName}).",
                ];
                $ineligibleStudentIds[] = $id;

                continue;
            }

            $classifiedStudents[] = [
                'id' => $student->id,
                'name' => $student->user?->name ?? "Siswa #{$student->id}",
                'nis' => $student->nis,
                'status' => 'valid',
                'reason' => null,
            ];
            $validStudentIds[] = $id;
        }

        $sourceData = $sourceClass ? [
            'id' => $sourceClass->id,
            'name' => $sourceClass->name,
            'level' => $sourceClass->level,
            'major' => $sourceClass->major,
            'academic_year' => $sourceClass->academicYear?->name,
            'current_students_count' => $sourceClass->enrollments()->whereNull('ended_at')->count(),
        ] : null;

        $targetData = null;
        $isOvercapacity = false;
        $warnings = [];

        if ($isPromotion && $targetClass) {
            $capacity = $targetClass->maxStudents();
            $occupied = StudentEnrollment::where('classroom_id', $targetClass->id)->whereNull('ended_at')->count();
            $remaining = max(0, $capacity - $occupied);
            $afterOccupied = $occupied + count($validStudentIds);
            $afterRemaining = $capacity - $afterOccupied;
            $isOvercapacity = $afterOccupied > $capacity;

            $targetData = [
                'id' => $targetClass->id,
                'name' => $targetClass->name,
                'level' => $targetClass->level,
                'major' => $targetClass->major,
                'academic_year' => $targetClass->academicYear?->name,
                'capacity' => $capacity,
                'occupied' => $occupied,
                'remaining' => $remaining,
                'after_occupied' => $afterOccupied,
                'after_remaining' => $afterRemaining,
                'is_overcapacity' => $isOvercapacity,
            ];

            if ($afterRemaining === 0 && ! $isOvercapacity) {
                $warnings[] = "Rombel tujuan {$targetClass->name} akan terisi penuh ({$capacity} siswa).";
            }
        }

        $blockReason = null;
        if ($sourceClass === null) {
            $blockReason = 'Kelas asal wajib dipilih.';
        } elseif ($isPromotion && $targetClass === null) {
            $blockReason = 'Kelas tujuan wajib dipilih untuk kenaikan kelas.';
        } elseif (count($studentIds) === 0) {
            $blockReason = 'Pilih minimal satu siswa untuk diproses.';
        } elseif ($isPromotion && $targetClass !== null) {
            $blockReason = $sourceClass->promotionBlockReason($targetClass, count($validStudentIds));
        } elseif (! $isPromotion) {
            $blockReason = $sourceClass->graduationBlockReason();
        }

        $isValid = $blockReason === null
            && ! $isOvercapacity
            && count($validStudentIds) > 0
            && count($ineligibleStudentIds) === 0;

        return [
            'is_valid' => $isValid,
            'action' => $action,
            'source_classroom' => $sourceData,
            'target_classroom' => $targetData,
            'summary' => [
                'selected_count' => count($studentIds),
                'valid_count' => count($validStudentIds),
                'ineligible_count' => count($ineligibleStudentIds),
            ],
            'students' => $classifiedStudents,
            'block_reason' => $blockReason,
            'warnings' => $warnings,
        ];
    }

    private function isDeadlockOrLockTimeout(QueryException $e): bool
    {
        $code = (string) $e->getCode();
        $message = strtolower($e->getMessage());

        return $code === '40001'
            || $code === '40P01'
            || str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout')
            || str_contains($message, 'database is locked')
            || str_contains($message, 'database table is locked');
    }

    private function isConstraintViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();
        $message = strtolower($e->getMessage());

        return $code === '23000'
            || $code === '23505'
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry');
    }
}
