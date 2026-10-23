<?php

namespace App\Console\Commands;

use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class DispatchWebhooks extends Command
{
    protected $signature = 'portal:dispatch-webhooks';

    protected $description = 'Encola los eventos pendientes de entrega o reintento';

    public function handle(): int
    {
        WebhookDelivery::whereNull('delivered_at')->whereNull('failed_at')->where('next_attempt_at', '<=', now())->chunkById(100, function ($deliveries) {
            foreach ($deliveries as $delivery) {
                DeliverWebhook::dispatch($delivery->id);
            }
        });

        return self::SUCCESS;
    }
}
