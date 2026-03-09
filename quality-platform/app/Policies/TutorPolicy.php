<?php

namespace App\Policies;

use App\Models\Tutor;
use App\Models\User;

class TutorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Tutor $tutor): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTutor() && $user->tutor?->id === $tutor->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Tutor $tutor): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Tutor $tutor): bool
    {
        return $user->isAdmin();
    }
}
