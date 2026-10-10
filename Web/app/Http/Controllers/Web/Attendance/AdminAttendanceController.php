<?php

namespace App\Http\Controllers\Web\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Attendance\ResolveAttendanceRequest;
use App\Http\Requests\Web\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Web\Attendance\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\User;
use App\Services\Shared\Storage\AttendanceProofStorage;
use App\Services\Web\Academic\ClassroomService;
use App\Services\Web\Attendance\AdminAttendanceService;
use App\Services\Web\Attendance\AttendanceExportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceExportService $exportService,
        private readonly AdminAttendanceService $attendanceService,
        private readonly ClassroomService $classroomService,
    ) {}

    public function index(Request $request)
    {
        return $this->renderIndex($request);
    }

    public function students(Request $request)
    {
        return $this->renderIndex($request, 'siswa');
    }

    public function employees(Request $request)
    {
        return $this->renderIndex($request, 'employee');
    }

    public function show(Request $request, int $id)
    {
        $attendance = Attendance::with(['user.student', 'user.employee'])->findOrFail($id);
        $scope = $this->scope($request->query('scope'));
        $attendance->proof_url = $attendance->proof_image
            ? route('admin.attendances.proof', $attendance)
            : null;

        return view('admin.attendances.detail', compact('attendance', 'scope'));
    }

    public function proof(int $id, AttendanceProofStorage $proofStorage)
    {
        return $proofStorage->response(Attendance::findOrFail($id));
    }

    public function create(Request $request)
    {
        $scope = $this->scope($request->query('scope'));
        $users = User::with(['student', 'employee'])
            ->when($scope === 'siswa', fn (Builder $query) => $query->role('siswa'))
            ->when($scope === 'employee', fn (Builder $query) => $query->role(['guru', 'staff']))
            ->orderBy('name')
            ->get();
        $tanggal = $request->query('date', now()->toDateString());

        return view('admin.attendances.create', compact('users', 'tanggal', 'scope'));
    }

    public function store(StoreAttendanceRequest $request)
    {
        $data = $request->validated();
        $scope = $this->scope($data['scope'] ?? null);
        unset($data['scope']);
        $this->attendanceService->create($data);

        return $this->redirectToScope($scope)->with('success', 'Attendance recorded successfully.');
    }

    public function approve(ResolveAttendanceRequest $request, int $id)
    {
        $data = $request->validated();
        $scope = $this->scope($data['scope'] ?? null);
        $this->attendanceService->resolve(Attendance::findOrFail($id), $data['action']);

        $message = $request->action === 'approve'
            ? 'Permohonan berhasil disetujui (Approved).'
            : 'Permohonan ditolak. Status otomatis menjadi Absent (Alfa).';

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('success', $message);
        }

        if ($request->headers->get('referer') && str_contains($request->headers->get('referer'), 'dashboard')) {
            return redirect()->route('admin.dashboard')->with('success', $message);
        }

        return $this->redirectToScope($scope)->with('success', $message);
    }

    public function edit(Request $request, int $id)
    {
        $attendance = Attendance::with('user')->findOrFail($id);
        $users = User::orderBy('name')->get();
        $scope = $this->scope($request->query('scope'));

        return view('admin.attendances.edit', compact('attendance', 'users', 'scope'));
    }

    public function update(UpdateAttendanceRequest $request, int $id)
    {
        $data = $request->validated();
        $scope = $this->scope($data['scope'] ?? null);
        unset($data['scope']);
        $this->attendanceService->update(Attendance::findOrFail($id), $data);

        return $this->redirectToScope($scope)->with('success', 'Attendance updated successfully.');
    }

    public function destroy(Request $request, int $id)
    {
        $scope = $this->scope($request->query('scope'));
        $this->attendanceService->delete(Attendance::findOrFail($id));

        return $this->redirectToScope($scope)->with('success', 'Attendance deleted successfully.');
    }

    public function print(Request $request)
    {
        $filters = $this->filters($request);
        $attendances = $this->attendanceQuery($filters)->orderByDesc('recorded_at')->get();

        return view('admin.attendances.print', array_merge(compact('attendances'), $filters, [
            'classroom' => $filters['classroom_id'] ? Classroom::find($filters['classroom_id']) : null,
        ]));
    }

    private function renderIndex(Request $request, ?string $attendanceType = null)
    {
        $filters = $this->filters($request);
        $query = $this->attendanceQuery($filters, $attendanceType);

        if (in_array($request->input('export'), ['excel', 'csv', 'pdf', 'zip'], true)) {
            return $this->exportService->export($request->input('export'), clone $query);
        }

        $perPage = min(max((int) $request->input('per_page', 10), 10), 100);
        $attendances = $query->orderByDesc('recorded_at')->paginate($perPage);
        $attendances->appends($filters + ['per_page' => $perPage]);
        $classrooms = $this->classroomService->getFilterableClassrooms();

        return view('admin.attendances.index', array_merge(compact('attendances', 'classrooms'), $filters, [
            'attendanceType' => $attendanceType,
            'attendanceRouteName' => match ($attendanceType) {
                'siswa' => 'admin.attendances.students',
                'employee' => 'admin.attendances.employees',
                default => 'admin.attendances.index',
            },
        ]));
    }

    private function filters(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->toString(),
            'date' => $request->has('date') ? $request->input('date') : now()->toDateString(),
            'month' => $request->integer('month') ?: null,
            'year' => $request->integer('year') ?: null,
            'role' => $request->input('role'),
            'classroom_id' => $request->integer('classroom_id') ?: null,
            'approval' => $request->input('approval') === 'pending' ? 'pending' : null,
        ];
    }

    private function attendanceQuery(array $filters, ?string $roleOverride = null): Builder
    {
        $query = Attendance::with(['user.student', 'user.employee']);

        if ($filters['search'] !== '') {
            $query->whereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$filters['search']}%"));
        }

        if ($filters['date']) {
            $date = Carbon::parse($filters['date'])->startOfDay();
            $query->where('recorded_at', '>=', $date)->where('recorded_at', '<', $date->copy()->addDay());
        } elseif ($filters['month'] || $filters['year']) {
            $start = Carbon::create($filters['year'] ?: now()->year, $filters['month'] ?: now()->month)->startOfMonth();
            $query->where('recorded_at', '>=', $start)->where('recorded_at', '<', $start->copy()->addMonth());
        }

        $role = $roleOverride ?: $filters['role'];
        if ($role) {
            $query->whereHas('user', fn (Builder $query) => $role === 'employee'
                ? $query->role(['guru', 'staff'])
                : $query->role($role));
        }

        if ($filters['classroom_id']) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        if ($filters['approval'] === 'pending') {
            $query->whereIn('status', ['permission', 'sick'])
                ->whereNull('is_approved');
        }

        return $query;
    }

    private function scope(?string $scope): ?string
    {
        return in_array($scope, ['siswa', 'employee'], true) ? $scope : null;
    }

    private function redirectToScope(?string $scope)
    {
        return redirect()->route(match ($scope) {
            'siswa' => 'admin.attendances.students',
            'employee' => 'admin.attendances.employees',
            default => 'admin.attendances.index',
        });
    }
}
