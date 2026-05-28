<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ApplicantRankingController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\GrantDistributionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RiskDetectionController;
use App\Http\Controllers\Api\ScholarController;
use App\Http\Controllers\Api\SemesterRequirementDraftController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => [
    'status' => 'ok',
    'service' => config('app.name'),
]);

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
});

Route::get('/programs', [ProgramController::class, 'index']);
Route::get('/programs/{program}', [ProgramController::class, 'show']);
Route::get('/announcements/public', [AnnouncementController::class, 'publicIndex']);
Route::get('/grant-distribution/announcements', [GrantDistributionController::class, 'publicAnnouncements']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::patch('/auth/password', [AuthController::class, 'changePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications/drafts', [ApplicationController::class, 'draft']);
    Route::post('/applications/{application}/documents', [ApplicationController::class, 'storeDocument']);
    Route::post('/applications/{application}/submit', [ApplicationController::class, 'submit']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/settings', [UserSettingsController::class, 'show']);
    Route::patch('/settings', [UserSettingsController::class, 'update']);
    Route::get('/semester-requirement-draft', [SemesterRequirementDraftController::class, 'show']);
    Route::put('/semester-requirement-draft', [SemesterRequirementDraftController::class, 'update']);
    Route::delete('/semester-requirement-draft', [SemesterRequirementDraftController::class, 'destroy']);

    Route::get('/scholars', [ScholarController::class, 'index']);
    Route::get('/scholars/{scholar}', [ScholarController::class, 'show']);
    Route::post('/scholars/{scholar}/semester-requirements', [ScholarController::class, 'submitSemesterRequirements']);
    Route::get('/grant-distribution', [GrantDistributionController::class, 'index']);
    Route::get('/documents/{document}/file', [DocumentController::class, 'showFile'])->name('documents.file');

    Route::middleware('role:head_officer,officer')->group(function (): void {
        Route::post('/notifications', [NotificationController::class, 'store']);
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

        Route::apiResource('users', UserController::class)->only(['index', 'store', 'show', 'update']);
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
        Route::put('/users/{user}/programs', [UserController::class, 'syncPrograms']);

        Route::post('/programs', [ProgramController::class, 'store']);
        Route::patch('/programs/{program}', [ProgramController::class, 'update']);
        Route::post('/programs/{program}/publish', [ProgramController::class, 'publish']);
        Route::post('/programs/{program}/officers', [ProgramController::class, 'assignAdmins']);

        Route::get('/applications/review', [ApplicationController::class, 'review']);
        Route::patch('/applications/{application}/status', [ApplicationController::class, 'updateStatus']);
        Route::patch('/documents/{document}/status', [DocumentController::class, 'updateStatus']);

        Route::get('/analytics', [AnalyticsController::class, 'summary']);
        Route::get('/analytics/applicant-forecast', [AnalyticsController::class, 'applicantForecast']);
        Route::get('/reports', [AnalyticsController::class, 'summary']);
        Route::get('/reports/export', [ReportController::class, 'export']);
        Route::get('/rankings', [ApplicantRankingController::class, 'index']);
        Route::get('/risk-detection', [RiskDetectionController::class, 'index']);

        Route::post('/grant-distribution/batches', [GrantDistributionController::class, 'store']);
        Route::patch('/grant-distribution/batches/{grantBatch}', [GrantDistributionController::class, 'update']);
        Route::post('/grant-distribution/batches/{grantBatch}/notify', [GrantDistributionController::class, 'notify']);
        Route::post('/grant-distribution/batches/{grantBatch}/announcements', [GrantDistributionController::class, 'announce']);
        Route::patch('/grant-distribution/batches/{grantBatch}/close', [GrantDistributionController::class, 'close']);
        Route::post('/grant-distribution/batches/{grantBatch}/beneficiaries/{grantBeneficiary}/release', [GrantDistributionController::class, 'release']);

        Route::post('/scholars/semester-requirements/require-all', [ScholarController::class, 'requireSemesterRequirementsForAll']);
        Route::patch('/scholars/{scholar}/compliance', [ScholarController::class, 'updateCompliance']);
        Route::post('/scholars/{scholar}/requirement-requests', [ScholarController::class, 'sendRequirementRequest']);
    });
});
