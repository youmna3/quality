<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Flag;
use App\Models\MentorTeamLeadAssignment;
use App\Models\Review;
use App\Models\ReviewEditLog;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Models\WeekCycle;
use App\Support\ReviewCriteria;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AdminAnalyticsController extends Controller
{
    private const LATE_THRESHOLD_HOURS = 48;

    public function index(Request $request): Response
    {
        $requestedWeek = (int) $request->query('week', 1);
        $isAllWeeks = $requestedWeek <= 0;
        $selectedWeek = $isAllWeeks ? 0 : max(1, $requestedWeek);
        $maxAssignmentWeek = (int) (ReviewerAssignment::query()->max('week_number') ?? 0);
        $maxCycleWeek = (int) (WeekCycle::query()->max('week_number') ?? 0);
        $maxWeek = max($selectedWeek > 0 ? $selectedWeek : 1, $maxAssignmentWeek + 1, $maxCycleWeek, 1);
        $weeks = range(1, $maxWeek);
        $weekCycle = $isAllWeeks
            ? null
            : WeekCycle::query()->where('week_number', $selectedWeek)->first();
        $deadlineAt = $weekCycle?->deadline_at;

        $reviewers = User::query()
            ->where('role', 'reviewer')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'reviewer_type', 'is_active']);

        $weekAssignments = ReviewerAssignment::query()
            ->when(! $isAllWeeks, fn ($query) => $query->where('week_number', $selectedWeek))
            ->get(['id', 'reviewer_id', 'tutor_id', 'created_at']);

        $allTimeAssignedCounts = ReviewerAssignment::query()
            ->selectRaw('reviewer_id, COUNT(*) as assigned_count')
            ->groupBy('reviewer_id')
            ->pluck('assigned_count', 'reviewer_id');

        $weekMetricsByReviewer = $this->buildWeekMetrics(
            $weekAssignments,
            $reviewers->keyBy('id'),
            $deadlineAt
        );

        $reviewerRows = $reviewers
            ->map(function (User $reviewer) use ($weekMetricsByReviewer, $allTimeAssignedCounts) {
                $reviewerId = (int) $reviewer->id;
                $weekMetrics = $weekMetricsByReviewer[$reviewerId] ?? [
                    'assigned_week' => 0,
                    'completed_week' => 0,
                    'pending_week' => 0,
                    'late_submitted_week' => 0,
                    'late_pending_week' => 0,
                    'avg_completion_hours' => null,
                    'completion_rate' => 0.0,
                ];

                return [
                    'id' => $reviewerId,
                    'name' => $reviewer->name,
                    'email' => $reviewer->email,
                    'reviewer_type' => $reviewer->reviewer_type,
                    'is_active' => (bool) $reviewer->is_active,
                    'assigned_week' => $weekMetrics['assigned_week'],
                    'completed_week' => $weekMetrics['completed_week'],
                    'pending_week' => $weekMetrics['pending_week'],
                    'late_submitted_week' => $weekMetrics['late_submitted_week'],
                    'late_pending_week' => $weekMetrics['late_pending_week'],
                    'avg_completion_hours' => $weekMetrics['avg_completion_hours'],
                    'completion_rate' => $weekMetrics['completion_rate'],
                    'assigned_all_time' => (int) ($allTimeAssignedCounts[$reviewerId] ?? 0),
                ];
            })
            ->values();

        $assignedWeekTotal = $reviewerRows->sum('assigned_week');
        $completedWeekTotal = $reviewerRows->sum('completed_week');
        $pendingWeekTotal = $reviewerRows->sum('pending_week');
        $lateSubmittedWeekTotal = $reviewerRows->sum('late_submitted_week');
        $latePendingWeekTotal = $reviewerRows->sum('late_pending_week');
        $onTimeRate = $completedWeekTotal > 0
            ? round((($completedWeekTotal - $lateSubmittedWeekTotal) / $completedWeekTotal) * 100, 1)
            : 0.0;

        $topFastReviewers = $reviewerRows
            ->filter(fn (array $row) => $row['completed_week'] > 0 && $row['avg_completion_hours'] !== null)
            ->sort(function (array $a, array $b) {
                if ($a['avg_completion_hours'] === $b['avg_completion_hours']) {
                    return $b['completion_rate'] <=> $a['completion_rate'];
                }

                return $a['avg_completion_hours'] <=> $b['avg_completion_hours'];
            })
            ->take(5)
            ->values();

        $weekReviews = Review::query()
            ->with([
                'reviewerAssignment:id,week_number',
                'tutor:id,mentor_name',
            ])
            ->when(
                ! $isAllWeeks,
                fn ($query) => $query->whereHas('reviewerAssignment', fn ($innerQuery) => $innerQuery->where('week_number', $selectedWeek))
            )
            ->get(['id', 'reviewer_name', 'tutor_id', 'total_score', 'reviewer_assignment_id', 'negative_comments_json']);

        $criteriaMap = ReviewCriteria::criteriaMap();
        $negativeCommentStats = [];

        foreach ($weekReviews as $review) {
            $negativePayload = $review->negative_comments_json;
            if (! is_array($negativePayload)) {
                continue;
            }

            foreach ($negativePayload as $criterionKey => $comments) {
                if (! is_array($comments)) {
                    continue;
                }

                foreach ($comments as $comment) {
                    $commentText = trim((string) $comment);
                    if ($commentText === '') {
                        continue;
                    }

                    $hash = $criterionKey.'|'.$this->normalizeText($commentText);
                    if (! isset($negativeCommentStats[$hash])) {
                        $negativeCommentStats[$hash] = [
                            'comment_text' => $commentText,
                            'criterion_label' => $criteriaMap[$criterionKey]['label'] ?? 'General',
                            'occurrences' => 0,
                            'tutor_ids' => [],
                        ];
                    }

                    $negativeCommentStats[$hash]['occurrences']++;
                    $negativeCommentStats[$hash]['tutor_ids'][(int) $review->tutor_id] = true;
                }
            }
        }

        $topNegativeComments = collect($negativeCommentStats)
            ->map(function (array $row) {
                return [
                    'comment_text' => $row['comment_text'],
                    'criterion_label' => $row['criterion_label'],
                    'occurrences' => (int) $row['occurrences'],
                    'tutors_count' => count($row['tutor_ids']),
                ];
            })
            ->filter(fn (array $row) => $row['occurrences'] > 1)
            ->sort(function (array $a, array $b) {
                if ($a['occurrences'] !== $b['occurrences']) {
                    return $b['occurrences'] <=> $a['occurrences'];
                }

                if ($a['tutors_count'] !== $b['tutors_count']) {
                    return $b['tutors_count'] <=> $a['tutors_count'];
                }

                return strcmp($a['comment_text'], $b['comment_text']);
            })
            ->take(20)
            ->values()
            ->all();

        $weekReviewIds = $weekReviews->pluck('id')->all();
        $weekFlags = Flag::query()
            ->whereIn('review_id', $weekReviewIds)
            ->get(['id', 'review_id', 'color', 'initial_color', 'status', 'objection_status']);

        $flagMetrics = [
            'total_flags' => $weekFlags->count(),
            'objections' => $weekFlags->where('objection_status', '!=', 'none')->count(),
            'yellow' => $weekFlags->where('color', 'yellow')->count(),
            'red' => $weekFlags->where('color', 'red')->count(),
            'both' => $weekFlags->where('color', 'both')->count(),
            'removed_red' => $weekFlags->where('status', 'removed')->where('initial_color', 'red')->count(),
            'removed_yellow' => $weekFlags->where('status', 'removed')->where('initial_color', 'yellow')->count(),
            'removed_both' => $weekFlags->where('status', 'removed')->where('initial_color', 'both')->count(),
            'partial' => $weekFlags->where('status', 'partial')->count(),
            'color_changed' => $weekFlags->filter(fn (Flag $flag) => $flag->initial_color !== null && $flag->initial_color !== $flag->color)->count(),
        ];

        $avgScoreByMentor = $weekReviews
            ->groupBy(fn (Review $review) => trim((string) ($review->tutor?->mentor_name ?? 'Unknown')))
            ->map(fn ($rows, $mentorName) => [
                'mentor_name' => $mentorName !== '' ? $mentorName : 'Unknown',
                'avg_score' => round((float) $rows->avg('total_score'), 2),
                'reviews_count' => $rows->count(),
            ])
            ->values()
            ->sortByDesc('avg_score')
            ->all();

        $mentorToTeamLead = MentorTeamLeadAssignment::query()
            ->with('teamLead:id,name')
            ->get()
            ->mapWithKeys(fn ($assignment) => [
                strtolower(trim((string) $assignment->mentor_name)) => $assignment->teamLead?->name ?? 'Unassigned Team Lead',
            ]);

        $avgScoreByTeamLead = $weekReviews
            ->groupBy(function (Review $review) use ($mentorToTeamLead) {
                $mentorKey = strtolower(trim((string) ($review->tutor?->mentor_name ?? '')));

                return $mentorToTeamLead[$mentorKey] ?? 'Unassigned Team Lead';
            })
            ->map(fn ($rows, $teamLeadName) => [
                'team_lead_name' => $teamLeadName,
                'avg_score' => round((float) $rows->avg('total_score'), 2),
                'reviews_count' => $rows->count(),
            ])
            ->values()
            ->sortByDesc('avg_score')
            ->all();

        $reviewById = $weekReviews->keyBy('id');
        $reviewerFlagAnalytics = $weekFlags
            ->groupBy(function (Flag $flag) use ($reviewById) {
                return $reviewById[$flag->review_id]?->reviewer_name ?? 'Unknown Reviewer';
            })
            ->map(function ($rows, $reviewerName) {
                return [
                    'reviewer_name' => $reviewerName,
                    'flags_count' => $rows->count(),
                    'objections_count' => $rows->where('objection_status', '!=', 'none')->count(),
                    'yellow' => $rows->where('color', 'yellow')->count(),
                    'red' => $rows->where('color', 'red')->count(),
                    'both' => $rows->where('color', 'both')->count(),
                    'removed' => $rows->where('status', 'removed')->count(),
                    'partial' => $rows->where('status', 'partial')->count(),
                    'color_changed' => $rows->filter(fn (Flag $flag) => $flag->initial_color !== null && $flag->initial_color !== $flag->color)->count(),
                ];
            })
            ->values()
            ->sortByDesc('objections_count')
            ->all();

        $trendReviews = Review::query()
            ->with([
                'reviewerAssignment:id,week_number',
                'flags:id,review_id,color',
            ])
            ->whereHas('reviewerAssignment')
            ->get(['id', 'reviewer_assignment_id', 'total_score']);

        $trendReviewsByWeek = $trendReviews
            ->filter(fn (Review $review) => (int) ($review->reviewerAssignment?->week_number ?? 0) > 0)
            ->groupBy(fn (Review $review) => (int) $review->reviewerAssignment?->week_number);

        $weeklyScoreTrend = collect($weeks)
            ->map(function (int $weekNumber) use ($trendReviewsByWeek) {
                $rows = $trendReviewsByWeek->get($weekNumber, collect());

                return [
                    'week' => $weekNumber,
                    'avg_score' => $rows->isNotEmpty()
                        ? round((float) $rows->avg('total_score'), 2)
                        : 0.0,
                    'reviews_count' => $rows->count(),
                ];
            })
            ->values()
            ->all();

        $weeklyFlagTrend = collect($weeks)
            ->map(function (int $weekNumber) use ($trendReviewsByWeek) {
                $rows = $trendReviewsByWeek->get($weekNumber, collect());
                $flags = $rows->flatMap(fn (Review $review) => $review->flags);

                return [
                    'week' => $weekNumber,
                    'yellow' => $flags->where('color', 'yellow')->count(),
                    'red' => $flags->where('color', 'red')->count(),
                    'both' => $flags->where('color', 'both')->count(),
                    'total_flags' => $flags->count(),
                    'reviews_count' => $rows->count(),
                ];
            })
            ->values()
            ->all();

        $fieldLabelMap = [
            'tutor_role' => 'Tutor Role',
            'session_date' => 'Session Date',
            'slot' => 'Slot',
            'group_code' => 'Group ID',
            'recorded_link' => 'Recorded Link',
            'issue_text' => 'Issue Text',
            'positive_comments_json' => 'Positive Comments',
            'negative_comments_json' => 'Negative Comments',
            'score_breakdown_json' => 'Score Breakdown',
            'total_score' => 'Total Score',
            'flags' => 'Flags',
        ];

        $allEditLogs = ReviewEditLog::query()
            ->with([
                'review:id,tutor_id,reviewer_assignment_id,session_date,slot,group_code',
                'review.tutor:id,tutor_code,name_en',
                'review.reviewerAssignment:id,week_number',
            ])
            ->latest('created_at')
            ->get([
                'id',
                'review_id',
                'actor_id',
                'actor_name',
                'actor_role',
                'changed_fields_json',
                'created_at',
            ]);

        $scopedEditLogs = ReviewEditLog::query()
            ->with([
                'review:id,tutor_id,reviewer_assignment_id,session_date,slot,group_code',
                'review.tutor:id,tutor_code,name_en',
                'review.reviewerAssignment:id,week_number',
            ])
            ->when(
                ! $isAllWeeks,
                fn ($query) => $query->whereHas('review.reviewerAssignment', fn ($innerQuery) => $innerQuery->where('week_number', $selectedWeek))
            )
            ->latest('created_at')
            ->get([
                'id',
                'review_id',
                'actor_id',
                'actor_name',
                'actor_role',
                'changed_fields_json',
                'created_at',
            ]);

        $reviewerEditAnalytics = $allEditLogs
            ->where('actor_role', 'reviewer')
            ->groupBy(fn (ReviewEditLog $log) => (int) ($log->actor_id ?? 0))
            ->map(function (Collection $logs) use ($fieldLabelMap) {
                $firstLog = $logs->first();
                $fieldCounts = [];
                $sessionMap = [];

                foreach ($logs as $log) {
                    foreach (array_keys($log->changed_fields_json ?? []) as $fieldKey) {
                        $fieldCounts[$fieldKey] = ($fieldCounts[$fieldKey] ?? 0) + 1;
                    }

                    $review = $log->review;
                    $sessionKey = implode('|', [
                        (string) ($review?->reviewerAssignment?->week_number ?? ''),
                        (string) ($review?->tutor?->tutor_code ?? ''),
                        (string) ($review?->session_date?->toDateString() ?? ''),
                        (string) ($review?->slot ?? ''),
                        (string) ($review?->group_code ?? ''),
                    ]);

                    if ($sessionKey !== '||||') {
                        if (! isset($sessionMap[$sessionKey])) {
                            $sessionMap[$sessionKey] = [
                                'week_number' => $review?->reviewerAssignment?->week_number,
                                'tutor_id' => $review?->tutor?->tutor_code,
                                'tutor_name' => $review?->tutor?->name_en,
                                'session_date' => $review?->session_date?->toDateString(),
                                'slot' => $review?->slot,
                                'group_code' => $review?->group_code,
                                'edit_count' => 0,
                                'last_edit_at' => null,
                            ];
                        }

                        $sessionMap[$sessionKey]['edit_count']++;
                        $sessionMap[$sessionKey]['last_edit_at'] = $log->created_at?->toDateTimeString();
                    }
                }

                arsort($fieldCounts);

                return [
                    'reviewer_id' => (int) ($firstLog?->actor_id ?? 0),
                    'reviewer_name' => $firstLog?->actor_name ?? 'Unknown Reviewer',
                    'edit_count' => $logs->count(),
                    'reviews_edited_count' => $logs->pluck('review_id')->unique()->count(),
                    'last_edit_at' => $logs->max('created_at')?->toDateTimeString(),
                    'fields_changed' => collect($fieldCounts)
                        ->map(fn ($count, $fieldKey) => [
                            'field_key' => $fieldKey,
                            'field_label' => $fieldLabelMap[$fieldKey] ?? ucfirst(str_replace('_', ' ', $fieldKey)),
                            'count' => $count,
                        ])
                        ->values()
                        ->all(),
                    'sessions' => collect($sessionMap)
                        ->sortByDesc('last_edit_at')
                        ->take(6)
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc('edit_count')
            ->values()
            ->all();

        $recentEditLogs = $allEditLogs
            ->take(30)
            ->map(function (ReviewEditLog $log) use ($fieldLabelMap) {
                return [
                    'id' => $log->id,
                    'week_number' => $log->review?->reviewerAssignment?->week_number,
                    'actor_name' => $log->actor_name,
                    'actor_role' => $log->actor_role,
                    'tutor_id' => $log->review?->tutor?->tutor_code,
                    'tutor_name' => $log->review?->tutor?->name_en,
                    'session_date' => $log->review?->session_date?->toDateString(),
                    'slot' => $log->review?->slot,
                    'group_code' => $log->review?->group_code,
                    'changed_fields' => collect(array_keys($log->changed_fields_json ?? []))
                        ->map(fn ($fieldKey) => $fieldLabelMap[$fieldKey] ?? ucfirst(str_replace('_', ' ', $fieldKey)))
                        ->values()
                        ->all(),
                    'edited_at' => $log->created_at?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();

        $scopedReviewerEditCount = $scopedEditLogs->where('actor_role', 'reviewer')->count();

        return Inertia::render('Admin/Analytics/Index', [
            'week' => $selectedWeek,
            'isAllWeeks' => $isAllWeeks,
            'weeks' => $weeks,
            'weekCycle' => [
                'starts_at' => $weekCycle?->starts_at?->toDateTimeString(),
                'deadline_at' => $weekCycle?->deadline_at?->toDateTimeString(),
            ],
            'kpis' => [
                'total_reviewers' => $reviewers->count(),
                'active_reviewers' => $reviewers->where('is_active', true)->count(),
                'assigned_week' => $assignedWeekTotal,
                'completed_week' => $completedWeekTotal,
                'pending_week' => $pendingWeekTotal,
                'late_submitted_week' => $lateSubmittedWeekTotal,
                'late_pending_week' => $latePendingWeekTotal,
                'on_time_rate' => $onTimeRate,
                'late_threshold_hours' => self::LATE_THRESHOLD_HOURS,
                'reviewer_edit_events' => $allEditLogs->where('actor_role', 'reviewer')->count(),
                'reviewer_edit_events_scope' => $scopedReviewerEditCount,
                'reviewers_with_edits' => collect($reviewerEditAnalytics)->where('edit_count', '>', 0)->count(),
            ],
            'reviewerAnalytics' => $reviewerRows,
            'topFastReviewers' => $topFastReviewers,
            'flagMetrics' => $flagMetrics,
            'avgScoreByMentor' => $avgScoreByMentor,
            'avgScoreByTeamLead' => $avgScoreByTeamLead,
            'reviewerFlagAnalytics' => $reviewerFlagAnalytics,
            'topNegativeComments' => $topNegativeComments,
            'weeklyScoreTrend' => $weeklyScoreTrend,
            'weeklyFlagTrend' => $weeklyFlagTrend,
            'reviewerEditAnalytics' => $reviewerEditAnalytics,
            'recentEditLogs' => $recentEditLogs,
            'reviewerEditScopeHasData' => $scopedReviewerEditCount > 0,
        ]);
    }

    private function buildWeekMetrics(
        Collection $weekAssignments,
        Collection $reviewersById,
        ?CarbonInterface $deadlineAt
    ): array
    {
        if ($weekAssignments->isEmpty()) {
            return [];
        }

        $tutorIds = $weekAssignments
            ->pluck('tutor_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tutorIds === []) {
            return [];
        }

        $reviewsByTutor = Review::query()
            ->whereIn('tutor_id', $tutorIds)
            ->orderBy('submitted_at')
            ->get(['tutor_id', 'reviewer_name', 'reviewer_assignment_id', 'submitted_at'])
            ->groupBy('tutor_id');

        $now = now();
        $metricsByReviewer = [];

        foreach ($weekAssignments as $assignment) {
            $reviewerId = (int) $assignment->reviewer_id;
            $reviewer = $reviewersById->get($reviewerId);

            if (! $reviewer) {
                continue;
            }

            if (! isset($metricsByReviewer[$reviewerId])) {
                $metricsByReviewer[$reviewerId] = [
                    'assigned_week' => 0,
                    'completed_week' => 0,
                    'pending_week' => 0,
                    'late_submitted_week' => 0,
                    'late_pending_week' => 0,
                    'completion_seconds_sum' => 0,
                ];
            }

            $metricsByReviewer[$reviewerId]['assigned_week']++;

            $matchedSubmittedAt = $this->findMatchingSubmission(
                $reviewsByTutor->get($assignment->tutor_id, collect()),
                (int) $assignment->id,
                $this->normalizeName((string) $reviewer->name),
                $assignment->created_at
            );

            if ($matchedSubmittedAt instanceof CarbonInterface) {
                $metricsByReviewer[$reviewerId]['completed_week']++;
                $completionSeconds = max(0, $assignment->created_at->diffInSeconds($matchedSubmittedAt, false));
                $metricsByReviewer[$reviewerId]['completion_seconds_sum'] += $completionSeconds;

                $isLateSubmission = $deadlineAt
                    ? $matchedSubmittedAt->gt($deadlineAt)
                    : (($completionSeconds / 3600) > self::LATE_THRESHOLD_HOURS);
                if ($isLateSubmission) {
                    $metricsByReviewer[$reviewerId]['late_submitted_week']++;
                }

                continue;
            }

            $metricsByReviewer[$reviewerId]['pending_week']++;
            $isLatePending = $deadlineAt
                ? $now->gt($deadlineAt)
                : ($assignment->created_at->diffInHours($now) > self::LATE_THRESHOLD_HOURS);
            if ($isLatePending) {
                $metricsByReviewer[$reviewerId]['late_pending_week']++;
            }
        }

        foreach ($metricsByReviewer as &$metrics) {
            $completed = $metrics['completed_week'];
            $assigned = $metrics['assigned_week'];

            $metrics['avg_completion_hours'] = $completed > 0
                ? round(($metrics['completion_seconds_sum'] / $completed) / 3600, 2)
                : null;
            $metrics['completion_rate'] = $assigned > 0
                ? round(($completed / $assigned) * 100, 1)
                : 0.0;
            unset($metrics['completion_seconds_sum']);
        }
        unset($metrics);

        return $metricsByReviewer;
    }

    private function findMatchingSubmission(
        Collection $reviewsForTutor,
        int $assignmentId,
        string $reviewerNameKey,
        CarbonInterface $assignedAt
    ): ?CarbonInterface {
        foreach ($reviewsForTutor as $review) {
            if ((int) ($review->reviewer_assignment_id ?? 0) === $assignmentId && $review->submitted_at instanceof CarbonInterface) {
                return $review->submitted_at;
            }

            if ($this->normalizeName((string) $review->reviewer_name) !== $reviewerNameKey) {
                continue;
            }

            if (! $review->submitted_at instanceof CarbonInterface) {
                continue;
            }

            if ($review->submitted_at->lt($assignedAt)) {
                continue;
            }

            return $review->submitted_at;
        }

        return null;
    }

    private function normalizeName(string $value): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }

    private function normalizeText(string $value): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }
}
