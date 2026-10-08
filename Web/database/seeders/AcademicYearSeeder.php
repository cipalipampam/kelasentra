<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        // Hanya satu tahun ajaran yang boleh berstatus aktif.
        $academicYears = [
            [
                'name' => '2025/2026',
                'start_date' => '2025-07-01',
                'end_date' => '2026-06-30',
                'status' => AcademicYear::STATUS_ARCHIVED,
            ],
            [
                'name' => '2026/2027',
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'status' => AcademicYear::STATUS_ACTIVE,
            ],
            [
                'name' => '2027/2028',
                'start_date' => '2027-07-01',
                'end_date' => '2028-06-30',
                'status' => AcademicYear::STATUS_UPCOMING,
            ],
        ];

        foreach ($academicYears as $academicYear) {
            AcademicYear::updateOrCreate(['name' => $academicYear['name']], $academicYear);
        }

        $this->command->info('✅ 3 tahun ajaran berhasil di-seed (2026/2027 aktif).');
    }
}
