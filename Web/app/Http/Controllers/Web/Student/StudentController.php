<?php

namespace App\Http\Controllers\Web\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Student\StoreStudentRequest;
use App\Http\Requests\Web\Student\UpdateStudentRequest;
use App\Models\User;
use App\Services\Web\Academic\ClassroomService;
use App\Services\Web\Student\StudentService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    protected $studentService;

    protected $classroomService;

    public function __construct(StudentService $studentService, ClassroomService $classroomService)
    {
        $this->studentService = $studentService;
        $this->classroomService = $classroomService;
    }

    public function index(Request $request)
    {
        $query = User::with([
            'student.currentEnrollment.classroom',
            'student.latestEnrollment.classroom',
            'roles',
        ])->role('siswa');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('nisn', 'like', "%{$search}%")
                            ->orWhere('nis', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('classroom_id')) {
            $classroomId = (int) $request->input('classroom_id');
            $query->whereHas('student.currentEnrollment', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            });
        }

        // Handle sorting
        if ($request->has('sort') && in_array($request->sort, ['name', 'classroom'])) {
            $direction = $request->direction === 'desc' ? 'desc' : 'asc';

            if ($request->sort === 'name') {
                $query->orderBy('name', $direction);
            } else {
                $query->leftJoin('students', 'users.id', '=', 'students.user_id')
                    ->leftJoin('student_enrollments', function ($join) {
                        $join->on('student_enrollments.student_id', '=', 'students.id')
                            ->whereNull('student_enrollments.ended_at');
                    })
                    ->leftJoin('classrooms', 'classrooms.id', '=', 'student_enrollments.classroom_id')
                    ->orderBy('classrooms.level', $direction)
                    ->orderBy('classrooms.name', $direction)
                    ->select('users.*');
            }
        } else {
            $query->orderBy('users.created_at', 'desc');
        }

        $students = $query->paginate($request->input('per_page', 10));

        $classrooms = $this->classroomService->getRunningClassrooms();

        return view('admin.students.index', compact('students', 'classrooms'));
    }

    public function create()
    {
        $classrooms = $this->classroomService->getEnrollableClassrooms();

        return view('admin.students.create', compact('classrooms'));
    }

    public function store(StoreStudentRequest $request)
    {
        $this->studentService->createStudent($request->validated());

        return redirect()->route('admin.students.index')->with('success', 'Student successfully created');
    }

    public function show($id)
    {
        $student = User::with([
            'student.currentEnrollment.classroom.homeroomTeacher',
            'student.latestEnrollment.classroom.homeroomTeacher',
            'student.entryAcademicYear',
        ])->findOrFail($id);

        return view('admin.students.detail', compact('student'));
    }

    public function edit($id)
    {
        $student = User::with([
            'student.currentEnrollment.classroom',
            'student.latestEnrollment.classroom',
        ])->findOrFail($id);

        $currentClassroomId = $student->student?->classroom_id;
        $classrooms = $this->classroomService->getEnrollableClassrooms($currentClassroomId);

        return view('admin.students.edit', compact('student', 'classrooms'));
    }

    public function update(UpdateStudentRequest $request, $id)
    {
        $user = User::findOrFail($id);
        $this->studentService->updateStudent($user, $request->validated());

        return redirect()->route('admin.students.index')->with('success', 'Student successfully updated');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $this->studentService->deleteStudent($user);

        return redirect()->route('admin.students.index')->with('success', 'Student successfully deleted');
    }
}
