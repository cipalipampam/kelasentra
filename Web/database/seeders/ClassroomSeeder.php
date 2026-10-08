<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed rombel untuk seluruh tahun ajaran yang terdaftar.
 *
 * Satu tahun ajaran berisi 3 tingkat (X, XI, XII) x 3 jurusan
 * (MIPA, IPS, BAHASA) = 9 rombel. Wali kelas dirotasi per tahun ajaran
 * sehingga satu guru hanya memegang satu rombel pada tahun ajaran yang sama.
 *
 * Rombel milik tahun ajaran yang sudah diarsipkan ditandai nonaktif.
 */
class ClassroomSeeder extends Seeder
{
    public const LEVELS = ['10', '11', '12'];

    public const LEVEL_ROMAN = ['10' => 'X', '11' => 'XI', '12' => 'XII'];

    public const MAJORS = ['MIPA', 'IPS', 'BAHASA'];

    public function run(): void
    {
        $academicYears = AcademicYear::orderBy('name')->get();

        if ($academicYears->isEmpty()) {
            $this->command->warn('⚠️ Belum ada tahun ajaran. Jalankan AcademicYearSeeder lebih dulu.');

            return;
        }

        $rombelPerYear = count(self::LEVELS) * count(self::MAJORS);

        // Hanya guru berstatus aktif yang boleh menjadi wali kelas.
        $teachers = User::query()->eligibleHomeroomTeacher()->orderBy('name')->get();

        if ($teachers->count() < $rombelPerYear) {
            $this->command->warn("⚠️ Guru aktif tersedia {$teachers->count()}, dibutuhkan minimal {$rombelPerYear}. Jalankan EmployeeSeeder lebih dulu.");

            return;
        }

        $this->pruneStaleClassrooms($academicYears->pluck('name')->all());

        $total = 0;
        $teacherCount = $teachers->count();

        foreach ($academicYears as $yearIndex => $academicYear) {
            $slot = 0;

            foreach (self::LEVELS as $level) {
                foreach (self::MAJORS as $major) {
                    Classroom::updateOrCreate(
                        [
                            'name' => self::LEVEL_ROMAN[$level].'-'.$major.' 1',
                            'academic_year' => $academicYear->name,
                        ],
                        [
                            'level' => $level,
                            'major' => $major,
                            'section' => '1',
                            'homeroom_teacher_id' => $teachers[($slot + $yearIndex) % $teacherCount]->id,
                            'is_active' => $academicYear->status !== AcademicYear::STATUS_ARCHIVED,
                        ]
                    );

                    $slot++;
                    $total++;
                }
            }
        }

        $this->command->info("✅ {$total} rombel berhasil di-seed ({$academicYears->count()} tahun ajaran × {$rombelPerYear} rombel).");
    }

    /**
     * Hapus rombel pada tahun ajaran yang di-seed namun tidak termasuk komposisi standar.
     *
     * @param  list<string>  $seededYears
     */
    private function pruneStaleClassrooms(array $seededYears): void
    {
        $stale = Classroom::query()
            ->whereIn('academic_year', $seededYears)
            ->whereNotIn('name', $this->blueprints())
            ->get();

        foreach ($stale as $classroom) {
            $this->command->warn("   ↻ Menghapus rombel lama di luar komposisi standar: {$classroom->name} ({$classroom->academic_year})");
            $classroom->delete();
        }
    }

    /**
     * Nama rombel standar, tanpa memandang tahun ajaran.
     *
     * @return list<string>
     */
    private function blueprints(): array
    {
        $names = [];

        foreach (self::LEVELS as $level) {
            foreach (self::MAJORS as $major) {
                $names[] = self::LEVEL_ROMAN[$level].'-'.$major.' 1';
            }
        }

        return $names;
    }
}
