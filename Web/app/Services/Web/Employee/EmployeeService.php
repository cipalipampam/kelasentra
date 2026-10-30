<?php

namespace App\Services\Web\Employee;

use App\Events\DirectoryChanged;
use App\Events\SessionInvalidated;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Employee;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function createEmployee(array $data)
    {
        $fotoPath = null;
        if (isset($data['profile_picture'])) {
            $fotoPath = $data['profile_picture']->store('pas_foto', 'public');
        }

        try {
            $user = DB::transaction(function () use ($data, $fotoPath) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);

                $user->assignRole($data['role']);

                $user->employee()->create($this->employeeAttributes($data, $fotoPath));
                $this->syncSubjects($user, $data);

                return $user;
            });
        } catch (\Throwable $e) {
            if ($fotoPath) {
                Storage::disk('public')->delete($fotoPath);
            }

            throw $e;
        }

        event(new DirectoryChanged($user->id, 'employee', 'created'));

        return $user;
    }

    public function updateEmployee(User $user, array $data)
    {
        $currentPic = $user->employee ? $user->employee->profile_picture : null;
        $fotoPath = $currentPic;

        if (isset($data['profile_picture'])) {
            $fotoPath = $data['profile_picture']->store('pas_foto', 'public');
        }

        try {
            $user = DB::transaction(function () use ($user, $data, $fotoPath) {
                // Guru dengan penugasan operasional harus dipindahkan atau ditutup dulu.
                if ($data['role'] !== 'guru' && $this->hasOperableTeachingAssignments($user)) {
                    throw ValidationException::withMessages([
                        'role' => 'Guru ini masih memiliki penugasan mengajar pada tahun ajaran operasional. Pindahkan atau tutup penugasannya terlebih dahulu.',
                    ]);
                }

                $userUpdate = ['name' => $data['name'], 'email' => $data['email']];
                if (! empty($data['password'])) {
                    $userUpdate['password'] = Hash::make($data['password']);
                }
                $user->update($userUpdate);
                $user->syncRoles([$data['role']]);
                $user->employee()->updateOrCreate(
                    ['user_id' => $user->id],
                    $this->employeeAttributes($data, $fotoPath, $user->employee?->employment_status),
                );

                $this->ensureSubjectsCanBeRemoved($user, $data);
                $this->syncSubjects($user, $data);

                return $user;
            });
        } catch (\Throwable $e) {
            if (isset($data['profile_picture']) && $fotoPath && $fotoPath !== $currentPic) {
                Storage::disk('public')->delete($fotoPath);
            }

            throw $e;
        }

        if (isset($data['profile_picture']) && $currentPic) {
            Storage::disk('public')->delete($currentPic);
        }

        event(new DirectoryChanged($user->id, 'employee', 'updated'));

        return $user;
    }

    public function deleteEmployee(User $user)
    {
        $userId = $user->id;
        $currentPic = $user->employee ? $user->employee->profile_picture : null;
        DB::transaction(function () use ($user) {
            // Riwayat mengajar tetap tersimpan: baris dinonaktifkan, bukan dihapus permanen.
            Classroom::query()->where('homeroom_teacher_id', $user->id)->update(['homeroom_teacher_id' => null]);
            $user->teachingAssignments()->whereNull('deleted_at')->delete();
            $user->employee?->delete();
            $user->tokens()->delete();
            $user->delete();
        });

        if ($currentPic) {
            Storage::disk('public')->delete($currentPic);
        }

        event(new DirectoryChanged($userId, 'employee', 'deleted'));
        event(new SessionInvalidated($userId, 'account_deleted'));
    }

    /** @return array<string, mixed> */
    private function employeeAttributes(array $data, ?string $fotoPath, ?string $currentStatus = null): array
    {
        return [
            'nip' => $data['nip'] ?? null,
            'position' => $data['position'] ?? null,
            'is_teacher' => $data['role'] === 'guru',
            'employment_status' => $data['employment_status'] ?? $currentStatus ?? Employee::STATUS_ACTIVE,
            'gender' => $data['gender'] ?? null,
            'place_of_birth' => $data['place_of_birth'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'religion' => $data['religion'] ?? null,
            'address' => $data['address'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'profile_picture' => $fotoPath,
        ];
    }

    private function hasOperableTeachingAssignments(User $user): bool
    {
        return $user->teachingAssignments()
            ->whereHas('classroom', fn ($classroom) => $classroom
                ->whereNull('classrooms.deleted_at')
                ->where('is_active', true)
                ->whereHas('academicYear', fn ($year) => $year->whereIn('status', AcademicYear::OPERABLE_STATUSES)))
            ->exists();
    }

    private function ensureSubjectsCanBeRemoved(User $user, array $data): void
    {
        if ($data['role'] !== 'guru') {
            return;
        }

        $subjectIds = array_map('intval', $data['subject_ids'] ?? []);
        $removedIds = $user->subjects()->pluck('subjects.id')->diff($subjectIds);

        $blockedSubjects = Subject::query()
            ->whereIn('id', $removedIds)
            ->whereHas('assignments', fn ($assignments) => $assignments
                ->where('teacher_id', $user->id)
                ->whereHas('classroom', fn ($classroom) => $classroom
                    ->whereNull('classrooms.deleted_at')
                    ->where('is_active', true)
                    ->whereHas('academicYear', fn ($year) => $year->whereIn('status', AcademicYear::OPERABLE_STATUSES))))
            ->pluck('name');

        if ($blockedSubjects->isNotEmpty()) {
            throw ValidationException::withMessages([
                'subject_ids' => 'Kualifikasi mapel tidak dapat dicabut karena masih memiliki penugasan aktif: '.$blockedSubjects->implode(', ').'.',
            ]);
        }
    }

    private function syncSubjects(User $user, array $data): void
    {
        if ($data['role'] !== 'guru' || empty($data['subject_ids'])) {
            $user->subjects()->detach();

            return;
        }

        $primaryId = $data['primary_subject_id'] ?? $data['subject_ids'][0];
        $syncData = [];
        foreach ($data['subject_ids'] as $subjectId) {
            $syncData[$subjectId] = ['is_primary' => (int) $subjectId === (int) $primaryId];
        }
        $user->subjects()->sync($syncData);
    }
}
