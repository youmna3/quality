<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MentorTeamLeadAssignment;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamLeadController extends Controller
{
    public function index(): Response
    {
        $teamLeads = User::query()
            ->where('role', 'team_lead')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active']);

        $mentorCounts = Tutor::query()
            ->selectRaw('TRIM(mentor_name) as mentor_name, COUNT(*) as tutors_count')
            ->whereNotNull('mentor_name')
            ->whereRaw("TRIM(mentor_name) <> ''")
            ->groupByRaw('TRIM(mentor_name)')
            ->orderBy('mentor_name')
            ->get();

        $assignments = MentorTeamLeadAssignment::query()
            ->get(['mentor_name', 'team_lead_user_id']);

        $assignmentByMentor = $assignments->keyBy(
            fn (MentorTeamLeadAssignment $assignment) => $this->mentorKey($assignment->mentor_name)
        );

        $mentorRows = [];
        foreach ($mentorCounts as $mentorCount) {
            $mentorName = $this->normalizeMentorName((string) $mentorCount->mentor_name);
            $assignment = $assignmentByMentor->get($this->mentorKey($mentorName));

            $mentorRows[] = [
                'mentor_name' => $mentorName,
                'tutors_count' => (int) $mentorCount->tutors_count,
                'team_lead_user_id' => $assignment?->team_lead_user_id,
            ];
        }

        $mappedMentors = collect($mentorRows)
            ->filter(fn (array $row) => ! empty($row['team_lead_user_id']))
            ->count();

        return Inertia::render('Admin/TeamLeads/Index', [
            'teamLeads' => $teamLeads->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
            ])->values(),
            'mentors' => $mentorRows,
            'stats' => [
                'total_team_leads' => $teamLeads->count(),
                'total_mentors' => count($mentorRows),
                'mapped_mentors' => $mappedMentors,
                'unmapped_mentors' => count($mentorRows) - $mappedMentors,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        User::query()->create([
            'name' => $payload['name'],
            'email' => strtolower(trim($payload['email'])),
            'password' => $payload['password'],
            'role' => 'team_lead',
            'reviewer_type' => null,
            'project_id' => null,
            'is_active' => (bool) $payload['is_active'],
        ]);

        return back()->with('success', 'Team lead account created.');
    }

    public function update(Request $request, User $teamLead): RedirectResponse
    {
        abort_unless($teamLead->role === 'team_lead', 404);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teamLead->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $attributes = [
            'name' => $payload['name'],
            'email' => strtolower(trim($payload['email'])),
            'is_active' => (bool) $payload['is_active'],
        ];

        if (! empty($payload['password'])) {
            $attributes['password'] = $payload['password'];
        }

        $teamLead->update($attributes);

        return back()->with('success', 'Team lead account updated.');
    }

    public function updateMappings(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.mentor_name' => ['required', 'string', 'max:255'],
            'assignments.*.team_lead_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'team_lead')),
            ],
        ]);

        $desired = [];
        foreach ($payload['assignments'] as $assignment) {
            $mentorName = $this->normalizeMentorName($assignment['mentor_name']);
            if ($mentorName === '') {
                continue;
            }

            $desired[$this->mentorKey($mentorName)] = [
                'mentor_name' => $mentorName,
                'team_lead_user_id' => $assignment['team_lead_user_id'] ?: null,
            ];
        }

        DB::transaction(function () use ($desired) {
            foreach ($desired as $assignment) {
                $mentorKey = strtolower($assignment['mentor_name']);
                $existing = MentorTeamLeadAssignment::query()
                    ->whereRaw('LOWER(TRIM(mentor_name)) = ?', [$mentorKey])
                    ->first();

                if ($assignment['team_lead_user_id'] === null) {
                    $existing?->delete();
                    continue;
                }

                if ($existing) {
                    $existing->update([
                        'mentor_name' => $assignment['mentor_name'],
                        'team_lead_user_id' => $assignment['team_lead_user_id'],
                    ]);
                    continue;
                }

                MentorTeamLeadAssignment::query()->create([
                    'mentor_name' => $assignment['mentor_name'],
                    'team_lead_user_id' => $assignment['team_lead_user_id'],
                ]);
            }
        });

        return back()->with('success', 'Team lead mentor assignments updated.');
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

