<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Memastikan wali kelas yang dipilih benar-benar seorang guru
 * dan status kepegawaiannya masih aktif (bukan cuti/pensiun/resign).
 */
class EligibleHomeroomTeacher implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $teacher = User::with('employee')->find($value);

        if (! $teacher || ! $teacher->hasRole('guru')) {
            $fail('Wali kelas harus dipilih dari daftar guru.');

            return;
        }

        if (! $teacher->isEligibleHomeroomTeacher()) {
            $status = $teacher->employee?->statusLabel() ?? 'Tidak Aktif';

            $fail("Guru berstatus {$status} tidak dapat ditunjuk sebagai wali kelas.");
        }
    }
}
