<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTutor();
    }

    public function view(User $user, Review $review): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTutor() && $user->tutor?->id === $review->tutor_id;
    }
}
