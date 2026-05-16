<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ScholarController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => [
    'status' => 'ok',
    'service' => config('app.name'),
]);

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/programs', [ProgramController::class, 'index']);
Route::get('/programs/{program}', [ProgramController::class, 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications/drafts', [ApplicationController::class, 'draft']);
    Route::post('/applications/{application}/documents', [ApplicationController::class, 'storeDocument']);
    Route::post('/applications/{application}/submit', [ApplicationController::class, 'submit']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::middleware('role:admin,officer')->group(function (): void {
        Route::post('/notifications', [NotificationController::class, 'store']);

        Route::apiResource('users', UserController::class)->only(['index', 'store', 'show', 'update']);
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
        Route::put('/users/{user}/programs', [UserController::class, 'syncPrograms']);

        Route::post('/programs', [ProgramController::class, 'store']);
        Route::patch('/programs/{program}', [ProgramController::class, 'update']);
        Route::post('/programs/{program}/publish', [ProgramController::class, 'publish']);
        Route::post('/programs/{program}/officers', [ProgramController::class, 'assignAdmins']);

        Route::patch('/applications/{application}/status', [ApplicationController::class, 'updateStatus']);
        Route::patch('/documents/{document}/status', [DocumentController::class, 'updateStatus']);

        Route::get('/analytics', [AnalyticsController::class, 'summary']);
        Route::get('/reports', [AnalyticsController::class, 'summary']);

        Route::get('/scholars', [ScholarController::class, 'index']);
        Route::get('/scholars/{scholar}', [ScholarController::class, 'show']);
        Route::patch('/scholars/{scholar}/compliance', [ScholarController::class, 'updateCompliance']);
        Route::post('/scholars/{scholar}/requirement-requests', [ScholarController::class, 'sendRequirementRequest']);
    });
});
