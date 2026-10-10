<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Mata pelajaran kurikulum demo beserta rumpunnya.
 *
 * Rumpun (cluster) dipakai untuk linieritas kualifikasi guru, sedangkan
 * `color_code` menentukan warna kartu jadwal di aplikasi mobile.
 */
class SubjectSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string, cluster: string, color_code: string}>
     */
    private const SUBJECTS = [
        ['code' => 'MAT-W', 'name' => 'Matematika Wajib', 'cluster' => 'mipa', 'color_code' => '#2563eb'],
        ['code' => 'BIND', 'name' => 'Bahasa Indonesia', 'cluster' => 'umum', 'color_code' => '#dc2626'],
        ['code' => 'BING', 'name' => 'Bahasa Inggris', 'cluster' => 'bahasa', 'color_code' => '#7c3aed'],
        ['code' => 'FIS', 'name' => 'Fisika', 'cluster' => 'mipa', 'color_code' => '#0891b2'],
        ['code' => 'KIM', 'name' => 'Kimia', 'cluster' => 'mipa', 'color_code' => '#059669'],
        ['code' => 'BIO', 'name' => 'Biologi', 'cluster' => 'mipa', 'color_code' => '#16a34a'],
        ['code' => 'SEJ', 'name' => 'Sejarah Indonesia', 'cluster' => 'ips', 'color_code' => '#b45309'],
        ['code' => 'PAI', 'name' => 'Pendidikan Agama dan Budi Pekerti', 'cluster' => 'umum', 'color_code' => '#0f766e'],
        ['code' => 'PJOK', 'name' => 'PJOK', 'cluster' => 'umum', 'color_code' => '#ea580c'],
        ['code' => 'INF', 'name' => 'Informatika', 'cluster' => 'mipa', 'color_code' => '#4f46e5'],
        ['code' => 'SENI', 'name' => 'Seni Budaya', 'cluster' => 'bahasa', 'color_code' => '#db2777'],
    ];

    public function run(): void
    {
        foreach (self::SUBJECTS as $subject) {
            Subject::updateOrCreate(['code' => $subject['code']], $subject);
        }
    }
}
