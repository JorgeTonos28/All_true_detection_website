<?php

namespace App\Services;

use App\Models\CustomerAccount;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookManagement
{
    public function authorize(User $actor, WebhookEndpoint $endpoint): void
    {
        abort_unless($actor->isAdmin() || (! $actor->isStaff() && $actor->customer_account_id === $endpoint->customer_account_id), 403);
    }

    public function create(User $actor, array $data): array
    {
        abort_if($actor->isStaff() || ! $actor->customer_account_id, 403);
        app(WebhookUrl::class)->host($data['url']);

        return DB::transaction(function () use ($actor, $data) {
            CustomerAccount::whereKey($actor->customer_account_id)->lockForUpdate()->firstOrFail();
            abort_if(WebhookEndpoint::where('customer_account_id', $actor->customer_account_id)->where('is_active', true)->count() >= 5, 422, 'Límite de endpoints activos alcanzado.');
            $secret = Str::random(64);
            $endpoint = WebhookEndpoint::create(['customer_account_id' => $actor->customer_account_id, 'url' => $data['url'], 'events' => $data['events'] ?? null, 'secret' => $secret]);
            Audit::record($actor, 'webhook.created', $endpoint);

            return [$endpoint, $secret];
        });
    }

    public function rotate(User $actor, WebhookEndpoint $endpoint): string
    {
        $this->authorize($actor, $endpoint);

        return DB::transaction(function () use ($actor, $endpoint) {
            $endpoint = WebhookEndpoint::whereKey($endpoint->id)->lockForUpdate()->firstOrFail();
            abort_unless($endpoint->is_active, 409, 'El endpoint está desactivado.');
            abort_if($endpoint->previous_secret_expires_at?->isFuture(), 409, 'Espere a que termine la transición de la rotación anterior.');
            $secret = Str::random(64);
            $endpoint->update(['previous_secret' => $endpoint->secret, 'previous_secret_expires_at' => now()->addDay(), 'secret' => $secret, 'secret_rotated_at' => now()]);
            Audit::record($actor, 'webhook.secret_rotated', $endpoint);

            return $secret;
        });
    }

    public function test(User $actor, WebhookEndpoint $endpoint): WebhookDelivery
    {
        $this->authorize($actor, $endpoint);
        app(WebhookUrl::class)->host($endpoint->url);

        return DB::transaction(function () use ($actor, $endpoint) {
            $endpoint = WebhookEndpoint::whereKey($endpoint->id)->lockForUpdate()->firstOrFail();
            abort_unless($endpoint->is_active, 409);
            $id = (string) Str::uuid();
            $delivery = $endpoint->deliveries()->create(['order_id' => null, 'event_id' => $id, 'event_type' => 'endpoint.test', 'payload' => ['id' => $id, 'type' => 'endpoint.test', 'created_at' => now()->toIso8601String(), 'data' => ['endpoint_id' => $endpoint->public_id, 'test' => true]], 'next_attempt_at' => now()]);
            Audit::record($actor, 'webhook.test_requested', $endpoint);

            return $delivery;
        });
    }

    public function replay(User $actor, WebhookDelivery $delivery): void
    {
        $this->authorize($actor, $delivery->endpoint);
        app(WebhookUrl::class)->host($delivery->endpoint->url);
        DB::transaction(function () use ($actor, $delivery) {
            $delivery = WebhookDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless($delivery->failed_at && ! $delivery->delivered_at && $delivery->endpoint->is_active && ! $delivery->processing_until?->isFuture(), 409, 'Solo se reencolan entregas agotadas de endpoints activos.');
            $delivery->update(['attempts' => 0, 'failed_at' => null, 'response_status' => null, 'next_attempt_at' => now(), 'processing_token' => null, 'processing_until' => null, 'replay_count' => $delivery->replay_count + 1]);
            Audit::record($actor, 'webhook.replayed', $delivery);
        });
    }
}
