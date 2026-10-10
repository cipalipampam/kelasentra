<?php

namespace App\Services\Web\Academic;

use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubjectService
{
    public function getPaginatedSubjects(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Subject::withCount('assignments');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function createSubject(array $data): Subject
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return Subject::create($data);
    }

    public function updateSubject(Subject $subject, array $data): bool
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $subject->update($data);
    }

    public function deleteSubject(Subject $subject): bool
    {
        if ($subject->assignments()->count() > 0) {
            return false;
        }

        return (bool) $subject->delete();
    }
}

