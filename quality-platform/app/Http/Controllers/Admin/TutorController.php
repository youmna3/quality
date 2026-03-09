<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTutorRequest;
use App\Http\Requests\Admin\UpdateTutorRequest;
use App\Models\Project;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TutorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tutor::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'project_type' => strtoupper($request->string('project_type')->toString()) ?: null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
        ];

        $tutors = Tutor::query()
            ->with(['user:id,name,email,is_active', 'project:id,code'])
            ->when($filters['search'], function ($query, $search) {
                $normalizedSearch = strtolower(trim((string) $search));
                $compactSearch = str_replace([' ', '-'], '', $normalizedSearch);
                $query->where(function ($innerQuery) use ($search, $normalizedSearch, $compactSearch) {
                    $innerQuery
                        ->where('tutor_code', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('mentor_name', 'like', "%{$search}%")
                        ->orWhere('dashboard_email', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search, $normalizedSearch, $compactSearch) {
                            $userQuery
                                ->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ["%{$normalizedSearch}%"])
                                ->orWhereRaw('LOWER(TRIM(email)) LIKE ?', ["%{$normalizedSearch}%"]);

                            if ($compactSearch !== '') {
                                $userQuery->orWhereRaw(
                                    "REPLACE(REPLACE(LOWER(TRIM(email)), '-', ''), ' ', '') LIKE ?",
                                    ["%{$compactSearch}%"]
                                );
                            }
                        })
                        ->orWhereRaw('LOWER(TRIM(tutor_code)) LIKE ?', ["%{$normalizedSearch}%"])
                        ->orWhereRaw('LOWER(TRIM(name_en)) LIKE ?', ["%{$normalizedSearch}%"])
                        ->orWhereRaw('LOWER(TRIM(mentor_name)) LIKE ?', ["%{$normalizedSearch}%"]);

                    if ($compactSearch !== '') {
                        $innerQuery->orWhereRaw(
                            "REPLACE(REPLACE(LOWER(TRIM(tutor_code)), '-', ''), ' ', '') LIKE ?",
                            ["%{$compactSearch}%"]
                        );
                    }
                });
            })
            ->when($filters['project_type'], function ($query, $projectType) {
                $query->whereHas('project', function ($projectQuery) use ($projectType) {
                    $projectQuery->where('code', $projectType);
                });
            })
            ->when($filters['is_active'] !== null, function ($query) use ($filters) {
                $query->where('is_active', $filters['is_active']);
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Tutor $tutor) => [
                'id' => $tutor->id,
                'tutor_code' => $tutor->tutor_code,
                'name_en' => $tutor->name_en,
                'mentor_name' => $tutor->mentor_name,
                'project_type' => $tutor->project?->code,
                'grade' => $tutor->grade,
                'zoom_email' => $tutor->zoom_email,
                'zoom_password' => $tutor->zoom_password,
                'dashboard_email' => $tutor->dashboard_email,
                'dashboard_password' => $tutor->dashboard_password,
                'is_active' => $tutor->is_active,
                'user' => $tutor->user,
            ]);

        return Inertia::render('Admin/Tutors/Index', [
            'filters' => $filters,
            'tutors' => $tutors,
        ]);
    }

    public function store(StoreTutorRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        $projectType = $this->normalizeProjectType($payload['project_type']);
        $projectId = $this->resolveProjectIdFromType($projectType);

        DB::transaction(function () use ($payload, $projectId) {
            $user = $this->createOrUpdateLinkedUser(null, $payload, $projectId);

            Tutor::create([
                'tutor_code' => $payload['tutor_code'],
                'name_en' => $payload['name_en'],
                'project_id' => $projectId,
                'mentor_name' => $payload['mentor_name'] ?? null,
                'grade' => $payload['grade'] ?? null,
                'zoom_email' => $payload['zoom_email'] ?? null,
                'zoom_password' => $payload['zoom_password'] ?? null,
                'dashboard_email' => $user->email,
                'dashboard_password' => $payload['dashboard_password'] ?? $payload['tutor_code'],
                'shift' => null,
                'is_active' => $payload['is_active'],
                'user_id' => $user->id,
            ]);
        });

        return back()->with('success', 'Tutor created successfully.');
    }

    public function update(UpdateTutorRequest $request, Tutor $tutor): RedirectResponse
    {
        $payload = $request->validated();
        $projectType = $this->normalizeProjectType($payload['project_type']);
        $projectId = $this->resolveProjectIdFromType($projectType);

        DB::transaction(function () use ($payload, $tutor, $projectId) {
            $linkedUser = $this->createOrUpdateLinkedUser($tutor->user, $payload, $projectId);

            $tutor->update([
                'tutor_code' => $payload['tutor_code'],
                'name_en' => $payload['name_en'],
                'project_id' => $projectId,
                'mentor_name' => $payload['mentor_name'] ?? null,
                'grade' => $payload['grade'] ?? null,
                'zoom_email' => $payload['zoom_email'] ?? null,
                'zoom_password' => $payload['zoom_password'] ?? null,
                'dashboard_email' => $linkedUser->email,
                'dashboard_password' => $payload['dashboard_password'] ?? $tutor->dashboard_password ?? $payload['tutor_code'],
                'shift' => null,
                'is_active' => $payload['is_active'],
                'user_id' => $linkedUser->id,
            ]);
        });

        return back()->with('success', 'Tutor updated successfully.');
    }

    public function destroy(Tutor $tutor): RedirectResponse
    {
        $this->authorize('delete', $tutor);

        DB::transaction(function () use ($tutor) {
            $linkedUser = $tutor->user;
            $tutor->update(['is_active' => false]);

            if ($linkedUser && $linkedUser->is_active) {
                $linkedUser->update(['is_active' => false]);
            }
        });

        return back()->with('success', 'Tutor deactivated. Record was kept in database.');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Tutor::class);

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        // Bulk account creation can hash many passwords; use a faster round for this request only.
        config(['hashing.bcrypt.rounds' => 8]);

        $request->validate([
            'sheet' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'default_project_type' => ['nullable', 'string', 'max:10'],
        ]);

        $defaultProjectType = $this->normalizeProjectType($request->input('default_project_type')) ?? 'DEMI';
        $rows = array_map('str_getcsv', file($request->file('sheet')->getRealPath()));

        if (count($rows) < 2) {
            return back()->with('error', 'The uploaded sheet is empty.');
        }

        $headers = array_map(
            fn ($header) => $this->normalizeHeader((string) $header),
            array_shift($rows)
        );
        $projectIdsByType = $this->projectIdsByType();
        $processed = 0;
        $skipped = 0;

        $payloadRows = [];
        foreach ($rows as $row) {
            if (! is_array($row) || count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                $skipped++;
                continue;
            }

            $record = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $record[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
            }

            $tutorId = trim((string) ($record['tutor_id'] ?? $record['tutor_code'] ?? ''));
            if ($tutorId === '') {
                $skipped++;
                continue;
            }

            $projectTypeRaw = $record['project_type'] ?? $record['type'] ?? $record['project'] ?? null;
            $projectType = $this->normalizeProjectType($projectTypeRaw) ?? $defaultProjectType;
            if (! $projectType || ! isset($projectIdsByType[$projectType])) {
                $skipped++;
                continue;
            }

            $payloadRows[] = [
                'tutor_code' => $tutorId,
                'name_en' => $record['name'] ?? $record['name_en'] ?? $record['tutor_name'] ?? $tutorId,
                'project_type' => $projectType,
                'mentor_name' => $record['mentor'] ?? $record['mentor_name'] ?? null,
                'grade' => $record['grade'] ?? null,
                'zoom_email' => $record['zoom_email'] ?? null,
                'zoom_password' => $record['zoom_password'] ?? null,
                'dashboard_password' => $record['dashboard_password'] ?? $record['password'] ?? null,
                'is_active' => $this->toBool($record['is_active'] ?? $record['active'] ?? $record['status'] ?? $record['status_action'] ?? true),
            ];
        }

        if ($payloadRows === []) {
            return back()->with('error', 'No valid rows found in the uploaded sheet.');
        }

        $tutorCodes = array_values(array_unique(array_map(fn ($row) => $row['tutor_code'], $payloadRows)));
        $existingTutors = Tutor::query()
            ->with('user:id,email')
            ->whereIn('tutor_code', $tutorCodes)
            ->get()
            ->keyBy('tutor_code');

        $dashboardEmails = array_map(
            fn ($code) => strtolower(trim((string) $code)).'@ischoolteams.com',
            $tutorCodes
        );
        $usersByEmail = User::query()
            ->whereIn('email', $dashboardEmails)
            ->get()
            ->keyBy(fn (User $user) => strtolower($user->email));

        foreach ($payloadRows as $payload) {
            $existingTutor = $existingTutors->get($payload['tutor_code']);
            $dashboardEmail = strtolower(trim((string) $payload['tutor_code'])).'@ischoolteams.com';

            $linkedUser = $this->createOrUpdateLinkedUser(
                $existingTutor?->user ?? $usersByEmail->get($dashboardEmail),
                $payload,
                $projectIdsByType[$payload['project_type']]
            );

            $tutor = Tutor::updateOrCreate(
                ['tutor_code' => $payload['tutor_code']],
                [
                    'name_en' => $payload['name_en'],
                    'project_id' => $projectIdsByType[$payload['project_type']],
                    'mentor_name' => $payload['mentor_name'],
                    'grade' => $payload['grade'],
                    'zoom_email' => $payload['zoom_email'],
                    'zoom_password' => $payload['zoom_password'],
                    'dashboard_email' => $linkedUser->email,
                    'dashboard_password' => $payload['dashboard_password'] ?? $existingTutor?->dashboard_password ?? $payload['tutor_code'],
                    'shift' => null,
                    'is_active' => $payload['is_active'],
                    'user_id' => $linkedUser->id,
                ]
            );

            $usersByEmail->put(strtolower($linkedUser->email), $linkedUser);
            $existingTutors->put($tutor->tutor_code, $tutor->setRelation('user', $linkedUser));
            $processed++;
        }

        return back()->with('success', "Import completed. {$processed} tutor(s) processed, {$skipped} row(s) skipped.");
    }

    private function createOrUpdateLinkedUser(?User $user, array $payload, int $projectId): User
    {
        $tutorId = strtolower(trim((string) $payload['tutor_code']));
        $dashboardEmail = $tutorId.'@ischoolteams.com';

        if (! $user) {
            $user = User::query()->where('email', $dashboardEmail)->first();
        }

        if (! $user) {
            return User::create([
                'name' => $payload['name_en'],
                'email' => $dashboardEmail,
                'password' => $payload['dashboard_password'] ?? $payload['tutor_code'],
                'role' => 'tutor',
                'project_id' => $projectId,
                'is_active' => (bool) $payload['is_active'],
            ]);
        }

        $attributes = [
            'name' => $payload['name_en'],
            'email' => $dashboardEmail,
            'role' => 'tutor',
            'project_id' => $projectId,
            'is_active' => (bool) $payload['is_active'],
        ];

        if (! empty($payload['dashboard_password'])) {
            $attributes['password'] = $payload['dashboard_password'];
        }

        $user->update($attributes);

        return $user;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = str_replace("\xC2\xA0", ' ', $header);
        $header = strtolower(str_replace([' ', '-', '/', '.'], '_', $header));
        $header = preg_replace('/_+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return ! in_array($normalized, ['0', 'false', 'no', 'inactive'], true);
    }

    private function normalizeProjectType(mixed $value): ?string
    {
        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['DEMI', 'DECI'], true) ? $normalized : null;
    }

    private function resolveProjectIdFromType(string $projectType): int
    {
        $name = $projectType === 'DEMI'
            ? 'Digital English Mastery Initiative'
            : 'Digital English Coaching Initiative';

        return Project::query()->firstOrCreate(
            ['code' => $projectType],
            ['name' => $name]
        )->id;
    }

    private function projectIdsByType(): array
    {
        return [
            'DEMI' => $this->resolveProjectIdFromType('DEMI'),
            'DECI' => $this->resolveProjectIdFromType('DECI'),
        ];
    }
}
