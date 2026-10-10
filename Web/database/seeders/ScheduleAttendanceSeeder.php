<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\ScheduleAttendance;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Presensi mata pelajaran (absensi per jam) untuk beberapa hari terakhir.
 *
 * Setiap baris menyimpan `student_enrollment_id` dan `teacher_id` saat
 * pencatatan, sama seperti yang ditulis ClassAttendanceService, sehingga
 * riwayat absensi mapel tetap menunjuk konteks yang benar.
 *
 * Dua slot pertama tiap hari sengaja diisi: itulah yang tampil pada halaman
 * presensi kelas di aplikasi mobile, termasuk fitur prefill dari presensi harian.
 */
class ScheduleAttendanceSeeder extends Seeder
{
    private const DAYS = 2;

    private const SUBJECT_SLOTS = 2;

    private const CHUNK_SIZE = 200;

    public function run(): void
    {
        if (ScheduleAttendance::query()->exists()) {
            $this->command?->warn('Presensi mata pelajaran sudah ada; ScheduleAttendanceSeeder dilewati.');

            return;
        }

        $year = AcademicYear::currentYear();

        if ($year === null) {
            $this->command?->warn('Tahun ajaran berjalan belum ada; ScheduleAttendanceSeeder dilewati.');

            return;
        }

        $cutoff = DemoData::dataCutoff($year->end_date);
        $dates = DemoData::schoolDays($cutoff, self::DAYS, $year->start_date);
        $classrooms = Classroom::query()->where('academic_year_id', $year->getKey())->get();

        $rows = [];

        foreach ($classrooms as $classroom) {
            $students = Student::query()
                ->with('currentEnrollment')
                ->where('academic_status', 'active')
                ->whereHas('currentEnrollment', fn ($query) => $query->where('classroom_id', $classroom->getKey()))
                ->orderBy('id')
                ->get();

            if ($students->isEmpty()) {
                continue;
            }

            foreach ($dates as $dateIndex => $date) {
                $schedules = $this->schedulesFor($classroom, $date);

                foreach ($schedules as $schedule) {
                    foreach ($students->values() as $index => $student) {
                        $rows[] = $this->row($schedule, $student, $date, $index);
                    }
                }
            }
        }

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            ScheduleAttendance::insert($chunk);
        }

        $this->command?->info(sprintf('✅ Presensi mapel: %d baris untuk %d hari terakhir.', count($rows), count($dates)));
    }

    /**
     * @return Collection<int, Schedule>
     */
    private function schedulesFor(Classroom $classroom, string $date)
    {
        return Schedule::query()
            ->with('assignment')
            ->where('is_active', true)
            ->where('day_of_week', Carbon::parse($date)->dayOfWeekIso)
            ->whereHas('assignment', fn ($query) => $query->where('classroom_id', $classroom->getKey()))
            ->orderBy('start_time')
            ->limit(self::SUBJECT_SLOTS)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Schedule $schedule, Student $student, string $date, int $index): array
    {
        $status = match ($index) {
            0 => 'absent',
            1 => 'late',
            2 => 'sick',
            3 => 'permission',
            default => 'present',
        };

        $recordedAt = $date.' '.substr((string) $schedule->end_time, 0, 8);

        return [
            'schedule_id' => $schedule->getKey(),
            'student_id' => $student->getKey(),
            'student_enrollment_id' => $student->currentEnrollment?->getKey(),
            'teacher_id' => $schedule->assignment?->teacher_id,
            'attendance_date' => $date,
            'status' => $status,
            'notes' => match ($status) {
                'absent' => 'Alfa tanpa keterangan.',
                'late' => 'Datang setelah bel berbunyi.',
                'sick' => 'Sakit dengan surat dokter.',
                'permission' => 'Izin mengikuti kegiatan sekolah.',
                default => null,
            },
            'recorded_at' => $recordedAt,
            'created_at' => $recordedAt,
            'updated_at' => $recordedAt,
        ];
    }
}
