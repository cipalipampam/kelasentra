<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'teaching_assignment_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Penugasan yang menjadi konteks jadwal ini. Penugasan yang sudah
     * di-soft-delete tetap dibaca agar riwayat jadwal tidak kehilangan makna.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id')->withTrashed();
    }

    public function getClassroomAttribute(): ?Classroom
    {
        return $this->assignment?->classroom;
    }

    public function getClassroomIdAttribute(): ?int
    {
        return $this->assignment?->classroom_id;
    }

    public function getSubjectAttribute(): ?Subject
    {
        return $this->assignment?->subject;
    }

    public function getTeacherAttribute(): ?User
    {
        return $this->assignment?->teacher;
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ScheduleAttendance::class);
    }

    public function getDayNameAttribute(): string
    {
        $days = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];

        return $days[$this->day_of_week] ?? 'Hari Tidak Diketahui';
    }

    public function getDurationInMinutesAttribute(): int
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        return max(0, $start->diffInMinutes($end));
    }

    public function getJpCountAttribute(): int
    {
        // 1 JP = 45 menit (standar SMA/SMK)
        $minutes = $this->duration_in_minutes;
        if ($minutes <= 0) {
            return 0;
        }

        return (int) round($minutes / 45);
    }

    public function getFormattedTimeRangeAttribute(): string
    {
        $start = substr($this->start_time, 0, 5);
        $end = substr($this->end_time, 0, 5);

        return "{$start} - {$end}";
    }

    public function scopeForDay($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    public function scopeOverlapping($query, int $dayOfWeek, string $startTime, string $endTime, ?int $ignoreId = null)
    {
        return $query->where('day_of_week', $dayOfWeek)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>', $startTime);
            })
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));
    }
}

