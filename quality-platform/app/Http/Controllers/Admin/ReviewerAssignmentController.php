<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpsGroupMapping;
use App\Models\OpsIssueEntry;
use App\Models\ReviewerAssignment;
use App\Models\Tutor;
use App\Models\WeekCycle;
use App\Services\ReviewerAutoAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewerAssignmentController extends Controller
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
        $weekCycle = WeekCycle::query()
            ->where('week_number', $selectedWeek)
            ->first();

        $assignments = ReviewerAssignment::query()
            ->with([
                'tutor:id,tutor_code,name_en,mentor_name',
                'reviewer:id,name,email,reviewer_type',
            ])
            ->when(! $searchAcrossWeeks, fn ($query) => $query->where('week_number', $selectedWeek))
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
                        ->orWhereHas('reviewer', function ($reviewerQuery) use ($search, $normalizedSearch, $compactSearch) {
                            $reviewerQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ["%{$normalizedSearch}%"])
                                ->orWhereRaw('LOWER(TRIM(email)) LIKE ?', ["%{$normalizedSearch}%"]);

                            if ($compactSearch !== '') {
                                $reviewerQuery->orWhereRaw(
                                    "REPLACE(REPLACE(LOWER(TRIM(email)), '-', ''), ' ', '') LIKE ?",
                                    ["%{$compactSearch}%"]
                                );
                            }
                        });
                });
            })
            ->orderByDesc('week_number')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ReviewerAssignment $assignment) => [
                'id' => $assignment->id,
                'week_number' => $assignment->week_number,
                'reviewer_type' => $assignment->reviewer_type,
                'reviewer' => $assignment->reviewer
                    ? [
                        'id' => $assignment->reviewer->id,
                        'name' => $assignment->reviewer->name,
                        'email' => $assignment->reviewer->email,
                        'reviewer_type' => $assignment->reviewer->reviewer_type,
                    ]
                    : null,
                'tutor' => $assignment->tutor
                    ? [
                        'id' => $assignment->tutor->id,
                        'tutor_code' => $assignment->tutor->tutor_code,
                        'name_en' => $assignment->tutor->name_en,
                        'mentor_name' => $assignment->tutor->mentor_name,
                    ]
                    : null,
            ]);

        $assignedTutorIds = ReviewerAssignment::query()
            ->where('week_number', $selectedWeek)
            ->pluck('tutor_id')
            ->all();

        $typeCounts = ReviewerAssignment::query()
            ->where('week_number', $selectedWeek)
            ->selectRaw('reviewer_type, COUNT(*) as total')
            ->groupBy('reviewer_type')
            ->pluck('total', 'reviewer_type');

        $mentorAssigned = (int) ($typeCounts['mentor'] ?? 0);
        $coordinatorAssigned = (int) ($typeCounts['coordinator'] ?? 0);
        $typeRatio = $mentorAssigned > 0
            ? round($coordinatorAssigned / $mentorAssigned, 2)
            : null;

        return Inertia::render('Admin/Assignments/Index', [
            'week' => $selectedWeek,
            'weeks' => $weeks,
            'weekCycle' => [
                'starts_at' => $weekCycle?->starts_at?->format('Y-m-d\TH:i'),
                'deadline_at' => $weekCycle?->deadline_at?->format('Y-m-d\TH:i'),
            ],
            'kpis' => [
                'assigned_this_week' => ReviewerAssignment::query()->where('week_number', $selectedWeek)->count(),
                'active_tutors' => Tutor::query()->where('is_active', true)->count(),
                'pending_tutors' => Tutor::query()
                    ->where('is_active', true)
                    ->whereNotIn('id', $assignedTutorIds)
                    ->count(),
                'mentor_assigned' => $mentorAssigned,
                'coordinator_assigned' => $coordinatorAssigned,
                'type_ratio' => $typeRatio,
            ],
            'filters' => $filters,
            'searchAcrossWeeks' => $searchAcrossWeeks,
            'opsStats' => [
                'group_rows' => OpsGroupMapping::query()->count(),
                'issue_rows' => OpsIssueEntry::query()->count(),
            ],
            'assignments' => $assignments,
        ]);
    }

    public function autoAssign(Request $request, ReviewerAutoAssignmentService $service): RedirectResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');

        $payload = $request->validate([
            'week' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $service->assignForWeek((int) $payload['week'], auth()->id());
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.assignments.index', ['week' => $payload['week']])
            ->with(
                'success',
                sprintf(
                    'Auto-assignment completed for Week %d. %d tutor(s) assigned, %d already assigned.',
                    $payload['week'],
                    $result['assigned'],
                    $result['already_assigned']
                )
            );
    }

    public function redoAssign(Request $request, ReviewerAutoAssignmentService $service): RedirectResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');

        $payload = $request->validate([
            'week' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $service->assignForWeek((int) $payload['week'], auth()->id(), true);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.assignments.index', ['week' => $payload['week']])
            ->with(
                'success',
                sprintf(
                    'Redo assignment completed for Week %d. %d reassigned, %d unchanged, %d newly assigned.',
                    $payload['week'],
                    $result['reassigned'],
                    $result['unchanged'],
                    $result['created']
                )
            );
    }

    public function importOpsGroups(Request $request): RedirectResponse
    {
        $request->validate([
            'week' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        [$headerMap, $rows] = $this->readCsvRows($request->file('file')->getRealPath());

        $tutorCodeIndex = $this->findColumnIndex($headerMap, ['tutorid', 'tutorcode', 'tutor']);
        $slotIndex = $this->findColumnIndex($headerMap, ['slot', 'sessionslot']);
        $groupIndex = $this->findColumnIndex($headerMap, ['groupid', 'groupcode', 'group']);
        $sessionDateIndex = $this->findColumnIndex($headerMap, ['sessiondate', 'date']);

        if ($tutorCodeIndex === null || $slotIndex === null || $groupIndex === null) {
            return back()->with('error', 'Session group sheet must include Tutor ID, Slot, and Group ID columns.');
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
            ->route('admin.assignments.index', ['week' => $request->integer('week', 1)])
            ->with('success', sprintf('Session group mappings imported: %d row(s).', $imported));
    }

    public function importOpsIssues(Request $request): RedirectResponse
    {
        $request->validate([
            'week' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        [$headerMap, $rows] = $this->readCsvRows($request->file('file')->getRealPath());

        $tutorCodeIndex = $this->findColumnIndex($headerMap, ['tutorid', 'tutorcode', 'tutor']);
        $issueIndex = $this->findColumnIndex($headerMap, ['issue', 'issuetext', 'problem', 'complainttext']);
        $slotIndex = $this->findColumnIndex($headerMap, ['slot', 'sessionslot']);
        $sessionDateIndex = $this->findColumnIndex($headerMap, ['sessiondate', 'date']);

        if ($tutorCodeIndex === null || $issueIndex === null) {
            return back()->with('error', 'Issue sheet must include Tutor ID and Issue columns.');
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
            ->route('admin.assignments.index', ['week' => $request->integer('week', 1)])
            ->with('success', sprintf('Session issue entries imported: %d row(s).', $imported));
    }

    public function updateCycle(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'week' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'deadline_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $week = (int) $payload['week'];
        $startsAt = $payload['starts_at'] ?? null;
        $deadlineAt = $payload['deadline_at'] ?? null;

        if ($startsAt === null && $deadlineAt === null) {
            WeekCycle::query()
                ->where('week_number', $week)
                ->delete();

            return redirect()
                ->route('admin.assignments.index', ['week' => $week])
                ->with('success', sprintf('Week %d cycle cleared.', $week));
        }

        WeekCycle::query()->updateOrCreate(
            ['week_number' => $week],
            [
                'starts_at' => $startsAt,
                'deadline_at' => $deadlineAt,
            ]
        );

        return redirect()
            ->route('admin.assignments.index', ['week' => $week])
            ->with('success', sprintf('Week %d cycle dates saved.', $week));
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
}
