<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use HasFactory;

    public const LEVEL_X = '10';

    public const LEVELS = ['10', '11', '12'];

    protected $fillable = [
        'name',
        'level',
        'major',
        'section',
        'academic_year',
        'homeroom_teacher_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Penjurusan baru dimulai setelah kelas X, sehingga jurusan wajib
     * untuk tingkat XI dan XII tetapi boleh kosong untuk kelas X.
     */
    public static function majorIsRequiredForLevel(?string $level): bool
    {
        return in_array((string) $level, ['11', '12'], true);
    }

    public static function studentCapacity(): int
    {
        return (int) config('classroom.student_capacity', 30);
    }

    public function maxStudents(): int
    {
        return static::studentCapacity();
    }

    public function activeStudentCount(): int
    {
        return $this->students()->where('academic_status', 'active')->count();
    }

    /**
     * Sisa kuota siswa aktif pada rombel ini.
     */
    public function remainingCapacity(): int
    {
        return max(0, $this->maxStudents() - $this->activeStudentCount());
    }

    public function hasRoomFor(int $incomingStudents): bool
    {
        return $incomingStudents <= $this->remainingCapacity();
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year', 'name');
    }
}

