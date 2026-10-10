<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Memastikan guru yang dipilih benar-benar boleh mengajar: berperan guru,
 * terdaftar sebagai tenaga pengajar, masih aktif, dan linier dengan mapel.
 */
class EligibleTeachingTeacher implements ValidationRule
{
    public function __construct(
        private readonly ?int $subjectId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $reason = self::reason(User::with(['employee', 'subjects'])->find($value), $this->subjectId);

        if ($reason !== null) {
            $fail($reason);
        }
    }

    /**
     * Alasan guru tidak eligible, atau null bila lolos. Dipakai juga oleh
     * service agar aturan tidak hanya bergantung pada validasi form.
     */
    public static function reason(?User $teacher, ?int $subjectId = null): ?string
    {
        if ($teacher === null || ! $teacher->hasRole('guru')) {
            return 'Pengajar harus dipilih dari daftar guru.';
        }

        $employee = $teacher->employee;

        if ($employee !== null && ! $employee->is_teacher) {
            return "{$teacher->name} bukan tenaga pengajar.";
        }

        if ($employee !== null && ! $employee->isActive()) {
            return "Guru berstatus {$employee->statusLabel()} tidak dapat menerima penugasan baru.";
        }

        if ($subjectId !== null && ! $teacher->subjects()->where('subjects.id', $subjectId)->exists()) {
            return "Guru {$teacher->name} tidak terdaftar linier untuk mata pelajaran ini.";
        }

        return null;
    }
}
