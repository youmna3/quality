<?php

namespace App\Services;

use App\Models\MentorCoordinatorAssignment;
use App\Models\ReviewerAssignment;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReviewerAutoAssignmentService
{
    private const MENTOR_WEIGHT = 1.0;
    private const COORDINATOR_WEIGHT = 1.5;

    public function assignForWeek(
        int $week,
        ?int $assignedByUserId = null,
        bool $replaceExistingForWeek = false
    ): array
    {
        if ($week < 1) {
            throw new RuntimeException('Week must be at least 1.');
        }

        $reviewers = User::query()
            ->where('role', 'reviewer')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'reviewer_type']);

        if ($reviewers->isEmpty()) {
            throw new RuntimeException('No active reviewers found.');
        }

        $tutors = Tutor::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'tutor_code', 'mentor_name']);

        if ($tutors->isEmpty()) {
            return [
                'assigned' => 0,
                'already_assigned' => 0,
                'total_tutors' => 0,
            ];
        }

        $reviewerIds = $reviewers->pluck('id')->all();
        $reviewerByNormalizedName = [];
        $reviewerTypeById = [];
        $reviewerPool = [];
        $typeCapacity = [
            'mentor' => 0.0,
            'coordinator' => 0.0,
        ];

        foreach ($reviewers as $reviewer) {
            $normalizedName = $this->normalizeName((string) $reviewer->name);
            if ($normalizedName !== '') {
                $reviewerByNormalizedName[$normalizedName] = (int) $reviewer->id;
            }

            $type = $reviewer->reviewer_type === 'coordinator' ? 'coordinator' : 'mentor';
            $weight = $type === 'coordinator'
                ? self::COORDINATOR_WEIGHT
                : self::MENTOR_WEIGHT;

            $reviewerTypeById[(int) $reviewer->id] = $type;
            $typeCapacity[$type] += $weight;
            $reviewerPool[] = [
                'id' => (int) $reviewer->id,
                'name' => $reviewer->name,
                'reviewer_type' => $type,
            ];
        }

        $coordinatorByMentor = MentorCoordinatorAssignment::query()
            ->whereNotNull('coordinator_user_id')
            ->get(['mentor_name', 'coordinator_user_id'])
            ->mapWithKeys(function (MentorCoordinatorAssignment $assignment) {
                return [$this->normalizeName($assignment->mentor_name) => (int) $assignment->coordinator_user_id];
            })
            ->all();

        $existingWeekAssignments = ReviewerAssignment::query()
            ->where('week_number', $week)
            ->get(['id', 'tutor_id', 'reviewer_id', 'reviewer_type']);

        $existingWeekByTutor = $existingWeekAssignments
            ->keyBy(fn (ReviewerAssignment $assignment) => (int) $assignment->tutor_id);

        $alreadyAssignedTutorIds = $replaceExistingForWeek
            ? []
            : $existingWeekAssignments
                ->pluck('tutor_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $weekLoad = array_fill_keys($reviewerIds, 0);
        $typeLoad = [
            'mentor' => 0,
            'coordinator' => 0,
        ];

        if (! $replaceExistingForWeek) {
            foreach ($existingWeekAssignments as $assignment) {
                $reviewerId = (int) $assignment->reviewer_id;
                if (array_key_exists($reviewerId, $weekLoad)) {
                    $weekLoad[$reviewerId]++;
                }

                $type = $assignment->reviewer_type;
                if (! in_array($type, ['mentor', 'coordinator'], true)) {
                    $type = $reviewerTypeById[$reviewerId] ?? 'mentor';
                }
                $typeLoad[$type] = ($typeLoad[$type] ?? 0) + 1;
            }
        }

        $globalLoad = array_fill_keys($reviewerIds, 0);
        $globalCounts = ReviewerAssignment::query()
            ->whereIn('reviewer_id', $reviewerIds)
            ->when($replaceExistingForWeek, fn ($query) => $query->where('week_number', '<>', $week))
            ->selectRaw('reviewer_id, COUNT(*) as assignments_count')
            ->groupBy('reviewer_id')
            ->pluck('assignments_count', 'reviewer_id');

        foreach ($globalCounts as $reviewerId => $count) {
            $reviewerId = (int) $reviewerId;
            if (array_key_exists($reviewerId, $globalLoad)) {
                $globalLoad[$reviewerId] = (int) $count;
            }
        }

        $usedByTutor = [];
        ReviewerAssignment::query()
            ->whereIn('tutor_id', $tutors->pluck('id'))
            ->when($replaceExistingForWeek, fn ($query) => $query->where('week_number', '<>', $week))
            ->get(['tutor_id', 'reviewer_id'])
            ->each(function (ReviewerAssignment $assignment) use (&$usedByTutor) {
                $tutorId = (int) $assignment->tutor_id;
                $reviewerId = (int) $assignment->reviewer_id;
                $usedByTutor[$tutorId][$reviewerId] = true;
            });

        $tutorsToAssign = $replaceExistingForWeek
            ? $tutors->values()->all()
            : $tutors
                ->reject(fn (Tutor $tutor) => in_array((int) $tutor->id, $alreadyAssignedTutorIds, true))
                ->values()
                ->all();
        shuffle($tutorsToAssign);

        $rowsToInsert = [];
        $rowsToUpdate = [];
        $conflicts = [];
        $reassigned = 0;
        $unchanged = 0;

        foreach ($tutorsToAssign as $tutor) {
            $mentorKey = $this->normalizeName((string) $tutor->mentor_name);
            $blockedReviewerIds = [];

            if ($mentorKey !== '' && isset($reviewerByNormalizedName[$mentorKey])) {
                $blockedReviewerIds[$reviewerByNormalizedName[$mentorKey]] = true;
            }

            if ($mentorKey !== '' && isset($coordinatorByMentor[$mentorKey])) {
                $blockedReviewerIds[(int) $coordinatorByMentor[$mentorKey]] = true;
            }

            $eligible = [];
            foreach ($reviewerPool as $reviewer) {
                $reviewerId = $reviewer['id'];
                if (isset($blockedReviewerIds[$reviewerId])) {
                    continue;
                }

                if (isset($usedByTutor[(int) $tutor->id][$reviewerId])) {
                    continue;
                }

                $eligible[] = $reviewer;
            }

            if ($eligible === []) {
                $conflicts[] = sprintf(
                    'Tutor %s has no eligible reviewer (mentor: %s).',
                    $tutor->tutor_code,
                    $tutor->mentor_name ?: '-'
                );
                continue;
            }

            $chosen = $this->pickReviewer($eligible, $weekLoad, $globalLoad, $typeLoad, $typeCapacity);

            $tutorId = (int) $tutor->id;
            $existingForTutor = $existingWeekByTutor->get($tutorId);

            if ($replaceExistingForWeek && $existingForTutor) {
                $previousReviewerId = (int) $existingForTutor->reviewer_id;
                if ($previousReviewerId !== $chosen['id']) {
                    $reassigned++;
                } else {
                    $unchanged++;
                }

                $rowsToUpdate[] = [
                    'id' => (int) $existingForTutor->id,
                    'reviewer_id' => $chosen['id'],
                    'reviewer_type' => $chosen['reviewer_type'],
                    'assigned_by' => $assignedByUserId,
                    'updated_at' => now(),
                ];
            } else {
                $rowsToInsert[] = [
                    'week_number' => $week,
                    'tutor_id' => $tutorId,
                    'reviewer_id' => $chosen['id'],
                    'reviewer_type' => $chosen['reviewer_type'],
                    'assigned_by' => $assignedByUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $usedByTutor[$tutorId][$chosen['id']] = true;
            $weekLoad[$chosen['id']] = ($weekLoad[$chosen['id']] ?? 0) + 1;
            $globalLoad[$chosen['id']] = ($globalLoad[$chosen['id']] ?? 0) + 1;
            $typeLoad[$chosen['reviewer_type']] = ($typeLoad[$chosen['reviewer_type']] ?? 0) + 1;
        }

        if ($conflicts !== []) {
            throw new RuntimeException(
                "Auto assignment stopped because some tutors have no eligible reviewer:\n"
                .implode("\n", array_slice($conflicts, 0, 20))
            );
        }

        if ($rowsToInsert !== [] || $rowsToUpdate !== []) {
            DB::transaction(function () use ($rowsToInsert, $rowsToUpdate) {
                if ($rowsToInsert !== []) {
                    ReviewerAssignment::query()->insert($rowsToInsert);
                }

                foreach ($rowsToUpdate as $row) {
                    ReviewerAssignment::query()
                        ->where('id', $row['id'])
                        ->update([
                            'reviewer_id' => $row['reviewer_id'],
                            'reviewer_type' => $row['reviewer_type'],
                            'assigned_by' => $row['assigned_by'],
                            'updated_at' => $row['updated_at'],
                        ]);
                }
            });
        }

        if ($replaceExistingForWeek) {
            return [
                'assigned' => count($rowsToInsert) + count($rowsToUpdate),
                'created' => count($rowsToInsert),
                'updated' => count($rowsToUpdate),
                'reassigned' => $reassigned,
                'unchanged' => $unchanged,
                'already_assigned' => $existingWeekAssignments->count(),
                'total_tutors' => $tutors->count(),
            ];
        }

        return [
            'assigned' => count($rowsToInsert),
            'created' => count($rowsToInsert),
            'updated' => 0,
            'reassigned' => 0,
            'unchanged' => 0,
            'already_assigned' => count($alreadyAssignedTutorIds),
            'total_tutors' => $tutors->count(),
        ];
    }

    private function pickReviewer(
        array $eligible,
        array $weekLoad,
        array $globalLoad,
        array $typeLoad,
        array $typeCapacity
    ): array
    {
        $eligibleTypeProgress = [];
        foreach ($eligible as $reviewer) {
            $type = $reviewer['reviewer_type'] ?? 'mentor';
            $capacity = (float) ($typeCapacity[$type] ?? 0.0);
            if ($capacity <= 0) {
                continue;
            }

            $eligibleTypeProgress[$type] = ($typeLoad[$type] ?? 0) / $capacity;
        }

        if ($eligibleTypeProgress !== []) {
            $minProgress = min($eligibleTypeProgress);
            $allowedTypes = array_keys(array_filter(
                $eligibleTypeProgress,
                fn (float $progress) => $progress <= ($minProgress + 0.08)
            ));

            $eligible = array_values(array_filter(
                $eligible,
                fn (array $reviewer) => in_array($reviewer['reviewer_type'] ?? 'mentor', $allowedTypes, true)
            ));
        }

        $bestScore = INF;
        $bestCandidates = [];

        foreach ($eligible as $reviewer) {
            $reviewerId = $reviewer['id'];
            $score = ($weekLoad[$reviewerId] ?? 0) + (($globalLoad[$reviewerId] ?? 0) * 0.15);

            if ($score < $bestScore - 1e-9) {
                $bestScore = $score;
                $bestCandidates = [$reviewer];
                continue;
            }

            if (abs($score - $bestScore) <= 1e-9) {
                $bestCandidates[] = $reviewer;
            }
        }

        return $bestCandidates[array_rand($bestCandidates)];
    }

    private function normalizeName(?string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
    }
}
