<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'entry_academic_year_id',
        'graduation_academic_year_id',
        'nis',
        'nisn',
        'gender',
        'place_of_birth',
        'date_of_birth',
        'religion',
        'address',
        'phone_number',
        'profile_picture',
        'academic_status',
        'graduated_at',
    ];

    protected $casts = [
        'graduated_at' => 'datetime',
    ];

    /**
     * `grade` dihitung dari enrollment, tetapi tetap dikirim pada payload API
     * agar kontrak aplikasi mobile tidak berubah.
     *
     * @var list<string>
     */
    protected $appends = ['grade'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /**
     * Enrollment yang sedang berjalan. Invarian: maksimal satu per siswa.
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)->whereNull('ended_at');
    }

    /**
     * Enrollment terakhir, dipakai untuk membaca kelas terakhir alumni.
     */
    public function latestEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)->latestOfMany();
    }

    public function entryAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'entry_academic_year_id');
    }

    public function graduationAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'graduation_academic_year_id');
    }

    public function isActive(): bool
    {
        return $this->academic_status === 'active';
    }

    /**
     * Kelas siswa saat ini, atau kelas terakhir bagi siswa non-aktif (alumni).
     */
    public function currentClassroom(): ?Classroom
    {
        return $this->currentEnrollment?->classroom;
    }

    public function getClassroomAttribute(): ?Classroom
    {
        return $this->currentEnrollment?->classroom
            ?? $this->latestEnrollment?->classroom;
    }

    /**
     * Kelas yang sedang ditempati. Null bila siswa tidak punya enrollment berjalan
     * (mis. alumni), berbeda dari `classroom` yang menyediakan fallback tampilan.
     */
    public function getClassroomIdAttribute(): ?int
    {
        return $this->currentEnrollment?->classroom_id;
    }

    /**
     * Nama rombel siswa, dihitung dari enrollment agar tidak ada data ganda.
     */
    public function getGradeAttribute(): ?string
    {
        return $this->classroom?->name;
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
}
