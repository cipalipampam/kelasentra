<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seed siswa untuk tahun ajaran aktif, plus alumni lulusan tahun ajaran
 * terarsip terakhir.
 *
 * Model data hanya menyimpan satu `classroom_id` per siswa (bukan riwayat
 * per tahun), sehingga siswa aktif ditempatkan pada rombel tahun ajaran
 * aktif dan angkatan yang sudah lulus disimpan sebagai alumni
 * (academic_status = graduated, tanpa rombel).
 *
 * Nama dibangkitkan secara deterministik dari kumpulan nama, sehingga
 * mengulang seeder menghasilkan data yang sama.
 */
class StudentSeeder extends Seeder
{
    public const STUDENTS_PER_CLASSROOM = 12;

    private const FIRST_NAME_POOL_SIZE = 24;

    private const MALE_FIRST_NAMES = [
        'Ahmad', 'Budi', 'Dimas', 'Fajar', 'Rizki', 'Bagas',
        'Reza', 'Galih', 'Alif', 'Farhan', 'Rizal', 'Kevin',
        'Bayu', 'Yoga', 'Arif', 'Ilham', 'Naufal', 'Rian',
        'Satria', 'Wahyu', 'Yusuf', 'Zaki', 'Daffa', 'Bintang',
    ];

    private const FEMALE_FIRST_NAMES = [
        'Siti', 'Dewi', 'Nabila', 'Aulia', 'Clarissa', 'Jessica',
        'Tiara', 'Syifa', 'Annisa', 'Ratna', 'Melati', 'Putri',
        'Ayu', 'Intan', 'Laras', 'Maya', 'Nadia', 'Rahma',
        'Salma', 'Tania', 'Vina', 'Wulan', 'Zahra', 'Kirana',
    ];

    private const LAST_NAMES = [
        'Pratama', 'Santoso', 'Anggraeni', 'Maulana', 'Azzahra', 'Ramadhan',
        'Sanjaya', 'Stephanie', 'Pamungkas', 'Tanuwijaya', 'Fahlevi', 'Andini',
        'Syahputra', 'Maharani', 'Hapsari', 'Rakasiwi', 'Firmansyah', 'Wardani',
        'Hidayat', 'Wandira', 'Kusuma', 'Permata', 'Wijaya', 'Nugroho',
        'Saputra', 'Setiawan', 'Lestari', 'Hartono', 'Susanto', 'Wibowo',
        'Handayani', 'Purnama',
    ];

    private const CITIES = [
        'Jakarta', 'Bandung', 'Surabaya', 'Semarang', 'Yogyakarta', 'Medan',
        'Malang', 'Bogor', 'Depok', 'Bekasi', 'Tangerang', 'Palembang',
        'Makassar', 'Denpasar', 'Padang', 'Surakarta',
    ];

    private const STREETS = [
        'Merdeka', 'Sudirman', 'Diponegoro', 'Gatot Subroto', 'Ahmad Yani',
        'Pajajaran', 'Cendrawasih', 'Melati', 'Kartini', 'Veteran',
    ];

    private const RELIGIONS = [
        'Islam', 'Islam', 'Islam', 'Islam', 'Islam', 'Islam', 'Kristen', 'Katolik',
    ];

    /**
     * Akun siswa contoh yang selalu tersedia agar kredensial demo tetap valid.
     */
    private const DEMO_STUDENTS = [
        'X-MIPA 1' => ['name' => 'Ahmad Rizki Pratama', 'email' => 'ahmad.rizki@siswa.sch.id', 'gender' => 'male'],
        'XI-MIPA 1' => ['name' => 'Dimas Arya Pamungkas', 'email' => 'dimas.arya@siswa.sch.id', 'gender' => 'male'],
        'XII-MIPA 1' => ['name' => 'Fajar Alfian Pratama', 'email' => 'fajar.alfian@siswa.sch.id', 'gender' => 'male'],
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

        $activeClassrooms = $this->orderedClassrooms($activeYear->name, null);

        if ($activeClassrooms->isEmpty()) {
            $this->command->warn("⚠️ Rombel tahun ajaran {$activeYear->name} belum ada. Jalankan ClassroomSeeder lebih dulu.");

            return;
        }

        $archivedYear = AcademicYear::query()
            ->where('status', AcademicYear::STATUS_ARCHIVED)
            ->orderByDesc('name')
            ->first();

        $roster = $this->buildRoster($activeYear, $archivedYear, $activeClassrooms);

        $this->pruneStaleStudents($roster);

        $this->persistRoster($roster);

        $activeCount = count(array_filter($roster, fn ($row) => $row['academic_status'] === 'active'));
        $alumniCount = count($roster) - $activeCount;

        $this->command->info("✅ {$activeCount} siswa aktif di {$activeClassrooms->count()} rombel ({$activeYear->name}) + {$alumniCount} alumni berhasil di-seed.");
    }

