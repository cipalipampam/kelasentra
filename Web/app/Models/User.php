<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Tell Spatie Permission to always resolve roles/permissions against
     * the 'web' guard. Without this, API requests authenticated via
     * Sanctum cause "there is no role X for guard sanctum" because roles
     * were seeded under the 'web' guard.
     */
    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'teacher_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Guru yang masih aktif dan boleh ditunjuk menjadi wali kelas.
     * Guru berstatus cuti, pensiun, atau resign dikecualikan. Pengguna
     * tanpa data kepegawaian tetap dianggap aktif demi kompatibilitas.
     */
    public function scopeEligibleHomeroomTeacher(Builder $query): Builder
    {
        return $query->role('guru')->where(function (Builder $query) {
            $query->whereDoesntHave('employee')
                ->orWhereHas('employee', fn (Builder $employee) => $employee
                    ->where('employment_status', Employee::STATUS_ACTIVE));
        });
    }

    public function isEligibleHomeroomTeacher(): bool
    {
        if (! $this->hasRole('guru')) {
            return false;
        }

        return $this->employee === null || $this->employee->isActive();
    }
}
