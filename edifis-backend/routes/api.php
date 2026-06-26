<?php

use App\Http\Controllers\Api\ResultsController;
use App\Http\Controllers\Api\AcademicController;
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
Route::get('/schools', [PublicWebsiteController::class, 'schools'])
    ->withoutMiddleware(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class)
    ->name('public.schools');

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
Route::post('/parent/firebase-login', [ParentAuthController::class, 'firebaseLogin'])->name('parent.firebase-login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    Route::get('/me/assignments', [AuthController::class, 'assignments'])->name('auth.assignments');
    Route::get('/school/profile', [\App\Http\Controllers\Api\SchoolController::class, 'profile'])->name('school.profile');

    Route::post('/conduct', [\App\Http\Controllers\Api\ConductController::class, 'store'])
        ->middleware('role:discipline_master|principal|vice_principal')
        ->name('conduct.store');
    Route::get('/conduct', [\App\Http\Controllers\Api\ConductController::class, 'index'])
        ->middleware('role:discipline_master|principal|vice_principal|class_master')
        ->name('conduct.index');
    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
    Route::post('/students', [StudentController::class, 'store'])
        ->middleware('role:secretary|bursar')
        ->name('students.store');
    Route::get('/students', [StudentController::class, 'index'])
        ->middleware('role:principal|vice_principal|secretary|bursar|class_master|subject_teacher|discipline_master')
        ->name('students.index');
    Route::get('/classes', [\App\Http\Controllers\Api\SchoolClassController::class, 'index'])
        ->name('classes.index');
    Route::get('/subjects', [\App\Http\Controllers\Api\SubjectController::class, 'index'])
        ->name('subjects.index');
    Route::get('/streams', [AcademicController::class, 'streams'])->name('streams.index');
    Route::get('/terms', [AcademicController::class, 'terms'])->name('terms.index');

    Route::post('/academics/marks', [MarksController::class, 'store'])
        ->middleware('role:subject_teacher|class_master|principal')
        ->name('academics.marks');
    Route::get('/enrollment/template', [\App\Http\Controllers\Api\EnrollmentController::class, 'template'])
        ->middleware('role:class_master|principal|vice_principal|school_admin')
        ->name('enrollment.template');
    Route::post('/enrollment/upload', [\App\Http\Controllers\Api\EnrollmentController::class, 'upload'])
        ->middleware('role:class_master|principal|vice_principal|school_admin')
        ->name('enrollment.upload');

    Route::get('/marks/template', [MarksController::class, 'template'])
        ->middleware('role:subject_teacher|class_master|principal|vice_principal')
        ->name('marks.template');
    Route::post('/marks/upload', [MarksController::class, 'upload'])
        ->middleware('role:subject_teacher|class_master|principal|vice_principal')
        ->name('marks.upload');

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
    Route::get('/fees/balances', [FeesController::class, 'balances'])
        ->middleware('role:bursar|principal|vice_principal')
        ->name('fees.balances');
    Route::get('/fees/overview', [FeesController::class, 'overview'])
        ->middleware('role:bursar|principal|vice_principal')
        ->name('fees.overview');
    Route::get('/fees/template', [FeesController::class, 'template'])
        ->middleware('role:bursar|principal|vice_principal')
        ->name('fees.template');
    Route::post('/fees/upload', [FeesController::class, 'upload'])
        ->middleware('role:bursar|principal|vice_principal')
        ->name('fees.upload');
    Route::post('/fees/bill', [FeesController::class, 'bill'])
        ->middleware('role:bursar|principal')
        ->name('fees.bill');

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
    Route::get('/parent/children/{studentId}/fees', [ParentPortalController::class, 'childFees'])
        ->middleware('role:parent')
        ->name('parent.child-fees');
    Route::get('/parent/children/{studentId}/results', [ParentPortalController::class, 'childResults'])
        ->middleware('role:parent')
        ->name('parent.child-results');
    Route::get('/parent/children/{studentId}/trend', [ParentPortalController::class, 'childTrend'])
        ->middleware('role:parent')
        ->name('parent.child-trend');
    Route::get('/parent/children/{studentId}/attendance', [ParentPortalController::class, 'childAttendance'])
        ->middleware('role:parent')
        ->name('parent.child-attendance');
    Route::get('/parent/calendar', [ParentPortalController::class, 'calendar'])
        ->middleware('role:parent')
        ->name('parent.calendar');
    Route::post('/parent/ask', [ParentPortalController::class, 'ask'])
        ->middleware('role:parent')
        ->name('parent.ask');

    // PEA Admin — school request management
    Route::get('/onboarding/requests', [OnboardingController::class, 'list'])
        ->middleware('role:pea_admin')
        ->name('onboarding.requests');
    Route::post('/onboarding/requests/{id}/approve', [OnboardingController::class, 'approve'])
        ->middleware('role:pea_admin')
        ->name('onboarding.approve');

    // Academic season rotation (Term 1 -> 2 -> 3 -> year-end)
    Route::get('/season', [\App\Http\Controllers\Api\SeasonController::class, 'show'])
        ->name('season.show');
    Route::get('/season/years', [\App\Http\Controllers\Api\SeasonController::class, 'years'])
        ->name('season.years');
    Route::post('/season/sequence/next', [\App\Http\Controllers\Api\SeasonController::class, 'openNextSequence'])
        ->middleware('role:principal|vice_principal|school_admin')
        ->name('season.sequence.next');
    Route::post('/season/advance', [\App\Http\Controllers\Api\SeasonController::class, 'advance'])
        ->middleware('role:principal|vice_principal|school_admin')
        ->name('season.advance');
    Route::post('/season/terms/{termId}/reopen', [\App\Http\Controllers\Api\SeasonController::class, 'reopen'])
        ->middleware('role:principal|school_admin')
        ->name('season.reopen');
    Route::post('/season/close-year', [\App\Http\Controllers\Api\SeasonController::class, 'closeYear'])
        ->middleware('role:principal|school_admin')
        ->name('season.close-year');

    Route::post('/results/compute', [ResultsController::class, 'compute'])
        ->middleware('role:principal|vice_principal|school_admin')
        ->name('results.compute');

    Route::post('/promotions/deliberate', [\App\Http\Controllers\Api\PromotionController::class, 'deliberate'])
        ->middleware('role:principal|vice_principal|school_admin')
        ->name('promotions.deliberate');
    Route::get('/promotions', [\App\Http\Controllers\Api\PromotionController::class, 'index'])
        ->middleware('role:principal|vice_principal|school_admin|class_master')
        ->name('promotions.index');
    Route::post('/promotions/{decisionId}/override', [\App\Http\Controllers\Api\PromotionController::class, 'override'])
        ->middleware('role:principal')
        ->name('promotions.override');
    Route::get('/results/overview', [ResultsController::class, 'overview'])
        ->middleware('role:principal|vice_principal|school_admin')
        ->name('results.overview');
    Route::get('/results/report-card', [ResultsController::class, 'reportCard'])
        ->name('results.report-card');
    Route::get('/results/report-card/pdf', [ResultsController::class, 'reportCardPdf'])
        ->name('results.report-card.pdf');
    Route::get('/results/mastersheet', [ResultsController::class, 'mastersheet'])
        ->name('results.mastersheet');

    Route::post('/fcm/register', [FcmTokenController::class, 'register'])
        ->name('fcm.register');
});
