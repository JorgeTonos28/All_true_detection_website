<?php
namespace App\Services;
use App\Models\Order;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;
class OrderEvents
{
    public static function record(Order $order, string $type): void
    {
        foreach (\App\Models\User::where('customer_account_id', $order->customer_account_id)->where('role', 'client')->where('is_active', true)->whereNotNull('email_verified_at')->get() as $client) {
            $client->notify(new \App\Notifications\PortalActivity('Actualización de su solicitud ATD', '/portal/orders/'.$order->public_id));
        }
        // Outbox records participate in the caller's transaction. Payloads never contain results or PII.
        foreach (WebhookEndpoint::where('customer_account_id', $order->customer_account_id)->where('is_active', true)->get() as $endpoint) {
            $eventId = (string) Str::uuid();
            WebhookDelivery::create([
                'event_id' => $eventId, 'webhook_endpoint_id' => $endpoint->id, 'order_id' => $order->id, 'event_type' => $type,
                'payload' => ['id' => $eventId, 'type' => $type, 'created_at' => now()->toIso8601String(), 'data' => ['order_id' => $order->public_id, 'status' => $order->status->value]],
                'next_attempt_at' => now(),
            ]);
        }
    }
}
