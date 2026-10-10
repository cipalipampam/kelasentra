<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Rombel untuk tiga tahun ajaran terakhir: dua angkatan paralel di setiap
 * tingkat, dengan wali kelas yang berbeda tiap tahun ajaran.
 *
 * `is_active` dibiarkan menyala karena saat seeder berjalan rombel-rombel ini
 * masih dipakai proses kenaikan kelas. PromotionHistorySeeder mematikannya
 * untuk tahun ajaran yang sudah selesai.
 */
class ClassroomSeeder extends Seeder
{
    /** @var list<string> */
    private const YEARS = ['2024/2025', '2025/2026', '2026/2027'];

    /** @var list<array{name: string, level: string, major: ?string, section: string}> */
    private const CLASSROOMS = [
        ['name' => 'X MIPA 1', 'level' => Classroom::LEVEL_X, 'major' => null, 'section' => '1'],
        ['name' => 'X MIPA 2', 'level' => Classroom::LEVEL_X, 'major' => null, 'section' => '2'],
        ['name' => 'XI MIPA 1', 'level' => Classroom::LEVEL_XI, 'major' => 'MIPA', 'section' => '1'],
        ['name' => 'XI MIPA 2', 'level' => Classroom::LEVEL_XI, 'major' => 'MIPA', 'section' => '2'],
        ['name' => 'XII MIPA 1', 'level' => Classroom::LEVEL_XII, 'major' => 'MIPA', 'section' => '1'],
        ['name' => 'XII MIPA 2', 'level' => Classroom::LEVEL_XII, 'major' => 'MIPA', 'section' => '2'],
    ];

    public function run(): void
    {
        $years = AcademicYear::query()->whereIn('name', self::YEARS)->get()->keyBy('name');
        $homeroomCandidates = User::query()
            ->role('guru')
            ->eligibleHomeroomTeacher()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($homeroomCandidates === []) {
            $this->command?->warn('Tidak ada guru aktif; rombel dibuat tanpa wali kelas.');

            return;
        }

        foreach (self::YEARS as $yearIndex => $yearName) {
            $year = $years->get($yearName);

            if ($year === null) {
                continue;
            }

            foreach (self::CLASSROOMS as $classroomIndex => $data) {
                Classroom::updateOrCreate(
                    ['name' => $data['name'], 'academic_year_id' => $year->getKey()],
                    [
                        'level' => $data['level'],
                        'major' => $data['major'],
                        'section' => $data['section'],
                        // Rotasi memastikan satu guru hanya menjadi wali kelas
                        // satu rombel pada tahun ajaran yang sama.
                        'homeroom_teacher_id' => $homeroomCandidates[($yearIndex * 2 + $classroomIndex) % count($homeroomCandidates)],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
