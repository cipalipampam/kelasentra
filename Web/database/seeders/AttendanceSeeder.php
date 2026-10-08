<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seed riwayat presensi gerbang harian 14 hari ke belakang untuk seluruh
 * siswa, guru, dan staf.
 *
 * Senin-Sabtu saja (Minggu dilewati). Distribusi acak: 80% hadir,
 * 10% izin, 10% sakit. Penulisan memakai bulk insert agar ribuan rekaman tetap cepat.
 */
class AttendanceSeeder extends Seeder
{
    private const HISTORY_DAYS = 14;

    private const INSERT_CHUNK = 500;

    public function run(): void
    {
        $users = User::with('roles')->get()->filter(
            fn (User $user) => $user->hasRole(['siswa', 'guru', 'staff'])
        );

        $existingDates = $this->existingDatesByUser();

        $rows = [];
        $now = Carbon::now();

        foreach ($users as $user) {
            $recordedDates = $existingDates->get($user->id, []);

            for ($dayOffset = self::HISTORY_DAYS; $dayOffset >= 1; $dayOffset--) {
                $date = Carbon::today()->subDays($dayOffset);

                // Skip Minggu
                if ($date->dayOfWeek === Carbon::SUNDAY) {
                    continue;
                }

                // Skip jika sudah ada record di hari ini untuk user ini
                if (in_array($date->toDateString(), $recordedDates, true)) {
                    continue;
                }

                $rows[] = $this->makeRow($user, $date, $now);
            }
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            Attendance::query()->insert($chunk);
        }

        $this->command->info('✅ '.count($rows).' record presensi gerbang ('.self::HISTORY_DAYS." hari terakhir) berhasil di-seed untuk {$users->count()} pengguna.");
    }

    /**
     * @return array<string, mixed>
     */
    private function makeRow(User $user, Carbon $date, Carbon $now): array
    {
        $roll = rand(1, 10); // 1-8 hadir, 9 izin, 10 sakit

        $base = [
            'user_id' => $user->id,
            'is_late' => false,
            'latitude' => null,
            'longitude' => null,
            'check_out_time' => null,
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($roll <= 8) {
            // HADIR — jam masuk acak antara 06:30-07:20
            $checkIn = $date->copy()->setHour(6)->setMinute(30)->addMinutes(rand(0, 50));

            return array_merge($base, [
                'status' => 'present',
                'is_late' => $checkIn->greaterThan($date->copy()->setHour(7)->setMinute(0)),
                'is_approved' => true,
                'latitude' => -6.200000 + (rand(-10, 10) / 10000),
                'longitude' => 106.816666 + (rand(-10, 10) / 10000),
                'recorded_at' => $checkIn,
                // Jam pulang acak antara 15:00-16:00
                'check_out_time' => $date->copy()->setHour(15)->addMinutes(rand(0, 60)),
            ]);
        }

        if ($roll === 9) {
            return array_merge($base, [
                'status' => 'permission',
                'is_approved' => rand(0, 1) === 1 ? true : null,
                'notes' => 'Keperluan keluarga.',
                'recorded_at' => $date->copy()->setHour(7)->setMinute(0),
            ]);
        }

        return array_merge($base, [
            'status' => 'sick',
            'is_approved' => rand(0, 1) === 1 ? true : null,
            'notes' => 'Demam dan flu.',
            'recorded_at' => $date->copy()->setHour(7)->setMinute(0),
        ]);
    }

    /**
     * Tanggal presensi yang sudah tercatat per pengguna, diambil sekali
     * agar tidak perlu query berulang per hari.
     *
     * @return \Illuminate\Support\Collection<int, list<string>>
     */
    private function existingDatesByUser()
    {
        return Attendance::query()
            ->where('recorded_at', '>=', Carbon::today()->subDays(self::HISTORY_DAYS)->startOfDay())
            ->get(['user_id', 'recorded_at'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => Carbon::parse($row->recorded_at)->toDateString())->all());
    }
}
