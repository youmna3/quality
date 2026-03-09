<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewerAssignment;
use App\Models\Tutor;
use App\Models\User;
use App\Models\WeekCycle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminHomeController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedWeek = max(1, (int) $request->query('week', 1));
        $maxAssignmentWeek = (int) (ReviewerAssignment::query()->max('week_number') ?? 0);
        $maxCycleWeek = (int) (WeekCycle::query()->max('week_number') ?? 0);
        $maxWeek = max($selectedWeek, $maxAssignmentWeek + 1, $maxCycleWeek, 1);
        $weeks = range(1, $maxWeek);

        $recentTutors = Tutor::query()
            ->select([
                'id',
                'tutor_code',
                'name_en',
                'mentor_name',
                'grade',
                'dashboard_email',
                'is_active',
            ])
            ->latest('id')
            ->limit(8)
            ->get();

        $assignedTutorIdsForWeek = ReviewerAssignment::query()
            ->where('week_number', $selectedWeek)
            ->pluck('tutor_id')
            ->all();

        return Inertia::render('Admin/Home', [
            'week' => $selectedWeek,
            'weeks' => $weeks,
            'kpis' => [
                'total_tutors' => Tutor::query()->count(),
                'active_tutors' => Tutor::query()->where('is_active', true)->count(),
                'reviewers' => User::query()->where('role', 'reviewer')->count(),
                'admins' => User::query()->where('role', 'admin')->count(),
                'assigned_this_week' => ReviewerAssignment::query()->where('week_number', $selectedWeek)->count(),
                'pending_this_week' => Tutor::query()
                    ->where('is_active', true)
                    ->whereNotIn('id', $assignedTutorIdsForWeek)
                    ->count(),
            ],
            'recentTutors' => $recentTutors,
        ]);
    }
}
