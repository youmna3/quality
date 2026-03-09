<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Flag;
use App\Models\OpsGroupMapping;
use App\Models\OpsIssueEntry;
use App\Models\Review;
use App\Models\ReviewEditRequest;
use App\Models\ReviewerAssignment;
use App\Models\WeekCycle;
use App\Support\ReviewEditAudit;
use App\Support\ReviewCriteria;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
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

    public function create(Request $request, ReviewerAssignment $assignment): Response
    {
        $assignment = $this->resolveAssignmentForReviewer($assignment, $request->user()->id);
        $assignment->loadMissing('tutor:id,tutor_code,name_en,project_id');
        $weekCycle = WeekCycle::query()->where('week_number', $assignment->week_number)->first();

        abort_if(! $assignment->tutor, 404, 'Tutor not found for this assignment.');
        abort_if(! $assignment->tutor->project_id, 422, 'Tutor project is missing. Please update tutor project type first.');

        $existingReview = Review::query()
            ->with('flags')
            ->where('reviewer_assignment_id', $assignment->id)
            ->first();
        $reviewerEditCount = $existingReview
            ? $existingReview->editLogs()->where('actor_role', 'reviewer')->count()
            : 0;
        $editLimitReached = $reviewerEditCount >= self::MAX_REVIEWER_EDITS;
        $latestEditRequest = $existingReview?->editRequests()
            ->latest('id')
            ->first();
        $reviewLocked = $this->isCycleClosed($weekCycle) || $editLimitReached;

        $complaintCandidates = Complaint::query()
            ->where('tutor_id', $assignment->tutor_id)
            ->latest('id')
            ->limit(50)
            ->get(['session_date', 'slot', 'issue_type', 'complaint_text'])
            ->map(fn (Complaint $complaint) => [
                'source' => $complaint->issue_type === 'student' ? 'student_issue_form' : 'session_issue_form',
                'session_date' => $complaint->session_date?->toDateString(),
                'slot' => $complaint->slot,
                'issue_text' => $complaint->complaint_text,
            ]);

        $opsIssueCandidates = OpsIssueEntry::query()
            ->where('tutor_code', $assignment->tutor->tutor_code)
            ->latest('id')
            ->limit(100)
            ->get(['session_date', 'slot', 'issue_text'])
            ->map(fn (OpsIssueEntry $issue) => [
                'source' => 'ops_upload',
                'session_date' => $issue->session_date?->toDateString(),
                'slot' => $issue->slot,
                'issue_text' => $issue->issue_text,
            ]);

        $groupMappings = OpsGroupMapping::query()
            ->where('tutor_code', $assignment->tutor->tutor_code)
            ->orderByDesc('session_date')
            ->orderBy('slot')
            ->get(['session_date', 'slot', 'group_code'])
            ->map(fn (OpsGroupMapping $mapping) => [
                'session_date' => $mapping->session_date?->toDateString(),
                'slot' => $mapping->slot,
                'group_code' => $mapping->group_code,
            ])
            ->values();

        $previousFlags = Flag::query()
            ->with('review:id,session_date,slot')
            ->where('tutor_id', $assignment->tutor_id)
            ->when(
                $existingReview?->id,
                fn ($query) => $query->where('review_id', '<>', $existingReview->id)
            )
            ->latest('created_at')
            ->limit(40)
            ->get(['id', 'review_id', 'color', 'subcategory', 'reason', 'status', 'created_at'])
            ->map(fn (Flag $flag) => [
                'id' => $flag->id,
                'color' => $flag->color,
                'subcategory' => $flag->subcategory,
                'reason' => $flag->reason,
                'status' => $flag->status,
                'created_at' => $flag->created_at?->toDateTimeString(),
                'session_date' => $flag->review?->session_date?->toDateString(),
                'slot' => $flag->review?->slot,
            ])
            ->values();

        $negativeHistory = Review::query()
            ->with('reviewerAssignment:id,week_number')
            ->where('tutor_id', $assignment->tutor_id)
            ->when(
                $existingReview?->id,
                fn ($query) => $query->where('id', '<>', $existingReview->id)
            )
            ->latest('session_date')
            ->latest('id')
            ->limit(30)
            ->get(['id', 'reviewer_assignment_id', 'session_date', 'slot', 'negative_comments_json', 'submitted_at'])
            ->map(function (Review $review) {
                return [
                    'id' => $review->id,
                    'week_number' => $review->reviewerAssignment?->week_number,
                    'session_date' => $review->session_date?->toDateString(),
                    'slot' => $review->slot,
                    'submitted_at' => $review->submitted_at?->toDateTimeString(),
                    'comments' => $this->flattenCommentList(
                        $this->normalizeCommentPayload($review->negative_comments_json ?? [])
                    ),
                ];
            })
            ->filter(fn (array $row) => $row['comments'] !== [])
            ->values();

        $tutorHistory = Review::query()
            ->with([
                'reviewerAssignment:id,week_number',
                'flags:id,review_id,color,status,subcategory,reason,duration_text,created_at',
            ])
            ->where('tutor_id', $assignment->tutor_id)
            ->when(
                $existingReview?->id,
                fn ($query) => $query->where('id', '<>', $existingReview->id)
            )
            ->latest('session_date')
            ->latest('id')
            ->limit(30)
            ->get([
                'id',
                'reviewer_assignment_id',
                'reviewer_name',
                'session_date',
                'slot',
                'group_code',
                'positive_comments_json',
                'negative_comments_json',
                'score_breakdown_json',
                'total_score',
                'submitted_at',
            ])
            ->map(function (Review $review) {
                return [
                    'id' => $review->id,
                    'week_number' => $review->reviewerAssignment?->week_number,
                    'reviewer_name' => $review->reviewer_name,
                    'session_date' => $review->session_date?->toDateString(),
                    'slot' => $review->slot,
                    'group_code' => $review->group_code,
                    'submitted_at' => $review->submitted_at?->toDateTimeString(),
                    'score' => $this->resolveReviewOverallScore($review),
                    'positive_comments' => $this->flattenCommentList(
                        $this->normalizeCommentPayload($review->positive_comments_json ?? [])
                    ),
                    'negative_comments' => $this->flattenCommentList(
                        $this->normalizeCommentPayload($review->negative_comments_json ?? [])
                    ),
                    'flags' => $review->flags
                        ->map(fn (Flag $flag) => [
                            'color' => $flag->color,
                            'subcategory' => $flag->subcategory,
                            'reason' => $flag->reason,
                            'duration_text' => $flag->duration_text,
                            'status' => $flag->status,
                            'created_at' => $flag->created_at?->toDateTimeString(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $issueCandidates = $complaintCandidates
            ->concat($opsIssueCandidates)
            ->values();
        $firstFlag = $existingReview?->flags?->first();
        $flagDefaults = $existingReview?->flags
            ? $existingReview->flags->map(fn (Flag $flag) => [
                'type' => $flag->color,
                'subcategory' => $flag->subcategory,
                'reason' => $flag->reason,
                'duration_text' => $flag->duration_text,
            ])->values()->all()
            : [];
        if ($flagDefaults === []) {
            $flagDefaults = [[
                'type' => 'none',
                'subcategory' => '',
                'reason' => '',
                'duration_text' => '',
            ]];
        }

        return Inertia::render('Reviewer/Reviews/Create', [
            'assignment' => [
                'id' => $assignment->id,
                'week_number' => $assignment->week_number,
                'tutor_id' => $assignment->tutor?->tutor_code,
                'tutor_name' => $assignment->tutor?->name_en,
                'reviewer_name' => $request->user()->name,
            ],
            'slotOptions' => self::SLOT_OPTIONS,
            'criteriaGroups' => ReviewCriteria::groups(),
            'flagSubcategories' => ReviewCriteria::flagSubcategories(),
            'groupMappings' => $groupMappings,
            'issueCandidates' => $issueCandidates,
            'previousFlags' => $previousFlags,
            'tutorHistory' => $tutorHistory,
            'negativeHistory' => $negativeHistory,
            'repeatPolicy' => [
                'min_days_for_red' => 3,
            ],
            'cycle' => [
                'starts_at' => $weekCycle?->starts_at?->toDateString(),
                'deadline_at' => $weekCycle?->deadline_at?->toDateString(),
            ],
            'editState' => [
                'locked' => $reviewLocked,
                'cycle_closed' => $this->isCycleClosed($weekCycle),
                'reviewer_edit_count' => $reviewerEditCount,
                'remaining_reviewer_edits' => max(self::MAX_REVIEWER_EDITS - $reviewerEditCount, 0),
                'max_reviewer_edits' => self::MAX_REVIEWER_EDITS,
                'edit_limit_reached' => $editLimitReached,
                'can_request_admin_edit' => $reviewLocked && $existingReview !== null,
                'latest_request' => $latestEditRequest
                    ? [
                        'status' => $latestEditRequest->status,
                        'message' => $latestEditRequest->message,
                        'reviewed_at' => $latestEditRequest->reviewed_at?->toDateTimeString(),
                        'admin_note' => $latestEditRequest->admin_note,
                        'created_at' => $latestEditRequest->created_at?->toDateTimeString(),
                    ]
                    : null,
            ],
            'formDefaults' => [
                'tutor_role' => $existingReview?->tutor_role ?? 'main',
                'session_date' => $existingReview?->session_date?->toDateString(),
                'slot' => $existingReview?->slot,
                'group_code' => $existingReview?->group_code,
                'recorded_link' => $existingReview?->recorded_link,
                'issue_text' => $existingReview?->issue_text ?? '',
                'flag_type' => $firstFlag?->color ?? 'none',
                'flag_subcategory' => $firstFlag?->subcategory,
                'flag_reason' => $firstFlag?->reason,
                'flag_duration_text' => $firstFlag?->duration_text,
                'flags' => $flagDefaults,
                'positive_comments' => $this->normalizeCommentPayload($existingReview?->positive_comments_json ?? []),
                'negative_comments' => $this->normalizeCommentPayload($existingReview?->negative_comments_json ?? []),
            ],
        ]);
    }

    public function store(Request $request, ReviewerAssignment $assignment): RedirectResponse
    {
        $assignment = $this->resolveAssignmentForReviewer($assignment, $request->user()->id);
        $assignment->loadMissing('tutor:id,tutor_code,name_en,project_id');
        abort_if(! $assignment->tutor, 404, 'Tutor not found for this assignment.');
        abort_if(! $assignment->tutor->project_id, 422, 'Tutor project is missing. Please update tutor project type first.');

        $weekCycle = WeekCycle::query()->where('week_number', $assignment->week_number)->first();

        $validated = $request->validate($this->reviewValidationRules());
        $this->validateSessionSchedule($assignment, $validated);

        $existingReview = Review::query()
            ->where('reviewer_assignment_id', $assignment->id)
            ->first();
        $reviewerEditCount = $existingReview
            ? $existingReview->editLogs()->where('actor_role', 'reviewer')->count()
            : 0;

        if ($this->isCycleClosed($weekCycle)) {
            $message = $existingReview
                ? 'Reviewer edits are closed because the cycle deadline has passed. Request an admin edit from your dashboard.'
                : 'Reviewer submissions are closed because the cycle deadline has passed.';

            throw ValidationException::withMessages([
                'session_date' => $message,
            ]);
        }
        if ($existingReview && $reviewerEditCount >= self::MAX_REVIEWER_EDITS) {
            throw ValidationException::withMessages([
                'session_date' => sprintf(
                    'Reviewer edit limit reached (%d edits). Request an admin edit from your dashboard.',
                    self::MAX_REVIEWER_EDITS
                ),
            ]);
        }
        $beforeSnapshot = $existingReview ? ReviewEditAudit::snapshot($existingReview) : null;

        $positiveComments = $this->normalizeCommentPayload($validated['positive_comments'] ?? []);
        $negativeComments = $this->normalizeCommentPayload($validated['negative_comments'] ?? []);
        $flagEntries = $this->normalizeFlagPayload($request, $validated);
        $autoEscalatedCount = 0;
        $redPenaltyCriterionKeys = [];

        foreach ($flagEntries as $index => $flagEntry) {
            if ($flagEntry['type'] === 'none') {
                continue;
            }

            if (
                $flagEntry['type'] === 'yellow'
                && $this->shouldAutoEscalateFlagToRed(
                    (int) $assignment->tutor_id,
                    $flagEntry['subcategory'],
                    $validated['session_date'],
                    $existingReview?->id
                )
            ) {
                $flagEntry['type'] = 'red';
                $flagEntries[$index] = $flagEntry;
                $autoEscalatedCount++;
            }

            if ($flagEntry['subcategory'] === '' || $flagEntry['reason'] === '') {
                throw ValidationException::withMessages([
                    'flags' => 'Each non-none flag requires subcategory and reason.',
                ]);
            }

            [$negativeComments, $flagCriterionKey] = $this->attachFlagCommentIfNeeded(
                $negativeComments,
                $flagEntry['subcategory'],
                $flagEntry['type']
            );

            if (in_array($flagEntry['type'], ['red', 'both'], true) && $flagCriterionKey) {
                $redPenaltyCriterionKeys[] = $flagCriterionKey;
            }
        }

        $this->assertPositiveCoverage($positiveComments);

        [$criterionScores, $groupPercentages, $totalScore] = $this->calculateScoreForTutor(
            (int) $assignment->tutor_id,
            $negativeComments,
            $existingReview?->id,
            $redPenaltyCriterionKeys
        );

        $review = Review::query()->updateOrCreate(
            ['reviewer_assignment_id' => $assignment->id],
            [
                'tutor_id' => $assignment->tutor_id,
                'reviewer_name' => $request->user()->name,
                'project_id' => $assignment->tutor->project_id,
                'session_id' => null,
                'tutor_role' => $validated['tutor_role'],
                'session_date' => $validated['session_date'],
                'slot' => $validated['slot'],
                'group_code' => trim($validated['group_code']),
                'recorded_link' => trim($validated['recorded_link']),
                'issue_text' => trim((string) ($validated['issue_text'] ?? '')),
                'positive_note' => $this->flattenComments($positiveComments),
                'negative_note' => $this->flattenComments($negativeComments),
                'positive_comments_json' => $positiveComments,
                'negative_comments_json' => $negativeComments,
                'score_breakdown_json' => [
                    'criteria_scores' => $criterionScores,
                    'group_percentages' => $groupPercentages,
                    'overall_score' => $totalScore,
                ],
                'total_score' => $totalScore,
                'submitted_at' => now(),
            ]
        );

        $existingScreenshots = $review->flags()->pluck('screenshot_path')->values();

        $review->flags()->delete();

        $storedFlagIndex = 0;
        foreach ($flagEntries as $flagEntry) {
            if ($flagEntry['type'] === 'none') {
                continue;
            }

            $uploadedScreenshotPath = $flagEntry['screenshot_file']
                ? $flagEntry['screenshot_file']->store('flags', 'public')
                : null;

            Flag::query()->create([
                'review_id' => $review->id,
                'tutor_id' => $review->tutor_id,
                'color' => $flagEntry['type'],
                'initial_color' => $flagEntry['type'],
                'subcategory' => $flagEntry['subcategory'] !== '' ? $flagEntry['subcategory'] : 'General',
                'reason' => $flagEntry['reason'],
                'duration_text' => $flagEntry['duration_text'],
                'screenshot_path' => $uploadedScreenshotPath ?: ($existingScreenshots[$storedFlagIndex] ?? null),
                'status' => 'open',
            ]);
            $storedFlagIndex++;
        }

        $review->load('flags');
        if ($existingReview && $beforeSnapshot) {
            ReviewEditAudit::log(
                $review,
                $request->user(),
                $beforeSnapshot,
                ReviewEditAudit::snapshot($review)
            );
        }

        $successMessage = 'Review submitted successfully.';
        if ($autoEscalatedCount > 0) {
            $successMessage = sprintf(
                'Review submitted. %d yellow flag(s) auto-escalated to red based on previous reviewed issues.',
                $autoEscalatedCount
            );
        }

        return redirect()
            ->route('reviewer.home', ['week' => $assignment->week_number])
            ->with('success', $successMessage);
    }

    public function requestEdit(Request $request, ReviewerAssignment $assignment): RedirectResponse
    {
        $assignment = $this->resolveAssignmentForReviewer($assignment, $request->user()->id);
        $review = Review::query()
            ->where('reviewer_assignment_id', $assignment->id)
            ->first();

        if (! $review) {
            return back()->with('error', 'No submitted review exists for this assignment yet.');
        }

        $weekCycle = WeekCycle::query()->where('week_number', $assignment->week_number)->first();
        $reviewerEditCount = $review->editLogs()->where('actor_role', 'reviewer')->count();
        $canRequestBecauseLocked = $this->isCycleClosed($weekCycle) || $reviewerEditCount >= self::MAX_REVIEWER_EDITS;
        if (! $canRequestBecauseLocked) {
            return back()->with('error', 'The review is still editable directly. Admin edit requests only open after cycle close or after the reviewer edit limit is reached.');
        }

        $payload = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $pendingRequest = ReviewEditRequest::query()
            ->where('review_id', $review->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($pendingRequest) {
            $pendingRequest->update([
                'message' => trim($payload['message']),
                'requester_id' => $request->user()->id,
                'requester_name' => $request->user()->name,
                'requester_role' => $request->user()->role,
            ]);
        } else {
            ReviewEditRequest::query()->create([
                'review_id' => $review->id,
                'requester_id' => $request->user()->id,
                'requester_name' => $request->user()->name,
                'requester_role' => $request->user()->role,
                'message' => trim($payload['message']),
                'status' => 'pending',
            ]);
        }

        return back()->with('success', 'Admin edit request submitted successfully.');
    }

    public function groupHistory(Request $request, ReviewerAssignment $assignment): JsonResponse
    {
        $assignment = $this->resolveAssignmentForReviewer($assignment, $request->user()->id);

        $groupCode = trim((string) $request->query('group_code', ''));
        if ($groupCode === '') {
            return response()->json(['data' => []]);
        }

        $normalizedGroupCode = $this->normalizeText($groupCode);
        $normalizedReviewerName = $this->normalizeText((string) $request->user()->name);

        $history = Review::query()
            ->with([
                'reviewerAssignment:id,week_number',
                'tutor:id,tutor_code,name_en',
            ])
            ->whereRaw('LOWER(TRIM(group_code)) = ?', [$normalizedGroupCode])
            ->whereRaw("LOWER(TRIM(COALESCE(reviewer_name, ''))) <> ?", [$normalizedReviewerName])
            ->latest('session_date')
            ->latest('id')
            ->limit(10)
            ->get([
                'id',
                'reviewer_assignment_id',
                'tutor_id',
                'reviewer_name',
                'session_date',
                'slot',
                'submitted_at',
            ])
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'week_number' => $review->reviewerAssignment?->week_number,
                'reviewer_name' => $review->reviewer_name,
                'tutor_code' => $review->tutor?->tutor_code,
                'tutor_name' => $review->tutor?->name_en,
                'session_date' => $review->session_date?->toDateString(),
                'slot' => $review->slot,
                'submitted_at' => $review->submitted_at?->toDateTimeString(),
            ])
            ->values();

        return response()->json(['data' => $history]);
    }

    public function importOpsGroups(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'assignment_id' => ['required', 'integer', Rule::exists('reviewer_assignments', 'id')],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $assignment = $this->resolveAssignmentForReviewer(
            ReviewerAssignment::query()->findOrFail((int) $payload['assignment_id']),
            $request->user()->id
        );

        [$headerMap, $rows] = $this->readCsvRows($request->file('file')->getRealPath());

        $tutorCodeIndex = $this->findColumnIndex($headerMap, ['tutorid', 'tutorcode', 'tutor']);
        $slotIndex = $this->findColumnIndex($headerMap, ['slot', 'sessionslot']);
        $groupIndex = $this->findColumnIndex($headerMap, ['groupid', 'groupcode', 'group']);
        $sessionDateIndex = $this->findColumnIndex($headerMap, ['sessiondate', 'date']);

        if ($tutorCodeIndex === null || $slotIndex === null || $groupIndex === null) {
            return redirect()
                ->route('reviewer.reviews.create', ['assignment' => $assignment->id])
                ->with('error', 'OPS group sheet must include Tutor ID, Slot, and Group ID columns.');
        }

        $imported = 0;
        foreach ($rows as $row) {
            $tutorCode = strtoupper(trim((string) ($row[$tutorCodeIndex] ?? '')));
            $slot = trim((string) ($row[$slotIndex] ?? ''));
            $groupCode = trim((string) ($row[$groupIndex] ?? ''));
            $sessionDate = $sessionDateIndex === null
                ? null
                : $this->parseDateValue($row[$sessionDateIndex] ?? null);

            if ($tutorCode === '' || $slot === '' || $groupCode === '') {
                continue;
            }

            OpsGroupMapping::query()->updateOrCreate(
                [
                    'tutor_code' => $tutorCode,
                    'slot' => $slot,
                    'session_date' => $sessionDate,
                ],
                ['group_code' => $groupCode]
            );
            $imported++;
        }

        return redirect()
            ->route('reviewer.reviews.create', ['assignment' => $assignment->id])
            ->with('success', sprintf('OPS group mappings imported: %d row(s).', $imported));
    }

    public function importOpsIssues(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'assignment_id' => ['required', 'integer', Rule::exists('reviewer_assignments', 'id')],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $assignment = $this->resolveAssignmentForReviewer(
            ReviewerAssignment::query()->findOrFail((int) $payload['assignment_id']),
            $request->user()->id
        );

        [$headerMap, $rows] = $this->readCsvRows($request->file('file')->getRealPath());

        $tutorCodeIndex = $this->findColumnIndex($headerMap, ['tutorid', 'tutorcode', 'tutor']);
        $issueIndex = $this->findColumnIndex($headerMap, ['issue', 'issuetext', 'problem', 'complainttext']);
        $slotIndex = $this->findColumnIndex($headerMap, ['slot', 'sessionslot']);
        $sessionDateIndex = $this->findColumnIndex($headerMap, ['sessiondate', 'date']);

        if ($tutorCodeIndex === null || $issueIndex === null) {
            return redirect()
                ->route('reviewer.reviews.create', ['assignment' => $assignment->id])
                ->with('error', 'Issue sheet must include Tutor ID and Issue columns.');
        }

        $imported = 0;
        foreach ($rows as $row) {
            $tutorCode = strtoupper(trim((string) ($row[$tutorCodeIndex] ?? '')));
            $issueText = trim((string) ($row[$issueIndex] ?? ''));
            $slot = $slotIndex === null ? null : trim((string) ($row[$slotIndex] ?? ''));
            $sessionDate = $sessionDateIndex === null
                ? null
                : $this->parseDateValue($row[$sessionDateIndex] ?? null);

            if ($tutorCode === '' || $issueText === '') {
                continue;
            }

            OpsIssueEntry::query()->create([
                'tutor_code' => $tutorCode,
                'session_date' => $sessionDate,
                'slot' => $slot ?: null,
                'issue_text' => $issueText,
            ]);
            $imported++;
        }

        return redirect()
            ->route('reviewer.reviews.create', ['assignment' => $assignment->id])
            ->with('success', sprintf('Issue entries imported: %d row(s).', $imported));
    }

    private function reviewValidationRules(): array
    {
        $rules = [
            'tutor_role' => ['required', Rule::in(['main', 'cover'])],
            'session_date' => ['required', 'date'],
            'slot' => ['required', Rule::in(self::SLOT_OPTIONS)],
            'group_code' => ['required', 'string', 'max:255'],
            'recorded_link' => ['required', 'url', 'max:1000'],
            'issue_text' => ['nullable', 'string'],
            'flags' => ['nullable', 'array', 'min:1'],
            'flags.*.type' => ['required_with:flags', Rule::in(['none', 'yellow', 'red', 'both'])],
            'flags.*.subcategory' => ['nullable', 'string', 'max:255', Rule::in(ReviewCriteria::flagSubcategories())],
            'flags.*.reason' => ['nullable', 'string'],
            'flags.*.duration_text' => ['nullable', 'string', 'max:255'],
            'flags.*.screenshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'flag_type' => ['nullable', Rule::in(['none', 'yellow', 'red', 'both'])],
            'flag_subcategory' => ['nullable', 'string', 'max:255', Rule::in(ReviewCriteria::flagSubcategories())],
            'flag_reason' => ['nullable', 'string'],
            'flag_duration_text' => ['nullable', 'string', 'max:255'],
            'flag_screenshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'positive_comments' => ['required', 'array'],
            'negative_comments' => ['nullable', 'array'],
        ];

        foreach (ReviewCriteria::criterionOrder() as $criterionKey) {
            $rules["positive_comments.$criterionKey"] = ['nullable', 'array'];
            $rules["positive_comments.$criterionKey.*"] = ['required', 'string', 'max:1000'];
            $rules["negative_comments.$criterionKey"] = ['nullable', 'array'];
            $rules["negative_comments.$criterionKey.*"] = ['required', 'string', 'max:1000'];
        }

        return $rules;
    }

    private function validateSessionSchedule(ReviewerAssignment $assignment, array $validated): void
    {
        $messages = [];

        try {
            $sessionDate = Carbon::parse((string) $validated['session_date'])->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'session_date' => 'Session date is invalid.',
            ]);
        }

        $weekCycle = WeekCycle::query()->where('week_number', $assignment->week_number)->first();
        if ($weekCycle?->starts_at && $sessionDate->lt($weekCycle->starts_at->copy()->startOfDay())) {
            $messages['session_date'] = sprintf(
                'Session date must be on or after the cycle start date (%s).',
                $weekCycle->starts_at->toDateString()
            );
        }

        if ($weekCycle?->deadline_at && $sessionDate->gt($weekCycle->deadline_at->copy()->startOfDay())) {
            $messages['session_date'] = sprintf(
                'Session date cannot be after the cycle deadline date (%s).',
                $weekCycle->deadline_at->toDateString()
            );
        }

        $expectedDayOfWeek = $this->expectedDayOfWeekForSlot((string) $validated['slot']);
        if ($expectedDayOfWeek !== null && $sessionDate->dayOfWeek !== $expectedDayOfWeek) {
            $messages['slot'] = 'Selected slot must match the session date. Fri slots require a Friday date and Sat slots require a Saturday date.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function assertPositiveCoverage(array $positiveComments): void
    {
        $groupCriteriaKeys = ReviewCriteria::groupCriteriaKeys();
        foreach ($groupCriteriaKeys as $groupLabel => $keys) {
            $count = 0;
            foreach ($keys as $key) {
                $count += count($positiveComments[$key] ?? []);
            }

            if ($count === 0) {
                throw ValidationException::withMessages([
                    'positive_comments' => $groupLabel.' requires at least one positive comment.',
                ]);
            }
        }
    }

    private function calculateScoreForTutor(
        int $tutorId,
        array $negativeComments,
        ?int $existingReviewId = null,
        array $redPenaltyCriterionKeys = []
    ): array {
        $history = [];

        Review::query()
            ->where('tutor_id', $tutorId)
            ->when($existingReviewId, fn ($query) => $query->where('id', '<>', $existingReviewId))
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get(['negative_comments_json'])
            ->each(function (Review $review) use (&$history) {
                $entries = $this->extractNegativeEntries($this->normalizeCommentPayload($review->negative_comments_json ?? []));
                foreach ($entries as $entry) {
                    $history[$entry['hash']] = ($history[$entry['hash']] ?? 0) + 1;
                }
            });

        $deductionByCriterion = array_fill_keys(ReviewCriteria::criterionOrder(), 0);
        foreach ($this->extractNegativeEntries($negativeComments) as $entry) {
            $timesSeen = ($history[$entry['hash']] ?? 0) + 1;
            $history[$entry['hash']] = $timesSeen;
            $deductionByCriterion[$entry['criterion_key']] += $timesSeen;
        }

        foreach ($redPenaltyCriterionKeys as $redPenaltyCriterionKey) {
            if (array_key_exists($redPenaltyCriterionKey, $deductionByCriterion)) {
                $deductionByCriterion[$redPenaltyCriterionKey] += 1;
            }
        }

        $criterionScores = [];
        $total = 0;
        foreach (ReviewCriteria::criterionOrder() as $criterionKey) {
            $score = max(0, ReviewCriteria::BASE_SCORE - (int) ($deductionByCriterion[$criterionKey] ?? 0));
            $criterionScores[$criterionKey] = $score;
            $total += $score;
        }

        $groupPercentages = [];
        foreach (ReviewCriteria::groupCriteriaKeys() as $groupLabel => $criteriaKeys) {
            $groupTotal = array_sum(array_map(fn ($key) => (int) ($criterionScores[$key] ?? 0), $criteriaKeys));
            $groupMax = count($criteriaKeys) * ReviewCriteria::BASE_SCORE;
            $groupPercentages[$groupLabel] = $groupMax > 0
                ? round(($groupTotal / $groupMax) * 100, 2)
                : 0.0;
        }

        return [$criterionScores, $groupPercentages, $total];
    }

    private function attachFlagCommentIfNeeded(
        array $negativeComments,
        string $flagSubcategory,
        string $flagType
    ): array {
        $criterionKey = ReviewCriteria::criterionKeyForSubcategory($flagSubcategory);
        if (! $criterionKey) {
            return [$negativeComments, null];
        }

        $existing = $negativeComments[$criterionKey] ?? [];
        if ($existing === []) {
            $negativeComments[$criterionKey] = [
                $this->buildAutoFlagLinkedComment($criterionKey, $flagType),
            ];
        }

        return [$this->normalizeCommentPayload($negativeComments), $criterionKey];
    }

    private function buildAutoFlagLinkedComment(string $criterionKey, string $flagType): string
    {
        $criterion = ReviewCriteria::criteriaMap()[$criterionKey] ?? null;
        $prefix = $criterion['prefix'] ?? 'Comment:';
        $flagLabel = in_array(strtolower($flagType), ['red', 'both'], true) ? 'Red Flag' : 'Yellow Flag';

        return sprintf('%s %s - Auto linked comment for score tracking.', $prefix, $flagLabel);
    }

    private function extractNegativeEntries(array $negativeComments): array
    {
        $entries = [];
        $criteriaMap = ReviewCriteria::criteriaMap();

        foreach ($negativeComments as $criterionKey => $comments) {
            if (! isset($criteriaMap[$criterionKey])) {
                continue;
            }

            foreach ($comments as $comment) {
                $normalizedComment = $this->normalizeText($comment);
                if ($normalizedComment === '') {
                    continue;
                }

                $entries[] = [
                    'criterion_key' => $criterionKey,
                    'hash' => $criterionKey.'|'.$normalizedComment,
                ];
            }
        }

        return $entries;
    }

    private function flattenComments(array $comments): ?string
    {
        $flat = collect($comments)
            ->flatten()
            ->filter(fn ($value) => $this->normalizeText((string) $value) !== '')
            ->values()
            ->all();

        if ($flat === []) {
            return null;
        }

        return implode("\n", $flat);
    }

    private function flattenCommentList(array $comments): array
    {
        return collect($comments)
            ->flatten()
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function resolveReviewOverallScore(Review $review): float|int
    {
        return $review->score_breakdown_json['overall_score']
            ?? $review->total_score
            ?? array_sum($review->score_breakdown_json['criteria_scores'] ?? []);
    }

    private function normalizeCommentPayload(array $payload): array
    {
        $normalized = ReviewCriteria::emptySelections();

        foreach ($payload as $key => $values) {
            if (! is_array($values)) {
                continue;
            }

            if (array_key_exists($key, $normalized)) {
                $normalized[$key] = collect($values)
                    ->filter(fn ($value) => $this->normalizeText((string) $value) !== '')
                    ->map(fn ($value) => trim((string) $value))
                    ->values()
                    ->all();
                continue;
            }

            // Backward compatibility for older payload shape.
            foreach ($values as $legacyComment) {
                $comment = trim((string) $legacyComment);
                if ($comment === '') {
                    continue;
                }
                $criterionKey = ReviewCriteria::inferCriterionKeyFromComment($comment);
                if ($criterionKey && array_key_exists($criterionKey, $normalized)) {
                    $normalized[$criterionKey][] = $comment;
                }
            }
        }

        foreach ($normalized as $key => $values) {
            $normalized[$key] = array_values(array_unique($values));
        }

        return $normalized;
    }

    private function normalizeFlagPayload(Request $request, array $validated): array
    {
        $flags = [];

        if (is_array($validated['flags'] ?? null)) {
            $rawFlags = array_values($validated['flags']);
            foreach ($rawFlags as $index => $flagInput) {
                if (! is_array($flagInput)) {
                    continue;
                }

                $type = strtolower(trim((string) ($flagInput['type'] ?? 'none')));
                if (! in_array($type, ['none', 'yellow', 'red', 'both'], true)) {
                    $type = 'none';
                }

                $flags[] = [
                    'type' => $type,
                    'subcategory' => trim((string) ($flagInput['subcategory'] ?? '')),
                    'reason' => trim((string) ($flagInput['reason'] ?? '')),
                    'duration_text' => trim((string) ($flagInput['duration_text'] ?? '')),
                    'screenshot_file' => $request->file("flags.$index.screenshot"),
                ];
            }
        }

        // Backward compatibility for older single-flag payload shape.
        if ($flags === []) {
            $type = strtolower(trim((string) ($validated['flag_type'] ?? 'none')));
            if (! in_array($type, ['none', 'yellow', 'red', 'both'], true)) {
                $type = 'none';
            }

            $flags[] = [
                'type' => $type,
                'subcategory' => trim((string) ($validated['flag_subcategory'] ?? '')),
                'reason' => trim((string) ($validated['flag_reason'] ?? '')),
                'duration_text' => trim((string) ($validated['flag_duration_text'] ?? '')),
                'screenshot_file' => $request->file('flag_screenshot'),
            ];
        }

        $hasActionableFlag = false;
        foreach ($flags as $flag) {
            if (($flag['type'] ?? 'none') !== 'none') {
                $hasActionableFlag = true;
                break;
            }
        }

        if (! $hasActionableFlag) {
            return [[
                'type' => 'none',
                'subcategory' => '',
                'reason' => '',
                'duration_text' => '',
                'screenshot_file' => null,
            ]];
        }

        return $flags;
    }

    private function resolveAssignmentForReviewer(ReviewerAssignment $assignment, int $userId): ReviewerAssignment
    {
        abort_unless((int) $assignment->reviewer_id === $userId, 403, 'This assignment is not assigned to you.');

        return $assignment;
    }

    private function readCsvRows(string $realPath): array
    {
        $handle = fopen($realPath, 'r');
        if ($handle === false) {
            return [[], []];
        }

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = (substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ','))
            ? ';'
            : ',';

        $headers = fgetcsv($handle, 0, $delimiter) ?: [];
        $headerMap = [];
        foreach ($headers as $index => $header) {
            $headerMap[$this->normalizeHeader((string) $header)] = $index;
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $data;
        }

        fclose($handle);

        return [$headerMap, $rows];
    }

    private function normalizeHeader(string $value): string
    {
        $clean = trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? '');

        return preg_replace('/[^a-z0-9]+/', '', strtolower($clean)) ?? '';
    }

    private function findColumnIndex(array $headerMap, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeHeader($alias);
            if (array_key_exists($key, $headerMap)) {
                return (int) $headerMap[$key];
            }
        }

        return null;
    }

    private function parseDateValue(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestampUTC(((int) $value - 25569) * 86400)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeText(string $value): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }

    private function expectedDayOfWeekForSlot(string $slot): ?int
    {
        $normalizedSlot = strtolower(trim($slot));

        return match (true) {
            str_starts_with($normalizedSlot, 'fri') => Carbon::FRIDAY,
            str_starts_with($normalizedSlot, 'sat') => Carbon::SATURDAY,
            default => null,
        };
    }

    private function shouldAutoEscalateFlagToRed(
        int $tutorId,
        string $subcategory,
        string $currentSessionDate,
        ?int $existingReviewId = null
    ): bool {
        $subcategory = trim($subcategory);
        if ($subcategory === '' || trim($currentSessionDate) === '') {
            return false;
        }

        try {
            $sessionDate = Carbon::parse($currentSessionDate)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        $cutoff = $sessionDate->copy()->subDays(3)->endOfDay();

        return Flag::query()
            ->where('tutor_id', $tutorId)
            ->where('subcategory', $subcategory)
            ->where('status', '!=', 'removed')
            ->where('created_at', '<=', $cutoff)
            ->when($existingReviewId, fn ($query) => $query->where('review_id', '<>', $existingReviewId))
            ->whereHas('review', fn ($query) => $query->whereDate('session_date', '<', $sessionDate->toDateString()))
            ->exists();
    }

    private function isCycleClosed(?WeekCycle $weekCycle): bool
    {
        if (! $weekCycle?->deadline_at) {
            return false;
        }

        return now()->gt($weekCycle->deadline_at);
    }
}
