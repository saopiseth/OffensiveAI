<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\SkillController;
use App\Http\Controllers\API\SkillStepController;
use App\Http\Controllers\API\WorkflowController;
use App\Http\Controllers\API\ExecutionController;
use App\Http\Controllers\API\FindingController;
use App\Http\Controllers\API\AiSettingController;
use App\Http\Controllers\API\AptSimulationController;
use App\Http\Controllers\API\ScheduledWorkflowController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);

    // Users
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/roles', [UserController::class, 'assignRoles']);
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);

    // Roles & Permissions
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions',            [RoleController::class, 'permissions']);
    Route::post('permissions',           [RoleController::class, 'storePermission']);
    Route::delete('permissions/{permission}', [RoleController::class, 'destroyPermission']);

    // Skills
    Route::apiResource('skills', SkillController::class);
    Route::post('skills/{skill}/duplicate',  [SkillController::class, 'duplicate']);
    Route::get('skills-mirror/list',         [SkillController::class, 'mirrorList']);
    Route::post('skills-mirror/preview',     [SkillController::class, 'mirrorPreview']);
    Route::post('skills-mirror/import',      [SkillController::class, 'mirrorImport']);
    Route::get('skills-convert/list',        [SkillController::class, 'converterList']);
    Route::post('skills-convert/preview',    [SkillController::class, 'converterPreview']);
    Route::post('skills-convert/import',     [SkillController::class, 'converterImport']);

    // Skill Steps (nested under skills)
    Route::apiResource('skills.steps', SkillStepController::class)->shallow();
    Route::post('skills/{skill}/steps/reorder', [SkillStepController::class, 'reorder']);

    // Workflows
    Route::apiResource('workflows', WorkflowController::class);
    Route::get('workflows/{workflow}/preflight',  [WorkflowController::class, 'preflight']);
    Route::post('workflows/{workflow}/execute',   [WorkflowController::class, 'execute']);

    // Executions
    Route::apiResource('executions', ExecutionController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::post('executions/{execution}/retry',           [ExecutionController::class, 'retry']);
    Route::post('executions/{execution}/generate-report', [ExecutionController::class, 'generateReport']);
    Route::get('executions/{execution}/report/download',  [ExecutionController::class, 'downloadReport']);
    Route::get('executions/{execution}/targets',          [WorkflowController::class, 'targets']);

    // Findings
    Route::get('executions/{execution}/findings',                     [FindingController::class, 'index']);
    Route::post('executions/{execution}/findings',                    [FindingController::class, 'store']);
    Route::get('executions/{execution}/findings/chart-data',          [FindingController::class, 'chartData']);
    Route::post('executions/{execution}/findings/generate-ai',        [FindingController::class, 'generateAi']);
    Route::put('executions/{execution}/findings/{finding}',           [FindingController::class, 'update']);
    Route::delete('executions/{execution}/findings/{finding}',        [FindingController::class, 'destroy']);

    // AI Settings
    Route::apiResource('ai-settings', AiSettingController::class);

    // AI Settings
    Route::post('ai-settings/{aiSetting}/test',     [AiSettingController::class, 'test']);
    Route::post('ai-settings/{aiSetting}/activate', [AiSettingController::class, 'activate']);

    // APT Simulation
    Route::get('apt-simulations/presets', [AptSimulationController::class, 'presets']);
    Route::get('apt-simulations',         [AptSimulationController::class, 'index']);
    Route::post('apt-simulations',        [AptSimulationController::class, 'generate']);

    // Scheduled Workflows
    Route::post('scheduled-workflows/{scheduledWorkflow}/run',    [ScheduledWorkflowController::class, 'run']);
    Route::post('scheduled-workflows/{scheduledWorkflow}/toggle', [ScheduledWorkflowController::class, 'toggle']);
    Route::apiResource('scheduled-workflows', ScheduledWorkflowController::class);


});
