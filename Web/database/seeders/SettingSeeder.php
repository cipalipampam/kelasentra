<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Pengaturan sekolah awal: koordinat gerbang, jam presensi, dan profil sekolah.
 */
class SettingSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const SETTINGS = [
        // Lokasi sekolah (default koordinat Kelasentra)
        'school_lat' => '-6.200000',
        'school_long' => '106.816666',
        'school_radius' => '100',          // meter

        // Jam operasional
        'check_in_start' => '06:00',
        'check_in_end' => '07:00',        // batas tepat waktu
        'late_tolerance_minutes' => '15', // toleransi keterlambatan
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

    public function run(): void
    {
        foreach (self::SETTINGS as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
