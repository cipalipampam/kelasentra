<?php

namespace App\Http\Controllers\Web\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Academic\StoreAcademicYearRequest;
use App\Http\Requests\Web\Academic\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\Web\Academic\AcademicYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function __construct(
        private readonly AcademicYearService $academicYearService,
    ) {}

    public function index(Request $request): View
    {
        $academicYears = $this->academicYearService->getPaginated(
            $request->only(['search', 'status']),
            10,
        );

        $stats = $this->academicYearService->getStats();
        $statuses = AcademicYear::statusLabels();

        return view('admin.academic-years.index', compact('academicYears', 'stats', 'statuses'));
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        $this->academicYearService->create($request->validated());

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->academicYearService->update($academicYear, $request->validated());

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Data tahun ajaran berhasil diperbarui.');
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        $this->academicYearService->setCurrent($academicYear);

        return redirect()->route('admin.academic-years.index')
            ->with('success', "Tahun ajaran {$academicYear->name} kini menjadi periode berjalan.");
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        if (! $this->academicYearService->delete($academicYear)) {
            return redirect()->route('admin.academic-years.index')
                ->with('error', 'Tidak dapat menghapus tahun ajaran karena masih digunakan oleh rombel.');
        }

        return redirect()->route('admin.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil dihapus.');
    }
}
