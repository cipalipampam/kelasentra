<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

/**
 * Kronologi tahun ajaran demo.
 *
 * Status di sini adalah status pada saat seeder berjalan: tahun 2025/2026
 * masih `current` dan 2026/2027 masih `upcoming` karena PromotionHistorySeeder
 * butuh tahun tujuan yang operable untuk memproses kenaikan kelas. Seeder itu
 * pula yang menutup 2025/2026 dan menjadikan 2026/2027 berjalan.
 */
class AcademicYearSeeder extends Seeder
{
    /**
     * @var list<array{name: string, status: string, start_date: string, end_date: string}>
     */
    private const YEARS = [
        ['name' => '2023/2024', 'status' => AcademicYear::STATUS_CLOSED, 'start_date' => '2023-07-17', 'end_date' => '2024-06-21'],
        ['name' => '2024/2025', 'status' => AcademicYear::STATUS_CLOSED, 'start_date' => '2024-07-15', 'end_date' => '2025-06-20'],
        ['name' => '2025/2026', 'status' => AcademicYear::STATUS_CURRENT, 'start_date' => '2025-07-14', 'end_date' => '2026-06-19'],
        ['name' => '2026/2027', 'status' => AcademicYear::STATUS_UPCOMING, 'start_date' => '2026-07-13', 'end_date' => '2027-06-18'],
    ];

    public function run(): void
    {
        // Seeder ini menulis kronologi awal (2025/2026 masih berjalan); setelah
        // PromotionHistorySeeder menutupnya, menjalankan ulang akan melanggar
        // index unik "satu tahun berjalan". Karena itu data yang sudah ada
        // dibiarkan apa adanya.
        if (AcademicYear::query()->exists()) {
            $this->command?->warn('Tahun ajaran sudah ada; AcademicYearSeeder dilewati.');

            return;
        }

        foreach (self::YEARS as $year) {
            AcademicYear::updateOrCreate(['name' => $year['name']], $year);
        }
    }
}
