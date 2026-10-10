<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Presensi gerbang harian: siswa, guru, dan tenaga kependidikan.
 *
 * Setiap baris menyimpan konteks akademik saat presensi dicatat (tahun ajaran +
 * rombel), sama seperti yang dilakukan AttendanceContext pada alur aplikasi,
 * sehingga laporan periode lama tetap benar walau siswa sudah berpindah kelas.
 *
 * Sebagian kecil presensi sengaja ditinggal tanpa persetujuan (`is_approved`
 * null) agar antrean "menunggu persetujuan" di panel admin ada isinya.
 */
class AttendanceSeeder extends Seeder
{
    private const CURRENT_YEAR_DAYS = 12;

    private const PREVIOUS_YEAR_DAYS = 3;

    private const CHUNK_SIZE = 200;

    /** Koordinat di dalam radius sekolah (lihat SettingSeeder). */
    private const SCHOOL_LAT = -6.2;

    private const SCHOOL_LONG = 106.816666;

    public function run(): void
    {
        if (Attendance::query()->exists()) {
            $this->command?->warn('Presensi sudah ada; AttendanceSeeder dilewati.');

            return;
        }

        $currentYear = AcademicYear::currentYear();

        if ($currentYear === null) {
            $this->command?->warn('Tahun ajaran berjalan belum ada; AttendanceSeeder dilewati.');

            return;
        }

        $this->seedCurrentYear($currentYear);
        $this->seedPreviousYear($currentYear);
    }

