<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Seed jadwal pelajaran mingguan untuk seluruh rombel pada tahun ajaran aktif.
 *
 * Setiap rombel mendapat 4 slot KBM per hari (2 JP @45 menit) selama 5 hari
 * sekolah. Rotasi modulo (Latin square) dipakai agar tidak pernah terjadi
 * bentrok guru maupun bentrok rombel.
 */
class ScheduleSeeder extends Seeder
{
    /** Slot waktu standar KBM, menghormati istirahat 09:30-10:00 dan ishoma 11:30-13:00. */
    private const DAILY_SLOTS = [
        ['start' => '08:00:00', 'end' => '09:30:00'],
        ['start' => '10:00:00', 'end' => '11:30:00'],
        ['start' => '13:00:00', 'end' => '14:30:00'],
        ['start' => '14:30:00', 'end' => '16:00:00'],
    ];

    /** Guru pengampu utama tiap mata pelajaran. */
    private const SUBJECT_TEACHERS = [
        'MAT-W' => 'hendra.kusuma@sekolah.sch.id',
        'B-IND' => 'sari.dewantari@sekolah.sch.id',
        'B-ING' => 'ratna.permata@sekolah.sch.id',
        'FIS' => 'bambang.sutrisno@sekolah.sch.id',
        'INF' => 'antonius.wibowo@sekolah.sch.id',
        'PAI' => 'siti.khadijah@sekolah.sch.id',
        'PJK' => 'eko.prasetyo@sekolah.sch.id',
        'KIM' => 'maya.indah@sekolah.sch.id',
        'BIO' => 'rizky.ramadhan@sekolah.sch.id',
        'SEJ' => 'dewi.lestari@sekolah.sch.id',
    ];

    public function run(): void
    {
        $activeYear = AcademicYear::query()
            ->where('status', AcademicYear::STATUS_ACTIVE)
            ->orderByDesc('name')
            ->first();

        if (! $activeYear) {
            $this->command->warn('⚠️ Belum ada tahun ajaran aktif. Jalankan AcademicYearSeeder lebih dulu.');

            return;
        }

        $classrooms = $this->activeYearClassrooms($activeYear->name);

        if ($classrooms->isEmpty()) {
            $this->command->warn("⚠️ Rombel tahun ajaran {$activeYear->name} belum ada. Jalankan ClassroomSeeder lebih dulu.");

            return;
        }

        $teachingPairs = $this->resolveTeachingPairs();

        if (count($teachingPairs) < count(self::SUBJECT_TEACHERS)) {
            $this->command->warn('⚠️ Data mapel atau guru belum lengkap. Jalankan SubjectSeeder & EmployeeSeeder lebih dulu.');

            return;
        }

        // Hapus jadwal lama agar seed ulang bersih (absensi mapel ikut terhapus via cascade).
        Schedule::query()->delete();

        $capacity = count($teachingPairs);

        // Menjaga penomoran ruang kelas unik per tingkat (R.101, R.102, ...).
        $roomsPerLevel = [];

        foreach ($classrooms as $classIndex => $classroom) {
            $roomsPerLevel[$classroom->level] = ($roomsPerLevel[$classroom->level] ?? 0) + 1;
            $defaultRoom = sprintf('R.%d%02d', (int) $classroom->level - 9, $roomsPerLevel[$classroom->level]);

            $slotCounter = 0;

            for ($dayOfWeek = 1; $dayOfWeek <= 5; $dayOfWeek++) {
                foreach (self::DAILY_SLOTS as $slot) {
                    $pair = $teachingPairs[($classIndex + $slotCounter) % $capacity];

                    Schedule::create([
                        'classroom_id' => $classroom->id,
                        'subject_id' => $pair['subject']->id,
                        'teacher_id' => $pair['teacher']->id,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'room' => match ($pair['subject']->code) {
                            'INF' => 'Lab Komputer',
                            'FIS', 'KIM', 'BIO' => 'Lab Sains',
                            'PJK' => 'Lapangan Olahraga',
                            default => $defaultRoom,
                        },
                        'is_active' => true,
                    ]);

                    $slotCounter++;
                }
            }
        }

        $slots = count($classrooms) * 5 * count(self::DAILY_SLOTS);

        $this->command->info("✅ {$slots} slot KBM di-seed untuk {$classrooms->count()} rombel tahun ajaran {$activeYear->name} ({$classrooms->count()} × 20 slot, tanpa bentrok).");
    }

    /**
     * @return Collection<int, Classroom>
     */
    private function activeYearClassrooms(string $academicYear): Collection
    {
        $majorRank = array_flip(ClassroomSeeder::MAJORS);

        return Classroom::query()
            ->where('academic_year', $academicYear)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Classroom $classroom) => $classroom->level.'-'.($majorRank[$classroom->major] ?? 99))
            ->values();
    }

    /**
     * Pasangan mapel-guru yang siap dipakai pada rotasi jadwal.
     *
     * @return list<array{subject: Subject, teacher: User}>
     */
    private function resolveTeachingPairs(): array
    {
        $pairs = [];

        foreach (self::SUBJECT_TEACHERS as $code => $email) {
            $subject = Subject::query()->where('code', $code)->first();
            $teacher = User::query()->where('email', $email)->first();

            if ($subject && $teacher) {
                $pairs[] = ['subject' => $subject, 'teacher' => $teacher];
            }
        }

        return $pairs;
    }
}
