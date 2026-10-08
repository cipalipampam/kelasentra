<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perubahan status satu siswa di dalam sebuah batch promosi,
 * menyimpan kondisi sebelum agar dapat dikembalikan.
 */
class PromotionBatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'promotion_batch_id',
        'student_id',
        'student_name',
        'student_nis',
        'from_classroom_id',
        'from_classroom_name',
        'from_grade',
        'from_academic_status',
        'to_classroom_id',
        'to_academic_status',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PromotionBatch::class, 'promotion_batch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
