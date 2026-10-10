<?php

namespace Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Bahan mentah bersama untuk seeder demo.
 *
 * Identitas siswa maupun tanggal hari sekolah dihasilkan secara deterministik
 * dari sebuah indeks, sehingga seluruh seeder saling konsisten, hasilnya bisa
 * diulang, dan tidak bergantung pada urutan acak Faker.
 */
class DemoData
{
    /** @var list<string> */
    private const MALE_FIRST_NAMES = [
        'Ahmad', 'Bagas', 'Candra', 'Dimas', 'Eko', 'Fajar', 'Gilang', 'Hendra',
        'Irfan', 'Joko', 'Krisna', 'Luthfi', 'Maulana', 'Naufal', 'Oka', 'Pandu',
        'Rafi', 'Rizky', 'Satria', 'Taufik', 'Umar', 'Vino', 'Wahyu', 'Yoga',
        'Zaki', 'Arif', 'Bimo', 'Dedi', 'Erwin', 'Farhan',
    ];

    /** @var list<string> */
    private const FEMALE_FIRST_NAMES = [
        'Ayu', 'Bella', 'Citra', 'Dewi', 'Endah', 'Fitri', 'Gita', 'Hanifah',
        'Indah', 'Jihan', 'Kartika', 'Lestari', 'Maya', 'Nadia', 'Oktaviani', 'Putri',
        'Qonita', 'Rina', 'Sari', 'Tiara', 'Ulfa', 'Vina', 'Wulan', 'Yuni',
        'Zahra', 'Anisa', 'Bunga', 'Diah', 'Elsa', 'Farida',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'Pratama', 'Saputra', 'Wijaya', 'Nugroho', 'Hidayat', 'Ramadhan',
        'Purnama', 'Setiawan', 'Handayani', 'Anggraini', 'Kusuma', 'Firmansyah',
        'Hakim', 'Simatupang', 'Siregar', 'Halim', 'Sasmita', 'Yulianto',
        'Permata', 'Syahputra', 'Gunawan', 'Wibowo', 'Santoso', 'Hartono',
        'Iskandar', 'Rahmawati', 'Sadewa', 'Mahendra', 'Kurniawan', 'Puspita',
    ];

    /** @var list<string> */
    private const BIRTH_PLACES = [
        'Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Medan',
        'Makassar', 'Denpasar', 'Palembang', 'Bogor', 'Bekasi', 'Malang',
    ];

    /** @var list<string> */
    private const STREET_NAMES = [
        'Jl. Melati', 'Jl. Kenanga', 'Jl. Cempaka', 'Jl. Anggrek', 'Jl. Mawar',
        'Jl. Kamboja', 'Jl. Flamboyan', 'Jl. Merpati', 'Jl. Elang', 'Jl. Rajawali',
    ];

    /** @var list<string> */
    private const RELIGIONS = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha'];

    /**
     * Identitas siswa ke-$index. Kombinasi nama depan + nama belakang tidak
     * pernah berulang untuk 120 siswa pertama.
     *
     * @return array{name: string, gender: string}
     */
    public static function studentIdentity(int $index): array
    {
        $gender = $index % 2 === 0 ? 'male' : 'female';
        $pool = $gender === 'male' ? self::MALE_FIRST_NAMES : self::FEMALE_FIRST_NAMES;

        $first = $pool[intdiv($index, 2) % count($pool)];
        $last = self::LAST_NAMES[intdiv($index, count($pool) * 2) % count(self::LAST_NAMES)];

        return ['name' => $first.' '.$last, 'gender' => $gender];
    }

    /**
     * Tanggal lahir siswa yang masuk pada tahun ajaran tertentu: usia 15 tahun
     * saat tahun ajaran dimulai, dengan variasi bulan dan tahun agar tidak seragam.
     */
    public static function studentBirthDate(int $entryStartYear, int $index): string
    {
        $birthYear = $entryStartYear - 15 - ($index % 2);

        return sprintf('%04d-%02d-%02d', $birthYear, 1 + ($index % 12), 1 + ($index % 27));
    }

    public static function birthPlace(int $index): string
    {
        return self::BIRTH_PLACES[$index % count(self::BIRTH_PLACES)];
    }

    public static function religion(int $index): string
    {
        // Mayoritas Islam, sisanya tersebar agar kolom agama tidak monoton.
        return $index % 7 === 0
            ? self::RELIGIONS[1 + ($index % (count(self::RELIGIONS) - 1))]
            : self::RELIGIONS[0];
    }

    public static function address(int $index): string
    {
        return sprintf(
            '%s No. %d, RT %02d/RW %02d, %s',
            self::STREET_NAMES[$index % count(self::STREET_NAMES)],
            1 + ($index % 120),
            1 + ($index % 12),
            1 + ($index % 9),
            self::BIRTH_PLACES[($index + 3) % count(self::BIRTH_PLACES)],
        );
    }

    public static function phoneNumber(int $index): string
    {
        return '08'.str_pad((string) (1200000000 + ($index * 7919) % 700000000), 10, '0', STR_PAD_LEFT);
    }

    /**
     * Daftar hari sekolah (Senin–Jumat) terakhir sampai dengan $end, terbaru lebih dulu.
     *
     * @return list<string>
     */
    public static function schoolDays(CarbonInterface $end, int $count, ?CarbonInterface $notBefore = null): array
    {
        $dates = [];
        $cursor = Carbon::parse($end->toDateString())->startOfDay();
        $limit = $notBefore !== null ? Carbon::parse($notBefore->toDateString()) : null;

        while (count($dates) < $count) {
            if ($limit !== null && $cursor->lessThan($limit)) {
                break;
            }

            if ($cursor->isWeekday()) {
                $dates[] = $cursor->toDateString();
            }

            $cursor->subDay();
        }

        return $dates;
    }

    /**
     * Hari sekolah terakhir sampai dengan $end.
     */
    public static function lastSchoolDay(CarbonInterface $end, ?CarbonInterface $notBefore = null): string
    {
        return self::schoolDays($end, 1, $notBefore)[0];
    }

    /**
     * Batas akhir data yang masuk akal untuk sebuah tahun ajaran: tanggal
     * selesainya, atau hari ini bila tahun ajaran masih berjalan.
     */
    public static function dataCutoff(?CarbonInterface $yearEnd): CarbonInterface
    {
        return $yearEnd !== null && $yearEnd->isPast() ? $yearEnd : Carbon::now();
    }
}
