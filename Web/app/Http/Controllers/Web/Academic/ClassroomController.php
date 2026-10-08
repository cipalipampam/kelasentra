<?php

namespace App\Http\Controllers\Web\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Academic\ProcessClassPromotionRequest;
use App\Http\Requests\Web\Academic\StoreClassroomRequest;
use App\Http\Requests\Web\Academic\UpdateClassroomRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Services\Web\Academic\ClassroomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function __construct(
        private readonly ClassroomService $classroomService,
    ) {}

    public function index(Request $request): View
    {
        $classrooms = $this->classroomService->getPaginatedClassrooms(
            $request->only(['level', 'major', 'search']),
            10,
        );

        $teachers = $this->classroomService->getEligibleHomeroomTeachers();
        $homeroomAssignments = $this->classroomService->getHomeroomAssignments();
        $academicYears = AcademicYear::selectableNames();
        $activeAcademicYear = AcademicYear::query()
            ->where('status', AcademicYear::STATUS_ACTIVE)
            ->orderByDesc('name')
            ->value('name');
        $majors = config('classroom.majors');
        $studentCapacity = Classroom::studentCapacity();

        $stats = [
            'total' => Classroom::count(),
            'active' => Classroom::where('is_active', true)->count(),
            'inactive' => Classroom::where('is_active', false)->count(),
        ];

        return view('admin.classrooms.index', compact(
            'classrooms',
            'teachers',
            'homeroomAssignments',
            'academicYears',
            'activeAcademicYear',
            'majors',
            'studentCapacity',
            'stats',
        ));
    }

    public function store(StoreClassroomRequest $request): RedirectResponse
    {
        $this->classroomService->createClassroom($request->validated());

        return redirect()->route('admin.classrooms.index')
            ->with('success', 'Rombel kelas berhasil ditambahkan.');
    }

    public function update(UpdateClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $this->classroomService->updateClassroom($classroom, $data);

        return redirect()->route('admin.classrooms.index')
            ->with('success', 'Data rombel kelas berhasil diperbarui.');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $deleted = $this->classroomService->deleteClassroom($classroom);

        if (! $deleted) {
            return redirect()->route('admin.classrooms.index')
                ->with('error', 'Tidak dapat menghapus kelas karena masih memiliki siswa terdaftar.');
        }

        return redirect()->route('admin.classrooms.index')
            ->with('success', 'Rombel kelas berhasil dihapus.');
    }

    /**
     * Halaman antarmuka Kenaikan Kelas Massal & Kelulusan Siswa.
     */
    public function promotion(Request $request): View
    {
        $allClassrooms = $this->classroomService->getAllActiveClassrooms();
        $sourceClassroomId = $request->input('source_classroom_id');
        $selectedClassroom = null;
        $students = collect();

        if ($sourceClassroomId) {
            $selectedClassroom = Classroom::find($sourceClassroomId);
            if ($selectedClassroom) {
                $students = $this->classroomService->getStudentsByClassroom($selectedClassroom->id, 'active');
            }
        }

        return view('admin.classrooms.promotion', compact(
            'allClassrooms',
            'selectedClassroom',
            'students',
        ) + ['studentCapacity' => Classroom::studentCapacity()]);
    }

    /**
     * AJAX endpoint untuk mengambil daftar siswa aktif di suatu rombel kelas.
     */
    public function getStudents(Classroom $classroom): JsonResponse
    {
        $students = $this->classroomService->getStudentsByClassroom($classroom->id, 'active');

        $data = $students->map(function ($student) {
            return [
                'id' => $student->id,
                'nis' => $student->nis ?? '-',
                'nisn' => $student->nisn ?? '-',
                'name' => $student->user?->name ?? 'Siswa #'.$student->id,
                'gender' => $student->gender ?? '-',
                'academic_status' => $student->academic_status ?? 'active',
                'profile_picture' => $student->profile_picture,
            ];
        });

        return response()->json([
            'success' => true,
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'level' => $classroom->level,
                'major' => $classroom->major,
                'academic_year' => $classroom->academic_year,
            ],
            'total' => $data->count(),
            'students' => $data,
        ]);
    }

    /**
     * Memproses batch kenaikan kelas atau kelulusan siswa terpilih.
     */
    public function processPromotion(ProcessClassPromotionRequest $request): RedirectResponse
    {
        $result = $this->classroomService->processPromotion($request->validated());

        if ($result['action'] === 'promote') {
            $message = "Sukses! Sebanyak {$result['processed_count']} siswa dari {$result['source_class']} berhasil dipromosikan ke {$result['target_class']}.";
        } else {
            $message = "Sukses! Sebanyak {$result['processed_count']} siswa dari {$result['source_class']} telah diproses status kelulusannya.";
        }

        return redirect()->route('admin.classrooms.promotion')
            ->with('success', $message);
    }
}
