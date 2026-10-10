<?php

namespace App\Http\Controllers\Web\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Academic\ProcessClassPromotionRequest;
use App\Http\Requests\Web\Academic\StoreClassroomRequest;
use App\Http\Requests\Web\Academic\UpdateClassroomRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\PromotionBatch;
use App\Services\Web\Academic\ClassroomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function __construct(
        private readonly ClassroomService $classroomService,
    ) {}

    public function index(Request $request): View
    {
        $showHistory = $request->boolean('show_history');

        $classrooms = $this->classroomService->getPaginatedClassrooms(
            $request->only(['level', 'major', 'search', 'show_history']),
            10,
        );

        $teachers = $this->classroomService->getEligibleHomeroomTeachers();
        $homeroomAssignments = $this->classroomService->getHomeroomAssignments();
        $sectionOverview = $this->classroomService->getSectionOverview();
        $academicYears = AcademicYear::operableYears();
        $allAcademicYears = AcademicYear::with('classrooms:id,academic_year_id,name,level,major,section')
            ->orderByDesc('start_year')
            ->get();
        $currentAcademicYear = AcademicYear::currentYear();
        $majors = config('classroom.majors');
        $studentCapacity = Classroom::studentCapacity();

        $stats = [
            'total'    => Classroom::count(),
            'active'   => Classroom::where('is_active', true)->count(),
            'inactive' => Classroom::where('is_active', false)->count(),
        ];

        return view('admin.classrooms.index', compact(
            'classrooms',
            'showHistory',
            'teachers',
            'homeroomAssignments',
            'sectionOverview',
            'academicYears',
            'allAcademicYears',
            'currentAcademicYear',
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

        $releasedTeacher = $data['is_active'] ? null : $classroom->homeroomTeacher?->name;

        $this->classroomService->updateClassroom($classroom, $data);

        $message = 'Data rombel kelas berhasil diperbarui.';

        if ($releasedTeacher !== null) {
            $message .= " Rombel {$classroom->name} dinonaktifkan sehingga wali kelas {$releasedTeacher} dilepas dan bisa dirotasi ke rombel lain.";
        }

        return redirect()->route('admin.classrooms.index')
            ->with('success', $message);
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

        return view('admin.classrooms.promotion', [
            'allClassrooms' => $allClassrooms,
            'selectedClassroom' => $selectedClassroom,
            'students' => $students,
            'studentCapacity' => Classroom::studentCapacity(),
            'promotionHistory' => $this->classroomService->getPromotionHistory(),
        ]);
    }

    /**
     * Endpoint AJAX daftar siswa aktif di suatu rombel kelas.
     *
     * Mengembalikan HTML baris tabel, bukan JSON, agar markup baris hanya
     * ada di satu partial yang dipakai bersama render server-side.
     */
    public function getStudents(Classroom $classroom): Response
    {
        return response()->view('admin.classrooms.components.promotion.student-rows', [
            'students' => $this->classroomService->getStudentsByClassroom($classroom->id, 'active'),
        ]);
    }

    /**
     * Memproses batch kenaikan kelas atau kelulusan siswa terpilih.
     */
    public function processPromotion(ProcessClassPromotionRequest $request): RedirectResponse
    {
        $batch = $this->classroomService->processPromotion($request->validated(), $request->user());

        $message = $batch->action === PromotionBatch::ACTION_PROMOTE
            ? "Sukses! Sebanyak {$batch->student_count} siswa dari {$batch->source_classroom_name} berhasil dipromosikan ke {$batch->target_classroom_name}."
            : "Sukses! Sebanyak {$batch->student_count} siswa dari {$batch->source_classroom_name} telah diproses status kelulusannya.";

        return redirect()->route('admin.classrooms.promotion')
            ->with('success', $message);
    }

    /**
     * Membatalkan satu batch kenaikan kelas / kelulusan.
     */
    public function revertPromotion(Request $request, PromotionBatch $promotionBatch): RedirectResponse
    {
        $result = $this->classroomService->revertBatch($promotionBatch, $request->user());

        $message = "Batch {$promotionBatch->actionLabel()} dari {$promotionBatch->source_classroom_name} dibatalkan: {$result['restored']} siswa dikembalikan.";

        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} siswa dilewati karena datanya sudah berubah sejak batch dijalankan.";
        }

        if ($result['missing_classroom'] > 0) {
            $message .= " {$result['missing_classroom']} siswa dilewati karena rombel asalnya sudah dihapus.";
        }

        return redirect()->route('admin.classrooms.promotion')
            ->with('success', $message);
    }

    /**
     * Menduplikasi batch rombel dari satu tahun ajaran ke tahun ajaran lain.
     */
    public function duplicateClassrooms(Request $request): RedirectResponse
    {
        $request->validate([
            'source_academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'target_academic_year_id' => ['required', 'integer', 'exists:academic_years,id', 'different:source_academic_year_id'],
            'classroom_ids'           => ['required', 'array', 'min:1'],
            'classroom_ids.*'         => ['integer', 'exists:classrooms,id'],
        ], [
            'source_academic_year_id.required' => 'Tahun ajaran sumber wajib dipilih.',
            'target_academic_year_id.required' => 'Tahun ajaran tujuan wajib dipilih.',
            'target_academic_year_id.different' => 'Tahun ajaran tujuan harus berbeda dari sumber.',
            'classroom_ids.required'           => 'Pilih minimal satu rombel untuk diduplikasi.',
        ]);

        $result = $this->classroomService->duplicateClassroomsFromYear(
            $request->input('classroom_ids', []),
            (int) $request->input('target_academic_year_id'),
        );

        $message = "{$result['created']} rombel berhasil diduplikasi ke tahun ajaran tujuan.";

        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} rombel dilewati karena nama sudah ada di tahun tujuan.";
        }

        return redirect()->route('admin.classrooms.index')
            ->with('success', $message);
    }
}
