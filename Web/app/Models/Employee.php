<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_RETIRED = 'retired';

    public const STATUS_RESIGNED = 'resigned';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_LEAVE, self::STATUS_RETIRED, self::STATUS_RESIGNED];

    protected $fillable = [
        'user_id',
        'nip',
        'position',
        'is_teacher',
        'employment_status',
        'gender',
        'place_of_birth',
        'date_of_birth',
        'religion',
        'address',
        'phone_number',
        'profile_picture',
    ];

    protected $casts = [
        'is_teacher' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->employment_status === self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->employment_status] ?? (string) $this->employment_status;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_LEAVE => 'Cuti',
            self::STATUS_RETIRED => 'Pensiun',
            self::STATUS_RESIGNED => 'Resign / Berhenti',
        ];
    }
}