    /**
     * Susun seluruh daftar siswa aktif maupun alumni.
     *
     * @param  Collection<int, Classroom>  $activeClassrooms
     * @return list<array<string, mixed>>
     */
    private function buildRoster(AcademicYear $activeYear, ?AcademicYear $archivedYear, Collection $activeClassrooms): array
    {
        $activeStart = (int) substr($activeYear->name, 0, 4);
        $entryYearByLevel = ['10' => $activeStart, '11' => $activeStart - 1, '12' => $activeStart - 2];

        $roster = [];
        $usedEmails = [];
        $nisSequence = [];
        $phoneSequence = 0;
        $maleIndex = 0;
        $femaleIndex = 0;

        foreach ($activeClassrooms as $classroom) {
            $entryYear = $entryYearByLevel[$classroom->level] ?? $activeStart;
            $demo = self::DEMO_STUDENTS[$classroom->name] ?? null;

            if ($demo) {
                $usedEmails[] = $demo['email'];
                $roster[] = $this->makeRow(
                    $demo['name'],
                    $demo['email'],
                    $demo['gender'],
                    $classroom,
                    'active',
                    $entryYear,
                    $nisSequence,
                    $phoneSequence,
                );
            }

            $slots = self::STUDENTS_PER_CLASSROOM - ($demo ? 1 : 0);

            for ($i = 0; $i < $slots; $i++) {
                $isMale = $i % 2 === 0;
                $generated = $this->nextGeneratedName($isMale, $maleIndex, $femaleIndex, $usedEmails);

                $roster[] = $this->makeRow(
                    $generated['name'],
                    $generated['email'],
                    $isMale ? 'male' : 'female',
                    $classroom,
                    'active',
                    $entryYear,
                    $nisSequence,
                    $phoneSequence,
                );
            }
        }

        if ($archivedYear) {
            $alumniClassrooms = $this->orderedClassrooms($archivedYear->name, '12');
            $alumniEntryYear = (int) substr($archivedYear->name, 0, 4) - 2;

            foreach ($alumniClassrooms as $classroom) {
                for ($i = 0; $i < self::STUDENTS_PER_CLASSROOM; $i++) {
                    $isMale = $i % 2 === 0;
                    $generated = $this->nextGeneratedName($isMale, $maleIndex, $femaleIndex, $usedEmails, 'alumni.sch.id');

                    $roster[] = $this->makeRow(
                        $generated['name'],
                        $generated['email'],
                        $isMale ? 'male' : 'female',
                        $classroom,
                        'graduated',
                        $alumniEntryYear,
                        $nisSequence,
                        $phoneSequence,
                    );
                }
            }
        }

        return $roster;
    }

    /**
     * Rombel pada satu tahun ajaran, diurutkan agar MIPA selalu lebih dulu.
     *
     * @return Collection<int, Classroom>
     */
    private function orderedClassrooms(string $academicYear, ?string $level): Collection
    {
        $majorRank = array_flip(ClassroomSeeder::MAJORS);

        return Classroom::query()
            ->where('academic_year', $academicYear)
            ->when($level !== null, fn ($query) => $query->where('level', $level))
            ->get()
            ->sortBy(fn (Classroom $classroom) => $classroom->level.'-'.($majorRank[$classroom->major] ?? 99))
            ->values();
    }

