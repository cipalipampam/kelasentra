<?php

namespace App\Services\Web\Employee;

use App\Events\DirectoryChanged;
use App\Events\SessionInvalidated;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class EmployeeService
{
    public function createEmployee(array $data)
    {
        $fotoPath = null;
        if (isset($data['profile_picture'])) {
            $fotoPath = $data['profile_picture']->store('pas_foto', 'public');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);

        $user->employee()->create([
            'nip' => $data['nip'] ?? null,
            'position' => $data['position'] ?? null,
            'employment_status' => $data['employment_status'] ?? Employee::STATUS_ACTIVE,
            'gender' => $data['gender'] ?? null,
            'place_of_birth' => $data['place_of_birth'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'religion' => $data['religion'] ?? null,
            'address' => $data['address'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'profile_picture' => $fotoPath,
        ]);

        if ($data['role'] === 'guru' && ! empty($data['subject_ids'])) {
            $syncData = [];
            $primaryId = $data['primary_subject_id'] ?? $data['subject_ids'][0] ?? null;
            foreach ($data['subject_ids'] as $subjectId) {
                $syncData[$subjectId] = [
                    'is_primary' => ((int) $subjectId === (int) $primaryId),
                ];
            }
            $user->subjects()->sync($syncData);
        } else {
            $user->subjects()->detach();
        }

        event(new DirectoryChanged($user->id, 'employee', 'created'));

        return $user;
    }

    public function updateEmployee(User $user, array $data)
    {
        if (! empty($data['password'])) {
            $user->update(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        } else {
            $user->update(['name' => $data['name'], 'email' => $data['email']]);
        }

        $user->syncRoles([$data['role']]);

        $currentPic = $user->employee ? $user->employee->profile_picture : null;
        $fotoPath = $currentPic;

        if (isset($data['profile_picture'])) {
            if ($currentPic) {
                Storage::disk('public')->delete($currentPic);
            }
            $fotoPath = $data['profile_picture']->store('pas_foto', 'public');
        }

        $user->employee()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'nip' => $data['nip'] ?? null,
                'position' => $data['position'] ?? null,
                'employment_status' => $data['employment_status']
                    ?? $user->employee?->employment_status
                    ?? Employee::STATUS_ACTIVE,
                'gender' => $data['gender'] ?? null,
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'religion' => $data['religion'] ?? null,
                'address' => $data['address'] ?? null,
                'phone_number' => $data['phone_number'] ?? null,
                'profile_picture' => $fotoPath,
            ]
        );

        if ($data['role'] === 'guru' && ! empty($data['subject_ids'])) {
            $syncData = [];
            $primaryId = $data['primary_subject_id'] ?? $data['subject_ids'][0] ?? null;
            foreach ($data['subject_ids'] as $subjectId) {
                $syncData[$subjectId] = [
                    'is_primary' => ((int) $subjectId === (int) $primaryId),
                ];
            }
            $user->subjects()->sync($syncData);
        } else {
            $user->subjects()->detach();
        }

        event(new DirectoryChanged($user->id, 'employee', 'updated'));

        return $user;
    }

    public function deleteEmployee(User $user)
    {
        $userId = $user->id;
        $currentPic = $user->employee ? $user->employee->profile_picture : null;
        if ($currentPic) {
            Storage::disk('public')->delete($currentPic);
        }
        $user->tokens()->delete();
        $user->delete();
        event(new DirectoryChanged($userId, 'employee', 'deleted'));
        event(new SessionInvalidated($userId, 'account_deleted'));
    }
}
