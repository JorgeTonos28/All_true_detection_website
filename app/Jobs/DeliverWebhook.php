<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeliverWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public int $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(WebhookUrl $urls): void
    {
        $lease = (string) Str::uuid();
        $delivery = DB::transaction(function () use ($lease) {
            $delivery = WebhookDelivery::whereKey($this->deliveryId)->lockForUpdate()->firstOrFail();
            if ($delivery->delivered_at || $delivery->failed_at || $delivery->next_attempt_at?->isFuture() || $delivery->processing_until?->isFuture()) {
                return null;
            }
            if ($delivery->attempts >= 8 || ! $delivery->endpoint->is_active || ($delivery->order_id && $delivery->endpoint->customer_account_id !== $delivery->order?->customer_account_id)) {
                $delivery->update(['failed_at' => now(), 'next_attempt_at' => null, 'processing_token' => null, 'processing_until' => null]);

                return null;
            }
            $delivery->update(['attempts' => $delivery->attempts + 1, 'processing_token' => $lease, 'processing_until' => now()->addSeconds(60)]);

            return $delivery;
        });
        if (! $delivery) {
            return;
        }

        // Do not keep database locks open during DNS resolution or remote HTTP.
        $started = hrtime(true);
        $status = null;
        $error = null;
        try {
            $endpoint = $delivery->endpoint;
            [$host, $address] = $urls->publicAddress($endpoint->url);
            $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $timestamp = (string) time();
            $signature = 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);
            if ($endpoint->previous_secret && $endpoint->previous_secret_expires_at?->isFuture()) {
                $signature .= ',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->previous_secret);
            }
            $response = Http::withOptions(['proxy' => '', 'allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$address]], 'progress' => function ($total, $downloaded) {
                if ($downloaded > 65536 || $total > 65536) {
                    throw new \RuntimeException('Webhook response exceeds limit');
                }
            }])->connectTimeout(5)->timeout(15)->withHeaders(['X-ATD-Event-ID' => $delivery->event_id, 'X-ATD-Timestamp' => $timestamp, 'X-ATD-Signature' => $signature])
                ->withBody($body, 'application/json')->post($endpoint->url);
            $status = $response->status();
            $error = $status >= 200 && $status < 300 ? null : 'http_error';
        } catch (\Throwable) {
            $error = 'transport_or_policy';
            // No remote bodies, URLs, signed payloads or exception messages in logs.
        }
        $duration = (int) ((hrtime(true) - $started) / 1000000);
        DB::transaction(function () use ($lease, $status, $error, $duration) {
            $delivery = WebhookDelivery::whereKey($this->deliveryId)->lockForUpdate()->firstOrFail();
            if ($delivery->processing_token !== $lease) {
                return;
            }
            $delivery->history()->create(['response_status' => $status, 'error_code' => $error, 'duration_ms' => $duration, 'created_at' => now()]);
            $delivery->response_status = $status;
            $delivery->processing_token = null;
            $delivery->processing_until = null;
            if ($status >= 200 && $status < 300) {
                $delivery->delivered_at = now();
                $delivery->next_attempt_at = null;
            } elseif ($delivery->attempts >= 8) {
                $delivery->failed_at = now();
                $delivery->next_attempt_at = null;
            } else {
                $delivery->next_attempt_at = now()->addMinutes([1, 5, 15, 60, 360, 1440, 1440][min(6, $delivery->attempts - 1)]);
            }
            $delivery->save();
        });
    }
}
