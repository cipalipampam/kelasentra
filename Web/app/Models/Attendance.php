<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'academic_year_id',
        'classroom_id',
        'recorded_at',
        'latitude',
        'longitude',
        'status',
        'notes',
        'proof_image',
        'is_approved',
        'is_late',
        'check_out_time',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'attendance_date' => 'date',
        'check_out_time' => 'datetime',
        'is_approved' => 'boolean',
        'is_late' => 'boolean',
    ];

    /** Riwayat presensi tetap harus menampilkan nama pemiliknya walau akunnya sudah dinonaktifkan. */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class)->withTrashed();
    }

    protected static function booted(): void
    {
        static::saving(function (Attendance $attendance) {
            $attendance->attendance_date = Carbon::parse(
                $attendance->recorded_at ?? now(),
            )->toDateString();
        });
    }
}
