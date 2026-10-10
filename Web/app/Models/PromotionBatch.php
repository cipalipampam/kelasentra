<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jejak audit satu kali eksekusi kenaikan kelas atau kelulusan massal.
 */
class PromotionBatch extends Model
{
    use HasFactory;

    public const ACTION_PROMOTE = 'promote';

    public const ACTION_GRADUATE = 'graduate';

    protected $fillable = [
        'action',
        'source_classroom_id',
        'source_classroom_name',
        'source_academic_year',
        'target_classroom_id',
        'target_classroom_name',
        'target_academic_year',
        'performed_by',
        'performed_by_name',
        'student_count',
        'reverted_at',
        'reverted_by',
        'reverted_by_name',
    ];

    protected $casts = [
        'reverted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PromotionBatchItem::class);
    }

    public function sourceClassroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'source_classroom_id');
    }

    public function targetClassroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'target_classroom_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }

    public function actionLabel(): string
    {
        return $this->action === self::ACTION_PROMOTE ? 'Kenaikan Kelas' : 'Kelulusan';
    }

    public function targetLabel(): string
    {
        return $this->target_classroom_name ?? 'Alumni / Lulus';
    }

}
