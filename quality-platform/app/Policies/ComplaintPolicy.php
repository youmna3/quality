<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTutor();
    }

    public function view(User $user, Complaint $complaint): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTutor() && $user->tutor?->id === $complaint->tutor_id;
    }

    public function create(User $user): bool
    {
        return $user->isTutor() && $user->tutor !== null;
    }
}
