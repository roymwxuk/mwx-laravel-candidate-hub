<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\CandidateInterviewerController;
use App\Http\Controllers\CandidateStageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoResetController;
use App\Http\Controllers\PositionAnalyticsController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ResumeDocumentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Public demo only (DEMO_MODE=true): restore the seeded data set.
    Route::post('/demo/reset', DemoResetController::class)->name('demo.reset');

    // Resume intake: upload → AI draft → human review → candidate
    Route::get('/resumes', [ResumeDocumentController::class, 'index'])->name('resumes.index');
    Route::get('/resumes/create', [ResumeDocumentController::class, 'create'])->name('resumes.create');
    Route::post('/resumes', [ResumeDocumentController::class, 'store'])->name('resumes.store');
    Route::get('/resumes/{resume}', [ResumeDocumentController::class, 'show'])->name('resumes.show');
    Route::get('/resumes/{resume}/status', [ResumeDocumentController::class, 'status'])->name('resumes.status');
    Route::get('/resumes/{resume}/file', [ResumeDocumentController::class, 'file'])->name('resumes.file');
    Route::post('/resumes/{resume}/retry', [ResumeDocumentController::class, 'retry'])->name('resumes.retry');
    Route::post('/resumes/{resume}/confirm', [ResumeDocumentController::class, 'confirm'])->name('resumes.confirm');

    // Candidates & selection process
    Route::get('/candidates', [CandidateController::class, 'index'])->name('candidates.index');
    Route::get('/candidates/{candidate}', [CandidateController::class, 'show'])->name('candidates.show');
    Route::patch('/candidates/{candidate}/stage', [CandidateStageController::class, 'update'])->name('candidates.stage.update');
    Route::post('/candidates/{candidate}/interviewers', [CandidateInterviewerController::class, 'store'])->name('candidates.interviewers.store');
    Route::delete('/candidates/{candidate}/interviewers/{user}', [CandidateInterviewerController::class, 'destroy'])->name('candidates.interviewers.destroy');

    // Positions: the job description, and applicant-pool analytics (aggregates only)
    Route::get('/positions/{position}', [PositionController::class, 'show'])->name('positions.show');
    Route::get('/positions/{position}/analytics', PositionAnalyticsController::class)->name('positions.analytics');

    // Account management (admin only; there is no self sign-up)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
});