    /**
     * @param  array<int, int>  $nisSequence
     * @return array<string, mixed>
     */
    private function makeRow(
        string $name,
        string $email,
        string $gender,
        Classroom $classroom,
        string $academicStatus,
        int $entryYear,
        array &$nisSequence,
        int &$phoneSequence,
    ): array {
        $sequence = ($nisSequence[$entryYear] ?? 0) + 1;
        $nisSequence[$entryYear] = $sequence;
        $phoneSequence++;

        // NIS diberikan saat siswa masuk (kelas X), NISN selalu 10 digit.
        $nis = $entryYear.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        return [
            'name' => $name,
            'email' => $email,
            'classroom_id' => $academicStatus === 'graduated' ? null : $classroom->id,
            'grade' => $classroom->name,
            'academic_status' => $academicStatus,
            'nis' => $nis,
            'nisn' => '000'.$nis,
            'gender' => $gender,
            'place_of_birth' => self::CITIES[$sequence % count(self::CITIES)],
            'date_of_birth' => sprintf('%04d-%02d-%02d', $entryYear - 15, (($sequence * 3) % 12) + 1, (($sequence * 7) % 27) + 1),
            'religion' => self::RELIGIONS[$sequence % count(self::RELIGIONS)],
            'phone' => '0812'.str_pad((string) $phoneSequence, 7, '0', STR_PAD_LEFT),
            'address' => 'Jl. '.self::STREETS[$sequence % count(self::STREETS)].' No.'.(($sequence % 90) + 1).', '.self::CITIES[($sequence * 5) % count(self::CITIES)],
        ];
    }

    /**
     * Bangkitkan nama unik dari kumpulan nama, dengan memastikan email belum terpakai.
     *
     * @param  list<string>  $usedEmails
     * @return array{name: string, email: string}
     */
    private function nextGeneratedName(bool $isMale, int &$maleIndex, int &$femaleIndex, array &$usedEmails, string $domain = 'siswa.sch.id'): array
    {
        $pool = $isMale ? self::MALE_FIRST_NAMES : self::FEMALE_FIRST_NAMES;

        while (true) {
            $index = $isMale ? $maleIndex++ : $femaleIndex++;

            $first = $pool[$index % self::FIRST_NAME_POOL_SIZE];
            $last = self::LAST_NAMES[intdiv($index, self::FIRST_NAME_POOL_SIZE) % count(self::LAST_NAMES)];
            $email = strtolower($first.'.'.$last).'@'.$domain;

            if (! in_array($email, $usedEmails, true)) {
                $usedEmails[] = $email;

                return ['name' => $first.' '.$last, 'email' => $email];
            }
        }
    }

    /**
     * Siswa (dan akunnya) yang tidak lagi masuk roster akan dihapus.
     *
     * @param  list<array<string, mixed>>  $roster
     */
    private function pruneStaleStudents(array $roster): void
    {
        $keepEmails = array_column($roster, 'email');

        $stale = User::query()->role('siswa')->whereNotIn('email', $keepEmails)->get();

        foreach ($stale as $user) {
            $this->command->warn("   ↻ Menghapus siswa lama di luar roster: {$user->email}");
            $user->tokens()->delete();
            $user->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $roster
     */
    private function persistRoster(array $roster): void
    {
        // Satu hash dipakai ulang agar seeding ratusan akun tetap cepat.
        $hashedPassword = Hash::make('password123');

        foreach ($roster as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            $user->name = $data['name'];

            if (! $user->exists) {
                $user->password = $hashedPassword;
            }

            $user->save();

            if (! $user->hasRole('siswa')) {
                $user->assignRole('siswa');
            }

            Student::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'classroom_id' => $data['classroom_id'],
                    'nis' => $data['nis'],
                    'nisn' => $data['nisn'],
                    'grade' => $data['grade'],
                    'academic_status' => $data['academic_status'],
                    'gender' => $data['gender'],
                    'place_of_birth' => $data['place_of_birth'],
                    'date_of_birth' => $data['date_of_birth'],
                    'religion' => $data['religion'],
                    'phone_number' => $data['phone'],
                    'address' => $data['address'],
                ]
            );
        }
    }
}
