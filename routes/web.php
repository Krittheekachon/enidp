<?php

use App\Http\Controllers\Admin\CompetencyController as AdminCompetencyController;
use App\Http\Controllers\Admin\CompetencyTypeController as AdminCompetencyTypeController;
use App\Http\Controllers\Admin\IdpDeliveryTypeSettingController as AdminIdpDeliveryTypeSettingController;
use App\Http\Controllers\Admin\IdpLearningMethodController as AdminIdpLearningMethodController;
use App\Http\Controllers\Admin\LearningCatalogController as AdminLearningCatalogController;
use App\Http\Controllers\Admin\ReviewerChainTemplateController as AdminReviewerChainTemplateController;
use App\Http\Controllers\Admin\StructureController as AdminStructureController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\Auth\KkuSsoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\FcTopicSelectionController as EmployeeFcTopicSelectionController;
use App\Http\Controllers\Employee\IdpActivityUpdateController;
use App\Http\Controllers\Employee\IdpController as EmployeeIdpController;
use App\Http\Controllers\FcTopicSelectionApprovalController;
use App\Http\Controllers\Hr\AssessmentRoundController as HrAssessmentRoundController;
use App\Http\Controllers\Hr\PositionCompetencyController as HrPositionCompetencyController;
use App\Http\Controllers\IdpApprovalController;
use App\Http\Controllers\IdpCompletionReviewController;
use App\Http\Controllers\IdpProgressEvidenceController;
use App\Http\Controllers\MockSsoController;
use App\Http\Controllers\ProfileController;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $ssoEnabled = (bool) config('services.kku_sso.enabled')
        && filled(config('services.kku_sso.app_id'))
        && filled(config('services.kku_sso.client_id'))
        && filled(config('services.kku_sso.client_secret'));

    return Inertia::render('Welcome', [
        'pageTitle' => 'เข้าสู่ระบบ',
        'ssoEnabled' => $ssoEnabled,
        'ssoError' => session('sso_error'),
    ]);
});

Route::get('/auth/kku', [KkuSsoController::class, 'redirect'])
    ->middleware('throttle:20,1')
    ->name('auth.kku.redirect');
Route::get('/auth/kku/callback', [KkuSsoController::class, 'callback'])
    ->middleware('throttle:20,1')
    ->name('auth.kku.callback');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'active', 'verified'])
    ->name('dashboard');

