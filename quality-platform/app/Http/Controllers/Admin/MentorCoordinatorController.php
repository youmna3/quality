<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MentorCoordinatorAssignment;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MentorCoordinatorController extends Controller
{
    public function index(): Response
    {
        $mentorCounts = Tutor::query()
            ->selectRaw('TRIM(mentor_name) as mentor_name, COUNT(*) as tutors_count')
            ->whereNotNull('mentor_name')
            ->whereRaw("TRIM(mentor_name) <> ''")
            ->groupByRaw('TRIM(mentor_name)')
            ->orderBy('mentor_name')
            ->get();

        $assignments = MentorCoordinatorAssignment::query()
            ->get(['mentor_name', 'coordinator_user_id']);

        $coordinatorIds = $assignments
            ->pluck('coordinator_user_id')
            ->filter()
            ->unique()
            ->values();

        $coordinatorUsers = User::query()
            ->whereIn('id', $coordinatorIds)
            ->get(['id', 'name', 'email', 'is_active'])
            ->keyBy('id');

        $assignmentByMentorKey = $assignments->keyBy(
            fn (MentorCoordinatorAssignment $assignment) => $this->mentorKey($assignment->mentor_name)
        );

        $mentorRows = [];
        foreach ($mentorCounts as $mentorCount) {
            $mentorName = $this->normalizeMentorName((string) $mentorCount->mentor_name);
            $assignment = $assignmentByMentorKey->get($this->mentorKey($mentorName));
            $coordinator = $assignment?->coordinator_user_id
                ? $coordinatorUsers->get($assignment->coordinator_user_id)
                : null;

            $mentorRows[] = [
                'mentor_name' => $mentorName,
                'tutors_count' => (int) $mentorCount->tutors_count,
                'coordinator_user_id' => $assignment?->coordinator_user_id,
                'coordinator_name' => $coordinator?->name,
                'coordinator_email' => $coordinator?->email,
                'coordinator_is_active' => $coordinator?->is_active,
                'has_tutors' => true,
            ];

            if ($assignment) {
                $assignmentByMentorKey->forget($this->mentorKey($mentorName));
            }
        }

        foreach ($assignmentByMentorKey as $orphanAssignment) {
            $coordinator = $orphanAssignment->coordinator_user_id
                ? $coordinatorUsers->get($orphanAssignment->coordinator_user_id)
                : null;

            $mentorRows[] = [
                'mentor_name' => $orphanAssignment->mentor_name,
                'tutors_count' => 0,
                'coordinator_user_id' => $orphanAssignment->coordinator_user_id,
                'coordinator_name' => $coordinator?->name,
                'coordinator_email' => $coordinator?->email,
                'coordinator_is_active' => $coordinator?->is_active,
                'has_tutors' => false,
            ];
        }

        usort($mentorRows, fn (array $a, array $b) => strcasecmp($a['mentor_name'], $b['mentor_name']));

        $assignedMentorCountByReviewer = MentorCoordinatorAssignment::query()
            ->selectRaw('coordinator_user_id, COUNT(*) as mentors_count')
            ->whereNotNull('coordinator_user_id')
            ->groupBy('coordinator_user_id')
            ->pluck('mentors_count', 'coordinator_user_id');

        $reviewers = User::query()
            ->where('role', 'reviewer')
            ->where('reviewer_type', 'coordinator')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active', 'reviewer_type'])
            ->map(function (User $reviewer) use ($assignedMentorCountByReviewer) {
                $mentorsCount = (int) ($assignedMentorCountByReviewer[$reviewer->id] ?? 0);

                return [
                    'id' => $reviewer->id,
                    'name' => $reviewer->name,
                    'email' => $reviewer->email,
                    'is_active' => $reviewer->is_active,
                    'reviewer_type' => $reviewer->reviewer_type,
                    'mentors_count' => $mentorsCount,
                    'is_coordinator' => $mentorsCount > 0,
                ];
            })
            ->values();

        $totalMentors = count($mentorRows);
        $mappedMentors = collect($mentorRows)
            ->filter(fn (array $row) => ! empty($row['coordinator_user_id']))
            ->count();

        return Inertia::render('Admin/MentorCoordinators/Index', [
            'mentors' => $mentorRows,
            'reviewers' => $reviewers,
            'stats' => [
                'total_mentors' => $totalMentors,
                'mapped_mentors' => $mappedMentors,
                'unmapped_mentors' => $totalMentors - $mappedMentors,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.mentor_name' => ['required', 'string', 'max:255'],
            'assignments.*.coordinator_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('role', 'reviewer')
                        ->where('reviewer_type', 'coordinator')
                ),
            ],
        ]);

        $desiredAssignments = [];
        foreach ($validated['assignments'] as $assignment) {
            $mentorName = $this->normalizeMentorName($assignment['mentor_name']);

            if ($mentorName === '') {
                continue;
            }

            $mentorKey = $this->mentorKey($mentorName);
            $desiredAssignments[$mentorKey] = [
                'mentor_name' => $mentorName,
                'coordinator_user_id' => $assignment['coordinator_user_id'] ?: null,
            ];
        }

        DB::transaction(function () use ($desiredAssignments) {
            foreach ($desiredAssignments as $assignment) {
                $mentorKey = strtolower($assignment['mentor_name']);
                $existing = MentorCoordinatorAssignment::query()
                    ->whereRaw('LOWER(TRIM(mentor_name)) = ?', [$mentorKey])
                    ->first();

                if ($assignment['coordinator_user_id'] === null) {
                    $existing?->delete();
                    continue;
                }

                if ($existing) {
                    $existing->update([
                        'mentor_name' => $assignment['mentor_name'],
                        'coordinator_user_id' => $assignment['coordinator_user_id'],
                    ]);
                    continue;
                }

                MentorCoordinatorAssignment::query()->create([
                    'mentor_name' => $assignment['mentor_name'],
                    'coordinator_user_id' => $assignment['coordinator_user_id'],
                ]);
            }
        });

        return back()->with('success', 'Mentor coordinator assignments updated.');
    }

    private function normalizeMentorName(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

    private function mentorKey(string $value): string
    {
        return strtolower($this->normalizeMentorName($value));
    }
}
