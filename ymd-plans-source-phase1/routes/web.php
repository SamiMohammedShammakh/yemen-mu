<?php

use App\Http\Controllers\Admin\AccessAdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\IndicatorController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanItemController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StrategicGoalController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\YearController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active', 'context'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'home'])->name('home');
    Route::post('/context', [ContextController::class, 'update'])->name('context.update');

    // لوحات العمل
    Route::get('/workspace', [DashboardController::class, 'workspace'])->name('workspace');
    Route::get('/planning-center', [DashboardController::class, 'planning'])->name('planning.center');
    Route::get('/executive', [DashboardController::class, 'executive'])->name('executive');

    // الخطط
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');
    Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::get('/plans/{plan}/quarters/{q}', [PlanController::class, 'quarter'])->whereNumber('q')->name('plans.quarter');
    Route::get('/plans/{plan}/versions/{no}', [PlanController::class, 'version'])->whereNumber('no')->name('plans.version');

    // عناصر الخطة (في حالة المسودة/المعادة فقط)
    Route::post('/plans/{plan}/objectives', [PlanItemController::class, 'storeObjective'])->name('objectives.store');
    Route::put('/objectives/{objective}', [PlanItemController::class, 'updateObjective'])->name('objectives.update');
    Route::delete('/objectives/{objective}', [PlanItemController::class, 'destroyObjective'])->name('objectives.destroy');
    Route::get('/objectives/{objective}', [PlanItemController::class, 'showObjective'])->name('objectives.show');
    Route::post('/plans/{plan}/projects', [PlanItemController::class, 'storeProject'])->name('projects.store');
    Route::get('/projects/{project}', [PlanItemController::class, 'showProject'])->name('projects.show');
    Route::put('/projects/{project}', [PlanItemController::class, 'updateProject'])->name('projects.update');
    Route::delete('/projects/{project}', [PlanItemController::class, 'destroyProject'])->name('projects.destroy');
    Route::post('/plans/{plan}/tasks', [PlanItemController::class, 'storeTask'])->name('tasks.store');
    Route::put('/tasks/{task}', [PlanItemController::class, 'updateTask'])->name('tasks.update');
    Route::delete('/tasks/{task}', [PlanItemController::class, 'destroyTask'])->name('tasks.destroy');
    Route::post('/tasks/{task}/defer', [UpdateController::class, 'defer'])->name('tasks.defer');

    Route::get('/plans/{plan}/indicators/create', [IndicatorController::class, 'create'])->name('indicators.create');
    Route::post('/plans/{plan}/indicators', [IndicatorController::class, 'store'])->name('indicators.store');
    Route::get('/indicators/{indicator}', [IndicatorController::class, 'show'])->name('indicators.show');
    Route::get('/indicators/{indicator}/edit', [IndicatorController::class, 'edit'])->name('indicators.edit');
    Route::put('/indicators/{indicator}', [IndicatorController::class, 'update'])->name('indicators.update');
    Route::delete('/indicators/{indicator}', [IndicatorController::class, 'destroy'])->name('indicators.destroy');

    // دورة الاعتماد
    Route::get('/plans/{plan}/review', [WorkflowController::class, 'review'])->name('plans.review');
    Route::post('/plans/{plan}/workflow/{action}', [WorkflowController::class, 'apply'])->whereIn('action', array_keys(\App\Services\PlanWorkflow::TRANSITIONS))->name('plans.workflow');
    Route::post('/plans/{plan}/copy', [PlanController::class, 'copy'])->name('plans.copy');

    // التحديثات والتحقق والأدلة
    Route::post('/plans/{plan}/updates', [UpdateController::class, 'store'])->name('updates.store');
    Route::get('/verification', [UpdateController::class, 'queue'])->name('verification.index');
    Route::post('/updates/{update}/review', [UpdateController::class, 'review'])->name('updates.review');
    Route::get('/attachments/{attachment}', [UpdateController::class, 'attachment'])->name('attachments.show');

    // طلبات التغيير
    Route::get('/plans/{plan}/change-requests/create', [ChangeRequestController::class, 'create'])->name('change-requests.create');
    Route::post('/plans/{plan}/change-requests', [ChangeRequestController::class, 'store'])->name('change-requests.store');
    Route::get('/change-requests', [ChangeRequestController::class, 'index'])->name('change-requests.index');
    Route::get('/change-requests/{changeRequest}', [ChangeRequestController::class, 'show'])->name('change-requests.show');
    Route::post('/change-requests/{changeRequest}/decide', [ChangeRequestController::class, 'decide'])->name('change-requests.decide');

    // المتابعة والإجراءات التصحيحية
    Route::post('/plans/{plan}/notes', [FollowUpController::class, 'note'])->name('notes.store');
    Route::post('/plans/{plan}/corrective-actions', [FollowUpController::class, 'storeAction'])->name('corrective.store');
    Route::post('/corrective-actions/{action}', [FollowUpController::class, 'updateAction'])->name('corrective.update');

    // الأهداف الاستراتيجية للجمعية
    Route::get('/strategic-goals', [StrategicGoalController::class, 'index'])->name('strategic-goals.index');
    Route::post('/strategic-goals', [StrategicGoalController::class, 'store'])->name('strategic-goals.store');
    Route::get('/strategic-goals/{goal}', [StrategicGoalController::class, 'show'])->name('strategic-goals.show');
    Route::put('/strategic-goals/{goal}', [StrategicGoalController::class, 'update'])->name('strategic-goals.update');
    Route::post('/strategic-goals/{goal}/status', [StrategicGoalController::class, 'status'])->name('strategic-goals.status');
    Route::delete('/strategic-goals/{goal}', [StrategicGoalController::class, 'destroy'])->name('strategic-goals.destroy');

    // السنوات والأرباع
    Route::get('/years', [YearController::class, 'index'])->name('years.index');
    Route::post('/years', [YearController::class, 'store'])->name('years.store');
    Route::get('/years/{year}/edit', [YearController::class, 'edit'])->name('years.edit');
    Route::put('/years/{year}', [YearController::class, 'update'])->name('years.update');
    Route::post('/years/{year}/status/{action}', [YearController::class, 'transition'])->whereIn('action', array_keys(\App\Services\YearService::TRANSITIONS))->name('years.transition');
    Route::post('/years/{year}/quarters/{number}/close', [YearController::class, 'closeQuarter'])->whereNumber('number')->name('quarters.close');
    Route::post('/years/rules', [YearController::class, 'rules'])->name('rules.store');

    // التقارير والتنزيلات
    Route::get('/reports', [ExportController::class, 'index'])->name('reports.index');
    Route::get('/exports/preview', [ExportController::class, 'preview'])->name('exports.preview');
    Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
    Route::get('/exports/{export:uuid}/download', [ExportController::class, 'download'])->name('exports.download');

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/read-all', [AlertController::class, 'readAll'])->name('alerts.readAll');
    Route::get('/alerts/{alert}', [AlertController::class, 'open'])->name('alerts.open');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

    // إدارة الحسابات والصلاحيات
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/positions', [UserController::class, 'assign'])->name('users.assign');
        Route::post('/assignments/{assignment}/end', [UserController::class, 'endAssignment'])->name('assignments.end');
        Route::get('/access', [AccessAdminController::class, 'index'])->name('access.index');
        Route::post('/executive', [AccessAdminController::class, 'grant'])->name('executive.grant');
        Route::post('/executive/{membership}/revoke', [AccessAdminController::class, 'revoke'])->name('executive.revoke');
        Route::post('/delegations', [AccessAdminController::class, 'delegate'])->name('delegations.store');
        Route::post('/delegations/{delegation}/revoke', [AccessAdminController::class, 'revokeDelegation'])->name('delegations.revoke');
    });

    // واجهات النظام (JSON) — نفس الجلسة ونفس فحوص الصلاحية
    Route::prefix('api/v1')->name('api.')->group(function () {
        Route::get('/me', [ApiController::class, 'me'])->name('me');
        Route::get('/plans', [ApiController::class, 'plans'])->name('plans');
        Route::get('/plans/{plan}', [ApiController::class, 'plan'])->name('plan');
        Route::get('/plans/{plan}/results', [ApiController::class, 'results'])->name('results');
        Route::get('/indicators/{indicator}', [ApiController::class, 'indicator'])->name('indicator');
        Route::get('/search', [ApiController::class, 'search'])->name('search');
        Route::get('/exports', [ApiController::class, 'exports'])->name('exports');
    });
});
