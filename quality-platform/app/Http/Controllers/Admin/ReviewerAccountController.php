<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReviewerAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'reviewer_type' => $request->string('reviewer_type')->toString() ?: null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
        ];

        $reviewers = User::query()
            ->where('role', 'reviewer')
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['reviewer_type'], function ($query, $reviewerType) {
                $query->where('reviewer_type', $reviewerType);
            })
            ->when($filters['is_active'] !== null, function ($query) use ($filters) {
                $query->where('is_active', $filters['is_active']);
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $reviewer) => [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
                'email' => $reviewer->email,
                'reviewer_type' => $reviewer->reviewer_type,
                'is_active' => $reviewer->is_active,
            ]);

        return Inertia::render('Admin/Reviewers/Index', [
            'filters' => $filters,
            'reviewers' => $reviewers,
            'stats' => [
                'total_reviewers' => User::query()->where('role', 'reviewer')->count(),
                'mentors' => User::query()->where('role', 'reviewer')->where('reviewer_type', 'mentor')->count(),
                'coordinators' => User::query()->where('role', 'reviewer')->where('reviewer_type', 'coordinator')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'reviewer_type' => ['required', Rule::in(['mentor', 'coordinator'])],
            'is_active' => ['required', 'boolean'],
        ]);

        User::query()->create([
            'name' => $payload['name'],
            'email' => strtolower(trim($payload['email'])),
            'password' => $payload['password'],
            'role' => 'reviewer',
            'reviewer_type' => $payload['reviewer_type'],
            'project_id' => null,
            'is_active' => $payload['is_active'],
        ]);

        return back()->with('success', 'Reviewer account created.');
    }

    public function update(Request $request, User $reviewer): RedirectResponse
    {
        abort_unless($reviewer->role === 'reviewer', 404);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($reviewer->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'reviewer_type' => ['required', Rule::in(['mentor', 'coordinator'])],
            'is_active' => ['required', 'boolean'],
        ]);

        $attributes = [
            'name' => $payload['name'],
            'email' => strtolower(trim($payload['email'])),
            'reviewer_type' => $payload['reviewer_type'],
            'is_active' => $payload['is_active'],
        ];

        if (! empty($payload['password'])) {
            $attributes['password'] = $payload['password'];
        }

        $reviewer->update($attributes);

        return back()->with('success', 'Reviewer account updated.');
    }
}
