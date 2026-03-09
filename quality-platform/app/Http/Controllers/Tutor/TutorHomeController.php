<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\OpsGroupMapping;
use App\Models\ReviewerAssignment;
use App\Models\WeekCycle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TutorHomeController extends Controller
{
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

        $tutor = $request->user()->tutor;

        abort_if(! $tutor, 403, 'Tutor profile is not linked to this account.');

        $assignment = ReviewerAssignment::query()
            ->where('tutor_id', $tutor->id)
            ->where('week_number', $selectedWeek)
            ->first();

        $recentIssues = Complaint::query()
            ->where('tutor_id', $tutor->id)
            ->latest('created_at')
            ->limit(8)
            ->get(['id', 'issue_type', 'session_date', 'slot', 'group_code', 'complaint_text', 'status', 'created_at'])
            ->map(fn (Complaint $issue) => [
                'id' => $issue->id,
                'issue_type' => $issue->issue_type,
                'session_date' => $issue->session_date?->toDateString(),
                'slot' => $issue->slot,
                'group_code' => $issue->group_code,
                'issue_text' => $issue->complaint_text,
                'status' => $issue->status,
                'created_at' => $issue->created_at?->toDateTimeString(),
            ])
            ->values();

        $groupMappings = OpsGroupMapping::query()
            ->where('tutor_code', $tutor->tutor_code)
            ->orderByDesc('session_date')
            ->orderBy('slot')
            ->get(['session_date', 'slot', 'group_code'])
            ->map(fn (OpsGroupMapping $mapping) => [
                'session_date' => $mapping->session_date?->toDateString(),
                'slot' => $mapping->slot,
                'group_code' => $mapping->group_code,
            ])
            ->values();

        return Inertia::render('Tutor/Home', [
            'week' => $selectedWeek,
            'weeks' => $weeks,
            'slotOptions' => self::SLOT_OPTIONS,
            'profile' => [
                'tutor_id' => $tutor->tutor_code,
                'name' => $tutor->name_en,
                'mentor_name' => $tutor->mentor_name,
                'grade' => $tutor->grade,
            ],
            'recentIssues' => $recentIssues,
            'groupMappings' => $groupMappings,
            'assignment' => $assignment
                ? [
                    'is_assigned' => true,
                ]
                : null,
        ]);
    }
}
