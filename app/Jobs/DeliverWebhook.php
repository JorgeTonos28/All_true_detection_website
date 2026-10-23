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
class DeliverWebhook implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=1;
    public int $uniqueFor=120;
    public int $timeout=30;
    public function __construct(public int $deliveryId) {}
    public function uniqueId(): string { return (string) $this->deliveryId; }
    public function handle(WebhookUrl $urls): void
    {
        DB::transaction(function () use ($urls) {
            $delivery=WebhookDelivery::whereKey($this->deliveryId)->lockForUpdate()->firstOrFail();
            if ($delivery->delivered_at || $delivery->failed_at || $delivery->next_attempt_at?->isFuture()) { return; }
            $endpoint=$delivery->endpoint;
            if (! $endpoint->is_active || $endpoint->customer_account_id !== $delivery->order->customer_account_id) {
                $delivery->update(['failed_at'=>now()]); return;
            }
            $delivery->attempts++;
            $status=null;
            try {
                [$host,$address]=$urls->publicAddress($endpoint->url);
                $body=json_encode($delivery->payload,JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $timestamp=(string) time();
                $signature=hash_hmac('sha256',$timestamp.'.'.$body,$endpoint->secret);
                $response=Http::withOptions(['proxy'=>'','allow_redirects'=>false,'curl'=>[CURLOPT_RESOLVE=>[$host.':443:'.$address]]])
                    ->connectTimeout(5)->timeout(15)->withHeaders(['X-ATD-Event-ID'=>$delivery->event_id,'X-ATD-Timestamp'=>$timestamp,'X-ATD-Signature'=>'v1='.$signature])
                    ->withBody($body,'application/json')->post($endpoint->url);
                $status=$response->status();
            } catch (\Throwable) {
                // Never log signed payloads, URLs with query secrets, or network response bodies.
            }
            $delivery->response_status=$status;
            if ($status >= 200 && $status < 300) { $delivery->delivered_at=now(); $delivery->next_attempt_at=null; }
            elseif ($delivery->attempts >= 8) { $delivery->failed_at=now(); $delivery->next_attempt_at=null; }
            else { $delay=[1,5,15,60,360,1440,1440][$delivery->attempts-1]; $delivery->next_attempt_at=now()->addMinutes($delay); }
            $delivery->save();
        });
    }
}
