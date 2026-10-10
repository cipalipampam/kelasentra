<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\PromotionBatch;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Web\Academic\ClassroomService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat kenaikan kelas dan kelulusan dua tahun terakhir.
 *
 * Prosesnya dijalankan lewat `ClassroomService::processPromotion()`, bukan
 * ditulis manual, supaya batch audit, enrollment, dan notifikasi siswa
 * dihasilkan dengan aturan yang sama seperti di panel admin. Batch inilah yang
 * tampil pada halaman riwayat promosi dan bisa dibatalkan (revert).
 *
 * Seeder ini juga menutup kronologi tahun ajaran: menutup 2025/2026,
 * menjadikan 2026/2027 berjalan, dan menyiapkan 2027/2028 sebagai tahun
 * berikutnya yang masih kosong.
 */
class PromotionHistorySeeder extends Seeder
{
    /**
     * @var list<array{action: string, year: string, source: string, target: ?string, date: string}>
     */
    private const PASSES = [
        ['action' => PromotionBatch::ACTION_PROMOTE, 'year' => '2024/2025', 'source' => 'X MIPA 1', 'target' => 'XI MIPA 1', 'date' => '2025-07-05'],
        ['action' => PromotionBatch::ACTION_PROMOTE, 'year' => '2024/2025', 'source' => 'X MIPA 2', 'target' => 'XI MIPA 2', 'date' => '2025-07-05'],
        ['action' => PromotionBatch::ACTION_PROMOTE, 'year' => '2024/2025', 'source' => 'XI MIPA 1', 'target' => 'XII MIPA 1', 'date' => '2025-07-06'],
        ['action' => PromotionBatch::ACTION_PROMOTE, 'year' => '2024/2025', 'source' => 'XI MIPA 2', 'target' => 'XII MIPA 2', 'date' => '2025-07-06'],
        ['action' => PromotionBatch::ACTION_GRADUATE, 'year' => '2025/2026', 'source' => 'XII MIPA 1', 'target' => null, 'date' => '2026-06-25'],
        ['action' => PromotionBatch::ACTION_GRADUATE, 'year' => '2025/2026', 'source' => 'XII MIPA 2', 'target' => null, 'date' => '2026-06-25'],
        ['action' => PromotionBatch::ACTION_PROMOTE, 'year' => '2025/2026', 'source' => 'X MIPA 1', 'target' => 'XI MIPA 1', 'date' => '2026-07-04'],
        ['action' => PromotionBatch::ACTION_PROMOTE, 'year' => '2025/2026', 'source' => 'X MIPA 2', 'target' => 'XI MIPA 2', 'date' => '2026-07-04'],
        ['action' => PromotionBatch::ACTION_PROMOTE, 'year' => '2025/2026', 'source' => 'XI MIPA 1', 'target' => 'XII MIPA 1', 'date' => '2026-07-05'],
        ['action' => PromotionBatch::ACTION_PROMOTE, 'year' => '2025/2026', 'source' => 'XI MIPA 2', 'target' => 'XII MIPA 2', 'date' => '2026-07-05'],
    ];

    private const NEXT_YEAR = ['name' => '2027/2028', 'start_date' => '2027-07-12', 'end_date' => '2028-06-16'];

    public function run(): void
    {
        if (PromotionBatch::query()->exists()) {
            $this->command?->warn('Riwayat promosi sudah ada; PromotionHistorySeeder dilewati.');

            return;
        }

        $actor = User::query()->role('admin')->orderBy('id')->first();

        if ($actor === null) {
            $this->command?->warn('Akun admin belum ada; PromotionHistorySeeder dilewati.');

            return;
        }

        $classroomService = app(ClassroomService::class);

        foreach (self::PASSES as $pass) {
            $source = $this->classroom($pass['year'], $pass['source']);
            $target = $pass['target'] === null
                ? null
                : $this->classroom($this->nextYearName($pass['year']), $pass['target']);

            if ($source === null || ($pass['target'] !== null && $target === null)) {
                $this->command?->warn("Rombel {$pass['source']} ({$pass['year']}) tidak ditemukan; satu proses dilewati.");

                continue;
            }

            $studentIds = Student::query()
                ->where('academic_status', 'active')
                ->whereHas('currentEnrollment', fn ($query) => $query->where('classroom_id', $source->getKey()))
                ->pluck('id')
                ->all();

            if ($studentIds === []) {
                continue;
            }

            $batch = $classroomService->processPromotion([
                'action' => $pass['action'],
                'source_classroom_id' => $source->getKey(),
                'target_classroom_id' => $target?->getKey(),
                'student_ids' => $studentIds,
            ], $actor);

            // Tanggal proses disesuaikan agar riwayat tampil kronologis.
            DB::table('promotion_batches')->where('id', $batch->getKey())->update([
                'created_at' => $pass['date'].' 09:00:00',
                'updated_at' => $pass['date'].' 09:00:00',
            ]);
        }

        $this->finalizeTimeline();
        $this->normalizeEnrollmentDates();
    }

    /**
     * Merapikan tanggal riwayat penempatan agar kronologis.
     *
     * Proses kenaikan kelas menutup enrollment lama dan membuka yang baru pada
     * tanggal eksekusi (2026), padahal secara data penempatan itu berlaku sejak
     * awal tahun ajarannya. Nilai aslinya tetap dipakai bila memang jatuh di
     * dalam rentang tahun ajaran tersebut (mis. siswa pindah di tengah tahun).
     */
    private function normalizeEnrollmentDates(): void
    {
        StudentEnrollment::query()
            ->with('academicYear')
            ->chunkById(200, function ($enrollments) {
                foreach ($enrollments as $enrollment) {
                    $year = $enrollment->academicYear;

                    if ($year?->start_date === null) {
                        continue;
                    }

                    $updates = ['started_at' => $year->start_date->toDateString()];

                    if ($enrollment->ended_at !== null
                        && $year->end_date !== null
                        && $enrollment->ended_at->greaterThan($year->end_date)) {
                        $updates['ended_at'] = $year->end_date->toDateString();
                    }

                    $enrollment->update($updates);
                }
            });
    }

    /**
     * Menutup kronologi tahun ajaran setelah semua proses historis dijalankan.
     */
    private function finalizeTimeline(): void
    {
        $closing = AcademicYear::query()->where('name', '2025/2026')->first();
        $current = AcademicYear::query()->where('name', '2026/2027')->first();

        // Periode lama harus ditutup sebelum periode baru ditetapkan agar index
        // unik "satu tahun berjalan" tidak dilanggar.
        $closing?->update(['status' => AcademicYear::STATUS_CLOSED]);
        $current?->update(['status' => AcademicYear::STATUS_CURRENT]);

        // Rombel pada tahun ajaran yang sudah selesai tidak lagi operasional.
        Classroom::query()
            ->whereHas('academicYear', fn ($query) => $query->where('status', AcademicYear::STATUS_CLOSED))
            ->update(['is_active' => false]);

        AcademicYear::firstOrCreate(['name' => self::NEXT_YEAR['name']], [
            'status' => AcademicYear::STATUS_UPCOMING,
            'start_date' => self::NEXT_YEAR['start_date'],
            'end_date' => self::NEXT_YEAR['end_date'],
        ]);
    }

    private function classroom(string $yearName, string $classroomName): ?Classroom
    {
        return Classroom::query()
            ->where('name', $classroomName)
            ->whereHas('academicYear', fn ($query) => $query->where('name', $yearName))
            ->first();
    }

    private function nextYearName(string $yearName): string
    {
        $start = (int) substr($yearName, 0, 4);

        return ($start + 1).'/'.($start + 2);
    }
}
