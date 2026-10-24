<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateNewUser;
use App\Jobs\DeliverWebhook;
use App\Models\CustomerAccount;
use App\Models\WebhookEndpoint;
use App\Notifications\PortalActivity;
use App\Services\WebhookManagement;
use App\Services\WebhookUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WebhookOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_test_delivery_lease_and_history(): void
    {
        Notification::fake();
        $this->seed();
        config(['portal.webhook_allowed_hosts' => ['hooks.example.com']]);
        $user = app(CreateNewUser::class)->create(['name' => 'Integrador', 'email' => 'integrador@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $management = app(WebhookManagement::class);
        [$endpoint, $old] = $management->create($user, ['url' => 'https://hooks.example.com/atd', 'events' => ['result.published']]);
        $secret = $management->rotate($user, $endpoint);
        $this->assertNotSame($old, $secret);
        $this->assertStringNotContainsString($old, $endpoint->fresh()->toJson());
        $delivery = $management->test($user, $endpoint->fresh());
        $this->assertNull($delivery->order_id);
        $level = DB::transactionLevel();
        $urls = $this->mock(WebhookUrl::class);
        $urls->shouldReceive('publicAddress')->twice()->andReturn(['hooks.example.com', '203.0.113.1']);
        $count = 0;
        Http::fake(function ($request) use ($level, $delivery, $old, $secret, $urls, &$count) {
            $this->assertSame($level, DB::transactionLevel());
            $timestamp = $request->header('X-ATD-Timestamp')[0];
            $this->assertSame('v1='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $secret).',v1='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $old), $request->header('X-ATD-Signature')[0]);
            (new DeliverWebhook($delivery->id))->handle($urls); // Reentrant worker cannot claim an active lease.

            return Http::response('', ++$count === 1 ? 503 : 204);
        });
        (new DeliverWebhook($delivery->id))->handle($urls);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertSame(1, $delivery->history()->count());
        $delivery->refresh()->update(['attempts' => 8, 'failed_at' => now(), 'next_attempt_at' => null]);
        $payload = $delivery->payload;
        $urls->shouldReceive('host')->once()->andReturn('hooks.example.com');
        $management->replay($user, $delivery);
        $this->assertSame($payload, $delivery->fresh()->payload);
        $this->assertSame(1, $delivery->fresh()->replay_count);
        (new DeliverWebhook($delivery->id))->handle($urls);
        $this->assertNotNull($delivery->fresh()->delivered_at);
        $this->assertSame(2, $delivery->history()->count());
        $this->assertSame(2, $count);
    }

    public function test_another_account_cannot_manage_or_replay_endpoints(): void
    {
        Notification::fake();
        $this->seed();
        $user = app(CreateNewUser::class)->create(['name' => 'Otra cuenta', 'email' => 'otra@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $endpoint = WebhookEndpoint::create(['customer_account_id' => CustomerAccount::create(['name' => 'Ajena', 'billing_email' => 'ajena@example.com'])->id, 'url' => 'https://hooks.example.com/atd', 'secret' => 'hidden']);
        $this->expectException(HttpException::class);
        app(WebhookManagement::class)->rotate($user, $endpoint);
    }

    public function test_exhausted_delivery_notifies_its_client_without_exposing_payload(): void
    {
        Notification::fake();
        $this->seed();
        config(['portal.webhook_allowed_hosts' => ['hooks.example.com']]);
        $user = app(CreateNewUser::class)->create(['name' => 'Avisos API', 'email' => 'hooknotice@example.com', 'password' => 'PasswordSegura123', 'password_confirmation' => 'PasswordSegura123', 'terms' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $management = app(WebhookManagement::class);
        [$endpoint] = $management->create($user, ['url' => 'https://hooks.example.com/atd']);
        $delivery = $management->test($user, $endpoint);
        $delivery->update(['attempts' => 7]);
        $urls = $this->mock(WebhookUrl::class);
        $urls->shouldReceive('publicAddress')->once()->andReturn(['hooks.example.com', '203.0.113.1']);
        Http::fake(['*' => Http::response('', 503)]);
        (new DeliverWebhook($delivery->id))->handle($urls);
        $this->assertNotNull($delivery->fresh()->failed_at);
        Notification::assertSentTo($user, PortalActivity::class, fn ($notice) => $notice->path === '/portal/integrations/deliveries/'.$delivery->id && ! str_contains($notice->copy, 'hooks.example.com'));
    }
}
