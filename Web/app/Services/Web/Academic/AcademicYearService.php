<?php

namespace App\Services\Web\Academic;

use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AcademicYearService
{
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = AcademicYear::query()->withCount('classrooms');

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('name')->paginate($perPage)->withQueryString();
    }

    public function create(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data) {
            $academicYear = AcademicYear::create($this->normalize($data));

            if ($academicYear->isActive()) {
                $this->archiveOtherActiveYears($academicYear->getKey());
            }

            return $academicYear;
        });
    }

    public function update(AcademicYear $academicYear, array $data): bool
    {
        return DB::transaction(function () use ($academicYear, $data) {
            $updated = $academicYear->update($this->normalize($data));

            if ($academicYear->isActive()) {
                $this->archiveOtherActiveYears($academicYear->getKey());
            }

            return (bool) $updated;
        });
    }

    /**
     * Tahun ajaran hanya boleh dihapus jika belum dipakai rombel mana pun.
     */
    public function delete(AcademicYear $academicYear): bool
    {
        if (Classroom::query()->where('academic_year', $academicYear->name)->exists()) {
            return false;
        }

        return (bool) $academicYear->delete();
    }

    /**
     * Menjadikan satu tahun ajaran sebagai tahun ajaran berjalan.
     * Tahun ajaran aktif sebelumnya otomatis diarsipkan.
     */
    public function setActive(AcademicYear $academicYear): bool
    {
        return DB::transaction(function () use ($academicYear) {
            $updated = $academicYear->update(['status' => AcademicYear::STATUS_ACTIVE]);

            $this->archiveOtherActiveYears($academicYear->getKey());

            return (bool) $updated;
        });
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return [
            'total' => AcademicYear::query()->count(),
            'active' => AcademicYear::query()->where('status', AcademicYear::STATUS_ACTIVE)->count(),
            'upcoming' => AcademicYear::query()->where('status', AcademicYear::STATUS_UPCOMING)->count(),
            'archived' => AcademicYear::query()->where('status', AcademicYear::STATUS_ARCHIVED)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'],
        ];
    }

    /**
     * Hanya satu tahun ajaran yang boleh berstatus aktif.
     */
    private function archiveOtherActiveYears(int $exceptId): void
    {
        AcademicYear::query()
            ->where('status', AcademicYear::STATUS_ACTIVE)
            ->whereKeyNot($exceptId)
            ->update(['status' => AcademicYear::STATUS_ARCHIVED]);
    }
}
