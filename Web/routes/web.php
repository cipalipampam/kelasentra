<?php

use App\Http\Controllers\Web\Academic\AcademicYearController;
use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\Attendance\AdminAttendanceController;
use App\Http\Controllers\Web\Auth\AuthController;
use App\Http\Controllers\Web\Dashboard\DashboardController;
use App\Http\Controllers\Web\Employee\EmployeeController;
use App\Http\Controllers\Web\Setting\SettingController;
use App\Http\Controllers\Web\Student\StudentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return redirect()->route('admin.login_form');
})->name('login');

Route::controller(AuthController::class)->group(function () {
    Route::get('/admin/login', 'showAdminLogin')->name('admin.login_form');
    Route::post('/admin/login', 'adminLogin')->middleware('throttle:5,1')->name('admin.login');
    Route::post('/logout', 'adminLogout')->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::controller(SettingController::class)->prefix('settings')->group(function () {
        Route::get('/', 'index')->name('settings.index');
        Route::post('/location', 'updateLocation')->name('update_location');
        Route::post('/attendance', 'updateAttendanceSettings')->name('update_attendance_settings');
        Route::post('/institution', 'updateInstitution')->name('update_institution');
    });

    // Delegated to decoupled StudentController
    Route::controller(StudentController::class)->prefix('students')->name('students.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{student}', 'show')->name('show');
        Route::get('/{student}/edit', 'edit')->name('edit');
        Route::put('/{student}', 'update')->name('update');
        Route::delete('/{student}', 'destroy')->name('destroy');
    });

    // Delegated to decoupled EmployeeController
    Route::controller(EmployeeController::class)->prefix('employees')->name('employees.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{employee}', 'show')->name('show');
        Route::get('/{employee}/edit', 'edit')->name('edit');
        Route::put('/{employee}', 'update')->name('update');
        Route::delete('/{employee}', 'destroy')->name('destroy');
    });

    Route::controller(AdminAttendanceController::class)->prefix('attendances')->name('attendances.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/students', 'students')->name('students');
        Route::get('/employees', 'employees')->name('employees');
        Route::get('/create', 'create')->name('create');
        Route::get('/report/print', 'print')->name('print'); // ← harus sebelum {id}
        Route::post('/', 'store')->name('store');
        Route::post('/{id}/approve', 'approve')->name('approve');
        Route::get('/{id}/proof', 'proof')->name('proof');
        Route::get('/{id}', 'show')->name('show');
        Route::get('/{id}/edit', 'edit')->name('edit');
        Route::put('/{id}', 'update')->name('update');
        Route::delete('/{id}', 'destroy')->name('destroy');
    });

    Route::post('announcements/{id}/toggle', [AnnouncementController::class, 'toggleStatus'])->name('announcements.toggle');
    Route::resource('announcements', AnnouncementController::class)->except(['show']);

    // Academic Master Data & Class Promotion
    Route::controller(AcademicYearController::class)->prefix('academic-years')->name('academic-years.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{academicYear}/activate', 'activate')->name('activate');
        Route::put('/{academicYear}', 'update')->name('update');
        Route::delete('/{academicYear}', 'destroy')->name('destroy');
    });

    Route::get('classrooms/promotion', [\App\Http\Controllers\Web\Academic\ClassroomController::class, 'promotion'])->name('classrooms.promotion');
    Route::post('classrooms/promotion', [\App\Http\Controllers\Web\Academic\ClassroomController::class, 'processPromotion'])->name('classrooms.promotion.process');
    Route::post('classrooms/promotion/{promotionBatch}/revert', [\App\Http\Controllers\Web\Academic\ClassroomController::class, 'revertPromotion'])->name('classrooms.promotion.revert');
    Route::get('classrooms/{classroom}/students', [\App\Http\Controllers\Web\Academic\ClassroomController::class, 'getStudents'])->name('classrooms.students');
    Route::resource('classrooms', \App\Http\Controllers\Web\Academic\ClassroomController::class)->except(['show', 'create', 'edit']);
    Route::resource('subjects', \App\Http\Controllers\Web\Academic\SubjectController::class)->except(['show', 'create', 'edit']);
    Route::get('schedules/teachers-by-subject/{subject}', [\App\Http\Controllers\Web\Academic\ScheduleController::class, 'getTeachersBySubject'])->name('schedules.teachers-by-subject');
    Route::resource('schedules', \App\Http\Controllers\Web\Academic\ScheduleController::class)->except(['show', 'create', 'edit']);
});
