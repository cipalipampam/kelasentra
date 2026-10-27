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

    public const REVERT_STATUS_RESTORED = 'restored';

    public const REVERT_STATUS_SKIPPED = 'skipped';

    public const REVERT_STATUS_MISSING_CLASSROOM = 'missing_classroom';

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
        'revert_status',
        'revert_note',
    ];

    public function isRestored(): bool
    {
        return $this->revert_status === self::REVERT_STATUS_RESTORED;
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PromotionBatch::class, 'promotion_batch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
