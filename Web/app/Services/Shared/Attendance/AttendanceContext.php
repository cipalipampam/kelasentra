<?php

namespace App\Services\Shared\Attendance;

use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Membekukan konteks akademik saat sebuah presensi dicatat.
 *
 * Snapshot ini membuat laporan periode lama tetap benar walau siswa sudah
 * berpindah rombel, dan memisahkan presensi siswa dari presensi pegawai.
 */
class AttendanceContext
{
    /**
     * @return array{academic_year_id: int, classroom_id: ?int}
     */
    public static function forUser(?User $user): array
    {
        $enrollment = $user?->student?->currentEnrollment;

        return [
            'academic_year_id' => $enrollment?->academic_year_id ?? self::requireCurrentYear(),
            'classroom_id' => $enrollment?->classroom_id,
        ];
    }

    /**
     * @return array{academic_year_id: ?int, classroom_id: ?int, student_enrollment_id: ?int}
     */
    public static function forEnrollment(?StudentEnrollment $enrollment): array
    {
        return [
            'academic_year_id' => $enrollment?->academic_year_id ?? self::requireCurrentYear(),
            'classroom_id' => $enrollment?->classroom_id,
            'student_enrollment_id' => $enrollment?->getKey(),
        ];
    }

    /**
     * Presensi tanpa tahun ajaran berjalan tidak punya konteks akademik,
     * sehingga ditolak lebih awal dengan pesan yang jelas.
     */
    private static function requireCurrentYear(): int
    {
        $yearId = AcademicYear::currentYear()?->getKey();

        if ($yearId === null) {
            throw ValidationException::withMessages([
                'academic_year' => 'Tahun ajaran aktif belum ditetapkan sehingga presensi tidak dapat dicatat.',
            ]);
        }

        return $yearId;
    }
}