    private function seedCurrentYear(AcademicYear $year): void
    {
        $cutoff = DemoData::dataCutoff($year->end_date);
        $dates = DemoData::schoolDays($cutoff, self::CURRENT_YEAR_DAYS, $year->start_date);

        if ($dates === []) {
            return;
        }

        $studentsByClassroom = Student::query()
            ->with('currentEnrollment')
            ->where('academic_status', 'active')
            ->whereHas('currentEnrollment')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Student $student) => $student->currentEnrollment->classroom_id);

        $employees = $this->activeEmployeeUsers();
        $latest = $dates[0];
        $rows = [];

        foreach ($dates as $dateIndex => $date) {
            foreach ($studentsByClassroom as $classroomStudents) {
                foreach ($classroomStudents->values() as $indexInClassroom => $student) {
                    $rows[] = $this->studentRow(
                        (int) $student->user_id,
                        $student->currentEnrollment,
                        $date,
                        $this->studentStatus($dateIndex, $indexInClassroom, $date === $latest),
                    );
                }
            }

            foreach ($employees->values() as $index => $employee) {
                $rows[] = $this->employeeRow((int) $employee->getKey(), (int) $year->getKey(), $date, $index);
            }
        }

        $this->insert($rows);

        $this->command?->info(sprintf('✅ Presensi gerbang: %d baris untuk %d hari sekolah terakhir.', count($rows), count($dates)));
    }

    /**
     * Sampel presensi tahun ajaran sebelumnya agar filter laporan historis
     * punya data; konteksnya diambil dari enrollment tahun itu.
     */
    private function seedPreviousYear(AcademicYear $currentYear): void
    {
        $previousYear = AcademicYear::query()->where('start_year', $currentYear->start_year - 1)->first();

        if ($previousYear === null) {
            return;
        }

        $cutoff = DemoData::dataCutoff($previousYear->end_date);
        $dates = DemoData::schoolDays($cutoff, self::PREVIOUS_YEAR_DAYS, $previousYear->start_date);
        $enrollments = StudentEnrollment::query()
            ->with('student')
            ->where('academic_year_id', $previousYear->getKey())
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($dates as $dateIndex => $date) {
            foreach ($enrollments as $index => $enrollment) {
                $student = $enrollment->student;

                if ($student === null || $student->user_id === null) {
                    continue;
                }

                $rows[] = $this->studentRow((int) $student->user_id, $enrollment, $date, $this->regularStatus($dateIndex, $index));
            }
        }

        $this->insert($rows);

        $this->command?->info(sprintf('✅ Presensi %s: %d baris (laporan periode lama).', $previousYear->name, count($rows)));
    }

    /**
     * @return array{0: string, 1: bool, 2: ?bool}
     */
    private function studentStatus(int $dateIndex, int $indexInClassroom, bool $isLatestDay): array
    {
        // Hari terakhir: pola tetap agar fitur prefill presensi mapel dan
        // antrean persetujuan selalu punya contoh.
        if ($isLatestDay) {
            return match ($indexInClassroom) {
                0 => ['sick', false, true],
                1 => ['permission', false, true],
                2 => ['absent', false, true],
                3 => ['permission', false, null],
                default => $this->regularStatus($dateIndex, $indexInClassroom),
            };
        }

        return $this->regularStatus($dateIndex, $indexInClassroom);
    }

    /**
     * @return array{0: string, 1: bool, 2: ?bool}
     */
    private function regularStatus(int $dateIndex, int $index): array
    {
        return match (($dateIndex * 7 + $index) % 20) {
            0, 1 => ['sick', false, true],
            2 => ['permission', false, true],
            3 => ['absent', false, true],
            4 => ['present', true, null],
            default => ['present', false, null],
        };
    }

    /**
     * @param  array{0: string, 1: bool, 2: ?bool}  $status
     * @return array<string, mixed>
     */
    private function studentRow(int $userId, StudentEnrollment $enrollment, string $date, array $status): array
    {
        [$state, $isLate, $isApproved] = $status;
        $recordedAt = $date.' 06:'.str_pad((string) ($userId % 60), 2, '0', STR_PAD_LEFT).':00';

        return [
            'user_id' => $userId,
            'academic_year_id' => $enrollment->academic_year_id,
            'classroom_id' => $enrollment->classroom_id,
            'attendance_date' => $date,
            'recorded_at' => $recordedAt,
            'check_out_time' => $state === 'present' && $userId % 3 === 0
                ? $date.' 15:'.str_pad((string) ($userId % 30), 2, '0', STR_PAD_LEFT).':00'
                : null,
            'latitude' => self::SCHOOL_LAT + (($userId % 11) - 5) / 100000,
            'longitude' => self::SCHOOL_LONG + (($userId % 9) - 4) / 100000,
            'status' => $state,
            'is_late' => $isLate,
            'is_approved' => $isApproved,
            'notes' => $this->notesFor($state),
            'proof_image' => null,
            'created_at' => $recordedAt,
            'updated_at' => $recordedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeRow(int $userId, int $academicYearId, string $date, int $index): array
    {
        $recordedAt = $date.' 06:'.str_pad((string) ($userId % 45), 2, '0', STR_PAD_LEFT).':00';

        return [
            'user_id' => $userId,
            // Pegawai tidak punya enrollment: hanya tahun ajaran yang dibekukan.
            'academic_year_id' => $academicYearId,
            'classroom_id' => null,
            'attendance_date' => $date,
            'recorded_at' => $recordedAt,
            'check_out_time' => $index % 4 === 0 ? $date.' 15:'.str_pad((string) ($userId % 40), 2, '0', STR_PAD_LEFT).':00' : null,
            'latitude' => self::SCHOOL_LAT + (($userId % 7) - 3) / 100000,
            'longitude' => self::SCHOOL_LONG + (($userId % 6) - 3) / 100000,
            'status' => 'present',
            'is_late' => $index % 9 === 0,
            'is_approved' => null,
            'notes' => null,
            'proof_image' => null,
            'created_at' => $recordedAt,
            'updated_at' => $recordedAt,
        ];
    }

    private function notesFor(string $state): ?string
    {
        return match ($state) {
            'sick' => 'Sakit dengan surat keterangan dokter.',
            'permission' => 'Izin keperluan keluarga.',
            'absent' => 'Tanpa keterangan.',
            default => null,
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function activeEmployeeUsers(): Collection
    {
        return User::query()
            ->whereHas('employee', fn ($query) => $query->where('employment_status', Employee::STATUS_ACTIVE))
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            Attendance::insert($chunk);
        }
    }
}
