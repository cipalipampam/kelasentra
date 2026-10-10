<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Pegawai demo: guru mata pelajaran, satu guru cuti, satu guru pensiun, dan
 * tenaga kependidikan.
 *
 * Kualifikasi mapel disimpan pada pivot `teacher_subjects` (`is_primary` untuk
 * mapel utama). Guru dengan status selain `active` sengaja tidak diberi
 * kualifikasi aktif agar terlihat bagaimana aturan eligibility menyaringnya.
 */
class EmployeeSeeder extends Seeder
{
    private const TEACHER_PASSWORD = 'guru123';

    private const STAFF_PASSWORD = 'staff123';

    /**
     * @var list<array{
     *     name: string, email: string, nip: string, role: string, position: string,
     *     gender: string, status: string, birth_date: string, subjects: array<string, bool>
     * }>
     */
    private const EMPLOYEES = [
        [
            'name' => 'Ahmad Fauzi, S.Pd.', 'email' => 'ahmad.fauzi@sekolah.test', 'nip' => '198204122008011002',
            'role' => 'guru', 'position' => 'Guru Matematika', 'gender' => 'male', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1982-04-12', 'subjects' => ['MAT-W' => true],
        ],
        [
            'name' => 'Siti Nurhaliza, S.Pd.', 'email' => 'siti.nurhaliza@sekolah.test', 'nip' => '198506172009022003',
            'role' => 'guru', 'position' => 'Guru Bahasa Indonesia', 'gender' => 'female', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1985-06-17', 'subjects' => ['BIND' => true],
        ],
        [
            'name' => 'Dewi Lestari, S.S.', 'email' => 'dewi.lestari@sekolah.test', 'nip' => '198803102011012004',
            'role' => 'guru', 'position' => 'Guru Bahasa Inggris', 'gender' => 'female', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1988-03-10', 'subjects' => ['BING' => true],
        ],
        [
            'name' => 'Budi Santoso, S.Pd.', 'email' => 'budi.santoso@sekolah.test', 'nip' => '198009232006041005',
            'role' => 'guru', 'position' => 'Guru Fisika', 'gender' => 'male', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1980-09-23', 'subjects' => ['FIS' => true, 'INF' => false],
        ],
        [
            'name' => 'Rina Kartika, S.Si.', 'email' => 'rina.kartika@sekolah.test', 'nip' => '198607142010012006',
            'role' => 'guru', 'position' => 'Guru Kimia', 'gender' => 'female', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1986-07-14', 'subjects' => ['KIM' => true, 'FIS' => false],
        ],
        [
            'name' => 'Umar Hakim, S.Si.', 'email' => 'umar.hakim@sekolah.test', 'nip' => '198311082009011007',
            'role' => 'guru', 'position' => 'Guru Biologi', 'gender' => 'male', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1983-11-08', 'subjects' => ['BIO' => true],
        ],
        [
            'name' => 'Ratna Sari, S.Pd.', 'email' => 'ratna.sari@sekolah.test', 'nip' => '198704252011012008',
            'role' => 'guru', 'position' => 'Guru Sejarah', 'gender' => 'female', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1987-04-25', 'subjects' => ['SEJ' => true],
        ],
        [
            'name' => 'Yusuf Maulana, S.Ag.', 'email' => 'yusuf.maulana@sekolah.test', 'nip' => '198105192007011009',
            'role' => 'guru', 'position' => 'Guru Pendidikan Agama', 'gender' => 'male', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1981-05-19', 'subjects' => ['PAI' => true],
        ],
        [
            'name' => 'Agus Prasetyo, S.Pd.', 'email' => 'agus.prasetyo@sekolah.test', 'nip' => '198902032012011010',
            'role' => 'guru', 'position' => 'Guru PJOK', 'gender' => 'male', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1989-02-03', 'subjects' => ['PJOK' => true],
        ],
        [
            'name' => 'Indra Permana, S.Kom.', 'email' => 'indra.permana@sekolah.test', 'nip' => '199001272013011011',
            'role' => 'guru', 'position' => 'Guru Informatika', 'gender' => 'male', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1990-01-27', 'subjects' => ['INF' => true],
        ],
        [
            'name' => 'Maya Puspita, S.Sn.', 'email' => 'maya.puspita@sekolah.test', 'nip' => '199106112014012012',
            'role' => 'guru', 'position' => 'Guru Seni Budaya', 'gender' => 'female', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1991-06-11', 'subjects' => ['SENI' => true],
        ],
        [
            'name' => 'Sri Wahyuni, S.Pd.', 'email' => 'sri.wahyuni@sekolah.test', 'nip' => '197805302003012013',
            'role' => 'guru', 'position' => 'Guru Bahasa Indonesia', 'gender' => 'female', 'status' => Employee::STATUS_LEAVE,
            'birth_date' => '1978-05-30', 'subjects' => ['BIND' => false, 'SENI' => false],
        ],
        [
            'name' => 'Suharto Wibowo, S.Pd.', 'email' => 'suharto.wibowo@sekolah.test', 'nip' => '196504181990031014',
            'role' => 'guru', 'position' => 'Guru Matematika', 'gender' => 'male', 'status' => Employee::STATUS_RETIRED,
            'birth_date' => '1965-04-18', 'subjects' => ['MAT-W' => false],
        ],
        [
            'name' => 'Murni Astuti', 'email' => 'murni.astuti@sekolah.test', 'nip' => '198409152010012015',
            'role' => 'staff', 'position' => 'Kepala Tata Usaha', 'gender' => 'female', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1984-09-15', 'subjects' => [],
        ],
        [
            'name' => 'Bambang Sutrisno', 'email' => 'bambang.sutrisno@sekolah.test', 'nip' => '198602222011011016',
            'role' => 'staff', 'position' => 'Bendahara Sekolah', 'gender' => 'male', 'status' => Employee::STATUS_ACTIVE,
            'birth_date' => '1986-02-22', 'subjects' => [],
        ],
    ];

    public function run(): void
    {
        $subjectIds = Subject::query()->pluck('id', 'code');

        foreach (self::EMPLOYEES as $index => $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['role'] === 'guru' ? self::TEACHER_PASSWORD : self::STAFF_PASSWORD),
                ],
            );

            if (! $user->hasRole($data['role'])) {
                $user->assignRole($data['role']);
            }

            $user->employee()->updateOrCreate(['user_id' => $user->id], [
                'nip' => $data['nip'],
                'position' => $data['position'],
                'is_teacher' => $data['role'] === 'guru',
                'employment_status' => $data['status'],
                'gender' => $data['gender'],
                'place_of_birth' => DemoData::birthPlace($index),
                'date_of_birth' => $data['birth_date'],
                'religion' => DemoData::religion($index),
                'address' => DemoData::address($index),
                'phone_number' => DemoData::phoneNumber($index + 200),
            ]);

            $qualifications = [];

            foreach ($data['subjects'] as $code => $isPrimary) {
                if (isset($subjectIds[$code])) {
                    $qualifications[$subjectIds[$code]] = ['is_primary' => $isPrimary];
                }
            }

            $user->subjects()->sync($qualifications);
        }
    }
}
