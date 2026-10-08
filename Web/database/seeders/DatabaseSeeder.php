<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\ScheduleAttendance;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles (harus pertama)
        $this->call([RoleSeeder::class]);

        // 2. Admin Utama
        $admin = User::firstOrCreate(
            ['email' => 'admin@sekolah.com'],
            [
                'name' => 'Admin Utama Kelasentra',
                'password' => Hash::make('admin123'),
            ]
        );
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
        $this->command->info('✅ Admin berhasil di-seed (admin@sekolah.com / admin123).');

        // 3. Master Mata Pelajaran (10 mapel lengkap kode, rumpun, dan warna aksen)
        $this->call([SubjectSeeder::class]);

        // 4. Guru & Staff Pegawai (10 guru + 2 staff, lengkap dengan mapel yang diampu)
        $this->call([EmployeeSeeder::class]);

        // 5. Master Tahun Ajaran (3 periode: arsip, aktif, dan akan datang)
        $this->call([AcademicYearSeeder::class]);

        // 6. Master Rombel (9 rombel per tahun ajaran: X/XI/XII × MIPA/IPS/BAHASA)
        $this->call([ClassroomSeeder::class]);

        // 7. Siswa (12 siswa per rombel tahun ajaran aktif + alumni lulusan)
        $this->call([StudentSeeder::class]);

        // 8. Jadwal Pelajaran Mingguan Anti-Bentrok (Senin-Jumat, rombel tahun ajaran aktif)
        $this->call([ScheduleSeeder::class]);

        // 9. Riwayat Absensi Presensi Mata Pelajaran di Kelas
        $this->call([ScheduleAttendanceSeeder::class]);

        // 10. Riwayat Presensi Gerbang Harian (14 hari terakhir)
        $this->call([AttendanceSeeder::class]);

        // 11. Pengumuman Sekolah Aktif
        $this->call([AnnouncementSeeder::class]);

        // 12. Notifikasi Pengguna (Siswa & Guru)
        $this->call([AppNotificationSeeder::class]);

        // 13. Settings Lengkap Sekolah
        $settings = [
            // Lokasi sekolah (default koordinat Kelasentra)
            'school_lat' => '-6.200000',
            'school_long' => '106.816666',
            'school_radius' => '100',          // meter

            // Jam operasional
            'check_in_start' => '06:00',
            'check_in_end' => '07:00',        // batas tepat waktu
            'late_tolerance_minutes' => '15',           // toleransi keterlambatan
            'check_out_start' => '15:00',
            'check_out_end' => '17:00',

            // Jam presensi fallback
            'presensi_start_time' => '07:00',
            'presensi_end_time' => '09:00',

            // Profil sekolah
            'school_name' => 'SMA Kelasentra Unggulan',
            'school_address' => 'Jl. Pendidikan Karakter No. 1, Jakarta Pusat',
            'school_phone' => '021-88997766',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $this->command->info('✅ Settings lengkap sekolah berhasil di-seed.');

        $this->printSummary();
    }

    /**
     * Ringkasan data hasil seeding beserta akun demo yang siap dipakai.
     */
    private function printSummary(): void
    {
        $line = str_repeat('═', 64);
        $activeYear = AcademicYear::query()
            ->where('status', AcademicYear::STATUS_ACTIVE)
            ->orderByDesc('name')
            ->value('name');

        $this->command->info('');
        $this->command->info($line);
        $this->command->info('  🎉 SEEDING LENGKAP SELESAI — RINGKASAN DATA');
        $this->command->info($line);
        $this->command->info('  Tahun ajaran      : '.AcademicYear::query()->count().' (aktif: '.($activeYear ?? '-').')');
        $this->command->info('  Rombel            : '.Classroom::query()->count());
        $this->command->info('  Mata pelajaran    : '.Subject::query()->count());
        $this->command->info('  Jadwal pelajaran  : '.Schedule::query()->count().' slot');
        $this->command->info('  Guru              : '.User::query()->role('guru')->count());
        $this->command->info('  Staff             : '.User::query()->role('staff')->count());
        $this->command->info('  Siswa aktif       : '.Student::query()->where('academic_status', 'active')->count());
        $this->command->info('  Alumni (lulus)    : '.Student::query()->where('academic_status', 'graduated')->count());
        $this->command->info('  Absensi mapel     : '.ScheduleAttendance::query()->count().' rekaman');
        $this->command->info('  Presensi gerbang  : '.Attendance::query()->count().' rekaman');
        $this->command->info($line);
        $this->command->info('  AKUN DEMO — kata sandi: password123 (admin: admin123)');
        $this->command->info('  • Admin : admin@sekolah.com');
        $this->command->info('  • Guru  : hendra.kusuma@sekolah.sch.id');
        $this->command->info('  • Staff : agus.triyono@sekolah.sch.id');
        $this->command->info('  • Siswa : ahmad.rizki@siswa.sch.id  (X-MIPA 1)');
        $this->command->info('  • Siswa : dimas.arya@siswa.sch.id   (XI-MIPA 1)');
        $this->command->info('  • Siswa : fajar.alfian@siswa.sch.id (XII-MIPA 1)');
        $this->command->info($line);
    }
}
