<?php

namespace App\Providers;

use App\Models\Complaint;
use App\Models\Flag;
use App\Models\Project;
use App\Models\Review;
use App\Models\Tutor;
use App\Policies\ComplaintPolicy;
use App\Policies\FlagPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\TutorPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Project::class => ProjectPolicy::class,
        Tutor::class => TutorPolicy::class,
        Review::class => ReviewPolicy::class,
        Flag::class => FlagPolicy::class,
        Complaint::class => ComplaintPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
