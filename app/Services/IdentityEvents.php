<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\IdentitySession;
use App\Models\Order;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class IdentityEvents
{
    public static function record(IdentitySession $session): void
    {
        if ($session->access_channel !== 'api_credit') {
            return;
        }
        $order = Order::whereKey($session->order_id)->lockForUpdate()->firstOrFail();
        if ($order->status === OrderStatus::Cancelled) {
            return;
        }
        $people = $order->people()->with('identitySession')->get();
        $complete = $people->isNotEmpty() && $people->every(fn ($person) => $person->identitySession?->access_channel === 'api_credit' && $person->identitySession->decision !== null);
        if ($order->status !== OrderStatus::Cancelled) {
            $order->update(['status' => $complete ? OrderStatus::Completed : OrderStatus::InProgress]);
        }
        // Reconcile accepted usage even after suspension, but never send a new result notification.
        if (! $order->account->hasIdentityApiCredit()) {
            return;
        }
        foreach (WebhookEndpoint::where('customer_account_id', $order->customer_account_id)->where('is_active', true)->get() as $endpoint) {
            if (is_array($endpoint->events) && ! in_array('identity.verification.updated', $endpoint->events, true)) {
                continue;
            }
            $eventId = (string) Str::uuid();
            WebhookDelivery::create(['event_id' => $eventId, 'webhook_endpoint_id' => $endpoint->id, 'order_id' => $order->id,
                'event_type' => 'identity.verification.updated', 'next_attempt_at' => now(),
                'payload' => ['id' => $eventId, 'type' => 'identity.verification.updated', 'created_at' => now()->toIso8601String(),
                    'data' => ['order_id' => $order->public_id, 'person_id' => $session->person->public_id,
                        'session_id' => $session->public_id, 'environment' => $session->environment,
                        'revision' => $session->decision_revision, 'state' => $session->publicState(),
                        'links' => ['result' => '/api/v1/orders/'.$order->public_id.'/people/'.$session->person->public_id.'/identity-session/result']]]]);
        }
    }
}
