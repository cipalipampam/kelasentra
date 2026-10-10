<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Notifikasi dalam aplikasi: catatan presensi, pengumuman sekolah, dan sapaan
 * sistem untuk guru/staff.
 *
 * Sebagian notifikasi ditandai sudah dibaca agar tampilan "belum dibaca" dan
 * "sudah dibaca" sama-sama ada isinya. Notifikasi riwayat kenaikan kelas dan
 * kelulusan bukan dari seeder ini, melainkan hasil PromotionHistorySeeder.
 */
class AppNotificationSeeder extends Seeder
{
    private const ATTENDANCE_LIMIT = 24;

    private const ANNOUNCEMENT_PER_STUDENT = 6;

    private const WELCOME_TITLE = 'Selamat datang di Kelasentra';

    public function run(): void
    {
        // Notifikasi riwayat promosi sudah dibuat PromotionHistorySeeder, jadi
        // penanda "sudah pernah dijalankan" memakai notifikasi milik seeder ini.
        if (AppNotification::query()->where('title', self::WELCOME_TITLE)->exists()) {
            $this->command?->warn('Notifikasi sudah ada; AppNotificationSeeder dilewati.');

            return;
        }

        $this->seedAttendanceNotifications();
        $this->seedAnnouncementNotifications();
        $this->seedSystemNotifications();
        $this->markSomeAsRead();
    }

    private function seedAttendanceNotifications(): void
    {
        $year = AcademicYear::currentYear();

        if ($year === null) {
            return;
        }

        $date = DemoData::lastSchoolDay(DemoData::dataCutoff($year->end_date), $year->start_date);

        $records = Attendance::query()
            ->whereDate('attendance_date', $date)
            ->whereIn('status', ['sick', 'permission', 'absent'])
            ->whereHas('user', fn ($query) => $query->role('siswa'))
            ->orderBy('id')
            ->limit(self::ATTENDANCE_LIMIT)
            ->get();

        foreach ($records as $record) {
            AppNotification::create([
                'user_id' => $record->user_id,
                'title' => 'Catatan presensi harian',
                'body' => sprintf(
                    'Presensi Anda pada %s tercatat sebagai %s.',
                    Carbon::parse($date)->translatedFormat('d F Y'),
                    $this->statusLabel($record->status),
                ),
                'type' => 'attendance',
                'data' => [
                    'screen' => 'attendance',
                    'attendance_id' => $record->getKey(),
                    'attendance_date' => $date,
                    'status' => $record->status,
                ],
                'is_read' => false,
            ]);
        }
    }

    /**
     * Pengumuman aktif dibagikan ke sebagian siswa saja supaya jumlahnya wajar.
     */
    private function seedAnnouncementNotifications(): void
    {
        $announcements = Announcement::query()->where('is_active', true)->orderBy('id')->get();

        if ($announcements->isEmpty()) {
            return;
        }

        $students = User::query()->role('siswa')->orderBy('id')->limit(self::ANNOUNCEMENT_PER_STUDENT)->get();

        foreach ($announcements as $announcement) {
            foreach ($students as $student) {
                AppNotification::create([
                    'user_id' => $student->getKey(),
                    'title' => $announcement->title,
                    'body' => (string) $announcement->content,
                    'type' => 'announcement',
                    'data' => ['action' => 'announcement', 'announcement_id' => $announcement->getKey()],
                    'is_read' => false,
                ]);
            }
        }
    }

    private function seedSystemNotifications(): void
    {
        $employees = User::query()->role(['guru', 'staff'])->orderBy('id')->get();

        foreach ($employees as $employee) {
            AppNotification::create([
                'user_id' => $employee->getKey(),
                'title' => self::WELCOME_TITLE,
                'body' => 'Jadwal mengajar dan presensi Anda dapat dipantau dari aplikasi mobile Kelasentra.',
                'type' => 'system',
                'data' => ['action' => 'system', 'version' => '1.0'],
                'is_read' => false,
            ]);
        }
    }

    /**
     * Menandai sebagian notifikasi sebagai sudah dibaca.
     */
    private function markSomeAsRead(): void
    {
        foreach ([['announcement', 20], ['system', 4]] as [$type, $limit]) {
            $ids = AppNotification::query()
                ->where('type', $type)
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                AppNotification::query()->whereIn('id', $ids)->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
            }
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'sick' => 'Sakit',
            'permission' => 'Izin',
            'absent' => 'Alfa (tanpa keterangan)',
            'late' => 'Terlambat',
            default => 'Hadir',
        };
    }
}
