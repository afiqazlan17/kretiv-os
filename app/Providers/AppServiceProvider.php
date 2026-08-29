<?php

namespace App\Providers;

use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Observers\JobObserver;
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
    }
}
