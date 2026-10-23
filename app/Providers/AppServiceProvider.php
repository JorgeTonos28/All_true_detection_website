<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

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
        Gate::define('manage-business', fn (User $user) => $user->is_active && $user->isAdmin());
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by((string) ($r->user()?->id ?? $r->ip())));
        RateLimiter::for('contact', fn (Request $r) => Limit::perHour(5)->by($r->ip()));
        Event::listen(Verified::class, function (Verified $event): void {
            if ($event->user->customer_account_id) {
                Conversation::whereNull('customer_account_id')->whereNull('order_id')->where('contact_email', $event->user->email)->update(['customer_account_id' => $event->user->customer_account_id]);
            }
        });
    }
}
