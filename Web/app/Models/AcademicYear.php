<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_UPCOMING, self::STATUS_ARCHIVED];

    /**
     * Status yang boleh digunakan untuk rombel yang sedang berjalan / akan datang.
     */
    public const SELECTABLE_STATUSES = [self::STATUS_ACTIVE, self::STATUS_UPCOMING];

    protected $fillable = ['name', 'start_date', 'end_date', 'status'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'academic_year', 'name');
    }

    public function scopeSelectable(Builder $query): Builder
    {
        return $query->whereIn('status', self::SELECTABLE_STATUSES);
    }

    public function isSelectable(): bool
    {
        return in_array($this->status, self::SELECTABLE_STATUSES, true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_UPCOMING => 'Akan Datang',
            self::STATUS_ARCHIVED => 'Diarsipkan',
        ];
    }

    /**
     * Nama tahun ajaran yang masih boleh dipakai untuk rombel baru.
     *
     * @return list<string>
     */
    public static function selectableNames(): array
    {
        return static::query()
            ->selectable()
            ->orderByDesc('name')
            ->pluck('name')
            ->all();
    }
}
