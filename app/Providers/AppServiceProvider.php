<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit;
use App\Services\MailAuditCopy;
use App\Services\NotificationInbox;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

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
        View::composer('portal.layout', fn ($view) => $view->with(NotificationInbox::summary(auth()->user())));
        View::composer(['auth.form', 'legal.*'], function ($view): void {
            $view->with('business', Setting::values());
        });
        Gate::define('manage-business', fn (User $user) => $user->is_active && $user->isAdmin());
        Event::listen(Login::class, fn ($event) => Audit::record($event->user, 'auth.login', $event->user));
        Event::listen(Logout::class, function ($event) {
            if ($event->user) {
                Audit::record($event->user, 'auth.logout', $event->user);
            }
        });
        Event::listen(MessageSending::class, MailAuditCopy::class);
        foreach ([TwoFactorAuthenticationEnabled::class, TwoFactorAuthenticationConfirmed::class, TwoFactorAuthenticationDisabled::class, RecoveryCodesGenerated::class] as $eventClass) {
            Event::listen($eventClass, function ($event) {
                Audit::record($event->user, 'auth.'.class_basename($event), $event->user);
                $event->user->tokens()->delete();
            });
        }
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by((string) ($r->user()?->id ?? $r->ip())));
        RateLimiter::for('api-edge', fn (Request $r) => Limit::perMinute(600)->by($r->ip()));
        RateLimiter::for('contact', fn (Request $r) => Limit::perHour(5)->by($r->ip()));
        Event::listen(Verified::class, function (Verified $event): void {
            if ($event->user->customer_account_id) {
                Conversation::whereNull('customer_account_id')->whereNull('order_id')->where('contact_email', $event->user->email)->update(['customer_account_id' => $event->user->customer_account_id]);
            }
        });
    }
}
