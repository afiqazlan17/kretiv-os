<?php

namespace App\Providers;

use App\Models\Job;
use App\Models\User;
use App\Observers\JobObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
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

        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('Reset your KretivOS password')
            ->greeting('Hi '.($user->short_name ?: $user->name).',')
            ->line('We received a request to reset the password for your KretivOS account.')
            ->action('Set a new password', route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->line('This link works for 60 minutes. If you did not ask for this, ignore this email and your password stays the same.')
            ->salutation('KretivOS'));
    }
}
