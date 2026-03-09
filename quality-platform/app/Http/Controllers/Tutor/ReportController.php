<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewerAssignment;
use App\Models\WeekCycle;
use App\Support\ReviewCriteria;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $tutor = $request->user()->tutor;
        abort_if(! $tutor, 403, 'Tutor profile is not linked to this account.');
        $tutor->loadMissing('project:id,code,name');

        $programType = strtoupper((string) ($tutor->project?->code ?? ''));
        $programLogos = match ($programType) {
            'DEMI' => [
                asset('images/demi-ministry-logo.png'),
                asset('images/demi-cubs-logo.png'),
            ],
            'DECI' => [
                asset('images/demi-cubs-logo.png'),
            ],
            default => [],
        };

        $publishedWeeks = WeekCycle::query()
            ->whereNotNull('reports_published_at')
            ->orderBy('week_number')
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->values()
            ->all();
        $selectedWeek = (int) $request->query('week', 0);
        if ($selectedWeek <= 0 || ! in_array($selectedWeek, $publishedWeeks, true)) {
            $selectedWeek = $publishedWeeks !== [] ? max($publishedWeeks) : 0;
        }
        $weeks = $publishedWeeks;

        $criteriaMap = ReviewCriteria::criteriaMap();
        $criteriaOrder = ReviewCriteria::criterionOrder();
        $groupLabels = ReviewCriteria::groupLabelsInOrder();

        $weekReviews = $selectedWeek > 0
            ? Review::query()
                ->with([
                    'reviewerAssignment:id,week_number',
                    'flags:id,review_id,color,status,objection_status,screenshot_path,subcategory,reason,duration_text',
                ])
                ->where('tutor_id', $tutor->id)
                ->whereHas('reviewerAssignment', fn ($query) => $query->where('week_number', $selectedWeek))
                ->latest('submitted_at')
                ->latest('id')
                ->get([
                    'id',
                    'tutor_id',
                    'reviewer_assignment_id',
                    'session_date',
                    'slot',
                    'group_code',
                    'recorded_link',
                    'positive_comments_json',
                    'negative_comments_json',
                    'score_breakdown_json',
                    'total_score',
                    'submitted_at',
                ])
            : collect();

        $scoreValues = $weekReviews
            ->map(fn (Review $review) => $this->resolveOverallScore($review, $criteriaOrder))
            ->values();
        $avgScore = $scoreValues->isNotEmpty() ? round((float) $scoreValues->avg(), 2) : 0.0;
        $bestScore = $scoreValues->isNotEmpty() ? round((float) $scoreValues->max(), 2) : 0.0;

        $groupSums = [];
        $groupCounts = [];
        foreach ($groupLabels as $groupLabel) {
            $groupSums[$groupLabel] = 0.0;
            $groupCounts[$groupLabel] = 0;
        }

        foreach ($weekReviews as $review) {
            $scores = $this->extractCriterionScores($review, $criteriaOrder);
            $groupPercentages = $this->extractGroupPercentages($review, $groupLabels, $scores);
            foreach ($groupLabels as $groupLabel) {
                if (! array_key_exists($groupLabel, $groupPercentages)) {
                    continue;
                }
                $groupSums[$groupLabel] += (float) $groupPercentages[$groupLabel];
                $groupCounts[$groupLabel]++;
            }
        }

        $groupAveragePercentages = [];
        foreach ($groupLabels as $groupLabel) {
            $groupAveragePercentages[$groupLabel] = $groupCounts[$groupLabel] > 0
                ? round($groupSums[$groupLabel] / $groupCounts[$groupLabel], 2)
                : 0.0;
        }

        $previousWeekAverage = null;
        if ($selectedWeek > 1) {
            $previousWeekScores = Review::query()
                ->where('tutor_id', $tutor->id)
                ->whereHas('reviewerAssignment', fn ($query) => $query->where('week_number', $selectedWeek - 1))
                ->get(['score_breakdown_json', 'total_score'])
                ->map(fn (Review $review) => $this->resolveOverallScore($review, $criteriaOrder));
            if ($previousWeekScores->isNotEmpty()) {
                $previousWeekAverage = round((float) $previousWeekScores->avg(), 2);
            }
        }

        $allFlags = $weekReviews->flatMap(fn (Review $review) => $review->flags->where('status', '!=', 'removed')->values());
        $summary = [
            'total_reviews' => $weekReviews->count(),
            'avg_score' => $avgScore,
            'best_score' => $bestScore,
            'previous_week_avg' => $previousWeekAverage,
            'score_delta' => $previousWeekAverage === null ? null : round($avgScore - $previousWeekAverage, 2),
            'flags_count' => $allFlags->count(),
            'yellow_flags' => $allFlags->where('color', 'yellow')->count(),
            'red_flags' => $allFlags->where('color', 'red')->count(),
            'both_flags' => $allFlags->where('color', 'both')->count(),
            'pending_objections' => $allFlags->where('objection_status', 'pending')->count(),
            'group_averages' => $groupAveragePercentages,
        ];

        $scoreTimeline = $weekReviews
            ->sortBy(fn (Review $review) => ($review->session_date?->toDateString() ?? '').'|'.($review->slot ?? ''))
            ->values()
            ->map(function (Review $review, int $index) use ($criteriaOrder) {
                return [
                    'index' => $index + 1,
                    'label' => $review->session_date?->format('m/d') ?? '#'.($index + 1),
                    'session_date' => $review->session_date?->toDateString(),
                    'slot' => $review->slot,
                    'score' => round((float) $this->resolveOverallScore($review, $criteriaOrder), 2),
                ];
            })
            ->all();

        $reports = Review::query()
            ->with([
                'reviewerAssignment:id,week_number',
                'flags:id,review_id,color,subcategory,reason,duration_text,screenshot_path,status,objection_status',
            ])
            ->where('tutor_id', $tutor->id)
            ->when(
                $selectedWeek > 0,
                fn ($query) => $query->whereHas('reviewerAssignment', fn ($innerQuery) => $innerQuery->where('week_number', $selectedWeek)),
                fn ($query) => $query->whereRaw('1 = 0')
            )
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
                    'timestamp' => $review->submitted_at?->toDateTimeString(),
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
                    'score' => $this->resolveOverallScore($review, $criteriaOrder),
                ];
            });

        return Inertia::render('Tutor/Reports/Index', [
            'week' => $selectedWeek,
            'weeks' => $weeks,
            'reportsPublished' => $weeks !== [],
            'programType' => $programType,
            'programLogos' => $programLogos,
            'criteriaHeaders' => array_map(fn ($key) => $criteriaMap[$key]['label'], $criteriaOrder),
            'groupHeaders' => $groupLabels,
            'summary' => $summary,
            'scoreTimeline' => $scoreTimeline,
            'reports' => $reports,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $tutor = $request->user()->tutor;
        abort_if(! $tutor, 403, 'Tutor profile is not linked to this account.');

        $selectedWeek = max(1, (int) $request->query('week', 1));
        abort_unless(
            WeekCycle::query()->where('week_number', $selectedWeek)->whereNotNull('reports_published_at')->exists(),
            403,
            'This week report has not been published yet.'
        );
        $criteriaMap = ReviewCriteria::criteriaMap();
        $criteriaOrder = ReviewCriteria::criterionOrder();
        $criteriaHeaders = array_map(fn ($key) => $criteriaMap[$key]['label'], $criteriaOrder);
        $groupLabels = ReviewCriteria::groupLabelsInOrder();

        $headers = [
            'TimeStamp',
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

        $fileName = sprintf('tutor-week-%d-report.csv', $selectedWeek);

        return response()->streamDownload(function () use ($tutor, $selectedWeek, $headers, $criteriaOrder, $groupLabels) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            Review::query()
                ->with([
                    'reviewerAssignment:id,week_number',
                    'flags:id,review_id,color,status,subcategory,reason,duration_text,screenshot_path',
                ])
                ->where('tutor_id', $tutor->id)
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

                        $row[] = $this->resolveOverallScore($review, $criteriaOrder);
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

    private function resolveOverallScore(Review $review, array $criteriaOrder): float|int
    {
        $scores = $this->extractCriterionScores($review, $criteriaOrder);

        return $review->score_breakdown_json['overall_score']
            ?? $review->total_score
            ?? array_sum($scores);
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
}
