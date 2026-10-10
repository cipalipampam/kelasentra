<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penugasan seorang guru untuk mengampu satu mata pelajaran di satu rombel.
 *
 * Tahun ajaran tidak disimpan di sini karena sudah dimiliki rombel. Guru
 * pengganti dibuat sebagai penugasan baru, sedangkan penugasan lama
 * di-soft-delete agar riwayat jadwalnya tetap terjaga.
 */
class TeachingAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'classroom_id',
        'subject_id',
        'teacher_id',
    ];

    /** Relasi riwayat: rombel/mapel/guru yang sudah dinonaktifkan tetap dibaca. */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class)->withTrashed();
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
