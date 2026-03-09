<?php

namespace App\Http\Controllers\TeamLead;

use App\Http\Controllers\Controller;
use App\Models\Flag;
use App\Models\MentorTeamLeadAssignment;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\Tutor;
use App\Models\WeekCycle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamLeadHomeController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedWeek = max(1, (int) $request->query('week', 1));
        $maxAssignmentWeek = (int) (ReviewerAssignment::query()->max('week_number') ?? 0);
        $maxCycleWeek = (int) (WeekCycle::query()->max('week_number') ?? 0);
        $maxWeek = max($selectedWeek, $maxAssignmentWeek + 1, $maxCycleWeek, 1);
        $weeks = range(1, $maxWeek);

        $mentorNames = MentorTeamLeadAssignment::query()
            ->where('team_lead_user_id', $request->user()->id)
            ->pluck('mentor_name')
            ->map(fn (string $name) => preg_replace('/\s+/', ' ', trim($name)) ?? '')
            ->filter()
            ->values()
            ->all();

        if ($mentorNames === []) {
            return Inertia::render('TeamLead/Home', [
                'week' => $selectedWeek,
                'weeks' => $weeks,
                'kpis' => [
                    'mentors' => 0,
                    'tutors' => 0,
                    'avg_score' => 0,
                    'flags' => 0,
                    'objections' => 0,
                ],
                'mentorAnalytics' => [],
                'reviewerPerformance' => [],
                'flagMetrics' => [
                    'yellow' => 0,
                    'red' => 0,
                    'both' => 0,
                    'removed_red' => 0,
                    'removed_yellow' => 0,
                    'removed_both' => 0,
                    'color_changed' => 0,
                    'partial' => 0,
                ],
                'topObjectionsByReviewer' => [],
            ]);
        }

        $tutors = Tutor::query()
            ->whereIn('mentor_name', $mentorNames)
            ->get(['id', 'mentor_name', 'tutor_code', 'name_en']);
        $tutorIds = $tutors->pluck('id')->values()->all();

        $weekAssignments = ReviewerAssignment::query()
            ->with('reviewer:id,name,reviewer_type')
            ->where('week_number', $selectedWeek)
            ->whereIn('tutor_id', $tutorIds)
            ->get(['id', 'reviewer_id', 'tutor_id', 'created_at']);

        $reviewByAssignment = Review::query()
            ->whereIn('reviewer_assignment_id', $weekAssignments->pluck('id')->all())
            ->get(['id', 'reviewer_assignment_id', 'tutor_id', 'total_score', 'submitted_at'])
            ->keyBy('reviewer_assignment_id');

        $reviewIds = $reviewByAssignment->pluck('id')->values()->all();
        $flags = Flag::query()
            ->whereIn('review_id', $reviewIds)
            ->get(['id', 'review_id', 'color', 'initial_color', 'status', 'objection_status']);

        $scoresByTutor = $reviewByAssignment
            ->groupBy('tutor_id')
            ->map(fn ($rows) => round($rows->avg('total_score'), 2));

        $mentorAnalytics = collect($mentorNames)
            ->map(function (string $mentorName) use ($tutors, $scoresByTutor) {
                $mentorTutors = $tutors->where('mentor_name', $mentorName);
                $mentorTutorIds = $mentorTutors->pluck('id')->all();
                $mentorScores = collect($mentorTutorIds)
                    ->map(fn ($id) => $scoresByTutor[$id] ?? null)
                    ->filter(fn ($value) => $value !== null);

                return [
                    'mentor_name' => $mentorName,
                    'tutors_count' => $mentorTutors->count(),
                    'avg_tutor_score' => $mentorScores->isNotEmpty() ? round($mentorScores->avg(), 2) : null,
                ];
            })
            ->values()
            ->all();

        $reviewerPerformance = $weekAssignments
            ->groupBy('reviewer_id')
            ->map(function ($rows, $reviewerId) use ($reviewByAssignment, $flags) {
                $reviewer = $rows->first()->reviewer;
                $assignmentIds = $rows->pluck('id')->all();
                $reviews = collect($assignmentIds)
                    ->map(fn ($id) => $reviewByAssignment->get($id))
                    ->filter();
                $reviewIds = $reviews->pluck('id')->all();
                $reviewFlags = $flags->whereIn('review_id', $reviewIds);

                return [
                    'reviewer_id' => (int) $reviewerId,
                    'name' => $reviewer?->name ?? 'Unknown',
                    'reviewer_type' => $reviewer?->reviewer_type,
                    'assigned' => count($assignmentIds),
                    'completed' => $reviews->count(),
                    'avg_score' => $reviews->isNotEmpty() ? round((float) $reviews->avg('total_score'), 2) : null,
                    'flags_issued' => $reviewFlags->count(),
                    'objections' => $reviewFlags->where('objection_status', '!=', 'none')->count(),
                    'yellow_flags' => $reviewFlags->where('color', 'yellow')->count(),
                    'red_flags' => $reviewFlags->where('color', 'red')->count(),
                    'both_flags' => $reviewFlags->where('color', 'both')->count(),
                ];
            })
            ->sortByDesc('flags_issued')
            ->values()
            ->all();

        $topObjectionsByReviewer = collect($reviewerPerformance)
            ->sortByDesc('objections')
            ->take(8)
            ->values()
            ->all();

        $flagMetrics = [
            'yellow' => $flags->where('color', 'yellow')->count(),
            'red' => $flags->where('color', 'red')->count(),
            'both' => $flags->where('color', 'both')->count(),
            'removed_red' => $flags->where('status', 'removed')->where('initial_color', 'red')->count(),
            'removed_yellow' => $flags->where('status', 'removed')->where('initial_color', 'yellow')->count(),
            'removed_both' => $flags->where('status', 'removed')->where('initial_color', 'both')->count(),
            'color_changed' => $flags->filter(fn (Flag $flag) => $flag->initial_color !== null && $flag->initial_color !== $flag->color)->count(),
            'partial' => $flags->where('status', 'partial')->count(),
        ];

        $allScores = $reviewByAssignment->pluck('total_score')->filter();

        return Inertia::render('TeamLead/Home', [
            'week' => $selectedWeek,
            'weeks' => $weeks,
            'kpis' => [
                'mentors' => count($mentorNames),
                'tutors' => count($tutorIds),
                'avg_score' => $allScores->isNotEmpty() ? round((float) $allScores->avg(), 2) : 0,
                'flags' => $flags->count(),
                'objections' => $flags->where('objection_status', '!=', 'none')->count(),
            ],
            'mentorAnalytics' => $mentorAnalytics,
            'reviewerPerformance' => $reviewerPerformance,
            'flagMetrics' => $flagMetrics,
            'topObjectionsByReviewer' => $topObjectionsByReviewer,
        ]);
    }
}

