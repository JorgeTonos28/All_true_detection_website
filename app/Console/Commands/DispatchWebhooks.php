<?php

namespace App\Console\Commands;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DispatchWebhooks extends Command
{
    protected $signature = 'portal:dispatch-webhooks';

    protected $description = 'Encola los eventos pendientes de entrega o reintento';

    public function handle(): int
    {
        WebhookDelivery::whereNull('delivered_at')->whereNull('failed_at')->where('next_attempt_at', '<=', now())->where(fn ($q) => $q->whereNull('processing_until')->orWhere('processing_until', '<=', now()))->chunkById(100, function ($deliveries) {
            foreach ($deliveries as $delivery) {
                DeliverWebhook::dispatch($delivery->id);
            }
        });

        Cache::put('portal.webhooks.dispatched_at', now()->toIso8601String(), now()->addDays(2));

        return self::SUCCESS;
    }
}
