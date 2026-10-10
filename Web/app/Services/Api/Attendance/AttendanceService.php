<?php

namespace App\Services\Api\Attendance;

use App\Events\AttendanceLogged;
use App\Events\DashboardStatsUpdated;
use App\Models\Attendance;
use App\Models\User;
use App\Services\Shared\Attendance\AttendanceContext;
use App\Services\Shared\Settings\SettingCache;
use App\Services\Shared\Storage\AttendanceProofStorage;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceProofStorage $proofStorage,
    ) {}

    /**
     * Submit check in with geolocation boundaries.
     */
    public function checkIn(array $data, User $user)
    {
        // 1. Validate if user already checked in today
        $startOfDay = Carbon::today();
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->where('recorded_at', '>=', $startOfDay)
            ->where('recorded_at', '<', $startOfDay->copy()->addDay())
            ->first();

        if ($todayAttendance) {
            throw ValidationException::withMessages([
                'attendance' => ['Anda sudah melakukan presensi hari ini.'],
            ]);
        }

        // 2. Fetch school coordinates and radius (single cached read)
        $settings = SettingCache::all();
        $schoolLat = $settings->get('school_lat');
        $schoolLong = $settings->get('school_long');
        $schoolRadius = $settings->get('school_radius');

        if (! $schoolLat || ! $schoolLong || ! $schoolRadius) {
            throw ValidationException::withMessages([
                'attendance' => ['Konfigurasi lokasi sekolah belum diatur oleh admin.'],
            ]);
        }

        // 3. Calculate Haversine distance
        $distance = $this->calculateDistance(
            (float) $schoolLat,
            (float) $schoolLong,
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        if ($distance > (int) $schoolRadius) {
            throw ValidationException::withMessages([
                'attendance' => [sprintf('Anda berada di luar jangkauan presensi sekolah. (Jarak anda: %dm, maksimal: %dm)', round($distance), $schoolRadius)],
            ]);
        }

        // 4. Handle time restrictions
        $checkInEndStr = $settings->get('check_in_end');
        if (! $checkInEndStr) {
            throw ValidationException::withMessages([
                'attendance' => ['Konfigurasi jam batas presensi masuk belum diatur oleh admin.'],
            ]);
        }
        $toleranceMinutes = (int) $settings->get('late_tolerance_minutes', 0);

        $cutoffOnTime = Carbon::createFromTimeString($checkInEndStr);
        $cutoffLate = $cutoffOnTime->copy()->addMinutes($toleranceMinutes);
        $currentTime = Carbon::now();

        if ($currentTime->greaterThan($cutoffLate)) {
            throw ValidationException::withMessages([
                'attendance' => ['Batas toleransi terlambat habis (Maks. '.$cutoffLate->format('H:i').'). Pintu absensi masuk telah ditutup. Anda tercatat Alfa.'],
            ]);
        }
        $isLate = $currentTime->greaterThan($cutoffOnTime);

        // 5. Handle proof image if any
        $proofPath = null;
        if (isset($data['proof_image'])) {
            $proofPath = $this->proofStorage->store($data['proof_image']);
        }

        $attendance = $this->persistAttendance(function () use ($user, $data, $isLate, $proofPath) {
            $record = Attendance::create(array_merge(AttendanceContext::forUser($user), [
                'user_id' => $user->id,
                'status' => 'present',
                'is_late' => $isLate,
                'is_approved' => true,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'notes' => $data['notes'] ?? null,
                'proof_image' => $proofPath,
                'recorded_at' => Carbon::now(),
            ]));

            event(new AttendanceLogged($record, 'check_in'));
            event(new DashboardStatsUpdated);

            return $record;
        });

        return $attendance;
    }

    /**
     * Submit leave or sickness without geolocation.
     */
    public function submitPermission(array $data, User $user)
    {
        $startOfDay = Carbon::today();
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->where('recorded_at', '>=', $startOfDay)
            ->where('recorded_at', '<', $startOfDay->copy()->addDay())
            ->first();

        if ($todayAttendance) {
            throw ValidationException::withMessages([
                'attendance' => ['Anda sudah mengirim data untuk hari ini.'],
            ]);
        }

        $proofPath = null;
        if (isset($data['proof_image'])) {
            $proofPath = $this->proofStorage->store($data['proof_image']);
        }

        $attendance = $this->persistAttendance(function () use ($user, $data, $proofPath) {
            $record = Attendance::create(array_merge(AttendanceContext::forUser($user), [
                'user_id' => $user->id,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'proof_image' => $proofPath,
                'recorded_at' => Carbon::now(),
            ]));

            event(new AttendanceLogged($record, 'permission_submitted'));
            event(new DashboardStatsUpdated);

            return $record;
        });

        return $attendance;
    }

    public function checkOut(array $data, User $user)
    {
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
            ->where('recorded_at', '>=', $today)
            ->where('recorded_at', '<', $today->copy()->addDay())
            ->where('status', 'present')
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'attendance' => ['Anda belum melakukan absensi masuk (Check-in) hari ini.'],
            ]);
        }

        if ($attendance->check_out_time) {
            throw ValidationException::withMessages([
                'attendance' => ['Anda sudah melakukan absensi pulang hari ini.'],
            ]);
        }

        $checkOutStartStr = SettingCache::get('check_out_start');
        if (! $checkOutStartStr) {
            throw ValidationException::withMessages([
                'attendance' => ['Konfigurasi jam mulai absensi pulang belum diatur oleh admin.'],
            ]);
        }
        $checkOutStart = Carbon::createFromTimeString($checkOutStartStr);
        $currentTime = Carbon::now();

        if ($currentTime->lessThan($checkOutStart)) {
            throw ValidationException::withMessages([
                'attendance' => ['Waktu absensi pulang belum dimulai (Minimal '.$checkOutStart->format('H:i').').'],
            ]);
        }

        DB::transaction(function () use ($attendance, $currentTime) {
            $attendance->update(['check_out_time' => $currentTime]);

            event(new AttendanceLogged($attendance, 'check_out'));
            event(new DashboardStatsUpdated);
        });

        return $attendance;
    }

    /**
     * Paginated and filtered History.
     */
    public function history(User $user, $month = null, $year = null)
    {
        $query = Attendance::where('user_id', $user->id)->orderBy('recorded_at', 'desc');

        if ($month || $year) {
            $start = Carbon::create($year ?: now()->year, $month ?: now()->month)->startOfMonth();
            $query->where('recorded_at', '>=', $start)
                ->where('recorded_at', '<', $start->copy()->addMonth());
        }

        return $query->paginate(15)->through(function (Attendance $attendance) {
            $attendance->proof_url = $attendance->proof_image
                ? URL::temporarySignedRoute('api.attendances.proof', now()->addMinutes(15), ['attendance' => $attendance])
                : null;

            return $attendance;
        });
    }

    /**
     * Calculate Distance in meters using Haversine algorithm.
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * The database is the final guard against duplicate concurrent requests.
     */
    private function persistAttendance(Closure $callback): Attendance
    {
        try {
            return DB::transaction($callback);
        } catch (QueryException $exception) {
            $message = $exception->getMessage();
            $sqlState = $exception->getCode();

            // SQLSTATE 23000 (Integrity constraint violation in MySQL/SQLite) or 23505 (PostgreSQL)
            $isUniqueViolation = in_array($sqlState, ['23000', '23505', 23000]);
            $isAttendanceDateDuplicate = (
                str_contains($message, 'attendances_user_date_unique') ||
                (str_contains($message, 'attendance_date') && str_contains($message, 'user_id'))
            );

            if ($isUniqueViolation && $isAttendanceDateDuplicate) {
                throw ValidationException::withMessages([
                    'attendance' => ['Anda sudah melakukan presensi hari ini.'],
                ]);
            }

            throw $exception;
        }
    }
}
