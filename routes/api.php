<?php

use App\Http\Controllers\Admin\AdminAssessmentController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\User\ActionPlanItemController;
use App\Http\Controllers\User\AssessmentController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\ExpansionAreaController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\ProjectReviewController;
use App\Http\Controllers\User\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [RegisterController::class, 'register'])
        ->middleware('throttle:auth')
        ->name('register');

    Route::post('login', [LoginController::class, 'login'])
        ->middleware('throttle:auth')
        ->name('login');

    Route::get('verify-email/{id}/{hash}', [RegisterController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:auth');

    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])
        ->middleware('throttle:auth');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    Route::get('auth/me', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'organization_name' => $user->organization_name,
            'locale' => $user->locale ?? 'ar',
            'theme' => $user->theme ?? 'system',
            'email_verified_at' => $user->email_verified_at,
        ]);
    });

    Route::post('auth/logout', [LogoutController::class, 'logout']);
    Route::post('auth/change-password', [PasswordChangeController::class, 'change']);
    Route::post('auth/email/resend', [RegisterController::class, 'resendVerification']);

    Route::get('profile/preferences', [ProfileController::class, 'preferences']);
    Route::patch('profile/preferences', [ProfileController::class, 'updatePreferences']);

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::prefix('project-reviews')->name('project-reviews.')->group(function () {
        Route::get('/', [ProjectReviewController::class, 'index']);
        Route::post('/', [ProjectReviewController::class, 'store']);
        Route::get('{id}', [ProjectReviewController::class, 'show']);
        Route::post('{id}/chat', [ProjectReviewController::class, 'chat']);
    });

    Route::prefix('assessment')->name('assessment.')->group(function () {
        Route::get('questions', [AssessmentController::class, 'getQuestions']);
        Route::post('start', [AssessmentController::class, 'start']);
        Route::post('{id}/submit', [AssessmentController::class, 'submit']);
        Route::get('{id}/results', [AssessmentController::class, 'results']);
        Route::get('history', [AssessmentController::class, 'history']);
    });

    Route::patch('action-plan/items/{id}', [ActionPlanItemController::class, 'updateStatus']);

    Route::prefix('expansion-areas')->group(function () {
        Route::get('/', [ExpansionAreaController::class, 'index']);
        Route::post('/', [ExpansionAreaController::class, 'store']);
        Route::put('{id}', [ExpansionAreaController::class, 'update']);
        Route::patch('{id}', [ExpansionAreaController::class, 'update']);
        Route::delete('{id}', [ExpansionAreaController::class, 'destroy']);
    });

    Route::prefix('report')->group(function () {
        Route::get('{assessment_id}/download', [ReportController::class, 'download']);
        Route::get('{assessment_id}/status', [ReportController::class, 'status']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('read-all', [NotificationController::class, 'markAllRead']);
        Route::post('{id}/read', [NotificationController::class, 'markRead']);
    });
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'is_admin', 'throttle:api'])->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index']);
        Route::get('assessments', [AdminAssessmentController::class, 'index']);
        Route::get('assessments/{id}', [AdminAssessmentController::class, 'show']);
        Route::get('users', [AdminDashboardController::class, 'users']);
        Route::get('analytics/pillars', [AdminDashboardController::class, 'pillarAnalytics']);
    });
});
