<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use App\Services\Web\Academic\TeachingAssignmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Jadwal tahun ajaran berjalan untuk seluruh rombel.
 *
 * Satu blok = 2 JP (90 menit) dan satu rombel mendapat 4 blok per hari
 * (5 hari × 4 blok = 20 blok = 40 JP per minggu).
 *
 * Tabel PATTERN adalah pola minggu untuk rombel pertama. Rombel ke-k memakai
 * pola yang digeser k sel, dan karena satu mata pelajaran diampu guru yang sama
 * di semua rombel, pergeseran itu menjamin tidak ada guru yang mengajar dua
 * rombel pada jam yang sama — tanpa perlu penjadwalan bertingkat.
 */
class ScheduleSeeder extends Seeder
{
    /**
     * Sembilan mapel muncul dua kali (jarak 10 sel sehingga tidak pernah dua
     * kali dalam satu hari), PJOK dan Seni sekali: total 20 blok.
     *
     * @var list<string>
     */
    private const PATTERN = [
        'MAT-W', 'FIS', 'BING', 'BIND', 'BIO', 'SEJ', 'PAI', 'INF', 'KIM', 'PJOK',
        'MAT-W', 'FIS', 'BING', 'BIND', 'BIO', 'SEJ', 'PAI', 'INF', 'KIM', 'SENI',
    ];

    /** @var list<array{0: string, 1: string}> */
    private const BLOCKS = [
        ['07:00:00', '08:30:00'],
        ['08:30:00', '10:00:00'],
        ['10:15:00', '11:45:00'],
        ['13:00:00', '14:30:00'],
    ];

    /** @var array<string, string> ruang khusus tiap mapel */
    private const ROOMS = [
        'FIS' => 'Lab IPA',
        'KIM' => 'Lab IPA',
        'BIO' => 'Lab IPA',
        'INF' => 'Lab Komputer',
        'SENI' => 'Ruang Seni',
        'PJOK' => 'Lapangan',
    ];

    public function run(): void
    {
        if (Schedule::query()->exists()) {
            $this->command?->warn('Jadwal sudah ada; ScheduleSeeder dilewati.');

            return;
        }

        $year = AcademicYear::currentYear();

        if ($year === null) {
            $this->command?->warn('Tahun ajaran berjalan belum ada; ScheduleSeeder dilewati.');

            return;
        }

        $classrooms = Classroom::query()
            ->where('academic_year_id', $year->getKey())
            ->orderBy('level')
            ->orderBy('section')
            ->get();

        $subjects = Subject::query()
            ->whereIn('code', array_unique(self::PATTERN))
            ->get()
            ->keyBy('code');

        $assignmentService = app(TeachingAssignmentService::class);
        $assignments = [];
        $teacherOfAssignment = [];
        $unavailable = [];

        foreach ($classrooms as $classroom) {
            foreach ($subjects as $code => $subject) {
                $teacher = $this->teacherFor($code);

                if ($teacher === null) {
                    $unavailable[$code] = true;

                    continue;
                }

                $assignment = $assignmentService->assign($classroom, $subject, $teacher);
                $assignments[$classroom->getKey().'|'.$code] = $assignment->getKey();
                $teacherOfAssignment[$assignment->getKey()] = $teacher->getKey();
            }
        }

        if ($unavailable !== []) {
            $this->command?->warn('Mapel tanpa guru eligible (dilewati): '.implode(', ', array_keys($unavailable)));
        }

        $rows = $this->buildRows($classrooms, $assignments, $teacherOfAssignment);

        Schedule::insert($rows);

        $this->command?->info(sprintf(
            '✅ Jadwal: %d slot untuk %d rombel (%d JP per rombel per minggu).',
            count($rows),
            $classrooms->count(),
            count(self::PATTERN) * 2,
        ));
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     * @param  array<string, int>  $assignments
     * @param  array<int, int>  $teacherOfAssignment
     * @return list<array<string, mixed>>
     */
    private function buildRows(Collection $classrooms, array $assignments, array $teacherOfAssignment): array
    {
        $rows = [];
        $now = now();
        $teacherBusy = [];

        foreach ($classrooms as $classroomIndex => $classroom) {
            for ($cell = 0; $cell < count(self::PATTERN); $cell++) {
                $code = self::PATTERN[($cell + $classroomIndex) % count(self::PATTERN)];
                $assignmentId = $assignments[$classroom->getKey().'|'.$code] ?? null;

                if ($assignmentId === null) {
                    continue;
                }

                [$start, $end] = self::BLOCKS[$cell % count(self::BLOCKS)];
                $day = intdiv($cell, count(self::BLOCKS)) + 1;

                $clashKey = $day.'|'.$start.'|'.$teacherOfAssignment[$assignmentId];

                if (isset($teacherBusy[$clashKey])) {
                    throw new RuntimeException("Jadwal bentrok pada hari {$day} pukul {$start} (guru yang sama dua rombel).");
                }

                $teacherBusy[$clashKey] = true;

                $rows[] = [
                    'teaching_assignment_id' => $assignmentId,
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'room' => self::ROOMS[$code] ?? $classroom->name,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }

    /**
     * Guru aktif yang mengampu mapel ini; mapel utama diprioritaskan.
     */
    private function teacherFor(string $code): ?User
    {
        return User::query()
            ->role('guru')
            ->whereHas('employee', fn ($query) => $query->where('employment_status', Employee::STATUS_ACTIVE))
            ->whereHas('subjects', fn ($query) => $query->where('subjects.code', $code))
            ->with(['subjects' => fn ($query) => $query->where('subjects.code', $code)])
            ->orderBy('id')
            ->get()
            ->sortByDesc(fn (User $teacher) => (int) (bool) $teacher->subjects->first()?->pivot->is_primary)
            ->first();
    }
}
