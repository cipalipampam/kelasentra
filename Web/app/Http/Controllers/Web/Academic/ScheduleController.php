<?php

namespace App\Http\Controllers\Web\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Academic\StoreScheduleRequest;
use App\Http\Requests\Web\Academic\UpdateScheduleRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use App\Services\Web\Academic\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $scheduleService,
    ) {}

    public function index(Request $request): View
    {
        $academicYears = AcademicYear::operableYears();
        $selectedAcademicYearId = $request->integer('academic_year_id')
            ?: (int) (AcademicYear::currentYear()?->getKey() ?? $academicYears->first()?->getKey());

        $classrooms = Classroom::query()
            ->where('academic_year_id', $selectedAcademicYearId)
            ->where('is_active', true)
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $subjects = Subject::where('is_active', true)->orderBy('cluster')->orderBy('name')->get();
        $teachers = User::role('guru')->with('subjects')->orderBy('name')->get();

        $selectedClassroomId = $request->input('classroom_id', $classrooms->first()?->id);
        $selectedDay = $request->input('day_of_week');

        $query = Schedule::with(['assignment.classroom', 'assignment.subject', 'assignment.teacher'])
            ->when($selectedClassroomId, fn ($q) => $q->whereHas(
                'assignment',
                fn ($assignment) => $assignment->where('classroom_id', $selectedClassroomId),
            ))
            ->when($selectedDay, fn ($q) => $q->where('day_of_week', $selectedDay));

        $schedules = $query->orderBy('day_of_week')->orderBy('start_time')->get();

        // Rekapitulasi per hari (Senin - Jumat)
        $daySchedules = [
            1 => $schedules->where('day_of_week', 1),
            2 => $schedules->where('day_of_week', 2),
            3 => $schedules->where('day_of_week', 3),
            4 => $schedules->where('day_of_week', 4),
            5 => $schedules->where('day_of_week', 5),
        ];

        $totalJp = $schedules->sum('jp_count');
        $activeClassroom = $classrooms->firstWhere('id', $selectedClassroomId);

        return view('admin.schedules.index', compact(
            'classrooms',
            'subjects',
            'teachers',
            'schedules',
            'daySchedules',
            'selectedClassroomId',
            'selectedDay',
            'totalJp',
            'activeClassroom',
            'academicYears',
            'selectedAcademicYearId'
        ));
    }

    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active', true);
            $this->scheduleService->createSchedule($data);

            return redirect()->route('admin.schedules.index', ['classroom_id' => $data['classroom_id']])
                ->with('success', 'Jadwal pelajaran berhasil ditambahkan.');
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        try {
            $data = $request->validated();
            $data['is_active'] = $request->boolean('is_active');
            $this->scheduleService->updateSchedule($schedule, $data);

            return redirect()->route('admin.schedules.index', ['classroom_id' => $schedule->classroom_id])
                ->with('success', 'Jadwal pelajaran berhasil diperbarui.');

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $classroomId = $schedule->classroom_id;
        $this->scheduleService->deleteSchedule($schedule);

        return redirect()->route('admin.schedules.index', ['classroom_id' => $classroomId])
            ->with('success', 'Jadwal pelajaran berhasil dihapus.');
    }

    /**
     * Endpoint API internal untuk mengambil guru yang linier dengan mata pelajaran terpilih.
     */
    public function getTeachersBySubject(Subject $subject): JsonResponse
    {
        $teachers = $subject->teachers()
            ->select('users.id', 'users.name', 'users.email')
            ->get()
            ->map(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'is_primary' => (bool) $teacher->pivot->is_primary,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $teachers,
        ]);
    }
}

