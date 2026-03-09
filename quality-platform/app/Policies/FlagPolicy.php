<?php

namespace App\Policies;

use App\Models\Flag;
use App\Models\User;

class FlagPolicy
{
    public function view(User $user, Flag $flag): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTutor() && $user->tutor?->id === $flag->tutor_id;
    }

    public function update(User $user, Flag $flag): bool
    {
        return $user->isAdmin();
    }
}
