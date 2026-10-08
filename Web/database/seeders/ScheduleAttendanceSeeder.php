<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\ScheduleAttendance;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seed riwayat absensi mata pelajaran untuk seluruh siswa aktif pada
 * rombel tahun ajaran aktif.
 *
 * Distribusi status per pertemuan: 85% hadir, 5% terlambat, 5% sakit, 5% izin.
 * Penulisan memakai bulk insert agar ribuan rekaman tetap cepat.
 */
class ScheduleAttendanceSeeder extends Seeder
{
    private const HISTORY_DAYS = 7;

    private const INSERT_CHUNK = 500;

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

        $classroomIds = Classroom::query()->where('academic_year', $activeYear->name)->pluck('id');

        $studentsByClassroom = Student::query()
            ->whereIn('classroom_id', $classroomIds)
            ->where('academic_status', 'active')
            ->get(['id', 'classroom_id'])
            ->groupBy('classroom_id');

        $schedulesByClassroom = Schedule::query()
            ->whereIn('classroom_id', $classroomIds)
            ->where('is_active', true)
            ->get(['id', 'classroom_id', 'teacher_id', 'day_of_week', 'start_time'])
            ->groupBy('classroom_id');

        if ($studentsByClassroom->isEmpty() || $schedulesByClassroom->isEmpty()) {
            $this->command->warn('⚠️ Belum ada siswa aktif atau jadwal untuk di-seed absensinya.');

            return;
        }

        $rows = [];
        $now = Carbon::now();

        for ($offset = self::HISTORY_DAYS; $offset >= 0; $offset--) {
            $date = Carbon::today()->subDays($offset);

            // Skip Minggu (0)
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }

            foreach ($schedulesByClassroom as $classroomId => $schedules) {
                $students = $studentsByClassroom->get($classroomId);

                if (! $students) {
                    continue;
                }

                foreach ($schedules->where('day_of_week', $date->dayOfWeek) as $schedule) {
                    foreach ($students as $student) {
                        $attendance = $this->randomAttendance();
                        $recordedAt = Carbon::parse($date->toDateString().' '.$schedule->start_time)->addMinutes(rand(5, 20));

                        $rows[] = [
                            'schedule_id' => $schedule->id,
                            'student_id' => $student->id,
                            'teacher_id' => $schedule->teacher_id,
                            'attendance_date' => $date->toDateString(),
                            'status' => $attendance['status'],
                            'notes' => $attendance['notes'],
                            'recorded_at' => $recordedAt,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            ScheduleAttendance::query()->insertOrIgnore($chunk);
        }

        $total = ScheduleAttendance::query()->count();

        $this->command->info("✅ {$total} rekaman absensi mata pelajaran berhasil di-seed ({$studentsByClassroom->flatten()->count()} siswa aktif × ".self::HISTORY_DAYS.' hari terakhir).');
    }

    /**
     * @return array{status: string, notes: string|null}
     */
    private function randomAttendance(): array
    {
        $roll = rand(1, 100);

        return match (true) {
            $roll <= 85 => ['status' => 'present', 'notes' => null],
            $roll <= 90 => ['status' => 'late', 'notes' => 'Masuk setelah 10 menit pelajaran dimulai.'],
            $roll <= 95 => ['status' => 'sick', 'notes' => 'Sakit flu, beristirahat di UKS.'],
            default => ['status' => 'permission', 'notes' => 'Izin mengikuti lomba sekolah.'],
        };
    }
}
