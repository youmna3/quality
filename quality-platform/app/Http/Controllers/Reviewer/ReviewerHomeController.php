<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\Tutor;
use App\Models\User;
use App\Models\WeekCycle;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ReviewerHomeController extends Controller
{
    private const LATE_THRESHOLD_HOURS = 48;
    private const MAX_REVIEWER_EDITS = 2;
    private const SLOT_OPTIONS = [
        'Fri slot 1',
        'Fri slot 2',
        'Fri slot 3',
        'Fri slot 4',
        'Sat slot 1',
        'Sat slot 2',
        'Sat slot 3',
        'Sat slot 4',
    ];

    public function index(Request $request): Response
    {
        $selectedWeek = max(1, (int) $request->query('week', 1));
        $maxAssignmentWeek = (int) (ReviewerAssignment::query()->max('week_number') ?? 0);
        $maxCycleWeek = (int) (WeekCycle::query()->max('week_number') ?? 0);
        $maxWeek = max($selectedWeek, $maxAssignmentWeek + 1, $maxCycleWeek, 1);
        $weeks = range(1, $maxWeek);
        $weekCycle = WeekCycle::query()->where('week_number', $selectedWeek)->first();
        $deadlineAt = $weekCycle?->deadline_at;

        $currentUser = $request->user();
        $assignmentModels = ReviewerAssignment::query()
            ->with([
                'tutor:id,tutor_code,name_en,mentor_name,project_id',
                'tutor.project:id,code',
                'review:id,reviewer_assignment_id',
                'review.editRequests' => fn ($query) => $query->latest('id'),
                'review.editLogs:id,review_id,actor_role',
            ])
            ->where('reviewer_id', $currentUser->id)
            ->where('week_number', $selectedWeek)
            ->latest('id')
            ->get();

        $reviewsByTutor = $this->loadReviewsByTutor($assignmentModels);
        $reviewerNameKey = $this->normalizeName((string) $currentUser->name);
        $now = now();
        $submittedCount = 0;
        $lateCount = 0;
        $assignments = [];
        $isCycleClosed = $this->isCycleClosed($weekCycle);

        foreach ($assignmentModels as $assignment) {
            $submittedAt = $this->findMatchingSubmission(
                $reviewsByTutor->get($assignment->tutor_id, collect()),
                (int) $assignment->id,
                $reviewerNameKey,
                $assignment->created_at
            );

            $isSubmitted = $submittedAt instanceof CarbonInterface;
            if ($isSubmitted) {
                $submittedCount++;
            }

            $isLate = false;
            if ($isSubmitted) {
                $completionHours = $assignment->created_at->diffInSeconds($submittedAt, false) / 3600;
                $isLate = $deadlineAt
                    ? $submittedAt->gt($deadlineAt)
                    : ($completionHours > self::LATE_THRESHOLD_HOURS);
            } else {
                $isLate = $deadlineAt
                    ? $now->gt($deadlineAt)
                    : ($assignment->created_at->diffInHours($now) > self::LATE_THRESHOLD_HOURS);
            }

            if ($isLate) {
                $lateCount++;
            }

            $latestEditRequest = $assignment->review?->editRequests?->first();
            $reviewerEditCount = (int) ($assignment->review?->editLogs?->where('actor_role', 'reviewer')->count() ?? 0);
            $editLimitReached = $reviewerEditCount >= self::MAX_REVIEWER_EDITS;
            $assignments[] = [
                'id' => $assignment->id,
                'assignment_id' => $assignment->id,
                'tutor_code' => $assignment->tutor?->tutor_code,
                'tutor_name' => $assignment->tutor?->name_en,
                'mentor_name' => $assignment->tutor?->mentor_name,
                'project_type' => $assignment->tutor?->project?->code,
                'is_submitted' => $isSubmitted,
                'submitted_at' => $submittedAt?->toDateTimeString(),
                'is_late' => $isLate,
                'review_submitted' => $assignment->review !== null,
                'is_edit_locked' => $isCycleClosed || $editLimitReached,
                'can_request_admin_edit' => ($isCycleClosed || $editLimitReached) && $assignment->review !== null,
                'reviewer_edit_count' => $reviewerEditCount,
                'remaining_reviewer_edits' => max(self::MAX_REVIEWER_EDITS - $reviewerEditCount, 0),
                'edit_limit_reached' => $editLimitReached,
                'max_reviewer_edits' => self::MAX_REVIEWER_EDITS,
                'latest_edit_request' => $latestEditRequest
                    ? [
                        'status' => $latestEditRequest->status,
                        'message' => $latestEditRequest->message,
                        'created_at' => $latestEditRequest->created_at?->toDateTimeString(),
                        'reviewed_at' => $latestEditRequest->reviewed_at?->toDateTimeString(),
                        'admin_note' => $latestEditRequest->admin_note,
                    ]
                    : null,
            ];
        }

        $pendingCount = max(count($assignments) - $submittedCount, 0);

        return Inertia::render('Reviewer/Home', [
            'week' => $selectedWeek,
            'weeks' => $weeks,
            'weekCycle' => [
                'starts_at' => $weekCycle?->starts_at?->toDateTimeString(),
                'deadline_at' => $weekCycle?->deadline_at?->toDateTimeString(),
            ],
            'kpis' => [
                'active_tutors' => Tutor::query()->where('is_active', true)->count(),
                'reviewers' => User::query()->where('role', 'reviewer')->count(),
                'assigned_this_week' => count($assignments),
                'submitted_this_week' => $submittedCount,
                'pending_this_week' => $pendingCount,
                'late_this_week' => $lateCount,
            ],
            'assignments' => $assignments,
            'leaderboard' => $this->buildLeaderboard($selectedWeek, $deadlineAt),
            'reviewerType' => $currentUser->reviewer_type,
            'slotOptions' => self::SLOT_OPTIONS,
        ]);
    }

    private function loadReviewsByTutor(Collection $assignments): Collection
    {
        $tutorIds = $assignments
            ->pluck('tutor_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tutorIds === []) {
            return collect();
        }

        return Review::query()
            ->whereIn('tutor_id', $tutorIds)
            ->orderBy('submitted_at')
            ->get(['tutor_id', 'reviewer_name', 'reviewer_assignment_id', 'submitted_at'])
            ->groupBy('tutor_id');
    }

    private function buildLeaderboard(int $week, ?CarbonInterface $deadlineAt): array
    {
        $reviewers = User::query()
            ->where('role', 'reviewer')
            ->orderBy('name')
            ->get(['id', 'name', 'reviewer_type']);

        $reviewersById = $reviewers->keyBy('id');
        $weekAssignments = ReviewerAssignment::query()
            ->where('week_number', $week)
            ->get(['reviewer_id', 'tutor_id', 'created_at']);

        if ($weekAssignments->isEmpty()) {
            return [];
        }

        $reviewsByTutor = $this->loadReviewsByTutor($weekAssignments);
        $now = now();
        $metrics = [];

        foreach ($weekAssignments as $assignment) {
            $reviewer = $reviewersById->get((int) $assignment->reviewer_id);
            if (! $reviewer) {
                continue;
            }

            $reviewerId = (int) $reviewer->id;
            if (! isset($metrics[$reviewerId])) {
                $metrics[$reviewerId] = [
                    'id' => $reviewerId,
                    'name' => $reviewer->name,
                    'reviewer_type' => $reviewer->reviewer_type,
                    'assigned' => 0,
                    'completed' => 0,
                    'late' => 0,
                    'completion_seconds_sum' => 0,
                ];
            }

            $metrics[$reviewerId]['assigned']++;
            $submittedAt = $this->findMatchingSubmission(
                $reviewsByTutor->get($assignment->tutor_id, collect()),
                (int) $assignment->id,
                $this->normalizeName((string) $reviewer->name),
                $assignment->created_at
            );

            if ($submittedAt instanceof CarbonInterface) {
                $completionSeconds = max(0, $assignment->created_at->diffInSeconds($submittedAt, false));
                $metrics[$reviewerId]['completed']++;
                $metrics[$reviewerId]['completion_seconds_sum'] += $completionSeconds;

                $isLate = $deadlineAt
                    ? $submittedAt->gt($deadlineAt)
                    : (($completionSeconds / 3600) > self::LATE_THRESHOLD_HOURS);
                if ($isLate) {
                    $metrics[$reviewerId]['late']++;
                }

                continue;
            }

            $pendingLate = $deadlineAt
                ? $now->gt($deadlineAt)
                : ($assignment->created_at->diffInHours($now) > self::LATE_THRESHOLD_HOURS);
            if ($pendingLate) {
                $metrics[$reviewerId]['late']++;
            }
        }

        return collect($metrics)
            ->map(function (array $row) {
                $row['avg_completion_hours'] = $row['completed'] > 0
                    ? round(($row['completion_seconds_sum'] / $row['completed']) / 3600, 2)
                    : null;
                $row['completion_rate'] = $row['assigned'] > 0
                    ? round(($row['completed'] / $row['assigned']) * 100, 1)
                    : 0.0;
                unset($row['completion_seconds_sum']);

                return $row;
            })
            ->sort(function (array $a, array $b) {
                if ($a['completed'] !== $b['completed']) {
                    return $b['completed'] <=> $a['completed'];
                }

                if ($a['completion_rate'] !== $b['completion_rate']) {
                    return $b['completion_rate'] <=> $a['completion_rate'];
                }

                $aHours = $a['avg_completion_hours'] ?? INF;
                $bHours = $b['avg_completion_hours'] ?? INF;

                return $aHours <=> $bHours;
            })
            ->take(8)
            ->values()
            ->all();
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

    private function isCycleClosed(?WeekCycle $weekCycle): bool
    {
        if (! $weekCycle?->deadline_at) {
            return false;
        }

        return now()->gt($weekCycle->deadline_at);
    }
}
