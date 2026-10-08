<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'classroom_id',
        'nis',
        'nisn',
        'grade',
        'gender',
        'place_of_birth',
        'date_of_birth',
        'religion',
        'address',
        'phone_number',
        'profile_picture',
        'academic_status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function isActive(): bool
    {
        return $this->academic_status === 'active';
    }

    /**
     * @return array<string, string>
     */
    public static function academicStatusLabels(): array
    {
        return [
            'active' => 'Siswa Aktif',
            'graduated' => 'Alumni (Lulus)',
            'transferred' => 'Pindah Sekolah',
            'dropped' => 'Keluar',
        ];
    }

    public function academicStatusLabel(): string
    {
        return self::academicStatusLabels()[$this->academic_status] ?? 'Tidak diketahui';
    }

    public function scheduleAttendances(): HasMany
    {
        return $this->hasMany(ScheduleAttendance::class);
    }

    /**
     * Accessor untuk grade: otomatis ambil dari rombel resmi jika ada,
     * fallback ke atribut grade lama untuk backward-compatibility.
     */
    public function getGradeAttribute($value): ?string
    {
        if ($this->relationLoaded('classroom') && $this->classroom) {
            return $this->classroom->name;
        }

        return $value;
    }
}
