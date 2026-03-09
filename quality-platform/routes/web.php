<?php

use App\Http\Controllers\Admin\AdminHomeController;
use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\FlagController as AdminFlagController;
use App\Http\Controllers\Admin\FlagDecisionController;
use App\Http\Controllers\Admin\MentorCoordinatorController;
use App\Http\Controllers\Admin\ReviewReportController;
use App\Http\Controllers\Admin\ReviewerAccountController;
use App\Http\Controllers\Admin\ReviewerAssignmentController;
use App\Http\Controllers\Admin\TeamLeadController;
use App\Http\Controllers\Admin\TutorController as AdminTutorController;
use App\Http\Controllers\Reviewer\ReviewerHomeController;
use App\Http\Controllers\Reviewer\ReviewController as ReviewerReviewController;
use App\Http\Controllers\TeamLead\TeamLeadHomeController;
use App\Http\Controllers\Tutor\FlagController as TutorFlagController;
use App\Http\Controllers\Tutor\ComplaintController as TutorComplaintController;
use App\Http\Controllers\Tutor\ReportController as TutorReportController;
use App\Http\Controllers\Tutor\TutorHomeController;
use Illuminate\Support\Facades\Route;

$dashboardRouteForRole = static function (string $role): string {
    return match ($role) {
        'admin' => route('admin.home'),
        'reviewer' => route('reviewer.home'),
        'team_lead' => route('team-lead.home'),
        default => route('tutor.home'),
    };
};

Route::get('/', function () use ($dashboardRouteForRole) {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->to($dashboardRouteForRole(auth()->user()->role));
})->name('home');

Route::middleware('auth')->group(function () use ($dashboardRouteForRole) {
    Route::get('/dashboard', function () use ($dashboardRouteForRole) {
        return redirect()->to($dashboardRouteForRole(auth()->user()->role));
    })->name('dashboard');

    Route::prefix('admin')
        ->as('admin.')
        ->middleware('role:admin')
        ->group(function () {
            Route::get('/', [AdminHomeController::class, 'index'])->name('home');

            Route::get('/tutors', [AdminTutorController::class, 'index'])->name('tutors.index');
            Route::post('/tutors', [AdminTutorController::class, 'store'])->name('tutors.store');
            Route::patch('/tutors/{tutor}', [AdminTutorController::class, 'update'])->name('tutors.update');
            Route::delete('/tutors/{tutor}', [AdminTutorController::class, 'destroy'])->name('tutors.destroy');
            Route::post('/tutors/import', [AdminTutorController::class, 'import'])->name('tutors.import');

            Route::get('/reviewers', [ReviewerAccountController::class, 'index'])->name('reviewers.index');
            Route::post('/reviewers', [ReviewerAccountController::class, 'store'])->name('reviewers.store');
            Route::patch('/reviewers/{reviewer}', [ReviewerAccountController::class, 'update'])->name('reviewers.update');

            Route::get('/mentor-coordinators', [MentorCoordinatorController::class, 'index'])->name('mentor-coordinators.index');
            Route::put('/mentor-coordinators', [MentorCoordinatorController::class, 'update'])->name('mentor-coordinators.update');
            Route::get('/team-leads', [TeamLeadController::class, 'index'])->name('team-leads.index');
            Route::post('/team-leads', [TeamLeadController::class, 'store'])->name('team-leads.store');
            Route::patch('/team-leads/{teamLead}', [TeamLeadController::class, 'update'])->name('team-leads.update');
            Route::put('/team-leads/mappings', [TeamLeadController::class, 'updateMappings'])->name('team-leads.mappings.update');

            Route::get('/assignments', [ReviewerAssignmentController::class, 'index'])->name('assignments.index');
            Route::post('/assignments/auto-assign', [ReviewerAssignmentController::class, 'autoAssign'])->name('assignments.auto-assign');
            Route::post('/assignments/redo-assign', [ReviewerAssignmentController::class, 'redoAssign'])->name('assignments.redo-assign');
            Route::post('/assignments/import-ops-groups', [ReviewerAssignmentController::class, 'importOpsGroups'])->name('assignments.import-ops-groups');
            Route::post('/assignments/import-ops-issues', [ReviewerAssignmentController::class, 'importOpsIssues'])->name('assignments.import-ops-issues');
            Route::put('/assignments/cycle', [ReviewerAssignmentController::class, 'updateCycle'])->name('assignments.cycle.update');
            Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');

            Route::get('/flags', [AdminFlagController::class, 'index'])->name('flags.index');
            Route::put('/flags/{flag}/status', [FlagDecisionController::class, 'update'])->name('flags.update');
            Route::put('/flags/{flag}/objection', [FlagDecisionController::class, 'updateObjection'])->name('flags.objection.update');
            Route::get('/reports', [ReviewReportController::class, 'index'])->name('reports.index');
            Route::post('/reports/publish', [ReviewReportController::class, 'publishWeek'])->name('reports.publish');
            Route::get('/reports/export', [ReviewReportController::class, 'exportCsv'])->name('reports.export');
            Route::get('/reports/{review}/edit', [ReviewReportController::class, 'edit'])->name('reports.edit');
            Route::put('/reports/{review}', [ReviewReportController::class, 'update'])->name('reports.update');
        });

    Route::prefix('reviewer')
        ->as('reviewer.')
        ->middleware('role:reviewer')
        ->group(function () {
            Route::get('/', [ReviewerHomeController::class, 'index'])->name('home');
            Route::get('/reviews/{assignment}/group-history', [ReviewerReviewController::class, 'groupHistory'])->name('reviews.group-history');
            Route::get('/reviews/{assignment}/create', [ReviewerReviewController::class, 'create'])->name('reviews.create');
            Route::post('/reviews/{assignment}', [ReviewerReviewController::class, 'store'])->name('reviews.store');
            Route::post('/reviews/{assignment}/request-edit', [ReviewerReviewController::class, 'requestEdit'])->name('reviews.request-edit');
        });

    Route::prefix('tutor')
        ->as('tutor.')
        ->middleware('role:tutor')
        ->group(function () {
            Route::get('/', [TutorHomeController::class, 'index'])->name('home');
            Route::get('/flags', [TutorFlagController::class, 'index'])->name('flags.index');
            Route::post('/flags/{flag}/objection', [TutorFlagController::class, 'submitObjection'])->name('flags.objection.store');
            Route::post('/issues', [TutorComplaintController::class, 'store'])->name('issues.store');
            Route::get('/reports', [TutorReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export', [TutorReportController::class, 'exportCsv'])->name('reports.export');
        });

    Route::prefix('team-lead')
        ->as('team-lead.')
        ->middleware('role:team_lead')
        ->group(function () {
            Route::get('/', [TeamLeadHomeController::class, 'index'])->name('home');
        });
});

require __DIR__.'/auth.php';
