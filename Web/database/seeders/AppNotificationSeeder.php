<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AppNotificationSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::role('siswa')->get();
        $teachers = User::role('guru')->get();

        $count = 0;

        // Notifikasi untuk siswa
        foreach ($students as $student) {
            $notifications = [
                [
                    'title' => 'Tahun Ajaran Baru 2026/2027 Dimulai',
                    'body' => 'Selamat datang di semester baru. Pastikan Anda memeriksa jadwal pelajaran mingguan dan mematuhi tata tertib presensi sekolah.',
                    'type' => 'announcement',
                    'data' => ['screen' => 'schedule'],
                    'is_read' => true,
                    'created_at' => Carbon::now()->subDays(5),
                ],
                [
                    'title' => 'Surat Sakit Anda Telah Disetujui',
                    'body' => 'Pengajuan surat keterangan sakit untuk tanggal '.Carbon::today()->subDays(2)->format('d M Y').' telah diverifikasi oleh tim piket.',
                    'type' => 'attendance',
                    'data' => ['screen' => 'attendance'],
                    'is_read' => false,
                    'created_at' => Carbon::now()->subDays(2),
                ],
                [
                    'title' => 'Pengingat: Jam Presensi Masuk',
                    'body' => 'Batas waktu presensi masuk gerbang sekolah adalah pukul 07:00 WIB. Segera lakukan check-in melalui aplikasi mobile.',
                    'type' => 'system',
                    'data' => null,
                    'is_read' => false,
                    'created_at' => Carbon::now()->subHours(2),
                ],
            ];

            if ($this->alreadySeeded($student, $notifications)) {
                continue;
            }

            foreach ($notifications as $notif) {
                AppNotification::create([
                    'user_id' => $student->id,
                    'title' => $notif['title'],
                    'body' => $notif['body'],
                    'type' => $notif['type'],
                    'data' => $notif['data'],
                    'is_read' => $notif['is_read'],
                    'created_at' => $notif['created_at'],
                    'updated_at' => $notif['created_at'],
                ]);
                $count++;
            }
        }

        // Notifikasi untuk guru
        foreach ($teachers as $teacher) {
            $notifications = [
                [
                    'title' => 'Jadwal Mengajar Hari Ini',
                    'body' => 'Anda memiliki jadwal mengajar di kelas hari ini. Jangan lupa untuk membuka presensi kelas setelah jam pelajaran dimulai.',
                    'type' => 'schedule',
                    'data' => ['screen' => 'schedule'],
                    'is_read' => false,
                    'created_at' => Carbon::now()->subHours(3),
                ],
                [
                    'title' => 'Rapat Dewan Guru & Kurikulum',
                    'body' => 'Rapat koordinasi evaluasi kurikulum dan presensi siswa akan diselenggarakan hari Jumat pukul 13:30 WIB di Ruang Guru.',
                    'type' => 'announcement',
                    'data' => null,
                    'is_read' => true,
                    'created_at' => Carbon::now()->subDays(3),
                ],
            ];

            if ($this->alreadySeeded($teacher, $notifications)) {
                continue;
            }

            foreach ($notifications as $notif) {
                AppNotification::create([
                    'user_id' => $teacher->id,
                    'title' => $notif['title'],
                    'body' => $notif['body'],
                    'type' => $notif['type'],
                    'data' => $notif['data'],
                    'is_read' => $notif['is_read'],
                    'created_at' => $notif['created_at'],
                    'updated_at' => $notif['created_at'],
                ]);
                $count++;
            }
        }

        $this->command->info("✅ {$count} notifikasi pengguna (siswa & guru) berhasil di-seed.");
    }

    /**
     * Hindari duplikasi notifikasi ketika seeder dijalankan ulang.
     *
     * @param  list<array<string, mixed>>  $notifications
     */
    private function alreadySeeded(User $user, array $notifications): bool
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->whereIn('title', array_column($notifications, 'title'))
            ->exists();
    }
}

