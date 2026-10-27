<?php

namespace App\Services\Web\Student;

use App\Events\DirectoryChanged;
use App\Events\SessionInvalidated;
use App\Models\Classroom;
use App\Models\User;
use App\Services\Web\Academic\EnrollmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StudentService
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
    ) {}

    public function createStudent(array $data)
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

                $user->assignRole('siswa');

                // Rombel wajib untuk siswa baru; kelas sekarang disimpan sebagai enrollment.
                $classroom = Classroom::findOrFail($data['classroom_id']);

                $student = $user->student()->create([
                    'entry_academic_year_id' => $classroom->academic_year_id,
                    'nis' => $data['nis'] ?? null,
                    'nisn' => $data['nisn'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'place_of_birth' => $data['place_of_birth'] ?? null,
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'religion' => $data['religion'] ?? null,
                    'address' => $data['address'] ?? null,
                    'phone_number' => $data['phone_number'] ?? null,
                    'profile_picture' => $fotoPath,
                    'academic_status' => 'active',
                ]);

                $this->enrollments->place($student, $classroom);

                return $user;
            });
        } catch (\Throwable $e) {
            if ($fotoPath) {
                Storage::disk('public')->delete($fotoPath);
            }
            throw $e;
        }

        event(new DirectoryChanged($user->id, 'siswa', 'created'));

        return $user;
    }

    public function updateStudent(User $user, array $data)
    {
        $currentPic = $user->student ? $user->student->profile_picture : null;
        $fotoPath = $currentPic;

        if (isset($data['profile_picture'])) {
            $fotoPath = $data['profile_picture']->store('pas_foto', 'public');
        }

        try {
            $user = DB::transaction(function () use ($user, $data, $fotoPath) {
                $userUpdate = ['name' => $data['name'], 'email' => $data['email']];
                if (! empty($data['password'])) {
                    $userUpdate['password'] = Hash::make($data['password']);
                }
                $user->update($userUpdate);

                $student = $user->student()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nis' => $data['nis'] ?? null,
                        'nisn' => $data['nisn'] ?? null,
                        'gender' => $data['gender'] ?? null,
                        'place_of_birth' => $data['place_of_birth'] ?? null,
                        'date_of_birth' => $data['date_of_birth'] ?? null,
                        'religion' => $data['religion'] ?? null,
                        'address' => $data['address'] ?? null,
                        'phone_number' => $data['phone_number'] ?? null,
                        'profile_picture' => $fotoPath,
                    ]
                );

                // Status non-aktif (pindah sekolah / keluar) menutup enrollment berjalan.
                $requestedStatus = $data['academic_status'] ?? null;

                if ($requestedStatus !== null && $requestedStatus !== $student->academic_status) {
                    $student->update(['academic_status' => $requestedStatus]);
                }

                if (! $student->isActive()) {
                    // Invariant spec §4.3: siswa non-aktif tidak boleh memiliki enrollment berjalan.
                    $this->enrollments->closeCurrent($student);
                } elseif (filled($data['classroom_id'] ?? null)) {
                    $classroom = Classroom::findOrFail($data['classroom_id']);

                    // Angkatan dicatat saat pertama kali ditempatkan, tidak ikut berubah.
                    if ($student->entry_academic_year_id === null) {
                        $student->update(['entry_academic_year_id' => $classroom->academic_year_id]);
                    }

                    $this->enrollments->place($student, $classroom);
                }

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

        event(new DirectoryChanged($user->id, 'siswa', 'updated'));

        return $user;
    }

    public function deleteStudent(User $user)
    {
        $userId = $user->id;
        $currentPic = $user->student ? $user->student->profile_picture : null;

        DB::transaction(function () use ($user) {
            // Riwayat tetap tersimpan: baris dinonaktifkan, bukan dihapus permanen.
            if ($student = $user->student) {
                $this->enrollments->closeCurrent($student);
                $student->delete();
            }

            $user->tokens()->delete();
            $user->delete();
        });

        if ($currentPic) {
            Storage::disk('public')->delete($currentPic);
        }

        event(new DirectoryChanged($userId, 'siswa', 'deleted'));
        event(new SessionInvalidated($userId, 'account_deleted'));
    }
}