if (app()->environment('local') && config('app.mock_sso_enabled')) {
    Route::get('/mock-sso', [MockSsoController::class, 'showLogin'])->name('mock.sso');
    Route::post('/mock-sso', [MockSsoController::class, 'login'])->name('mock.sso.login');
    Route::post('/mock-sso/test-notification', [MockSsoController::class, 'testNotification'])->name('mock.sso.test-notification');
    Route::post('/mock-sso/reset-assessment-flow', [MockSsoController::class, 'resetAssessmentFlow'])->name('mock.sso.reset-assessment-flow');
    Route::get('/mock-sso/notification-toggle', function () {
        $enabled = ! Cache::get('dev_notifications_enabled', true);
        Cache::forever('dev_notifications_enabled', $enabled);

        return response()->json(['enabled' => $enabled]);
    })->name('mock.sso.notification-toggle');
}

Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::post('/admin/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::patch('/admin/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('admin.users.status');
        Route::post('/admin/reviewer-chain-templates', [AdminReviewerChainTemplateController::class, 'store'])->name('admin.reviewer-chain-templates.store');
        Route::patch('/admin/reviewer-chain-templates/{template}', [AdminReviewerChainTemplateController::class, 'update'])->name('admin.reviewer-chain-templates.update');
        Route::delete('/admin/reviewer-chain-templates/{template}', [AdminReviewerChainTemplateController::class, 'destroy'])->name('admin.reviewer-chain-templates.destroy');
        Route::post('/admin/reviewer-chain-templates/{template}/users', [AdminReviewerChainTemplateController::class, 'addUsers'])->name('admin.reviewer-chain-templates.users.store');
        Route::delete('/admin/reviewer-chain-templates/{template}/users/{user}', [AdminReviewerChainTemplateController::class, 'removeUser'])->name('admin.reviewer-chain-templates.users.destroy');
        Route::post('/admin/competency-types', [AdminCompetencyTypeController::class, 'store'])->name('admin.competency-types.store');
        Route::put('/admin/competency-types/{competencyType}', [AdminCompetencyTypeController::class, 'update'])->name('admin.competency-types.update');
        Route::delete('/admin/competency-types/{competencyType}', [AdminCompetencyTypeController::class, 'destroy'])->name('admin.competency-types.destroy');
        Route::post('/admin/competencies', [AdminCompetencyController::class, 'store'])->name('admin.competencies.store');
        Route::post('/admin/competencies/import', [AdminCompetencyController::class, 'import'])->name('admin.competencies.import');
        Route::put('/admin/competencies/{competency}', [AdminCompetencyController::class, 'update'])->name('admin.competencies.update');
        Route::delete('/admin/competencies/{competency}', [AdminCompetencyController::class, 'destroy'])->name('admin.competencies.destroy');
        Route::post('/admin/structure/worklines', [AdminStructureController::class, 'storeWorkline'])->name('admin.structure.worklines.store');
        Route::put('/admin/structure/worklines', [AdminStructureController::class, 'updateWorkline'])->name('admin.structure.worklines.update');
        Route::delete('/admin/structure/worklines', [AdminStructureController::class, 'destroyWorkline'])->name('admin.structure.worklines.destroy');
        Route::post('/admin/structure/job-families', [AdminStructureController::class, 'storeJobFamily'])->name('admin.structure.job-families.store');
        Route::put('/admin/structure/job-families', [AdminStructureController::class, 'updateJobFamily'])->name('admin.structure.job-families.update');
        Route::delete('/admin/structure/job-families', [AdminStructureController::class, 'destroyJobFamily'])->name('admin.structure.job-families.destroy');
        Route::post('/admin/structure/positions', [AdminStructureController::class, 'storePosition'])->name('admin.structure.positions.store');
        Route::put('/admin/structure/positions', [AdminStructureController::class, 'updatePosition'])->name('admin.structure.positions.update');
        Route::delete('/admin/structure/positions', [AdminStructureController::class, 'destroyPosition'])->name('admin.structure.positions.destroy');
        Route::post('/admin/structure/support-departments', [AdminStructureController::class, 'storeSupportDepartment'])->name('admin.structure.support-departments.store');
        Route::put('/admin/structure/support-departments', [AdminStructureController::class, 'updateSupportDepartment'])->name('admin.structure.support-departments.update');
        Route::delete('/admin/structure/support-departments', [AdminStructureController::class, 'destroySupportDepartment'])->name('admin.structure.support-departments.destroy');
        Route::post('/admin/structure/support-works', [AdminStructureController::class, 'storeSupportWork'])->name('admin.structure.support-works.store');
        Route::put('/admin/structure/support-works', [AdminStructureController::class, 'updateSupportWork'])->name('admin.structure.support-works.update');
        Route::delete('/admin/structure/support-works', [AdminStructureController::class, 'destroySupportWork'])->name('admin.structure.support-works.destroy');
        Route::post('/admin/structure/support-units', [AdminStructureController::class, 'storeSupportUnit'])->name('admin.structure.support-units.store');
        Route::put('/admin/structure/support-units', [AdminStructureController::class, 'updateSupportUnit'])->name('admin.structure.support-units.update');
        Route::delete('/admin/structure/support-units', [AdminStructureController::class, 'destroySupportUnit'])->name('admin.structure.support-units.destroy');
        Route::post('/admin/structure/levels', [AdminStructureController::class, 'storeLevel'])->name('admin.structure.levels.store');
        Route::put('/admin/structure/levels', [AdminStructureController::class, 'updateLevel'])->name('admin.structure.levels.update');
        Route::delete('/admin/structure/levels', [AdminStructureController::class, 'destroyLevel'])->name('admin.structure.levels.destroy');
        Route::post('/admin/structure/learning-methods', [AdminStructureController::class, 'storeLearningMethod'])->name('admin.structure.learning-methods.store');
        Route::put('/admin/structure/learning-methods', [AdminStructureController::class, 'updateLearningMethod'])->name('admin.structure.learning-methods.update');
        Route::delete('/admin/structure/learning-methods', [AdminStructureController::class, 'destroyLearningMethod'])->name('admin.structure.learning-methods.destroy');
        Route::post('/admin/idp-learning-methods', [AdminIdpLearningMethodController::class, 'store'])->name('admin.idp-learning-methods.store');
        Route::put('/admin/idp-learning-methods/{method}', [AdminIdpLearningMethodController::class, 'update'])->name('admin.idp-learning-methods.update');
        Route::delete('/admin/idp-learning-methods/{method}', [AdminIdpLearningMethodController::class, 'destroy'])->name('admin.idp-learning-methods.destroy');
        Route::put('/admin/idp-delivery-type-settings', [AdminIdpDeliveryTypeSettingController::class, 'update'])->name('admin.idp-delivery-type-settings.update');
        Route::post('/admin/learning-catalogs', [AdminLearningCatalogController::class, 'store'])->name('admin.learning-catalogs.store');
        Route::put('/admin/learning-catalogs/{catalog}', [AdminLearningCatalogController::class, 'update'])->name('admin.learning-catalogs.update');
        Route::delete('/admin/learning-catalogs/{catalog}', [AdminLearningCatalogController::class, 'destroy'])->name('admin.learning-catalogs.destroy');
    });
    Route::post('/assessments/draft', [AssessmentController::class, 'draft'])->name('assessments.draft');
    Route::post('/assessments/save', [AssessmentController::class, 'save'])->name('assessments.save');
    Route::get('/assessments/load', [AssessmentController::class, 'load'])->name('assessments.load');
    Route::post('/assessments/approve', [AssessmentController::class, 'approve'])->name('assessments.approve');
    Route::post('/assessments/reject', [AssessmentController::class, 'reject'])->name('assessments.reject');
    Route::post('/employee/fc-topic-selection/submit', [EmployeeFcTopicSelectionController::class, 'submit'])->name('employee.fc-topic-selection.submit');
    Route::post('/fc-topic-selections/approve', [FcTopicSelectionApprovalController::class, 'approve'])->name('fc-topic-selections.approve');
    Route::post('/fc-topic-selections/reject', [FcTopicSelectionApprovalController::class, 'reject'])->name('fc-topic-selections.reject');
    Route::post('/employee/idp/draft', [EmployeeIdpController::class, 'saveDraft'])->name('employee.idp.draft');
    Route::post('/employee/idp/submit', [EmployeeIdpController::class, 'submit'])->name('employee.idp.submit');
    Route::post('/employee/idp/submit-item', [EmployeeIdpController::class, 'submitItem'])->name('employee.idp.submit-item');
    Route::post('/employee/idp-activities/progress', [IdpActivityUpdateController::class, 'store'])
        ->name('employee.idp-activities.update-progress');
    Route::post('/employee/idp-items/submit-completion', [IdpActivityUpdateController::class, 'submitCompletion'])
        ->name('employee.idp-items.submit-completion');
    Route::get('/idp-progress/evidence/{evidence}', [IdpProgressEvidenceController::class, 'show'])
        ->whereUuid('evidence')
        ->name('idp-progress.evidence.show');
    Route::post('/idp-completions/approve', [IdpCompletionReviewController::class, 'approve'])
        ->name('idp-completions.approve');
    Route::post('/idp-completions/reject', [IdpCompletionReviewController::class, 'reject'])
        ->name('idp-completions.reject');
    Route::post('/idp-items/approve', [IdpApprovalController::class, 'approve'])->name('idp-items.approve');
    Route::post('/idp-items/reject', [IdpApprovalController::class, 'reject'])->name('idp-items.reject');
    Route::get('/idp-activities/{activity}/review-detail', [IdpApprovalController::class, 'activityDetail'])
        ->name('idp-activities.review-detail');
    Route::middleware('role:hr')->group(function () {
        Route::post('/hr/position-competencies', [HrPositionCompetencyController::class, 'store'])->name('hr.position-competencies.store');
        Route::delete('/hr/position-competencies', [HrPositionCompetencyController::class, 'destroy'])->name('hr.position-competencies.destroy');
        Route::post('/hr/position-competencies/copy-round', [HrPositionCompetencyController::class, 'copyFromRound'])->name('hr.position-competencies.copy-round');
        Route::put('/hr/position-fc-selection-rules', [HrPositionCompetencyController::class, 'updateFcSelectionRule'])->name('hr.position-fc-selection-rules.update');
        Route::post('/hr/assessment-rounds', [HrAssessmentRoundController::class, 'store'])->name('hr.assessment-rounds.store');
        Route::put('/hr/assessment-rounds/{round}', [HrAssessmentRoundController::class, 'update'])->name('hr.assessment-rounds.update');
        Route::patch('/hr/assessment-rounds/{round}/activate', [HrAssessmentRoundController::class, 'activate'])->name('hr.assessment-rounds.activate');
    });
    Route::post('/hr/remind-assess', function (NotificationService $notifications) {
        $notifications->remindPendingEmployees();

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'ส่งอีเมลแจ้งเตือนการประเมินตนเองแล้ว',
        ]);
    })->middleware('role:hr')->name('hr.remind-assess');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
