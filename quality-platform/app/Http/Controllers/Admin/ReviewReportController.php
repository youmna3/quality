<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewEditRequest;
use App\Models\ReviewerAssignment;
use App\Models\WeekCycle;
use App\Support\ReviewCriteria;
use App\Support\ReviewEditAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewReportController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedWeek = max(1, (int) $request->query('week', 1));
        $filters = [
            'search' => trim((string) $request->query('search', '')),
        ];
        $searchAcrossWeeks = $filters['search'] !== '';
        $maxAssignmentWeek = (int) (ReviewerAssignment::query()->max('week_number') ?? 0);
        $maxCycleWeek = (int) (WeekCycle::query()->max('week_number') ?? 0);
        $maxWeek = max($selectedWeek, $maxAssignmentWeek + 1, $maxCycleWeek, 1);
        $weeks = range(1, $maxWeek);

        $criteriaMap = ReviewCriteria::criteriaMap();
        $criteriaOrder = ReviewCriteria::criterionOrder();
        $groupLabels = ReviewCriteria::groupLabelsInOrder();
        $selectedCycle = WeekCycle::query()->where('week_number', $selectedWeek)->first();

        $reports = Review::query()
            ->with([
                'tutor:id,tutor_code,name_en,mentor_name',
                'reviewerAssignment:id,week_number',
                'flags:id,review_id,color,status,subcategory,reason,duration_text,screenshot_path',
                'editRequests:id,review_id,status',
            ])
            ->when(
                ! $searchAcrossWeeks,
                fn ($query) => $query->whereHas('reviewerAssignment', fn ($innerQuery) => $innerQuery->where('week_number', $selectedWeek))
            )
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $normalizedSearch = strtolower(trim((string) $search));
                $compactSearch = str_replace([' ', '-'], '', $normalizedSearch);

                $query->where(function ($innerQuery) use ($search, $normalizedSearch, $compactSearch) {
                    $innerQuery
                        ->whereHas('tutor', function ($tutorQuery) use ($search, $normalizedSearch, $compactSearch) {
                            $tutorQuery
                                ->where('tutor_code', 'like', "%{$search}%")
                                ->orWhere('name_en', 'like', "%{$search}%")
                                ->orWhere('mentor_name', 'like', "%{$search}%")
                                ->orWhereRaw('LOWER(TRIM(tutor_code)) LIKE ?', ["%{$normalizedSearch}%"])
                                ->orWhereRaw('LOWER(TRIM(name_en)) LIKE ?', ["%{$normalizedSearch}%"])
                                ->orWhereRaw('LOWER(TRIM(mentor_name)) LIKE ?', ["%{$normalizedSearch}%"]);

                            if ($compactSearch !== '') {
                                $tutorQuery->orWhereRaw(
                                    "REPLACE(REPLACE(LOWER(TRIM(tutor_code)), '-', ''), ' ', '') LIKE ?",
                                    ["%{$compactSearch}%"]
                                );
                            }
                        })
                        ->orWhere('reviewer_name', 'like', "%{$search}%")
                        ->orWhereRaw('LOWER(TRIM(reviewer_name)) LIKE ?', ["%{$normalizedSearch}%"]);
                });
            })
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Review $review) use ($criteriaOrder, $criteriaMap, $groupLabels) {
                $scores = $this->extractCriterionScores($review, $criteriaOrder);
                $groupPercentages = $this->extractGroupPercentages($review, $groupLabels, $scores);
                $flags = $review->flags->where('status', '!=', 'removed')->values();
                $flagScreenshots = $flags
                    ->filter(fn ($flag) => ! empty($flag->screenshot_path))
                    ->map(fn ($flag) => asset('storage/'.$flag->screenshot_path))
                    ->values()
                    ->all();

                return [
                    'id' => $review->id,
                    'week_number' => $review->reviewerAssignment?->week_number,
                    'timestamp' => $review->submitted_at?->toDateTimeString(),
                    'tutor_id' => $review->tutor?->tutor_code,
                    'tutor_name' => $review->tutor?->name_en,
                    'mentor_name' => $review->tutor?->mentor_name,
                    'reviewer_name' => $review->reviewer_name,
                    'session_date' => $review->session_date?->toDateString(),
                    'slot' => $review->slot,
                    'group_code' => $review->group_code,
                    'flag_type' => $this->buildFlagTypeString($flags->all()),
                    'flag_details' => $this->buildFlagDetailsString($flags->all()),
                    'positive_concat' => $this->flattenComments($review->positive_comments_json ?? []),
                    'negative_concat' => $this->flattenComments($review->negative_comments_json ?? []),
                    'zoom_link' => $review->recorded_link,
                    'flag_screenshot_url' => $flagScreenshots[0] ?? null,
                    'flag_screenshot_urls' => $flagScreenshots,
                    'criteria_scores' => collect($criteriaOrder)->mapWithKeys(
                        fn ($criterionKey) => [$criteriaMap[$criterionKey]['label'] => (int) ($scores[$criterionKey] ?? ReviewCriteria::BASE_SCORE)]
                    ),
                    'group_percentages' => $groupPercentages,
                    'score' => $review->score_breakdown_json['overall_score']
                        ?? $review->total_score
                        ?? array_sum($scores),
                    'edit_url' => route('admin.reports.edit', $review),
                    'has_pending_edit_request' => $review->editRequests->where('status', 'pending')->isNotEmpty(),
                ];
            });

        $pendingEditRequests = ReviewEditRequest::query()
            ->with([
                'review:id,tutor_id,reviewer_assignment_id,reviewer_name',
                'review.tutor:id,tutor_code,name_en',
                'review.reviewerAssignment:id,week_number',
            ])
            ->where('status', 'pending')
            ->when(
                ! $searchAcrossWeeks,
                fn ($query) => $query->whereHas('review.reviewerAssignment', fn ($innerQuery) => $innerQuery->where('week_number', $selectedWeek))
            )
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];
                $query->whereHas('review.tutor', function ($tutorQuery) use ($search) {
                    $tutorQuery
                        ->where('tutor_code', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (ReviewEditRequest $editRequest) => [
                'id' => $editRequest->id,
                'week_number' => $editRequest->review?->reviewerAssignment?->week_number,
                'tutor_id' => $editRequest->review?->tutor?->tutor_code,
                'tutor_name' => $editRequest->review?->tutor?->name_en,
                'reviewer_name' => $editRequest->review?->reviewer_name,
                'requester_name' => $editRequest->requester_name,
                'message' => $editRequest->message,
                'created_at' => $editRequest->created_at?->toDateTimeString(),
                'edit_url' => $editRequest->review ? route('admin.reports.edit', $editRequest->review) : null,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Reports/Index', [
            'week' => $selectedWeek,
            'weeks' => $weeks,
            'filters' => $filters,
            'searchAcrossWeeks' => $searchAcrossWeeks,
            'criteriaHeaders' => array_map(fn ($key) => $criteriaMap[$key]['label'], $criteriaOrder),
            'groupHeaders' => $groupLabels,
            'reports' => $reports,
            'publishState' => [
                'is_published' => $selectedCycle?->reports_published_at !== null,
                'published_at' => $selectedCycle?->reports_published_at?->toDateTimeString(),
            ],
            'pendingEditRequests' => $pendingEditRequests,
        ]);
    }

    public function publishWeek(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'week' => ['required', 'integer', 'min:1'],
        ]);

        $weekCycle = WeekCycle::query()->firstOrCreate(
            ['week_number' => (int) $payload['week']],
            [
                'starts_at' => null,
                'deadline_at' => null,
            ]
        );

        $weekCycle->update([
            'reports_published_at' => now(),
        ]);

        return back()->with('success', sprintf('Week %d reports published to tutors.', (int) $payload['week']));
    }

    public function edit(Review $review): Response
    {
        $review->load([
            'tutor:id,tutor_code,name_en,mentor_name',
            'reviewerAssignment:id,week_number',
            'flags:id,review_id,color,status,subcategory,reason,duration_text,screenshot_path',
            'editRequests' => fn ($query) => $query->latest('id'),
        ]);

        $criteriaScores = $this->extractCriterionScores($review, ReviewCriteria::criterionOrder());
        $criteriaGroups = collect(ReviewCriteria::groups())
            ->map(function (array $group) use ($criteriaScores) {
                $group['criteria'] = collect($group['criteria'])
                    ->map(function (array $criterion) use ($criteriaScores) {
                        $criterion['current_score'] = (int) ($criteriaScores[$criterion['key']] ?? ReviewCriteria::BASE_SCORE);

                        return $criterion;
                    })
                    ->values()
                    ->all();

                return $group;
            })
            ->values()
            ->all();

        return Inertia::render('Admin/Reports/Edit', [
            'review' => [
                'id' => $review->id,
                'week_number' => $review->reviewerAssignment?->week_number,
                'tutor' => [
                    'tutor_code' => $review->tutor?->tutor_code,
                    'name_en' => $review->tutor?->name_en,
                    'mentor_name' => $review->tutor?->mentor_name,
                ],
                'reviewer_name' => $review->reviewer_name,
                'tutor_role' => $review->tutor_role,
                'session_date' => $review->session_date?->toDateString(),
                'slot' => $review->slot,
                'group_code' => $review->group_code,
                'recorded_link' => $review->recorded_link,
                'issue_text' => $review->issue_text,
                'positive_lines' => $this->flattenComments($review->positive_comments_json ?? []),
                'negative_lines' => $this->flattenComments($review->negative_comments_json ?? []),
                'criterion_scores' => $criteriaScores,
                'flags' => $review->flags
                    ->where('status', '!=', 'removed')
                    ->values()
                    ->map(fn ($flag) => [
                        'color' => $flag->color,
                        'subcategory' => $flag->subcategory,
                        'reason' => $flag->reason,
                        'duration_text' => $flag->duration_text,
                        'status' => $flag->status,
                    ])
                    ->all(),
            ],
            'criteriaGroups' => $criteriaGroups,
            'slotOptions' => [
                'Fri slot 1',
                'Fri slot 2',
                'Fri slot 3',
                'Fri slot 4',
                'Sat slot 1',
                'Sat slot 2',
                'Sat slot 3',
                'Sat slot 4',
            ],
            'pendingEditRequests' => $review->editRequests
                ->where('status', 'pending')
                ->values()
                ->map(fn (ReviewEditRequest $editRequest) => [
                    'id' => $editRequest->id,
                    'requester_name' => $editRequest->requester_name,
                    'message' => $editRequest->message,
                    'created_at' => $editRequest->created_at?->toDateTimeString(),
                ])
                ->all(),
            'flagsIndexUrl' => route('admin.flags.index', ['search' => $review->tutor?->tutor_code]),
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $rules = [
            'tutor_role' => ['required', Rule::in(['main', 'cover'])],
            'session_date' => ['required', 'date'],
            'slot' => ['required', 'string', 'max:255'],
            'group_code' => ['required', 'string', 'max:255'],
            'recorded_link' => ['required', 'url', 'max:1000'],
            'issue_text' => ['nullable', 'string'],
            'positive_lines' => ['nullable', 'string'],
            'negative_lines' => ['nullable', 'string'],
            'criterion_scores' => ['required', 'array'],
        ];

        foreach (ReviewCriteria::criterionOrder() as $criterionKey) {
            $rules["criterion_scores.$criterionKey"] = ['required', 'integer', 'min:0', 'max:'.ReviewCriteria::BASE_SCORE];
        }

        $payload = $request->validate($rules);

        $beforeSnapshot = ReviewEditAudit::snapshot($review);
        $positiveComments = $this->parseCommentTextarea(
            (string) ($payload['positive_lines'] ?? ''),
            is_array($review->positive_comments_json) ? $review->positive_comments_json : []
        );
        $negativeComments = $this->parseCommentTextarea(
            (string) ($payload['negative_lines'] ?? ''),
            is_array($review->negative_comments_json) ? $review->negative_comments_json : []
        );

        $criterionScores = [];
        foreach (ReviewCriteria::criterionOrder() as $criterionKey) {
            $criterionScores[$criterionKey] = (int) ($payload['criterion_scores'][$criterionKey] ?? ReviewCriteria::BASE_SCORE);
        }

        $groupPercentages = [];
        foreach (ReviewCriteria::groupCriteriaKeys() as $groupLabel => $criteriaKeys) {
            $groupTotal = array_sum(array_map(fn ($key) => (int) ($criterionScores[$key] ?? 0), $criteriaKeys));
            $groupMax = count($criteriaKeys) * ReviewCriteria::BASE_SCORE;
            $groupPercentages[$groupLabel] = $groupMax > 0
                ? round(($groupTotal / $groupMax) * 100, 2)
                : 0.0;
        }
        $overallScore = array_sum($criterionScores);

        $review->update([
            'tutor_role' => $payload['tutor_role'],
            'session_date' => $payload['session_date'],
            'slot' => trim((string) $payload['slot']),
            'group_code' => trim((string) $payload['group_code']),
            'recorded_link' => trim((string) $payload['recorded_link']),
            'issue_text' => trim((string) ($payload['issue_text'] ?? '')),
            'positive_note' => $this->implodeCommentsForStorage($positiveComments),
            'negative_note' => $this->implodeCommentsForStorage($negativeComments),
            'positive_comments_json' => $positiveComments,
            'negative_comments_json' => $negativeComments,
            'score_breakdown_json' => [
                'criteria_scores' => $criterionScores,
                'group_percentages' => $groupPercentages,
                'overall_score' => $overallScore,
            ],
            'total_score' => $overallScore,
        ]);

        $review->load('flags', 'reviewerAssignment');
        ReviewEditAudit::log(
            $review,
            $request->user(),
            $beforeSnapshot,
            ReviewEditAudit::snapshot($review)
        );

        $review->editRequests()
            ->where('status', 'pending')
            ->update([
                'status' => 'completed',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'admin_note' => 'Resolved through admin report update.',
            ]);

        return redirect()
            ->route('admin.reports.index', ['week' => $review->reviewerAssignment?->week_number ?? 1])
            ->with('success', 'Report updated successfully.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $selectedWeek = max(1, (int) $request->query('week', 1));
        $criteriaMap = ReviewCriteria::criteriaMap();
        $criteriaOrder = ReviewCriteria::criterionOrder();
        $criteriaHeaders = array_map(fn ($key) => $criteriaMap[$key]['label'], $criteriaOrder);
        $groupLabels = ReviewCriteria::groupLabelsInOrder();

        $headers = [
            'TimeStamp',
            'Tutor ID',
            'Name',
            'Mentor',
            'Reviewer',
            'Session Date',
            'Slot',
            'Group ID',
            'Type of flag',
            'Flag Color - Subcategory: Reason [Duration]',
            'Positive Concat',
            'Negative Concat',
            'Zoom link',
            'Flag Screenshot',
            ...$criteriaHeaders,
            ...array_map(fn ($label) => $label.' %', $groupLabels),
            'Score',
        ];

        $fileName = sprintf('quality-week-%d-report.csv', $selectedWeek);

        return response()->streamDownload(function () use ($selectedWeek, $headers, $criteriaOrder, $groupLabels) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            Review::query()
                ->with([
                    'tutor:id,tutor_code,name_en,mentor_name',
                    'reviewerAssignment:id,week_number',
                    'flags:id,review_id,color,status,subcategory,reason,duration_text,screenshot_path',
                ])
                ->whereHas('reviewerAssignment', fn ($query) => $query->where('week_number', $selectedWeek))
                ->orderBy('id')
                ->chunkById(200, function ($reviews) use ($output, $criteriaOrder, $groupLabels) {
                    foreach ($reviews as $review) {
                        $scores = $this->extractCriterionScores($review, $criteriaOrder);
                        $groupPercentages = $this->extractGroupPercentages($review, $groupLabels, $scores);
                        $flags = $review->flags->where('status', '!=', 'removed')->values();
                        $flagScreenshots = $flags
                            ->filter(fn ($flag) => ! empty($flag->screenshot_path))
                            ->map(fn ($flag) => asset('storage/'.$flag->screenshot_path))
                            ->values()
                            ->all();

                        $row = [
                            $review->submitted_at?->toDateTimeString(),
                            $review->tutor?->tutor_code,
                            $review->tutor?->name_en,
                            $review->tutor?->mentor_name,
                            $review->reviewer_name,
                            $review->session_date?->toDateString(),
                            $review->slot,
                            $review->group_code,
                            $this->buildFlagTypeString($flags->all()),
                            $this->buildFlagDetailsString($flags->all()),
                            $this->flattenComments($review->positive_comments_json ?? []),
                            $this->flattenComments($review->negative_comments_json ?? []),
                            $review->recorded_link,
                            $flagScreenshots === [] ? null : implode(' | ', $flagScreenshots),
                        ];

                        foreach ($criteriaOrder as $criterionKey) {
                            $row[] = (int) ($scores[$criterionKey] ?? ReviewCriteria::BASE_SCORE);
                        }

                        foreach ($groupLabels as $groupLabel) {
                            $row[] = (float) ($groupPercentages[$groupLabel] ?? 0);
                        }

                        $row[] = $review->score_breakdown_json['overall_score']
                            ?? $review->total_score
                            ?? array_sum($scores);

                        fputcsv($output, $row);
                    }
                });

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function extractCriterionScores(Review $review, array $criteriaOrder): array
    {
        $scoreFromJson = $review->score_breakdown_json['criteria_scores'] ?? [];
        $scores = [];

        foreach ($criteriaOrder as $criterionKey) {
            $scores[$criterionKey] = (int) ($scoreFromJson[$criterionKey] ?? ReviewCriteria::BASE_SCORE);
        }

        return $scores;
    }

    private function extractGroupPercentages(Review $review, array $groupLabels, array $criterionScores): array
    {
        $fromJson = $review->score_breakdown_json['group_percentages'] ?? [];
        $groupKeys = ReviewCriteria::groupCriteriaKeys();
        $result = [];

        foreach ($groupLabels as $groupLabel) {
            if (array_key_exists($groupLabel, $fromJson)) {
                $result[$groupLabel] = (float) $fromJson[$groupLabel];
                continue;
            }

            $keys = $groupKeys[$groupLabel] ?? [];
            $total = array_sum(array_map(fn ($key) => (int) ($criterionScores[$key] ?? 0), $keys));
            $max = count($keys) * ReviewCriteria::BASE_SCORE;
            $result[$groupLabel] = $max > 0 ? round(($total / $max) * 100, 2) : 0.0;
        }

        return $result;
    }

    private function flattenComments(array $comments): string
    {
        $flat = collect($comments)->flatten()->filter()->values()->all();
        if ($flat === []) {
            return '-';
        }

        return implode("\n", $flat);
    }

    private function implodeCommentsForStorage(array $comments): ?string
    {
        $flat = collect($comments)->flatten()->filter()->values()->all();

        return $flat === [] ? null : implode("\n", $flat);
    }

    private function buildFlagTypeString(array $flags): string
    {
        if ($flags === []) {
            return 'none';
        }

        $colors = collect($flags)
            ->map(fn ($flag) => ucfirst((string) ($flag->color ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $colors === [] ? 'none' : implode(', ', $colors);
    }

    private function buildFlagDetailsString(array $flags): string
    {
        if ($flags === []) {
            return '-';
        }

        $lines = collect($flags)
            ->map(function ($flag) {
                $color = ucfirst((string) ($flag->color ?? ''));
                $subcategory = (string) ($flag->subcategory ?? '');
                $reason = (string) ($flag->reason ?? '');
                $duration = (string) (($flag->duration_text ?? '') ?: '-');

                return sprintf('%s - %s: %s [%s]', $color, $subcategory, $reason, $duration);
            })
            ->filter(fn (string $line) => trim($line) !== '')
            ->values()
            ->all();

        return $lines === [] ? '-' : implode("\n", $lines);
    }

    private function parseCommentTextarea(string $text, array $existingPayload): array
    {
        $normalized = ReviewCriteria::emptySelections();
        $existingMap = [];

        foreach ($existingPayload as $criterionKey => $comments) {
            if (! is_array($comments)) {
                continue;
            }

            foreach ($comments as $comment) {
                $existingMap[$this->normalizeText((string) $comment)] = $criterionKey;
            }
        }

        $fallbackCriterion = ReviewCriteria::criterionOrder()[0] ?? null;
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $criterionKey = $existingMap[$this->normalizeText($line)]
                ?? ReviewCriteria::inferCriterionKeyFromComment($line)
                ?? $fallbackCriterion;

            if (! $criterionKey || ! array_key_exists($criterionKey, $normalized)) {
                continue;
            }

            $normalized[$criterionKey][] = $line;
        }

        foreach ($normalized as $criterionKey => $comments) {
            $normalized[$criterionKey] = array_values(array_unique($comments));
        }

        return $normalized;
    }

    private function normalizeText(string $value): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }
}
