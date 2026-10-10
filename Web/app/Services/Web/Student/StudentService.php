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
        ]);

        $this->enrollments->place($student, $classroom);

        event(new DirectoryChanged($user->id, 'siswa', 'created'));

        return $user;
    }

    public function updateStudent(User $user, array $data)
    {
        if (! empty($data['password'])) {
            $user->update(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        } else {
            $user->update(['name' => $data['name'], 'email' => $data['email']]);
        }

        $currentPic = $user->student ? $user->student->profile_picture : null;
        $fotoPath = $currentPic;

        if (isset($data['profile_picture'])) {
            if ($currentPic) {
                Storage::disk('public')->delete($currentPic);
            }
            $fotoPath = $data['profile_picture']->store('pas_foto', 'public');
        }

        // Rombel opsional saat mengedit agar data alumni tidak dipaksa masuk rombel.
        $classroom = filled($data['classroom_id'] ?? null)
            ? Classroom::findOrFail($data['classroom_id'])
            : null;

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

            if ($requestedStatus !== 'active') {
                $this->enrollments->closeCurrent($student);
            }
        }

        if ($classroom) {
            // Angkatan dicatat saat pertama kali ditempatkan, tidak ikut berubah.
            if ($student->entry_academic_year_id === null) {
                $student->update(['entry_academic_year_id' => $classroom->academic_year_id]);
            }

            $this->enrollments->place($student, $classroom);
        }

        event(new DirectoryChanged($user->id, 'siswa', 'updated'));

        return $user;
    }

    public function deleteStudent(User $user)
    {
        $userId = $user->id;
        $currentPic = $user->student ? $user->student->profile_picture : null;
        if ($currentPic) {
            Storage::disk('public')->delete($currentPic);
        }

        DB::transaction(function () use ($user) {
            // Riwayat tetap tersimpan: baris dinonaktifkan, bukan dihapus permanen.
            if ($student = $user->student) {
                $this->enrollments->closeCurrent($student);
                $student->delete();
            }

            $user->tokens()->delete();
            $user->delete();
        });

        event(new DirectoryChanged($userId, 'siswa', 'deleted'));
        event(new SessionInvalidated($userId, 'account_deleted'));
    }
}
