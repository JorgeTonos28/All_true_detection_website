<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Actions\Orders\CreateOrder;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\OrderEvents;
use App\Services\WebhookUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_delivery_retries_with_same_event_id_without_sensitive_content(): void
    {
        Notification::fake();
        $this->seed();
        $user = app(CreateNewUser::class)->create(['name' => 'Client', 'email' => 'hooks@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $order = app(CreateOrder::class)->handle($user, ['subject' => 'Privado', 'details' => 'Texto sensible del expediente', 'items' => [['service_code' => 'polygraph', 'quantity' => 1]]]);
        $endpoint = WebhookEndpoint::create(['customer_account_id' => $user->customer_account_id, 'url' => 'https://hooks.example.com/atd', 'secret' => 'test-secret']);
        OrderEvents::record($order, 'order.updated');
        $delivery = WebhookDelivery::first();
        $this->assertStringNotContainsString('sensible', json_encode($delivery->payload));
        $this->assertNotSame('test-secret', $endpoint->getRawOriginal('secret'));
        $urls = $this->mock(WebhookUrl::class);
        $urls->shouldReceive('publicAddress')->twice()->andReturn(['hooks.example.com', '203.0.113.1']);
        Http::fakeSequence()->push('', 503)->push('', 204);
        (new DeliverWebhook($delivery->id))->handle($urls);
        $this->assertNull($delivery->fresh()->delivered_at);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->travel(2)->minutes();
        (new DeliverWebhook($delivery->id))->handle($urls);
        $this->assertNotNull($delivery->fresh()->delivered_at);
        Http::assertSent(function (Request $request) use ($delivery) {
            $timestamp = $request->header('X-ATD-Timestamp')[0];

            return $request->header('X-ATD-Event-ID')[0] === $delivery->event_id && hash_equals('v1='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'test-secret'), $request->header('X-ATD-Signature')[0]);
        });
    }

    public function test_webhook_urls_reject_unapproved_hosts_and_unsafe_url_forms(): void
    {
        config(['portal.webhook_allowed_hosts' => ['hooks.example.com']]);
        $urls = new WebhookUrl;
        $this->assertSame('hooks.example.com', $urls->host('https://hooks.example.com/atd'));
        foreach (['http://hooks.example.com', 'https://127.0.0.1/a', 'https://hooks.example.com:444/a', 'https://user@hooks.example.com/a', 'https://other.example.com/a'] as $url) {
            try {
                $urls->host($url);
                $this->fail($url);
            } catch (HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }
    }
}
