<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Employee\StoreEmployeeRequest;
use App\Http\Requests\Web\Employee\UpdateEmployeeRequest;
use App\Models\Subject;
use App\Models\User;
use App\Services\Web\Academic\ScheduleService;
use App\Services\Web\Employee\EmployeeService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService,
        protected ScheduleService $scheduleService
    ) {}

    public function index(Request $request)
    {
        // Get users with roles 'guru' or 'staff'
        $query = User::with(['employee', 'roles'])->whereHas('roles', function ($q) use ($request) {
            if ($request->filled('role')) {
                $q->where('name', $request->role);
            } else {
                $q->whereIn('name', ['guru', 'staff']);
            }
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('nip', 'like', "%{$search}%")
                            ->orWhere('position', 'like', "%{$search}%");
                    });
            });
        }

        $employees = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 10));

        return view('admin.employees.index', compact('employees'));
    }

    public function create()
    {
        $subjects = Subject::where('is_active', true)->orderBy('cluster')->orderBy('name')->get()->groupBy('cluster');

        return view('admin.employees.create', compact('subjects'));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $this->employeeService->createEmployee($request->validated());

        return redirect()->route('admin.employees.index')->with('success', 'Data pegawai/guru berhasil ditambahkan.');
    }

    public function show(User $employee)
    {
        $this->ensureEmployee($employee);

        $employee->load(['employee', 'roles', 'attendances', 'subjects']);

        $workload = null;
        if ($employee->hasRole('guru')) {
            $workload = $this->scheduleService->calculateTeacherWeeklyWorkload($employee);
        }

        return view('admin.employees.detail', compact('employee', 'workload'));
    }

    public function edit(User $employee)
    {
        $this->ensureEmployee($employee);

        $employee->load(['employee', 'roles', 'subjects']);
        $subjects = Subject::where('is_active', true)->orderBy('cluster')->orderBy('name')->get()->groupBy('cluster');

        return view('admin.employees.edit', compact('employee', 'subjects'));
    }

    public function update(UpdateEmployeeRequest $request, User $employee)
    {
        $this->ensureEmployee($employee);

        $this->employeeService->updateEmployee($employee, $request->validated());

        return redirect()->route('admin.employees.index')->with('success', 'Data pegawai/guru berhasil diperbarui.');
    }

    public function destroy(User $employee)
    {
        $this->ensureEmployee($employee);

        $this->employeeService->deleteEmployee($employee);

        return redirect()->route('admin.employees.index')->with('success', 'Data pegawai/guru berhasil dihapus.');
    }

    private function ensureEmployee(User $employee): void
    {
        if (! $employee->hasAnyRole(['guru', 'staff'])) {
            abort(404);
        }
    }
}
