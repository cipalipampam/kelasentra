<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\Web\Academic\EnrollmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Siswa demo beserta penempatan pertamanya.
 *
 * Satu angkatan hanya dibuatkan satu enrollment (kelas saat pertama tercatat).
 * Perpindahan kelas tahun-tahun berikutnya dibentuk PromotionHistorySeeder
 * lewat proses kenaikan kelas yang sebenarnya, sehingga riwayat penempatan dan
 * batch auditnya konsisten dengan perilaku aplikasi.
 */
class StudentSeeder extends Seeder
{
    private const PASSWORD = 'siswa123';

    /**
     * @var list<array{entry: string, count: int, year: string, classroom: string}>
     */
    private const COHORTS = [
        // Angkatan 2024/2025: sekarang duduk di tingkat XII.
        ['entry' => '2024/2025', 'count' => 12, 'year' => '2024/2025', 'classroom' => 'X MIPA 1'],
        ['entry' => '2024/2025', 'count' => 12, 'year' => '2024/2025', 'classroom' => 'X MIPA 2'],
        // Angkatan 2025/2026: sekarang duduk di tingkat XI.
        ['entry' => '2025/2026', 'count' => 12, 'year' => '2025/2026', 'classroom' => 'X MIPA 1'],
        ['entry' => '2025/2026', 'count' => 12, 'year' => '2025/2026', 'classroom' => 'X MIPA 2'],
        // Angkatan 2023/2024: riwayat kelas X-nya di luar sistem, kini sudah lulus.
        ['entry' => '2023/2024', 'count' => 12, 'year' => '2024/2025', 'classroom' => 'XI MIPA 1'],
        ['entry' => '2023/2024', 'count' => 12, 'year' => '2024/2025', 'classroom' => 'XI MIPA 2'],
        // Angkatan 2026/2027: siswa baru tahun ajaran berjalan.
        ['entry' => '2026/2027', 'count' => 12, 'year' => '2026/2027', 'classroom' => 'X MIPA 1'],
        ['entry' => '2026/2027', 'count' => 12, 'year' => '2026/2027', 'classroom' => 'X MIPA 2'],
    ];

    private int $serial = 0;

    private int $identityIndex = 0;

    public function run(): void
    {
        if (Student::query()->exists()) {
            $this->command?->warn('Data siswa sudah ada; StudentSeeder dilewati.');

            return;
        }

        // Kata sandi demo sama untuk semua siswa; hash dihitung sekali agar
        // seeding tidak menghabiskan waktu pada ratusan panggilan bcrypt.
        $passwordHash = Hash::make(self::PASSWORD);

        $enrollments = app(EnrollmentService::class);
        $years = AcademicYear::query()->get()->keyBy('name');
        $classrooms = $this->classroomIndex();

        foreach (self::COHORTS as $cohort) {
            $entryYear = $years->get($cohort['entry']);
            $classroom = $classrooms->get($cohort['year'].'|'.$cohort['classroom']);

            if ($entryYear === null || $classroom === null) {
                $this->command?->warn("Angkatan {$cohort['entry']} dilewati: rombel {$cohort['classroom']} tidak ditemukan.");

                continue;
            }

            for ($i = 0; $i < $cohort['count']; $i++) {
                $student = $this->createStudent($entryYear, $passwordHash);
                $enrollments->place($student, $classroom, $this->yearStartDate($classroom));
            }
        }

        $this->createInactiveStudents($enrollments, $years, $classrooms, $passwordHash);
    }

    /**
     * Dua siswa non-aktif: satu pindah sekolah di tengah tahun 2025/2026 dan
     * satu keluar pada tahun berjalan. Keduanya tidak menyisakan enrollment
     * berjalan, sesuai aturan status akademik.
     *
     * @param  Collection<string, Classroom>  $classrooms
     * @param  Collection<string, AcademicYear>  $years
     */
    private function createInactiveStudents(EnrollmentService $enrollments, $years, $classrooms, string $passwordHash): void
    {
        $transferredYear = $years->get('2025/2026');
        $transferredClassroom = $classrooms->get('2025/2026|X MIPA 1');

        if ($transferredYear !== null && $transferredClassroom !== null) {
            $student = $this->createStudent($transferredYear, $passwordHash);
            $enrollments->place($student, $transferredClassroom, $this->yearStartDate($transferredClassroom));
            $enrollments->closeCurrent($student, '2026-01-12');
            $student->update(['academic_status' => 'transferred']);
        }

        $droppedYear = $years->get('2026/2027');
        $droppedClassroom = $classrooms->get('2026/2027|X MIPA 2');

        if ($droppedYear !== null && $droppedClassroom !== null) {
            $student = $this->createStudent($droppedYear, $passwordHash);
            $enrollments->place($student, $droppedClassroom, $this->yearStartDate($droppedClassroom));
            $enrollments->closeCurrent($student, '2026-09-18');
            $student->update(['academic_status' => 'dropped']);
        }
    }

    private function createStudent(AcademicYear $entryYear, string $passwordHash): Student
    {
        $index = $this->identityIndex++;
        $identity = DemoData::studentIdentity($index);
        $nis = $entryYear->start_year.str_pad((string) ++$this->serial, 3, '0', STR_PAD_LEFT);

        $user = User::create([
            'name' => $identity['name'],
            'email' => $nis.'@siswa.sekolah.test',
            'password' => $passwordHash,
        ]);

        $user->assignRole('siswa');

        return Student::create([
            'user_id' => $user->getKey(),
            'entry_academic_year_id' => $entryYear->getKey(),
            'nis' => $nis,
            'nisn' => '000'.$entryYear->start_year.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'gender' => $identity['gender'],
            'place_of_birth' => DemoData::birthPlace($index),
            'date_of_birth' => DemoData::studentBirthDate($entryYear->start_year, $index),
            'religion' => DemoData::religion($index),
            'address' => DemoData::address($index),
            'phone_number' => DemoData::phoneNumber($index),
        ]);
    }

    /**
     * @return Collection<string, Classroom>
     */
    private function classroomIndex()
    {
        return Classroom::query()
            ->with('academicYear')
            ->get()
            ->keyBy(fn (Classroom $classroom) => $classroom->academicYear->name.'|'.$classroom->name);
    }

    private function yearStartDate(Classroom $classroom): ?string
    {
        return $classroom->academicYear?->start_date?->toDateString();
    }
}
