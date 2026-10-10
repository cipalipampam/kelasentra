<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Seeder;

/**
 * Pengumuman sekolah: campuran yang masih tampil di aplikasi dan arsip lama.
 */
class AnnouncementSeeder extends Seeder
{
    /**
     * @var list<array{title: string, content: string, is_active: bool}>
     */
    private const ANNOUNCEMENTS = [
        [
            'title' => 'Jadwal Ujian Tengah Semester Ganjil',
            'content' => 'Ujian Tengah Semester dilaksanakan pada 20–24 Oktober 2026. Siswa wajib membawa kartu peserta dan hadir 15 menit sebelum ujian dimulai.',
            'is_active' => true,
        ],
        [
            'title' => 'Pekan Literasi Sekolah',
            'content' => 'Seluruh siswa mengikuti pekan literasi dengan membaca satu buku non-pelajaran dan menuliskan resensinya di perpustakaan.',
            'is_active' => true,
        ],
        [
            'title' => 'Pengambilan Rapor Tengah Semester',
            'content' => 'Rapor tengah semester dapat diambil oleh orang tua/wali pada hari Sabtu pukul 08.00–11.00 di ruang kelas masing-masing.',
            'is_active' => true,
        ],
        [
            'title' => 'Libur Semester Genap 2025/2026',
            'content' => 'Libur akhir tahun ajaran 2025/2026 berlangsung pada 22 Juni sampai 11 Juli 2026. Kegiatan belajar dimulai kembali pada 13 Juli 2026.',
            'is_active' => false,
        ],
    ];

    public function run(): void
    {
        foreach (self::ANNOUNCEMENTS as $announcement) {
            Announcement::updateOrCreate(['title' => $announcement['title']], $announcement);
        }
    }
}
