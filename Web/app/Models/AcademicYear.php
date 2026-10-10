<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    use HasFactory;

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_CURRENT = 'current';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_UPCOMING, self::STATUS_CURRENT, self::STATUS_CLOSED];

    /**
     * Status yang masih boleh dipakai untuk data operasional baru
     * (rombel baru, enrollment baru).
     */
    public const OPERABLE_STATUSES = [self::STATUS_CURRENT, self::STATUS_UPCOMING];

    protected $fillable = ['name', 'start_year', 'start_date', 'end_date', 'status'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * start_year selalu diturunkan dari name agar kronologi tidak pernah melenceng.
     */
    protected static function booted(): void
    {
        static::saving(function (self $academicYear): void {
            $academicYear->start_year = (int) substr((string) $academicYear->name, 0, 4);
        });
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'academic_year_id');
    }

    public function scopeOperable(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPERABLE_STATUSES);
    }

    public function isOperable(): bool
    {
        return in_array($this->status, self::OPERABLE_STATUSES, true);
    }

    public function isCurrent(): bool
    {
        return $this->status === self::STATUS_CURRENT;
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
            self::STATUS_UPCOMING => 'Akan Datang',
            self::STATUS_CURRENT => 'Berjalan',
            self::STATUS_CLOSED => 'Selesai',
        ];
    }

    /**
     * Tahun ajaran yang sedang berjalan secara kalender sekolah.
     */
    public static function currentYear(): ?self
    {
        return static::query()->where('status', self::STATUS_CURRENT)->first();
    }

    /**
     * Tahun ajaran berikutnya yang sedang disiapkan.
     */
    public static function nextYear(): ?self
    {
        return static::query()->where('status', self::STATUS_UPCOMING)->first();
    }

    /**
     * Tahun ajaran yang masih boleh menerima data operasional baru.
     *
     * @return Collection<int, self>
     */
    public static function operableYears(): Collection
    {
        return static::query()->operable()->orderByDesc('start_year')->get();
    }

    /**
     * @return list<int>
     */
    public static function operableIds(): array
    {
        return static::query()->operable()->pluck('id')->all();
    }
}
