<?php

namespace App\Providers;

use App\Domain\HR\Observers\UserObserver;
use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Observers\JobObserver;
use App\Models\User;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Job::observe(JobObserver::class);
        User::observe(UserObserver::class);
    }
}
