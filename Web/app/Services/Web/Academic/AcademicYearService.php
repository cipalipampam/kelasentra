<?php

namespace App\Services\Web\Academic;

use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        return $query->orderByDesc('start_year')->paginate($perPage)->withQueryString();
    }

    public function create(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data) {
            $this->guardSingleUpcoming($data['status']);

            // Periode berjalan lama ditutup lebih dulu agar indeks unik tidak menolak.
            if ($data['status'] === AcademicYear::STATUS_CURRENT) {
                $this->closeOtherCurrentYears();
            }

            return AcademicYear::create($this->normalize($data));
        });
    }

    public function update(AcademicYear $academicYear, array $data): bool
    {
        return DB::transaction(function () use ($academicYear, $data) {
            $this->guardSingleUpcoming($data['status'], $academicYear->getKey());

            if ($data['status'] === AcademicYear::STATUS_CURRENT) {
                $this->closeOtherCurrentYears($academicYear->getKey());
            }

            return (bool) $academicYear->update($this->normalize($data));
        });
    }

    /**
     * Tahun ajaran hanya boleh dihapus jika belum dipakai rombel mana pun.
     */
    public function delete(AcademicYear $academicYear): bool
    {
        if (Classroom::query()->where('academic_year_id', $academicYear->getKey())->exists()) {
            return false;
        }

        return (bool) $academicYear->delete();
    }

    /**
     * Menjadikan satu tahun ajaran sebagai periode berjalan.
     * Periode berjalan sebelumnya otomatis ditutup, bukan dihapus.
     */
    public function setCurrent(AcademicYear $academicYear): bool
    {
        return DB::transaction(function () use ($academicYear) {
            $this->closeOtherCurrentYears($academicYear->getKey());

            return (bool) $academicYear->update(['status' => AcademicYear::STATUS_CURRENT]);
        });
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return [
            'total' => AcademicYear::query()->count(),
            'current' => AcademicYear::query()->where('status', AcademicYear::STATUS_CURRENT)->count(),
            'upcoming' => AcademicYear::query()->where('status', AcademicYear::STATUS_UPCOMING)->count(),
            'closed' => AcademicYear::query()->where('status', AcademicYear::STATUS_CLOSED)->count(),
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
     * Maksimal satu periode berikutnya. Periode berjalan tidak dibatasi di sini
     * karena periode berjalan sebelumnya otomatis ditutup.
     */
    private function guardSingleUpcoming(string $status, ?int $exceptId = null): void
    {
        if ($status !== AcademicYear::STATUS_UPCOMING) {
            return;
        }

        $exists = AcademicYear::query()
            ->where('status', AcademicYear::STATUS_UPCOMING)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'status' => 'Sudah ada tahun ajaran yang akan datang. Selesaikan atau ubah periode tersebut terlebih dahulu.',
            ]);
        }
    }

    /**
     * Periode berjalan sebelumnya ditutup agar riwayatnya tetap tersimpan.
     */
    private function closeOtherCurrentYears(?int $exceptId = null): void
    {
        AcademicYear::query()
            ->where('status', AcademicYear::STATUS_CURRENT)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['status' => AcademicYear::STATUS_CLOSED]);
    }
}
