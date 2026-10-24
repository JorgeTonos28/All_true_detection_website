<?php

use App\Models\ApiRequestLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('portal:dispatch-webhooks')->everyMinute()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(fn () => ApiRequestLog::where('created_at', '<', now()->subDays(30))->delete())->daily()->name('api-metrics-retention')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
