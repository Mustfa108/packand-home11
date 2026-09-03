<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\User;

class AssessmentPolicy
{
    public function view(User $user, Assessment $assessment): bool
    {
        return $user->id === $assessment->user_id;
    }

    public function submit(User $user, Assessment $assessment): bool
    {
        return $user->id === $assessment->user_id;
    }
}
