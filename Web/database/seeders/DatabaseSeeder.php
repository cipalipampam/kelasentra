<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\PromotionBatch;
use App\Models\Schedule;
use App\Models\ScheduleAttendance;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Data awal lengkap untuk pengembangan dan demo: peran, akun, pengaturan
 * sekolah, kronologi tahun ajaran, rombel, siswa, jadwal, dan presensi.
 *
 * Urutan pemanggilan tidak boleh diubah sembarangan karena saling bergantung:
 * pegawai butuh mapel, rombel butuh wali kelas, siswa butuh rombel, riwayat
 * kenaikan kelas butuh siswa pada rombel asal, dan presensi butuh enrollment.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeding tidak memerlukan sisi realtime: siaran diarahkan ke driver
        // "null" dan queue dijalankan langsung (sync) supaya tidak ada job
        // menumpuk di tabel `jobs` yang menunggu Reverb belum berjalan.
        config([
            'broadcasting.default' => 'null',
            'queue.default' => 'sync',
        ]);

        $this->call([
            RoleSeeder::class,
            SettingSeeder::class,
            UserSeeder::class,
            AcademicYearSeeder::class,
            SubjectSeeder::class,
            EmployeeSeeder::class,
            ClassroomSeeder::class,
            StudentSeeder::class,
            PromotionHistorySeeder::class,
            ScheduleSeeder::class,
            AttendanceSeeder::class,
            ScheduleAttendanceSeeder::class,
            AnnouncementSeeder::class,
            AppNotificationSeeder::class,
        ]);

        $this->printSummary();
    }

    /**
     * Ringkasan data hasil seeding.
     */
    private function printSummary(): void
    {
        $line = str_repeat('═', 64);
        $demoTeacher = User::query()->role('guru')->orderBy('id')->first();
        $demoStudent = Student::query()->orderBy('id')->first()?->user;

        $this->command->info('');
        $this->command->info($line);
        $this->command->info('  🎉 SEEDING SELESAI — RINGKASAN DATA DEMO');
        $this->command->info($line);
        $this->command->info('  Peran (role)        : '.Role::query()->count());
        $this->command->info('  Pengguna            : '.User::query()->count());
        $this->command->info('  Pengaturan sekolah  : '.Setting::query()->count());
        $this->command->info('  Tahun ajaran        : '.AcademicYear::query()->count()
            .' (berjalan: '.(AcademicYear::currentYear()?->name ?? '-').')');
        $this->command->info('  Mata pelajaran      : '.Subject::query()->count());
        $this->command->info('  Pegawai             : '.Employee::query()->count());
        $this->command->info('  Rombel              : '.Classroom::query()->count());
        $this->command->info('  Siswa               : '.Student::query()->count()
            .' (aktif: '.Student::query()->where('academic_status', 'active')->count().')');
        $this->command->info('  Riwayat penempatan  : '.StudentEnrollment::query()->count());
        $this->command->info('  Penugasan mengajar  : '.TeachingAssignment::query()->count());
        $this->command->info('  Slot jadwal         : '.Schedule::query()->count());
        $this->command->info('  Riwayat promosi     : '.PromotionBatch::query()->count());
        $this->command->info('  Presensi gerbang    : '.Attendance::query()->count());
        $this->command->info('  Presensi mapel      : '.ScheduleAttendance::query()->count());
        $this->command->info('  Pengumuman          : '.Announcement::query()->count());
        $this->command->info('  Notifikasi aplikasi : '.AppNotification::query()->count());
        $this->command->info($line);
        $this->command->info('  AKUN DEMO — ganti kata sandi sebelum dipakai di produksi');
        $this->command->info('  • Admin : admin@sekolah.com / admin123');
        $this->command->info('  • Guru  : '.($demoTeacher?->email ?? '-').' / guru123');
        $this->command->info('  • Staff : murni.astuti@sekolah.test / staff123');
        $this->command->info('  • Siswa : '.($demoStudent?->email ?? '-').' / siswa123');
        $this->command->info($line);
    }
}
