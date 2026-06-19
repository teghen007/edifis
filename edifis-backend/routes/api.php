<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\IssuanceController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\FeesController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\MarksController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\TimetableController;
use App\Http\Controllers\Api\VacuumController;
use App\Http\Controllers\Api\ParentAuthController;
use App\Http\Controllers\ParentPortalController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\TenancyController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\PublicWebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::get('/', [PublicWebsiteController::class, 'landing'])->name('public.landing');
Route::post('/onboarding/request', [PublicWebsiteController::class, 'submit'])->name('public.submit');

Route::get('/tenancy/domain-allowed', [TenancyController::class, 'domainAllowed'])
    ->withoutMiddleware(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class)
    ->name('tenancy.domain-allowed');

Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
Route::get('/auth/revocations', [AuthController::class, 'revocations'])->name('auth.revocations');

Route::post('/sync', SyncController::class)->name('sync');

Route::post('/monitoring/node-status', [MonitoringController::class, 'nodeStatus'])
    ->name('monitoring.node-status');

// Parent auth (cloud-only — checked at runtime via mode parameter)
Route::post('/parent/login', [ParentAuthController::class, 'login'])->name('parent.login');
Route::post('/parent/verify-otp', [ParentAuthController::class, 'verifyOtp'])->name('parent.verify-otp');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
    Route::post('/students', [StudentController::class, 'store'])
        ->middleware('role:secretary|bursar')
        ->name('students.store');
    Route::get('/students', [StudentController::class, 'index'])
        ->middleware('role:principal|vice_principal|secretary|bursar|class_master|subject_teacher|discipline_master')
        ->name('students.index');

    Route::post('/academics/marks', [MarksController::class, 'store'])
        ->middleware('role:subject_teacher|class_master|principal')
        ->name('academics.marks');

    Route::post('/issuance/catalogue:import', [IssuanceController::class, 'import'])
        ->middleware('role:bursar')
        ->name('issuance.import');
    Route::post('/issuance/issue', [IssuanceController::class, 'issue'])
        ->middleware('role:bursar')
        ->name('issuance.issue');
    Route::post('/issuance/return', [IssuanceController::class, 'return'])
        ->middleware('role:bursar')
        ->name('issuance.return');

    Route::post('/attendance/sessions', [AttendanceController::class, 'openSession'])
        ->middleware('role:class_master|subject_teacher')
        ->name('attendance.sessions.open');
    Route::post('/attendance/sessions/{sessionId}/scan', [AttendanceController::class, 'scan'])
        ->middleware('role:class_master|subject_teacher')
        ->name('attendance.sessions.scan');
    Route::post('/attendance/sessions/{sessionId}/close', [AttendanceController::class, 'closeSession'])
        ->middleware('role:class_master|subject_teacher')
        ->name('attendance.sessions.close');
    Route::post('/attendance/void', [AttendanceController::class, 'voidScan'])
        ->middleware('role:class_master|subject_teacher')
        ->name('attendance.void');
    Route::get('/attendance/sessions/{sessionId}/tally', [AttendanceController::class, 'tally'])
        ->middleware('role:class_master|subject_teacher|principal')
        ->name('attendance.tally');

    Route::get('/fees/students/{studentId}/balance', [FeesController::class, 'balance'])
        ->middleware('role:bursar|parent')
        ->name('fees.balance');

    Route::post('/timetable', [TimetableController::class, 'store'])
        ->middleware('role:vice_principal|principal')
        ->name('timetable.store');
    Route::get('/timetable', [TimetableController::class, 'index'])
        ->name('timetable.index');
    Route::post('/timetable/{entryId}/approve', [TimetableController::class, 'approve'])
        ->middleware('role:principal')
        ->name('timetable.approve');
    Route::match(['get', 'post'], '/calendar', [TimetableController::class, 'calendar'])
        ->name('calendar');

    Route::post('/vacuum/query', [VacuumController::class, 'query'])
        ->name('vacuum.query');
    Route::post('/vacuum/command', [VacuumController::class, 'command'])
        ->middleware('role:principal')
        ->name('vacuum.command');

    // Parent portal (parent role, ModeGate checks at action level)
    Route::post('/parent/set-pin', [ParentAuthController::class, 'setPin'])
        ->middleware('role:parent')
        ->name('parent.set-pin');
    Route::get('/parent/children', [ParentPortalController::class, 'children'])
        ->middleware('role:parent')
        ->name('parent.children');
    Route::get('/parent/children/{studentId}/balance', [ParentPortalController::class, 'childBalance'])
        ->middleware('role:parent')
        ->name('parent.child-balance');
    Route::get('/parent/children/{studentId}/results', [ParentPortalController::class, 'childResults'])
        ->middleware('role:parent')
        ->name('parent.child-results');
    Route::get('/parent/children/{studentId}/attendance', [ParentPortalController::class, 'childAttendance'])
        ->middleware('role:parent')
        ->name('parent.child-attendance');
    Route::get('/parent/calendar', [ParentPortalController::class, 'calendar'])
        ->middleware('role:parent')
        ->name('parent.calendar');

    // PEA Admin — school request management
    Route::get('/onboarding/requests', [OnboardingController::class, 'list'])
        ->middleware('role:pea_admin')
        ->name('onboarding.requests');
    Route::post('/onboarding/requests/{id}/approve', [OnboardingController::class, 'approve'])
        ->middleware('role:pea_admin')
        ->name('onboarding.approve');
});
