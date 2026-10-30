<?php

namespace App\Services\Web\Academic;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use App\Rules\EligibleTeachingTeacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function __construct(
        private readonly TeachingAssignmentService $assignments,
    ) {}

    public const JP_MINUTES = 45;

    public const MIN_JP_PER_WEEK = 24;

    public const MAX_JP_PER_WEEK = 40;

    public const SCHOOL_START_TIME = '07:00';

    public const SCHOOL_END_TIME = '16:00';

    /**
     * Menghitung rincian beban mengajar guru secara komprehensif.
     */
    public function calculateTeacherWeeklyWorkload(User $teacher, ?AcademicYear $academicYear = null): array
    {
        $academicYear ??= AcademicYear::currentYear();

        $schedules = Schedule::query()
            ->with(['assignment.subject', 'assignment.classroom'])
            ->where('is_active', true)
            ->whereHas('assignment', fn ($assignment) => $assignment->where('teacher_id', $teacher->getKey()))
            ->when($academicYear !== null, fn ($query) => $query->whereHas(
                'assignment.classroom',
                fn ($classroom) => $classroom->where('academic_year_id', $academicYear->getKey()),
            ))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $totalMinutes = 0;
        $totalJp = 0;
        $subjectWorkloads = [];
        $dayBreakdown = [
            1 => ['name' => 'Senin', 'jp' => 0, 'minutes' => 0, 'slots' => 0],
            2 => ['name' => 'Selasa', 'jp' => 0, 'minutes' => 0, 'slots' => 0],
            3 => ['name' => 'Rabu', 'jp' => 0, 'minutes' => 0, 'slots' => 0],
            4 => ['name' => 'Kamis', 'jp' => 0, 'minutes' => 0, 'slots' => 0],
            5 => ['name' => 'Jumat', 'jp' => 0, 'minutes' => 0, 'slots' => 0],
        ];

        // Gunakan relasi yang sudah dimuat oleh pemanggil bila tersedia.
        $teacherSubjects = $teacher->relationLoaded('subjects')
            ? $teacher->subjects->keyBy('id')
            : $teacher->subjects()->get()->keyBy('id');

        foreach ($schedules as $sched) {
            $duration = $sched->duration_in_minutes;
            $jp = $sched->jp_count;

            $totalMinutes += $duration;
            $totalJp += $jp;

            $subId = $sched->subject_id;
            if (! isset($subjectWorkloads[$subId])) {
                $isPrimary = false;
                $cluster = $sched->subject->cluster ?? 'umum';
                if ($teacherSubjects->has($subId)) {
                    $isPrimary = (bool) ($teacherSubjects->get($subId)->pivot->is_primary ?? false);
                }

                $subjectWorkloads[$subId] = [
                    'subject' => $sched->subject,
                    'is_primary' => $isPrimary,
                    'cluster' => $cluster,
                    'total_jp' => 0,
                    'total_minutes' => 0,
                    'slots_count' => 0,
                    'classrooms' => [],
                ];
            }

            $subjectWorkloads[$subId]['total_jp'] += $jp;
            $subjectWorkloads[$subId]['total_minutes'] += $duration;
            $subjectWorkloads[$subId]['slots_count']++;
            if ($sched->classroom && ! in_array($sched->classroom->name, $subjectWorkloads[$subId]['classrooms'], true)) {
                $subjectWorkloads[$subId]['classrooms'][] = $sched->classroom->name;
            }

            if (isset($dayBreakdown[$sched->day_of_week])) {
                $dayBreakdown[$sched->day_of_week]['jp'] += $jp;
                $dayBreakdown[$sched->day_of_week]['minutes'] += $duration;
                $dayBreakdown[$sched->day_of_week]['slots']++;
            }
        }

        // Status beban tatap muka regulasi
        $workloadStatus = 'optimal';
        $statusLabel = 'Memenuhi Syarat (24 - 40 JP)';
        $statusColor = 'success';

        if ($totalJp < self::MIN_JP_PER_WEEK) {
            $workloadStatus = 'underload';
            $deficit = self::MIN_JP_PER_WEEK - $totalJp;
            $statusLabel = "Kurang {$deficit} JP dari Batas Minimal (24 JP)";
            $statusColor = 'warning';
        } elseif ($totalJp > self::MAX_JP_PER_WEEK) {
            $workloadStatus = 'overload';
            $excess = $totalJp - self::MAX_JP_PER_WEEK;
            $statusLabel = "Kelebihan {$excess} JP dari Batas Maksimal (40 JP)";
            $statusColor = 'danger';
        }

        $percentage = min(100, round(($totalJp / self::MIN_JP_PER_WEEK) * 100));

        return [
            'total_jp' => $totalJp,
            'total_minutes' => $totalMinutes,
            'total_hours_real' => round($totalMinutes / 60, 1),
            'slots_count' => $schedules->count(),
            'status' => $workloadStatus,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'target_percentage' => $percentage,
            'min_target_jp' => self::MIN_JP_PER_WEEK,
            'max_target_jp' => self::MAX_JP_PER_WEEK,
            'subjects' => array_values($subjectWorkloads),
            'days' => $dayBreakdown,
            'schedules' => $schedules,
        ];
    }

    /**
     * Memvalidasi seluruh aturan operasional KBM, linieritas guru, dan anti-bentrok.
     *
     * @throws ValidationException
     */
    public function validateScheduleSlot(array $data, ?int $ignoreScheduleId = null): void
    {
        $dayOfWeek = (int) $data['day_of_week'];
        $startTime = $data['start_time'];
        $end_time = $data['end_time'];
        $teacherId = (int) $data['teacher_id'];
        $subjectId = (int) $data['subject_id'];
        $classroomId = (int) $data['classroom_id'];

        // 1. Hari Sekolah (Senin - Jumat)
        if ($dayOfWeek < 1 || $dayOfWeek > 5) {
            throw ValidationException::withMessages([
                'day_of_week' => 'Jadwal KBM hanya dapat diselenggarakan pada hari Senin hingga Jumat (Full Day School).',
            ]);
        }

        // 2. Format & Rentang Jam Belajar (07:00 - 16:00)
        $startCarbon = Carbon::parse($startTime);
        $endCarbon = Carbon::parse($end_time);

        if ($startCarbon->greaterThanOrEqualTo($endCarbon)) {
            throw ValidationException::withMessages([
                'end_time' => 'Jam selesai mengajar harus lebih besar dari jam mulai.',
            ]);
        }

        $earliest = Carbon::parse(self::SCHOOL_START_TIME);
        $latest = Carbon::parse(self::SCHOOL_END_TIME);

        if ($startCarbon->format('H:i') < $earliest->format('H:i') || $endCarbon->format('H:i') > $latest->format('H:i')) {
            throw ValidationException::withMessages([
                'start_time' => 'Waktu kegiatan belajar mengajar harus berada di dalam rentang pukul 07:00 hingga 16:00 WIB.',
            ]);
        }

        // 2b. Break Time Guard (Pasal 2 Permendikbud No. 23/2017 - Istirahat I & Ishoma)
        // Istirahat I (Dhuha & Camilan): 09:30 - 10:00 WIB (Senin - Jumat)
        // Istirahat II (Ishoma Utama): 11:45 - 13:00 WIB (Senin - Kamis), 11:30 - 13:00 WIB (Jumat)
        $startStr = $startCarbon->format('H:i');
        $endStr = $endCarbon->format('H:i');

        // Check Istirahat I (09:30 - 10:00)
        if ($startStr < '10:00' && $endStr > '09:30') {
            throw ValidationException::withMessages([
                'start_time' => 'Jadwal KBM bertabrakan dengan Istirahat I / Sholat Dhuha (09:30 - 10:00 WIB).',
            ]);
        }

        // Check Istirahat II / Ishoma Utama
        $ishomaStart = ($dayOfWeek == 5) ? '11:30' : '11:45';
        $ishomaEnd = '13:00';
        if ($startStr < $ishomaEnd && $endStr > $ishomaStart) {
            $label = ($dayOfWeek == 5) ? 'Sholat Jumat & Makan Siang (11:30 - 13:00 WIB)' : 'Ishoma Utama & Sholat Dzuhur (11:45 - 13:00 WIB)';
            throw ValidationException::withMessages([
                'start_time' => "Jadwal KBM bertabrakan dengan waktu {$label}.",
            ]);
        }

        // 3. Durasi Minimal (1 JP = 45 menit)
        $durationMinutes = $startCarbon->diffInMinutes($endCarbon);
        if ($durationMinutes < self::JP_MINUTES) {
            throw ValidationException::withMessages([
                'end_time' => 'Durasi mengajar minimal adalah 1 JP (45 menit).',
            ]);
        }

        // 4. Kelayakan guru: peran guru, tenaga pengajar aktif, dan linier mapel.
        $teacher = User::with(['employee', 'subjects'])->find($teacherId);
        if (! $teacher) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Data guru tidak ditemukan.',
            ]);
        }

        if ($reason = EligibleTeachingTeacher::reason($teacher, $subjectId)) {
            throw ValidationException::withMessages([
                'teacher_id' => $reason,
            ]);
        }

        $classroom = Classroom::with('academicYear')->find($classroomId);
        if (! $classroom) {
            throw ValidationException::withMessages([
                'classroom_id' => 'Data rombel kelas tidak ditemukan.',
            ]);
        }

        if (! $classroom->is_active) {
            throw ValidationException::withMessages([
                'classroom_id' => "Rombel {$classroom->name} tidak aktif sehingga tidak dapat menerima jadwal baru.",
            ]);
        }

        if (! $classroom->academicYear?->isOperable()) {
            throw ValidationException::withMessages([
                'classroom_id' => "Tahun ajaran rombel {$classroom->name} sudah selesai sehingga tidak dapat menerima jadwal.",
            ]);
        }

        $academicYearId = $classroom->academic_year_id;

        // 5. Anti-Bentrok Guru, dibatasi tahun ajaran yang sama agar penyusunan
        //    jadwal tahun berikutnya tidak dianggap bentrok.
        $teacherClash = Schedule::query()
            ->with(['assignment.classroom'])
            ->where('is_active', true)
            ->whereHas('assignment', fn ($assignment) => $assignment->where('teacher_id', $teacherId))
            ->when($academicYearId !== null, fn ($query) => $query->whereHas(
                'assignment.classroom',
                fn ($clashClassroom) => $clashClassroom->where('academic_year_id', $academicYearId),
            ))
            ->overlapping($dayOfWeek, $startTime, $end_time, $ignoreScheduleId)
            ->first();

        if ($teacherClash) {
            $clashClass = $teacherClash->classroom->name ?? 'Kelas lain';
            $clashRange = substr($teacherClash->start_time, 0, 5).' - '.substr($teacherClash->end_time, 0, 5);
            throw ValidationException::withMessages([
                'teacher_id' => "Bentrok jadwal! Guru {$teacher->name} sudah memiliki jadwal di {$clashClass} pada pukul {$clashRange}.",
            ]);
        }

        // 6. Anti-Bentrok Rombel (satu rombel hanya milik satu tahun ajaran)
        $classroomClash = Schedule::query()
            ->with(['assignment.subject'])
            ->where('is_active', true)
            ->whereHas('assignment', fn ($assignment) => $assignment->where('classroom_id', $classroomId))
            ->overlapping($dayOfWeek, $startTime, $end_time, $ignoreScheduleId)
            ->first();

        if ($classroomClash) {
            $clashSub = $classroomClash->subject->name ?? 'Mata Pelajaran lain';
            $clashRange = substr($classroomClash->start_time, 0, 5).' - '.substr($classroomClash->end_time, 0, 5);
            throw ValidationException::withMessages([
                'classroom_id' => "Bentrok jadwal! Rombel kelas ini sudah memiliki kegiatan pembelajaran {$clashSub} pada pukul {$clashRange}.",
            ]);
        }
    }

    /**
     * Membuat jadwal baru dengan validasi integritas.
     */
    public function createSchedule(array $data): Schedule
    {
        $this->validateScheduleSlot($data);

        return Schedule::create($this->scheduleAttributes($data));
    }

    /**
     * Memperbarui jadwal dengan validasi integritas.
     */
    public function updateSchedule(Schedule $schedule, array $data): bool
    {
        $schedule->loadMissing('assignment.classroom.academicYear');
        if (! $schedule->classroom?->academicYear?->isOperable()) {
            throw ValidationException::withMessages([
                'classroom_id' => 'Jadwal pada tahun ajaran yang sudah selesai tidak dapat diubah.',
            ]);
        }

        if (! $schedule->classroom?->is_active) {
            throw ValidationException::withMessages([
                'classroom_id' => "Rombel {$schedule->classroom?->name} tidak aktif sehingga jadwal tidak dapat diubah.",
            ]);
        }

        $this->validateScheduleSlot($data, $schedule->getKey());

        $currentAssignment = $schedule->assignment;
        $teacherChanged = $currentAssignment !== null && (int) $currentAssignment->teacher_id !== (int) $data['teacher_id'];

        if ($teacherChanged) {
            $academicYearId = $schedule->classroom?->academic_year_id;
            // A-01: Pergantian guru pada rombel+mapel ini memengaruhi seluruh slot aktif lainnya.
            // Pastikan guru pengganti tidak memiliki jadwal bentrok pada slot-slot lain tersebut.
            $otherSchedules = Schedule::query()
                ->where('teaching_assignment_id', $currentAssignment->getKey())
                ->whereKeyNot($schedule->getKey())
                ->where('is_active', true)
                ->get();

            foreach ($otherSchedules as $other) {
                $clash = Schedule::query()
                    ->where('is_active', true)
                    ->whereHas('assignment', fn ($q) => $q->where('teacher_id', $data['teacher_id']))
                    ->when($academicYearId !== null, fn ($query) => $query->whereHas(
                        'assignment.classroom',
                        fn ($clashClassroom) => $clashClassroom->where('academic_year_id', $academicYearId),
                    ))
                    ->overlapping($other->day_of_week, $other->start_time, $other->end_time)
                    ->first();

                if ($clash) {
                    $clashClass = $clash->classroom?->name ?? 'Kelas lain';
                    $clashRange = substr($clash->start_time, 0, 5).' - '.substr($clash->end_time, 0, 5);
                    $dayName = ['1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu', '4' => 'Kamis', '5' => 'Jumat'][$other->day_of_week] ?? "Hari {$other->day_of_week}";
                    throw ValidationException::withMessages([
                        'teacher_id' => "Pergantian guru memengaruhi seluruh slot mapel ini, namun Guru pengganti bentrok pada hari {$dayName} di {$clashClass} ({$clashRange}).",
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($schedule, $data) {
            return $schedule->update($this->scheduleAttributes($data, $schedule));
        });
    }

    /**
     * Menyiapkan atribut jadwal, termasuk penugasan guru yang menjadi konteksnya.
     *
     * @return array<string, mixed>
     */
    private function scheduleAttributes(array $data, ?Schedule $schedule = null): array
    {
        $assignment = $this->assignments->assign(
            Classroom::findOrFail($data['classroom_id']),
            Subject::findOrFail($data['subject_id']),
            User::findOrFail($data['teacher_id']),
        );

        return [
            'teaching_assignment_id' => $assignment->getKey(),
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'room' => $data['room'] ?? null,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : ($schedule?->is_active ?? true),
        ];
    }

    /**
     * Menghapus jadwal.
     */
    public function deleteSchedule(Schedule $schedule): bool
    {
        $schedule->loadMissing('assignment.classroom.academicYear');
        if (! $schedule->classroom?->academicYear?->isOperable()) {
            throw ValidationException::withMessages([
                'classroom_id' => 'Jadwal pada tahun ajaran yang sudah selesai tidak dapat dihapus.',
            ]);
        }

        return (bool) $schedule->delete();
    }
}
