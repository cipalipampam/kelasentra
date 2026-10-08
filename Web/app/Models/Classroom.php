<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use HasFactory;

    public const LEVEL_X = '10';

    public const LEVEL_XI = '11';

    public const LEVEL_XII = '12';

    public const LEVELS = [self::LEVEL_X, self::LEVEL_XI, self::LEVEL_XII];

    protected $fillable = [
        'name',
        'level',
        'major',
        'section',
        'academic_year',
        'homeroom_teacher_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Penjurusan baru dimulai setelah kelas X, sehingga jurusan wajib
     * untuk tingkat XI dan XII tetapi boleh kosong untuk kelas X.
     */
    public static function majorIsRequiredForLevel(?string $level): bool
    {
        return in_array((string) $level, ['11', '12'], true);
    }

    public static function studentCapacity(): int
    {
        return (int) config('classroom.student_capacity', 30);
    }

    public function maxStudents(): int
    {
        return static::studentCapacity();
    }

    public function activeStudentCount(): int
    {
        return $this->students()->where('academic_status', 'active')->count();
    }

    /**
     * Sisa kuota siswa aktif pada rombel ini.
     */
    public function remainingCapacity(): int
    {
        return max(0, $this->maxStudents() - $this->activeStudentCount());
    }

    public function hasRoomFor(int $incomingStudents): bool
    {
        return $incomingStudents <= $this->remainingCapacity();
    }

    /**
     * Tingkat berikutnya, atau null bila rombel sudah di tingkat akhir.
     */
    public function nextLevel(): ?string
    {
        return match ($this->level) {
            self::LEVEL_X => self::LEVEL_XI,
            self::LEVEL_XI => self::LEVEL_XII,
            default => null,
        };
    }

    /**
     * Pengelompokan sesi rombel: tingkat + jurusan + tahun ajaran.
     *
     * Nomor sesi hanya bermakna di dalam kelompok ini, sehingga saran sesi
     * berikutnya pun dihitung per kelompok.
     */
    public function sectionKey(): string
    {
        return $this->level.'|'.($this->major ?? '').'|'.$this->academic_year;
    }

    /**
     * Apakah nomor sesi berupa angka sehingga bisa dilanjutkan otomatis?
     */
    public function hasNumericSection(): bool
    {
        return is_string($this->section) && ctype_digit($this->section);
    }

    /**
     * Alasan rombel ini tidak boleh naik ke $target, atau null bila valid.
     *
     * Kenaikan kelas wajib bertahap satu tingkat dan tidak boleh berpindah
     * jurusan. Pengecualiannya kelas X yang belum berjurusan: penjurusan
     * memang baru dimulai di kelas XI, jadi jurusan tujuan bebas dipilih.
     */
    public function promotionBlockReason(?self $target, int $incomingStudents): ?string
    {
        $nextLevel = $this->nextLevel();

        if ($nextLevel === null) {
            return 'Rombel tingkat XII tidak dapat dinaikkan. Gunakan proses kelulusan.';
        }

        if ($target === null) {
            return 'Kelas tujuan wajib dipilih untuk proses kenaikan kelas.';
        }

        if ($target->level !== $nextLevel) {
            return "Kenaikan kelas harus bertahap satu tingkat. {$this->name} (tingkat {$this->level}) hanya dapat naik ke tingkat {$nextLevel}.";
        }

        if ($this->major !== null && $target->major !== $this->major) {
            return "Jurusan tidak boleh berubah saat kenaikan kelas. {$this->name} berjurusan {$this->major}.";
        }

        if ($target->academic_year <= $this->academic_year) {
            return "Tahun ajaran kelas tujuan harus lebih baru dari {$this->academic_year}.";
        }

        if (! $target->hasRoomFor($incomingStudents)) {
            return "Kelas tujuan {$target->name} hanya menyisakan {$target->remainingCapacity()} kursi, sedangkan {$incomingStudents} siswa akan dipindahkan. Kapasitas maksimal {$target->maxStudents()} siswa per rombel.";
        }

        return null;
    }

    /**
     * Alasan rombel ini tidak boleh diluluskan, atau null bila valid.
     */
    public function graduationBlockReason(): ?string
    {
        if ($this->level !== self::LEVEL_XII) {
            return "Kelulusan hanya dapat diproses untuk rombel tingkat XII. {$this->name} berada di tingkat {$this->level}.";
        }

        return null;
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year', 'name');
    }
}

