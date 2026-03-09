<?php

namespace App\Http\Controllers\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Review::class);

        $tutor = auth()->user()->tutor;
        abort_if(! $tutor, 403, 'Tutor profile is not linked to this account.');

        $reviews = Review::query()
            ->where('tutor_id', $tutor->id)
            ->withCount('flags')
            ->latest('session_date')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Tutor/Reviews/Index', [
            'reviews' => $reviews,
        ]);
    }

    public function show(Review $review): Response
    {
        $this->authorize('view', $review);

        $review->load(['flags' => function ($query) {
            $query->latest('created_at');
        }]);

        return Inertia::render('Tutor/Reviews/Show', [
            'review' => $review,
        ]);
    }
}
