<?php

namespace App\Services\Web\Attendance;

use App\Events\AdminAttendanceChanged;
use App\Events\AttendanceApproved;
use App\Events\DashboardStatsUpdated;
use App\Models\Attendance;
use App\Models\User;
use App\Services\Shared\Attendance\AttendanceContext;
use App\Services\Shared\Storage\AttendanceProofStorage;

class AdminAttendanceService
{
    public function __construct(
        private readonly AttendanceProofStorage $proofStorage,
    ) {}

    public function create(array $data): Attendance
    {
        $data['proof_image'] = isset($data['proof_image'])
            ? $this->proofStorage->store($data['proof_image'])
            : null;
        $context = AttendanceContext::forUser(User::find($data['user_id'] ?? null));

        $attendance = Attendance::create(array_merge($context, $data));
        $this->notify($attendance, 'created');

        return $attendance;
    }

    public function update(Attendance $attendance, array $data): Attendance
    {
        if (isset($data['user_id']) && (int) $data['user_id'] !== $attendance->user_id) {
            $data = array_merge(AttendanceContext::forUser(User::find($data['user_id'])), $data);
        }

        if (isset($data['proof_image'])) {
            $newProof = $this->proofStorage->store($data['proof_image']);
            $this->proofStorage->delete($attendance->proof_image);
            $data['proof_image'] = $newProof;
        } else {
            unset($data['proof_image']);
        }

        $attendance->update($data);
        $this->notify($attendance, 'updated');

        return $attendance;
    }

    public function resolve(Attendance $attendance, string $action): void
    {
        if ($action === 'approve') {
            $attendance->update(['is_approved' => true]);
            event(new AttendanceApproved($attendance, 'Pengajuan absensi Anda telah disetujui.'));
            $this->notify($attendance, 'approved');

            return;
        }

        $attendance->update(['is_approved' => false, 'status' => 'absent']);
        event(new AttendanceApproved($attendance, 'Pengajuan absensi Anda ditolak.'));
        $this->notify($attendance, 'rejected');
    }

    public function delete(Attendance $attendance): void
    {
        $this->proofStorage->delete($attendance->proof_image);
        $attendance->delete();
        $this->notify($attendance, 'deleted');
    }

    private function notify(Attendance $attendance, string $action): void
    {
        event(AdminAttendanceChanged::fromAttendance($attendance, $action));
        event(new DashboardStatsUpdated);
    }
}
