<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'student_id',
        'student_enrollment_id',
        'teacher_id',
        'attendance_date',
        'status',
        'notes',
        'recorded_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'recorded_at' => 'datetime',
    ];

    /** Relasi riwayat: entitas yang sudah dinonaktifkan tetap harus bisa dibaca. */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class)->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }
}
